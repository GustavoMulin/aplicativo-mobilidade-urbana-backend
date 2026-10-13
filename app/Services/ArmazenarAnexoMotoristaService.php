<?php

namespace App\Services;

use App\Models\Motorista;
use App\Models\MotoristaDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ArmazenarAnexoMotoristaService
{
    public const DIRETORIO = 'motorista_documentos_anexos';

    public function salvar(UploadedFile $arquivo, Request $request, ?string $nomeBase = null): array
    {
        $dados = [
            'name' => $arquivo->getClientOriginalName(),
            'type' => $arquivo->extension(),
            'mime_type' => $arquivo->getMimeType(),
            'size' => $arquivo->getSize(),
        ];
        $nome = ($nomeBase ?? time().'_'.Str::uuid()).'.'.$dados['type'];
        $salvo = $arquivo->storeAs('', $nome, self::DIRETORIO);
        abort_if($salvo === false, 500, 'Não foi possível armazenar o arquivo.');

        $path = self::DIRETORIO.'/'.$salvo;
        $host = App::environment('local')
            ? $request->getSchemeAndHttpHost()
            : rtrim((string) config('app.url'), '/');

        return [...$dados, 'path' => $path, 'url' => $host.'/'.$path];
    }

    public function regrasVerso(Request $request, string $formatos = 'jpg,jpeg,png', int $limite = 2048): array
    {
        $arquivo = $request->file('arquivo');
        $fotoCnh = $request->input('tipo_documento') === 'cnh'
            && $arquivo instanceof UploadedFile
            && $arquivo->isValid()
            && str_starts_with($arquivo->getMimeType() ?? '', 'image/');

        return [
            'bail',
            Rule::requiredIf($fotoCnh),
            Rule::prohibitedIf(! $fotoCnh),
            'file',
            'mimes:'.$formatos,
            'max:'.$limite,
        ];
    }

    public function salvarEnvio(Request $request): array
    {
        $anexo = $this->salvar($request->file('arquivo'), $request);
        try {
            $anexo['verso'] = $request->hasFile('arquivo_verso')
                ? $this->salvar($request->file('arquivo_verso'), $request, pathinfo($anexo['path'], PATHINFO_FILENAME).'_verso')
                : null;
        } catch (Throwable $exception) {
            $this->excluir($anexo['path']);
            throw $exception;
        }

        return $anexo;
    }

    public function registrarEnvio(array $dados, array $anexo): MotoristaDocumento
    {
        $verso = $anexo['verso'] ?? null;
        unset($anexo['verso']);
        if ($dados['tipo_documento'] === 'cnh') {
            $motorista = Motorista::findOrFail($dados['motorista_id']);
            $dados['informacoes_complementares'] = array_replace(array_intersect_key($motorista->attributesToArray(),
                array_flip(['nome', 'cpf', 'data_nascimento', 'numero_registro', 'cnh_categoria',
                    'primeira_habilitacao', 'data_emissao', 'cnh_expiracao', 'ear', 'observacao'])),
                $dados['informacoes_complementares'] ?? []);
            if (isset($dados['informacoes_complementares']['ear'])) {
                $dados['informacoes_complementares']['ear'] = (bool) $dados['informacoes_complementares']['ear'];
            }
        }
        $principal = MotoristaDocumento::create([...$dados, ...$anexo, 'ordem' => 0]);
        $registroVerso = $verso === null ? null : MotoristaDocumento::create([
            ...$dados, ...$verso, 'ordem' => 1,
        ]);
        $principal->setRelation('anexoVerso', $registroVerso);

        return $principal;
    }

    /** Copia um verso antigo para o nome do envio sem alterar o conteúdo. */
    public function padronizarVerso(array $frente, array $verso): array
    {
        $origem = $verso['path'];
        $destino = dirname($frente['path']).'/'.pathinfo($frente['path'], PATHINFO_FILENAME)
            .'_verso.'.pathinfo($origem, PATHINFO_EXTENSION);
        if ($origem === $destino) {
            return $verso;
        }
        foreach ([$origem, $destino] as $path) {
            if (! str_starts_with($path, self::DIRETORIO.'/') || str_contains($path, '..')) {
                throw new \RuntimeException('Caminho inválido na migração do verso.');
            }
        }
        $disk = Storage::disk(self::DIRETORIO);
        $arquivoOrigem = substr($origem, strlen(self::DIRETORIO) + 1);
        $arquivoDestino = substr($destino, strlen(self::DIRETORIO) + 1);
        if (! $disk->exists($arquivoOrigem)) {
            throw new \RuntimeException('Arquivo do verso não encontrado para migração.');
        }
        if (! $disk->exists($arquivoDestino) && ! $disk->copy($arquivoOrigem, $arquivoDestino)) {
            throw new \RuntimeException('Não foi possível copiar o verso.');
        }
        if (hash('sha256', $disk->get($arquivoOrigem)) !== hash('sha256', $disk->get($arquivoDestino))) {
            throw new \RuntimeException('O conteúdo do verso não foi preservado.');
        }
        $verso['path'] = $destino;
        if (! empty($verso['url'])) {
            $verso['url'] = Str::beforeLast($verso['url'], '/').'/'.basename($destino);
        }

        return $verso;
    }

    public function excluirEnvio(array $anexo): void
    {
        $this->excluir($anexo['path'] ?? null);
        $this->excluir($anexo['verso']['path'] ?? null);
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
