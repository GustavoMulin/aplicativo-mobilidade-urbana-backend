<?php

namespace App\Http\Requests\Compartilhada;

use Illuminate\Contracts\Validation\ValidationRule;

class AbrirSalaCompartilhadaRequest extends EntrarSalaCompartilhadaRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tipo' => ['required', 'in:publica,convite'],
        ];
    }
}
