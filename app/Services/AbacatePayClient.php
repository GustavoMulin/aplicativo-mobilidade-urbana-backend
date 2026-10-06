<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Chamadas HTTP à AbacatePay. A chave define o ambiente: abc_dev_ gera
 * cobranças simuladas. Toda resposta com success != true vira exceção 502.
 */
class AbacatePayClient
{
    /**
     * @param  array<string, mixed>  $corpo
     * @return array<string, mixed>
     */
    public function post(string $caminho, array $corpo): array
    {
        return $this->dados(Http::withToken($this->chave())->timeout(15)->post($this->url($caminho), $corpo));
    }

    /**
     * @param  array<string, string>  $consulta
     * @return array<string, mixed>
     */
    public function get(string $caminho, array $consulta): array
    {
        return $this->dados(Http::withToken($this->chave())->timeout(15)->get($this->url($caminho), $consulta));
    }

    /**
     * Estorno integral (a API não faz estorno parcial).
     */
    public function estornarPix(string $chargeId): void
    {
        $this->post('/v2/transparents/refund', ['id' => $chargeId]);
    }

    public function estornarCheckout(string $checkoutId): void
    {
        $this->post('/v2/checkouts/refund', ['id' => $checkoutId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(Response $resposta): array
    {
        $json = $resposta->json();

        if (! $resposta->successful() || ! is_array($json) || ($json['success'] ?? false) !== true) {
            throw new RuntimeException(
                'Não foi possível falar com a AbacatePay: '.(is_array($json) ? ($json['error'] ?? 'resposta inválida') : 'resposta inválida'),
                502
            );
        }

        return is_array($json['data'] ?? null) ? $json['data'] : [];
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
