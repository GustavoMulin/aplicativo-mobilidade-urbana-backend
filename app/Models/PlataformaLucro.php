<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlataformaLucro extends Model
{
    protected $table = 'plataforma_lucros';

    protected $guarded = ['*'];

    protected $attributes = ['versao' => 1, 'demonstracao' => false];

    protected function casts(): array
    {
        $casts = array_fill_keys(['valor_bruto_passageiro', 'repasse_motorista', 'margem_bruta_retida', 'comissao_prevista', 'custo_gateway', 'subsidio_aplicado', 'bonus_alocado', 'compensacao_paga', 'lucro_liquido'], 'decimal:2');

        return [...$casts, 'pendencias_detalhes' => 'array', 'custos_confirmados' => 'array', 'custos_incluidos_repasse' => 'array', 'snapshot_origem' => 'array', 'ocorrido_em' => 'immutable_datetime', 'recebido_em' => 'immutable_datetime', 'composicao_conferida' => 'boolean', 'demonstracao' => 'boolean'];
    }

    public function corrida(): BelongsTo
    {
        return $this->belongsTo(Corrida::class);
    }
}
