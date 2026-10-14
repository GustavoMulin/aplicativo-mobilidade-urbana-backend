<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TicketMensagemEnviada implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(public int $ticketId, public array $mensagem) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("ajuda.ticket.{$this->ticketId}.cliente"), new PrivateChannel("ajuda.ticket.{$this->ticketId}.suporte")];
    }

    public function broadcastAs(): string
    {
        return 'ticket.mensagem';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        return ['ticket_id' => $this->ticketId, 'mensagem' => $this->mensagem];
    }
}
