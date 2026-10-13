<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CadastroUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        $motorista = $this->input('perfil') === 'motorista';

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:1024',
            'telefone' => 'nullable|string|max:20|unique:users,telefone',
            'cpf' => $motorista
                ? 'nullable|string|size:11|unique:users,cpf'
                : 'required|string|size:11|unique:users,cpf',
            'data_nascimento' => 'required|date|before_or_equal:'.now()->subYears(18)->toDateString(),
            'perfil' => 'required|string|in:passageiro,motorista',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:5120',
        ];
    }
}
