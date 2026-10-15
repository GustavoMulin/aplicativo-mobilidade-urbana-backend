<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'transferred_by' => ['id' => $this->transferred_by_user_id, 'name' => $this->transferred_by_name],
            'from_user' => $this->from_user_name === null ? null : ['id' => $this->from_user_id, 'name' => $this->from_user_name],
            'to_user' => $this->to_user_name === null ? null : ['id' => $this->to_user_id, 'name' => $this->to_user_name],
            'type' => $this->type,
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
