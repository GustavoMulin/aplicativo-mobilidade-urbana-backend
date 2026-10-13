<?php

namespace App\Http\Controllers\Gestor;

use App\Http\Controllers\Controller;
use App\Models\Gestor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class GestorController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        return $this->listar($request, Gestor::query());
    }

    public function arquivados(Request $request): LengthAwarePaginator
    {
        return $this->listar($request, Gestor::onlyTrashed());
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $this->validar($request);
        unset($dados['image'], $dados['remover_foto']);
        $foto = $this->guardarFoto($request);
        try {
            $gestor = Gestor::create([...$dados, 'foto' => $foto]);
        } catch (\Throwable $erro) {
            $this->excluirFoto($foto);
            throw $erro;
        }

        return response()->json(['gestor' => $gestor, 'message' => 'Gestor cadastrado com sucesso.'], 201);
    }

    public function show(int $gestor): Gestor
    {
        return Gestor::withTrashed()->findOrFail($gestor);
    }

    public function update(Request $request, int $gestor): JsonResponse
    {
        $registro = Gestor::findOrFail($gestor);
        $dados = $this->validar($request, $registro);
        unset($dados['image'], $dados['remover_foto']);
        if (empty($dados['password'])) {
            unset($dados['password']);
        }
        if (isset($dados['email']) && $dados['email'] !== $registro->email) {
            $registro->email_verified_at = null;
        }

        $fotoAnterior = $registro->foto;
        $novaFoto = $this->guardarFoto($request);
        if ($novaFoto !== null || $request->boolean('remover_foto')) {
            $dados['foto'] = $novaFoto;
        }
        try {
            $registro->fill($dados)->save();
        } catch (\Throwable $erro) {
            $this->excluirFoto($novaFoto);
            throw $erro;
        }
        if ($registro->foto !== $fotoAnterior) {
            $this->excluirFoto($fotoAnterior);
        }

        return response()->json(['gestor' => $registro->fresh(), 'message' => 'Gestor atualizado com sucesso.']);
    }

    public function destroy(Request $request, int $gestor): JsonResponse
    {
        $registro = Gestor::findOrFail($gestor);
        if ($registro->id === $request->user()->id) {
            return response()->json(['message' => 'Você não pode arquivar o gestor que está utilizando o painel.'], 422);
        }
        $registro->delete();

        return response()->json(['message' => 'Gestor arquivado com sucesso.']);
    }

    public function restaurar(int $gestor): JsonResponse
    {
        $registro = Gestor::onlyTrashed()->findOrFail($gestor);
        $registro->restore();

        return response()->json(['gestor' => $registro->fresh(), 'message' => 'Gestor restaurado com sucesso.']);
    }

    private function listar(Request $request, Builder $query): LengthAwarePaginator
    {
        $dados = $request->validate([
            'search' => 'nullable|string|max:255',
            'rowsPerPage' => 'sometimes|integer|between:0,50',
            'page' => 'sometimes|integer|min:1',
        ]);
        $busca = trim($dados['search'] ?? '');
        if ($busca !== '') {
            $termo = '%'.$busca.'%';
            $numero = preg_match('/^[\d\s().+-]+$/', $busca) ? preg_replace('/\D/', '', $busca) : $busca;
            $query->where(function (Builder $consulta) use ($termo, $numero): void {
                $consulta->where('name', 'like', $termo)->orWhere('email', 'like', $termo)
                    ->orWhere('cpf', 'like', '%'.$numero.'%')->orWhere('telefone', 'like', '%'.$numero.'%');
            });
        }

        return $query->orderBy('id', 'desc')->paginate(($dados['rowsPerPage'] ?? 5) ?: 50);
    }

    private function validar(Request $request, ?Gestor $gestor = null): array
    {
        $obrigatorio = $gestor === null ? 'required' : 'sometimes|required';
        $presenca = explode('|', $obrigatorio);

        return $request->validate([
            'name' => $obrigatorio.'|string|min:3|max:255',
            'email' => [...$presenca, 'email', 'max:255', Rule::unique('gestores', 'email')->ignore($gestor?->id)],
            'cpf' => [...$presenca, 'digits:11', Rule::unique('gestores', 'cpf')->ignore($gestor?->id)],
            'telefone' => ['sometimes', 'nullable', 'digits_between:10,11', Rule::unique('gestores', 'telefone')->ignore($gestor?->id)],
            'data_nascimento' => $obrigatorio.'|date_format:Y-m-d|before_or_equal:'.now()->subYears(18)->toDateString(),
            'password' => ($gestor === null ? 'required' : 'sometimes|nullable').'|string|min:8|max:255|confirmed',
            'image' => 'sometimes|nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'remover_foto' => 'sometimes|boolean',
        ], [
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'data_nascimento.before_or_equal' => 'O gestor deve ter pelo menos 18 anos.',
            'email.unique' => 'Este e-mail já está cadastrado, inclusive entre os gestores arquivados.',
            'cpf.unique' => 'Este CPF já está cadastrado, inclusive entre os gestores arquivados.',
            'telefone.unique' => 'Este telefone já está cadastrado.',
        ]);
    }

    private function guardarFoto(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }
        $nome = $request->file('image')->store('', 'gestores_fotos');
        if (! is_string($nome)) {
            throw new \RuntimeException('Não foi possível guardar a foto do gestor.');
        }
        $host = app()->environment('local') ? $request->getSchemeAndHttpHost() : rtrim((string) config('app.url'), '/');

        return $host.'/images/gestores/'.$nome;
    }

    private function excluirFoto(?string $url): void
    {
        $caminho = $url === null ? null : parse_url($url, PHP_URL_PATH);
        if (is_string($caminho) && preg_match('~^/images/gestores/([a-zA-Z0-9_-]+\.(?:jpe?g|png|webp))$~', $caminho, $partes)) {
            Storage::disk('gestores_fotos')->delete($partes[1]);
        }
    }
}
