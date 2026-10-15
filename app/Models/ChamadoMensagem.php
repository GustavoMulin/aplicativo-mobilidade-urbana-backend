<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChamadoMensagem extends Model
{
    protected $table = 'chamado_mensagens';

    protected $fillable = ['chamado_id', 'autor_tipo', 'user_id', 'gestor_id', 'autor_nome', 'conteudo', 'client_message_id', 'tipo_sistema'];

    public function dados(): array
    {
        return $this->only(['id', 'chamado_id', 'autor_tipo', 'user_id', 'gestor_id', 'autor_nome', 'conteudo', 'client_message_id', 'tipo_sistema'])
            + ['created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString()];
    }
}
