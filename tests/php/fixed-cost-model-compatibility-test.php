<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Financeiro.php');
$model = file_get_contents($root . '/application/models/Financeiro_model.php');
$checks = [
    [$controller, 'adicionarCustoFixo($data)', 'controller uses cost insert method'],
    [$controller, 'atualizarCustoFixo($id, $data)', 'controller uses cost update method'],
    [$model, 'public function adicionarCustoFixo', 'model exposes cost insert method'],
    [$model, 'public function atualizarCustoFixo', 'model exposes cost update method'],
];
foreach ($checks as [$haystack, $needle, $label]) {
    if (!str_contains((string) $haystack, $needle)) {
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }
}
if (str_contains((string) $controller, "financeiro_model->add('custos_fixos'")) {
    fwrite(STDERR, "FAIL stale cost insert API\n");
    exit(1);
}
echo "PASS fixed-cost model/controller compatibility regression\n";
