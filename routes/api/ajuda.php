<?php

use App\Http\Controllers\Ajuda\TicketsController;
use App\Http\Controllers\Usuario\ChamadosAjudaController;
use Illuminate\Support\Facades\Route;

// já dentro do grupo auth:jwt (ver routes/api.php)
Route::get('ajuda/chamados', [ChamadosAjudaController::class, 'index']);
Route::post('ajuda/chamados', [ChamadosAjudaController::class, 'store'])->middleware('throttle:10,1,chamados-ajuda');

Route::get('ajuda/tickets', [TicketsController::class, 'index']);
Route::post('ajuda/tickets', [TicketsController::class, 'store'])->middleware('throttle:10,1,chamados-ajuda');
Route::get('ajuda/tickets/{ticket}', [TicketsController::class, 'show'])->whereNumber('ticket');
Route::get('ajuda/tickets/{ticket}/mensagens', [TicketsController::class, 'mensagens'])->whereNumber('ticket');
Route::post('ajuda/tickets/{ticket}/mensagens', [TicketsController::class, 'enviar'])->whereNumber('ticket')->middleware('throttle:60,1');
