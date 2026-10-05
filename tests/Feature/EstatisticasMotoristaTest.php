<?php

use App\Models\Corrida;
use App\Models\Motorista;
use App\Models\Passageiro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function usuarioEstatistica(string $papel): User
{
    $id = str_replace('-', '', (string) Str::uuid());

    return User::create([
        'name' => ucfirst($papel).' Estatística',
        'telefone' => substr('69'.preg_replace('/\D/', '', $id).'000000000', 0, 11),
        'cpf' => substr(preg_replace('/\D/', '', $id).'00000000000', 0, 11),
        'data_nascimento' => '1990-01-01',
        'email' => "$papel-$id@example.test",
        'foto' => null,
        'foto_thumbnail' => null,
        'status' => 'ativo',
        'password' => 'senha-de-teste',
    ]);
}

it('calcula a taxa de finalização pelas corridas aceitas e não inventa a de aceitação', function () {
    $motorista = Motorista::create([
        'user_id' => usuarioEstatistica('motorista')->id,
        'status' => 'aprovado',
        'cnh_numero' => null,
        'cnh_categoria' => null,
        'cnh_expiracao' => null,
        'ear' => null,
    ]);
    $passageiro = Passageiro::create([
        'user_id' => usuarioEstatistica('passageiro')->id,
        'media_avaliacao' => null,
    ]);

    foreach (['finalizada', 'finalizada', 'finalizada', 'cancelada'] as $indice => $status) {
        Corrida::create([
            'codigo_corrida' => 'EST-'.$indice.'-'.Str::upper(Str::random(6)),
            'motorista_id' => $motorista->id,
            'passageiro_id' => $passageiro->id,
            'status_corrida' => $status,
            'tempo_solicitacao' => now()->subHour(),
            'tempo_aceite' => now()->subHour(),
            'metodo_pagamento' => 'dinheiro',
            'status_pagamento' => 'pendente',
        ]);
    }

    Corrida::create([
        'codigo_corrida' => 'EST-SEM-ACEITE-'.Str::upper(Str::random(6)),
        'motorista_id' => null,
        'passageiro_id' => $passageiro->id,
        'status_corrida' => 'cancelada',
        'tempo_solicitacao' => now()->subHour(),
        'metodo_pagamento' => 'dinheiro',
        'status_pagamento' => 'pendente',
    ]);

    $this->actingAs($motorista->user, 'jwt')
        ->getJson('/api/motorista/me/estatisticas')
        ->assertOk()
        ->assertJsonPath('corridas_aceitas', 4)
        ->assertJsonPath('corridas_finalizadas', 3)
        ->assertJsonPath('taxa_finalizacao', 75)
        ->assertJsonPath('taxa_aceitacao', null);
});

it('responde sem corridas com finalização nula', function () {
    $motorista = Motorista::create([
        'user_id' => usuarioEstatistica('motorista')->id,
        'status' => 'aprovado',
        'cnh_numero' => null,
        'cnh_categoria' => null,
        'cnh_expiracao' => null,
        'ear' => null,
    ]);

    $this->actingAs($motorista->user, 'jwt')
        ->getJson('/api/motorista/me/estatisticas')
        ->assertOk()
        ->assertJsonPath('taxa_finalizacao', null);
});

it('calcula a taxa de aceitação sobre as corridas que foram ofertadas', function () {
    $motorista = Motorista::create([
        'user_id' => usuarioEstatistica('motorista')->id,
        'status' => 'aprovado',
        'cnh_numero' => null,
        'cnh_categoria' => null,
        'cnh_expiracao' => null,
        'ear' => null,
    ]);
    $passageiro = Passageiro::create([
        'user_id' => usuarioEstatistica('passageiro')->id,
        'media_avaliacao' => null,
    ]);

    $ofertadas = collect(range(1, 4))->map(fn () => Corrida::create([
        'codigo_corrida' => 'OFR-'.Str::upper(Str::random(8)),
        'passageiro_id' => $passageiro->id,
        'status_corrida' => 'solicitada',
        'tempo_solicitacao' => now()->subMinutes(5),
        'metodo_pagamento' => 'dinheiro',
        'status_pagamento' => 'pendente',
    ]));

    $ofertadas->each(fn (Corrida $corrida) => DB::table('ofertas_motorista')->insert([
        'motorista_id' => $motorista->id,
        'corrida_id' => $corrida->id,
        'ofertada_em' => now(),
    ]));

    // duas ofertas viraram aceite; a mesma corrida ofertada duas vezes não conta dobrado
    $ofertadas[0]->update(['motorista_id' => $motorista->id, 'status_corrida' => 'aceita', 'tempo_aceite' => now()]);
    $ofertadas[1]->update(['motorista_id' => $motorista->id, 'status_corrida' => 'finalizada', 'tempo_aceite' => now()]);

    $this->actingAs($motorista->user, 'jwt')
        ->getJson('/api/motorista/me/estatisticas')
        ->assertOk()
        ->assertJsonPath('taxa_aceitacao', 50);
});
