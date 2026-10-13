<?php

use App\Http\Controllers\Usuario\ChamadosAjudaController;
use Illuminate\Support\Facades\Route;

// já dentro do grupo auth:jwt (ver routes/api.php)
Route::get('ajuda/chamados', [ChamadosAjudaController::class, 'index']);
Route::post('ajuda/chamados', [ChamadosAjudaController::class, 'store'])->middleware('throttle:10,1,chamados-ajuda');
