<?php

use App\Events\CorridaAtualizada;
use App\Events\CorridasDisponiveisAlteradas;
use App\Models\CobrancaPix;
use App\Models\Corrida;
use App\Models\CorridaFinanceiro;
use App\Models\CotacaoCorrida;
use App\Models\MovimentoCredito;
use App\Models\Passageiro;
use App\Models\User;
use App\Services\PagamentoCorridaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'abacatepay.api_key' => 'abc_dev_teste',
        'abacatepay.base_url' => 'https://api.abacatepay.com',
        'abacatepay.validade_segundos' => 900,
        'abacatepay.webhook_secret' => 'segredo-teste',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function passageiroPre(): Passageiro
{
    $id = str_replace('-', '', (string) Str::uuid());
    $user = User::create([
        'name' => 'Passageiro Pré',
        'telefone' => substr('69'.preg_replace('/\D/', '', $id).'000000000', 0, 11),
        'cpf' => substr(preg_replace('/\D/', '', $id).'00000000000', 0, 11),
        'data_nascimento' => '1990-01-01',
        'email' => "pre-$id@example.test",
        'foto' => null,
        'foto_thumbnail' => null,
        'status' => 'ativo',
        'password' => 'senha-de-teste',
    ]);

    return Passageiro::create(['user_id' => $user->id, 'media_avaliacao' => null]);
}

function cotacaoPre(Passageiro $passageiro, float $valor = 20.0): CotacaoCorrida
{
    return CotacaoCorrida::create([
        'user_id' => $passageiro->user_id,
        'distancia_km' => 5,
        'tempo_min' => 12,
        'enderecos' => [
            ['order' => 0, 'latitude' => -8.76, 'longitude' => -63.90, 'formattedAddress' => 'Origem'],
            ['order' => 1, 'latitude' => -8.74, 'longitude' => -63.88, 'formattedAddress' => 'Destino'],
        ],
        'categorias' => [[
            'tarifa_id' => null,
            'produto' => ['id' => null, 'codigo' => 'pop', 'nome' => 'Pop'],
            'composicao' => [
                'subtotal' => $valor,
                'tarifa_base' => 2,
                'valor_distancia' => 10,
                'valor_tempo' => 3,
                'valor_por_minuto_espera' => 0.3,
                'diferenca_negociada' => 0,
            ],
            'valores' => [
                'valor_passageiro' => $valor,
                'valor_motorista' => round($valor * 0.94, 2),
                'taxa_plataforma' => round($valor * 0.06, 2),
                'taxa_plataforma_percentual' => 6,
            ],
        ]],
        'expira_em' => now()->addMinutes(10),
    ]);
}

function pedirCorrida(Passageiro $passageiro, string $metodo, float $valor = 20.0)
{
    return test()->actingAs($passageiro->user, 'jwt')->postJson('/api/corridas', [
        'cotacao_id' => cotacaoPre($passageiro, $valor)->id,
        'produto_codigo' => 'pop',
        'metodo_pagamento' => $metodo,
    ]);
}

function respostaPix(string $status = 'PENDING', int $centavos = 2000, string $id = 'pix_char_pre'): array
{
    return ['success' => true, 'error' => null, 'data' => [
        'id' => $id,
        'amount' => $centavos,
        'status' => $status,
        'devMode' => true,
        'brCode' => '00020101devmode',
        'brCodeBase64' => 'data:image/png;base64,AAA',
        'expiresAt' => now()->addMinutes(15)->toIso8601String(),
    ]];
}

function corridaTerminada(Passageiro $passageiro, string $status, string $metodo, float $valor, array $extra = []): Corrida
{
    $taxa = $extra['__taxa'] ?? null;
    unset($extra['__taxa']);

    $corrida = Corrida::create(array_merge([
        'codigo_corrida' => 'LIQ-'.Str::upper(Str::random(8)),
        'passageiro_id' => $passageiro->id,
        'status_corrida' => $status,
        'tempo_solicitacao' => now()->subHour(),
        'metodo_pagamento' => $metodo,
        'status_pagamento' => 'pago',
    ], $extra));
    CorridaFinanceiro::create([
        'corrida_id' => $corrida->id,
        'valor_pago_passageiro' => $valor,
        'valor_motorista' => $valor,
        'valor_liquido_motorista' => $valor,
        'taxa_cancelamento' => $taxa,
        'metodo_pagamento' => $metodo,
    ]);

    return $corrida;
}

