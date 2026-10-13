<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\Gestor;
use App\Models\Motorista;
use App\Models\Passageiro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FotoPerfilController extends Controller
{
    public function atualizar(Request $request, int $perfil, string $tipo): JsonResponse
    {
        [$classe, $disco, $diretorio, $prefixo] = match ($tipo) {
            'motorista' => [Motorista::class, 'perfis_fotos', 'motorista', '/images/perfis/'],
            'passageiro' => [Passageiro::class, 'perfis_fotos', 'passageiro', '/images/perfis/'],
            'gestor' => [Gestor::class, 'gestores_fotos', '', '/images/gestores/'],
        };
        $classe::findOrFail($perfil);
        $request->validate([
            'image' => 'required_unless:remover_foto,1,true|prohibited_if:remover_foto,1,true|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remover_foto' => 'sometimes|boolean',
        ]);
        $caminho = null;
        $fotoAnterior = null;
        try {
            if ($request->hasFile('image')) {
                $caminho = $request->file('image')->store($diretorio, $disco);
                abort_unless(is_string($caminho), 500, 'Não foi possível guardar a imagem.');
            }
            $host = app()->environment('local') ? $request->getSchemeAndHttpHost() : rtrim((string) config('app.url'), '/');
            $registro = DB::transaction(function () use ($classe, $perfil, $caminho, $host, $prefixo, &$fotoAnterior) {
                $registro = $classe::lockForUpdate()->findOrFail($perfil);
                $fotoAnterior = $registro->foto;
                $registro->update(['foto' => $caminho === null ? null : $host.$prefixo.$caminho]);

                return $registro->fresh();
            });
        } catch (Throwable $erro) {
            if (is_string($caminho)) {
                Storage::disk($disco)->delete($caminho);
            }
            throw $erro;
        }
        $pathAnterior = $fotoAnterior === null ? null : parse_url($fotoAnterior, PHP_URL_PATH);
        $pasta = $diretorio === '' ? '' : $diretorio.'/';
        $padrao = '~^'.preg_quote($prefixo.$pasta, '~').'([a-zA-Z0-9_-]+\.(?:jpe?g|png|webp))$~';
        if (is_string($pathAnterior) && preg_match($padrao, $pathAnterior, $partes)) {
            Storage::disk($disco)->delete($pasta.$partes[1]);
        }

        return response()->json([
            'data' => $registro,
            'message' => $caminho === null ? 'Imagem removida com sucesso.' : 'Imagem atualizada com sucesso.',
        ]);
    }
}
