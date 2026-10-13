<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('cada rota com limite tem o próprio contador e o limite continua valendo', function () {
    $id = str_replace('-', '', (string) Str::uuid());
    $usuario = User::create([
        'name' => 'Passageiro Limite',
        'telefone' => substr('69'.preg_replace('/\D/', '', $id).'000000000', 0, 11),
        'cpf' => substr(preg_replace('/\D/', '', $id).'00000000000', 0, 11),
        'data_nascimento' => '1990-01-01',
        'email' => "limite-$id@example.test",
        'foto' => null,
        'foto_thumbnail' => null,
        'status' => 'ativo',
        'password' => 'senha-de-teste',
    ]);

    $sugestao = ['tipo' => 'comentario', 'descricao' => 'A busca poderia mostrar mais lojas.'];

    foreach (range(1, 10) as $_) {
        $this->actingAs($usuario, 'jwt')->postJson('/api/locais/sugestoes', $sugestao)->assertCreated();
    }

    $this->actingAs($usuario, 'jwt')->postJson('/api/locais/sugestoes', $sugestao)->assertTooManyRequests();

    // outra rota com o mesmo limite de 10 não herda o contador das sugestões
    $this->actingAs($usuario, 'jwt')
        ->postJson('/api/ajuda/chamados', ['motivo' => 'outro', 'descricao' => 'Dúvida sobre o aplicativo.'])
        ->assertCreated();
});
