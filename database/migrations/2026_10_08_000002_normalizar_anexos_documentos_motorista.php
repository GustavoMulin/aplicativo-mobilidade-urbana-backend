<?php

use App\Models\MotoristaDocumento;
use App\Services\ArmazenarAnexoMotoristaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motorista_documentos_anexos', function (Blueprint $table) {
            $table->unsignedTinyInteger('ordem')->default(0)->after('tipo_documento');
            $table->json('informacoes_complementares')->nullable()->after('ordem');
        });

        $origensVersos = [];
        $ultimoId = DB::table('motorista_documentos_anexos')->max('id') ?? 0;
        $ultimasCnhs = DB::table('motorista_documentos_anexos')->where('tipo_documento', 'cnh')
            ->selectRaw('motorista_id, MAX(id) as id')->groupBy('motorista_id')->pluck('id', 'motorista_id');
        $camposCnh = ['nome', 'cpf', 'data_nascimento', 'numero_registro', 'cnh_categoria',
            'primeira_habilitacao', 'data_emissao', 'cnh_expiracao', 'ear', 'observacao'];

        DB::table('motorista_documentos_anexos')->where('id', '<=', $ultimoId)->orderBy('id')
            ->chunkById(100, function ($documentos) use ($ultimasCnhs, $camposCnh, &$origensVersos) {
                DB::transaction(function () use ($documentos, $ultimasCnhs, $camposCnh, &$origensVersos) {
                    foreach ($documentos as $documento) {
                        $informacoes = $documento->tipo_documento === 'crlv' ? $documento->crlv : null;
                        if ($documento->tipo_documento === 'cnh'
                            && (int) ($ultimasCnhs[$documento->motorista_id] ?? 0) === (int) $documento->id) {
                            $motorista = DB::table('motoristas')->where('id', $documento->motorista_id)->first($camposCnh);
                            if ($motorista !== null) {
                                $informacoes = json_encode((array) $motorista, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                            }
                        }
                        DB::table('motorista_documentos_anexos')->where('id', $documento->id)
                            ->update(['informacoes_complementares' => $informacoes]);

                        if ($documento->tipo_documento !== 'cnh' || $documento->verso === null) {
                            continue;
                        }
                        $verso = json_decode($documento->verso, true, flags: JSON_THROW_ON_ERROR);
                        if (! is_array($verso) || empty($verso['path'])) {
                            throw new RuntimeException('Anexo do verso sem path no documento '.$documento->id);
                        }
                        $origensVersos[] = $verso['path'];
                        $verso = app(ArmazenarAnexoMotoristaService::class)->padronizarVerso((array) $documento, $verso);
                        $registro = (array) $documento;
                        unset($registro['id'], $registro['verso'], $registro['crlv']);
                        foreach (['name', 'type', 'mime_type', 'size', 'path', 'url'] as $campo) {
                            $registro[$campo] = $verso[$campo] ?? ($campo === 'url' ? null : $registro[$campo]);
                        }
                        $registro['ordem'] = 1;
                        $registro['informacoes_complementares'] = $informacoes;
                        DB::table('motorista_documentos_anexos')->insert($registro);
                    }
                });
            });

        Schema::table('motorista_documentos_anexos', function (Blueprint $table) {
            $table->dropColumn(['verso', 'crlv']);
        });
        $this->ordenarTimestamps();
        foreach ($origensVersos as $path) {
            if (! DB::table('motorista_documentos_anexos')->where('path', $path)->exists()) {
                app(ArmazenarAnexoMotoristaService::class)->excluir($path);
            }
        }
    }

    public function down(): void
    {
        Schema::table('motorista_documentos_anexos', function (Blueprint $table) {
            $table->json('verso')->nullable()->after('url');
            $table->json('crlv')->nullable()->after('verso');
        });
        DB::table('motorista_documentos_anexos')->where('ordem', 0)->orderBy('id')
            ->chunkById(100, function ($documentos) {
                foreach ($documentos as $documento) {
                    $verso = DB::table('motorista_documentos_anexos')
                        ->where('motorista_id', $documento->motorista_id)->where('tipo_documento', 'cnh')
                        ->whereIn('path', MotoristaDocumento::caminhosDoEnvio($documento->path))->where('ordem', 1)->first();
                    $dados = ['crlv' => $documento->tipo_documento === 'crlv' ? $documento->informacoes_complementares : null];
                    if ($verso !== null) {
                        $dados['verso'] = json_encode(array_intersect_key((array) $verso,
                            array_flip(['name', 'type', 'mime_type', 'size', 'path', 'url'])), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    }
                    DB::table('motorista_documentos_anexos')->where('id', $documento->id)->update($dados);
                }
            });
        DB::table('motorista_documentos_anexos')->where('ordem', 1)->delete();
        Schema::table('motorista_documentos_anexos', function (Blueprint $table) {
            $table->dropColumn(['ordem', 'informacoes_complementares']);
        });
        $this->ordenarTimestamps('crlv');
    }

    private function ordenarTimestamps(string $apos = 'descricao_reprovacao'): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('motorista_documentos_anexos', function (Blueprint $table) use ($apos) {
                $table->timestamp('created_at')->nullable()->after($apos)->change();
                $table->timestamp('updated_at')->nullable()->after('created_at')->change();
                $table->timestamp('deleted_at')->nullable()->after('updated_at')->change();
            });
        }
    }
};
