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
            // aguardando_pagamento: Pix/cartão pedido, ainda invisível aos motoristas
            $table->enum('status_corrida', [
                'aguardando_pagamento',
                'solicitada',
                'aceita',
                'em_andamento',
                'finalizada',
                'cancelada',
                'em_busca',
                'motorista_chegou',
            ])->default('solicitada')->change();

            // em_aberto: falta pagar (bloqueia o próximo pedido);
            // sem_cobranca: cancelada sem nada a pagar
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

    public function down(): void
    {
        DB::table('corridas')->where('status_corrida', 'aguardando_pagamento')->update(['status_corrida' => 'cancelada']);
        DB::table('corridas')->whereIn('status_pagamento', ['em_aberto', 'sem_cobranca'])->update(['status_pagamento' => 'pendente']);

        Schema::table('corridas', function (Blueprint $table) {
            $table->enum('status_corrida', [
                'solicitada',
                'aceita',
                'em_andamento',
                'finalizada',
                'cancelada',
                'em_busca',
                'motorista_chegou',
            ])->default('solicitada')->change();

            $table->enum('status_pagamento', [
                'pendente',
                'pago',
                'estornado',
                'falhou',
            ])->nullable()->change();
        });
    }
};
