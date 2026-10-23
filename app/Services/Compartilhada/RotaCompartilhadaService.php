<?php

namespace App\Services\Compartilhada;

use App\Support\Polyline;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Rota da viagem compartilhada. A ordem de embarque e desembarque é a
 * projeção de cada ponto na rota de quem abriu a sala (a espinha), sempre
 * avançando: o carro nunca volta atrás. A ordem e a rota são calculadas aqui,
 * nunca no app, porque o preço depende delas.
 */
class RotaCompartilhadaService
{
    private const KM_POR_GRAU_LATITUDE = 110.574;

    private const KM_POR_GRAU_LONGITUDE = 111.320;

    /**
     * Rota de carro passando pelos pontos na ordem dada.
     *
     * @param  list<array{latitude: float, longitude: float}>  $pontos
     * @return array{trechos: list<array{segundos: int, metros: int}>, pontos: list<array{latitude: float, longitude: float}>}|null
     */
    public function trajeto(array $pontos): ?array
    {
        if (count($pontos) < 2) {
            return null;
        }

        $chave = 'rota-compartilhada:'.md5(collect($pontos)
            ->map(fn (array $ponto) => sprintf('%.5f,%.5f', $ponto['latitude'], $ponto['longitude']))
            ->implode('|'));

        $guardado = Cache::get($chave);
        if (is_array($guardado)) {
            /** @var array{trechos: list<array{segundos: int, metros: int}>, pontos: list<array{latitude: float, longitude: float}>} $guardado */
            return $guardado;
        }

        $origem = $pontos[0];
        $destino = $pontos[count($pontos) - 1];
        $paradas = collect(array_slice($pontos, 1, -1))
            ->map(fn (array $ponto) => $ponto['latitude'].','.$ponto['longitude'])
            ->implode('|');

        try {
            $resposta = Http::connectTimeout(3)->timeout(8)->get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => $origem['latitude'].','.$origem['longitude'],
                'destination' => $destino['latitude'].','.$destino['longitude'],
                'waypoints' => $paradas !== '' ? $paradas : null,
                'mode' => 'driving',
                'language' => 'pt-BR',
                'key' => config('services.google_maps.key'),
            ]);
        } catch (ConnectionException) {
            return null;
        }

        $trechos = [];
        foreach ((array) $resposta->json('routes.0.legs', []) as $trecho) {
            if (! is_array($trecho)) {
                continue;
            }
            $trechos[] = [
                'segundos' => (int) ($trecho['duration']['value'] ?? 0),
                'metros' => (int) ($trecho['distance']['value'] ?? 0),
            ];
        }

        $codificado = $resposta->json('routes.0.overview_polyline.points');

        if (count($trechos) !== count($pontos) - 1 || ! is_string($codificado) || $codificado === '') {
            return null;
        }

        $rota = ['trechos' => $trechos, 'pontos' => Polyline::decodificar($codificado)];
        Cache::put($chave, $rota, now()->addMinutes(15));

        return $rota;
    }

    /**
     * Onde o ponto cai ao longo da espinha (km desde o início) e a que
     * distância está dela. Antes do início a posição fica negativa e depois do
     * fim passa do comprimento, para a ordem continuar fazendo sentido nas pontas.
     *
     * @param  list<array{latitude: float, longitude: float}>  $espinha
     * @return array{posicao: float, distancia: float}
     */
    public function projetar(array $espinha, float $latitude, float $longitude): array
    {
        $cosseno = cos(deg2rad($latitude));
        $plano = fn (array $ponto) => [
            ($ponto['longitude'] - $longitude) * self::KM_POR_GRAU_LONGITUDE * $cosseno,
            ($ponto['latitude'] - $latitude) * self::KM_POR_GRAU_LATITUDE,
        ];

        if (count($espinha) === 1) {
            [$x, $y] = $plano($espinha[0]);

            return ['posicao' => 0.0, 'distancia' => hypot($x, $y)];
        }

        $melhor = null;
        $percorrido = 0.0;
        $ultimo = count($espinha) - 2;

        for ($i = 0; $i <= $ultimo; $i++) {
            [$ax, $ay] = $plano($espinha[$i]);
            [$bx, $by] = $plano($espinha[$i + 1]);
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $comprimento = hypot($dx, $dy);
            // o ponto que se projeta é a origem do plano (0, 0)
            $t = $comprimento > 0 ? ((-$ax) * $dx + (-$ay) * $dy) / ($comprimento ** 2) : 0.0;
            $preso = max(0.0, min(1.0, $t));
            $distancia = hypot($ax + $preso * $dx, $ay + $preso * $dy);

            if ($melhor === null || $distancia < $melhor['distancia']) {
                $livre = ($i === 0 && $t < 0) || ($i === $ultimo && $t > 1) ? $t : $preso;
                $melhor = ['posicao' => $percorrido + $livre * $comprimento, 'distancia' => $distancia];
            }

            $percorrido += $comprimento;
        }

        return $melhor;
    }

    /**
     * @param  list<array{latitude: float, longitude: float}>  $espinha
     */
    public function comprimento(array $espinha): float
    {
        $total = 0.0;
        for ($i = 1, $n = count($espinha); $i < $n; $i++) {
            $total += $this->distanciaKm($espinha[$i - 1], $espinha[$i]);
        }

        return $total;
    }

    /**
     * Ordem de todos os pontos pela projeção na espinha. Null se alguém
     * desembarca antes de onde embarca (está indo no sentido contrário).
     *
     * @param  list<array{latitude: float, longitude: float}>  $espinha
     * @param  array<int|string, array{embarque: array{latitude: float, longitude: float}, desembarque: array{latitude: float, longitude: float}}>  $passageiros
     * @return list<array{chave: int|string, acao: 'embarque'|'desembarque', latitude: float, longitude: float}>|null
     */
    public function ordenar(array $espinha, array $passageiros): ?array
    {
        $pontos = [];

        foreach ($passageiros as $chave => $passageiro) {
            $embarque = $this->projetar($espinha, $passageiro['embarque']['latitude'], $passageiro['embarque']['longitude']);
            $desembarque = $this->projetar($espinha, $passageiro['desembarque']['latitude'], $passageiro['desembarque']['longitude']);

            if ($desembarque['posicao'] <= $embarque['posicao']) {
                return null;
            }

            $pontos[] = ['chave' => $chave, 'acao' => 'embarque', 'posicao' => $embarque['posicao'], ...$passageiro['embarque']];
            $pontos[] = ['chave' => $chave, 'acao' => 'desembarque', 'posicao' => $desembarque['posicao'], ...$passageiro['desembarque']];
        }

        usort($pontos, fn (array $a, array $b) => [$a['posicao'], $a['acao'] === 'embarque' ? 0 : 1]
            <=> [$b['posicao'], $b['acao'] === 'embarque' ? 0 : 1]);

        return array_map(fn (array $ponto) => [
            'chave' => $ponto['chave'],
            'acao' => $ponto['acao'],
            'latitude' => (float) $ponto['latitude'],
            'longitude' => (float) $ponto['longitude'],
        ], $pontos);
    }

    /**
     * Rota do grupo na ordem da espinha e quanto tempo cada um fica no carro.
     *
     * @param  list<array{latitude: float, longitude: float}>  $espinha
     * @param  array<int|string, array{embarque: array{latitude: float, longitude: float}, desembarque: array{latitude: float, longitude: float}}>  $passageiros
     * @return array{sequencia: list<array{chave: int|string, acao: 'embarque'|'desembarque', latitude: float, longitude: float}>, segundos: int, metros: int, no_carro: array<int|string, int>, pontos: list<array{latitude: float, longitude: float}>}|null
     */
    public function avaliar(array $espinha, array $passageiros): ?array
    {
        $sequencia = $this->ordenar($espinha, $passageiros);

        if ($sequencia === null) {
            return null;
        }

        $trajeto = $this->trajeto(array_map(fn (array $ponto) => [
            'latitude' => $ponto['latitude'],
            'longitude' => $ponto['longitude'],
        ], $sequencia));

        if ($trajeto === null) {
            return null;
        }

        $relogio = [0];
        foreach ($trajeto['trechos'] as $indice => $trecho) {
            $relogio[$indice + 1] = $relogio[$indice] + $trecho['segundos'];
        }

        $entrada = [];
        $noCarro = [];
        foreach ($sequencia as $indice => $ponto) {
            if ($ponto['acao'] === 'embarque') {
                $entrada[$ponto['chave']] = $relogio[$indice];
            } else {
                $noCarro[$ponto['chave']] = $relogio[$indice] - $entrada[$ponto['chave']];
            }
        }

        return [
            'sequencia' => $sequencia,
            'segundos' => (int) end($relogio),
            'metros' => (int) array_sum(array_column($trajeto['trechos'], 'metros')),
            'no_carro' => $noCarro,
            'pontos' => $trajeto['pontos'],
        ];
    }

    /**
     * @param  array{latitude: float, longitude: float}  $a
     * @param  array{latitude: float, longitude: float}  $b
     */
    public function distanciaKm(array $a, array $b): float
    {
        $dLat = deg2rad($b['latitude'] - $a['latitude']);
        $dLon = deg2rad($b['longitude'] - $a['longitude']);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['latitude'])) * cos(deg2rad($b['latitude'])) * sin($dLon / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }
}
