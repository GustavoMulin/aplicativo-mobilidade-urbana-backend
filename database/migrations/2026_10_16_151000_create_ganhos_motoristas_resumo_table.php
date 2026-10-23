<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ganhos_motoristas_resumo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorista_id')->constrained('motoristas')->cascadeOnDelete();
            $table->date('data_referencia');
            $table->enum('tipo_servico', ['corrida', 'entrega', 'todos'])->default('todos');
            foreach (['total_ganhos', 'total_dinheiro', 'total_online', 'total_nao_identificado', 'valor_solicitacoes', 'recompensas', 'gorjetas', 'compensacoes', 'outros'] as $campo) {
                $table->decimal($campo, 10, 2)->default(0);
            }
            $table->unsignedInteger('quantidade_solicitacoes')->default(0);
            $table->decimal('km_rodados', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['motorista_id', 'data_referencia', 'tipo_servico'], 'uq_motorista_data_servico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ganhos_motoristas_resumo');
    }
};
