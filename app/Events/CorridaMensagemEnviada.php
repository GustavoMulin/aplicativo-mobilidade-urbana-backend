<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CorridaMensagemEnviada implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(public int $corridaId, public int $mensagemId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("corrida.{$this->corridaId}.chat"), new PrivateChannel("corrida.{$this->corridaId}.chat.suporte"), new PrivateChannel("corrida.{$this->corridaId}.chat.financeiro")];
    }

    public function broadcastAs(): string
    {
        return 'corrida.mensagem';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        // O conteúdo é consultado pela API, que revalida os participantes e o acesso do operador.
        return ['corrida_id' => $this->corridaId, 'mensagem_id' => $this->mensagemId];
    }
}
