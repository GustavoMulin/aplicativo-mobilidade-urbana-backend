<?php

namespace App\Services;

use App\Events\CorridaAtualizada;
use App\Events\CorridasDisponiveisAlteradas;
use App\Models\Corrida;
use App\Models\CorridaAlteracaoDestino;
use App\Models\CorridaDestino;
use App\Models\CorridaFinanceiro;
use App\Models\Motorista;
use App\Models\Tarifa;
use App\Support\Avisar;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mudanças que o passageiro pede com a corrida já pedida, nas regras do 99:
 * - pagamento: uma troca por corrida; durante a viagem só entre Pix e cartão
 *   (trocar de/para dinheiro no meio do trajeto muda como o motorista recebe);
 * - destino: na busca vale na hora; depois do aceite vira um pedido que o
 *   motorista aceita ou recusa, com o preço recalculado; em categorias de
 *   preço negociado o destino não muda depois do aceite.
 */
class AlterarCorridaService
{
    public const PRAZO_RESPOSTA_SEGUNDOS = 120;

    private const STATUS_ALTERAVEIS = ['solicitada', 'em_busca', 'aceita', 'motorista_chegou', 'em_andamento'];

    private const PAGAMENTOS_DIGITAIS = ['pix', 'cartao'];

    public function __construct(
        protected EstimarRotaService $estimarRotaService,
        protected CalcularPrecoCorridaService $calcularPrecoCorridaService,
    ) {}

    public function pagamentoAlteravel(Corrida $corrida): bool
    {
        return $corrida->pagamento_alterado_em === null
            && in_array($corrida->status_corrida, self::STATUS_ALTERAVEIS, true);
    }

    public function destinoAlteravel(Corrida $corrida): bool
    {
        if (! in_array($corrida->status_corrida, self::STATUS_ALTERAVEIS, true)) {
            return false;
        }

        return in_array($corrida->status_corrida, ['solicitada', 'em_busca'], true)
            || ! $this->precoNegociado($corrida);
    }

    public function alterarPagamento(int $passageiroId, int $corridaId, string $metodo): Corrida
    {
        return DB::transaction(function () use ($passageiroId, $corridaId, $metodo) {
            $corrida = $this->corridaDoPassageiro($passageiroId, $corridaId);

            if (! in_array($corrida->status_corrida, self::STATUS_ALTERAVEIS, true)) {
                throw new RuntimeException('A corrida já terminou e o pagamento não pode mais ser trocado.', 409);
            }

            if ($corrida->pagamento_alterado_em !== null) {
                throw new RuntimeException('O pagamento só pode ser trocado uma vez por corrida.', 409);
            }

            $atual = $corrida->metodo_pagamento;

            if ($atual === $metodo) {
                throw new RuntimeException('Esta já é a forma de pagamento da corrida.', 422);
            }

            $entreDigitais = in_array($atual, self::PAGAMENTOS_DIGITAIS, true)
                && in_array($metodo, self::PAGAMENTOS_DIGITAIS, true);

            if ($corrida->status_corrida === 'em_andamento' && ! $entreDigitais) {
                throw new RuntimeException('Durante a viagem só dá para trocar entre Pix e cartão.', 409);
            }

            // o que já entrou pelo app não volta: em dinheiro o passageiro pagaria duas vezes
            if (! in_array($metodo, self::PAGAMENTOS_DIGITAIS, true) && app(PagamentoCorridaService::class)->valorPago($corrida) > 0.009) {
                throw new RuntimeException('Esta corrida já foi paga pelo app e não pode passar para dinheiro.', 409);
            }

            $corrida->update([
                'metodo_pagamento' => $metodo,
                'pagamento_alterado_em' => now(),
            ]);
            CorridaFinanceiro::where('corrida_id', $corrida->id)->update(['metodo_pagamento' => $metodo]);

            Avisar::semQuebrar(new CorridaAtualizada($corrida->id, $corrida->status_corrida));

            return $corrida->fresh(['corrida_destinos', 'corrida_financeiro']);
        });
    }

