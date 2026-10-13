<?php

namespace App\Http\Controllers\Publicidade;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCidadeRequest;
use App\Http\Requests\UpdateCidadeRequest;
use App\Models\Cidade;
use App\Models\Tarifa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CidadeController extends Controller
{
    /**
     * Lista as cidades em ordem alfabética. Filtros: uf=RO e busca=porto.
     *
     * @return Collection<int, Cidade>
     */
    public function index(Request $request): Collection
    {
        $filtros = $request->validate([
            'uf' => 'sometimes|string|size:2',
            'busca' => 'sometimes|string|max:120',
        ]);

        return Cidade::query()
            ->when(isset($filtros['uf']), fn (Builder $consulta) => $consulta->where('uf', mb_strtoupper($filtros['uf'])))
            ->when(isset($filtros['busca']), fn (Builder $consulta) => $consulta->where('nome', 'like', '%'.$filtros['busca'].'%'))
            ->withCount('banners')
            ->orderBy('nome')
            ->get();
    }

    public function store(StoreCidadeRequest $request): JsonResponse
    {
        $cidade = Cidade::create($request->validated());

        return response()->json([
            'message' => 'Cidade cadastrada.',
            'data' => $cidade,
        ], 201);
    }

    public function show(Cidade $cidade): Cidade
    {
        return $cidade->loadCount('banners');
    }

    public function update(UpdateCidadeRequest $request, Cidade $cidade): JsonResponse
    {
        $cidade->update($request->validated());

        return response()->json([
            'message' => 'Cidade atualizada.',
            'data' => $cidade,
        ]);
    }

    public function destroy(Cidade $cidade): JsonResponse
    {
        // os banners apagados continuam no banco (dá para restaurar) e
        // apontam para a cidade
        if ($cidade->banners()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'Esta cidade tem banners. Mova ou apague os banners dela antes.',
            ], 409);
        }

        if (Tarifa::where('cidade_id', $cidade->id)->exists()) {
            return response()->json([
                'message' => 'Esta cidade tem tarifas próprias. Apague ou mova as tarifas dela antes.',
            ], 409);
        }

        // corridas da cidade ficam sem cidade (a chave é nullOnDelete)
        $cidade->delete();

        return response()->json(['message' => 'Cidade excluída.']);
    }
}
