<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saldo_motoristas', function (Blueprint $table) {
            $table->string('categoria', 80)->nullable()->after('tipo');
            $table->index(['categoria', 'motorista_id', 'created_at'], 'idx_saldo_categoria_motorista_data');
        });
        // Copia somente a classificação. Não modifica valores, JSON ou datas de auditoria.
        DB::table('saldo_motoristas')->select(['id', 'detalhes', 'updated_at'])->orderBy('id')->chunkById(500, function ($registros) {
            foreach ($registros as $registro) {
                $dados = is_string($registro->detalhes) ? json_decode($registro->detalhes, true) : $registro->detalhes;
                $categoria = $dados['categoria'] ?? null;
                if (is_string($categoria) && $categoria !== '' && mb_strlen($categoria) <= 80) {
                    $categoria = ['ganho_corrida' => 'valor_corrida', 'bonus' => 'recompensa'][$categoria] ?? $categoria;
                    DB::table('saldo_motoristas')->where('id', $registro->id)->update(['categoria' => $categoria, 'updated_at' => $registro->updated_at]);
                }
            }
        });
    }

    public function down(): void
    {
        // Mantém a classificação no JSON inclusive para lançamentos criados após a migration.
        DB::table('saldo_motoristas')->whereNotNull('categoria')->orderBy('id')->chunkById(500, function ($registros) {
            foreach ($registros as $registro) {
                $dados = json_decode($registro->detalhes ?? '{}', true) ?: [];
                $dados['categoria'] = $registro->categoria;
                DB::table('saldo_motoristas')->where('id', $registro->id)->update(['detalhes' => json_encode($dados), 'updated_at' => $registro->updated_at]);
            }
        });
        Schema::table('saldo_motoristas', function (Blueprint $table) {
            $table->dropIndex('idx_saldo_categoria_motorista_data');
            $table->dropColumn('categoria');
        });
    }
};
