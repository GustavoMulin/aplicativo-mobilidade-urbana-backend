<?php

use App\Events\CorridaAtualizada;
use App\Models\Corrida;
use App\Models\CorridaAlteracaoDestino;
use App\Models\CorridaDestino;
use App\Models\CorridaFinanceiro;
use App\Models\Motorista;
use App\Models\Passageiro;
use App\Models\ProdutosCorrida;
use App\Models\Tarifa;
use App\Models\User;
use App\Services\EstimarRotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // rota da nova viagem: 10 km e 20 min, sem chamar o Google
    $this->mock(EstimarRotaService::class, function ($mock) {
        $mock->shouldReceive('executar')->andReturn([
            'distancia_km' => 10.0,
            'tempo_minutos' => 20.0,
        ]);
    });
});

afterEach(function () {
    Carbon::setTestNow();
});

function criarUsuarioAlteracao(string $papel): User
{
    $id = str_replace('-', '', (string) Str::uuid());

    return User::create([
        'name' => ucfirst($papel).' Alteração',
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

/**
 * @return array{Corrida, Passageiro, Motorista}
 */
function criarCorridaAlteravel(
    string $status = 'aceita',
    string $pagamento = 'dinheiro',
    string $estrategia = 'normal',
): array {
    $motorista = Motorista::create([
        'user_id' => criarUsuarioAlteracao('motorista')->id,
        'status' => 'aprovado',
        'cnh_numero' => null,
        'cnh_categoria' => null,
        'cnh_expiracao' => null,
        'ear' => null,
    ]);
    $passageiro = Passageiro::create([
        'user_id' => criarUsuarioAlteracao('passageiro')->id,
        'media_avaliacao' => null,
    ]);
    $produto = ProdutosCorrida::firstOrCreate(
        ['codigo' => $estrategia === 'negociada' ? 'negocia' : 'pop'],
        [
            'nome' => $estrategia === 'negociada' ? 'Negocia' : 'Pop',
            'estrategia_precificacao' => $estrategia,
        ],
    );
    $tarifa = Tarifa::create([
        'produto_id' => $produto->id,
        'horario_inicio' => '00:00:00',
        'horario_fim' => '23:59:59',
        'dias_semana' => '[1,2,3,4,5,6,7]',
        'vira_dia' => false,
        'valor_minimo_corrida' => 8.00,
        'tarifa_base' => 2.00,
        'valor_por_km' => 1.55,
        'valor_por_minuto' => 0.28,
        'valor_por_minuto_espera' => 0.30,
        'taxa_plataforma_percentual' => 6.00,
        'raio_busca_motorista_km' => 5,
        'ativo' => true,
    ]);
    $corrida = Corrida::create([
        'codigo_corrida' => 'ALT-'.Str::upper(Str::random(8)),
        'produto_id' => $produto->id,
        'motorista_id' => $status === 'solicitada' ? null : $motorista->id,
        'passageiro_id' => $passageiro->id,
        'tarifa_id' => $tarifa->id,
        'status_corrida' => $status,
        'tempo_solicitacao' => now()->subMinutes(10),
        'distancia_total' => 5,
        'metodo_pagamento' => $pagamento,
        'status_pagamento' => 'pendente',
    ]);

    foreach ([['origem', 'Embarque', -8.76], ['destino', 'Destino antigo', -8.74]] as $ordem => [$tipo, $endereco, $latitude]) {
        CorridaDestino::create([
            'corrida_id' => $corrida->id,
            'nome_local' => $endereco,
            'tipo' => $tipo,
            'ordem' => $ordem,
            'endereco' => $endereco,
            'latitude' => $latitude,
            'longitude' => -63.90,
        ]);
    }

    CorridaFinanceiro::create([
        'corrida_id' => $corrida->id,
        'valor_bruto' => 12.00,
        'tarifa_base' => 2.00,
        'taxa_espera' => 0.60,
        'valor_motorista' => 12.60,
        'valor_liquido_motorista' => 12.60,
        'valor_pago_passageiro' => 13.40,
        'taxa_plataforma_valor' => 0.80,
        'taxa_plataforma_percentual' => 6.00,
        'metodo_pagamento' => $pagamento,
    ]);

    return [$corrida, $passageiro, $motorista];
}

$novoDestino = [
    'endereco' => 'Destino novo, 123',
    'latitude' => -8.70,
    'longitude' => -63.88,
];

it('troca o pagamento uma única vez e avisa a corrida', function () {
    Event::fake([CorridaAtualizada::class]);
    [$corrida, $passageiro] = criarCorridaAlteravel('aceita', 'dinheiro');

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/pagamento", ['metodo_pagamento' => 'pix'])
        ->assertOk()
        ->assertJsonPath('metodo_pagamento', 'pix')
        ->assertJsonPath('corrida_financeiro.metodo_pagamento', 'pix')
        ->assertJsonPath('pagamento_alteravel', false);

    Event::assertDispatched(CorridaAtualizada::class);

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/pagamento", ['metodo_pagamento' => 'cartao'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'O pagamento só pode ser trocado uma vez por corrida.');
});

it('durante a viagem só troca entre pix e cartão', function () {
    [$emDinheiro, $passageiro] = criarCorridaAlteravel('em_andamento', 'dinheiro');

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$emDinheiro->id}/pagamento", ['metodo_pagamento' => 'pix'])
        ->assertStatus(409);

    [$emPix, $outroPassageiro] = criarCorridaAlteravel('em_andamento', 'pix');

    $this->actingAs($outroPassageiro->user, 'jwt')
        ->postJson("/api/corridas/{$emPix->id}/pagamento", ['metodo_pagamento' => 'cartao'])
        ->assertOk()
        ->assertJsonPath('metodo_pagamento', 'cartao');
});

it('não troca o pagamento de corrida encerrada nem de outro passageiro', function () {
    [$finalizada, $passageiro] = criarCorridaAlteravel('finalizada', 'dinheiro');
    [$alheia] = criarCorridaAlteravel('aceita', 'dinheiro');

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$finalizada->id}/pagamento", ['metodo_pagamento' => 'pix'])
        ->assertStatus(409);

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$alheia->id}/pagamento", ['metodo_pagamento' => 'pix'])
        ->assertNotFound();
});

