<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $corrida_id
 * @property int $produto_id
 * @property int|null $tarifa_id
 * @property float $valor_passageiro
 * @property float $valor_motorista
 * @property array<string, mixed> $categoria
 * @property-read ProdutosCorrida|null $produto
 */
class CorridaOpcao extends Model
{
    protected $table = 'corrida_opcoes';

    protected $fillable = [
        'corrida_id',
        'produto_id',
        'tarifa_id',
        'valor_passageiro',
        'valor_motorista',
        'categoria',
    ];

    protected $hidden = ['categoria'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_passageiro' => 'float',
            'valor_motorista' => 'float',
            'categoria' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ProdutosCorrida, $this>
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(ProdutosCorrida::class, 'produto_id');
    }
}
