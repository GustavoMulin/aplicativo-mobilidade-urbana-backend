<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // crédito do passageiro usado para abater o valor de uma corrida pré-paga
        Schema::table('corrida_financeiros', function (Blueprint $table) {
            $table->decimal('credito_aplicado', 10, 2)->default(0);
        });

        Schema::table('cobrancas_pix', function (Blueprint $table) {
            $table->timestamp('estornado_em')->nullable();
        });

        Schema::table('cobrancas_cartao', function (Blueprint $table) {
            $table->timestamp('estornado_em')->nullable();
        });

        // extrato de crédito no app: positivo entra (sobra de pagamento), negativo
        // sai (uso em corrida). O saldo é a soma dos movimentos.
        Schema::create('movimentos_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passageiro_id')->constrained('passageiros')->cascadeOnDelete();
            $table->foreignId('corrida_id')->nullable()->constrained('corridas')->nullOnDelete();
            $table->decimal('valor', 10, 2);
            $table->string('descricao', 120);
            $table->timestamps();

            $table->index('passageiro_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos_credito');

        Schema::table('cobrancas_cartao', function (Blueprint $table) {
            $table->dropColumn('estornado_em');
        });

        Schema::table('cobrancas_pix', function (Blueprint $table) {
            $table->dropColumn('estornado_em');
        });

        Schema::table('corrida_financeiros', function (Blueprint $table) {
            $table->dropColumn('credito_aplicado');
        });
    }
};
