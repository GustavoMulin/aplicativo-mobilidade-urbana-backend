<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corridas', function (Blueprint $table) {
            // como no 99, o passageiro troca o pagamento uma vez por corrida
            $table->timestamp('pagamento_alterado_em')->nullable()->after('status_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('corridas', function (Blueprint $table) {
            $table->dropColumn('pagamento_alterado_em');
        });
    }
};
