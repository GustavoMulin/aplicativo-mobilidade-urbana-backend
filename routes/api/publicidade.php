<?php

use App\Http\Controllers\Publicidade\BannerPublicidadeController;
use App\Http\Controllers\Publicidade\CidadeController;
use Illuminate\Support\Facades\Route;

// já dentro do grupo auth:jwt (ver routes/api.php). Sem papel de gestão por
// enquanto: qualquer conta logada gerencia, como o resto do painel.
Route::apiResource('publicidades', BannerPublicidadeController::class)
    ->parameters(['publicidades' => 'banner']);
Route::apiResource('cidades', CidadeController::class);
