<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locais_salvos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // casa | trabalho | favorito — casa e trabalho são no máximo um por
            // usuário (garantido no controller, porque favorito pode repetir)
            $table->string('tipo', 20);
            $table->string('nome', 60)->nullable();
            $table->string('endereco', 255);
            $table->string('descricao', 255)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();

            $table->index(['user_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locais_salvos');
    }
};
