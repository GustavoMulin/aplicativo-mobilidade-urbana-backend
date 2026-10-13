<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Havia duas tabelas de cidade: `municipios` (vazia, só alvo das chaves
     * de corridas e tarifas) e `cidades` (publicidade, com IBGE e UF). Fica
     * uma só, `cidades`, para tarifa por cidade, corrida e banner.
     */
    public function up(): void
    {
        if (DB::table('municipios')->exists()) {
            throw new RuntimeException('A tabela municipios tem dados: migre-os para cidades antes de rodar esta migration.');
        }

        Schema::table('corridas', function (Blueprint $table) {
            $table->dropForeign(['cidade_id']);
            // apagar uma cidade não apaga as corridas dela
            $table->foreign('cidade_id')->references('id')->on('cidades')->nullOnDelete();
        });

        Schema::table('tarifas', function (Blueprint $table) {
            $table->dropForeign(['cidade_id']);
            // tarifa de cidade não vira tarifa geral sem querer
            $table->foreign('cidade_id')->references('id')->on('cidades')->restrictOnDelete();
        });

        Schema::dropIfExists('municipios');
    }

    public function down(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->integer('codigo');
            $table->string('nome');
            $table->string('uf');
            $table->timestamps();
        });

        DB::table('corridas')->update(['cidade_id' => null]);
        DB::table('tarifas')->update(['cidade_id' => null]);

        Schema::table('corridas', function (Blueprint $table) {
            $table->dropForeign(['cidade_id']);
            $table->foreign('cidade_id')->references('id')->on('municipios')->cascadeOnDelete();
        });

        Schema::table('tarifas', function (Blueprint $table) {
            $table->dropForeign(['cidade_id']);
            $table->foreign('cidade_id')->references('id')->on('municipios')->nullOnDelete();
        });
    }
};
