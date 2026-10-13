<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('telefone')->unique()->nullable();
            $table->string('cpf', 11)->unique();
            $table->date('data_nascimento');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            // Mantidos temporariamente para compatibilidade com bancos antigos.
            // A aplicação usa as fotos e o status dos perfis.
            $table->string('foto')->nullable();
            $table->string('foto_thumbnail')->nullable();
            $table->rememberToken();
            $table->string('status');
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
