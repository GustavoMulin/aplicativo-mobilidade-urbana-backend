<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quem é passageiro e motorista na mesma conta via as notificações dos
     * dois papéis nos dois apps. Agora cada uma diz para qual papel é.
     */
    public function up(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            $table->string('perfil', 20)->nullable()->after('user_id');
            $table->index(['user_id', 'perfil']);
        });

        // as que já existem: o título diz de qual lado é
        $porTitulo = [
            'motorista' => ['Corrida finalizada', 'Pagamento recebido', 'O passageiro escolheu sua proposta'],
            'passageiro' => ['Viagem concluída', 'Pagamento pendente', 'Nova proposta no Negocia'],
        ];

        foreach ($porTitulo as $perfil => $titulos) {
            DB::table('notificacoes')->whereNull('perfil')->whereIn('titulo', $titulos)->update(['perfil' => $perfil]);
        }
    }

    public function down(): void
    {
        Schema::table('notificacoes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'perfil']);
            $table->dropColumn('perfil');
        });
    }
};
