<?php

use App\Http\Controllers\Auth\GestorAutenticacaoController;
use App\Http\Controllers\Corrida\CorridaController;
use App\Http\Controllers\Gestor\GestorController;
use App\Http\Controllers\Motorista\MotoristaController;
use App\Http\Controllers\Motorista\MotoristaDocumentoController;
use App\Http\Controllers\Usuario\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('gestao')->name('gestao.')->group(function () {
    Route::post('auth/login', [GestorAutenticacaoController::class, 'login'])
        ->middleware('throttle:10,1')->name('auth.login');
    Route::post('auth/refresh', [GestorAutenticacaoController::class, 'refresh'])
        ->middleware('throttle:30,1')->name('auth.refresh');

    Route::middleware(['auth:gestores', 'gestor'])->group(function () {
        Route::get('usuario-logado', [GestorAutenticacaoController::class, 'usuarioLogado']);
        Route::post('auth/logout', [GestorAutenticacaoController::class, 'logout'])->name('auth.logout');

        Route::get('gestores/arquivados', [GestorController::class, 'arquivados']);
        Route::post('gestores/{gestor}/restaurar', [GestorController::class, 'restaurar'])->whereNumber('gestor');
        Route::apiResource('gestores', GestorController::class)->parameters(['gestores' => 'gestor']);
        Route::apiResource('users', UsuarioController::class)->only(['index', 'store', 'show', 'update']);
        Route::delete('usuario-remover-foto-perfil/{id}', [UsuarioController::class, 'removerFotoPerfil']);
        Route::post('usuario-arquivar', [UsuarioController::class, 'usuarioArquivar']);
        Route::post('usuario-deletar', [UsuarioController::class, 'usuarioDeletar']);
        Route::post('usuario-restaurar', [UsuarioController::class, 'usuarioRestaurar']);
        Route::get('usuarios-arquivados', [UsuarioController::class, 'usuariosArquivados']);
        Route::put('usuario-alterar-foto-perfil/{id}', [UsuarioController::class, 'alterarFotoPerfil']);

        Route::get('motorista-veiculos/{motoristaId}', [MotoristaController::class, 'motoristaVeiculos']);
        Route::apiResource('motoristas', MotoristaController::class);
        Route::post('adicionar-veiculo-ao-motorista', [MotoristaController::class, 'adicionarVeiculoAoMotorista']);
        Route::get('motorista-documentos/tipos', [MotoristaDocumentoController::class, 'tipos']);
        Route::get('motorista-documentos/motivos-reprovacao', [MotoristaDocumentoController::class, 'motivosReprovacao']);
        Route::get('motorista-documentos/{motoristaId}/resumo', [MotoristaDocumentoController::class, 'resumo']);
        Route::get('motorista-documentos/{motoristaDocumentoId}/download', [MotoristaDocumentoController::class, 'baixar']);
        Route::apiResource('motorista-documentos', MotoristaDocumentoController::class);
        Route::put('mudar-status-documento/{motoristaDocumentoId}', [MotoristaDocumentoController::class, 'mudarStatusDocumento']);
        Route::apiResource('corridas', CorridaController::class)->only(['index', 'show']);

        require __DIR__.'/veiculo.php';
        require __DIR__.'/passageiro.php';
        require __DIR__.'/tarifa.php';
        require __DIR__.'/produto.php';
        require __DIR__.'/publicidade.php';
        require __DIR__.'/estimativa.php';
    });
});
