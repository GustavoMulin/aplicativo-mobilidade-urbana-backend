<?php

namespace App\Services;

use App\Models\Motorista;
use App\Models\Passageiro;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Throwable;

class PerfilUsuarioService
{
    public const PASSAGEIRO = 'passageiro';

    public const MOTORISTA = 'motorista';

    public function solicitado(Request $request): string
    {
        $perfil = $request->input('perfil') ?? $request->header('X-Perfil');

        if (! is_string($perfil) || $perfil === '') {
            try {
                $guard = auth('jwt');
                $perfil = $guard instanceof JWTGuard
                    ? $guard->payload()->get('perfil')
                    : null;
            } catch (Throwable) {
                $perfil = null;
            }
        }

        return in_array($perfil, [self::PASSAGEIRO, self::MOTORISTA], true)
            ? $perfil
            : self::PASSAGEIRO;
    }

    public function garantir(User $user, string $perfil): Motorista|Passageiro
    {
        return match ($perfil) {
            self::MOTORISTA => Motorista::withTrashed()->firstOrCreate(
                ['user_id' => $user->id],
                ['status' => 'pendente'],
            ),
            self::PASSAGEIRO => Passageiro::withTrashed()->firstOrCreate(
                ['user_id' => $user->id],
                ['status' => 'ativo'],
            ),
            default => throw new InvalidArgumentException('Perfil de aplicativo inválido.'),
        };
    }

    public function localizar(User $user, string $perfil): Motorista|Passageiro|null
    {
        return match ($perfil) {
            self::MOTORISTA => Motorista::where('user_id', $user->id)->first(),
            self::PASSAGEIRO => Passageiro::where('user_id', $user->id)->first(),
            default => null,
        };
    }

    public function indisponivel(Model $perfil): bool
    {
        return $perfil->getAttribute('deleted_at') !== null
            || in_array($perfil->getAttribute('status'), ['bloqueado', 'inativo'], true);
    }

    /**
     * Mantém o contrato consumido pelos aplicativos, usando foto e status do
     * perfil aberto no momento.
     *
     * @return array<string, mixed>
     */
    public function dados(User $user, string $perfil, ?Model $registro = null): array
    {
        $registro ??= $this->localizar($user, $perfil);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'telefone' => $user->telefone,
            'cpf' => $user->cpf,
            'data_nascimento' => $user->data_nascimento,
            'foto' => $registro?->getAttribute('foto'),
            'status' => $registro?->getAttribute('status'),
            'perfil' => $perfil,
            'motorista' => $perfil === self::MOTORISTA,
            'created_at' => $user->created_at,
        ];
    }
}
