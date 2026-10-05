<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cada corrida que apareceu para o motorista; a taxa de aceitação
        // é quantas dessas ele acabou aceitando
        Schema::create('ofertas_motorista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorista_id')->constrained('motoristas')->cascadeOnDelete();
            $table->foreignId('corrida_id')->constrained('corridas')->cascadeOnDelete();
            $table->timestamp('ofertada_em');

            $table->unique(['motorista_id', 'corrida_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofertas_motorista');
    }
};
