<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pontos sugeridos para o embarque perto de onde o passageiro marcou, como no
 * 99 (shopping, aeroporto e outros lugares grandes têm vários acessos). Vem
 * do Google Geocoding v4 "destinations": os pontos de parada para carro
 * (navigationPoints com DRIVE) e, sem eles, as entradas do prédio.
 */
class PontosDeEmbarqueService
{
    private const URL = 'https://geocode.googleapis.com/v4beta/geocode/destinations';

    private const DISTANCIA_MAXIMA_METROS = 300.0;

    private const LIMITE = 4;

    /**
     * @return list<array{id: string, nome: string, descricao: string|null, latitude: float, longitude: float, distancia_m: int, tipo: string}>
     */
    public function executar(float $latitude, float $longitude): array
    {
        $chave = (string) config('services.google_maps.key');

        if ($chave === '') {
            return [];
        }

        // ~11 m: arrastar o pino um pouco não refaz a consulta
        $cache = sprintf('pontos-embarque:v2:%.4f,%.4f', $latitude, $longitude);

        return Cache::remember($cache, now()->addHours(6), function () use ($chave, $latitude, $longitude) {
            try {
                $resposta = Http::connectTimeout(3)->timeout(6)
                    ->withHeaders(['X-Goog-Api-Key' => $chave, 'X-Goog-FieldMask' => 'destinations.primary.displayName,destinations.primary.formattedAddress,destinations.containingPlaces,destinations.subDestinations.displayName,destinations.navigationPoints,destinations.entrances'])
                    ->post(self::URL, [
                        'locationQuery' => ['location' => compact('latitude', 'longitude')],
                        'languageCode' => 'pt-BR',
                    ]);
            } catch (Throwable $erro) {
                Log::info('Pontos de embarque indisponíveis.', ['erro' => $erro->getMessage()]);

                return [];
            }

            if (! $resposta->successful()) {
                return [];
            }

            return $this->pontos((array) $resposta->json('destinations', []), $latitude, $longitude);
        });
    }

    /**
     * @param  array<int, mixed>  $destinos
     * @return list<array{id: string, nome: string, descricao: string|null, latitude: float, longitude: float, distancia_m: int, tipo: string}>
     */
    private function pontos(array $destinos, float $latitude, float $longitude): array
    {
        $candidatos = [];

        foreach ($destinos as $destino) {
            if (! is_array($destino)) {
                continue;
            }

            $lugar = $this->nomeDoLugar($destino);

            foreach ((array) ($destino['navigationPoints'] ?? []) as $ponto) {
                if (is_array($ponto) && in_array('DRIVE', (array) ($ponto['travelModes'] ?? []), true)) {
                    $candidatos[] = ['tipo' => 'parada', 'lugar' => $lugar, 'nome' => $ponto['displayName']['text'] ?? null, 'location' => $ponto['location'] ?? null];
                }
            }

            // sem ponto de parada mapeado, as entradas do prédio servem de referência
            if ($candidatos === []) {
                foreach ((array) ($destino['entrances'] ?? []) as $entrada) {
                    if (is_array($entrada)) {
                        $candidatos[] = ['tipo' => 'entrada', 'lugar' => $lugar, 'nome' => null, 'location' => $entrada['location'] ?? null];
                    }
                }
            }
        }

        $pontos = [];

        foreach ($candidatos as $candidato) {
            $lat = $candidato['location']['latitude'] ?? null;
            $lng = $candidato['location']['longitude'] ?? null;

            if (! is_numeric($lat) || ! is_numeric($lng)) {
                continue;
            }

            $distancia = $this->distanciaMetros($latitude, $longitude, (float) $lat, (float) $lng);

            if ($distancia > self::DISTANCIA_MAXIMA_METROS) {
                continue;
            }

            $pontos[] = [
                'tipo' => $candidato['tipo'],
                'lugar' => $candidato['lugar'],
                'nome' => $candidato['nome'],
                'latitude' => round((float) $lat, 7),
                'longitude' => round((float) $lng, 7),
                'distancia_m' => (int) round($distancia),
            ];
        }

        usort($pontos, fn (array $a, array $b) => $a['distancia_m'] <=> $b['distancia_m']);
        $pontos = array_slice($this->semRepetidos($pontos), 0, self::LIMITE);

        // nome repetido (dois pontos na "Rua Brasília") ganha a letra para dar
        // para diferenciar na lista
        $repetidos = array_keys(array_filter(array_count_values(array_filter(array_column($pontos, 'nome'))), fn (int $vezes) => $vezes > 1));

        // pontos sem nome viram "Ponto A", "Entrada B"... na ordem de distância
        return array_map(function (array $ponto, int $indice) use ($repetidos) {
            $letra = ['A', 'B', 'C', 'D'][$indice] ?? (string) ($indice + 1);
            $nome = $ponto['nome'] ?: ($ponto['tipo'] === 'entrada' ? "Entrada {$letra}" : "Ponto {$letra}");

            if (in_array($ponto['nome'], $repetidos, true)) {
                $nome .= ", ponto {$letra}";
            }

            return [
                'id' => sprintf('%s-%.6f,%.6f', $ponto['tipo'], $ponto['latitude'], $ponto['longitude']),
                'nome' => $nome,
                'descricao' => $ponto['lugar'],
                'latitude' => $ponto['latitude'],
                'longitude' => $ponto['longitude'],
                'distancia_m' => $ponto['distancia_m'],
                'tipo' => $ponto['tipo'],
            ];
        }, $pontos, array_keys($pontos));
    }

    /**
     * @param  array<string, mixed>  $destino
     */
    private function nomeDoLugar(array $destino): ?string
    {
        foreach ((array) ($destino['subDestinations'] ?? []) as $sub) {
            if (is_array($sub) && is_string($sub['displayName']['text'] ?? null)) {
                return $sub['displayName']['text'];
            }
        }

        foreach ((array) ($destino['containingPlaces'] ?? []) as $lugar) {
            if (is_array($lugar) && is_string($lugar['displayName']['text'] ?? null)) {
                return $lugar['displayName']['text'];
            }
        }

        $primario = $destino['primary'] ?? [];

        return is_array($primario) && is_string($primario['displayName']['text'] ?? null)
            ? $primario['displayName']['text']
            : null;
    }

    /**
     * Dois pontos a menos de 15 m são o mesmo lugar para quem vai embarcar.
     *
     * @param  list<array<string, mixed>>  $pontos
     * @return list<array<string, mixed>>
     */
    private function semRepetidos(array $pontos): array
    {
        $unicos = [];

        foreach ($pontos as $ponto) {
            foreach ($unicos as $indice => $existente) {
                if ($this->distanciaMetros($ponto['latitude'], $ponto['longitude'], $existente['latitude'], $existente['longitude']) < 15) {
                    // fica o que tem nome ("Aice park" vale mais que "Ponto B")
                    if (! $existente['nome'] && $ponto['nome']) {
                        $unicos[$indice] = $ponto;
                    }

                    continue 2;
                }
            }
            $unicos[] = $ponto;
        }

        return array_values($unicos);
    }

    private function distanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $raio = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $raio * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
