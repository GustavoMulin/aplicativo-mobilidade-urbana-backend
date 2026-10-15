<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('chamados_ajuda')->cascadeOnDelete();
            // Operadores do painel pertencem a gestores; users identifica o solicitante.
            $table->foreignId('transferred_by_user_id')->nullable()->constrained('gestores')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('gestores')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('gestores')->nullOnDelete();
            // Preserva os nomes da época mesmo após renomear ou excluir um operador.
            $table->string('transferred_by_name');
            $table->string('from_user_name')->nullable();
            $table->string('to_user_name')->nullable();
            $table->string('type', 40);
            $table->text('note')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['ticket_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_transfers');
    }
};
