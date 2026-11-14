<?php

require_once __DIR__ . '/../../application/helpers/status_helper.php';

$casos = [
    '123,45' => '123.45',
    '1.234,56' => '1234.56',
    '1234.56' => '1234.56',
    '12,3' => '12.30',
    'R$ 9.876,50' => '9876.50',
    'invalido' => null,
];

foreach ($casos as $entrada => $esperado) {
    $obtido = valorDecimal($entrada);
    if ($obtido !== $esperado) {
        fwrite(STDERR, sprintf("FAIL entrada=%s esperado=%s obtido=%s\n", $entrada, var_export($esperado, true), var_export($obtido, true)));
        exit(1);
    }
}

foreach (['application/controllers/Produtos.php', 'application/controllers/Servicos.php'] as $arquivo) {
    $conteudo = file_get_contents(__DIR__ . '/../../' . $arquivo);
    if ($conteudo === false || substr_count($conteudo, 'valorDecimal(') < 2) {
        fwrite(STDERR, "FAIL normalizador ausente em {$arquivo}\n");
        exit(1);
    }
}

echo "PASS monetary normalization regression\n";
