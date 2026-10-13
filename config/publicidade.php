<?php

return [
    // onde ficam as imagens dos banners: o S3 do Sail (RustFS) no
    // desenvolvimento; os testes trocam por um disco falso
    'disco' => env('PUBLICIDADE_DISCO', 's3'),
    'pasta' => 'banners',
    'tamanho_maximo_kb' => (int) env('PUBLICIDADE_TAMANHO_MAXIMO_KB', 5120),
];
