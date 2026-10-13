<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUsuarioRequest;
use App\Models\User;
use App\Services\PerfilUsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    public function __invoke(LoginUsuarioRequest $request, PerfilUsuarioService $perfis): JsonResponse
    {
        $dados = $request->validated();
        $perfil = $perfis->solicitado($request);
        $user = User::where('email', $dados['email'])->first();

        if ($user === null || ! Hash::check($dados['password'], $user->password)) {
            return response()->json([
                'message' => 'E-mail ou senha inválidos.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->status, ['bloqueado', 'inativo'], true)) {
            return response()->json([
                'message' => 'Esta conta está indisponível. Entre em contato com o suporte.',
            ], Response::HTTP_FORBIDDEN);
        }

        $registroPerfil = $perfis->garantir($user, $perfil);

        if ($perfis->indisponivel($registroPerfil)) {
            return response()->json([
                'message' => 'Este perfil está indisponível. Entre em contato com o suporte.',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var JWTGuard $guard */
        $guard = auth('jwt');
        $token = $guard->claims(['perfil' => $perfil])->login($user);

        return response()->json([
            'user' => $perfis->dados($user, $perfil, $registroPerfil),
            'message' => 'Login realizado com sucesso.',
            'token' => $token,
            'perfil_criado' => $registroPerfil->wasRecentlyCreated,
        ])->withCookie($this->tokenCookie($token, $guard->getTTL()));
    }

    public function enviarCodigo(Request $request): JsonResponse
    {
        $telefone = $this->telefoneNormalizado($request);
        $request->validate(['perfil' => 'sometimes|string|in:passageiro,motorista']);
        $codigo = random_int(1000, 9999);

        Cache::put("codigo_verificacao:$telefone", $codigo, now()->addMinutes(30));

        $resposta = ['message' => 'Código enviado.'];

        // Apoio aos botões de desenvolvimento sem expor o código em produção.
        if (app()->environment(['local', 'testing'])) {
            $resposta['codigo'] = $codigo;
        }

        return response()->json($resposta);
    }

    public function verificarCodigo(Request $request, PerfilUsuarioService $perfis): JsonResponse
    {
        $telefone = $this->telefoneNormalizado($request);
        $request->validate([
            'codigo' => 'required|digits:4',
            'perfil' => 'sometimes|string|in:passageiro,motorista',
        ]);
        $perfil = $perfis->solicitado($request);
        $codigo = preg_replace('/\D/', '', $request->string('codigo')->toString());
        $registro = Cache::get("codigo_verificacao:$telefone");

        if (! $registro || (string) $registro !== $codigo) {
            return response()->json([
                'message' => 'Código inválido ou expirado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::where('telefone', $telefone)->first();

        if ($user === null) {
            return response()->json([
                'message' => 'Conta não encontrada para este telefone.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->status, ['bloqueado', 'inativo'], true)) {
            Cache::forget("codigo_verificacao:$telefone");

            return response()->json([
                'message' => 'Esta conta está indisponível. Entre em contato com o suporte.',
            ], Response::HTTP_FORBIDDEN);
        }

        $registroPerfil = $perfis->garantir($user, $perfil);

        if ($perfis->indisponivel($registroPerfil)) {
            Cache::forget("codigo_verificacao:$telefone");

            return response()->json([
                'message' => 'Este perfil está indisponível. Entre em contato com o suporte.',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var JWTGuard $guard */
        $guard = auth('jwt');
        $token = $guard->claims(['perfil' => $perfil])->login($user);
        Cache::forget("codigo_verificacao:$telefone");

        return response()->json([
            'user' => $perfis->dados($user, $perfil, $registroPerfil),
            'message' => 'Login realizado com sucesso.',
            'token' => $token,
            'perfil_criado' => $registroPerfil->wasRecentlyCreated,
        ])->withCookie($this->tokenCookie($token, $guard->getTTL()));
    }

    public function verificaSeContaExiste(Request $request, PerfilUsuarioService $perfis): JsonResponse
    {
        $telefone = $this->telefoneNormalizado($request);
        $request->validate(['perfil' => 'sometimes|string|in:passageiro,motorista']);
        $perfil = $perfis->solicitado($request);
        $user = User::where('telefone', $telefone)->first();

        return response()->json([
            'contaExiste' => $user !== null,
            'perfilExiste' => $user !== null && $perfis->localizar($user, $perfil) !== null,
        ]);
    }

    private function tokenCookie(string $token, int $ttlMinutes): Cookie
    {
        return cookie(
            name: 'token',
            value: $token,
            minutes: $ttlMinutes,
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            raw: false,
            sameSite: 'Lax'
        );
    }

    private function telefoneNormalizado(Request $request): string
    {
        $request->validate(['telefone' => 'required|string|max:32']);
        $telefone = preg_replace('/\D/', '', $request->string('telefone')->toString());

        if ($telefone === null || strlen($telefone) < 10 || strlen($telefone) > 13) {
            throw ValidationException::withMessages(['telefone' => 'Informe um telefone válido.']);
        }

        return $telefone;
    }
}
