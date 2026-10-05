<?php

namespace App\Http\Controllers\Usuario;

use App\Http\Controllers\Controller;
use App\Models\ChamadoAjuda;
use App\Models\Corrida;
use App\Models\Passageiro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChamadosAjudaController extends Controller
{
    public const MOTIVOS = [
        'corrida_com_problema',
        'tarifa_incorreta',
        'objeto_esquecido',
        'motorista_ou_passageiro',
        'outro',
    ];

    public function index(Request $request): JsonResponse
    {
        $chamados = ChamadoAjuda::where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(50)
            ->get(['id', 'corrida_id', 'motivo', 'descricao', 'status', 'created_at']);

        return response()->json(['data' => $chamados]);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'motivo' => 'required|string|in:'.implode(',', self::MOTIVOS),
            'descricao' => 'required|string|min:10|max:1000',
            'corrida_id' => 'nullable|integer',
        ]);

        // a corrida informada precisa ser do próprio usuário (passageiro)
        if (isset($dados['corrida_id'])) {
            $passageiroId = Passageiro::where('user_id', $request->user()->id)->value('id');
            $dona = $passageiroId !== null && Corrida::whereKey($dados['corrida_id'])
                ->where('passageiro_id', $passageiroId)
                ->exists();

            if (! $dona) {
                return response()->json(['message' => 'Corrida não encontrada.'], 404);
            }
        }

        $chamado = ChamadoAjuda::create([
            'user_id' => $request->user()->id,
            'corrida_id' => $dados['corrida_id'] ?? null,
            'motivo' => $dados['motivo'],
            'descricao' => $dados['descricao'],
        ]);

        return response()->json(['data' => $chamado->refresh()], 201);
    }
}
