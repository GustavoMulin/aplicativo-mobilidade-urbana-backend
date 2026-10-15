<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChamadoMensagemAnexo extends Model
{
    protected $table = 'chamado_mensagem_anexos';

    protected $fillable = ['ordem', 'disk', 'path', 'nome', 'mime_type', 'tamanho', 'largura', 'altura', 'sha256'];

    public function mensagem(): BelongsTo
    {
        return $this->belongsTo(ChamadoMensagem::class, 'mensagem_id');
    }

    public function dados(int $ticketId): array
    {
        return $this->only(['id', 'nome', 'mime_type', 'tamanho', 'largura', 'altura'])
            + ['url' => '/ajuda/tickets/'.$ticketId.'/mensagens/'.$this->mensagem_id.'/anexos/'.$this->id];
    }
}
