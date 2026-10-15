<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetalhesCorridaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['produto:id,nome,codigo', 'veiculo:id,marca,modelo,placa,cor', 'corrida_financeiro', 'corrida_destinos']);
        $inicio = $this->tempo_inicio;
        $fim = $this->tempo_final;

        return [
            'produto' => $this->produto?->only(['nome', 'codigo']),
            'veiculo' => $this->veiculo?->only(['marca', 'modelo', 'placa', 'cor']),
            'convidado_nome' => $this->convidado_nome,
            'distancia_km' => $this->distancia_total === null ? null : (float) $this->distancia_total,
            'duracao_segundos' => $inicio && $fim && $fim->gte($inicio) ? (int) $inicio->diffInSeconds($fim) : null,
            'solicitada_em' => $this->tempo_solicitacao?->toISOString(),
            'iniciada_em' => $inicio?->toISOString(), 'finalizada_em' => $fim?->toISOString(),
            'metodo_pagamento' => $this->metodo_pagamento ?? $this->corrida_financeiro?->metodo_pagamento,
            'status_pagamento' => $this->status_pagamento,
            'motivo_cancelamento' => $this->motivo_cancelamento,
            'financeiro' => $this->corrida_financeiro?->only([
                'valor_bruto', 'valor_dinamico_aplicado', 'taxa_espera', 'taxa_cancelamento',
                'valor_descontos', 'valor_ajuste_negociado', 'valor_pago_passageiro',
                'taxa_plataforma_valor', 'taxa_plataforma_percentual',
                'valor_motorista', 'valor_liquido_motorista', 'subsidiado_plataforma', 'credito_aplicado',
            ]),
            'destinos' => $this->corrida_destinos->sortBy('ordem')->values()->map(fn ($destino) => [
                'id' => $destino->id, 'tipo' => $destino->tipo, 'ordem' => $destino->ordem,
                'nome_local' => $destino->nome_local, 'endereco' => $destino->endereco,
                'latitude' => $destino->latitude === null ? null : (float) $destino->latitude,
                'longitude' => $destino->longitude === null ? null : (float) $destino->longitude,
                'concluida_em' => $destino->concluida_em?->toISOString(),
            ])->all(),
        ];
    }
}