it('não permite alterar o trajeto depois que a corrida termina', function () use ($novoDestino) {
    foreach (['finalizada', 'cancelada'] as $status) {
        [$corrida, $passageiro] = criarCorridaAlteravel($status);

        $this->actingAs($passageiro->user, 'jwt')
            ->postJson("/api/corridas/{$corrida->id}/destino", $novoDestino)
            ->assertStatus(409)
            ->assertJsonPath('message', 'A corrida já terminou e o trajeto não pode mais ser alterado.');
    }

    expect(CorridaAlteracaoDestino::count())->toBe(0);
});

it('antes do aceite altera o destino imediatamente e recalcula a oferta', function () use ($novoDestino) {
    [$corrida, $passageiro] = criarCorridaAlteravel('em_busca');

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", $novoDestino)
        ->assertOk()
        ->assertJsonPath('alteracao_destino.status', 'aplicada');

    expect($corrida->corrida_destinos()->where('tipo', 'destino')->value('endereco'))
        ->toBe('Destino novo, 123');
});

it('edita o itinerário completo com paradas antes de encontrar motorista', function () {
    [$corrida, $passageiro] = criarCorridaAlteravel('em_busca');

    $itinerario = [
        ['endereco' => 'Origem que não deve mudar', 'latitude' => -8.70, 'longitude' => -63.80],
        ['endereco' => 'Parada nova, 50', 'latitude' => -8.73, 'longitude' => -63.89],
        ['endereco' => 'Destino editado, 200', 'latitude' => -8.71, 'longitude' => -63.87],
    ];

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", [
            ...$itinerario[2],
            'itinerario' => $itinerario,
        ])
        ->assertOk()
        ->assertJsonPath('alteracao_destino.status', 'aplicada');

    $pontos = $corrida->corrida_destinos()->orderBy('ordem')->get();

    expect($pontos)->toHaveCount(3)
        ->and($pontos[0]->tipo)->toBe('origem')
        ->and($pontos[0]->endereco)->toBe('Embarque')
        ->and($pontos[1]->tipo)->toBe('parada')
        ->and($pontos[1]->endereco)->toBe('Parada nova, 50')
        ->and($pontos[2]->tipo)->toBe('destino')
        ->and($pontos[2]->endereco)->toBe('Destino editado, 200');
});

