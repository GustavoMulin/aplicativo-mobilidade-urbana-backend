<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corrida_destinos', function (Blueprint $table) {
            // quando o motorista confirmou a passagem pela parada; a
            // navegação segue para o próximo ponto pendente da rota
            $table->timestamp('concluida_em')->nullable()->after('distancia_ate_proximo_destino');
        });
    }

    public function down(): void
    {
        Schema::table('corrida_destinos', function (Blueprint $table) {
            $table->dropColumn('concluida_em');
        });
    }
};
