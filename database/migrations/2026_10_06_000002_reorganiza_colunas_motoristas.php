<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motoristas', function (Blueprint $table) {
            $table->string('nome')->nullable()->after('user_id')->change();
            $table->string('cpf', 11)->nullable()->after('nome')->change();
            $table->date('data_nascimento')->nullable()->after('cpf')->change();
            $table->string('cnh_numero')->nullable()->after('data_nascimento')->change();
            $table->string('numero_registro', 20)->nullable()->after('cnh_numero')->change();
            $table->string('cnh_categoria')->nullable()->after('numero_registro')->change();
            $table->date('primeira_habilitacao')->nullable()->after('cnh_categoria')->change();
            $table->date('data_emissao')->nullable()->after('primeira_habilitacao')->change();
            $table->date('cnh_expiracao')->nullable()->after('data_emissao')->change();
            $table->boolean('ear')->nullable()->after('cnh_expiracao')->change();
            $table->string('status')->default('pendente')->after('ear')->change();
        });
    }

    public function down(): void
    {
        Schema::table('motoristas', function (Blueprint $table) {
            $table->string('status')->default('pendente')->after('user_id')->change();
            $table->string('cnh_numero')->nullable()->after('status')->change();
            $table->string('cnh_categoria')->nullable()->after('cnh_numero')->change();
            $table->date('cnh_expiracao')->nullable()->after('cnh_categoria')->change();
            $table->boolean('ear')->nullable()->after('cnh_expiracao')->change();
            $table->string('numero_registro', 20)->nullable()->after('deleted_at')->change();
            $table->date('data_nascimento')->nullable()->after('numero_registro')->change();
            $table->string('nome')->nullable()->after('data_nascimento')->change();
            $table->string('cpf', 11)->nullable()->after('nome')->change();
            $table->date('data_emissao')->nullable()->after('cpf')->change();
            $table->date('primeira_habilitacao')->nullable()->after('data_emissao')->change();
        });
    }
};
