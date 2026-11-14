<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$expected = [
    'application/views/clientes/formulario.php' => 'name="idClientes"',
    'application/views/produtos/formulario.php' => 'name="idProdutos"',
    'application/views/servicos/formulario.php' => 'name="idServicos"',
    'application/views/garantias/formulario.php' => 'name="idGarantias"',
    'application/views/equipamentos/formulario.php' => 'name="idEquipamentos"',
    'application/views/os/formulario.php' => 'name="idOs"',
    'application/views/vendas/formulario.php' => 'name="idVendas"',
    'application/views/financeiro/formulario.php' => 'name="idLancamentos"',
    'application/views/financeiro/custoFixoForm.php' => 'name="idCustoFixo"',
];
foreach ($expected as $relative => $needle) {
    $path = $root . '/' . $relative;
    $contents = file_get_contents($path);
    if ($contents === false || !str_contains($contents, $needle)) {
        fwrite(STDERR, "FAIL missing edit identifier: {$relative}\n");
        exit(1);
    }
}
echo "PASS edit identifier regression\n";
