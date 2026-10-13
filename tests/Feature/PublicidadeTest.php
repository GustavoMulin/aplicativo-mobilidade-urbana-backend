<?php

use App\Models\BannerPublicidade;
use App\Models\Cidade;
use App\Models\Tarifa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake((string) config('publicidade.disco'));
});

function usuarioPublicidade(): User
{
    $id = str_replace('-', '', (string) Str::uuid());

    return User::create([
        'name' => 'Gestão Teste',
        'telefone' => substr('69'.preg_replace('/\D/', '', $id).'000000000', 0, 11),
        'cpf' => substr(preg_replace('/\D/', '', $id).'00000000000', 0, 11),
        'data_nascimento' => '1990-01-01',
        'email' => "gestao-$id@example.test",
        'status' => 'ativo',
        'password' => 'senha-de-teste',
    ]);
}

it('cadastra cidade com a sigla do estado e recusa uf ou ibge inválidos', function () {
    $this->actingAs(usuarioPublicidade(), 'jwt');

    $this->postJson('/api/cidades', ['nome' => ' Porto Velho ', 'uf' => 'ro', 'ibge' => 1100205])
        ->assertCreated()
        ->assertJsonPath('data.nome', 'Porto Velho')
        ->assertJsonPath('data.uf', 'RO');

    $this->postJson('/api/cidades', ['nome' => 'Outra', 'uf' => 'XX', 'ibge' => 1100023])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('uf');

    $this->postJson('/api/cidades', ['nome' => 'Repetida', 'uf' => 'RO', 'ibge' => 1100205])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ibge');
});

it('lista cidades em ordem alfabética com filtro por uf e busca', function () {
    $this->actingAs(usuarioPublicidade(), 'jwt');
    Cidade::factory()->create(['nome' => 'Porto Velho', 'uf' => 'RO']);
    Cidade::factory()->create(['nome' => 'Ariquemes', 'uf' => 'RO']);
    Cidade::factory()->create(['nome' => 'Rio Branco', 'uf' => 'AC', 'ibge' => 1200401]);

    $this->getJson('/api/cidades?uf=ro')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.nome', 'Ariquemes')
        ->assertJsonPath('1.nome', 'Porto Velho');

    $this->getJson('/api/cidades?busca=branco')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.uf', 'AC');
});

it('envia o banner para o disco com nome gerado pelo servidor e devolve a url', function () {
    $this->actingAs(usuarioPublicidade(), 'jwt');
    $cidade = Cidade::factory()->create();

    $resposta = $this->post('/api/publicidades', [
        'cidade_id' => $cidade->id,
        'titulo' => 'Convide um amigo para dirigir',
        'arquivo' => UploadedFile::fake()->image('../../meu banner.png', 1200, 400),
    ], ['Accept' => 'application/json'])->assertCreated();

    $banner = BannerPublicidade::firstOrFail();

    expect($banner->path)->toStartWith('banners/')
        ->and($banner->path)->not->toContain('meu banner')
        ->and($banner->mime_type)->toBe('image/png')
        ->and($resposta->json('data.url'))->toContain($banner->path)
        ->and($resposta->json('data.cidade.id'))->toBe($cidade->id);
    Storage::disk((string) config('publicidade.disco'))->assertExists($banner->path);
});

