<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // motorista que desiste de um grupo fica um tempo sem chamadas de grupo
        Schema::table('motoristas', function (Blueprint $table) {
            $table->timestamp('compartilhada_suspensa_ate')->nullable()->after('aceita_compartilhada');
        });
    }

    public function down(): void
    {
        Schema::table('motoristas', fn (Blueprint $table) => $table->dropColumn('compartilhada_suspensa_ate'));
    }
};
