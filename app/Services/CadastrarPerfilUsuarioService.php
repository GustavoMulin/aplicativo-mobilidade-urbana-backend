<?php

namespace App\Services;

use App\Models\Motorista;
use App\Models\Passageiro;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CadastrarPerfilUsuarioService
{
    public function executar(Request $request, string $tipo): Model
    {
        $classe = match ($tipo) {
            'motorista' => Motorista::class,
            'passageiro' => Passageiro::class,
        };
        $tabela = (new $classe)->getTable();
        $regrasPessoais = [
            'user_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique($tabela, 'user_id')],
            'name' => 'required_without:user_id|string|min:3|max:255',
            'cpf' => 'required_without:user_id|digits:11',
            'data_nascimento' => 'required_without:user_id|date_format:Y-m-d|before_or_equal:'.now()->subYears(18)->toDateString(),
        ];
        $pessoais = $request->validate($regrasPessoais, [
            'user_id.unique' => 'Este usuário já possui um cadastro de '.$tipo.'.',
            'data_nascimento.before_or_equal' => 'É necessário ter pelo menos 18 anos.',
        ]);
        $existente = isset($pessoais['user_id'])
            ? User::findOrFail($pessoais['user_id'])
            : User::withTrashed()->where('cpf', $pessoais['cpf'])->first();
        if ($existente?->trashed()) {
            throw ValidationException::withMessages(['cpf' => 'Este CPF pertence a um usuário arquivado. Restaure o usuário antes de cadastrar o perfil.']);
        }
        $emailUnico = Rule::unique('users', 'email');
        $telefoneUnico = Rule::unique('users', 'telefone');
        if ($existente !== null) {
            $emailUnico->ignore($existente);
            $telefoneUnico->ignore($existente);
        }
        $regras = [
            ...$regrasPessoais,
            'email' => ['required', 'email', 'max:255', $emailUnico],
            'telefone' => ['nullable', 'digits_between:10,11', $telefoneUnico],
            'password' => 'required|string|min:8|max:255|confirmed',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ];
        if ($tipo === 'motorista') {
            $regras = [...$regras,
                'numero_registro' => 'nullable|string|max:20', 'cnh_categoria' => 'nullable|string',
                'cnh_expiracao' => 'nullable|date', 'ear' => 'nullable|boolean', 'observacao' => 'nullable|string|max:5000',
            ];
        }
        $dados = $request->validate($regras, [
            'user_id.unique' => 'Este usuário já possui um cadastro de '.$tipo.'.',
            'email.unique' => 'Este e-mail já está cadastrado para outro usuário.',
            'telefone.unique' => 'Este telefone já está cadastrado para outro usuário.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'data_nascimento.before_or_equal' => 'É necessário ter pelo menos 18 anos.',
        ]);
        $caminho = null;
        try {
            return DB::transaction(function () use ($request, $dados, $tipo, $classe, &$caminho): Model {
                if (isset($dados['user_id'])) {
                    $user = User::lockForUpdate()->findOrFail($dados['user_id']);
                } else {
                    $anterior = User::withTrashed()->where('cpf', $dados['cpf'])->lockForUpdate()->first();
                    if ($anterior?->trashed()) {
                        throw ValidationException::withMessages(['cpf' => 'Este CPF pertence a um usuário arquivado. Restaure o usuário antes de cadastrar o perfil.']);
                    }
                    $user = $anterior ?? User::create([
                        'cpf' => $dados['cpf'],
                        'name' => trim($dados['name']), 'data_nascimento' => $dados['data_nascimento'],
                        'email' => $dados['email'], 'telefone' => $dados['telefone'] ?? null,
                        'password' => $dados['password'], 'status' => 'ativo',
                    ]);
                    if (Str::lower(trim($user->name)) !== Str::lower(trim($dados['name'])) || $user->getRawOriginal('data_nascimento') !== $dados['data_nascimento']) {
                        throw ValidationException::withMessages(['cpf' => 'Este CPF já está cadastrado com dados pessoais diferentes. Confira o nome e a data de nascimento.']);
                    }
                }
                if ($classe::withTrashed()->where('user_id', $user->id)->exists()) {
                    throw ValidationException::withMessages(['cpf' => 'Este usuário já possui um cadastro de '.$tipo.'.']);
                }
                // Adicionar um perfil mantém as credenciais compartilhadas da pessoa.
                $credenciais = [];
                foreach (['email', 'telefone', 'password'] as $campo) {
                    if ($user->{$campo} === null) {
                        $credenciais[$campo] = $dados[$campo] ?? null;
                    } elseif ($campo !== 'password' && ! empty($dados[$campo]) && Str::lower((string) $user->{$campo}) !== Str::lower((string) $dados[$campo])) {
                        throw ValidationException::withMessages([$campo => 'Este CPF já possui um usuário. Use o '.$campo.' cadastrado para adicionar outro perfil.']);
                    }
                }
                if ($credenciais !== []) {
                    $user->update($credenciais);
                }
                $perfil = [
                    'user_id' => $user->id, 'status' => $tipo === 'motorista' ? 'pendente' : 'ativo',
                ];
                foreach (['numero_registro', 'cnh_categoria', 'cnh_expiracao', 'ear', 'observacao'] as $campo) {
                    if (array_key_exists($campo, $dados)) {
                        $perfil[$campo] = $dados[$campo];
                    }
                }
                if ($request->hasFile('image')) {
                    $caminho = $request->file('image')->store($tipo, 'perfis_fotos');
                    $host = app()->environment('local') ? $request->getSchemeAndHttpHost() : rtrim((string) config('app.url'), '/');
                    $perfil['foto'] = $host.'/images/perfis/'.$caminho;
                }

                return $classe::create($perfil)->load('user');
            });
        } catch (\Throwable $erro) {
            if ($caminho !== null) {
                Storage::disk('perfis_fotos')->delete($caminho);
            }
            throw $erro;
        }
    }
}
