<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Passageiro com valor em aberto de uma corrida anterior não pode pedir outra
 * até pagar. Não estende RuntimeException para não ser engolida pelos
 * try/catch genéricos dos controllers: o Laravel chama render() direto.
 */
class PagamentoPendenteException extends Exception
{
    public function __construct(
        public readonly int $corridaId,
        public readonly string $codigoCorrida,
        public readonly float $valor,
    ) {
        parent::__construct('Você tem um pagamento pendente de uma corrida anterior. Pague para pedir uma nova corrida.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'codigo' => 'pagamento_pendente',
            'pendencia' => [
                'corrida_id' => $this->corridaId,
                'codigo_corrida' => $this->codigoCorrida,
                'valor' => $this->valor,
            ],
        ], 409);
    }
}
