<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aparelhos que recebem push (token do FCM). O mesmo usuário pode ter o app
     * do passageiro e o do motorista: o perfil diz qual app recebe o aviso.
     */
    public function up(): void
    {
        Schema::create('dispositivos_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('perfil', 20);
            $table->string('token', 512)->unique();
            $table->string('plataforma', 20)->default('android');
            $table->timestamps();

            $table->index(['user_id', 'perfil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_push');
    }
};
