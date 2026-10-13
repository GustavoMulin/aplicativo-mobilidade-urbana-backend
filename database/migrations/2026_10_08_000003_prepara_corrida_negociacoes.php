<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Negocia: o passageiro oferece um valor, cada motorista aceita esse valor
     * ou manda outro, e o passageiro escolhe uma das propostas.
     */
    public function up(): void
    {
        Schema::table('corrida_negociacoes', function (Blueprint $table) {
            $table->unsignedBigInteger('motorista_id')->nullable()->after('usuario_id');
            $table->unsignedBigInteger('veiculo_id')->nullable()->after('motorista_id');
            $table->decimal('valor_motorista', 10, 2)->nullable()->after('valor_proposto');
            $table->decimal('valor_passageiro', 10, 2)->nullable()->after('valor_motorista');
        });

        // pendente, aceita, recusada (outra foi escolhida), expirada, cancelada
        Schema::table('corrida_negociacoes', function (Blueprint $table) {
            $table->string('status', 20)->default('pendente')->change();
        });

        DB::table('corrida_negociacoes')->where('status', 'aceito')->update(['status' => 'aceita']);
        DB::table('corrida_negociacoes')->where('status', 'recusado')->update(['status' => 'recusada']);

        Schema::table('corrida_negociacoes', function (Blueprint $table) {
            $table->unique(['corrida_id', 'motorista_id']);
            $table->index(['corrida_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('corrida_negociacoes', function (Blueprint $table) {
            $table->dropUnique(['corrida_id', 'motorista_id']);
            $table->dropIndex(['corrida_id', 'status']);
            $table->dropColumn(['motorista_id', 'veiculo_id', 'valor_motorista', 'valor_passageiro']);
        });

        DB::table('corrida_negociacoes')->where('status', 'aceita')->update(['status' => 'aceito']);
        DB::table('corrida_negociacoes')->whereIn('status', ['recusada', 'expirada', 'cancelada'])->update(['status' => 'recusado']);

        Schema::table('corrida_negociacoes', function (Blueprint $table) {
            $table->enum('status', ['pendente', 'aceito', 'recusado'])->change();
        });
    }
};
