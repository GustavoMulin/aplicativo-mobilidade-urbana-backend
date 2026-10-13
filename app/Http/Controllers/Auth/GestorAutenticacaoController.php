<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Gestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Cookie;

class GestorAutenticacaoController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|max:1024',
        ]);

        /** @var JWTGuard $guard */
        $guard = auth('gestores');
        $token = $guard->attempt($dados);
        if (! is_string($token)) {
            return response()->json(['message' => 'E-mail ou senha inválidos.'], 401);
        }

        return response()->json([
            'user' => $guard->user(),
            'message' => 'Login realizado com sucesso.',
            'token' => $token,
        ])->withCookie($this->tokenCookie($token, $guard->getTTL()));
    }

    public function usuarioLogado(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function refresh(Request $request): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('gestores');
        try {
            $jwt = app('tymon.jwt')->setRequest($request);
            $token = $jwt->getToken();
            if ($token === null) {
                throw new JWTException('Token ausente.');
            }

            // Valida também tokens expirados dentro da janela de renovação.
            $manager = clone $jwt->manager();
            $payload = $manager->setRefreshFlow(true)->decode($token);
            if ($payload->get('perfil') !== 'gestao' || $payload->get('prv') !== sha1(Gestor::class) || Gestor::find($payload->get('sub')) === null) {
                throw new JWTException('Gestor não autenticado.');
            }

            $novoToken = $guard->setToken($token)->refresh();
        } catch (JWTException $exception) {
            return response()->json(['message' => 'Sessão expirada. Faça login novamente.'], 401);
        }

        return response()->json(['token' => $novoToken])
            ->withCookie($this->tokenCookie($novoToken, $guard->getTTL()));
    }

    public function logout(): JsonResponse
    {
        auth('gestores')->logout();

        return response()->json(['message' => 'Logout realizado com sucesso.'])
            ->withoutCookie('gestao_token');
    }

    private function tokenCookie(string $token, int $ttlMinutes): Cookie
    {
        return cookie(
            name: 'gestao_token',
            value: $token,
            minutes: $ttlMinutes,
            path: '/',
            secure: app()->environment('production'),
            httpOnly: true,
            sameSite: 'Lax',
        );
    }
}
