<?php

// CODEX: 9 linhas alteradas; avaliação, embarque e cancelamentos. Remover após validação.

use App\Services\DespachoCorridaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(DespachoCorridaService::class)->cancelarEsperasExpiradas())
    ->name('corridas:cancelar-esperas-expiradas')
    ->everyTenSeconds()
    ->withoutOverlapping();
