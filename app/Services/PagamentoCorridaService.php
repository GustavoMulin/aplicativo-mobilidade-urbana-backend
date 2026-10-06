<?php

namespace App\Services;

use App\Events\CorridaAtualizada;
use App\Events\CorridasDisponiveisAlteradas;
use App\Exceptions\AbacatePaySemRespostaException;
use App\Exceptions\PagamentoPendenteException;
use App\Models\CobrancaCartao;
use App\Models\CobrancaPix;
use App\Models\Corrida;
use App\Models\CorridaFinanceiro;
use App\Models\MovimentoCredito;
use App\Models\Passageiro;
use App\Support\Avisar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Regras de pagamento da corrida (modelo pré-pago, como 99 e Uber):
 * - Pix e cartão são pagos ANTES: a corrida fica em "aguardando_pagamento" e
 *   só entra na busca de motoristas quando o pagamento é confirmado.
 * - Dinheiro é pago ao motorista no fim, como antes.
 * - Ao terminar (finalizada ou cancelada), a corrida é liquidada: o que faltar
 *   vira pendência que bloqueia o próximo pedido; o que sobrar vira crédito no
 *   app, ou estorno integral quando a corrida foi cancelada sem taxa.
 */
class PagamentoCorridaService
{
    public const PRE_PAGOS = ['pix', 'cartao'];

    private const TOLERANCIA = 0.009;

    public function __construct(
        private readonly AbacatePayClient $abacatePay,
        private readonly NotificarUsuarioService $notificarUsuario,
    ) {}

    public function ehPrePago(?string $metodo): bool
    {
        return in_array($metodo, self::PRE_PAGOS, true);
    }

    /**
     * Quanto o passageiro deve pela corrida no estado atual.
     */
    public function valorCobrado(Corrida $corrida): float
    {
        $financeiro = $this->financeiro($corrida);

        if ($corrida->status_corrida === 'cancelada') {
            $comTaxa = in_array($corrida->tipo_cancelamento, ['cancelamento_com_taxa', 'nao_comparecimento'], true);

            return $comTaxa ? round((float) ($financeiro->taxa_cancelamento ?? 0), 2) : 0.0;
        }

        return round((float) ($financeiro->valor_pago_passageiro ?? 0), 2);
    }

    /**
     * Quanto já entrou: cobranças pagas e não estornadas, mais o crédito usado
     * nesta corrida, menos o crédito já devolvido por ela. Contar o crédito
     * devolvido aqui torna a liquidação idempotente: liquidar de novo não
     * credita a mesma sobra duas vezes.
     */
    public function valorPago(Corrida $corrida): float
    {
        $pix = (int) CobrancaPix::where('corrida_id', $corrida->id)
            ->where('status', 'PAID')
            ->whereNull('estornado_em')
            ->sum('valor_centavos');
        $cartao = (int) CobrancaCartao::where('corrida_id', $corrida->id)
            ->where('status', 'PAID')
            ->whereNull('estornado_em')
            ->sum('valor_centavos');
        $creditoLiquido = -(float) MovimentoCredito::where('corrida_id', $corrida->id)->sum('valor');

        return round(($pix + $cartao) / 100 + $creditoLiquido, 2);
    }

    public function valorDevido(Corrida $corrida): float
    {
        return round(max(0.0, $this->valorCobrado($corrida) - $this->valorPago($corrida)), 2);
    }

    public function saldoCredito(int $passageiroId): float
    {
        return round((float) MovimentoCredito::where('passageiro_id', $passageiroId)->sum('valor'), 2);
    }

    /**
     * Abate crédito do passageiro no valor da corrida. Chamar dentro da
     * transação que cria a corrida.
     */
    public function aplicarCredito(Corrida $corrida, float $valor): float
    {
        // trava o passageiro: dois pedidos simultâneos não gastam o mesmo saldo
        Passageiro::whereKey($corrida->passageiro_id)->lockForUpdate()->first();

        $uso = round(min($this->saldoCredito((int) $corrida->passageiro_id), $valor), 2);

        if ($uso <= self::TOLERANCIA) {
            return 0.0;
        }

        MovimentoCredito::create([
            'passageiro_id' => $corrida->passageiro_id,
            'corrida_id' => $corrida->id,
            'valor' => -$uso,
            'descricao' => "Usado na corrida {$corrida->codigo_corrida}",
        ]);
        CorridaFinanceiro::where('corrida_id', $corrida->id)->update(['credito_aplicado' => $uso]);

        return $uso;
    }

