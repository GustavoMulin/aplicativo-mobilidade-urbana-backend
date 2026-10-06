<?php

namespace App\Models;

use Database\Factories\MotoristaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'nome',
    'cpf',
    'data_nascimento',
    'numero_registro',
    'cnh_categoria',
    'primeira_habilitacao',
    'data_emissao',
    'cnh_expiracao',
    'ear',
    'observacao',
    'status',
])]

class Motorista extends Model
{
    /** @use HasFactory<MotoristaFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date:Y-m-d',
            'primeira_habilitacao' => 'date:Y-m-d',
            'data_emissao' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
