<?php

namespace Database\Seeders;

use App\Models\ProdutosCorrida;
use App\Models\Tarifa;
use Illuminate\Database\Seeder;

class PrecificacaoSeeder extends Seeder
{
    /**
     * Nome = categoria · veículo. Normal, Negocia e Táxi têm carro e moto;
     * Elétrico e Expresso só carro. O Expresso é prioritário: vai primeiro aos
     * motoristas mais perto (raio menor) e aparece antes das outras chamadas.
     *
     * @var array<string, array{nome: string, estrategia: string, ordem: int, grupo: string, tipo_veiculo: string, requisito_veiculo: string|null, prioritario?: bool}>
     */
    private const PRODUTOS = [
        'negocia' => ['nome' => 'Negocia · Carro', 'estrategia' => 'negociada', 'ordem' => 1, 'grupo' => 'negocia', 'tipo_veiculo' => 'carro', 'requisito_veiculo' => null],
        'pop' => ['nome' => 'Normal · Carro', 'estrategia' => 'normal', 'ordem' => 2, 'grupo' => 'normal', 'tipo_veiculo' => 'carro', 'requisito_veiculo' => null],
        'expresso' => ['nome' => 'Expresso · Carro', 'estrategia' => 'normal', 'ordem' => 3, 'grupo' => 'expresso', 'tipo_veiculo' => 'carro', 'requisito_veiculo' => null, 'prioritario' => true],
        'carro_eletrico' => ['nome' => 'Elétrico · Carro', 'estrategia' => 'eletrico', 'ordem' => 4, 'grupo' => 'eletrico', 'tipo_veiculo' => 'carro', 'requisito_veiculo' => 'eletrico'],
        'moto' => ['nome' => 'Normal · Moto', 'estrategia' => 'normal', 'ordem' => 5, 'grupo' => 'normal', 'tipo_veiculo' => 'moto', 'requisito_veiculo' => null],
        'moto_negocia' => ['nome' => 'Negocia · Moto', 'estrategia' => 'negociada', 'ordem' => 6, 'grupo' => 'negocia', 'tipo_veiculo' => 'moto', 'requisito_veiculo' => null],
        'taxi' => ['nome' => 'Táxi · Carro', 'estrategia' => 'taxi', 'ordem' => 7, 'grupo' => 'taxi', 'tipo_veiculo' => 'carro', 'requisito_veiculo' => 'taxi'],
        'moto_taxi' => ['nome' => 'Táxi · Moto', 'estrategia' => 'taxi', 'ordem' => 8, 'grupo' => 'taxi', 'tipo_veiculo' => 'moto', 'requisito_veiculo' => 'taxi'],
    ];

    /**
     * @var array<string, array{base: float, km: float, minuto: float, espera: float, minimo: float}>
     */
    private const TARIFAS = [
        'negocia' => ['base' => 0.00, 'km' => 1.55, 'minuto' => 0.28, 'espera' => 0.30, 'minimo' => 8.00],
        'pop' => ['base' => 2.00, 'km' => 1.55, 'minuto' => 0.28, 'espera' => 0.30, 'minimo' => 8.00],
        'expresso' => ['base' => 2.50, 'km' => 1.85, 'minuto' => 0.33, 'espera' => 0.30, 'minimo' => 9.50],
        'moto' => ['base' => 1.00, 'km' => 0.90, 'minuto' => 0.16, 'espera' => 0.20, 'minimo' => 5.00],
        'moto_negocia' => ['base' => 0.00, 'km' => 0.90, 'minuto' => 0.16, 'espera' => 0.20, 'minimo' => 5.00],
        'carro_eletrico' => ['base' => 2.50, 'km' => 1.70, 'minuto' => 0.30, 'espera' => 0.30, 'minimo' => 9.00],
        'taxi' => ['base' => 3.00, 'km' => 1.80, 'minuto' => 0.32, 'espera' => 0.35, 'minimo' => 10.00],
        'moto_taxi' => ['base' => 1.50, 'km' => 1.00, 'minuto' => 0.18, 'espera' => 0.20, 'minimo' => 6.00],
    ];

    private const TAXA_PLATAFORMA_PERCENTUAL = 6.00;

    private const RAIO_BUSCA_MOTORISTA_KM = 5;

    // o Expresso começa só com quem está bem perto
    private const RAIO_BUSCA_PRIORITARIO_KM = 2;

    public function run(): void
    {
        foreach (self::PRODUTOS as $codigo => $dados) {
            $produto = ProdutosCorrida::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nome' => $dados['nome'],
                    'estrategia_precificacao' => $dados['estrategia'],
                    'ordem' => $dados['ordem'],
                    'grupo' => $dados['grupo'],
                    'tipo_veiculo' => $dados['tipo_veiculo'],
                    'requisito_veiculo' => $dados['requisito_veiculo'],
                    'prioritario' => $dados['prioritario'] ?? false,
                ]
            );

            $valores = self::TARIFAS[$codigo];

            Tarifa::updateOrCreate(
                [
                    'produto_id' => $produto->id,
                    'cidade_id' => null,
                ],
                [
                    'horario_inicio' => '00:00:00',
                    'horario_fim' => '23:59:59',
                    'dias_semana' => json_encode([1, 2, 3, 4, 5, 6, 7]),
                    'vira_dia' => false,
                    'valor_minimo_corrida' => $valores['minimo'],
                    'tarifa_base' => $valores['base'],
                    'valor_por_km' => $valores['km'],
                    'valor_por_minuto' => $valores['minuto'],
                    'valor_por_minuto_espera' => $valores['espera'],
                    'taxa_plataforma_percentual' => self::TAXA_PLATAFORMA_PERCENTUAL,
                    'raio_busca_motorista_km' => ($dados['prioritario'] ?? false)
                        ? self::RAIO_BUSCA_PRIORITARIO_KM
                        : self::RAIO_BUSCA_MOTORISTA_KM,
                    'ativo' => true,
                ]
            );
        }
    }
}
