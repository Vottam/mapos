<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Os.php');
$start = strpos($controller, 'public function adicionar()');
$end = strpos($controller, 'public function editar()', $start);
$create = substr($controller, $start, $end - $start);
foreach (['$datasPostadas[\'dataFinal\']', '$this->input->post(\'garantias_id\') ?: null'] as $needle) {
    if (!str_contains($create, $needle)) {
        fwrite(STDERR, "FAIL OS create normalization missing: {$needle}\n");
        exit(1);
    }
}
if (str_contains($create, 'explode(\'/\', $dataFinal)')) {
    fwrite(STDERR, "FAIL OS create retains legacy date split\n");
    exit(1);
}
echo "PASS OS create date/guarantee normalization regression\n";
