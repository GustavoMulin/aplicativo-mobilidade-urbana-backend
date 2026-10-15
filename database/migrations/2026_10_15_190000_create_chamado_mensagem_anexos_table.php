<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamado_mensagem_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->constrained('chamado_mensagens')->cascadeOnDelete();
            $table->unsignedTinyInteger('ordem');
            $table->string('disk', 40)->default('ticket_anexos');
            $table->string('path');
            $table->string('nome');
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('tamanho');
            $table->unsignedInteger('largura');
            $table->unsignedInteger('altura');
            $table->char('sha256', 64);
            $table->timestamps();
            $table->unique(['mensagem_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamado_mensagem_anexos');
    }
};
