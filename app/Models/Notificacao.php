<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $perfil passageiro ou motorista (nulo: antiga, aparece nos dois)
 * @property string $titulo
 * @property string $mensagem
 * @property Carbon|null $lida_em
 */
class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected $fillable = ['user_id', 'perfil', 'titulo', 'mensagem', 'lida_em'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['lida_em' => 'datetime'];
    }
}
