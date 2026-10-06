<?php

use App\Models\ChamadoAjuda;
use App\Models\Corrida;
use App\Models\Passageiro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function passageiroAjuda(string $cpf = '52998224725'): Passageiro
{
    $id = str_replace('-', '', (string) Str::uuid());
    $user = User::create([
        'name' => 'Passageiro Ajuda',
        'telefone' => substr('69'.preg_replace('/\D/', '', $id).'000000000', 0, 11),
        'cpf' => $cpf,
        'data_nascimento' => '1990-01-01',
        'email' => "ajuda-$id@example.test",
        'foto' => null,
        'foto_thumbnail' => null,
        'status' => 'ativo',
        'password' => 'senha-de-teste',
    ]);

    return Passageiro::create(['user_id' => $user->id, 'media_avaliacao' => null]);
}

it('abre um chamado com motivo e descrição', function () {
    $passageiro = passageiroAjuda();

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson('/api/ajuda/chamados', [
            'motivo' => 'tarifa_incorreta',
            'descricao' => 'O valor cobrado não bate com o que apareceu antes da corrida.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'aberto');

    expect(ChamadoAjuda::where('user_id', $passageiro->user_id)->count())->toBe(1);
});

it('vincula o chamado só a uma corrida do próprio passageiro', function () {
    $dono = passageiroAjuda();
    $outro = passageiroAjuda('11144477735');
    $corrida = Corrida::create([
        'codigo_corrida' => 'AJU-'.Str::upper(Str::random(8)),
        'passageiro_id' => $outro->id,
        'status_corrida' => 'finalizada',
        'tempo_solicitacao' => now(),
        'metodo_pagamento' => 'dinheiro',
        'status_pagamento' => 'pendente',
    ]);

    $this->actingAs($dono->user, 'jwt')
        ->postJson('/api/ajuda/chamados', [
            'motivo' => 'outro',
            'descricao' => 'Quero falar sobre uma corrida que não é minha.',
            'corrida_id' => $corrida->id,
        ])
        ->assertNotFound();
});

it('lista só os chamados do próprio usuário', function () {
    $passageiro = passageiroAjuda();
    $outro = passageiroAjuda('11144477735');
    ChamadoAjuda::create(['user_id' => $passageiro->user_id, 'motivo' => 'outro', 'descricao' => 'Meu chamado de teste.']);
    ChamadoAjuda::create(['user_id' => $outro->user_id, 'motivo' => 'outro', 'descricao' => 'Chamado de outra pessoa.']);

    $this->actingAs($passageiro->user, 'jwt')
        ->getJson('/api/ajuda/chamados')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('recusa motivo desconhecido e descrição curta demais', function () {
    $passageiro = passageiroAjuda();

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson('/api/ajuda/chamados', ['motivo' => 'inventado', 'descricao' => 'curta'])
        ->assertUnprocessable();
});
