<?php

namespace App\Providers;

use App\Contracts\GatewayPagamento;
use App\Contracts\GatewaySaque;
use App\Services\AbacatePayClient;
use App\Services\Pagamento\GatewayPagamentoAbacatePay;
use App\Services\Pagamento\GatewayPagamentoSimulado;
use App\Services\Saque\GatewaySaqueSimulado;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GatewaySaque::class, fn () => match (config('saques.gateway')) {
            'simulado' => new GatewaySaqueSimulado,
            default => throw new InvalidArgumentException('Provedor de saque desconhecido: '.config('saques.gateway')),
        });

        $this->app->bind(GatewayPagamento::class, fn ($app) => match (config('pagamentos.gateway')) {
            'simulado' => $app->environment('production') && ! config('pagamentos.simulado_em_producao')
                ? throw new InvalidArgumentException('Pagamento simulado não roda em produção sem PAGAMENTOS_SIMULADO_EM_PRODUCAO=true.')
                : new GatewayPagamentoSimulado,
            'abacatepay' => new GatewayPagamentoAbacatePay($app->make(AbacatePayClient::class)),
            default => throw new InvalidArgumentException('Provedor de pagamento desconhecido: '.config('pagamentos.gateway')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
