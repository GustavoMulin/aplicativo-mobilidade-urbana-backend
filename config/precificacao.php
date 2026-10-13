<?php

// CODEX: 5 linhas alteradas; avaliação, embarque e cancelamentos. Remover após validação.

return [
    'valor_por_km' => (float) env('PRECIFICACAO_VALOR_POR_KM', 1.50),
    'valor_por_minuto' => (float) env('PRECIFICACAO_VALOR_POR_MINUTO', 0.25),
    'taxa_plataforma_percentual' => (float) env('PRECIFICACAO_TAXA_PLATAFORMA', 0.06),

    'distancia_maxima_km' => (float) env('PRECIFICACAO_DISTANCIA_MAXIMA_KM', 5000),
    'tempo_maximo_min' => (float) env('PRECIFICACAO_TEMPO_MAXIMO_MIN', 10080),

    'taxa_plataforma_maxima' => 0.95,

    'raio_busca_padrao_km' => (float) env('PRECIFICACAO_RAIO_BUSCA_PADRAO_KM', 5),
    'intervalo_expansao_raio_segundos' => (int) env('PRECIFICACAO_INTERVALO_EXPANSAO_RAIO_SEGUNDOS', 30),
    'incremento_raio_busca_km' => (float) env('PRECIFICACAO_INCREMENTO_RAIO_BUSCA_KM', 2),
    'raio_busca_maximo_km' => (float) env('PRECIFICACAO_RAIO_BUSCA_MAXIMO_KM', 20),

    'motorista_online_expira_segundos' => (int) env('MOTORISTA_ONLINE_EXPIRA_SEGUNDOS', 90),
    'distancia_maxima_chegada_km' => (float) env('MOTORISTA_DISTANCIA_MAXIMA_CHEGADA_KM', 0.5),
    'posicao_chegada_validade_segundos' => (int) env('MOTORISTA_POSICAO_CHEGADA_VALIDADE_SEGUNDOS', 120),

    'cancelamento_passageiro_carencia_segundos' => (int) env('CANCELAMENTO_PASSAGEIRO_CARENCIA_SEGUNDOS', 180),
    'cancelamento_passageiro_distancia_minima_km' => (float) env('CANCELAMENTO_PASSAGEIRO_DISTANCIA_MINIMA_KM', 1.0),

    'cotacao_validade_minutos' => (int) env('PRECIFICACAO_COTACAO_VALIDADE_MIN', 10),

    'espera_tolerancia_segundos' => (int) env('PRECIFICACAO_ESPERA_TOLERANCIA_SEGUNDOS', 120),
    'espera_limite_cobranca_segundos' => (int) env('PRECIFICACAO_ESPERA_LIMITE_COBRANCA_SEGUNDOS', 720),
    'espera_cancelamento_automatico_segundos' => (int) env('PRECIFICACAO_ESPERA_CANCELAMENTO_AUTOMATICO_SEGUNDOS', 720),

    // Negocia: oferta do passageiro entre essas frações do preço sugerido
    'negocia_oferta_minima_fracao' => (float) env('NEGOCIA_OFERTA_MINIMA_FRACAO', 0.7),
    'negocia_oferta_maxima_fracao' => (float) env('NEGOCIA_OFERTA_MAXIMA_FRACAO', 2.0),
    'negocia_proposta_validade_segundos' => (int) env('NEGOCIA_PROPOSTA_VALIDADE_SEGUNDOS', 90),
    // escolhida a proposta no Pix ou cartão, o motorista espera o pagamento por até
    'negocia_pagamento_limite_segundos' => (int) env('NEGOCIA_PAGAMENTO_LIMITE_SEGUNDOS', 300),
];
