<?php

namespace App\Http\Resources;

use App\Models\Gestor;
use App\Services\FilaTicketsService;
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
            'mensagens_nao_lidas' => (int) ($this->resource->getAttribute('mensagens_nao_lidas') ?? 0),
            'ultima_mensagem_cliente_id' => (int) ($this->resource->getAttribute('ultima_mensagem_cliente_id') ?? 0),
            'fila' => [
                'pode_iniciar' => $this->status === 'waiting'
                    && $request->user() instanceof Gestor && $this->gestor_responsavel_id === $request->user()->id,
                'posicao' => $this->fila_posicao,
                'pessoas_a_frente' => $this->status === 'waiting' ? $this->fila_posicao : 0,
                'aguardando_desde' => $this->status === 'waiting' ? $this->fila_entrada_em?->toISOString() : null,
                'tempo_espera_segundos' => $this->status === 'waiting' && $this->fila_entrada_em
                    ? max(0, (int) $this->fila_entrada_em->diffInSeconds(now())) : 0,
                'calculado_em' => now()->toISOString(),
                'mensagem' => $this->status === 'waiting' && $this->fila_posicao !== null
                    ? FilaTicketsService::mensagemEspera($this->fila_posicao) : null,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
