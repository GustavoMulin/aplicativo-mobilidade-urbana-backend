<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_leituras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('chamados_ajuda')->cascadeOnDelete();
            $table->foreignId('gestor_id')->constrained('gestores')->cascadeOnDelete();
            // Cursor de leitura, preservado mesmo que uma mensagem seja removida.
            $table->unsignedBigInteger('ultima_mensagem_id')->default(0);
            $table->timestamp('lida_em')->nullable();
            $table->unique(['ticket_id', 'gestor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_leituras');
    }
};
