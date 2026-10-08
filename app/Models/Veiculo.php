<?php

namespace App\Models;

use Database\Factories\VeiculoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Veiculo extends Model
{
    /** @use HasFactory<VeiculoFactory> */
    use HasFactory;

    protected $fillable = [
        'marca',
        'modelo',
        'ano_fabricacao',
        'ano_modelo',
        'cor',
        'placa',
        'renavam',
        'numero_crv',
        'codigo_seguranca',
        'chassi',
        'exercicio',
        'data_emissao',
        'nome_proprietario',
        'cpf_cnpj_proprietario',
        'marca_modelo',
        'categoria_crlv',
        'observacao',
        'categoria',
        'status',
        'uf',
    ];

    public function ultimoCrlv(): HasOne
    {
        return $this->hasOne(MotoristaDocumento::class)->ofMany(['id' => 'max'], function (Builder $query) {
            $query->where('tipo_documento', 'crlv')->where('ordem', 0);
        });
    }

    public function scopeVisiveis(Builder $query): Builder
    {
        return $query->where('status', '!=', 'reprovado')
            ->where(function (Builder $query) {
                $query->whereDoesntHave('ultimoCrlv')
                    ->orWhereHas('ultimoCrlv', fn (Builder $crlv) => $crlv->whereIn('status', ['em_analise', 'aprovado']));
            });
    }

    public function scopeLiberados(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['em_analise', 'reprovado'])
            ->where(function (Builder $query) {
                $query->whereDoesntHave('ultimoCrlv')
                    ->orWhereHas('ultimoCrlv', fn (Builder $crlv) => $crlv->where('status', 'aprovado'));
            });
    }
}
