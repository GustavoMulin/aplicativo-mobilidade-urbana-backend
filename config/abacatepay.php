<?php

return [
    // a chave define o ambiente: chaves abc_dev_ geram cobranças simuladas
    'base_url' => env('ABACATEPAY_BASE_URL', 'https://api.abacatepay.com'),
    'api_key' => env('ABACATEPAY_API_KEY'),
    'validade_segundos' => (int) env('ABACATEPAY_VALIDADE_SEGUNDOS', 900),
];
