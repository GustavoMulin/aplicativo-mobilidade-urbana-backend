<?php

// CODEX: 9 linhas alteradas; avaliação, embarque e cancelamentos. Remover após validação.

use App\Services\DespachoCorridaService;
use App\Services\PagamentoCorridaService;
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

// pré-pagos (Pix/cartão) não pagos no prazo: cancela sem cobrança
Schedule::call(fn () => app(PagamentoCorridaService::class)->expirarPagamentosVencidos())
    ->name('corridas:expirar-pagamentos')
    ->everyMinute()
    ->withoutOverlapping();

// estornos que ficaram sem resposta da AbacatePay
Schedule::call(fn () => app(PagamentoCorridaService::class)->reprocessarEstornosPendentes())
    ->name('pagamentos:reprocessar-estornos')
    ->everyFiveMinutes()
    ->withoutOverlapping();
