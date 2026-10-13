<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grupos do pedido: Negocia, Normal, Elétrico, Táxi e Entrega. Normal,
     * Negocia, Táxi e Entrega têm carro e moto; Elétrico só carro.
     *
     * @var array<string, array{grupo: string, tipo_veiculo: string, requisito_veiculo: string|null}>
     */
    private const CLASSIFICACAO = [
        'negocia' => ['grupo' => 'negocia', 'tipo_veiculo' => 'carro', 'requisito_veiculo' => null],
        'moto_negocia' => ['grupo' => 'negocia', 'tipo_veiculo' => 'moto', 'requisito_veiculo' => null],
        'pop' => ['grupo' => 'normal', 'tipo_veiculo' => 'carro', 'requisito_veiculo' => null],
        'moto' => ['grupo' => 'normal', 'tipo_veiculo' => 'moto', 'requisito_veiculo' => null],
        'carro_eletrico' => ['grupo' => 'eletrico', 'tipo_veiculo' => 'carro', 'requisito_veiculo' => 'eletrico'],
        'moto_eletrica' => ['grupo' => 'eletrico', 'tipo_veiculo' => 'moto', 'requisito_veiculo' => 'eletrico'],
        'taxi' => ['grupo' => 'taxi', 'tipo_veiculo' => 'carro', 'requisito_veiculo' => 'taxi'],
        'moto_taxi' => ['grupo' => 'taxi', 'tipo_veiculo' => 'moto', 'requisito_veiculo' => 'taxi'],
    ];

    public function up(): void
    {
        Schema::table('produtos_corridas', function (Blueprint $table) {
            $table->string('grupo', 20)->nullable()->after('estrategia_precificacao');
            // veículo que atende: nulo aceita qualquer um
            $table->string('tipo_veiculo', 10)->nullable()->after('grupo');
            $table->string('requisito_veiculo', 20)->nullable()->after('tipo_veiculo');
        });

        Schema::table('veiculos', function (Blueprint $table) {
            $table->boolean('eletrico')->default(false)->after('categoria');
            $table->boolean('taxi')->default(false)->after('eletrico');
        });

        foreach (self::CLASSIFICACAO as $codigo => $classificacao) {
            DB::table('produtos_corridas')->where('codigo', $codigo)->update($classificacao);
        }

        // o código "taxi" era o Moto Táxi; o táxi de carro fica com ele
        DB::table('produtos_corridas')->where('codigo', 'taxi')->update(['nome' => 'Táxi']);

        foreach (['moto', 'carro'] as $tipo) {
            DB::table('produtos_corridas')
                ->where('estrategia_precificacao', 'entrega')
                ->where('nome', 'like', "%{$tipo}%")
                ->update(['grupo' => 'entrega', 'tipo_veiculo' => $tipo]);
        }

        // não existe moto elétrica: o produto sai das cotações, mas as
        // corridas antigas continuam apontando para ele
        $motoEletrica = DB::table('produtos_corridas')->where('codigo', 'moto_eletrica')->value('id');
        if ($motoEletrica !== null) {
            DB::table('tarifas')->where('produto_id', $motoEletrica)->update(['ativo' => false]);
        }

        // só carro e moto (a validação antiga aceitava bicicleta, e o seeder
        // de desenvolvimento gravava "pop")
        DB::table('veiculos')->where('categoria', 'like', 'moto%')->update(['categoria' => 'moto']);
        DB::table('veiculos')->whereNotIn('categoria', ['carro', 'moto'])->update(['categoria' => 'carro']);
    }

    public function down(): void
    {
        DB::table('produtos_corridas')->where('codigo', 'taxi')->update(['nome' => 'Moto Táxi']);

        $motoEletrica = DB::table('produtos_corridas')->where('codigo', 'moto_eletrica')->value('id');
        if ($motoEletrica !== null) {
            DB::table('tarifas')->where('produto_id', $motoEletrica)->update(['ativo' => true]);
        }

        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropColumn(['eletrico', 'taxi']);
        });

        Schema::table('produtos_corridas', function (Blueprint $table) {
            $table->dropColumn(['grupo', 'tipo_veiculo', 'requisito_veiculo']);
        });
    }
};
