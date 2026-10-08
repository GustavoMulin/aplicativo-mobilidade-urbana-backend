<?php

namespace App\Services;

use App\Enums\TipoDocumentoMotorista;
use App\Models\Motorista;
use App\Models\MotoristaDocumento;
use App\Models\MotoristaVeiculo;
use Illuminate\Support\Collection;

class DocumentosMotoristaService
{
    public function veiculos(Motorista $motorista): Collection
    {
        return MotoristaVeiculo::where('motorista_id', $motorista->id)->with('veiculo')->orderBy('id')->get()->pluck('veiculo')->filter()->unique('id')->values();
    }

    public function ultimos(Motorista $motorista): Collection
    {
        $veiculos = $this->veiculos($motorista)->pluck('id');

        return MotoristaDocumento::where('motorista_id', $motorista->id)->where('ordem', 0)->with('veiculo')->orderByDesc('id')->get()
            ->filter(fn ($d) => $d->tipo_documento !== TipoDocumentoMotorista::CRLV || $veiculos->contains($d->veiculo_id))
            ->unique(fn ($d) => $d->tipo_documento->value.':'.($d->veiculo_id ?? 'motorista'))->values();
    }

    public function resumo(Motorista $motorista): array
    {
        $ultimos = $this->ultimos($motorista);
        $veiculos = $this->veiculos($motorista);
        $rows = [];
        foreach (TipoDocumentoMotorista::catalogo() as $tipo) {
            $base = [...$tipo, 'id' => null, 'status' => null, 'url' => null, 'verso' => null, 'veiculo_id' => null, 'ordem' => 0, 'informacoes_complementares' => null];
            if ($tipo['tipo_documento'] !== 'crlv') {
                $doc = $ultimos->first(fn ($d) => $d->tipo_documento->value === $tipo['tipo_documento']);
                $rows[] = [...$base, ...($doc?->toArray() ?? []), 'chave' => $tipo['tipo_documento']];

                continue;
            }
            foreach ($veiculos as $veiculo) {
                $doc = $ultimos->first(fn ($d) => $d->tipo_documento === TipoDocumentoMotorista::CRLV && $d->veiculo_id === $veiculo->id);
                $rows[] = [...$base, ...($doc?->toArray() ?? []), 'chave' => 'crlv:'.$veiculo->id, 'veiculo_id' => $veiculo->id, 'veiculo' => $veiculo->toArray()];
            }
            $legado = MotoristaDocumento::where('motorista_id', $motorista->id)->where('tipo_documento', 'crlv')->where('ordem', 0)->whereNull('veiculo_id')->orderByDesc('id')->first();
            if ($legado) {
                $rows[] = [...$base, ...$legado->toArray(), 'chave' => 'crlv:legado', 'legado' => true];
            }
            if ($veiculos->isEmpty() && ! $legado) {
                $rows[] = [...$base, 'chave' => 'crlv:sem_veiculo', 'sem_veiculo' => true];
            }
        }

        return $rows;
    }
}
