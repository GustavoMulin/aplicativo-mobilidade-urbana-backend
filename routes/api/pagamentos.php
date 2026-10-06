<?php

use App\Http\Controllers\Pagamento\SituacaoPagamentoController;
use Illuminate\Support\Facades\Route;

// já dentro do grupo auth:jwt (ver routes/api.php)
Route::get('pagamentos/situacao', SituacaoPagamentoController::class);
