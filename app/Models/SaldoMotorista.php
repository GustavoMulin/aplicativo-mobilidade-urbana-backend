<?php

namespace App\Models;

use App\Enums\CategoriaSaldoMotorista;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaldoMotorista extends Model
{
    protected $table = 'saldo_motoristas';

    // Este módulo expõe somente consulta; não há preenchimento em massa de lançamentos.
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::saving(function (self $registro) {
            $registro->categoria = $registro->categoriaEfetiva();
        });
    }

    public function categoriaEfetiva(): ?string
    {
        return CategoriaSaldoMotorista::normalizar($this->categoria ?? ($this->detalhes['categoria'] ?? null));
    }

    public function scopeFiltrarCategoria(Builder $query, string $categoria): void
    {
        $valores = CategoriaSaldoMotorista::filtro($categoria);
        $query->where(fn ($q) => $q->whereIn('categoria', $valores)
            ->orWhere(fn ($legado) => $legado->whereNull('categoria')->whereIn('detalhes->categoria', $valores)));
    }

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
