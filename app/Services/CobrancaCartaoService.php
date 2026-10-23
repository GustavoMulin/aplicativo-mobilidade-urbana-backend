<?php

namespace App\Services;

use App\Contracts\GatewayPagamento;
use App\Models\CobrancaCartao;
use App\Models\Corrida;
use RuntimeException;

/**
 * Cobrança por cartão pelo provedor de pagamento (GatewayPagamento). O
 * aplicativo nunca envia dados de cartão ao backend: no provedor simulado o
 * formulário é só do app e o passageiro confirma; num provedor real o cartão
 * é digitado na página dele (url do checkout).
 */
class CobrancaCartaoService
{
    public function __construct(
        private readonly GatewayPagamento $gateway,
        private readonly PagamentoCorridaService $pagamento,
    ) {}

    public function paraCorrida(Corrida $corrida): CobrancaCartao
    {
        $valor = $this->pagamento->valorDevido($corrida);

        if ($valor <= 0) {
            throw new RuntimeException('Não há valor a pagar nesta corrida.', 409);
        }

        $centavos = (int) round($valor * 100);
        $existente = CobrancaCartao::where('corrida_id', $corrida->id)
            ->where('status', 'PENDING')
            ->where('valor_centavos', $centavos)
            ->latest('id')
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $dados = $this->gateway->criarCartao($corrida, $centavos);

        return CobrancaCartao::create([
            'corrida_id' => $corrida->id,
            'produto_id' => $dados['produto_id'],
            'checkout_id' => $dados['checkout_id'],
            'url' => $dados['url'],
            'status' => $dados['status'],
            'valor_centavos' => $centavos,
            'dev_mode' => $dados['dev_mode'],
        ]);
    }

    public function sincronizar(CobrancaCartao $cobranca, bool $acionar = true): CobrancaCartao
    {
        $status = $this->gateway->statusCartao($cobranca->checkout_id);

        $acabouDePagar = $status === 'PAID' && $cobranca->status !== 'PAID';

        $cobranca->update([
            'status' => $status,
            'pago_em' => $acabouDePagar ? now() : $cobranca->pago_em,
        ]);

        if ($acabouDePagar && $acionar) {
            $this->pagamento->aoConfirmarPagamento($cobranca->corrida_id);
        }

        return $cobranca->refresh();
    }

    public function simular(CobrancaCartao $cobranca): CobrancaCartao
    {
        if (! $cobranca->dev_mode) {
            throw new RuntimeException('Simulação de pagamento indisponível neste ambiente.', 409);
        }

        $this->gateway->simularCartao($cobranca->checkout_id);

        return $this->sincronizar($cobranca);
    }
}
