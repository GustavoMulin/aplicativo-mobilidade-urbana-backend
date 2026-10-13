<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->string('numero_crv', 20)->nullable()->after('renavam');
            $table->string('codigo_seguranca', 20)->nullable()->after('numero_crv');
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropColumn(['numero_crv', 'codigo_seguranca']);
        });
    }
};