    /**
     * Valor em aberto de corrida anterior impede pedir outra.
     */
    public function exigirSemPendencia(int $passageiroId): void
    {
        $pendente = $this->pendencia($passageiroId);

        if ($pendente !== null) {
            throw new PagamentoPendenteException($pendente->id, $pendente->codigo_corrida, $this->valorDevido($pendente));
        }
    }

    public function pendencia(int $passageiroId): ?Corrida
    {
        return Corrida::where('passageiro_id', $passageiroId)
            ->where('status_pagamento', 'em_aberto')
            ->orderBy('id')
            ->first();
    }

    /**
     * Chamado quando uma cobrança passa a PAID. Libera a corrida para a busca
     * de motoristas ou quita a pendência.
     */
    public function aoConfirmarPagamento(int $corridaId): void
    {
        $liberada = DB::transaction(function () use ($corridaId) {
            $corrida = Corrida::whereKey($corridaId)->lockForUpdate()->first();

            if ($corrida === null || $this->valorDevido($corrida) > self::TOLERANCIA) {
                return null;
            }

            if ($corrida->status_corrida === 'aguardando_pagamento') {
                // o raio de busca cresce a partir de tempo_solicitacao: conta do pagamento
                $corrida->update([
                    'status_corrida' => 'solicitada',
                    'status_pagamento' => 'pago',
                    'tempo_solicitacao' => now(),
                ]);

                return $corrida;
            }

            if ($corrida->status_pagamento === 'em_aberto') {
                $corrida->update(['status_pagamento' => 'pago']);
                $this->avisarMotoristaRecebimento($corrida);
            }

            return null;
        });

        if ($liberada !== null) {
            Avisar::semQuebrar(new CorridasDisponiveisAlteradas);
            Avisar::semQuebrar(new CorridaAtualizada($liberada->id, 'solicitada'));

            return;
        }

        // pagamento que chegou depois de a corrida ser cancelada: devolve
        $corrida = Corrida::find($corridaId);
        if ($corrida !== null && in_array($corrida->status_corrida, ['cancelada', 'finalizada'], true)) {
            $this->liquidar($corrida);
        }
    }

    /**
     * Fecha as contas de uma corrida finalizada ou cancelada.
     */
    public function liquidar(Corrida $corrida): void
    {
        // trava a corrida: agendador, webhook e consulta do app podem liquidar
        // ao mesmo tempo, e cada um estornaria ou creditaria de novo
        DB::transaction(function () use ($corrida) {
            $travada = Corrida::whereKey($corrida->id)->lockForUpdate()->first();

            if ($travada !== null) {
                $this->liquidarTravada($travada);
            }
        });
    }

    private function liquidarTravada(Corrida $corrida): void
    {
        if (! in_array($corrida->status_corrida, ['finalizada', 'cancelada'], true)) {
            return;
        }

        $cobrado = $this->valorCobrado($corrida);
        $pago = $this->valorPago($corrida);
        $saldo = round($cobrado - $pago, 2);

        // dinheiro sem nada pago pelo app: o motorista recebeu em mãos
        if ($corrida->status_corrida === 'finalizada' && $pago <= self::TOLERANCIA && ! $this->ehPrePago($corrida->metodo_pagamento)) {
            $corrida->update(['status_pagamento' => 'pago']);

            return;
        }

        if ($saldo > self::TOLERANCIA) {
            if ($corrida->status_pagamento !== 'em_aberto') {
                $corrida->update(['status_pagamento' => 'em_aberto']);
                $this->avisarPendencia($corrida, $saldo);
            }

            return;
        }

        if ($saldo < -self::TOLERANCIA) {
            if ($cobrado <= self::TOLERANCIA) {
                // estorno sem resposta da AbacatePay fica pendente e o agendador tenta de novo
                $concluido = $this->estornarTudo($corrida);
                $corrida->update(['status_pagamento' => $concluido ? 'estornado' : 'estorno_pendente']);

                return;
            }

            $this->creditar($corrida, -$saldo, "Sobra da corrida {$corrida->codigo_corrida}");
        }

        // corrida já estornada continua estornada se for liquidada de novo
        if ($cobrado <= self::TOLERANCIA && $corrida->status_pagamento === 'estornado') {
            return;
        }

        $corrida->update(['status_pagamento' => $cobrado <= self::TOLERANCIA ? 'sem_cobranca' : 'pago']);
    }

