<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// cartão simulado (ou de um provedor sem página de checkout): não há produto
// nem url externos
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas_cartao', function (Blueprint $table) {
            $table->string('produto_id')->nullable()->change();
            $table->text('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas_cartao', function (Blueprint $table) {
            $table->string('produto_id')->nullable(false)->change();
            $table->text('url')->nullable(false)->change();
        });
    }
};
