<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a sala vive antes de existir corrida: uma sala meio formada nunca é
        // confundida com corrida. Cada passageiro ganha a própria corrida
        // quando o grupo fecha, ligada à sala por corridas.sala_compartilhada_id
        Schema::create('salas_compartilhadas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 8)->unique();
            $table->string('tipo', 20);
            $table->string('status', 20)->index();
            $table->foreignId('criador_passageiro_id')->constrained('passageiros');
            $table->unsignedBigInteger('produto_id')->nullable();
            $table->unsignedBigInteger('tarifa_id')->nullable();
            $table->unsignedBigInteger('cidade_id')->nullable();
            $table->unsignedTinyInteger('capacidade')->default(4);
            $table->json('espinha');
            $table->json('rota')->nullable();
            $table->timestamp('expira_em');
            $table->timestamp('fecha_em')->nullable();
            $table->timestamp('fechada_em')->nullable();
            $table->timestamps();
        });

        Schema::create('sala_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sala_id')->constrained('salas_compartilhadas')->cascadeOnDelete();
            $table->foreignId('passageiro_id')->constrained('passageiros');
            $table->boolean('criador')->default(false);
            $table->string('status', 20)->default('na_sala');
            $table->string('embarque_endereco');
            $table->decimal('embarque_latitude', 10, 7);
            $table->decimal('embarque_longitude', 10, 7);
            $table->string('desembarque_endereco');
            $table->decimal('desembarque_latitude', 10, 7);
            $table->decimal('desembarque_longitude', 10, 7);
            $table->uuid('cotacao_id');
            $table->decimal('preco_solo', 10, 2);
            $table->unsignedInteger('solo_segundos');
            $table->decimal('solo_km', 8, 3);
            $table->decimal('preco_atual', 10, 2);
            $table->decimal('teto', 10, 2)->nullable();
            $table->integer('desvio_segundos')->default(0);
            $table->string('metodo_pagamento', 20);
            $table->timestamp('pronto_em')->nullable();
            $table->timestamp('entrou_em');
            $table->timestamp('saiu_em')->nullable();
            $table->unsignedTinyInteger('ordem_embarque')->nullable();
            $table->unsignedTinyInteger('ordem_desembarque')->nullable();
            $table->unsignedBigInteger('corrida_id')->nullable()->index();
            $table->timestamps();
            $table->index(['passageiro_id', 'status']);
        });

        Schema::table('corridas', function (Blueprint $table) {
            $table->foreignId('sala_compartilhada_id')->nullable()->after('cidade_id')
                ->constrained('salas_compartilhadas')->nullOnDelete();
        });

        Schema::table('veiculos', function (Blueprint $table) {
            // lugares para passageiros; a compartilhada não aparece se o grupo não couber
            $table->unsignedTinyInteger('assentos')->default(4)->after('categoria');
        });

        // motorista novo começa sem compartilhada: liga nas Preferências quando quiser
        Schema::table('motoristas', function (Blueprint $table) {
            $table->boolean('aceita_compartilhada')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('motoristas', fn (Blueprint $table) => $table->dropColumn('aceita_compartilhada'));
        Schema::table('veiculos', fn (Blueprint $table) => $table->dropColumn('assentos'));
        Schema::table('corridas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sala_compartilhada_id');
        });
        Schema::dropIfExists('sala_participantes');
        Schema::dropIfExists('salas_compartilhadas');
    }
};
