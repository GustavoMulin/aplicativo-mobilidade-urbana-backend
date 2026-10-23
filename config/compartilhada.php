<?php

// Viagem compartilhada: regras fechadas com o produto em 16/09/2026
// (app-mobilidade-urbana-frontend-passageiro/docs/docs-claude/viagem-compartilhada.md)
return [
    // a compartilhada é só de carro e usa a tarifa desta categoria como preço solo
    'produto' => env('COMPARTILHADA_PRODUTO', 'pop'),
    'capacidade' => (int) env('COMPARTILHADA_CAPACIDADE', 4),
    'sala_minutos' => 10,
    // depois do último "pronto", qualquer um ainda pode voltar atrás
    'janela_pronto_segundos' => 10,
    // quem ficou sozinho na sala expirada tem esse tempo para pedir sozinho com um toque
    'expirada_aguarda_segundos' => 300,

    // pré-filtros sem custo de API; a decisão real é o desvio
    'raio_embarque_km' => (float) env('COMPARTILHADA_RAIO_EMBARQUE_KM', 1.5),
    'distancia_maxima_da_rota_km' => 3.0,

    'desvio_maximo_por_entrada_segundos' => 480,
    'desvio_maximo_percentual' => 35,
    // espera da sala + desvio, contado de quando cada um entrou
    'teto_total_segundos' => 1500,

    // fração do preço solo que cada um paga, pelo número de pessoas no carro;
    // quem abriu (ou quem desce por último, se ele sair) paga a menor
    'descontos' => [
        2 => ['outros' => 0.75, 'ultimo' => 0.70],
        3 => ['outros' => 0.65, 'ultimo' => 0.60],
        4 => ['outros' => 0.55, 'ultimo' => 0.50],
    ],
    'adicional_motorista_por_embarque' => 1.50,

    'abrir_minimo_corridas' => (int) env('COMPARTILHADA_MINIMO_CORRIDAS', 20),
    'abrir_nota_minima' => (float) env('COMPARTILHADA_NOTA_MINIMA', 4.0),

    // grupo fechado: prazo para cada um pagar antes de o carro seguir sem ele
    'pagamento_limite_segundos' => 180,
    'espera_embarque_segundos' => 120,
    'espera_cancelamento_automatico_segundos' => 300,

    // ninguém aceitou: amplia o raio e paga bônus ao motorista; no fim, dissolve
    // já despachando corrida normal para cada um
    'escada' => [
        ['apos_segundos' => 30, 'raio_km' => 8.0, 'bonus' => 0.15],
        ['apos_segundos' => 60, 'raio_km' => 12.0, 'bonus' => 0.15],
    ],
    'oferecer_sozinho_apos_segundos' => 60,
    // motorista que desiste de um grupo depois de aceitar fica sem chamadas de
    // grupo por este tempo (as corridas normais continuam)
    'suspensao_por_desistencia_horas' => 24,
    'fuso_para_avisos' => 'America/Porto_Velho',
    'dissolver_apos_segundos' => 120,
];
