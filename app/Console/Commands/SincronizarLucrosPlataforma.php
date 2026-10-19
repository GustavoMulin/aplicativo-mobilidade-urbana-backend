<?php

namespace App\Console\Commands;

use App\Services\PlataformaLucrosService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SincronizarLucrosPlataforma extends Command
{
    protected $signature = 'plataforma:sincronizar {--inicio=} {--fim=}';

    protected $description = 'Atualiza retratos de apuração da plataforma sem movimentar carteiras, cobranças ou contas bancárias';

    public function handle(PlataformaLucrosService $service): int
    {
        $inicio = $this->option('inicio') ?? now()->subDays(30)->toDateString();
        $fim = $this->option('fim') ?? now()->toDateString();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
            $this->error('Use datas YYYY-MM-DD.');

            return self::FAILURE;
        }
        $zona = config('ganhos.timezone', 'America/Porto_Velho');
        $de = CarbonImmutable::parse($inicio, $zona)->startOfDay();
        $ate = CarbonImmutable::parse($fim, $zona)->startOfDay()->addDay();
        if ($ate->lte($de) || $de->diffInDays($ate) > 366) {
            $this->error('O intervalo deve ter entre 1 e 366 dias.');

            return self::FAILURE;
        }
        $this->info($service->sincronizar($de->utc(), $ate->utc()).' apurações atualizadas. Nenhum pagamento foi executado.');

        return self::SUCCESS;
    }
}