function pixPago(Corrida $corrida, float $valor, string $id = 'pix_char_pago'): CobrancaPix
{
    return CobrancaPix::create([
        'corrida_id' => $corrida->id,
        'charge_id' => $id,
        'status' => 'PAID',
        'valor_centavos' => (int) round($valor * 100),
        'br_code' => 'x',
        'br_code_base64' => 'x',
        'dev_mode' => true,
        'expira_em' => now()->addMinutes(10),
        'pago_em' => now(),
    ]);
}

it('pedido no Pix fica aguardando pagamento e não é oferecido aos motoristas', function () {
    Event::fake([CorridasDisponiveisAlteradas::class]);
    Http::fake(['api.abacatepay.com/v2/transparents/create' => Http::response(respostaPix())]);
    $passageiro = passageiroPre();

    pedirCorrida($passageiro, 'pix')
        ->assertCreated()
        ->assertJsonPath('status_corrida', 'aguardando_pagamento');

    expect(CobrancaPix::count())->toBe(1)
        ->and(Corrida::where('status_corrida', 'solicitada')->count())->toBe(0);
    Event::assertNotDispatched(CorridasDisponiveisAlteradas::class);
});

it('confirmado o Pix, a corrida entra na busca de motoristas', function () {
    Event::fake([CorridasDisponiveisAlteradas::class, CorridaAtualizada::class]);
    Http::fake([
        'api.abacatepay.com/v2/transparents/create' => Http::response(respostaPix()),
        'api.abacatepay.com/v2/transparents/check*' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'pix_char_pre', 'status' => 'PAID']]),
    ]);
    $passageiro = passageiroPre();
    $corridaId = pedirCorrida($passageiro, 'pix')->json('id');

    $this->actingAs($passageiro->user, 'jwt')
        ->getJson("/api/corridas/{$corridaId}/pix")
        ->assertOk()
        ->assertJsonPath('cobranca.status', 'PAID');

    $corrida = Corrida::find($corridaId);
    expect($corrida->status_corrida)->toBe('solicitada')
        ->and($corrida->status_pagamento)->toBe('pago');
    Event::assertDispatched(CorridasDisponiveisAlteradas::class);
});

it('dinheiro continua indo direto para a busca, sem cobrança no app', function () {
    Http::fake();
    $passageiro = passageiroPre();

    pedirCorrida($passageiro, 'dinheiro')->assertCreated()->assertJsonPath('status_corrida', 'solicitada');

    Http::assertNothingSent();
});

it('valor em aberto de corrida anterior bloqueia novo pedido até ser pago', function () {
    Http::fake();
    $passageiro = passageiroPre();
    $anterior = corridaTerminada($passageiro, 'finalizada', 'pix', 18.0, ['status_pagamento' => 'em_aberto']);

    pedirCorrida($passageiro, 'dinheiro')
        ->assertStatus(409)
        ->assertJsonPath('codigo', 'pagamento_pendente')
        ->assertJsonPath('pendencia.corrida_id', $anterior->id)
        ->assertJsonPath('pendencia.valor', 18);

    $this->actingAs($passageiro->user, 'jwt')
        ->getJson('/api/pagamentos/situacao')
        ->assertOk()
        ->assertJsonPath('pendencia.corrida_id', $anterior->id);

    Http::assertNothingSent();
});

it('crédito que cobre a corrida libera a busca sem gerar cobrança', function () {
    Http::fake();
    $passageiro = passageiroPre();
    MovimentoCredito::create(['passageiro_id' => $passageiro->id, 'valor' => 25, 'descricao' => 'teste']);

    pedirCorrida($passageiro, 'pix', 20.0)->assertCreated()->assertJsonPath('status_corrida', 'solicitada');

    expect(app(PagamentoCorridaService::class)->saldoCredito($passageiro->id))->toBe(5.0);
    Http::assertNothingSent();
});

