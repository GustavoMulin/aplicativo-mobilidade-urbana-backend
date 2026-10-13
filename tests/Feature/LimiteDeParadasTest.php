<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('não cota corrida com mais de 2 paradas', function () {
    $id = str_replace('-', '', (string) Str::uuid());
    $usuario = User::create([
        'name' => 'Passageiro Paradas',
        'telefone' => substr('69'.preg_replace('/\D/', '', $id).'000000000', 0, 11),
        'cpf' => substr(preg_replace('/\D/', '', $id).'00000000000', 0, 11),
        'data_nascimento' => '1990-01-01',
        'email' => "paradas-$id@example.test",
        'foto' => null,
        'foto_thumbnail' => null,
        'status' => 'ativo',
        'password' => 'senha-de-teste',
    ]);

    $enderecos = collect(range(0, 4))->map(fn (int $ordem) => [
        'order' => $ordem,
        'latitude' => -8.76 + $ordem / 100,
        'longitude' => -63.90,
        'formattedAddress' => "Ponto {$ordem}",
    ])->all();

    $this->actingAs($usuario, 'jwt')
        ->postJson('/api/precos-corrida', ['enderecos' => $enderecos])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enderecos' => 'A corrida pode ter no máximo 2 paradas.']);
});
