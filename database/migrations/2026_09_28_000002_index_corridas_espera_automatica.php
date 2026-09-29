<?php

// CODEX: 24 linhas alteradas; avaliação, embarque e cancelamentos. Remover após validação.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corridas', function (Blueprint $table) {
            $table->index(['status_corrida', 'tempo_chegada_origem'], 'corridas_espera_automatica_idx');
        });
    }

    public function down(): void
    {
        Schema::table('corridas', function (Blueprint $table) {
            $table->dropIndex('corridas_espera_automatica_idx');
        });
    }
};
