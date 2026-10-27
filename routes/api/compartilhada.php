<?php

use App\Http\Controllers\Corrida\SalaCompartilhadaController;
use Illuminate\Support\Facades\Route;

// já dentro do grupo auth:jwt (ver routes/api.php)
Route::prefix('compartilhada')->group(function () {
    Route::get('disponibilidade', [SalaCompartilhadaController::class, 'disponibilidade'])->middleware('throttle:30,1,compartilhada-disponibilidade');
    // cada oferta pode custar uma consulta de rota: limite apertado
    Route::get('ofertas', [SalaCompartilhadaController::class, 'ofertas'])->middleware('throttle:20,1,compartilhada-ofertas');
    Route::get('sala-atual', [SalaCompartilhadaController::class, 'atual']);
    Route::post('salas', [SalaCompartilhadaController::class, 'store'])->middleware('throttle:10,1,compartilhada-abrir');
    Route::get('salas/{sala}', [SalaCompartilhadaController::class, 'show'])->whereNumber('sala');
    Route::post('salas/{sala}/entrar', [SalaCompartilhadaController::class, 'entrar'])->whereNumber('sala')->middleware('throttle:10,1,compartilhada-entrar');
    Route::post('convites/{codigo}/entrar', [SalaCompartilhadaController::class, 'entrarPorCodigo'])
        ->where('codigo', '[A-Za-z0-9]{4,8}')
        ->middleware('throttle:10,1,compartilhada-entrar');
    Route::post('salas/{sala}/pronto', [SalaCompartilhadaController::class, 'pronto'])->whereNumber('sala');
    Route::delete('salas/{sala}/pronto', [SalaCompartilhadaController::class, 'desfazerPronto'])->whereNumber('sala');
    Route::post('salas/{sala}/sair', [SalaCompartilhadaController::class, 'sair'])->whereNumber('sala');
    Route::post('salas/{sala}/pedir-sozinho', [SalaCompartilhadaController::class, 'pedirSozinho'])->whereNumber('sala');
    Route::post('corridas/{corrida}/seguir-sozinho', [SalaCompartilhadaController::class, 'seguirSozinho'])->whereNumber('corrida');
});
