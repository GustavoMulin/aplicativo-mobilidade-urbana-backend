<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legados = DB::table('motoristas')
            ->where(function ($query) {
                $query->whereNull('numero_registro')->orWhereRaw("TRIM(numero_registro) = ''");
            })
            ->whereNotNull('cnh_numero')
            ->whereRaw("TRIM(cnh_numero) <> ''");

        if ((clone $legados)->whereRaw('LENGTH(TRIM(cnh_numero)) > 20')->exists()) {
            throw new RuntimeException('Existe um número de CNH antigo com mais de 20 caracteres. Corrija o número de registro antes de remover a coluna.');
        }

        $legados->update(['numero_registro' => DB::raw('TRIM(cnh_numero)')]);

        Schema::table('motoristas', function (Blueprint $table) {
            $table->dropColumn('cnh_numero');
        });
    }

    public function down(): void
    {
        Schema::table('motoristas', function (Blueprint $table) {
            $table->string('cnh_numero')->nullable()->after('data_nascimento');
        });

        DB::table('motoristas')->update(['cnh_numero' => DB::raw('numero_registro')]);
    }
};
