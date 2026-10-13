<?php

namespace App\Http\Requests;

use App\Models\Cidade;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCidadeRequest extends FormRequest
{
    /**
     * Qualquer conta logada, como o resto do painel de gestão por enquanto
     * (não existe papel de gestão; ver pendências de produção).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('uf'))) {
            $this->merge(['uf' => mb_strtoupper(trim($this->input('uf')))]);
        }

        if (is_string($this->input('nome'))) {
            $this->merge(['nome' => trim($this->input('nome'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:120',
            'uf' => ['required', 'string', Rule::in(Cidade::UFS)],
            'ibge' => 'required|integer|digits:7|unique:cidades,ibge',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'uf.in' => 'Informe a sigla do estado, como RO ou SP.',
            'ibge.digits' => 'O código IBGE do município tem 7 dígitos.',
            'ibge.unique' => 'Já existe uma cidade com esse código IBGE.',
        ];
    }
}