    /**
     * @param  array{tipo?: 'destino'|'parada', endereco: string, latitude: float, longitude: float, itinerario?: array<int, array{endereco: string, latitude: float, longitude: float}>|null}  $destino
     */
    public function pedirNovoDestino(int $passageiroId, int $corridaId, array $destino): Corrida
    {
        return DB::transaction(function () use ($passageiroId, $corridaId, $destino) {
            $corrida = $this->corridaDoPassageiro($passageiroId, $corridaId);

            if (! in_array($corrida->status_corrida, self::STATUS_ALTERAVEIS, true)) {
                throw new RuntimeException('A corrida já terminou e o trajeto não pode mais ser alterado.', 409);
            }

            $semMotorista = in_array($corrida->status_corrida, ['solicitada', 'em_busca'], true);

            if (! $semMotorista && $this->precoNegociado($corrida)) {
                throw new RuntimeException(
                    'Em corridas com preço negociado o trajeto não pode ser alterado. Cancele e peça uma nova corrida.',
                    409
                );
            }

            $tipo = $destino['tipo'] ?? 'destino';
            $itinerario = $destino['itinerario'] ?? null;

            if ($itinerario !== null) {
                $origem = $corrida->corrida_destinos()->where('tipo', 'origem')->first();

                if ($origem === null) {
                    throw new RuntimeException('O local de embarque da corrida não foi encontrado.', 409);
                }

                // O ponto de embarque não muda nesta edição. Ele apenas abre
                // o itinerário já existente para alterar paradas e destino.
                $itinerario[0] = [
                    'endereco' => $origem->endereco,
                    'latitude' => (float) $origem->latitude,
                    'longitude' => (float) $origem->longitude,
                ];
            }

            $orcamento = $this->orcar($corrida, $destino, $tipo, $itinerario);
            $financeiro = $corrida->corrida_financeiro()->first();

            $corrida->alteracoesDestino()->where('status', 'pendente')->update([
                'status' => 'substituida',
                'respondida_em' => now(),
            ]);

            $alteracao = $corrida->alteracoesDestino()->create([
                'status' => 'pendente',
                'tipo' => $tipo,
                'endereco' => $destino['endereco'],
                'latitude' => $destino['latitude'],
                'longitude' => $destino['longitude'],
                'itinerario' => $itinerario,
                'distancia_km' => $orcamento['distancia_km'],
                'tempo_min' => $orcamento['tempo_min'],
                'valor_passageiro' => $orcamento['valor_pago_passageiro'],
                'valor_motorista' => $orcamento['valor_motorista'],
                'valor_passageiro_anterior' => $financeiro?->valor_pago_passageiro,
                'valor_motorista_anterior' => $financeiro?->valor_motorista,
            ]);

            if ($semMotorista) {
                $this->aplicar($corrida, $alteracao, $orcamento);
                $alteracao->update(['status' => 'aplicada', 'respondida_em' => now()]);
                Avisar::semQuebrar(new CorridasDisponiveisAlteradas);
            }

            Avisar::semQuebrar(new CorridaAtualizada($corrida->id, $corrida->status_corrida));

            return $corrida->fresh(['corrida_destinos', 'corrida_financeiro']);
        });
    }

    public function desistirDoNovoDestino(int $passageiroId, int $corridaId): Corrida
    {
        return DB::transaction(function () use ($passageiroId, $corridaId) {
            $corrida = $this->corridaDoPassageiro($passageiroId, $corridaId);
            $pendente = $this->pendente($corrida);

            if ($pendente === null) {
                throw new RuntimeException('Não há troca de destino esperando resposta.', 409);
            }

            $pendente->update(['status' => 'cancelada', 'respondida_em' => now()]);

            Avisar::semQuebrar(new CorridaAtualizada($corrida->id, $corrida->status_corrida));

            return $corrida->fresh(['corrida_destinos', 'corrida_financeiro']);
        });
    }

