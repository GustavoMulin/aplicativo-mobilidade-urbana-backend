<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sala_id
 * @property int $passageiro_id
 * @property bool $criador
 * @property string $status
 * @property string $embarque_endereco
 * @property float $embarque_latitude
 * @property float $embarque_longitude
 * @property string $desembarque_endereco
 * @property float $desembarque_latitude
 * @property float $desembarque_longitude
 * @property string $cotacao_id
 * @property float $preco_solo
 * @property int $solo_segundos
 * @property float $solo_km
 * @property float $preco_atual
 * @property float|null $teto
 * @property int $desvio_segundos
 * @property string $metodo_pagamento
 * @property Carbon|null $pronto_em
 * @property Carbon $entrou_em
 * @property Carbon|null $saiu_em
 * @property int|null $ordem_embarque
 * @property int|null $ordem_desembarque
 * @property int|null $corrida_id
 */
class SalaParticipante extends Model
{
    public const NA_SALA = 'na_sala';

    public const SAIU = 'saiu';

    public const NO_GRUPO = 'no_grupo';

    protected $table = 'sala_participantes';

    protected $fillable = [
        'sala_id',
        'passageiro_id',
        'criador',
        'status',
        'embarque_endereco',
        'embarque_latitude',
        'embarque_longitude',
        'desembarque_endereco',
        'desembarque_latitude',
        'desembarque_longitude',
        'cotacao_id',
        'preco_solo',
        'solo_segundos',
        'solo_km',
        'preco_atual',
        'teto',
        'desvio_segundos',
        'metodo_pagamento',
        'pronto_em',
        'entrou_em',
        'saiu_em',
        'ordem_embarque',
        'ordem_desembarque',
        'corrida_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criador' => 'boolean',
            'embarque_latitude' => 'float',
            'embarque_longitude' => 'float',
            'desembarque_latitude' => 'float',
            'desembarque_longitude' => 'float',
            'preco_solo' => 'float',
            'solo_segundos' => 'integer',
            'solo_km' => 'float',
            'preco_atual' => 'float',
            'teto' => 'float',
            'desvio_segundos' => 'integer',
            'pronto_em' => 'datetime',
            'entrou_em' => 'datetime',
            'saiu_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SalaCompartilhada, $this>
     */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(SalaCompartilhada::class, 'sala_id');
    }

    /**
     * @return BelongsTo<Passageiro, $this>
     */
    public function passageiro(): BelongsTo
    {
        return $this->belongsTo(Passageiro::class);
    }

    /**
     * @return BelongsTo<Corrida, $this>
     */
    public function corrida(): BelongsTo
    {
        return $this->belongsTo(Corrida::class);
    }
}
