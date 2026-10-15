<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrida_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrida_id')->constrained('corridas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('autor_tipo', 20);
            $table->string('autor_nome');
            $table->text('conteudo');
            $table->uuid('client_message_id');
            $table->timestamps();
            $table->unique(['corrida_id', 'client_message_id']);
            $table->index(['corrida_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrida_mensagens');
    }
};
