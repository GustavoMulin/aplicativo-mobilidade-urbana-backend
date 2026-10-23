<?php

namespace App\Enums;

enum CategoriaSaldoMotorista: string
{
    case ValorCorrida = 'valor_corrida';
    case Recompensa = 'recompensa';
    case Gorjeta = 'gorjeta';
    case Compensacao = 'compensacao';
    case GanhosIntegrais = 'ganhos_integrais';
    case Outro = 'outro';
    case Transferencia = 'transferencia';
    case Taxa = 'taxa_plataforma';
    case Desconto = 'desconto';
    case Ajuste = 'ajuste';
    case Estorno = 'estorno';

    public static function normalizar(mixed $categoria): ?string
    {
        if (! is_string($categoria) || $categoria === '') {
            return null;
        }

        return ['ganho_corrida' => 'valor_corrida', 'bonus' => 'recompensa'][$categoria] ?? $categoria;
    }

    public static function filtro(string $categoria): array
    {
        return match ($categoria) {
            'valor_corrida', 'ganho_corrida' => ['valor_corrida', 'ganho_corrida'],
            'recompensa', 'bonus' => ['recompensa', 'bonus'],
            'descontos_taxas' => ['desconto', 'taxa_plataforma'],
            'ajustes_estornos' => ['ajuste', 'estorno'],
            default => [$categoria],
        };
    }
}
