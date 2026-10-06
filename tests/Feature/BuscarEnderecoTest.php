<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('responde lista vazia para texto curto sem consultar o provedor de lugares', function () {
    $usuario = User::factory()->create();
    Http::fake();

    $this->actingAs($usuario, 'jwt')
        ->getJson('/api/buscar-endereco?endereco=ru')
        ->assertOk()
        ->assertExactJson([]);

    Http::assertNothingSent();
});

it('responde lista vazia quando o provedor não encontra endereços', function () {
    $usuario = User::factory()->create();

    Http::fake([
        'places.googleapis.com/*' => Http::response([]),
    ]);

    $this->actingAs($usuario, 'jwt')
        ->getJson('/api/buscar-endereco?endereco=Rua+inexistente')
        ->assertOk()
        ->assertExactJson([]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'places.googleapis.com'));
});
