<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $perfil passageiro ou motorista
 * @property string $token token do FCM do aparelho
 * @property string $plataforma
 */
class DispositivoPush extends Model
{
    protected $table = 'dispositivos_push';

    protected $fillable = ['user_id', 'perfil', 'token', 'plataforma'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
