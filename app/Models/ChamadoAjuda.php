<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $corrida_id
 * @property string $motivo
 * @property string $descricao
 * @property string $status
 */
class ChamadoAjuda extends Model
{
    protected $table = 'chamados_ajuda';

    protected $fillable = ['user_id', 'corrida_id', 'motivo', 'descricao', 'status'];

    /**
     * @return BelongsTo<Corrida, $this>
     */
    public function corrida(): BelongsTo
    {
        return $this->belongsTo(Corrida::class);
    }
}
