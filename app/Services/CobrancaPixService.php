<?php

namespace App\Services;

use App\Models\CobrancaPix;
use App\Models\Corrida;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Cobrança Pix da corrida pela AbacatePay. Cobra o valor devido no momento:
 * o pré-pagamento ao pedir a corrida ou a pendência depois que ela terminou.
 * Com chave abc_dev_ a cobrança é simulada e pode ser paga pelo sandbox.
 */
class CobrancaPixService
{
    public function __construct(
        private readonly AbacatePayClient $abacatePay,
        private readonly PagamentoCorridaService $pagamento,
    ) {}

    public function paraCorrida(Corrida $corrida): CobrancaPix
    {
        $valor = $this->pagamento->valorDevido($corrida);

        if ($valor <= 0) {
            throw new RuntimeException('Não há valor a pagar nesta corrida.', 409);
        }

        $centavos = (int) round($valor * 100);
        $existente = CobrancaPix::where('corrida_id', $corrida->id)
            ->where('status', 'PENDING')
            ->where('valor_centavos', $centavos)
            ->latest('id')
            ->first();

        if ($existente !== null && $existente->expira_em?->isFuture()) {
            return $existente;
        }

        $usuario = $corrida->passageiro()->first()?->user;

        if ($usuario === null) {
            throw new RuntimeException('Passageiro da corrida não encontrado.', 409);
        }

        $dados = $this->abacatePay->post('/v2/transparents/create', [
            'method' => 'PIX',
            'data' => [
                'amount' => $centavos,
                'description' => "Corrida {$corrida->codigo_corrida}",
                'expiresIn' => (int) config('abacatepay.validade_segundos'),
                'customer' => [
                    'name' => (string) $usuario->name,
                    'email' => (string) $usuario->email,
                    'cellphone' => preg_replace('/\D/', '', (string) $usuario->telefone),
                    'taxId' => preg_replace('/\D/', '', (string) $usuario->cpf),
                ],
                'metadata' => ['corrida_id' => $corrida->id],
            ],
        ]);

        return CobrancaPix::create([
            'corrida_id' => $corrida->id,
            'charge_id' => $dados['id'],
            'status' => $dados['status'],
            'valor_centavos' => (int) $dados['amount'],
            'br_code' => $dados['brCode'],
            'br_code_base64' => $dados['brCodeBase64'],
            'dev_mode' => (bool) ($dados['devMode'] ?? false),
            'expira_em' => Carbon::parse($dados['expiresAt']),
        ]);
    }

    /**
     * Reconsulta a AbacatePay; nunca confia em status vindo do aplicativo ou
     * do corpo do webhook. $acionar=false só atualiza o registro.
     */
    public function sincronizar(CobrancaPix $cobranca, bool $acionar = true): CobrancaPix
    {
        $dados = $this->abacatePay->get('/v2/transparents/check', ['id' => $cobranca->charge_id]);

        // a resposta precisa ser da mesma cobrança consultada
        if (($dados['id'] ?? null) !== $cobranca->charge_id) {
            throw new RuntimeException('A AbacatePay devolveu uma cobrança diferente da consultada.', 502);
        }

        $acabouDePagar = $dados['status'] === 'PAID' && $cobranca->status !== 'PAID';

        $cobranca->update([
            'status' => $dados['status'],
            'pago_em' => $acabouDePagar ? now() : $cobranca->pago_em,
        ]);

        if ($acabouDePagar && $acionar) {
            $this->pagamento->aoConfirmarPagamento($cobranca->corrida_id);
        }

        return $cobranca->refresh();
    }

    public function simular(CobrancaPix $cobranca): CobrancaPix
    {
        if (! config('abacatepay.simulacao_habilitada') || ! $cobranca->dev_mode) {
            throw new RuntimeException('Simulação de pagamento indisponível neste ambiente.', 409);
        }

        // a AbacatePay exige o id na query string, além do corpo
        $this->abacatePay->post(
            '/v2/transparents/simulate-payment?'.http_build_query(['id' => $cobranca->charge_id]),
            ['id' => $cobranca->charge_id]
        );

        return $this->sincronizar($cobranca);
    }
}
