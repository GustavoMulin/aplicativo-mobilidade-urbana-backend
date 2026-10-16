<?php

use App\Models\Corrida;
use App\Models\CorridaDestino;
use App\Models\CorridaFinanceiro;
use App\Models\DispositivoPush;
use App\Models\Motorista;
use App\Models\MotoristaVeiculo;
use App\Models\Notificacao;
use App\Models\Passageiro;
use App\Models\StatusBusca;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\DespachoCorridaService;
use App\Services\EnviarPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// conta de serviço de mentira, com chave RSA de verdade para a assinatura
function credenciaisFirebaseDeTeste(): string
{
    $chave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($chave, $privada);
    $arquivo = tempnam(sys_get_temp_dir(), 'firebase');
    file_put_contents($arquivo, json_encode([
        'project_id' => 'projeto-teste',
        'client_email' => 'push@projeto-teste.iam.gserviceaccount.com',
        'private_key' => $privada,
    ]));

    return $arquivo;
}

function firebaseRespondendo(int $statusDoEnvio = 200, array $respostaDoEnvio = ['name' => 'projects/projeto-teste/messages/1']): void
{
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-de-acesso', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response($respostaDoEnvio, $statusDoEnvio),
    ]);
}

function motoristaOnlineParaPush(): Motorista
{
    $motorista = Motorista::create(['user_id' => User::factory()->create()->id, 'status' => 'ativo']);
    $veiculo = Veiculo::create([
        'marca' => 'Chevrolet',
        'modelo' => 'Onix',
        'ano_fabricacao' => 2023,
        'ano_modelo' => 2023,
        'cor' => 'Prata',
        'placa' => 'PSH'.random_int(1000, 9999),
        'renavam' => (string) random_int(10000000000, 99999999999),
        'cpf_cnpj_proprietario' => '12345678909',
        'categoria' => 'carro',
        'status' => 'aprovado',
        'uf' => 'RO',
    ]);
    MotoristaVeiculo::create(['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id]);
    StatusBusca::create([
        'motorista_id' => $motorista->id,
        'veiculo_id' => $veiculo->id,
        'disponivel' => true,
        'latitude' => -8.761160,
        'longitude' => -63.900430,
        'visto_em' => now(),
    ]);

    return $motorista;
}

function corridaSolicitadaParaPush(Passageiro $passageiro): Corrida
{
    $corrida = Corrida::create([
        'codigo_corrida' => 'PUSH-'.Str::upper(Str::random(12)),
        'passageiro_id' => $passageiro->id,
        'status_corrida' => 'solicitada',
        'tempo_solicitacao' => now(),
        'distancia_total' => 7.5,
        'metodo_pagamento' => 'dinheiro',
        'status_pagamento' => 'pendente',
    ]);
    CorridaDestino::create([
        'corrida_id' => $corrida->id,
        'nome_local' => 'Embarque',
        'tipo' => 'origem',
        'ordem' => 0,
        'endereco' => 'Embarque',
        'latitude' => -8.760160,
        'longitude' => -63.900430,
    ]);
    CorridaDestino::create([
        'corrida_id' => $corrida->id,
        'nome_local' => 'Destino',
        'tipo' => 'destino',
        'ordem' => 1,
        'endereco' => 'Destino',
        'latitude' => -8.701000,
        'longitude' => -63.910000,
    ]);
    CorridaFinanceiro::create([
        'corrida_id' => $corrida->id,
        'valor_motorista' => 18.75,
        'valor_pago_passageiro' => 22.00,
        'metodo_pagamento' => 'dinheiro',
    ]);

    return $corrida;
}

beforeEach(function () {
    Cache::flush();
});

it('o app registra o aparelho e tira ao sair', function () {
    $usuario = User::factory()->create();
    $this->actingAs($usuario, 'jwt');

    $this->postJson('/api/dispositivos-push', ['token' => 'token-fcm-1', 'perfil' => 'passageiro'])->assertCreated();
    $this->postJson('/api/dispositivos-push', ['token' => 'token-fcm-1', 'perfil' => 'passageiro'])->assertCreated();

    expect(DispositivoPush::where('token', 'token-fcm-1')->count())->toBe(1)
        ->and(DispositivoPush::first()->user_id)->toBe($usuario->id);

    $this->deleteJson('/api/dispositivos-push', ['token' => 'token-fcm-1'])->assertNoContent();
    expect(DispositivoPush::count())->toBe(0);
});

it('o aparelho passa para a conta que entrou nele', function () {
    $antiga = User::factory()->create();
    $nova = User::factory()->create();
    DispositivoPush::create(['user_id' => $antiga->id, 'perfil' => 'passageiro', 'token' => 'token-fcm-1']);

    $this->actingAs($nova, 'jwt')
        ->postJson('/api/dispositivos-push', ['token' => 'token-fcm-1', 'perfil' => 'motorista'])
        ->assertCreated();

    expect(DispositivoPush::sole())->user_id->toBe($nova->id)->perfil->toBe('motorista');
});

it('recusa perfil desconhecido e exige login', function () {
    $this->postJson('/api/dispositivos-push', ['token' => 'x', 'perfil' => 'passageiro'])->assertUnauthorized();

    $this->actingAs(User::factory()->create(), 'jwt')
        ->postJson('/api/dispositivos-push', ['token' => 'x', 'perfil' => 'gestor'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('perfil');
});

it('avisa o passageiro pelo celular quando o motorista chega e quando a corrida começa', function () {
    firebaseRespondendo();
    config(['services.fcm.credenciais' => credenciaisFirebaseDeTeste()]);
    $motorista = motoristaOnlineParaPush();
    $passageiro = Passageiro::create(['user_id' => User::factory()->create()->id]);
    $corrida = corridaSolicitadaParaPush($passageiro);
    DispositivoPush::create(['user_id' => $passageiro->user_id, 'perfil' => 'passageiro', 'token' => 'token-do-passageiro']);
    DispositivoPush::create(['user_id' => $passageiro->user_id, 'perfil' => 'motorista', 'token' => 'outro-app']);

    $despacho = app(DespachoCorridaService::class);
    $despacho->aceitar($motorista, $corrida->id);
    $despacho->transicionar($motorista, $corrida->id, 'cheguei');
    $despacho->transicionar($motorista, $corrida->id, 'iniciar');
    app()->terminate();

    expect(Notificacao::where('user_id', $passageiro->user_id)->pluck('titulo')->all())
        ->toBe(['Motorista a caminho', 'Seu motorista chegou', 'Corrida iniciada']);

    $enviados = Http::recorded(fn (PedidoHttp $pedido) => str_contains($pedido->url(), 'fcm.googleapis.com'))->values();
    expect($enviados)->toHaveCount(3);

    [$pedido] = $enviados[1];
    expect($pedido->url())->toBe('https://fcm.googleapis.com/v1/projects/projeto-teste/messages:send')
        ->and($pedido->header('Authorization')[0])->toBe('Bearer token-de-acesso')
        ->and($pedido['message']['token'])->toBe('token-do-passageiro')
        ->and($pedido['message']['notification']['title'])->toBe('Seu motorista chegou')
        ->and($pedido['message']['data']['corrida_id'])->toBe((string) $corrida->id)
        ->and($pedido['message']['android']['notification']['channel_id'])->toBe('corridas');
});

it('sem a conta do Firebase a notificação fica só no app', function () {
    firebaseRespondendo();
    config(['services.fcm.credenciais' => null]);
    $motorista = motoristaOnlineParaPush();
    $passageiro = Passageiro::create(['user_id' => User::factory()->create()->id]);
    $corrida = corridaSolicitadaParaPush($passageiro);
    DispositivoPush::create(['user_id' => $passageiro->user_id, 'perfil' => 'passageiro', 'token' => 'token-do-passageiro']);

    app(DespachoCorridaService::class)->aceitar($motorista, $corrida->id);
    app()->terminate();

    expect(Notificacao::where('user_id', $passageiro->user_id)->count())->toBe(1);
    Http::assertNothingSent();
});

it('o cancelamento avisa o outro lado da corrida', function () {
    firebaseRespondendo();
    config(['services.fcm.credenciais' => credenciaisFirebaseDeTeste()]);
    $motorista = motoristaOnlineParaPush();
    $passageiro = Passageiro::create(['user_id' => User::factory()->create()->id]);
    $corrida = corridaSolicitadaParaPush($passageiro);
    DispositivoPush::create(['user_id' => $motorista->user_id, 'perfil' => 'motorista', 'token' => 'token-do-motorista']);

    $despacho = app(DespachoCorridaService::class);
    $despacho->aceitar($motorista, $corrida->id);
    $despacho->cancelar($corrida->id, 'passageiro', $passageiro->id, 'Mudei de ideia');
    app()->terminate();

    expect(Notificacao::where('user_id', $motorista->user_id)->where('titulo', 'Corrida cancelada')->exists())->toBeTrue()
        ->and(Notificacao::where('user_id', $passageiro->user_id)->where('titulo', 'Corrida cancelada')->exists())->toBeFalse();
    Http::assertSent(fn (PedidoHttp $pedido) => str_contains($pedido->url(), 'fcm.googleapis.com')
        && $pedido['message']['token'] === 'token-do-motorista');
});

it('esquece o aparelho que o FCM diz não existir mais', function () {
    config(['services.fcm.credenciais' => credenciaisFirebaseDeTeste()]);
    firebaseRespondendo(404, ['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]]);
    $usuario = User::factory()->create();
    DispositivoPush::create(['user_id' => $usuario->id, 'perfil' => 'passageiro', 'token' => 'token-velho']);

    app(EnviarPushService::class)->paraUsuario($usuario->id, 'passageiro', 'Teste', 'Mensagem');

    expect(DispositivoPush::count())->toBe(0);
});
