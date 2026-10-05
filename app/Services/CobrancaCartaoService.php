<?php

namespace App\Services;

use App\Models\CobrancaCartao;
use App\Models\Corrida;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cobrança por cartão pela AbacatePay: cria um produto para a corrida e um
 * checkout só com cartão. O cliente digita o cartão na página da AbacatePay.
 */
class CobrancaCartaoService
{
    public function paraCorrida(Corrida $corrida): CobrancaCartao
    {
        $existente = CobrancaCartao::where('corrida_id', $corrida->id)
            ->where('status', 'PENDING')
            ->latest('id')
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $corrida->loadMissing('corrida_financeiro');
        $valor = (float) $corrida->corrida_financeiro?->valor_pago_passageiro;

        if ($valor <= 0) {
            throw new RuntimeException('A corrida não tem valor para cobrar no cartão.', 409);
        }

        $centavos = (int) round($valor * 100);
        $produto = $this->post('/v2/products/create', [
            'externalId' => 'corrida-'.$corrida->id.'-'.$centavos,
            'name' => 'Corrida '.$corrida->codigo_corrida,
            'price' => $centavos,
            'currency' => 'BRL',
            'description' => 'Corrida '.$corrida->codigo_corrida,
        ]);

        $checkout = $this->post('/v2/checkouts/create', [
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

    public function sincronizar(CobrancaCartao $cobranca): CobrancaCartao
    {
        $dados = $this->get('/v2/checkouts/get', ['id' => $cobranca->checkout_id]);

        if (($dados['id'] ?? null) !== $cobranca->checkout_id) {
            throw new RuntimeException('A AbacatePay devolveu um checkout diferente do consultado.', 502);
        }

        $pago = $dados['status'] === 'PAID';
        $cobranca->update([
            'status' => $dados['status'],
            'pago_em' => $pago ? ($cobranca->pago_em ?? now()) : $cobranca->pago_em,
        ]);

        if ($pago) {
            Corrida::whereKey($cobranca->corrida_id)->update(['status_pagamento' => 'pago']);
        }

        return $cobranca->refresh();
    }

    /**
     * @param  array<string, mixed>  $corpo
     * @return array<string, mixed>
     */
    private function post(string $caminho, array $corpo): array
    {
        return $this->enviar(Http::withToken($this->chave())->timeout(15)->post($this->url($caminho), $corpo));
    }

    /**
     * @param  array<string, string>  $consulta
     * @return array<string, mixed>
     */
    private function get(string $caminho, array $consulta): array
    {
        return $this->enviar(Http::withToken($this->chave())->timeout(15)->get($this->url($caminho), $consulta));
    }

    /**
     * @return array<string, mixed>
     */
    private function enviar(Response $resposta): array
    {
        $json = $resposta->json();

        if (! $resposta->successful() || ! is_array($json) || ($json['success'] ?? false) !== true) {
            throw new RuntimeException(
                'Não foi possível falar com a AbacatePay: '.($json['error'] ?? 'resposta inválida'),
                502
            );
        }

        return $json['data'];
    }

    private function url(string $caminho): string
    {
        return rtrim((string) config('abacatepay.base_url'), '/').$caminho;
    }

    private function chave(): string
    {
        $chave = (string) config('abacatepay.api_key');

        if ($chave === '') {
            throw new RuntimeException('Chave da AbacatePay não configurada.', 502);
        }

        return $chave;
    }
}
