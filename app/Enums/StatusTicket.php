<?php

namespace App\Enums;

enum StatusTicket: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function nome(): string
    {
        return match ($this) {
            self::Waiting => 'Aguardando atendimento',
            self::Open => 'Aberto', self::InProgress => 'Em atendimento',
            self::Pending => 'Aguardando resposta', self::Resolved => 'Resolvido', self::Closed => 'Fechado',
        };
    }
}
