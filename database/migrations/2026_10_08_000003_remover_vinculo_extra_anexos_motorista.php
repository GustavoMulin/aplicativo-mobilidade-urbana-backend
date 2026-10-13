<?php

use App\Services\ArmazenarAnexoMotoristaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('motorista_documentos_anexos', 'anexo_principal_id')) {
            return;
        }
        $origens = [];
        DB::transaction(function () use (&$origens) {
            $versos = DB::table('motorista_documentos_anexos')->where('ordem', 1)->lockForUpdate()->get();
            foreach ($versos as $verso) {
                $frente = DB::table('motorista_documentos_anexos')->where('id', $verso->anexo_principal_id)->first();
                if ($frente === null || $frente->tipo_documento !== 'cnh' || $frente->motorista_id !== $verso->motorista_id) {
                    throw new RuntimeException('Não foi possível identificar os lados da CNH.');
                }
                $dados = app(ArmazenarAnexoMotoristaService::class)->padronizarVerso((array) $frente, (array) $verso);
                DB::table('motorista_documentos_anexos')->where('id', $verso->id)
                    ->update(['path' => $dados['path'], 'url' => $dados['url']]);
                $origens[] = $verso->path;
            }
        });
        Schema::table('motorista_documentos_anexos', function (Blueprint $table) {
            $table->dropForeign(['anexo_principal_id']);
            $table->dropUnique('anexos_principal_ordem_unique');
            $table->dropColumn('anexo_principal_id');
        });
        foreach ($origens as $path) {
            if (! DB::table('motorista_documentos_anexos')->where('path', $path)->exists()) {
                app(ArmazenarAnexoMotoristaService::class)->excluir($path);
            }
        }
    }

    public function down(): void
    {
        // A migração anterior corrigida também não contém a coluna extra.
    }
};
