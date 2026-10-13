<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expresso (como o Pop Expresso do 99 e a Prioridade do Uber): o
     * passageiro paga um pouco mais para ser atendido antes. A chamada vai
     * primeiro aos motoristas mais perto (raio inicial menor, que depois cresce
     * como nas outras categorias) e aparece na frente das outras para eles.
     * Qualquer carro atende.
     */
    public function up(): void
    {
        Schema::table('produtos_corridas', function (Blueprint $table) {
            $table->boolean('prioritario')->default(false)->after('requisito_veiculo');
        });

        // instalação nova recebe o Expresso pelo PrecificacaoSeeder
        $pop = DB::table('produtos_corridas')->where('codigo', 'pop')->first();
        if ($pop === null || DB::table('produtos_corridas')->where('codigo', 'expresso')->exists()) {
            return;
        }

        // entra logo depois do Normal · Carro, sem mudar a ordem das outras
        $ordem = (int) $pop->ordem + 1;
        DB::table('produtos_corridas')->where('ordem', '>=', $ordem)->increment('ordem');

        $agora = now();
        $produtoId = DB::table('produtos_corridas')->insertGetId([
            'codigo' => 'expresso',
            'nome' => 'Expresso · Carro',
            'estrategia_precificacao' => 'normal',
            'grupo' => 'expresso',
            'tipo_veiculo' => 'carro',
            'requisito_veiculo' => null,
            'prioritario' => true,
            'ordem' => $ordem,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $tarifaPop = DB::table('tarifas')->where('produto_id', $pop->id)->whereNull('cidade_id')->first();

        DB::table('tarifas')->insert([
            'produto_id' => $produtoId,
            'cidade_id' => null,
            'horario_inicio' => $tarifaPop->horario_inicio ?? '00:00:00',
            'horario_fim' => $tarifaPop->horario_fim ?? '23:59:59',
            'dias_semana' => $tarifaPop->dias_semana ?? json_encode([1, 2, 3, 4, 5, 6, 7]),
            'vira_dia' => $tarifaPop->vira_dia ?? false,
            'valor_minimo_corrida' => 9.50,
            'tarifa_base' => 2.50,
            'valor_por_km' => 1.85,
            'valor_por_minuto' => 0.33,
            'valor_por_minuto_espera' => 0.30,
            'taxa_plataforma_percentual' => $tarifaPop->taxa_plataforma_percentual ?? 6.00,
            'raio_busca_motorista_km' => 2,
            'ativo' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    }

    public function down(): void
    {
        $expresso = DB::table('produtos_corridas')->where('codigo', 'expresso')->first();

        if ($expresso !== null) {
            DB::table('tarifas')->where('produto_id', $expresso->id)->delete();
            DB::table('produtos_corridas')->where('id', $expresso->id)->delete();
            DB::table('produtos_corridas')->where('ordem', '>', $expresso->ordem)->decrement('ordem');
        }

        Schema::table('produtos_corridas', function (Blueprint $table) {
            $table->dropColumn('prioritario');
        });
    }
};
