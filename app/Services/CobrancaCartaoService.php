<?php

namespace App\Services;

use App\Models\CobrancaCartao;
use App\Models\Corrida;
use RuntimeException;

/**
 * Cobrança por cartão pela AbacatePay: cria um produto com o valor devido e um
 * checkout só com cartão. O cliente digita o cartão na página da AbacatePay,
 * então o aplicativo nunca recebe dados de cartão.
 */
class CobrancaCartaoService
{
    public function __construct(
        private readonly AbacatePayClient $abacatePay,
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

        $produto = $this->abacatePay->post('/v2/products/create', [
            'externalId' => 'corrida-'.$corrida->id.'-'.$centavos.'-'.now()->timestamp,
            'name' => 'Corrida '.$corrida->codigo_corrida,
            'price' => $centavos,
            'currency' => 'BRL',
            'description' => 'Corrida '.$corrida->codigo_corrida,
        ]);

        $checkout = $this->abacatePay->post('/v2/checkouts/create', [
            'items' => [['id' => $produto['id'], 'quantity' => 1]],
            'methods' => ['CARD'],
            'returnUrl' => (string) config('abacatepay.url_retorno'),
            'completionUrl' => (string) config('abacatepay.url_retorno'),
            'metadata' => ['corrida_id' => $corrida->id],
        ]);

        return CobrancaCartao::create([
            'corrida_id' => $corrida->id,
            'produto_id' => $produto['id'],
            'checkout_id' => $checkout['id'],
            'url' => $checkout['url'],
            'status' => $checkout['status'],
            'valor_centavos' => $centavos,
            'dev_mode' => (bool) ($checkout['devMode'] ?? false),
        ]);
    }

    public function sincronizar(CobrancaCartao $cobranca, bool $acionar = true): CobrancaCartao
    {
        $dados = $this->abacatePay->get('/v2/checkouts/get', ['id' => $cobranca->checkout_id]);

        if (($dados['id'] ?? null) !== $cobranca->checkout_id) {
            throw new RuntimeException('A AbacatePay devolveu um checkout diferente do consultado.', 502);
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
}
