<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $corrida_id
 * @property string $charge_id
 * @property string $status
 * @property int $valor_centavos
 * @property string $br_code
 * @property string $br_code_base64
 * @property bool $dev_mode
 * @property Carbon|null $expira_em
 * @property Carbon|null $pago_em
 */
class CobrancaPix extends Model
{
    protected $table = 'cobrancas_pix';

    protected $fillable = [
        'corrida_id',
        'charge_id',
        'status',
        'valor_centavos',
        'br_code',
        'br_code_base64',
        'dev_mode',
        'expira_em',
        'pago_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dev_mode' => 'boolean',
            'expira_em' => 'datetime',
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
