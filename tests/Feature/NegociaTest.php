<?php

use App\Models\CobrancaPix;
use App\Models\Corrida;
use App\Models\CorridaNegociacoes;
use App\Models\CotacaoCorrida;
use App\Models\Motorista;
use App\Models\MotoristaVeiculo;
use App\Models\Passageiro;
use App\Models\ProdutosCorrida;
use App\Models\StatusBusca;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\DespachoCorridaService;
use App\Services\PagamentoCorridaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'abacatepay.api_key' => 'abc_dev_teste',
        'abacatepay.base_url' => 'https://api.abacatepay.com',
        'abacatepay.validade_segundos' => 900,
        'precificacao.negocia_proposta_validade_segundos' => 90,
        'precificacao.negocia_pagamento_limite_segundos' => 300,
    ]);

    ProdutosCorrida::create([
        'codigo' => 'negocia',
        'nome' => 'Negocia',
        'estrategia_precificacao' => 'negociada',
        'grupo' => 'negocia',
        'tipo_veiculo' => 'carro',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function negPixGerado(): void
{
    Http::fake([
        'api.abacatepay.com/v2/transparents/create' => Http::response(['success' => true, 'error' => null, 'data' => [
            'id' => 'pix_negocia',
            'amount' => 1100,
            'status' => 'PENDING',
            'devMode' => true,
            'brCode' => '00020101devmode',
            'brCodeBase64' => 'data:image/png;base64,AAA',
            'expiresAt' => now()->addMinutes(15)->toIso8601String(),
        ]]),
    ]);
}

function negUsuario(string $prefixo, string $nome = 'Teste Silva'): User
{
    $id = str_replace('-', '', (string) Str::uuid());

    return User::create([
        'name' => $nome,
        'telefone' => substr('69'.preg_replace('/\D/', '', $id).'000000000', 0, 11),
        'cpf' => substr(preg_replace('/\D/', '', $id).'00000000000', 0, 11),
        'data_nascimento' => '1990-01-01',
        'email' => "$prefixo-$id@example.test",
        'status' => 'ativo',
        'password' => 'senha-de-teste',
    ]);
}

function negMotorista(string $nome = 'Carlos Souza', string $tipo = 'carro', float $latitude = -8.7601): Motorista
{
    $motorista = Motorista::create(['user_id' => negUsuario('motorista', $nome)->id, 'status' => 'aprovado']);
    $veiculo = Veiculo::create([
        'marca' => 'Chevrolet',
        'modelo' => 'Onix',
        'ano_fabricacao' => 2023,
        'ano_modelo' => 2023,
        'cor' => 'Prata',
        'placa' => 'NEG'.random_int(1000, 9999),
        'renavam' => (string) random_int(10000000000, 99999999999),
        'categoria' => $tipo,
        'status' => 'aprovado',
        'uf' => 'RO',
    ]);
    MotoristaVeiculo::create(['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id]);
    StatusBusca::create([
        'motorista_id' => $motorista->id,
        'disponivel' => true,
        'latitude' => $latitude,
        'longitude' => -63.9004,
        'visto_em' => now(),
    ]);

    return $motorista;
}

function negPassageiro(): Passageiro
{
    return Passageiro::create(['user_id' => negUsuario('passageiro')->id]);
}

function negCotacao(Passageiro $passageiro, float $sugerido = 10.0): CotacaoCorrida
{
    $produto = ProdutosCorrida::where('codigo', 'negocia')->firstOrFail();

    return CotacaoCorrida::create([
        'user_id' => $passageiro->user_id,
        'distancia_km' => 4,
        'tempo_min' => 10,
        'enderecos' => [
            ['order' => 0, 'latitude' => -8.7600, 'longitude' => -63.9004, 'formattedAddress' => 'Embarque'],
            ['order' => 1, 'latitude' => -8.7400, 'longitude' => -63.8800, 'formattedAddress' => 'Destino'],
        ],
        'categorias' => [[
            'tarifa_id' => null,
            'produto' => [
                'id' => $produto->id,
                'codigo' => 'negocia',
                'nome' => 'Negocia',
                'estrategia_precificacao' => 'negociada',
                'grupo' => 'negocia',
            ],
            'composicao' => [
                'subtotal' => 9.4,
                'tarifa_base' => 0,
                'valor_distancia' => 6.2,
                'valor_tempo' => 3.2,
                'valor_por_minuto_espera' => 0.3,
                'diferenca_negociada' => 0,
            ],
            'valores' => [
                'valor_passageiro' => $sugerido,
                'valor_motorista' => round($sugerido * 0.94, 2),
                'taxa_plataforma' => round($sugerido * 0.06, 2),
                'taxa_plataforma_percentual' => 6,
            ],
        ]],
        'expira_em' => now()->addMinutes(10),
    ]);
}

function negPedir(Passageiro $passageiro, float $oferta = 9.0, string $metodo = 'dinheiro'): Corrida
{
    $resposta = test()->actingAs($passageiro->user, 'jwt')->postJson('/api/corridas', [
        'cotacao_id' => negCotacao($passageiro)->id,
        'produtos_codigos' => ['negocia'],
        'valor_oferecido' => $oferta,
        'metodo_pagamento' => $metodo,
    ])->assertCreated();

    return Corrida::findOrFail($resposta->json('id'));
}

function negPropor(Motorista $motorista, Corrida $corrida, ?float $valor = null)
{
    return test()->actingAs($motorista->user, 'jwt')->postJson(
        "/api/motorista/corridas/{$corrida->id}/propostas",
        $valor === null ? [] : ['valor_motorista' => $valor]
    );
}

it('o passageiro oferece um valor dentro da faixa e a corrida abre a negociação', function () {
    $corrida = negPedir(negPassageiro(), 9.0);

    expect($corrida->status_corrida)->toBe('solicitada')
        ->and($corrida->status_negociacao)->toBe('em_negociacao')
        ->and((float) $corrida->corrida_financeiro->valor_pago_passageiro)->toBe(9.0)
        ->and((float) $corrida->corrida_financeiro->valor_motorista)->toBe(8.46);

    $passageiro = negPassageiro();
    $this->actingAs($passageiro->user, 'jwt')->postJson('/api/corridas', [
        'cotacao_id' => negCotacao($passageiro)->id,
        'produtos_codigos' => ['negocia'],
        'valor_oferecido' => 6.5,
        'metodo_pagamento' => 'dinheiro',
    ])->assertStatus(422)->assertJsonPath('message', 'Ofereça entre R$ 7,00 e R$ 20,00.');
});

it('no Pix o pedido do Negocia não gera cobrança antes da escolha', function () {
    $corrida = negPedir(negPassageiro(), 9.0, 'pix');

    expect($corrida->status_corrida)->toBe('solicitada')
        ->and(CobrancaPix::where('corrida_id', $corrida->id)->exists())->toBeFalse();
});

it('motorista não aceita direto: manda proposta pela oferta ou por outro valor', function () {
    $corrida = negPedir(negPassageiro(), 9.0);
    $motorista = negMotorista();

    expect(fn () => app(DespachoCorridaService::class)->aceitar($motorista, $corrida->id))
        ->toThrow(RuntimeException::class, 'Nesta corrida o passageiro escolhe o motorista. Envie sua proposta.');

    $oferta = app(DespachoCorridaService::class)->ofertasPara($motorista)->first();
    expect($oferta['negociavel'])->toBeTrue()
        ->and($oferta['minha_proposta'])->toBeNull();

    negPropor($motorista, $corrida)->assertCreated()
        ->assertJsonPath('valor_motorista', 8.46)
        ->assertJsonPath('valor_passageiro', 9);

    negPropor($motorista, $corrida, 10.0)->assertCreated()
        ->assertJsonPath('valor_passageiro', 10.64);
    expect(CorridaNegociacoes::where('corrida_id', $corrida->id)->count())->toBe(1);

    $oferta = app(DespachoCorridaService::class)->ofertasPara($motorista)->first();
    expect($oferta['minha_proposta']['valor_motorista'])->toBe(10.0)
        ->and($oferta['minha_proposta']['status'])->toBe('pendente');

    negPropor($motorista, $corrida, 8.0)->assertStatus(422)
        ->assertJsonPath('message', 'A proposta não pode ser menor que a oferta do passageiro.');
    negPropor($motorista, $corrida, 17.0)->assertStatus(422);
    negPropor(negMotorista('Moto Rider', 'moto'), $corrida)->assertStatus(409)
        ->assertJsonPath('message', 'Seu veículo não atende a categoria pedida nesta corrida.');
});

it('o passageiro vê as propostas valendo, da mais barata para a mais cara', function () {
    $passageiro = negPassageiro();
    $corrida = negPedir($passageiro, 9.0);
    $carlos = negMotorista('Carlos Souza');
    $ana = negMotorista('Ana Lima', latitude: -8.7700);
    $velho = negMotorista('Bruno Reis');

    negPropor($carlos, $corrida, 10.0)->assertCreated();
    negPropor($ana, $corrida)->assertCreated();
    negPropor($velho, $corrida)->assertCreated();
    CorridaNegociacoes::where('motorista_id', $velho->id)->update(['expira_em' => now()->subSecond()]);

    $propostas = $this->actingAs($passageiro->user, 'jwt')
        ->getJson("/api/corridas/{$corrida->id}/propostas")
        ->assertOk()
        ->json('propostas');

    expect($propostas)->toHaveCount(2)
        ->and($propostas[0]['motorista']['nome'])->toBe('Ana')
        ->and($propostas[0]['valor_passageiro'])->toBe(9)
        ->and($propostas[0]['veiculo']['modelo'])->toBe('Onix')
        ->and($propostas[0]['chegada_min'])->toBeGreaterThan(1)
        ->and($propostas[1]['motorista']['nome'])->toBe('Carlos')
        ->and(CorridaNegociacoes::where('motorista_id', $velho->id)->value('status'))->toBe('expirada');

    $this->actingAs(negPassageiro()->user, 'jwt')
        ->getJson("/api/corridas/{$corrida->id}/propostas")
        ->assertNotFound();
});

it('no dinheiro, escolher a proposta já entrega a corrida ao motorista', function () {
    $passageiro = negPassageiro();
    $corrida = negPedir($passageiro, 9.0);
    $carlos = negMotorista('Carlos Souza');
    $ana = negMotorista('Ana Lima');
    $propostaCarlos = negPropor($carlos, $corrida, 10.0)->json('id');
    negPropor($ana, $corrida)->assertCreated();

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/propostas/{$propostaCarlos}/escolher")
        ->assertOk()
        ->assertJsonPath('status_corrida', 'aceita');

    $corrida->refresh();
    expect($corrida->motorista_id)->toBe($carlos->id)
        ->and($corrida->status_negociacao)->toBe('aceita')
        ->and((float) $corrida->valor_negociado_final)->toBe(10.64)
        ->and((float) $corrida->corrida_financeiro->valor_pago_passageiro)->toBe(10.64)
        ->and((float) $corrida->corrida_financeiro->valor_motorista)->toBe(10.0)
        ->and(CorridaNegociacoes::where('motorista_id', $ana->id)->value('status'))->toBe('recusada')
        ->and(StatusBusca::where('motorista_id', $carlos->id)->value('disponivel'))->toBeFalsy();
});

it('no Pix a cobrança sai na escolha e o motorista só vai depois do pagamento', function () {
    negPixGerado();
    $passageiro = negPassageiro();
    $corrida = negPedir($passageiro, 9.0, 'pix');
    $carlos = negMotorista();
    $proposta = negPropor($carlos, $corrida, 10.0)->json('id');

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/propostas/{$proposta}/escolher")
        ->assertOk()
        ->assertJsonPath('status_corrida', 'aguardando_pagamento');

    $cobranca = CobrancaPix::where('corrida_id', $corrida->id)->first();
    expect($cobranca)->not->toBeNull()
        ->and($corrida->refresh()->motorista_id)->toBe($carlos->id);

    // o motorista reservado não pega outra corrida enquanto espera
    expect(fn () => app(DespachoCorridaService::class)->aceitar($carlos, negPedir(negPassageiro())->id))
        ->toThrow(RuntimeException::class);

    $cobranca->update(['status' => 'PAID', 'valor_centavos' => 1064, 'pago_em' => now()]);
    app(PagamentoCorridaService::class)->aoConfirmarPagamento($corrida->id);

    expect($corrida->refresh()->status_corrida)->toBe('aceita')
        ->and($corrida->status_pagamento)->toBe('pago');
});

it('o motorista que espera o Pix do Negocia sabe até quando, e vê o passageiro', function () {
    negPixGerado();
    $passageiro = negPassageiro();
    $corrida = negPedir($passageiro, 9.0, 'pix');
    $carlos = negMotorista();
    $proposta = negPropor($carlos, $corrida, 10.0)->json('id');

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/propostas/{$proposta}/escolher")
        ->assertOk();

    $aceite = $corrida->refresh()->tempo_aceite;
    $resposta = $this->actingAs($carlos->user, 'jwt')
        ->getJson('/api/minha-corrida-atual?perfil=motorista')
        ->assertOk()
        ->assertJsonPath('corrida.status_corrida', 'aguardando_pagamento')
        ->assertJsonPath('passageiro.foto', null);

    expect($resposta->json('corrida.pagamento_expira_em'))
        ->toBe($aceite->copy()->addSeconds((int) config('precificacao.negocia_pagamento_limite_segundos'))->toIso8601String())
        ->and($resposta->json('passageiro.nome'))->not->toBeNull();
});

it('Pix não pago no prazo do Negocia cancela e libera o motorista', function () {
    // o banco grava o horário sem fração de segundo
    Carbon::setTestNow(now()->startOfSecond());
    negPixGerado();
    $passageiro = negPassageiro();
    $corrida = negPedir($passageiro, 9.0, 'pix');
    $carlos = negMotorista();
    $proposta = negPropor($carlos, $corrida)->json('id');
    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/propostas/{$proposta}/escolher")
        ->assertOk();
    Http::fake([
        'api.abacatepay.com/v2/transparents/check*' => Http::response(['success' => true, 'error' => null, 'data' => ['id' => 'pix_negocia', 'status' => 'PENDING']]),
    ]);

    Carbon::setTestNow(now()->addSeconds(299));
    app(PagamentoCorridaService::class)->expirarPagamentosVencidos();
    expect($corrida->refresh()->status_corrida)->toBe('aguardando_pagamento');

    Carbon::setTestNow(now()->addSeconds(2));
    app(PagamentoCorridaService::class)->expirarPagamentosVencidos();

    expect($corrida->refresh()->status_corrida)->toBe('cancelada')
        ->and(StatusBusca::where('motorista_id', $carlos->id)->value('disponivel'))->toBeTruthy();
});

it('proposta de motorista que ficou ocupado não pode ser escolhida', function () {
    $passageiro = negPassageiro();
    $corrida = negPedir($passageiro, 9.0);
    $carlos = negMotorista();
    $proposta = negPropor($carlos, $corrida)->json('id');
    StatusBusca::where('motorista_id', $carlos->id)->update(['disponivel' => false]);

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/propostas/{$proposta}/escolher")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Este motorista não está mais disponível. Escolha outra proposta.');

    expect(CorridaNegociacoes::find($proposta)->status)->toBe('expirada')
        ->and($corrida->refresh()->status_corrida)->toBe('solicitada');
});

it('no Negocia o trajeto não muda nem durante a negociação', function () {
    $passageiro = negPassageiro();
    $corrida = negPedir($passageiro, 9.0);

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/destino", [
            'endereco' => 'Outro lugar',
            'latitude' => -8.70,
            'longitude' => -63.88,
        ])
        ->assertStatus(409);
});

it('se o Pix não puder ser gerado, a escolha não vale e a negociação continua', function () {
    Http::fake([
        'api.abacatepay.com/v2/transparents/create' => Http::response(['success' => false, 'error' => 'Invalid taxId', 'data' => null], 400),
    ]);
    $passageiro = negPassageiro();
    $corrida = negPedir($passageiro, 9.0, 'pix');
    $carlos = negMotorista();
    $proposta = negPropor($carlos, $corrida)->json('id');

    $this->actingAs($passageiro->user, 'jwt')
        ->postJson("/api/corridas/{$corrida->id}/propostas/{$proposta}/escolher")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Não foi possível gerar o pagamento agora. Tente de novo ou troque para dinheiro.');

    $corrida->refresh();
    expect($corrida->status_corrida)->toBe('solicitada')
        ->and($corrida->status_negociacao)->toBe('em_negociacao')
        ->and($corrida->motorista_id)->toBeNull()
        ->and((float) $corrida->corrida_financeiro->valor_pago_passageiro)->toBe(9.0)
        ->and(CorridaNegociacoes::find($proposta)->status)->toBe('pendente')
        ->and(StatusBusca::where('motorista_id', $carlos->id)->value('disponivel'))->toBeTruthy();
});
