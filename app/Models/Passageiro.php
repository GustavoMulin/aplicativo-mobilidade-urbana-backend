<?php

namespace App\Models;

use Database\Factories\PassageiroFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Passageiro extends Model
{
    /** @use HasFactory<PassageiroFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'media_avaliacao',
        'email', 'telefone', 'password', 'foto', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
