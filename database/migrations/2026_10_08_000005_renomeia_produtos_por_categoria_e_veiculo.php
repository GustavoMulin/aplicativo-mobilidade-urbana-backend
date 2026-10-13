<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nome exibido = categoria · veículo (não existe "Pop": a categoria é
     * Normal, com carro e moto). Os códigos continuam os mesmos.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const NOMES = [
        'negocia' => ['Negocia · Carro', 'Negocia'],
        'moto_negocia' => ['Negocia · Moto', 'Moto Negocia'],
        'pop' => ['Normal · Carro', 'Pop'],
        'moto' => ['Normal · Moto', 'Moto'],
        'carro_eletrico' => ['Elétrico · Carro', 'Carro Elétrico'],
        'taxi' => ['Táxi · Carro', 'Táxi'],
        'moto_taxi' => ['Táxi · Moto', 'Moto Táxi'],
    ];

    public function up(): void
    {
        foreach (self::NOMES as $codigo => [$novo]) {
            DB::table('produtos_corridas')->where('codigo', $codigo)->update(['nome' => $novo]);
        }

        foreach (['carro' => 'Carro', 'moto' => 'Moto'] as $tipo => $rotulo) {
            DB::table('produtos_corridas')
                ->where('grupo', 'entrega')
                ->where('tipo_veiculo', $tipo)
                ->update(['nome' => "Entrega · {$rotulo}"]);
        }
    }

    public function down(): void
    {
        foreach (self::NOMES as $codigo => [, $antigo]) {
            DB::table('produtos_corridas')->where('codigo', $codigo)->update(['nome' => $antigo]);
        }

        foreach (['carro' => 'Carro', 'moto' => 'Moto'] as $tipo => $rotulo) {
            DB::table('produtos_corridas')
                ->where('grupo', 'entrega')
                ->where('tipo_veiculo', $tipo)
                ->update(['nome' => "Entrega {$rotulo}"]);
        }
    }
};
