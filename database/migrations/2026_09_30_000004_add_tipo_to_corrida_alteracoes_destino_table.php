<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corrida_alteracoes_destino', function (Blueprint $table) {
            $table->string('tipo', 20)->default('destino')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('corrida_alteracoes_destino', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
