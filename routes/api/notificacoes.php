<?php

use App\Http\Controllers\Usuario\NotificacoesController;
use Illuminate\Support\Facades\Route;

// já dentro do grupo auth:jwt (ver routes/api.php)
Route::get('notificacoes', [NotificacoesController::class, 'index']);
Route::post('notificacoes/lidas', [NotificacoesController::class, 'marcarLidas']);
