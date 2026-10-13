<?php

// CODEX: 104 linhas criadas; cobre cadastro autenticado, isolamento e validação dos veículos do motorista.

use App\Models\Motorista;
use App\Models\MotoristaVeiculo;
use App\Models\StatusBusca;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function criarMotoristaParaVeiculos(): Motorista
{
    $usuario = User::factory()->create();

    return Motorista::create([
        'user_id' => $usuario->id,
        'status' => 'aprovado',
        'numero_registro' => fake()->unique()->numerify('###########'),
        'cnh_categoria' => 'B',
        'cnh_expiracao' => now()->addYear()->toDateString(),
        'ear' => true,
    ]);
}

function dadosValidosDoVeiculo(array $alteracoes = []): array
{
    return array_merge([
        'marca' => 'Chevrolet',
        'modelo' => 'Onix',
        'ano_fabricacao' => 2025,
        'ano_modelo' => 2026,
        'cor' => 'Branco',
        'placa' => 'ABC1D23',
        'renavam' => '12345678901',
        'categoria' => 'carro',
        'uf' => 'RO',
    ], $alteracoes);
}

it('cadastra e lista somente os veículos do motorista autenticado', function () {
    $motorista = criarMotoristaParaVeiculos();
    $outroMotorista = criarMotoristaParaVeiculos();

    $resposta = $this->actingAs($motorista->user, 'jwt')
        ->postJson('/api/motorista/me/veiculos', dadosValidosDoVeiculo());

    $resposta->assertCreated()
        ->assertJsonPath('data.placa', 'ABC1D23')
        ->assertJsonPath('data.status', 'aprovado');

    $veiculoId = $resposta->json('data.id');
    expect(MotoristaVeiculo::where([
        'motorista_id' => $motorista->id,
        'veiculo_id' => $veiculoId,
    ])->exists())->toBeTrue();

    $this->actingAs($motorista->user, 'jwt')
        ->getJson('/api/motorista/me/veiculos')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $veiculoId);

    $this->actingAs($outroMotorista->user, 'jwt')
        ->getJson('/api/motorista/me/veiculos')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('torna o veículo novo o veículo da próxima busca do motorista', function () {
    $motorista = criarMotoristaParaVeiculos();
    StatusBusca::create([
        'motorista_id' => $motorista->id,
        'disponivel' => false,
        'visto_em' => now(),
    ]);

    $resposta = $this->actingAs($motorista->user, 'jwt')
        ->postJson('/api/motorista/me/veiculos', dadosValidosDoVeiculo());

    $resposta->assertCreated();
    expect(StatusBusca::where('motorista_id', $motorista->id)->value('veiculo_id'))
        ->toBe($resposta->json('data.id'));
});

it('rejeita placa inválida e dados duplicados', function () {
    $motorista = criarMotoristaParaVeiculos();

    $this->actingAs($motorista->user, 'jwt')
        ->postJson('/api/motorista/me/veiculos', dadosValidosDoVeiculo([
            'placa' => 'INVALIDA',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('placa');

    Veiculo::create([...dadosValidosDoVeiculo(), 'status' => 'aprovado']);

    $this->actingAs($motorista->user, 'jwt')
        ->postJson('/api/motorista/me/veiculos', dadosValidosDoVeiculo())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['placa', 'renavam']);
});
