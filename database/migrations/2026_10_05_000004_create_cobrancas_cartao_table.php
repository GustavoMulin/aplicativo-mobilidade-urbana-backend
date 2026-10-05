<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cobrança de cartão feita por checkout hospedado na AbacatePay: o cartão
        // é digitado na página deles, e o app só guarda a referência e o status
        Schema::create('cobrancas_cartao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corrida_id')->constrained('corridas')->cascadeOnDelete();
            $table->string('produto_id');
            $table->string('checkout_id')->unique();
            $table->text('url');
            $table->string('status', 20);
            $table->unsignedInteger('valor_centavos');
            $table->boolean('dev_mode')->default(false);
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas_cartao');
    }
};
