<?php

namespace App\Http\Requests;

use App\Models\Cidade;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCidadeRequest extends FormRequest
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
        /** @var Cidade $cidade */
        $cidade = $this->route('cidade');

        return [
            'nome' => 'sometimes|required|string|max:120',
            'uf' => ['sometimes', 'required', 'string', Rule::in(Cidade::UFS)],
            'ibge' => [
                'sometimes',
                'required',
                'integer',
                'digits:7',
                Rule::unique('cidades', 'ibge')->ignore($cidade->id),
            ],
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