    public function responderNovoDestino(Motorista $motorista, int $corridaId, int $alteracaoId, bool $aceitar): Corrida
    {
        // fora da transação: a recusa por prazo vencido desfaria a marcação
        $this->expirarVencidos($corridaId);

        return DB::transaction(function () use ($motorista, $corridaId, $alteracaoId, $aceitar) {
            $corrida = Corrida::whereKey($corridaId)->lockForUpdate()->first();

            if ($corrida === null || $corrida->motorista_id !== $motorista->id) {
                throw new RuntimeException('Corrida não encontrada.', 404);
            }

            $pendente = $this->pendente($corrida);

            if ($pendente === null || $pendente->id !== $alteracaoId) {
                throw new RuntimeException('Este pedido de troca de destino não está mais valendo.', 409);
            }

            if ($aceitar) {
                // recalcula na hora do aceite: a espera pode ter mudado o total
                $orcamento = $this->orcar($corrida, [
                    'endereco' => $pendente->endereco,
                    'latitude' => $pendente->latitude,
                    'longitude' => $pendente->longitude,
                ], $pendente->tipo === 'parada' ? 'parada' : 'destino', $pendente->itinerario);
                $this->aplicar($corrida, $pendente, $orcamento);
            }

            $pendente->update([
                'status' => $aceitar ? 'aceita' : 'recusada',
                'respondida_em' => now(),
            ]);

            Avisar::semQuebrar(new CorridaAtualizada($corrida->id, $corrida->status_corrida));

            return $corrida->fresh(['corrida_destinos', 'corrida_financeiro']);
        });
    }

    /**
     * Pedido ainda esperando o motorista; o que passou do prazo vira
     * "expirada" aqui mesmo, na leitura.
     */
    public function pendente(Corrida $corrida): ?CorridaAlteracaoDestino
    {
        $this->expirarVencidos($corrida->id);

        return $corrida->alteracoesDestino()
            ->where('status', 'pendente')
            ->latest('id')
            ->first();
    }

    private function expirarVencidos(int $corridaId): void
    {
        CorridaAlteracaoDestino::where('corrida_id', $corridaId)
            ->where('status', 'pendente')
            ->where('created_at', '<=', now()->subSeconds(self::PRAZO_RESPOSTA_SEGUNDOS))
            ->update(['status' => 'expirada', 'respondida_em' => now()]);
    }

    /**
     * O último pedido da corrida (pendente ou já respondido), para os dois
     * lados mostrarem o andamento.
     *
     * @return array<string, mixed>|null
     */
    public function resumo(Corrida $corrida): ?array
    {
        $this->pendente($corrida);

        $ultima = $corrida->alteracoesDestino()->latest('id')->first();

        if ($ultima === null) {
            return null;
        }

        return [
            'id' => $ultima->id,
            'status' => $ultima->status,
            'tipo' => $ultima->tipo,
            // trajeto inteiro pedido (sem o embarque), quando o passageiro editou as paradas
            'paradas' => $ultima->itinerario === null ? null : array_column(array_slice($ultima->itinerario, 1), 'endereco'),
            'endereco' => $ultima->endereco,
            'latitude' => $ultima->latitude,
            'longitude' => $ultima->longitude,
            'distancia_km' => $ultima->distancia_km,
            'tempo_min' => $ultima->tempo_min,
            'valor_passageiro' => $ultima->valor_passageiro,
            'valor_motorista' => $ultima->valor_motorista,
            'valor_passageiro_anterior' => $ultima->valor_passageiro_anterior,
            'valor_motorista_anterior' => $ultima->valor_motorista_anterior,
            'expira_em' => $ultima->status === 'pendente'
                ? $ultima->created_at?->copy()->addSeconds(self::PRAZO_RESPOSTA_SEGUNDOS)->toIso8601String()
                : null,
            'respondida_em' => $ultima->respondida_em?->toIso8601String(),
        ];
    }

    private function corridaDoPassageiro(int $passageiroId, int $corridaId): Corrida
    {
        $corrida = Corrida::whereKey($corridaId)->lockForUpdate()->first();

        if ($corrida === null || $corrida->passageiro_id !== $passageiroId) {
            throw new RuntimeException('Corrida não encontrada.', 404);
        }

        return $corrida;
    }

    private function precoNegociado(Corrida $corrida): bool
    {
        return $corrida->produto()->value('estrategia_precificacao') === 'negociada';
    }

