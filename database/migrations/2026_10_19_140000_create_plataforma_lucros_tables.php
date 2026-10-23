<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plataforma_lucros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrida_id')->nullable()->constrained('corridas')->nullOnDelete();
            $table->string('origem', 120)->unique();
            $table->string('descricao', 255);
            $table->enum('tipo_operacao', ['comissao_corrida', 'subsidio_tarifa', 'recompensa_bonus', 'compensacao_reembolso', 'ajuste_manual']);
            $table->enum('tipo_servico', ['corrida', 'entrega', 'outro'])->default('outro');
            $table->enum('origem_pagamento', ['online_cartao', 'online_pix', 'dinheiro_motorista', 'nao_identificada'])->default('nao_identificada');
            foreach (['valor_bruto_passageiro', 'repasse_motorista', 'margem_bruta_retida', 'comissao_prevista', 'custo_gateway', 'subsidio_aplicado', 'bonus_alocado', 'compensacao_paga', 'lucro_liquido'] as $campo) {
                // NULL significa desconhecido. Zero precisa de confirmação para fechar custos.
                $table->decimal($campo, 10, 2)->nullable();
            }
            $table->enum('status_caixa', ['aguardando_liquidacao', 'realizado_em_conta', 'a_receber_motorista', 'estornado'])->default('aguardando_liquidacao');
            $table->enum('status_custos', ['consolidado', 'custos_pendentes'])->default('custos_pendentes');
            $table->json('pendencias_detalhes')->nullable();
            $table->json('custos_confirmados')->nullable();
            $table->json('custos_incluidos_repasse')->nullable();
            $table->boolean('composicao_conferida')->default(false);
            $table->json('snapshot_origem')->nullable();
            $table->string('hash_origem', 64)->nullable();
            $table->timestamp('ocorrido_em')->index();
            $table->timestamp('recebido_em')->nullable();
            $table->string('referencia_liquidacao', 255)->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->boolean('demonstracao')->default(false)->index();
            $table->timestamps();
            $table->index(['ocorrido_em', 'status_caixa', 'status_custos'], 'idx_plataforma_data_status');
        });
        Schema::create('plataforma_lucros_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plataforma_lucro_id')->constrained('plataforma_lucros')->restrictOnDelete();
            $table->foreignId('gestor_id')->nullable()->constrained('gestores')->nullOnDelete();
            $table->string('gestor_nome')->nullable();
            $table->string('acao', 50);
            $table->uuid('client_request_id')->nullable()->unique();
            $table->unsignedInteger('versao');
            $table->string('motivo', 1000);
            $table->string('referencia', 255)->nullable();
            $table->json('antes')->nullable();
            $table->json('depois');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['plataforma_lucro_id', 'versao'], 'uq_plataforma_auditoria_versao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plataforma_lucros_auditoria');
        Schema::dropIfExists('plataforma_lucros');
    }
};
