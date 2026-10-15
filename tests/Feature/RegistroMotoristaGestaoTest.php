<?php

use App\Models\Motorista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registra pelo painel um usuario como motorista pendente sem exigir CNH e EAR', function () {
    $operador = User::factory()->create();
    $usuario = User::factory()->create();

    $this->actingAs($operador, 'jwt')
        // o painel confirma as credenciais da pessoa ao adicionar o perfil
        ->postJson('/api/motoristas', [
            'user_id' => $usuario->id,
            'email' => $usuario->email,
            'password' => 'senha-do-painel',
            'password_confirmation' => 'senha-do-painel',
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $usuario->id)
        ->assertJsonPath('data.status', 'pendente');

    $motorista = Motorista::where('user_id', $usuario->id)->firstOrFail();
    expect($motorista->numero_registro)->toBeNull()
        ->and($motorista->cnh_categoria)->toBeNull()
        ->and($motorista->cnh_expiracao)->toBeNull()
        ->and($motorista->ear)->toBeNull()
        ->and(Motorista::where('user_id', $operador->id)->exists())->toBeFalse();
});
