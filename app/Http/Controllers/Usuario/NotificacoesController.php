<?php

namespace App\Http\Controllers\Usuario;

use App\Http\Controllers\Controller;
use App\Models\Notificacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacoesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consulta = Notificacao::where('user_id', $request->user()->id);

        return response()->json([
            'data' => (clone $consulta)->latest('id')->limit(50)->get(['id', 'titulo', 'mensagem', 'lida_em', 'created_at']),
            'nao_lidas' => (clone $consulta)->whereNull('lida_em')->count(),
        ]);
    }

    public function marcarLidas(Request $request): JsonResponse
    {
        Notificacao::where('user_id', $request->user()->id)
            ->whereNull('lida_em')
            ->update(['lida_em' => now()]);

        return response()->json(['ok' => true]);
    }
}
