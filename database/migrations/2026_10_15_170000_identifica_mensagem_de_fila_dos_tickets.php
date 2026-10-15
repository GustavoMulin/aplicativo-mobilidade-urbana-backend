<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chamado_mensagens', function (Blueprint $table) {
            $table->string('tipo_sistema', 30)->nullable();
            $table->unique(['chamado_id', 'tipo_sistema'], 'chamado_mensagem_tipo_sistema_unique');
        });
        // Aproveita o aviso atual para que o próximo avanço atualize a mesma mensagem.
        DB::table('chamados_ajuda')->orderBy('id')->chunkById(100, function ($tickets) {
            foreach ($tickets as $ticket) {
                $aviso = DB::table('chamado_mensagens')->where('chamado_id', $ticket->id)
                    ->where('autor_tipo', 'sistema')->where(function ($query) {
                        $query->where('conteudo', 'like', 'Tem % na sua frente. Aguarde para ser atendido.')
                            ->orWhere('conteudo', 'Chegou sua vez! Seu atendimento foi iniciado.');
                    })->orderByDesc('id')->first();
                if ($aviso) {
                    DB::table('chamado_mensagens')->where('id', $aviso->id)->update(['tipo_sistema' => 'fila_atendimento']);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('chamado_mensagens', function (Blueprint $table) {
            $table->dropUnique('chamado_mensagem_tipo_sistema_unique');
            $table->dropColumn('tipo_sistema');
        });
    }
};
