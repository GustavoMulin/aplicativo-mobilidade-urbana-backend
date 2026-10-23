<?php

namespace App\Http\Requests\Compartilhada;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EntrarSalaCompartilhadaRequest extends FormRequest
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
            'cotacao_id' => ['required', 'uuid'],
            // cada passageiro escolhe o próprio meio de pagamento
            'metodo_pagamento' => ['required', 'in:dinheiro,cartao,pix'],
        ];
    }
}
