<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldo_motoristas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorista_id')->constrained('motoristas')->cascadeOnDelete();
            $table->foreignId('corrida_id')->nullable()->constrained('corridas')->nullOnDelete();
            $table->enum('tipo', ['entrada', 'saida']);
            $table->decimal('valor', 10, 2);
            $table->string('descricao');
            $table->enum('metodo_pagamento', ['dinheiro', 'pix', 'cartao_online', 'saldo_carteira'])->nullable();
            $table->decimal('saldo_anterior', 10, 2)->default(0);
            $table->decimal('saldo_posterior', 10, 2)->default(0);
            $table->json('detalhes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index(['motorista_id', 'created_at'], 'idx_motorista_data');
            $table->index('tipo', 'idx_tipo');
            $table->index(['created_at', 'id'], 'idx_saldo_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_motoristas');
    }
};
