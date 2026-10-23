<?php

namespace App\Support;

class Polyline
{
    /**
     * Desfaz o encoded polyline do Google (deltas em base64 de 5 bits).
     *
     * @return list<array{latitude: float, longitude: float}>
     */
    public static function decodificar(string $codificado): array
    {
        $pontos = [];
        $indice = 0;
        $latitude = 0;
        $longitude = 0;
        $tamanho = strlen($codificado);

        while ($indice < $tamanho) {
            foreach (['lat', 'lng'] as $eixo) {
                $deslocamento = 0;
                $resultado = 0;

                do {
                    $byte = ord($codificado[$indice++]) - 63;
                    $resultado |= ($byte & 0x1F) << $deslocamento;
                    $deslocamento += 5;
                } while ($byte >= 0x20 && $indice < $tamanho);

                $delta = ($resultado & 1) ? ~($resultado >> 1) : ($resultado >> 1);

                if ($eixo === 'lat') {
                    $latitude += $delta;
                } else {
                    $longitude += $delta;
                }
            }

            $pontos[] = [
                'latitude' => $latitude / 1e5,
                'longitude' => $longitude / 1e5,
            ];
        }

        return $pontos;
    }
}
