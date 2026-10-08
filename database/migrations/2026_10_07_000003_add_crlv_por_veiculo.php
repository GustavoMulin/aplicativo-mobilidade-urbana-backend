<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->string('chassi', 17)->nullable()->after('renavam');
            $table->unsignedSmallInteger('exercicio')->nullable();
            $table->date('data_emissao')->nullable();
            $table->string('nome_proprietario')->nullable();
            $table->string('cpf_cnpj_proprietario', 14)->nullable();
            $table->string('marca_modelo')->nullable();
            $table->string('categoria_crlv', 60)->nullable();
            $table->text('observacao')->nullable();
        });
        Schema::table('motorista_documentos_anexos', function (Blueprint $table) {
            $table->foreignId('veiculo_id')->nullable()->after('motorista_id')->constrained('veiculos')->restrictOnDelete();
            // Preserva a leitura de cada envio; os campos do cadastro ficam em veiculos.
            $table->json('crlv')->nullable();
            $table->index(['motorista_id', 'tipo_documento', 'veiculo_id'], 'documentos_motorista_tipo_veiculo');
        });
        $unicos = DB::table('motorista_veiculos')->select('motorista_id')->selectRaw('MIN(veiculo_id) as veiculo_id')->groupBy('motorista_id')->havingRaw('COUNT(DISTINCT veiculo_id) = 1')->get();
        foreach ($unicos as $vinculo) {
            DB::table('motorista_documentos_anexos')->where('motorista_id', $vinculo->motorista_id)->where('tipo_documento', 'crlv')->whereNull('veiculo_id')->update(['veiculo_id' => $vinculo->veiculo_id]);
        }
        // Cadastros já aprovados também precisam de um CRLV para cada veículo.
        DB::table('motoristas')->where('status', 'aprovado')->orderBy('id')->chunkById(100, function ($motoristas) {
            foreach ($motoristas as $motorista) {
                $veiculos = DB::table('motorista_veiculos')->where('motorista_id', $motorista->id)->distinct()->pluck('veiculo_id');
                $documentos = DB::table('motorista_documentos_anexos')->where('motorista_id', $motorista->id)->where('tipo_documento', 'crlv')->whereIn('veiculo_id', $veiculos)->orderByDesc('id')->get()->unique('veiculo_id');
                $reprovado = $documentos->contains(fn ($documento) => $documento->status === 'reprovado');
                $faltando = $veiculos->isEmpty() || $veiculos->contains(fn ($id) => ! $documentos->contains(fn ($documento) => $documento->veiculo_id === $id && $documento->status === 'aprovado'));
                if ($reprovado || $faltando) {
                    DB::table('motoristas')->where('id', $motorista->id)->update(['status' => $reprovado ? 'reprovado' : 'em_analise']);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('motorista_documentos_anexos', function (Blueprint $table) {
            $table->dropIndex('documentos_motorista_tipo_veiculo');
            $table->dropConstrainedForeignId('veiculo_id');
            $table->dropColumn('crlv');
        });
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropColumn(['chassi', 'exercicio', 'data_emissao', 'nome_proprietario', 'cpf_cnpj_proprietario', 'marca_modelo', 'categoria_crlv', 'observacao']);
        });
    }
};