it('na viagem o novo trajeto mantém a parada já feita como concluída', function () {
    [$corrida, $passageiro, $motorista] = criarCorridaAlteravel('em_andamento');
    $corrida->corrida_destinos()->where('tipo', 'destino')->update(['ordem' => 2]);
    $corrida->corrida_destinos()->create([
        'nome_local' => 'Parada feita',
        'tipo' => 'parada',
        'ordem' => 1,
        'endereco' => 'Parada feita',
        'latitude' => -8.75,
        'longitude' => -63.90,
        'concluida_em' => now()->subMinute(),
    ]);

    $itinerario = [
        ['endereco' => 'Embarque', 'latitude' => -8.76, 'longitude' => -63.90],
        ['endereco' => 'Parada feita', 'latitude' => -8.75, 'longitude' => -63.90],
        ['endereco' => 'Destino antigo', 'latitude' => -8.74, 'longitude' => -63.90],
        ['endereco' => 'Destino depois, 9', 'latitude' => -8.71, 'longitude' => -63.87],
    ];

    $pedido = $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", [...$itinerario[3], 'itinerario' => $itinerario])
        ->assertOk()
        ->assertJsonPath('alteracao_destino.status', 'pendente')
        ->json('alteracao_destino');

    $this->actingAs($motorista->user, 'jwt')
        ->getJson('/api/minha-corrida-atual?perfil=motorista')
        ->assertJsonPath('alteracao_destino.paradas', ['Parada feita', 'Destino antigo', 'Destino depois, 9']);

    $this->actingAs($motorista->user, 'jwt')
        ->postJson("/api/motorista/corridas/{$corrida->id}/destino/{$pedido['id']}/aceitar")
        ->assertOk();

    $pontos = $corrida->corrida_destinos()->orderBy('ordem')->get();

    expect($pontos->pluck('tipo')->all())->toBe(['origem', 'parada', 'parada', 'destino'])
        ->and($pontos[1]->concluida_em)->not->toBeNull()
        ->and($pontos[2]->concluida_em)->toBeNull()
        ->and($pontos[3]->endereco)->toBe('Destino depois, 9');
});

it('não deixa tirar nem reordenar a parada já feita para baratear a viagem', function () {
    [$corrida, $passageiro] = criarCorridaAlteravel('em_andamento');
    $corrida->corrida_destinos()->where('tipo', 'destino')->update(['ordem' => 2]);
    $corrida->corrida_destinos()->create([
        'nome_local' => 'Parada feita',
        'tipo' => 'parada',
        'ordem' => 1,
        'endereco' => 'Parada feita',
        'latitude' => -8.75,
        'longitude' => -63.90,
        'concluida_em' => now()->subMinute(),
    ]);
    $embarque = ['endereco' => 'Embarque', 'latitude' => -8.76, 'longitude' => -63.90];
    $feita = ['endereco' => 'Parada feita', 'latitude' => -8.75, 'longitude' => -63.90];
    $outra = ['endereco' => 'Outra, 1', 'latitude' => -8.72, 'longitude' => -63.88];
    $destino = ['endereco' => 'Destino antigo', 'latitude' => -8.74, 'longitude' => -63.90];

    foreach ([[$embarque, $destino], [$embarque, $outra, $feita, $destino]] as $itinerario) {
        $this->actingAs($passageiro->user, 'jwt')
            ->postJson("/api/corridas/{$corrida->id}/destino", [...end($itinerario), 'itinerario' => $itinerario])
            ->assertStatus(422)
            ->assertJsonPath('message', 'As paradas já feitas continuam no trajeto, na mesma ordem.');
    }

    expect(CorridaAlteracaoDestino::count())->toBe(0);
});

