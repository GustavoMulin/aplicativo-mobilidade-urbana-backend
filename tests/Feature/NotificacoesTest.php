<?php

use App\Models\Corrida;
use App\Models\CorridaDestino;
use App\Models\CorridaFinanceiro;
use App\Models\Motorista;
use App\Models\Notificacao;
use App\Models\Passageiro;
use App\Models\User;
use App\Services\DespachoCorridaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function usuarioNotificacao(string $papel): User
{
    $id = str_replace('-', '', (string) Str::uuid());

    return User::create([
        'name' => ucfirst($papel).' Notificação',
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

it('avisa motorista e passageiro quando a corrida é finalizada', function () {
    $motorista = Motorista::create([
        'user_id' => usuarioNotificacao('motorista')->id,
        'status' => 'aprovado',
        'cnh_numero' => null,
        'cnh_categoria' => null,
        'cnh_expiracao' => null,
        'ear' => null,
    ]);
    $passageiro = Passageiro::create([
        'user_id' => usuarioNotificacao('passageiro')->id,
        'media_avaliacao' => null,
    ]);
    $corrida = Corrida::create([
        'codigo_corrida' => 'NOT-'.Str::upper(Str::random(8)),
        'motorista_id' => $motorista->id,
        'passageiro_id' => $passageiro->id,
        'status_corrida' => 'em_andamento',
        'tempo_solicitacao' => now()->subHour(),
        'tempo_aceite' => now()->subHour(),
        'tempo_inicio' => now()->subMinutes(10),
        'metodo_pagamento' => 'pix',
        'status_pagamento' => 'pendente',
    ]);
    CorridaDestino::create(['corrida_id' => $corrida->id, 'nome_local' => 'Destino', 'tipo' => 'destino', 'ordem' => 0, 'endereco' => 'Destino', 'latitude' => -8.7, 'longitude' => -63.9]);
    CorridaFinanceiro::create([
        'corrida_id' => $corrida->id,
        'valor_pago_passageiro' => 15.5,
        'valor_motorista' => 14.0,
        'valor_liquido_motorista' => 14.0,
        'metodo_pagamento' => 'pix',
    ]);

    app(DespachoCorridaService::class)->transicionar($motorista, $corrida->id, 'finalizar');

    expect(Notificacao::where('user_id', $motorista->user_id)->value('mensagem'))->toContain('14,00')
        ->and(Notificacao::where('user_id', $passageiro->user_id)->value('mensagem'))->toContain('15,50');
});

it('lista só as notificações do usuário e marca como lidas', function () {
    $eu = usuarioNotificacao('usuario');
    $outro = usuarioNotificacao('outro');
    Notificacao::create(['user_id' => $eu->id, 'titulo' => 'Minha', 'mensagem' => 'texto']);
    Notificacao::create(['user_id' => $outro->id, 'titulo' => 'Alheia', 'mensagem' => 'texto']);

    $this->actingAs($eu, 'jwt')
        ->getJson('/api/notificacoes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('nao_lidas', 1);

    $this->actingAs($eu, 'jwt')->postJson('/api/notificacoes/lidas')->assertOk();

    expect(Notificacao::where('user_id', $eu->id)->whereNull('lida_em')->count())->toBe(0);
    expect(Notificacao::where('user_id', $outro->id)->whereNull('lida_em')->count())->toBe(1);
});
