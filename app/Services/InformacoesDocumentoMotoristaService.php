<?php

namespace App\Services;

use App\Enums\TipoDocumentoMotorista;
use Illuminate\Http\Request;

class InformacoesDocumentoMotoristaService
{
    public function normalizar(Request $request): void
    {
        // Compatibilidade com os clientes que ainda enviam cnh/crlv.
        $tipo = $request->input('tipo_documento');
        if (! $request->exists('informacoes_complementares') && in_array($tipo, ['cnh', 'crlv'], true)) {
            if ($request->exists($tipo)) {
                $request->merge(['informacoes_complementares' => $request->input($tipo)]);
            }
        }
    }

    public function regras(Request $request): array
    {
        if ($request->input('tipo_documento') === TipoDocumentoMotorista::CRLV->value) {
            return app(CrlvVeiculoService::class)->regras($request);
        }
        if ($request->input('tipo_documento') !== TipoDocumentoMotorista::CNH->value) {
            // Os demais tipos ainda não possuem campos próprios no formulário.
            return ['informacoes_complementares' => 'nullable|array|max:0', 'veiculo_id' => 'prohibited'];
        }

        return [
            'veiculo_id' => 'prohibited',
            'informacoes_complementares' => 'sometimes|nullable|array:nome,cpf,data_nascimento,numero_registro,cnh_categoria,primeira_habilitacao,data_emissao,cnh_expiracao,ear,observacao',
            'informacoes_complementares.nome' => 'nullable|string|max:255',
            'informacoes_complementares.cpf' => ['nullable', 'string', 'regex:/^[0-9]{11}$/'],
            'informacoes_complementares.data_nascimento' => 'nullable|date_format:Y-m-d',
            'informacoes_complementares.numero_registro' => 'nullable|string|max:20',
            'informacoes_complementares.cnh_categoria' => 'nullable|string|max:20',
            'informacoes_complementares.primeira_habilitacao' => 'nullable|date_format:Y-m-d',
            'informacoes_complementares.data_emissao' => 'nullable|date_format:Y-m-d',
            'informacoes_complementares.cnh_expiracao' => 'nullable|date_format:Y-m-d',
            'informacoes_complementares.ear' => 'nullable|boolean',
            'informacoes_complementares.observacao' => 'nullable|string|max:5000',
        ];
    }
}
