<?php

namespace App\Services;

use App\Models\CobrancaPix;
use App\Models\Corrida;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cobrança Pix da corrida pela AbacatePay. Com chave abc_dev_ a cobrança é
 * simulada: o aplicativo pode marcar como paga pelo endpoint de simulação.
 */
class CobrancaPixService
{
    public function paraCorrida(Corrida $corrida): CobrancaPix
    {
        $existente = CobrancaPix::where('corrida_id', $corrida->id)
            ->where('status', 'PENDING')
            ->latest('id')
            ->first();

        if ($existente !== null && $existente->expira_em?->isFuture()) {
            return $existente;
        }

        $corrida->loadMissing(['corrida_financeiro', 'passageiro.user']);
        $valor = (float) $corrida->corrida_financeiro?->valor_pago_passageiro;
        $usuario = $corrida->passageiro?->user;

        if ($valor <= 0 || $usuario === null) {
            throw new RuntimeException('A corrida não tem valor ou passageiro para cobrar por Pix.', 409);
        }

        $dados = $this->post('/v2/transparents/create', [
            'method' => 'PIX',
            'data' => [
                'amount' => (int) round($valor * 100),
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

    public function sincronizar(CobrancaPix $cobranca): CobrancaPix
    {
        $dados = $this->get('/v2/transparents/check', ['id' => $cobranca->charge_id]);

        // a resposta precisa ser da mesma cobrança consultada
        if (($dados['id'] ?? null) !== $cobranca->charge_id) {
            throw new RuntimeException('A AbacatePay devolveu uma cobrança diferente da consultada.', 502);
        }

        $cobranca->update([
            'status' => $dados['status'],
            'pago_em' => $dados['status'] === 'PAID' ? ($cobranca->pago_em ?? now()) : $cobranca->pago_em,
        ]);

        if ($dados['status'] === 'PAID') {
            Corrida::whereKey($cobranca->corrida_id)->update(['status_pagamento' => 'pago']);
        }

        return $cobranca->refresh();
    }

    public function simular(CobrancaPix $cobranca): CobrancaPix
    {
        if (! config('abacatepay.simulacao_habilitada') || ! $cobranca->dev_mode) {
            throw new RuntimeException('Simulação de pagamento indisponível neste ambiente.', 409);
        }

        $this->post('/v2/transparents/simulate-payment', ['id' => $cobranca->charge_id]);

        return $this->sincronizar($cobranca);
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
