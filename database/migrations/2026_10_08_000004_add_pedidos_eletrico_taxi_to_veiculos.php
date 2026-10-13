<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // o motorista pede Elétrico ou Táxi pelo app; "eletrico" e "taxi" só
        // passam a valer (e a trazer essas corridas) quando a gestão aprova
        Schema::table('veiculos', function (Blueprint $table) {
            $table->boolean('eletrico_solicitado')->default(false)->after('taxi');
            $table->boolean('taxi_solicitado')->default(false)->after('eletrico_solicitado');
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropColumn(['eletrico_solicitado', 'taxi_solicitado']);
        });
    }
};
