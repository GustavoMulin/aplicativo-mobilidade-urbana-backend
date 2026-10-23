<?php

namespace App\Http\Requests\Motorista;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AtualizarPreferenciasMotoristaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'aceita_compartilhada' => ['sometimes', 'boolean'],
            // lugares para passageiros no veículo em uso
            'assentos' => ['sometimes', 'integer', 'min:1', 'max:6'],
        ];
    }
}