it('crédito parcial é abatido do valor cobrado no Pix', function () {
    Http::fake(['api.abacatepay.com/v2/transparents/create' => Http::response(respostaPix('PENDING', 1200))]);
    $passageiro = passageiroPre();
    MovimentoCredito::create(['passageiro_id' => $passageiro->id, 'valor' => 8, 'descricao' => 'teste']);

    pedirCorrida($passageiro, 'pix', 20.0)->assertCreated()->assertJsonPath('status_corrida', 'aguardando_pagamento');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'transparents/create') && $req['data']['amount'] === 1200);
});

it('falha ao gerar o pagamento cancela o pedido e devolve o crédito', function () {
    Http::fake(['api.abacatepay.com/*' => Http::response(['success' => false, 'error' => 'falhou'], 500)]);
    $passageiro = passageiroPre();
    MovimentoCredito::create(['passageiro_id' => $passageiro->id, 'valor' => 5, 'descricao' => 'teste']);

    pedirCorrida($passageiro, 'pix', 20.0)->assertStatus(409);

    expect(Corrida::first()->status_corrida)->toBe('cancelada')
        ->and(app(PagamentoCorridaService::class)->saldoCredito($passageiro->id))->toBe(5.0);
});

it('valor final maior que o pago vira pendência', function () {
    $passageiro = passageiroPre();
    $corrida = corridaTerminada($passageiro, 'finalizada', 'pix', 23.5);
    pixPago($corrida, 20.0);

    app(PagamentoCorridaService::class)->liquidar($corrida);

    expect($corrida->fresh()->status_pagamento)->toBe('em_aberto')
        ->and(app(PagamentoCorridaService::class)->valorDevido($corrida))->toBe(3.5);
});

it('valor final igual ao pago quita a corrida', function () {
    $passageiro = passageiroPre();
    $corrida = corridaTerminada($passageiro, 'finalizada', 'pix', 20.0);
    pixPago($corrida, 20.0);

    app(PagamentoCorridaService::class)->liquidar($corrida);

    expect($corrida->fresh()->status_pagamento)->toBe('pago');
});

it('cancelamento sem taxa de corrida já paga faz estorno integral', function () {
    Http::fake(['api.abacatepay.com/v2/transparents/refund' => Http::response(['success' => true, 'error' => null, 'data' => ['status' => 'COMPLETE']])]);
    $passageiro = passageiroPre();
    $corrida = corridaTerminada($passageiro, 'cancelada', 'pix', 20.0, ['cancelado_por' => 'motorista']);
    $pix = pixPago($corrida, 20.0);

    app(PagamentoCorridaService::class)->liquidar($corrida);

    expect($corrida->fresh()->status_pagamento)->toBe('estornado')
        ->and($pix->fresh()->estornado_em)->not->toBeNull();
    Http::assertSent(fn ($req) => str_contains($req->url(), 'transparents/refund') && $req['id'] === 'pix_char_pago');
});

it('cancelamento com taxa devolve a sobra como crédito', function () {
    Http::fake();
    $passageiro = passageiroPre();
    $corrida = corridaTerminada($passageiro, 'cancelada', 'pix', 4.0, [
        'tipo_cancelamento' => 'cancelamento_com_taxa',
        '__taxa' => 4.0,
    ]);
    pixPago($corrida, 20.0);

    app(PagamentoCorridaService::class)->liquidar($corrida);

    expect($corrida->fresh()->status_pagamento)->toBe('pago')
        ->and(app(PagamentoCorridaService::class)->saldoCredito($passageiro->id))->toBe(16.0);
    Http::assertNothingSent();
});