    public function liquidarSemQuebrar(Corrida $corrida): void
    {
        try {
            $this->liquidar($corrida);
        } catch (Throwable $erro) {
            Log::warning('Não foi possível liquidar o pagamento da corrida.', [
                'corrida_id' => $corrida->id,
                'erro' => $erro->getMessage(),
            ]);
        }
    }

    /**
     * Cancela pedidos pré-pagos cujo pagamento não chegou no prazo. Antes de
     * cancelar, reconsulta a cobrança: se foi paga, libera a corrida.
     */
    public function expirarPagamentosVencidos(): int
    {
        $limite = now()->subSeconds((int) config('abacatepay.validade_segundos', 900) + 60);
        $ids = Corrida::where('status_corrida', 'aguardando_pagamento')
            ->where('tempo_solicitacao', '<=', $limite)
            ->pluck('id');
        $canceladas = 0;

        foreach ($ids as $corridaId) {
            if ($this->pagamentoConfirmadoNaAbacatePay((int) $corridaId)) {
                $this->aoConfirmarPagamento((int) $corridaId);

                // pago só em parte continua aguardando: cancela e devolve o que entrou
                if (Corrida::whereKey($corridaId)->value('status_corrida') !== 'aguardando_pagamento') {
                    continue;
                }
            }

            $corrida = DB::transaction(function () use ($corridaId) {
                $corrida = Corrida::whereKey($corridaId)->lockForUpdate()->first();

                if ($corrida === null || $corrida->status_corrida !== 'aguardando_pagamento') {
                    return null;
                }

                $corrida->update([
                    'status_corrida' => 'cancelada',
                    'cancelado_por' => 'sistema',
                    'motivo_cancelamento' => 'Pagamento não confirmado a tempo.',
                ]);

                return $corrida;
            });

            if ($corrida !== null) {
                $canceladas++;
                Avisar::semQuebrar(new CorridaAtualizada($corrida->id, 'cancelada'));
                $this->liquidarSemQuebrar($corrida);
            }
        }

        return $canceladas;
    }

    /**
     * Tenta de novo os estornos que ficaram sem resposta da AbacatePay.
     */
    public function reprocessarEstornosPendentes(): int
    {
        // rodízio pelo updated_at: um estorno que falha sempre não impede os outros
        $corridas = Corrida::where('status_pagamento', 'estorno_pendente')->orderBy('updated_at')->orderBy('id')->limit(50)->get();

        foreach ($corridas as $corrida) {
            $corrida->touch();
            $this->liquidarSemQuebrar($corrida);
        }

        return $corridas->count();
    }

