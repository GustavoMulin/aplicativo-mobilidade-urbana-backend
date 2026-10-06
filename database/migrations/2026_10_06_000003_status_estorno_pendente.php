<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corridas', function (Blueprint $table) {
            // estorno_pendente: a AbacatePay não respondeu ao estorno; o agendador refaz
            $table->enum('status_pagamento', [
                'pendente',
                'pago',
                'em_aberto',
                'estornado',
                'estorno_pendente',
                'sem_cobranca',
                'falhou',
            ])->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('corridas')->where('status_pagamento', 'estorno_pendente')->update(['status_pagamento' => 'pago']);

        Schema::table('corridas', function (Blueprint $table) {
            $table->enum('status_pagamento', [
                'pendente',
                'pago',
                'em_aberto',
                'estornado',
                'sem_cobranca',
                'falhou',
            ])->nullable()->change();
        });
    }
};
