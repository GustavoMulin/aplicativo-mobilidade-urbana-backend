<?php

namespace App\Http\Middleware;

use App\Models\Gestor;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidarGestor
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = auth('gestores')->payload();
        if (! $request->user() instanceof Gestor || $payload->get('perfil') !== 'gestao' || $payload->get('prv') !== sha1(Gestor::class)) {
            throw new AuthenticationException('Não autenticado.', ['gestores']);
        }

        return $next($request);
    }
}
