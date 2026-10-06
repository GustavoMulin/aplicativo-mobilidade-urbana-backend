<?php

namespace App\Models;

use App\Enums\TipoDocumentoMotorista;
use Illuminate\Database\Eloquent\Model;

class MotoristaDocumento extends Model
{
    protected $fillable = [
        'motorista_id',
        'tipo_documento',
        'name',
        'type',
        'mime_type',
        'size',
        'path',
        'status',
        'observacao',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tipo_documento' => TipoDocumentoMotorista::class];
    }
}
