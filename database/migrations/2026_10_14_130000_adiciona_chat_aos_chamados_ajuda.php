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
            $table->string('status', 20)->default('open')->change();
            $table->string('solicitante_tipo', 20)->default('passageiro');
            $table->foreignId('gestor_responsavel_id')->nullable()->constrained('gestores')->nullOnDelete();
            $table->timestamp('ultima_mensagem_em')->nullable();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamp('fechado_em')->nullable();
            $table->index(['status', 'ultima_mensagem_em']);
            $table->index(['gestor_responsavel_id', 'status']);
        });

        Schema::create('chamado_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamado_id')->constrained('chamados_ajuda')->cascadeOnDelete();
            $table->string('autor_tipo', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gestor_id')->nullable()->constrained('gestores')->nullOnDelete();
            $table->string('autor_nome');
            $table->text('conteudo');
            $table->uuid('client_message_id')->nullable();
            $table->timestamps();
            $table->index(['chamado_id', 'id']);
            $table->unique(['chamado_id', 'client_message_id']);
        });

        // Usa Query Builder para não depender de versões futuras dos models/services.
        DB::table('chamados_ajuda')->orderBy('id')->chunkById(100, function ($tickets) {
            foreach ($tickets as $ticket) {
                $driver = DB::table('motoristas')->where('user_id', $ticket->user_id)->exists();
                $passenger = DB::table('passageiros')->where('user_id', $ticket->user_id)->exists();
                $tipo = $driver && ! $passenger ? 'motorista' : 'passageiro';
                if ($ticket->corrida_id && $driver) {
                    $driverId = DB::table('motoristas')->where('user_id', $ticket->user_id)->value('id');
                    if (DB::table('corridas')->where('id', $ticket->corrida_id)->where('motorista_id', $driverId)->exists()) {
                        $tipo = 'motorista';
                    }
                }
                $status = match ($ticket->status) {
                    'aberto' => 'open', 'em_atendimento' => 'in_progress', 'pendente' => 'pending',
                    'resolvido' => 'resolved', 'fechado' => 'closed', default => $ticket->status,
                };
                DB::table('chamados_ajuda')->where('id', $ticket->id)->update([
                    'status' => $status, 'solicitante_tipo' => $tipo,
                    'ultima_mensagem_em' => $ticket->created_at,
                    'resolvido_em' => $status === 'resolved' ? $ticket->updated_at : null,
                    'fechado_em' => $status === 'closed' ? $ticket->updated_at : null,
                ]);
                DB::table('chamado_mensagens')->insert([
                    'chamado_id' => $ticket->id, 'autor_tipo' => 'usuario', 'user_id' => $ticket->user_id,
                    'autor_nome' => DB::table('users')->where('id', $ticket->user_id)->value('name') ?? 'Usuário',
                    'conteudo' => $ticket->descricao, 'created_at' => $ticket->created_at, 'updated_at' => $ticket->created_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamado_mensagens');
        Schema::table('chamados_ajuda', function (Blueprint $table) {
            $table->dropForeign(['gestor_responsavel_id']);
            $table->dropIndex(['status', 'ultima_mensagem_em']);
            $table->dropIndex(['gestor_responsavel_id', 'status']);
            $table->dropColumn(['gestor_responsavel_id', 'solicitante_tipo', 'ultima_mensagem_em', 'resolvido_em', 'fechado_em']);
            $table->string('status', 20)->default('aberto')->change();
        });
        foreach (['open' => 'aberto', 'in_progress' => 'em_atendimento', 'pending' => 'pendente', 'resolved' => 'resolvido', 'closed' => 'fechado'] as $novo => $antigo) {
            DB::table('chamados_ajuda')->where('status', $novo)->update(['status' => $antigo]);
        }
    }
};
