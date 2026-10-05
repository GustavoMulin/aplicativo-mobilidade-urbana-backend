<?php

use App\Models\CobrancaCartao;
use App\Models\Corrida;
use App\Models\CorridaFinanceiro;
use App\Models\Passageiro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['abacatepay.api_key' => 'abc_dev_teste']);
    config(['abacatepay.base_url' => 'https://api.abacatepay.com']);
});

function passageiroCartao(): Passageiro
{
    $id = str_replace('-', '', (string) Str::uuid());
    $user = User::create([
        'name' => 'Passageiro Cartão',
        'telefone' => substr('69'.preg_replace('/\D/', '', $id).'000000000', 0, 11),
        'cpf' => '52998224725',
        'data_nascimento' => '1990-01-01',
        'email' => "cartao-$id@example.test",
        'foto' => null,
        'foto_thumbnail' => null,
        'status' => 'ativo',
        'password' => 'senha-de-teste',
    ]);

    return Passageiro::create(['user_id' => $user->id, 'media_avaliacao' => null]);
}

function corridaCartao(Passageiro $passageiro, string $status = 'finalizada', string $metodo = 'cartao'): Corrida
{
    $corrida = Corrida::create([
        'codigo_corrida' => 'CRT-'.Str::upper(Str::random(8)),
        'passageiro_id' => $passageiro->id,
        'status_corrida' => $status,
        'tempo_solicitacao' => now()->subHour(),
        'metodo_pagamento' => $metodo,
        'status_pagamento' => 'pendente',
    ]);
    CorridaFinanceiro::create([
        'corrida_id' => $corrida->id,
        'valor_pago_passageiro' => 18.9,
        'valor_motorista' => 17.0,
        'valor_liquido_motorista' => 17.0,
        'metodo_pagamento' => $metodo,
    ]);

    return $corrida;
}

function fakeCheckoutAbacate(string $statusCheckout = 'PENDING'): void
{
    Http::fake([
        'api.abacatepay.com/v2/products/create' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'prod_teste', 'devMode' => true]]),
        'api.abacatepay.com/v2/checkouts/create' => Http::response(['success' => true, 'error' => null, 'data' => [
            'id' => 'bill_teste', 'url' => 'https://app.abacatepay.com/pay/bill_teste', 'status' => 'PENDING', 'devMode' => true, 'amount' => 1890,
        ]]),
        'api.abacatepay.com/v2/checkouts/get*' => Http::response(['success' => true, 'error' => null, 'data' => [
            'id' => 'bill_teste', 'status' => $statusCheckout, 'url' => 'https://app.abacatepay.com/pay/bill_teste',
        ]]),
    ]);
}

it('cria produto e checkout só com cartão e devolve o link de pagamento', function () {
    fakeCheckoutAbacate();
    $passageiro = passageiroCartao();
    $corrida = corridaCartao($passageiro);

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/cartao")
        ->assertOk()
        ->assertJsonPath('cobranca.url', 'https://app.abacatepay.com/pay/bill_teste')
        ->assertJsonPath('cobranca.valor', 18.9);

    Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/checkouts/create')
        && $req['methods'] === ['CARD']
        && $req['items'][0]['id'] === 'prod_teste');
    Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/products/create') && $req['price'] === 1890);
    expect(CobrancaCartao::count())->toBe(1);
});

it('reaproveita o checkout pendente da corrida', function () {
    fakeCheckoutAbacate();
    $passageiro = passageiroCartao();
    $corrida = corridaCartao($passageiro);

    $this->actingAs($passageiro->user, 'jwt')->postJson("/api/corridas/{$corrida->id}/cartao")->assertOk();
    $this->actingAs($passageiro->user, 'jwt')->postJson("/api/corridas/{$corrida->id}/cartao")->assertOk();

    expect(CobrancaCartao::count())->toBe(1);
    Http::assertSentCount(2);
});

it('marca a corrida como paga quando o checkout é pago', function () {
    fakeCheckoutAbacate('PAID');
    $passageiro = passageiroCartao();
    $corrida = corridaCartao($passageiro);
    $this->actingAs($passageiro->user, 'jwt')->postJson("/api/corridas/{$corrida->id}/cartao")->assertOk();

    $this->actingAs($passageiro->user, 'jwt')
        ->getJson("/api/corridas/{$corrida->id}/cartao")
        ->assertOk()
        ->assertJsonPath('cobranca.status', 'PAID');

    expect($corrida->fresh()->status_pagamento)->toBe('pago');
});

it('recusa cartão para corrida em outro método ou ainda não finalizada', function () {
    Http::fake();
    $passageiro = passageiroCartao();
    $pix = corridaCartao($passageiro, 'finalizada', 'pix');
    $andamento = corridaCartao($passageiro, 'em_andamento');

    $this->actingAs($passageiro->user, 'jwt')->postJson("/api/corridas/{$pix->id}/cartao")->assertStatus(409);
    $this->actingAs($passageiro->user, 'jwt')->postJson("/api/corridas/{$andamento->id}/cartao")->assertStatus(409);

    Http::assertNothingSent();
});
