<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $corrida_id
 * @property string $status
 * @property string $endereco
 * @property float $latitude
 * @property float $longitude
 * @property float $distancia_km
 * @property float $tempo_min
 * @property float $valor_passageiro
 * @property float $valor_motorista
 * @property float|null $valor_passageiro_anterior
 * @property float|null $valor_motorista_anterior
 * @property Carbon|null $respondida_em
 * @property Carbon|null $created_at
 */
class CorridaAlteracaoDestino extends Model
{
    protected $table = 'corrida_alteracoes_destino';

    protected $fillable = [
        'corrida_id',
        'status',
        'endereco',
        'latitude',
        'longitude',
        'distancia_km',
        'tempo_min',
        'valor_passageiro',
        'valor_motorista',
        'valor_passageiro_anterior',
        'valor_motorista_anterior',
        'respondida_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'distancia_km' => 'float',
            'tempo_min' => 'float',
            'valor_passageiro' => 'float',
            'valor_motorista' => 'float',
            'valor_passageiro_anterior' => 'float',
            'valor_motorista_anterior' => 'float',
            'respondida_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Corrida, $this>
     */
    public function corrida(): BelongsTo
    {
        return $this->belongsTo(Corrida::class);
    }
}
