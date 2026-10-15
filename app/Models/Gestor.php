<?php

namespace App\Models;

use App\Enums\PerfilAcessoGestor;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Gestor extends Authenticatable implements JWTSubject
{
    use SoftDeletes;

    protected $table = 'gestores';

    protected $attributes = ['perfil_acesso' => 'atendimento'];

    protected $appends = ['permissoes', 'perfil_acesso_nome'];

    public function pode(string $permissao): bool
    {
        return in_array($permissao, $this->permissoes, true);
    }

    protected function permissoes(): Attribute
    {
        return Attribute::get(fn () => PerfilAcessoGestor::tryFrom((string) $this->perfil_acesso)?->permissoes() ?? []);
    }

    protected function perfilAcessoNome(): Attribute
    {
        return Attribute::get(fn () => PerfilAcessoGestor::tryFrom((string) $this->perfil_acesso)?->nome() ?? 'Sem acesso');
    }

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
