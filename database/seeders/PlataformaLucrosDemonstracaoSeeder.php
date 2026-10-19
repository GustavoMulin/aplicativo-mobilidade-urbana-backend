<?php

namespace Database\Seeders;

use App\Models\PlataformaLucro;
use App\Services\PlataformaLucrosService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlataformaLucrosDemonstracaoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demonstração disponível apenas em local/testing.');

            return;
        }
        $service = app(PlataformaLucrosService::class);
        $agora = now()->subMinutes(10);
        foreach ([
            ['001', 'Comissão PIX · demonstração', 'comissao_corrida', 'online_pix', '30.00', '27.00', '3.00', '0.50', '0.00', '0.00', '0.00', 'realizado_em_conta'],
            ['002', 'Comissão em dinheiro · demonstração', 'comissao_corrida', 'dinheiro_motorista', '50.00', '45.00', '5.00', '0.00', '0.00', '0.00', '0.00', 'a_receber_motorista'],
            ['003', 'Cartão com custo pendente · demonstração', 'comissao_corrida', 'online_cartao', '40.00', '36.00', '4.00', null, '0.00', '0.00', '0.00', 'aguardando_liquidacao'],
            ['004', 'Bônus de campanha · demonstração', 'recompensa_bonus', 'online_pix', '0.00', '0.00', '0.00', '0.00', '0.00', '6.00', '0.00', 'realizado_em_conta'],
        ] as [$codigo, $descricao, $tipo, $metodo, $gmv, $repasse, $comissao, $gateway, $subsidio, $bonus, $compensacao, $caixa]) {
            DB::transaction(function () use ($service, $agora, $codigo, $descricao, $tipo, $metodo, $gmv, $repasse, $comissao, $gateway, $subsidio, $bonus, $compensacao, $caixa) {
                if (PlataformaLucro::where('origem', 'demonstracao:plataforma:'.$codigo)->exists()) {
                    return;
                }
                $registro = new PlataformaLucro;
                $registro->forceFill(['origem' => 'demonstracao:plataforma:'.$codigo, 'descricao' => $descricao, 'tipo_operacao' => $tipo,
                    'tipo_servico' => $codigo === '003' ? 'entrega' : 'corrida', 'origem_pagamento' => $metodo,
                    'valor_bruto_passageiro' => $gmv, 'repasse_motorista' => $repasse, 'comissao_prevista' => $comissao,
                    'custo_gateway' => $gateway, 'subsidio_aplicado' => $subsidio, 'bonus_alocado' => $bonus, 'compensacao_paga' => $compensacao,
                    'custos_confirmados' => $gateway === null ? ['subsidio_aplicado', 'bonus_alocado', 'compensacao_paga'] : PlataformaLucrosService::CUSTOS,
                    'custos_incluidos_repasse' => [], 'composicao_conferida' => true, 'status_caixa' => $caixa,
                    'ocorrido_em' => $agora, 'demonstracao' => true, 'versao' => 1,
                    'recebido_em' => $caixa === 'realizado_em_conta' ? $agora : null,
                    'referencia_liquidacao' => $caixa === 'realizado_em_conta' ? 'SIMULAÇÃO — nenhum recebimento bancário real' : null]);
                $service->recalcular($registro);
                $registro->save();
                DB::table('plataforma_lucros_auditoria')->insert(['plataforma_lucro_id' => $registro->id, 'acao' => 'demonstracao', 'versao' => 1,
                    'motivo' => 'Cenário fictício isolado. Não representa lucro, custo ou recebimento real.', 'depois' => json_encode($registro->attributesToArray()), 'created_at' => now()]);
            });
        }
        $this->call(PlataformaLucrosMassaDemonstracaoSeeder::class);
        $this->command?->info('Cenários fictícios disponíveis; ative Mostrar demonstração na tela. Nenhum dado operacional foi alterado.');
    }
}
