<?php

namespace App\Enums;

enum TipoDocumentoMotorista: string
{
    case CNH = 'cnh';
    case CRLV = 'crlv';
    case NADA_CONSTA = 'nada_consta';
    case SEGURO_OBRIGATORIO = 'seguro_obrigatorio';

    public function titulo(): string
    {
        return match ($this) {
            self::CNH => 'CNH',
            self::CRLV => 'VEÍCULO - CRLV',
            self::NADA_CONSTA => 'NADA CONSTA',
            self::SEGURO_OBRIGATORIO => 'SEGURO',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::CNH => 'CARTEIRA NACIONAL DE HABILITAÇÃO',
            self::CRLV => 'CERTIFICADO DE REGISTRO E LICENCIAMENTO DO VEÍCULO',
            self::NADA_CONSTA => 'CERTIDÃO NEGATIVA DE ANTECEDENTES CRIMINAIS',
            self::SEGURO_OBRIGATORIO => 'SEGURO OBRIGATÓRIO',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $tipo): string => $tipo->value, self::cases());
    }

    /** @return list<array{tipo_documento: string, titulo: string, descricao: string, possui_dados_cnh: bool}> */
    public static function catalogo(): array
    {
        return array_map(fn (self $tipo): array => [
            'tipo_documento' => $tipo->value,
            'titulo' => $tipo->titulo(),
            'descricao' => $tipo->descricao(),
            'possui_dados_cnh' => $tipo === self::CNH,
        ], self::cases());
    }
}
