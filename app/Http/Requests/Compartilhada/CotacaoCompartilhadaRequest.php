<?php

namespace App\Http\Requests\Compartilhada;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CotacaoCompartilhadaRequest extends FormRequest
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
        ];
    }
}
