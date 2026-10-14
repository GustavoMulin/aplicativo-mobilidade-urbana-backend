<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestores', function (Blueprint $table): void {
            $table->string('perfil_acesso', 20)->default('atendimento')->index()->after('password');
        });
        // Preserva o acesso operacional dos gestores existentes e habilita a administração inicial.
        DB::table('gestores')->update(['perfil_acesso' => 'gestao']);
        $primeiro = DB::table('gestores')->whereNull('deleted_at')->orderBy('id')->value('id');
        if ($primeiro !== null) {
            DB::table('gestores')->where('id', $primeiro)->update(['perfil_acesso' => 'admin']);
        }
        Schema::create('gestao_auditorias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gestor_id')->nullable()->constrained('gestores')->nullOnDelete();
            $table->string('acao');
            $table->json('dados')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestao_auditorias');
        Schema::table('gestores', fn (Blueprint $table) => $table->dropColumn('perfil_acesso'));
    }
};