    /**
     * Preço da viagem inteira com o novo destino (origem, paradas e o novo
     * ponto final), mantendo a taxa de espera já contada.
     *
     * @param  array{endereco: string, latitude: float, longitude: float}  $destino
     * @param  'destino'|'parada'  $tipo
     * @param  array<int, array{endereco: string, latitude: float, longitude: float}>|null  $itinerario
     * @return array{distancia_km: float, tempo_min: float, valor_motorista: float, valor_pago_passageiro: float, taxa_plataforma: float, composicao: array<string, mixed>}
     */
    private function orcar(Corrida $corrida, array $destino, string $tipo, ?array $itinerario = null): array
    {
        $tarifa = $corrida->tarifa_id === null ? null : Tarifa::find($corrida->tarifa_id);

        if ($tarifa === null) {
            throw new RuntimeException('A tarifa desta corrida não está disponível para recalcular o preço.', 409);
        }

        $pontosAtuais = $corrida->corrida_destinos()
            ->whereIn('tipo', ['origem', 'parada'])
            ->orderBy('ordem')
            ->get()
            ->values()
            ->map(fn (CorridaDestino $ponto, int $indice) => [
                'order' => $indice,
                'latitude' => (float) $ponto->latitude,
                'longitude' => (float) $ponto->longitude,
                'formattedAddress' => $ponto->endereco,
            ])
            ->all();

        if ($itinerario !== null) {
            $pontos = array_map(
                fn (array $ponto, int $indice): array => [
                    'order' => $indice,
                    'latitude' => (float) $ponto['latitude'],
                    'longitude' => (float) $ponto['longitude'],
                    'formattedAddress' => $ponto['endereco'],
                ],
                $itinerario,
                array_keys($itinerario),
            );
        } else {
            $pontos = $pontosAtuais;
        }

        if ($itinerario === null) {
            $pontos[] = [
                'order' => count($pontos),
                'latitude' => (float) $destino['latitude'],
                'longitude' => (float) $destino['longitude'],
                'formattedAddress' => $destino['endereco'],
            ];
        }

        if ($tipo === 'parada') {
            $destinoAtual = $corrida->corrida_destinos()->where('tipo', 'destino')->first();

            if ($destinoAtual === null) {
                throw new RuntimeException('O destino atual da corrida não foi encontrado.', 409);
            }

            $pontos[] = [
                'order' => count($pontos),
                'latitude' => (float) $destinoAtual->latitude,
                'longitude' => (float) $destinoAtual->longitude,
                'formattedAddress' => $destinoAtual->endereco,
            ];
        }

        $rota = $this->estimarRotaService->executar(enderecos: $pontos);
        $distanciaKm = (float) ($rota['distancia_km'] ?? 0);
        $tempoMin = (float) ($rota['tempo_minutos'] ?? 0);

        if ($distanciaKm <= 0) {
            throw new RuntimeException('Não foi possível calcular a rota até o novo destino.', 422);
        }

        $preco = $this->calcularPrecoCorridaService->executar($tarifa, $distanciaKm, $tempoMin);
        $financeiro = $corrida->corrida_financeiro()->first();

        $esperaMotoristaCentavos = (int) round((float) ($financeiro->taxa_espera ?? 0) * 100);
        $percentual = min(max((float) ($preco['valores']['taxa_plataforma_percentual'] ?? 0), 0), 95);
        $esperaPassageiroCentavos = $percentual >= 95
            ? $esperaMotoristaCentavos
            : (int) round($esperaMotoristaCentavos / (1 - $percentual / 100));

        $motoristaCentavos = (int) round($preco['valores']['valor_motorista'] * 100) + $esperaMotoristaCentavos;
        $passageiroCentavos = (int) round($preco['valores']['valor_passageiro'] * 100) + $esperaPassageiroCentavos;

        return [
            'distancia_km' => round($distanciaKm, 2),
            'tempo_min' => round($tempoMin, 2),
            'valor_motorista' => $motoristaCentavos / 100,
            'valor_pago_passageiro' => $passageiroCentavos / 100,
            'taxa_plataforma' => ($passageiroCentavos - $motoristaCentavos) / 100,
            'composicao' => $preco['composicao'] + ['espera_motorista' => $esperaMotoristaCentavos / 100],
        ];
    }