it('não passa do limite de paradas pedindo uma de cada vez', function () use ($novoDestino) {
    [$corrida, $passageiro] = criarCorridaAlteravel('em_busca');
    $corrida->corrida_destinos()->where('tipo', 'destino')->update(['ordem' => 5]);
    foreach (range(1, 4) as $ordem) {
        $corrida->corrida_destinos()->create([
            'nome_local' => "Parada {$ordem}",
            'tipo' => 'parada',
            'ordem' => $ordem,
            'endereco' => "Parada {$ordem}",
            'latitude' => -8.75 + $ordem / 1000,
            'longitude' => -63.90,
        ]);
    }

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", [...$novoDestino, 'tipo' => 'parada'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A corrida já tem o máximo de 4 paradas.');
});

it('limita quantas vezes o trajeto pode ser alterado na mesma corrida', function () use ($novoDestino) {
    [$corrida, $passageiro] = criarCorridaAlteravel('aceita');

    foreach (range(1, 6) as $vez) {
        $this->actingAs($passageiro->user, 'jwt')
            ->postJson("/api/corridas/{$corrida->id}/destino", [...$novoDestino, 'latitude' => -8.70 + $vez / 1000])
            ->assertOk();
    }

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", $novoDestino)
        ->assertStatus(429)
        ->assertJsonPath('message', 'O trajeto desta corrida já foi alterado muitas vezes.');
});

it('depois do aceite o novo destino espera a aprovação do motorista', function () use ($novoDestino) {
    Event::fake([CorridaAtualizada::class]);
    [$corrida, $passageiro, $motorista] = criarCorridaAlteravel('aceita');

    $pedido = $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", $novoDestino)
        ->assertOk()
        ->assertJsonPath('alteracao_destino.status', 'pendente')
        ->assertJsonPath('alteracao_destino.endereco', 'Destino novo, 123')
        ->assertJsonPath('alteracao_destino.valor_passageiro', 25.21)
        ->json('alteracao_destino');

    expect($corrida->corrida_destinos()->where('tipo', 'destino')->value('endereco'))->toBe('Destino antigo');

    $this->actingAs($motorista->user, 'jwt')
        ->getJson('/api/minha-corrida-atual?perfil=motorista')
        ->assertOk()
        ->assertJsonPath('alteracao_destino.id', $pedido['id'])
        ->assertJsonPath('alteracao_destino.valor_motorista', 23.70);

    $this->actingAs($motorista->user, 'jwt')
        ->postJson("/api/motorista/corridas/{$corrida->id}/destino/{$pedido['id']}/aceitar")
        ->assertOk()
        ->assertJsonPath('alteracao_destino.status', 'aceita');

    expect($corrida->corrida_destinos()->where('tipo', 'destino')->value('endereco'))->toBe('Destino novo, 123')
        ->and((float) $corrida->corrida_financeiro()->value('valor_pago_passageiro'))->toBe(25.21);
    Event::assertDispatchedTimes(CorridaAtualizada::class, 2);
});

it('motorista pode recusar o novo destino', function () use ($novoDestino) {
    [$corrida, $passageiro, $motorista] = criarCorridaAlteravel('em_andamento');

    $pedido = $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", $novoDestino)
        ->json('alteracao_destino');

    $this->actingAs($motorista->user, 'jwt')
        ->postJson("/api/motorista/corridas/{$corrida->id}/destino/{$pedido['id']}/recusar")
        ->assertOk()
        ->assertJsonPath('alteracao_destino.status', 'recusada');

    expect($corrida->corrida_destinos()->where('tipo', 'destino')->value('endereco'))->toBe('Destino antigo')
        ->and((float) $corrida->corrida_financeiro()->value('valor_pago_passageiro'))->toBe(13.40);

    $this->actingAs($passageiro->user, 'jwt')
        ->getJson('/api/minha-corrida-atual?perfil=passageiro')
        ->assertJsonPath('alteracao_destino.status', 'recusada');
});

