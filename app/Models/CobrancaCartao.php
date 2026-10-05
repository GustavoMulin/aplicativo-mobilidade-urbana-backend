<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $corrida_id
 * @property string $produto_id
 * @property string $checkout_id
 * @property string $url
 * @property string $status
 * @property int $valor_centavos
 * @property bool $dev_mode
 * @property Carbon|null $pago_em
 */
class CobrancaCartao extends Model
{
    protected $table = 'cobrancas_cartao';

    protected $fillable = [
        'corrida_id',
        'produto_id',
        'checkout_id',
        'url',
        'status',
        'valor_centavos',
        'dev_mode',
        'pago_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dev_mode' => 'boolean',
            'pago_em' => 'datetime',
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
