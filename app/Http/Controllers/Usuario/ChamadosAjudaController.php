<?php

namespace App\Http\Controllers\Usuario;

use App\Http\Controllers\Controller;
use App\Models\ChamadoAjuda;
use App\Services\TicketsAjudaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChamadosAjudaController extends Controller
{
    public const MOTIVOS = ['corrida_com_problema', 'tarifa_incorreta', 'objeto_esquecido', 'motorista_ou_passageiro', 'outro'];

    private function legado(ChamadoAjuda $ticket): array
    {
        // O app existente continua recebendo os nomes antigos; novas rotas usam StatusTicket.
        return $ticket->only(['id', 'corrida_id', 'motivo', 'descricao', 'created_at']) + ['status' => match ($ticket->status) {
            'open' => 'aberto', 'in_progress' => 'em_atendimento', 'waiting' => 'pendente', 'pending' => 'pendente', 'resolved' => 'resolvido', 'closed' => 'fechado', default => $ticket->status,
        }];
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => ChamadoAjuda::where('user_id', $request->user()->id)->latest('id')->limit(50)->get()->map(fn ($ticket) => $this->legado($ticket))]);
    }

    public function store(Request $request, TicketsAjudaService $service): JsonResponse
    {
        $dados = $request->validate([
            'motivo' => 'required|string|in:'.implode(',', self::MOTIVOS),
            'descricao' => 'required|string|min:10|max:1000', 'corrida_id' => 'nullable|integer|min:1',
            'solicitante_tipo' => 'nullable|in:passageiro,motorista',
        ]);

        return response()->json(['data' => $this->legado($service->criar($request->user(), $dados))], 201);
    }
}
