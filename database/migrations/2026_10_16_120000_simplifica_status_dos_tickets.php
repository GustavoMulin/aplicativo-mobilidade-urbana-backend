<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $operadores = DB::table('chamados_ajuda')->whereIn('status', ['pending', 'closed'])
                ->whereNotNull('gestor_responsavel_id')->distinct()->orderBy('gestor_responsavel_id')
                ->pluck('gestor_responsavel_id');

            DB::table('gestores')->whereIn('id', $operadores)->orderBy('id')->lockForUpdate()->get();

            // Query Builder preserva updated_at; mensagens e transferências são auditoria histórica.
            DB::table('chamados_ajuda')->where('status', 'pending')->whereNull('gestor_responsavel_id')->update([
                'status' => 'open', 'fila_entrada_em' => null, 'fila_posicao' => null,
            ]);
            DB::table('chamados_ajuda')->where('status', 'pending')->whereNotNull('gestor_responsavel_id')->update([
                'status' => 'waiting',
                'fila_entrada_em' => DB::raw('COALESCE(fila_entrada_em, updated_at, created_at, CURRENT_TIMESTAMP)'),
                'fila_posicao' => null,
            ]);
            DB::table('chamados_ajuda')->where('status', 'closed')->update([
                'status' => 'resolved',
                'resolvido_em' => DB::raw('COALESCE(resolvido_em, fechado_em, updated_at, created_at)'),
                'fila_entrada_em' => null, 'fila_posicao' => null,
            ]);

            // Recalcula as filas afetadas sem iniciar tickets ou gerar/editar mensagens.
            foreach ($operadores as $operador) {
                $tickets = DB::table('chamados_ajuda')->where('gestor_responsavel_id', $operador)
                    ->whereIn('status', ['in_progress', 'waiting'])
                    ->orderBy('fila_entrada_em')->orderBy('id')->lockForUpdate()->get();
                $posicao = $tickets->where('status', 'in_progress')->count();
                foreach ($tickets as $ticket) {
                    DB::table('chamados_ajuda')->where('id', $ticket->id)->update([
                        'fila_posicao' => $ticket->status === 'in_progress' ? 0 : $posicao++,
                    ]);
                }
            }
        }, 3);
    }

    public function down(): void
    {
        // Normalização definitiva: não é possível distinguir os estados originais sem inventar histórico.
        // Os quatro estados resultantes também são aceitos pela versão anterior do backend.
    }
};
