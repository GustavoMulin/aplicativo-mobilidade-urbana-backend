<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property string $tipo
 * @property string $status
 * @property int $criador_passageiro_id
 * @property int|null $produto_id
 * @property int|null $tarifa_id
 * @property int|null $cidade_id
 * @property int $capacidade
 * @property list<array{latitude: float, longitude: float}> $espinha
 * @property array<string, mixed>|null $rota
 * @property Carbon $expira_em
 * @property Carbon|null $fecha_em
 * @property Carbon|null $fechada_em
 */
class SalaCompartilhada extends Model
{
    public const ABERTA = 'aberta';

    // todos prontos: contagem curta em que ainda dá para voltar atrás
    public const FECHANDO = 'fechando';

    public const FECHADA = 'fechada';

    // o tempo acabou com uma pessoa só: ela pode pedir sozinha com um toque
    public const EXPIRADA = 'expirada';

    public const CANCELADA = 'cancelada';

    public const DISSOLVIDA = 'dissolvida';

    public const EM_FORMACAO = [self::ABERTA, self::FECHANDO];

    protected $table = 'salas_compartilhadas';

    protected $fillable = [
        'codigo',
        'tipo',
        'status',
        'criador_passageiro_id',
        'produto_id',
        'tarifa_id',
        'cidade_id',
        'capacidade',
        'espinha',
        'rota',
        'expira_em',
        'fecha_em',
        'fechada_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'espinha' => 'array',
            'rota' => 'array',
            'capacidade' => 'integer',
            'expira_em' => 'datetime',
            'fecha_em' => 'datetime',
            'fechada_em' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SalaParticipante, $this>
     */
    public function participantes(): HasMany
    {
        return $this->hasMany(SalaParticipante::class, 'sala_id');
    }

    /**
     * Quem está na sala agora (ou foi para o grupo quando ela fechou).
     *
     * @return HasMany<SalaParticipante, $this>
     */
    public function presentes(): HasMany
    {
        return $this->participantes()->whereIn('status', [SalaParticipante::NA_SALA, SalaParticipante::NO_GRUPO]);
    }

    /**
     * @return HasMany<Corrida, $this>
     */
    public function corridas(): HasMany
    {
        return $this->hasMany(Corrida::class, 'sala_compartilhada_id');
    }

    /**
     * @return BelongsTo<Passageiro, $this>
     */
    public function criador(): BelongsTo
    {
        return $this->belongsTo(Passageiro::class, 'criador_passageiro_id');
    }
}