    /**
     * Confere todas as cobranças abertas do pedido, não só a última: um Pix
     * antigo ainda pode ter sido pago.
     */
    private function pagamentoConfirmadoNaAbacatePay(int $corridaId): bool
    {
        $pixes = CobrancaPix::where('corrida_id', $corridaId)
            ->whereIn('status', ['PENDING', 'PAID'])
            ->whereNull('estornado_em')
            ->get();

        foreach ($pixes as $pix) {
            if ($this->pagaOuSincronizada($pix, fn () => app(CobrancaPixService::class)->sincronizar($pix, false)->status)) {
                return true;
            }
        }

        $cartoes = CobrancaCartao::where('corrida_id', $corridaId)
            ->whereIn('status', ['PENDING', 'PAID'])
            ->whereNull('estornado_em')
            ->get();

        foreach ($cartoes as $cartao) {
            if ($this->pagaOuSincronizada($cartao, fn () => app(CobrancaCartaoService::class)->sincronizar($cartao, false)->status)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  callable(): string  $sincronizar
     */
    private function pagaOuSincronizada(CobrancaPix|CobrancaCartao $cobranca, callable $sincronizar): bool
    {
        if ($cobranca->status === 'PAID') {
            return true;
        }

        try {
            return $sincronizar() === 'PAID';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return bool false quando algum estorno ficou sem resposta e precisa ser refeito
     */
    private function estornarTudo(Corrida $corrida): bool
    {
        $concluido = true;

        foreach (CobrancaPix::where('corrida_id', $corrida->id)->where('status', 'PAID')->whereNull('estornado_em')->get() as $pix) {
            $concluido = $this->estornar($corrida, $pix->valor_centavos, fn () => $this->abacatePay->estornarPix($pix->charge_id), $pix) && $concluido;
        }

        foreach (CobrancaCartao::where('corrida_id', $corrida->id)->where('status', 'PAID')->whereNull('estornado_em')->get() as $cartao) {
            $concluido = $this->estornar($corrida, $cartao->valor_centavos, fn () => $this->abacatePay->estornarCheckout($cartao->checkout_id), $cartao) && $concluido;
        }

        // crédito usado na corrida volta para o saldo (o extrato já fica zerado nela)
        $creditoUsado = round(-(float) MovimentoCredito::where('corrida_id', $corrida->id)->sum('valor'), 2);

        if ($creditoUsado > self::TOLERANCIA) {
            $this->creditar($corrida, $creditoUsado, "Crédito devolvido da corrida {$corrida->codigo_corrida}");
        }

        return $concluido;
    }

    /**
     * Estorno integral na AbacatePay. Só vira crédito no app quando a
     * AbacatePay recusou o estorno E confirma que a cobrança continua paga;
     * sem resposta, nada é marcado e o estorno é refeito depois, porque ele
     * pode ter saído e o passageiro receberia em dobro.
     *
     * @param  callable(): void  $estornarNaApi
     * @return bool false quando o resultado ficou desconhecido
     */
    private function estornar(Corrida $corrida, int $centavos, callable $estornarNaApi, CobrancaPix|CobrancaCartao $cobranca): bool
    {
        try {
            $estornarNaApi();
            $cobranca->update(['estornado_em' => now()]);

            return true;
        } catch (AbacatePaySemRespostaException $erro) {
            Log::warning('Estorno na AbacatePay sem resposta; será refeito.', [
                'corrida_id' => $corrida->id,
                'erro' => $erro->getMessage(),
            ]);

            return false;
        } catch (RuntimeException $erro) {
            $situacao = $this->situacaoNaAbacatePay($cobranca);

            // recusado porque já estava estornada (ex.: outra liquidação chegou antes)
            if ($situacao === 'REFUNDED') {
                $cobranca->update(['estornado_em' => now()]);

                return true;
            }

            if ($situacao !== 'PAID') {
                Log::warning('Estorno na AbacatePay falhou e a cobrança não pôde ser conferida; será refeito.', [
                    'corrida_id' => $corrida->id,
                    'erro' => $erro->getMessage(),
                ]);

                return false;
            }

            Log::warning('Estorno na AbacatePay recusado; valor convertido em crédito.', [
                'corrida_id' => $corrida->id,
                'erro' => $erro->getMessage(),
            ]);
            $cobranca->update(['estornado_em' => now()]);

            // sem corrida_id: o dinheiro desta cobrança já sai do valor pago ao
            // marcá-la estornada; contar o crédito também geraria dívida falsa
            MovimentoCredito::create([
                'passageiro_id' => $corrida->passageiro_id,
                'corrida_id' => null,
                'valor' => round($centavos / 100, 2),
                'descricao' => "Estorno da corrida {$corrida->codigo_corrida} em crédito",
            ]);

            return true;
        }
    }

    private function situacaoNaAbacatePay(CobrancaPix|CobrancaCartao $cobranca): ?string
    {
        try {
            $dados = $cobranca instanceof CobrancaPix
                ? $this->abacatePay->get('/v2/transparents/check', ['id' => $cobranca->charge_id])
                : $this->abacatePay->get('/v2/checkouts/get', ['id' => $cobranca->checkout_id]);

            return is_string($dados['status'] ?? null) ? $dados['status'] : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    private function creditar(Corrida $corrida, float $valor, string $descricao): void
    {
        MovimentoCredito::create([
            'passageiro_id' => $corrida->passageiro_id,
            'corrida_id' => $corrida->id,
            'valor' => round($valor, 2),
            'descricao' => $descricao,
        ]);
    }

    private function avisarPendencia(Corrida $corrida, float $valor): void
    {
        $userId = $corrida->passageiro()->value('user_id');

        if ($userId !== null) {
            $texto = number_format($valor, 2, ',', '.');
            $this->notificarUsuario->executar(
                (int) $userId,
                'Pagamento pendente',
                "Falta pagar R$ {$texto} da corrida {$corrida->codigo_corrida}. Pague para pedir uma nova corrida."
            );
        }
    }

    private function avisarMotoristaRecebimento(Corrida $corrida): void
    {
        $userId = $corrida->motorista()->value('user_id');

        if ($userId !== null) {
            $this->notificarUsuario->executar(
                (int) $userId,
                'Pagamento recebido',
                "O passageiro quitou o valor pendente da corrida {$corrida->codigo_corrida}."
            );
        }
    }

    private function financeiro(Corrida $corrida): ?CorridaFinanceiro
    {
        return CorridaFinanceiro::where('corrida_id', $corrida->id)->first();
    }
}