it('pedido sem resposta expira em dois minutos', function () use ($novoDestino) {
    Carbon::setTestNow('2026-09-30 12:00:00');
    [$corrida, $passageiro, $motorista] = criarCorridaAlteravel('em_andamento');

    $pedido = $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", $novoDestino)
        ->json('alteracao_destino');

    Carbon::setTestNow('2026-09-30 12:02:01');

    $this->actingAs($motorista->user, 'jwt')
        ->postJson("/api/motorista/corridas/{$corrida->id}/destino/{$pedido['id']}/aceitar")
        ->assertStatus(409);

    expect(CorridaAlteracaoDestino::find($pedido['id'])->status)->toBe('expirada')
        ->and($corrida->corrida_destinos()->where('tipo', 'destino')->value('endereco'))->toBe('Destino antigo');
});

it('em categoria negociada o trajeto não muda durante a viagem', function () use ($novoDestino) {
    [$corrida, $passageiro] = criarCorridaAlteravel('em_andamento', 'dinheiro', 'negociada');

    $this->actingAs($passageiro->user, 'jwt')
        ->getJson('/api/minha-corrida-atual?perfil=passageiro')
        ->assertJsonPath('corrida.destino_alteravel', false)
        ->assertJsonPath('corrida.pagamento_alteravel', true);

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", $novoDestino)
        ->assertStatus(409);

    expect(CorridaAlteracaoDestino::count())->toBe(0);
});

it('passageiro desiste do pedido de novo destino', function () use ($novoDestino) {
    [$corrida, $passageiro, $motorista] = criarCorridaAlteravel('em_andamento');

    $pedido = $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", $novoDestino)
        ->json('alteracao_destino');

    $this->actingAs($passageiro->user, 'jwt')
        ->deleteJson("/api/corridas/{$corrida->id}/destino")
        ->assertOk()
        ->assertJsonPath('alteracao_destino.status', 'cancelada');

    $this->actingAs($motorista->user, 'jwt')
        ->postJson("/api/motorista/corridas/{$corrida->id}/destino/{$pedido['id']}/aceitar")
        ->assertStatus(409);
});

it('adiciona uma parada antes do destino somente depois do aceite do motorista', function () use ($novoDestino) {
    [$corrida, $passageiro, $motorista] = criarCorridaAlteravel('em_andamento');

    $pedido = $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", $novoDestino + ['tipo' => 'parada'])
        ->assertOk()
        ->assertJsonPath('alteracao_destino.status', 'pendente')
        ->assertJsonPath('alteracao_destino.tipo', 'parada')
        ->json('alteracao_destino');

    expect($corrida->corrida_destinos()->where('tipo', 'parada')->count())->toBe(0);

    $this->actingAs($motorista->user, 'jwt')
        ->postJson("/api/motorista/corridas/{$corrida->id}/destino/{$pedido['id']}/aceitar")
        ->assertOk()
        ->assertJsonPath('alteracao_destino.status', 'aceita')
        ->assertJsonPath('alteracao_destino.tipo', 'parada');

    $pontos = $corrida->corrida_destinos()->orderBy('ordem')->get();

    expect($pontos)->toHaveCount(3)
        ->and($pontos[0]->tipo)->toBe('origem')
        ->and($pontos[1]->tipo)->toBe('parada')
        ->and($pontos[1]->endereco)->toBe('Destino novo, 123')
        ->and($pontos[2]->tipo)->toBe('destino')
        ->and($pontos[2]->endereco)->toBe('Destino antigo');
});
