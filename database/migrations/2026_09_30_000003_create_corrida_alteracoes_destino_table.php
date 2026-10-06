<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pedido do passageiro para trocar o destino depois do aceite; só
        // vale quando o motorista aceita
        Schema::create('corrida_alteracoes_destino', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrida_id')->constrained('corridas')->cascadeOnDelete();
            $table->string('status', 20)->default('pendente');
            $table->string('endereco');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('distancia_km', 10, 2);
            $table->decimal('tempo_min', 10, 2);
            $table->decimal('valor_passageiro', 10, 2);
            $table->decimal('valor_motorista', 10, 2);
            $table->decimal('valor_passageiro_anterior', 10, 2)->nullable();
            $table->decimal('valor_motorista_anterior', 10, 2)->nullable();
            $table->timestamp('respondida_em')->nullable();
            $table->timestamps();

            $table->index(['corrida_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrida_alteracoes_destino');
    }
};
