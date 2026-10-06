<?php

namespace App\Http\Controllers\Usuario;

use App\Http\Controllers\Controller;
use App\Models\LocalSalvo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocaisSalvosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locais = LocalSalvo::where('user_id', $request->user()->id)
            ->orderByRaw("CASE tipo WHEN 'casa' THEN 0 WHEN 'trabalho' THEN 1 ELSE 2 END")
            ->orderBy('nome')
            ->orderBy('id')
            ->get();

        return response()->json($locais);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', 'string', Rule::in(LocalSalvo::TIPOS)],
            'nome' => ['nullable', 'string', 'max:60'],
            'endereco' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $userId = $request->user()->id;

        if (in_array($dados['tipo'], LocalSalvo::TIPOS_UNICOS, true)) {
            $local = DB::transaction(fn () => LocalSalvo::updateOrCreate(
                ['user_id' => $userId, 'tipo' => $dados['tipo']],
                $dados,
            ));

            return response()->json($local, $local->wasRecentlyCreated ? 201 : 200);
        }

        $favoritos = LocalSalvo::where('user_id', $userId)->where('tipo', 'favorito')->count();

        if ($favoritos >= LocalSalvo::LIMITE_FAVORITOS) {
            throw ValidationException::withMessages([
                'tipo' => 'Você pode guardar até '.LocalSalvo::LIMITE_FAVORITOS.' favoritos.',
            ]);
        }

        $local = LocalSalvo::create([...$dados, 'user_id' => $userId]);

        return response()->json($local, 201);
    }

    public function destroy(Request $request, int $localSalvo): Response
    {
        LocalSalvo::where('user_id', $request->user()->id)
            ->findOrFail($localSalvo)
            ->delete();

        return response()->noContent();
    }
}
