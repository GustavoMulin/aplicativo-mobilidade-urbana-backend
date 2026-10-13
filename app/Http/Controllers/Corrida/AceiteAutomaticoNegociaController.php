<?php

namespace App\Http\Controllers\Corrida;

use App\Http\Controllers\Controller;
use App\Models\Passageiro;
use App\Services\NegociacaoCorridaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AceiteAutomaticoNegociaController extends Controller
{
    /**
     * Negocia: liga ou desliga a escolha automática do primeiro motorista que
     * aceitar a oferta.
     */
    public function __invoke(Request $request, int $corrida, NegociacaoCorridaService $negociacao): JsonResponse
    {
        $dados = $request->validate([
            'ativo' => 'required|boolean',
        ]);

        $passageiroId = Passageiro::where('user_id', $request->user()->id)->value('id');

        if ($passageiroId === null) {
            return response()->json(['message' => 'Corrida não encontrada.'], 404);
        }

        try {
            return response()->json($negociacao->definirAceiteAutomatico(
                (int) $passageiroId,
                $corrida,
                $request->boolean('ativo'),
            ));
        } catch (RuntimeException $excecao) {
            $status = in_array($excecao->getCode(), [404, 409, 422], true) ? $excecao->getCode() : 422;

            return response()->json(['message' => $excecao->getMessage()], $status);
        }
    }
}
