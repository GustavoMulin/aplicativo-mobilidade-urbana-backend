<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // categorias que o passageiro aceitou no pedido (ex.: Pop e Moto); a
        // corrida vai para quem atende qualquer uma e fica com a do motorista
        Schema::create('corrida_opcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrida_id')->constrained('corridas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos_corridas');
            $table->unsignedBigInteger('tarifa_id')->nullable();
            $table->decimal('valor_passageiro', 10, 2);
            $table->decimal('valor_motorista', 10, 2);
            // a categoria da cotação inteira, para refazer o financeiro no aceite
            $table->json('categoria');
            $table->timestamps();

            $table->unique(['corrida_id', 'produto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrida_opcoes');
    }
};