it('recusa banner sem imagem válida ou de cidade que não existe', function () {
    $this->actingAs(usuarioPublicidade(), 'jwt');
    $cidade = Cidade::factory()->create();

    $this->post('/api/publicidades', [
        'cidade_id' => $cidade->id,
        'titulo' => 'SVG não',
        'arquivo' => UploadedFile::fake()->create('banner.svg', 10, 'image/svg+xml'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('arquivo');

    $this->post('/api/publicidades', [
        'cidade_id' => $cidade->id,
        'titulo' => 'Grande demais',
        'arquivo' => UploadedFile::fake()->image('banner.png')->size(6000),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('arquivo');

    $this->post('/api/publicidades', [
        'cidade_id' => 999999,
        'titulo' => 'Cidade inexistente',
        'arquivo' => UploadedFile::fake()->image('banner.png'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('cidade_id');

    expect(BannerPublicidade::count())->toBe(0);
});

it('troca a imagem do banner e apaga a antiga do disco', function () {
    $this->actingAs(usuarioPublicidade(), 'jwt');
    $disco = Storage::disk((string) config('publicidade.disco'));
    $cidade = Cidade::factory()->create();

    $this->post('/api/publicidades', [
        'cidade_id' => $cidade->id,
        'titulo' => 'Primeira versão',
        'arquivo' => UploadedFile::fake()->image('a.png'),
    ], ['Accept' => 'application/json'])->assertCreated();
    $banner = BannerPublicidade::firstOrFail();
    $antiga = $banner->path;

    // PUT com arquivo vai como POST + _method=PUT (multipart)
    $this->post('/api/publicidades/'.$banner->id, [
        '_method' => 'PUT',
        'titulo' => 'Segunda versão',
        'arquivo' => UploadedFile::fake()->image('b.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.titulo', 'Segunda versão')
        ->assertJsonPath('data.type', 'jpg');

    $banner->refresh();
    expect($banner->path)->not->toBe($antiga);
    $disco->assertMissing($antiga);
    $disco->assertExists($banner->path);

    // só o título, sem arquivo: a imagem fica
    $this->putJson('/api/publicidades/'.$banner->id, ['titulo' => 'Terceira versão'])
        ->assertOk()
        ->assertJsonPath('data.titulo', 'Terceira versão');
    $disco->assertExists($banner->refresh()->path);
});

it('lista os banners da cidade, mostra um e exclui de forma reversível', function () {
    $this->actingAs(usuarioPublicidade(), 'jwt');
    [$portoVelho, $ariquemes] = Cidade::factory()->count(2)->create();
    $banner = BannerPublicidade::factory()->create(['cidade_id' => $portoVelho->id]);
    BannerPublicidade::factory()->create(['cidade_id' => $ariquemes->id]);

    $this->getJson('/api/publicidades?cidade_id='.$portoVelho->id)
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $banner->id)
        ->assertJsonPath('data.0.cidade.nome', $portoVelho->nome);

    $this->getJson('/api/publicidades/'.$banner->id)
        ->assertOk()
        ->assertJsonPath('url', Storage::disk((string) config('publicidade.disco'))->url($banner->path));

    $this->deleteJson('/api/publicidades/'.$banner->id)->assertOk();
    $this->getJson('/api/publicidades/'.$banner->id)->assertNotFound();
    expect(BannerPublicidade::withTrashed()->find($banner->id))->not->toBeNull();
});

it('não exclui cidade que tem banner, nem apagado', function () {
    $this->actingAs(usuarioPublicidade(), 'jwt');
    $comBanner = Cidade::factory()->create();
    BannerPublicidade::factory()->create(['cidade_id' => $comBanner->id])->delete();
    $semBanner = Cidade::factory()->create();

    $this->deleteJson('/api/cidades/'.$comBanner->id)->assertStatus(409);
    $this->deleteJson('/api/cidades/'.$semBanner->id)->assertOk();

    expect(Cidade::find($comBanner->id))->not->toBeNull()
        ->and(Cidade::find($semBanner->id))->toBeNull();
});

it('exige login para publicidade e cidades', function () {
    $this->getJson('/api/publicidades')->assertUnauthorized();
    $this->getJson('/api/cidades')->assertUnauthorized();
});

it('não exclui cidade com tarifa própria', function () {
    $this->actingAs(usuarioPublicidade(), 'jwt');
    $cidade = Cidade::factory()->create();
    Tarifa::create(['cidade_id' => $cidade->id, 'tarifa_base' => 2, 'ativo' => true]);

    $this->deleteJson('/api/cidades/'.$cidade->id)->assertStatus(409);
    expect(Cidade::find($cidade->id))->not->toBeNull();
});
