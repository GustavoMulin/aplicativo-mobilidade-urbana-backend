<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TicketTransferredEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(
        public int $ticketId,
        public int $transferId,
        public ?int $fromUserId,
        public ?int $toUserId,
    ) {}

    public function broadcastOn(): array
    {
        $canais = [new PrivateChannel('ajuda.suporte'), new PrivateChannel("ajuda.ticket.{$this->ticketId}.suporte")];
        if ($this->fromUserId === null || $this->toUserId === null) {
            $canais[] = new PrivateChannel('ajuda.entrada');
        }
        foreach (array_unique(array_filter([$this->fromUserId, $this->toUserId])) as $id) {
            $canais[] = new PrivateChannel("ajuda.operador.{$id}");
        }

        return $canais;
    }

    public function broadcastAs(): string
    {
        return 'ticket.transferido';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        // A fila anterior recebe apenas o aviso de retirada, sem conteúdo do atendimento.
        return ['ticket_id' => $this->ticketId, 'transfer_id' => $this->transferId, 'from_user_id' => $this->fromUserId, 'to_user_id' => $this->toUserId];
    }
}
