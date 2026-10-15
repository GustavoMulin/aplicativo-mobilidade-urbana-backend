<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TicketLido implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(public int $ticketId, public int $gestorId, public int $ultimaMensagemId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('ajuda.operador.'.$this->gestorId)];
    }

    public function broadcastAs(): string
    {
        return 'ticket.lido';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        return ['ticket_id' => $this->ticketId, 'ultima_mensagem_id' => $this->ultimaMensagemId];
    }
}
