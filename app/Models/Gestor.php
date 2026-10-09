<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Gestor extends Authenticatable implements JWTSubject
{
    use SoftDeletes;

    protected $table = 'gestores';

    protected $fillable = [
        'name', 'telefone', 'cpf', 'data_nascimento', 'email', 'foto', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date:Y-m-d',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['uid' => (string) Str::uuid(), 'perfil' => 'gestao'];
    }
}
