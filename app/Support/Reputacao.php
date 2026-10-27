<?php

namespace App\Support;

class Reputacao
{
    /**
     * Média que pode ser mostrada a outras pessoas: só depois do mínimo de
     * corridas finalizadas (config avaliacoes.minimo_corridas_para_media).
     */
    public static function media(mixed $nota, int $corridasFinalizadas): ?float
    {
        if ($nota === null || $corridasFinalizadas < self::minimoDeCorridas()) {
            return null;
        }

        return round((float) $nota, 2);
    }

    public static function minimoDeCorridas(): int
    {
        return (int) config('avaliacoes.minimo_corridas_para_media', 20);
    }
}
