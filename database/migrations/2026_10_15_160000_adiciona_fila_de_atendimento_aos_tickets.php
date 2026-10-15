<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chamados_ajuda', function (Blueprint $table) {
            $table->timestamp('fila_entrada_em', 6)->nullable();
            $table->unsignedInteger('fila_posicao')->nullable();
            $table->index(['gestor_responsavel_id', 'status', 'fila_entrada_em'], 'tickets_fila_operador_index');
        });

        // Organiza os atendimentos existentes sem apagar mensagens ou responsáveis.
        $operadores = DB::table('chamados_ajuda')->whereNotNull('gestor_responsavel_id')
            ->where('status', 'in_progress')->distinct()->pluck('gestor_responsavel_id');
        foreach ($operadores as $operador) {
            DB::transaction(function () use ($operador) {
                DB::table('gestores')->where('id', $operador)->lockForUpdate()->first();
                $tickets = DB::table('chamados_ajuda')->where('gestor_responsavel_id', $operador)
                    ->where('status', 'in_progress')->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
                $tickets = $tickets->map(function ($ticket) use ($operador) {
                    $ticket->entrada_fila = DB::table('ticket_transfers')->where('ticket_id', $ticket->id)
                        ->where('to_user_id', $operador)->max('created_at') ?? $ticket->created_at;

                    return $ticket;
                })->sortBy([['entrada_fila', 'asc'], ['id', 'asc']])->values();
                foreach ($tickets as $posicao => $ticket) {
                    DB::table('chamados_ajuda')->where('id', $ticket->id)->update([
                        'status' => $posicao === 0 ? 'in_progress' : 'waiting',
                        'fila_entrada_em' => $ticket->entrada_fila,
                        'fila_posicao' => $posicao,
                    ]);
                    if ($posicao > 0) {
                        DB::table('chamado_mensagens')->insert([
                            'chamado_id' => $ticket->id, 'autor_tipo' => 'sistema', 'autor_nome' => 'Sistema',
                            'conteudo' => 'Tem '.$posicao.($posicao === 1 ? ' pessoa' : ' pessoas').' na sua frente. Aguarde para ser atendido.',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }, 3);
        }
    }

    public function down(): void
    {
        // Evita reabrir múltiplos atendimentos ativos ao remover a fila.
        DB::table('chamados_ajuda')->where('status', 'waiting')->update(['status' => 'pending']);
        Schema::table('chamados_ajuda', function (Blueprint $table) {
            $table->dropIndex('tickets_fila_operador_index');
            $table->dropColumn(['fila_entrada_em', 'fila_posicao']);
        });
    }
};
