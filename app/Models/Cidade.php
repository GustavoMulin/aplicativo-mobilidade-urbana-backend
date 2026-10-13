<?php

namespace App\Models;

use Database\Factories\CidadeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cidade do app: tarifa por cidade, corrida e publicidade.
 *
 * @property int $id
 * @property string $nome
 * @property string $uf
 * @property int $ibge
 */
class Cidade extends Model
{
    /** @use HasFactory<CidadeFactory> */
    use HasFactory;

    public const UFS = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA',
        'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    protected $fillable = [
        'nome',
        'uf',
        'ibge',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ibge' => 'integer',
        ];
    }

    /**
     * @return HasMany<BannerPublicidade, $this>
     */
    public function banners(): HasMany
    {
        return $this->hasMany(BannerPublicidade::class);
    }
}
