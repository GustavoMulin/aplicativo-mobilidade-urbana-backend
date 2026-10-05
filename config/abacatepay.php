<?php

return [
    // a chave define o ambiente: chaves abc_dev_ geram cobranças simuladas
    'base_url' => env('ABACATEPAY_BASE_URL', 'https://api.abacatepay.com'),
    'api_key' => env('ABACATEPAY_API_KEY'),
    // para onde a AbacatePay devolve o cliente depois do checkout de cartão
    'url_retorno' => env('ABACATEPAY_URL_RETORNO', 'https://example.test/pagamento-concluido'),
    'validade_segundos' => (int) env('ABACATEPAY_VALIDADE_SEGUNDOS', 900),
    // simulação de pagamento só existe em ambiente local/dev, nunca em produção
    'simulacao_habilitada' => (bool) env('ABACATEPAY_SIMULACAO_HABILITADA', false),
];
