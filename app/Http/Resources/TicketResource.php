<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'protocolo' => 'AJ-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT),
            'solicitante_tipo' => $this->solicitante_tipo,
            'solicitante' => $this->solicitante?->only(['id', 'name', 'cpf']),
            'gestor_responsavel' => $this->responsavel?->only(['id', 'name']),
            'corrida' => $this->corrida ? ['id' => $this->corrida->id, 'codigo_corrida' => $this->corrida->codigo_corrida, 'status' => $this->corrida->status_corrida] : null,
            'motivo' => $this->motivo, 'descricao' => $this->descricao, 'status' => $this->status,
            'ultima_mensagem_em' => $this->ultima_mensagem_em?->toISOString(),
            'resolvido_em' => $this->resolvido_em?->toISOString(), 'fechado_em' => $this->fechado_em?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
