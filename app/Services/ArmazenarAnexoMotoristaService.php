<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArmazenarAnexoMotoristaService
{
    public const DIRETORIO = 'motorista_documentos_anexos';

    public function salvar(UploadedFile $arquivo, Request $request): array
    {
        $dados = [
            'name' => $arquivo->getClientOriginalName(),
            'type' => $arquivo->extension(),
            'mime_type' => $arquivo->getMimeType(),
            'size' => $arquivo->getSize(),
        ];
        $nome = time().'_'.Str::uuid().'.'.$dados['type'];
        $salvo = $arquivo->storeAs('', $nome, self::DIRETORIO);
        abort_if($salvo === false, 500, 'Não foi possível armazenar o arquivo.');

        $path = self::DIRETORIO.'/'.$salvo;
        $host = App::environment('local')
            ? $request->getSchemeAndHttpHost()
            : rtrim((string) config('app.url'), '/');

        return [...$dados, 'path' => $path, 'url' => $host.'/'.$path];
    }

    public function excluir(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (str_starts_with($path, self::DIRETORIO.'/')) {
            $arquivo = substr($path, strlen(self::DIRETORIO) + 1);
            Storage::disk(self::DIRETORIO)->delete($arquivo);

            return;
        }

        Storage::disk('local')->delete($path);
    }
}
