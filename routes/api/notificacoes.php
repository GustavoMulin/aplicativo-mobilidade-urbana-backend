<?php

use App\Http\Controllers\Usuario\DispositivosPushController;
use App\Http\Controllers\Usuario\NotificacoesController;
use Illuminate\Support\Facades\Route;

// já dentro do grupo auth:jwt (ver routes/api.php)
Route::get('notificacoes', [NotificacoesController::class, 'index']);
Route::post('notificacoes/lidas', [NotificacoesController::class, 'marcarLidas']);

// aparelho que recebe push (token do FCM), registrado pelo app ao entrar
Route::post('dispositivos-push', [DispositivosPushController::class, 'store'])->middleware('throttle:20,1');
Route::delete('dispositivos-push', [DispositivosPushController::class, 'destroy']);
