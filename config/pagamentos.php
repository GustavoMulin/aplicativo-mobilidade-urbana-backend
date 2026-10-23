<?php

return [
    // quem gera e confirma o Pix e o cartão das corridas. "simulado" não move
    // dinheiro: o Pix e o cartão são de mentira e o passageiro confirma o
    // pagamento pelo próprio app. "abacatepay" usa a integração antiga.
    'gateway' => env('PAGAMENTOS_GATEWAY', 'simulado'),
    // em produção o simulado é recusado (corrida de graça com um toque), a
    // menos que esta liberação seja ligada de propósito, ex.: ambiente de demonstração
    'simulado_em_producao' => (bool) env('PAGAMENTOS_SIMULADO_EM_PRODUCAO', false),
    // validade do Pix e prazo para pagar o pedido antes de ele ser cancelado
    'validade_segundos' => (int) env('PAGAMENTOS_VALIDADE_SEGUNDOS', env('ABACATEPAY_VALIDADE_SEGUNDOS', 900)),
];
