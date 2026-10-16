<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Resources\DetalhesCorridaResource;
use App\Models\Gestor;
use App\Models\SaldoMotorista;
use App\Services\ChatCorridaService;
use Illuminate\Http\Request;

class HistoricoCorridaSaldoController extends Controller
{
    public function __invoke(Request $request, int $movimentacao, ChatCorridaService $service)
    {
        $gestor = $request->user();
        abort_unless($gestor instanceof Gestor && $gestor->pode('financeiro.consultar'), 403);
        $registro = SaldoMotorista::findOrFail($movimentacao);
        $corrida = $registro->corrida;
        abort_unless($corrida, 404, 'Esta movimentação não possui uma corrida vinculada.');
        $dados = $request->validate([
            'before_id' => 'nullable|integer|min:1',
            'after_id' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        abort_if(isset($dados['before_id'], $dados['after_id']), 422, 'Use apenas um cursor.');
        $resultado = $service->listar($corrida, $dados);
        $resultado['corrida'] += (new DetalhesCorridaResource($corrida))->resolve($request);

        return response()->json($resultado);
    }
}