it('taxa de cancelamento de corrida em dinheiro vira pendência', function () {
    $passageiro = passageiroPre();
    $corrida = corridaTerminada($passageiro, 'cancelada', 'dinheiro', 5.0, [
        'tipo_cancelamento' => 'cancelamento_com_taxa',
        '__taxa' => 5.0,
    ]);

    app(PagamentoCorridaService::class)->liquidar($corrida);

    expect($corrida->fresh()->status_pagamento)->toBe('em_aberto');
});

it('corrida em dinheiro finalizada fica paga sem cobrança no app', function () {
    $passageiro = passageiroPre();
    $corrida = corridaTerminada($passageiro, 'finalizada', 'dinheiro', 15.0, ['status_pagamento' => 'pendente']);

    app(PagamentoCorridaService::class)->liquidar($corrida);

    expect($corrida->fresh()->status_pagamento)->toBe('pago');
});

it('passageiro cancela de graça enquanto o pagamento não foi feito', function () {
    Http::fake(['api.abacatepay.com/v2/transparents/create' => Http::response(respostaPix())]);
    $passageiro = passageiroPre();
    $corridaId = pedirCorrida($passageiro, 'pix')->json('id');

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corridaId}/cancelar", ['motivo' => 'desisti'])
        ->assertOk();

    $corrida = Corrida::find($corridaId);
    expect($corrida->status_corrida)->toBe('cancelada')
        ->and($corrida->status_pagamento)->toBe('sem_cobranca');
});

it('pedido não pago no prazo é cancelado; pago na última hora é liberado', function () {
    Carbon::setTestNow('2026-10-06 10:00:00');
    Http::fake([
        'api.abacatepay.com/v2/transparents/create' => Http::sequence()
            ->push(respostaPix('PENDING', 2000, 'pix_char_vencido'))
            ->push(respostaPix('PENDING', 2000, 'pix_char_pago_tarde')),
        'api.abacatepay.com/v2/transparents/check?id=pix_char_vencido' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'pix_char_vencido', 'status' => 'EXPIRED']]),
        'api.abacatepay.com/v2/transparents/check?id=pix_char_pago_tarde' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'pix_char_pago_tarde', 'status' => 'PAID']]),
    ]);
    $vencido = pedirCorrida(passageiroPre(), 'pix')->json('id');
    $pagoTarde = pedirCorrida(passageiroPre(), 'pix')->json('id');

    Carbon::setTestNow('2026-10-06 10:17:00');
    $canceladas = app(PagamentoCorridaService::class)->expirarPagamentosVencidos();

    expect($canceladas)->toBe(1)
        ->and(Corrida::find($vencido)->status_corrida)->toBe('cancelada')
        ->and(Corrida::find($pagoTarde)->status_corrida)->toBe('solicitada');
});

it('webhook só é aceito com o segredo e reconsulta a cobrança', function () {
    Http::fake([
        'api.abacatepay.com/v2/transparents/create' => Http::response(respostaPix()),
        'api.abacatepay.com/v2/transparents/check*' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'pix_char_pre', 'status' => 'PAID']]),
    ]);
    $corridaId = pedirCorrida(passageiroPre(), 'pix')->json('id');

    $this->postJson('/api/webhooks/abacatepay?webhookSecret=errado', ['event' => 'transparent.completed', 'data' => ['id' => 'pix_char_pre']])
        ->assertUnauthorized();
    expect(Corrida::find($corridaId)->status_corrida)->toBe('aguardando_pagamento');

    $this->postJson('/api/webhooks/abacatepay?webhookSecret=segredo-teste', ['event' => 'transparent.completed', 'data' => ['id' => 'pix_char_pre', 'status' => 'PAID']])
        ->assertOk();
    expect(Corrida::find($corridaId)->status_corrida)->toBe('solicitada');
});

it('no pré-pagamento só aceita o método escolhido no pedido', function () {
    Http::fake(['api.abacatepay.com/v2/transparents/create' => Http::response(respostaPix())]);
    $passageiro = passageiroPre();
    $corridaId = pedirCorrida($passageiro, 'pix')->json('id');

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corridaId}/cartao")
        ->assertStatus(409);
});
