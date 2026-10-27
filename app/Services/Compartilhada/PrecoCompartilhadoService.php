<?php

namespace App\Services\Compartilhada;

use App\Models\Tarifa;
use App\Services\CalcularPrecoCorridaService;

/**
 * A tabela de desconto é uma promessa fixa ao passageiro: cada um paga uma
 * fração do próprio preço solo, que cai conforme o carro enche. Quem desce por
 * último (quem abriu, enquanto estiver no grupo) paga a fração menor. O voto
 * de "pronto" trava o teto: depois dele o preço pode cair, nunca subir.
 */
class PrecoCompartilhadoService
{
    public function __construct(private readonly CalcularPrecoCorridaService $calcularPreco) {}

    public function fracao(int $pessoas, bool $ultimo): float
    {
        /** @var array<int, array{outros: float, ultimo: float}> $tabela */
        $tabela = config('compartilhada.descontos', []);

        if ($pessoas < 2 || $tabela === []) {
            return 1.0;
        }

        $faixa = $tabela[min($pessoas, max(array_keys($tabela)))] ?? null;

        if ($faixa === null) {
            return 1.0;
        }

        return (float) ($ultimo ? $faixa['ultimo'] : $faixa['outros']);
    }

    /**
     * @param  array<int|string, array{preco_solo: float, teto: float|null}>  $passageiros
     * @return array<int|string, float>
     */
    public function precos(array $passageiros, int|string|null $ultimo): array
    {
        $pessoas = count($passageiros);
        $precos = [];

        foreach ($passageiros as $chave => $passageiro) {
            $preco = round($passageiro['preco_solo'] * $this->fracao($pessoas, $chave === $ultimo), 2);
            $precos[$chave] = $passageiro['teto'] === null ? $preco : min($preco, $passageiro['teto']);
        }

        return $precos;
    }

    /**
     * Tarifa sobre a rota inteira do grupo, mais um adicional por embarque
     * extra: compartilhada é mais trabalho e o card do motorista precisa
     * mostrar que vale mais a pena.
     */
    public function valorMotorista(?Tarifa $tarifa, float $km, float $minutos, int $passageiros): float
    {
        if ($tarifa === null) {
            return 0.0;
        }

        $calculo = $this->calcularPreco->executar(tarifa: $tarifa, distanciaKm: $km, tempoMin: $minutos);
        $adicional = (float) config('compartilhada.adicional_motorista_por_embarque', 0) * max(0, $passageiros - 1);

        return round((float) $calculo['valores']['valor_motorista'] + $adicional, 2);
    }

    /**
     * Divide o valor do motorista entre as corridas do grupo na proporção do
     * que cada passageiro paga; o arredondamento fica na última.
     *
     * @param  array<int|string, float>  $precos
     * @return array<int|string, float>
     */
    public function repartir(float $total, array $precos): array
    {
        $soma = array_sum($precos);
        $partes = [];
        $distribuido = 0.0;
        $chaves = array_keys($precos);
        $ultima = end($chaves);

        foreach ($precos as $chave => $preco) {
            if ($chave === $ultima) {
                $partes[$chave] = round($total - $distribuido, 2);
                break;
            }

            $parte = $soma > 0 ? round($total * $preco / $soma, 2) : round($total / count($precos), 2);
            $partes[$chave] = $parte;
            $distribuido += $parte;
        }

        return $partes;
    }
}
