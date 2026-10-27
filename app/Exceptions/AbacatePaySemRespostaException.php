<?php

namespace App\Exceptions;

/**
 * A AbacatePay não respondeu (timeout ou queda de conexão). Diferente de uma
 * recusa: a operação pode ter sido feita do lado de lá, então quem chamou não
 * deve presumir que falhou (ex.: estorno que talvez já tenha saído).
 */
class AbacatePaySemRespostaException extends GatewaySemRespostaException {}
