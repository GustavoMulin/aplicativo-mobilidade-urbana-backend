<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// quando a corrida foi cancelada: a taxa de cancelamento entra nos ganhos do
// motorista nesse dia (updated_at muda depois, na liquidação do pagamento)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corridas', function (Blueprint $table) {
            $table->timestamp('cancelada_em')->nullable()->after('tempo_final');
        });
    }

    public function down(): void
    {
        Schema::table('corridas', function (Blueprint $table) {
            $table->dropColumn('cancelada_em');
        });
    }
};
