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
        Schema::create('gestores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('telefone')->unique()->nullable();
            $table->string('cpf', 11)->unique();
            $table->date('data_nascimento');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('foto')->nullable();
            $table->rememberToken();
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
        Schema::dropIfExists('gestores');
    }
};