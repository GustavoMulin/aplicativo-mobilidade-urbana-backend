<?php

namespace App\Services;

use App\Models\ChamadoMensagem;
use App\Models\ChamadoMensagemAnexo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketAnexosService
{
    public const DISK = 'ticket_anexos';

    /** @param array<UploadedFile> $imagens */
    public function preparar(array $imagens): array
    {
        return array_map(function (UploadedFile $arquivo) {
            $dimensoes = @getimagesize($arquivo->getRealPath());
            if (! $dimensoes || $dimensoes[0] * $dimensoes[1] > 40000000) {
                throw ValidationException::withMessages(['imagens' => 'A imagem é inválida ou excede 40 megapixels.']);
            }

            return [
                'arquivo' => $arquivo, 'sha256' => hash_file('sha256', $arquivo->getRealPath()),
                'nome' => Str::limit(basename(str_replace('\\', '/', $arquivo->getClientOriginalName())), 255, ''),
                'mime_type' => $arquivo->getMimeType(), 'tamanho' => $arquivo->getSize(),
                'largura' => $dimensoes[0], 'altura' => $dimensoes[1],
            ];
        }, $imagens);
    }

    public function salvar(ChamadoMensagem $mensagem, array $imagens, array &$gravados): void
    {
        foreach ($imagens as $ordem => $imagem) {
            $path = 'tickets/'.$mensagem->chamado_id.'/'.Str::uuid().'.'.$imagem['arquivo']->extension();
            $gravados[] = $path;
            if (Storage::disk(self::DISK)->putFileAs(dirname($path), $imagem['arquivo'], basename($path)) === false) {
                throw new \RuntimeException('Não foi possível armazenar a imagem.');
            }
            $mensagem->anexos()->create([
                ...array_diff_key($imagem, ['arquivo' => true]),
                'disk' => self::DISK, 'path' => $path, 'ordem' => $ordem,
            ]);
        }
        $mensagem->unsetRelation('anexos');
    }

    public function corresponde(ChamadoMensagem $mensagem, array $imagens): bool
    {
        return $mensagem->anexos->pluck('sha256')->all() === array_column($imagens, 'sha256');
    }

    /** Remove arquivos de tentativas revertidas, incluindo retries de transação. */
    public function limparNaoPersistidos(array $gravados): void
    {
        foreach ($gravados as $path) {
            // Uma falha de broadcast depois do commit não pode apagar um anexo salvo.
            if (! ChamadoMensagemAnexo::where('disk', self::DISK)->where('path', $path)->exists()) {
                Storage::disk(self::DISK)->delete($path);
            }
        }
    }
}
