<?php

namespace App\Http\Controllers\Motorista;

use App\Enums\TipoDocumentoMotorista;
use App\Http\Controllers\Controller;
use App\Models\Motorista;
use App\Models\MotoristaDocumento;
use App\Models\MotoristaVeiculo;
use App\Services\ArmazenarAnexoMotoristaService;
use App\Services\AtualizarSituacaoMotoristaService;
use App\Services\CrlvVeiculoService;
use App\Services\DocumentosMotoristaService;
use App\Services\InformacoesDocumentoMotoristaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class MotoristaCadastroController extends Controller
{
    public const APROVADO = 'aprovado';

    public function __construct(
        protected AtualizarSituacaoMotoristaService $atualizarSituacaoMotoristaService,
        protected ArmazenarAnexoMotoristaService $armazenarAnexoMotoristaService
    ) {}

    public const SEM_CADASTRO = 'sem_cadastro';

    /**
     * Onde o motorista está na esteira de liberação. Responde também para
     * quem ainda não é motorista — é por aqui que um passageiro descobre
     * que precisa enviar documentos para virar motorista.
     */
    public function mostrar(Request $request): JsonResponse
    {
        $motorista = Motorista::where('user_id', $request->user()->id)->first();

        if ($motorista === null) {
            return response()->json([
                'situacao' => self::SEM_CADASTRO,
                'cnh' => null,
                'documentos' => [],
                'veiculos' => 0,
                'pendencias' => ['cnh', 'documentos', 'veiculo'],
                'documentos_faltando' => TipoDocumentoMotorista::valores(),
            ]);
        }

        $documentos = app(DocumentosMotoristaService::class)->ultimos($motorista);

        $veiculos = MotoristaVeiculo::where('motorista_id', $motorista->id)->count();

        return response()->json([
            'situacao' => $motorista->status,
            'cnh' => $motorista->numero_registro === null ? null : [
                'numero' => $motorista->numero_registro,
                'categoria' => $motorista->cnh_categoria,
                'expiracao' => $motorista->cnh_expiracao,
                'ear' => (bool) $motorista->ear,
                'observacao' => $motorista->observacao,
            ],
            'documentos' => $documentos,
            'veiculos' => $veiculos,
            'pendencias' => $this->pendencias($motorista, $veiculos),
            'documentos_faltando' => $this->atualizarSituacaoMotoristaService
                ->documentosQueFaltam($motorista),
        ]);
    }

    /**
     * Envia ou corrige os dados da CNH. Cria o registro de motorista quando
     * quem chama é um passageiro que está virando motorista.
     */
    public function salvarCnh(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'numero_registro' => 'required|string|max:20',
            'cnh_categoria' => 'required|string|in:A,B,AB,C,D,E,a,b,ab,c,d,e',
            'cnh_expiracao' => 'required|date|after:today',
            'ear' => 'required|boolean',
            'observacao' => 'nullable|string|max:5000',
        ], [
            'numero_registro.required' => 'Informe o número de registro da sua CNH.',
            'cnh_categoria.in' => 'Categoria de CNH inválida.',
            'cnh_expiracao.after' => 'Sua CNH está vencida.',
        ]);

        $motorista = Motorista::firstOrNew(['user_id' => $request->user()->id]);

        if ($motorista->exists && $motorista->status === self::APROVADO) {
            return response()->json([
                'message' => 'Seu cadastro já foi aprovado. Fale com o suporte para alterar a CNH.',
            ], 409);
        }

        $motorista->fill([
            'numero_registro' => preg_replace('/\D/', '', (string) $dados['numero_registro']),
            'cnh_categoria' => strtoupper((string) $dados['cnh_categoria']),
            'cnh_expiracao' => $dados['cnh_expiracao'],
            'ear' => (bool) $dados['ear'],
            ...array_intersect_key($dados, ['observacao' => true]),
            'status' => 'em_analise',
        ])->save();

        return response()->json([
            'situacao' => $motorista->status,
            'message' => 'CNH enviada para análise.',
        ], $motorista->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Envia um documento do próprio motorista.
     *
     * O `motorista-documentos` do painel de gestão recebe `motorista_id` pelo
     * corpo da requisição — o que serve ao painel, que age sobre terceiros,
     * mas deixaria qualquer usuário autenticado enviar documento em nome de
     * outro motorista. Aqui o dono sai sempre do token.
     */
    public function enviarDocumento(Request $request): JsonResponse
    {
        $informacoesService = app(InformacoesDocumentoMotoristaService::class);
        $informacoesService->normalizar($request);
        $crlvService = app(CrlvVeiculoService::class);
        $crlvService->normalizar($request);
        $dados = $request->validate([
            ...$informacoesService->regras($request),
            'tipo_documento' => ['required', Rule::enum(TipoDocumentoMotorista::class)],
            'arquivo' => 'required|file|mimes:jpg,jpeg,png,webp,heic,heif,gif,pdf|max:10240',
            'arquivo_verso' => $this->armazenarAnexoMotoristaService->regrasVerso($request, 'jpg,jpeg,png,webp,heic,heif,gif', 10240),
        ]);

        $motorista = Motorista::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['status' => 'pendente']
        );

        if ($motorista->status === self::APROVADO) {
            return response()->json([
                'message' => 'Seu cadastro já foi aprovado. Fale com o suporte para trocar documentos.',
            ], 409);
        }

        if ($dados['tipo_documento'] === 'crlv' && isset($dados['veiculo_id'])) {
            $veiculo = $crlvService->veiculoVinculado($motorista->id, (int) $dados['veiculo_id']);
            $crlvService->validar($veiculo, $dados['informacoes_complementares']);
        }
        $anexo = $this->armazenarAnexoMotoristaService->salvarEnvio($request);
        try {
            [$documento, $situacao] = DB::transaction(function () use ($motorista, $dados, $anexo): array {
                $motorista = Motorista::lockForUpdate()->findOrFail($motorista->id);
                if ($dados['tipo_documento'] === 'cnh' && isset($dados['informacoes_complementares'])) {
                    $motorista->update($dados['informacoes_complementares']);
                }
                $veiculo = $dados['tipo_documento'] === 'crlv'
                    ? app(CrlvVeiculoService::class)->registrarOuAtualizar($motorista, $dados['informacoes_complementares'], $dados['veiculo_id'] ?? null)
                    : null;

                $documento = $this->armazenarAnexoMotoristaService->registrarEnvio([
                    'motorista_id' => $motorista->id,
                    'tipo_documento' => $dados['tipo_documento'],
                    'veiculo_id' => $veiculo?->id,
                    'informacoes_complementares' => $dados['informacoes_complementares'] ?? null,
                    'status' => 'em_analise',
                ], $anexo);
                if ($veiculo !== null) {
                    app(CrlvVeiculoService::class)->sincronizarStatus($veiculo);
                }
                $situacao = $this->atualizarSituacaoMotoristaService->executar($motorista);

                return [$documento, $situacao];
            });
        } catch (Throwable $exception) {
            $this->armazenarAnexoMotoristaService->excluirEnvio($anexo);
            throw $exception;
        }

        return response()->json([
            'message' => 'Documento enviado para análise.',
            'documento' => $documento->load(['veiculo']),
            'situacao' => $situacao,
        ], 201);
    }

    /**
     * Remove um documento do próprio motorista, enquanto o cadastro não foi
     * aprovado.
     */
    public function removerDocumento(Request $request, int $documento): JsonResponse
    {
        $motorista = Motorista::where('user_id', $request->user()->id)->first();

        if ($motorista === null) {
            return response()->json(['message' => 'Você ainda não tem cadastro de motorista.'], 403);
        }

        $registro = MotoristaDocumento::where('id', $documento)
            ->where('motorista_id', $motorista->id)
            ->first();

        if ($registro === null) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        $registro = $registro->principalDoEnvio();
        $this->armazenarAnexoMotoristaService->excluirEnvio($registro->toArray());

        $situacao = DB::transaction(function () use ($motorista, $registro): string {
            $motorista = Motorista::lockForUpdate()->findOrFail($motorista->id);
            $registro = MotoristaDocumento::lockForUpdate()->findOrFail($registro->id);
            $veiculo = $registro->tipo_documento === TipoDocumentoMotorista::CRLV ? $registro->veiculo : null;
            $registro->anexosDoEnvio()->delete();
            if ($veiculo !== null) {
                app(CrlvVeiculoService::class)->sincronizarStatus($veiculo);
            }

            return $this->atualizarSituacaoMotoristaService->executar($motorista);
        });

        return response()->json([
            'message' => 'Documento removido.',
            'situacao' => $situacao,
        ]);
    }

    /**
     * Atalho de desenvolvimento: pula CNH e documentos e aprova o motorista
     * direto, sem passar pela análise. Mesmo espírito do código de
     * verificação fake em LoginController::enviarCodigo — remover antes de
     * publicar.
     */
    public function aprovarDev(Request $request): JsonResponse
    {
        abort_unless(App::environment(['local', 'testing']), 404);

        $motorista = Motorista::firstOrNew(['user_id' => $request->user()->id]);

        $motorista->numero_registro ??= '00000000000';
        $motorista->cnh_categoria ??= 'B';
        $motorista->cnh_expiracao ??= now()->addYears(5)->toDateString();
        $motorista->ear ??= false;
        $motorista->save();

        foreach (TipoDocumentoMotorista::valores() as $tipo) {
            MotoristaDocumento::create([
                'motorista_id' => $motorista->id,
                'tipo_documento' => $tipo,
                'name' => "dev-$tipo.pdf",
                'type' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => 0,
                'path' => "motorista_documentos_anexos/dev-$tipo.pdf",
                'url' => rtrim((string) config('app.url'), '/')."/motorista_documentos_anexos/dev-$tipo.pdf",
                'status' => 'aprovado',
            ]);
        }

        // O atalho local continua dispensando a análise, inclusive sem frota.
        $motorista->update(['status' => self::APROVADO]);
        $situacao = self::APROVADO;

        return response()->json([
            'message' => 'Cadastro aprovado (atalho de desenvolvimento).',
            'situacao' => $situacao,
        ]);
    }

    /**
     * @return list<string>
     */
    private function pendencias(Motorista $motorista, int $veiculos): array
    {
        $pendencias = [];

        if ($motorista->numero_registro === null) {
            $pendencias[] = 'cnh';
        }

        if ($this->atualizarSituacaoMotoristaService->documentosQueFaltam($motorista) !== []) {
            $pendencias[] = 'documentos';
        }

        if ($veiculos === 0) {
            $pendencias[] = 'veiculo';
        }

        return $pendencias;
    }
}
