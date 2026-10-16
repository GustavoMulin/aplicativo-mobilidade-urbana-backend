<?php

namespace App\Enums;

enum StatusTicket: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Resolved = 'resolved';

    public function nome(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::InProgress => 'Em atendimento',
            self::Waiting => 'Aguardando atendimento',
            self::Resolved => 'Finalizado',
        };
    }
}
