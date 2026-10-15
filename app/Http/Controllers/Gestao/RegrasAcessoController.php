<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\PerfilAcessoGestor;
use App\Http\Controllers\Controller;
use App\Models\Gestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegrasAcessoController extends Controller
{
    public function index(): JsonResponse
    {
        $totais = Gestor::query()->selectRaw('perfil_acesso, COUNT(*) AS total')->groupBy('perfil_acesso')->pluck('total', 'perfil_acesso');
        $rotulos = PerfilAcessoGestor::catalogoPermissoes();

        return response()->json(['data' => array_map(fn ($perfil) => [
            'codigo' => $perfil->value, 'nome' => $perfil->nome(), 'descricao' => $perfil->descricao(),
            'total_gestores' => (int) ($totais[$perfil->value] ?? 0),
            'permissoes' => array_map(fn ($codigo) => ['codigo' => $codigo, 'nome' => $rotulos[$codigo]], $perfil->permissoes()),
        ], PerfilAcessoGestor::cases())]);
    }

    public function gestores(Request $request, string $perfil): JsonResponse
    {
        abort_unless(PerfilAcessoGestor::tryFrom($perfil) !== null, 404);
        $dados = $request->validate(['search' => 'nullable|string|max:255', 'adicionar' => 'sometimes|boolean', 'rowsPerPage' => 'sometimes|integer|between:1,50', 'page' => 'sometimes|integer|min:1']);
        $query = Gestor::query()->where('perfil_acesso', $request->boolean('adicionar') ? '!=' : '=', $perfil);
        if ($busca = trim($dados['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$busca.'%')->orWhere('email', 'like', '%'.$busca.'%')->orWhere('cpf', 'like', '%'.preg_replace('/[.\-\s]/', '', $busca).'%'));
        }

        return response()->json($query->orderBy('name')->orderBy('id')->paginate($dados['rowsPerPage'] ?? 8));
    }

    public function atribuir(Request $request, string $perfil): JsonResponse
    {
        abort_unless(PerfilAcessoGestor::tryFrom($perfil) !== null, 404);
        $dados = $request->validate([
            'gestores' => 'required|array|min:1|max:50',
            'gestores.*' => ['required', 'integer', 'distinct', Rule::exists('gestores', 'id')->whereNull('deleted_at')],
        ]);
        DB::transaction(function () use ($request, $dados, $perfil): void {
            // Ordem única de bloqueio: dois admins não podem remover simultaneamente os últimos acessos.
            $ativos = Gestor::query()->orderBy('id')->lockForUpdate()->get();
            $ator = $ativos->firstWhere('id', $request->user()->id);
            abort_unless($ator?->pode('regras.gerenciar'), 403, 'Seu perfil não tem permissão para esta ação.');
            $selecionados = $ativos->whereIn('id', $dados['gestores']);
            if ($selecionados->count() !== count($dados['gestores'])) {
                throw ValidationException::withMessages(['gestores' => 'Um dos gestores foi arquivado. Atualize a lista.']);
            }
            if ($perfil !== 'admin' && $ativos->where('perfil_acesso', 'admin')->whereNotIn('id', $dados['gestores'])->isEmpty()) {
                throw ValidationException::withMessages(['gestores' => 'Mantenha pelo menos um administrador ativo.']);
            }
            foreach ($selecionados as $gestor) {
                $anterior = $gestor->perfil_acesso;
                if ($anterior === $perfil) {
                    continue;
                }
                $gestor->forceFill(['perfil_acesso' => $perfil])->save();
                DB::table('gestao_auditorias')->insert([
                    'gestor_id' => $ator->id, 'acao' => 'regras.atribuir',
                    'dados' => json_encode(['gestor_alvo_id' => $gestor->id, 'perfil_anterior' => $anterior, 'perfil_novo' => $perfil], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }, 3);

        return response()->json(['message' => 'Perfil de acesso atualizado com sucesso.']);
    }
}
