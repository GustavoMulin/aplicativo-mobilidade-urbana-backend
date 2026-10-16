<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaldoMotorista extends Model
{
    protected $table = 'saldo_motoristas';

    // Este módulo expõe somente consulta; não há preenchimento em massa de lançamentos.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'saldo_anterior' => 'decimal:2',
            'saldo_posterior' => 'decimal:2',
            'detalhes' => 'array',
        ];
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class)->withTrashed();
    }

    public function corrida(): BelongsTo
    {
        return $this->belongsTo(Corrida::class);
    }
}
