<?php

namespace App\Http\Controllers\Usuario;

use App\Http\Controllers\Controller;
use App\Models\Notificacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacoesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consulta = $this->doUsuario($request);

        return response()->json([
            'data' => (clone $consulta)->latest('id')->limit(50)->get(['id', 'titulo', 'mensagem', 'lida_em', 'created_at']),
            'nao_lidas' => (clone $consulta)->whereNull('lida_em')->count(),
        ]);
    }

    public function marcarLidas(Request $request): JsonResponse
    {
        $this->doUsuario($request)
            ->whereNull('lida_em')
            ->update(['lida_em' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * ?perfil=passageiro (ou motorista): só as daquele app. As antigas, sem
     * perfil, aparecem nos dois.
     *
     * @return Builder<Notificacao>
     */
    private function doUsuario(Request $request): Builder
    {
        $perfil = $request->validate([
            'perfil' => 'sometimes|string|in:passageiro,motorista',
        ])['perfil'] ?? null;

        return Notificacao::where('user_id', $request->user()->id)
            ->when($perfil !== null, fn (Builder $consulta) => $consulta->where(
                fn (Builder $doPerfil) => $doPerfil->where('perfil', $perfil)->orWhereNull('perfil')
            ));
    }
}
