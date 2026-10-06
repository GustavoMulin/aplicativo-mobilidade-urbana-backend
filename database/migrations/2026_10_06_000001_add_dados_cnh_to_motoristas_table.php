<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motoristas', function (Blueprint $table) {
            $table->string('numero_registro', 20)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('nome')->nullable();
            $table->string('cpf', 11)->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('primeira_habilitacao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('motoristas', function (Blueprint $table) {
            $table->dropColumn([
                'numero_registro',
                'data_nascimento',
                'nome',
                'cpf',
                'data_emissao',
                'primeira_habilitacao',
            ]);
        });
    }
};
