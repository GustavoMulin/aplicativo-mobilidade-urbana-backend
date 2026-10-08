<?php

namespace App\Models;

use App\Enums\MotivoReprovacaoDocumento;
use App\Enums\TipoDocumentoMotorista;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MotoristaDocumento extends Model
{
    protected $table = 'motorista_documentos_anexos';

    protected $fillable = [
        'motorista_id',
        'veiculo_id',
        'crlv',
        'tipo_documento',
        'name',
        'type',
        'mime_type',
        'size',
        'path',
        'status',
        'url',
        'verso',
        'motivo_reprovacao',
        'descricao_reprovacao',
    ];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    protected $appends = ['motivo_reprovacao_texto'];

    protected function motivoReprovacaoTexto(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->status !== 'reprovado' || $this->motivo_reprovacao === null) {
                return null;
            }

            return $this->motivo_reprovacao === MotivoReprovacaoDocumento::OUTRO
                ? $this->descricao_reprovacao
                : $this->motivo_reprovacao->titulo();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tipo_documento' => TipoDocumentoMotorista::class, 'verso' => 'array', 'crlv' => 'array', 'veiculo_id' => 'integer', 'motivo_reprovacao' => MotivoReprovacaoDocumento::class];
    }
}
