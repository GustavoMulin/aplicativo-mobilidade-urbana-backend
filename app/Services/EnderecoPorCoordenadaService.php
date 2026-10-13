<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Endereço legível de uma coordenada ("Rua Brasília, 2940 · São Cristóvão").
 * Usado quando o app não conseguiu descobrir a rua (o geocodificador do
 * Android falha às vezes) e mandou só "Localização Atual": o motorista
 * precisa saber onde buscar.
 */
class EnderecoPorCoordenadaService
{
    /**
     * Textos que o app usa quando não sabe o endereço.
     */
    private const SEM_ENDERECO = [
        '',
        'localização atual',
        'minha localização',
        'ponto selecionado no mapa',
        'procurando a rua mais próxima...',
    ];

    public function semEndereco(?string $endereco): bool
    {
        return in_array(mb_strtolower(trim((string) $endereco)), self::SEM_ENDERECO, true);
    }

    public function executar(float $latitude, float $longitude): ?string
    {
        $chave = (string) config('services.google_maps.key');

        if ($chave === '') {
            return null;
        }

        $chaveCache = sprintf('endereco-por-coordenada:%.5f,%.5f', $latitude, $longitude);
        $guardado = Cache::get($chaveCache);

        if (is_string($guardado)) {
            return $guardado;
        }

        try {
            $resposta = Http::connectTimeout(3)->timeout(6)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => $latitude.','.$longitude,
                'key' => $chave,
                'language' => 'pt-BR',
                'result_type' => 'street_address|route',
            ]);
        } catch (ConnectionException) {
            return null;
        }

        $completo = $resposta->json('results.0.formatted_address');

        if (! is_string($completo) || $completo === '') {
            return null;
        }

        $endereco = $this->resumir($completo);
        Cache::put($chaveCache, $endereco, now()->addDay());

        return $endereco;
    }

    /**
     * "R. Brasília, 2940 - São Cristóvão, Porto Velho - RO, 76804-000, Brasil"
     * vira "R. Brasília, 2940 · São Cristóvão".
     */
    private function resumir(string $completo): string
    {
        $partes = explode(' - ', $completo);
        $rua = trim($partes[0]);
        $bairro = isset($partes[1]) && count($partes) > 2 ? trim(explode(',', $partes[1])[0]) : '';

        return $bairro !== '' ? "{$rua} · {$bairro}" : $rua;
    }
}
