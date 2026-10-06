<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $passageiro_id
 * @property int|null $corrida_id
 * @property float $valor
 * @property string $descricao
 */
class MovimentoCredito extends Model
{
    protected $table = 'movimentos_credito';

    protected $fillable = ['passageiro_id', 'corrida_id', 'valor', 'descricao'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['valor' => 'float'];
    }
}