    /**
     * @param  array{distancia_km: float, tempo_min: float, valor_motorista: float, valor_pago_passageiro: float, taxa_plataforma: float, composicao: array<string, mixed>}  $orcamento
     */
    private function aplicar(Corrida $corrida, CorridaAlteracaoDestino $alteracao, array $orcamento): void
    {
        if ($alteracao->itinerario !== null) {
            $this->aplicarItinerario($corrida, $alteracao->itinerario);
        } elseif ($alteracao->tipo === 'parada') {
            $destinoAtual = $corrida->corrida_destinos()
                ->where('tipo', 'destino')
                ->lockForUpdate()
                ->first();

            if ($destinoAtual === null) {
                throw new RuntimeException('O destino atual da corrida não foi encontrado.', 409);
            }

            $ordem = (int) $destinoAtual->ordem;
            $corrida->corrida_destinos()->where('ordem', '>=', $ordem)->increment('ordem');
            $corrida->corrida_destinos()->create([
                'nome_local' => $alteracao->endereco,
                'tipo' => 'parada',
                'ordem' => $ordem,
                'endereco' => $alteracao->endereco,
                'latitude' => $alteracao->latitude,
                'longitude' => $alteracao->longitude,
            ]);
        } else {
            $corrida->corrida_destinos()->where('tipo', 'destino')->update([
                'nome_local' => $alteracao->endereco,
                'endereco' => $alteracao->endereco,
                'latitude' => $alteracao->latitude,
                'longitude' => $alteracao->longitude,
            ]);
        }

        $subtotal = (float) $orcamento['composicao']['subtotal'] + (float) $orcamento['composicao']['espera_motorista'];

        $corrida->corrida_financeiro()->update([
            'valor_bruto' => $subtotal,
            'tarifa_base' => $orcamento['composicao']['tarifa_base'],
            'valor_por_km' => $orcamento['composicao']['valor_distancia'],
            'valor_por_minuto' => $orcamento['composicao']['valor_tempo'],
            'valor_sem_dinamica' => $subtotal,
            'valor_base_calculado' => $subtotal,
            'valor_pago_passageiro' => $orcamento['valor_pago_passageiro'],
            'taxa_plataforma_valor' => $orcamento['taxa_plataforma'],
            'valor_motorista' => $orcamento['valor_motorista'],
            'valor_liquido_motorista' => $orcamento['valor_motorista'],
            'valor_repassado_plataforma' => $orcamento['taxa_plataforma'],
        ]);

        $corrida->update(['distancia_total' => $orcamento['distancia_km']]);
    }

    /**
     * @param  array<int, array{endereco: string, latitude: float, longitude: float}>  $itinerario
     */
    private function aplicarItinerario(Corrida $corrida, array $itinerario): void
    {
        $origem = $corrida->corrida_destinos()->where('tipo', 'origem')->lockForUpdate()->first();

        if ($origem === null) {
            throw new RuntimeException('O local de embarque da corrida não foi encontrado.', 409);
        }

        if (! isset($itinerario[0])) {
            throw new RuntimeException('Informe ao menos origem e destino para atualizar o trajeto.', 422);
        }

        // parada já feita continua feita: senão o motorista teria de
        // confirmá-la de novo e a rota do passageiro voltaria a ela
        $chave = fn (float|string $latitude, float|string $longitude): string => sprintf('%.5f,%.5f', (float) $latitude, (float) $longitude);
        $concluidas = $corrida->corrida_destinos()
            ->where('tipo', 'parada')
            ->whereNotNull('concluida_em')
            ->get()
            ->mapWithKeys(fn (CorridaDestino $parada) => [$chave($parada->latitude, $parada->longitude) => $parada->concluida_em])
            ->all();

        $corrida->corrida_destinos()->where('tipo', '!=', 'origem')->delete();

        foreach (array_slice($itinerario, 1) as $indice => $ponto) {
            $ultimo = $indice === count($itinerario) - 2;
            $corrida->corrida_destinos()->create([
                'nome_local' => $ponto['endereco'],
                'tipo' => $ultimo ? 'destino' : 'parada',
                'ordem' => $indice + 1,
                'endereco' => $ponto['endereco'],
                'latitude' => $ponto['latitude'],
                'longitude' => $ponto['longitude'],
                'concluida_em' => $ultimo ? null : ($concluidas[$chave($ponto['latitude'], $ponto['longitude'])] ?? null),
            ]);
        }
    }
}
