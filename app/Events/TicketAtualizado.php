<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TicketAtualizado implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(public int $ticketId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('ajuda.suporte'), new PrivateChannel("ajuda.ticket.{$this->ticketId}.cliente"), new PrivateChannel("ajuda.ticket.{$this->ticketId}.suporte")];
    }

    public function broadcastAs(): string
    {
        return 'ticket.atualizado';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        return ['ticket_id' => $this->ticketId];
    }
}
