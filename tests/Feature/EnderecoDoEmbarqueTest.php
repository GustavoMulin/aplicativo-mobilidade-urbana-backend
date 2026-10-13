<?php

use App\Services\EnderecoPorCoordenadaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.google_maps.key' => 'chave-de-teste']);
});

it('troca Localização Atual pela rua e bairro da coordenada', function () {
    Http::fake([
        'maps.googleapis.com/maps/api/geocode/*' => Http::response([
            'results' => [[
                'formatted_address' => 'R. Brasília, 2940 - São Cristóvão, Porto Velho - RO, 76804-000, Brasil',
            ]],
        ]),
    ]);
    $servico = app(EnderecoPorCoordenadaService::class);

    expect($servico->semEndereco('Localização Atual'))->toBeTrue()
        ->and($servico->semEndereco('  '))->toBeTrue()
        ->and($servico->semEndereco('Av. Rio Madeira, 3288'))->toBeFalse()
        ->and($servico->executar(-8.7552, -63.8974))->toBe('R. Brasília, 2940 · São Cristóvão');

    // a segunda consulta da mesma coordenada vem do cache
    $servico->executar(-8.7552, -63.8974);
    Http::assertSentCount(1);
});

it('sem resposta do Google fica sem endereço, sem quebrar o pedido', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response(['results' => []])]);

    expect(app(EnderecoPorCoordenadaService::class)->executar(-8.7552, -63.8974))->toBeNull();
});
