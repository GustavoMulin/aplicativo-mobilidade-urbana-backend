<?php

return [
    // com poucas corridas a primeira nota vira a média inteira (um 5 na
    // primeira corrida deixaria a pessoa com 5,0); até aqui o app não mostra média
    'minimo_corridas_para_media' => (int) env('AVALIACOES_MINIMO_CORRIDAS_PARA_MEDIA', 20),
];
