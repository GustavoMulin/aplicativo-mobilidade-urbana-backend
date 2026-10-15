<?php

namespace App\Http\Middleware;

use App\Models\Gestor;
use App\Services\PermissoesGestaoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AutorizarGestao
{
    public function handle(Request $request, Closure $next): Response
    {
        $permissao = app(PermissoesGestaoService::class)->permissaoDaRota($request);
        $gestor = $request->user();
        if (! $gestor instanceof Gestor || $permissao === null || ! $gestor->pode($permissao)) {
            return response()->json(['message' => 'Seu perfil não tem permissão para esta ação.'], 403);
        }
        $resposta = $next($request);
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && $resposta->isSuccessful() && ! $request->is('api/gestao/administracao/*')) {
            try {
                DB::table('gestao_auditorias')->insert([
                    'gestor_id' => $gestor->id, 'acao' => $permissao,
                    'dados' => json_encode(['metodo' => $request->method(), 'rota' => $request->path(), 'status' => $resposta->getStatusCode()], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Throwable $erro) {
                report($erro);
            }
        }

        return $resposta;
    }
}
