<?php

// CODEX: 4 linhas alteradas; publica as rotas autenticadas dos veículos da própria conta.

use App\Http\Controllers\Motorista\MotoristaCadastroController;
use App\Http\Controllers\Motorista\MotoristaController;
use App\Http\Controllers\Motorista\MotoristaDocumentoController;
use Illuminate\Support\Facades\Route;

// já dentro do grupo auth:jwt (ver routes/api.php)
Route::get('motorista/cadastro', [MotoristaCadastroController::class, 'mostrar']);
Route::post('motorista/cadastro/cnh', [MotoristaCadastroController::class, 'salvarCnh']);
Route::post('motorista/cadastro/documentos', [MotoristaCadastroController::class, 'enviarDocumento']);
Route::delete('motorista/cadastro/documentos/{documento}', [MotoristaCadastroController::class, 'removerDocumento']);
// atalho de desenvolvimento: ver MotoristaCadastroController::aprovarDev
Route::post('motorista/cadastro/aprovar-dev', [MotoristaCadastroController::class, 'aprovarDev']);
Route::get('motorista/me/veiculos', [MotoristaController::class, 'meusVeiculos']);
Route::post('motorista/me/veiculos', [MotoristaController::class, 'cadastrarMeuVeiculo']);

Route::get('motorista-veiculos/{motoristaId}', [MotoristaController::class, 'motoristaVeiculos']);
Route::apiResource('motoristas', MotoristaController::class);
Route::post('adicionar-veiculo-ao-motorista', [MotoristaController::class, 'adicionarVeiculoAoMotorista']);

Route::get('motorista-documentos/tipos', [MotoristaDocumentoController::class, 'tipos']);
Route::get('motorista-documentos/{motoristaId}/resumo', [MotoristaDocumentoController::class, 'resumo']);
Route::apiResource('motorista-documentos', MotoristaDocumentoController::class);
Route::put('mudar-status-documento/{motoristaDocumentoId}', [MotoristaDocumentoController::class, 'mudarStatusDocumento']);
