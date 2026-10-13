<?php

use App\Http\Controllers\Pagamento\WebhookAbacatePayController;
use Illuminate\Support\Facades\Route;

// público (sem JWT): a AbacatePay autentica pelo segredo na URL
Route::post('webhooks/abacatepay', WebhookAbacatePayController::class)->middleware('throttle:60,1,webhook-abacatepay');
