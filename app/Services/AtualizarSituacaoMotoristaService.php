<?php

namespace App\Services;

use App\Enums\TipoDocumentoMotorista;
use App\Models\Motorista;

class AtualizarSituacaoMotoristaService
{
    public function executar(Motorista $motorista): string
    {
        $situacao = $this->calcular($motorista);

        if ($motorista->status !== $situacao) {
            $motorista->update(['status' => $situacao]);
        }

        return $situacao;
    }

    /**
     * @return list<string>
     */
    public function documentosQueFaltam(Motorista $motorista): array
    {
        $service = app(DocumentosMotoristaService::class);
        $documentos = $service->ultimos($motorista);
        $faltam = [];
        foreach (TipoDocumentoMotorista::cases() as $tipo) {
            if ($tipo === TipoDocumentoMotorista::CRLV) {
                $veiculos = $service->veiculos($motorista);
                if ($veiculos->isEmpty() || $veiculos->contains(fn ($v) => ! $documentos->contains(fn ($d) => $d->tipo_documento === $tipo && $d->veiculo_id === $v->id && $d->status === 'aprovado'))) {
                    $faltam[] = $tipo->value;
                }
            } elseif (! $documentos->contains(fn ($d) => $d->tipo_documento === $tipo && $d->status === 'aprovado')) {
                $faltam[] = $tipo->value;
            }
        }

        return $faltam;
    }

    private function calcular(Motorista $motorista): string
    {
        $documentos = app(DocumentosMotoristaService::class)->ultimos($motorista);
        if ($documentos->isEmpty()) {
            return 'pendente';
        }
        if ($documentos->contains(fn ($d) => $d->status === 'reprovado')) {
            return 'reprovado';
        }

        return $this->documentosQueFaltam($motorista) === [] ? 'aprovado' : 'em_analise';
    }
}
