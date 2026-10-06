<?php

namespace App\Services;

use App\Enums\TipoDocumentoMotorista;
use App\Models\Motorista;
use App\Models\MotoristaDocumento;

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
        $documentos = MotoristaDocumento::where('motorista_id', $motorista->id)
            ->whereIn('tipo_documento', TipoDocumentoMotorista::valores())
            ->orderByDesc('id')
            ->get(['tipo_documento', 'status'])
            ->unique('tipo_documento');

        $aprovados = $documentos
            ->where('status', 'aprovado')
            ->pluck('tipo_documento')
            ->map(fn (TipoDocumentoMotorista $tipo): string => $tipo->value)
            ->all();

        return array_values(array_diff(TipoDocumentoMotorista::valores(), $aprovados));
    }

    private function calcular(Motorista $motorista): string
    {
        $documentos = MotoristaDocumento::where('motorista_id', $motorista->id)
            ->whereIn('tipo_documento', TipoDocumentoMotorista::valores())
            ->orderByDesc('id')
            ->get(['tipo_documento', 'status'])
            ->unique('tipo_documento');

        if ($documentos->isEmpty()) {
            return 'pendente';
        }

        // um documento reprovado reprova o cadastro: o motorista precisa
        // reenviar aquele item
        if ($documentos->contains(fn ($documento) => $documento->status === 'reprovado')) {
            return 'reprovado';
        }

        $aprovados = $documentos
            ->where('status', 'aprovado')
            ->pluck('tipo_documento')
            ->map(fn (TipoDocumentoMotorista $tipo): string => $tipo->value)
            ->unique();

        $faltam = array_diff(TipoDocumentoMotorista::valores(), $aprovados->all());

        return $faltam === [] ? 'aprovado' : 'em_analise';
    }
}
