<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../../application/helpers/garantia_helper.php';

$casos = [
    ['Zero é permitido', garantiaDiasValido('0'), true],
    ['Inteiro positivo simples é aceito', garantiaDiasValido('30'), true],
    ['Memória RAM 365 dias é aceita', garantiaDiasValido('365'), true],
    ['Componente com 1095 dias é aceito', garantiaDiasValido('1095'), true],
    ['Prazo negativo é rejeitado', garantiaDiasValido('-1'), false],
    ['Decimal é rejeitado', garantiaDiasValido('30.5'), false],
    ['Texto inválido é rejeitado', garantiaDiasValido('trinta'), false],
    ['Valor acima do tipo SQL é rejeitado', garantiaDiasValido('4294967296'), false],
    ['O máximo unsigned é aceito', garantiaDiasValido('4294967295'), true],
    ['Snapshot do catálogo com 90 dias', garantiaSnapshotDoCatalogo((object) ['garantia_dias' => 90]) === ['garantia_dias_snapshot' => 90], true],
    ['Snapshot do catálogo com zero dias', garantiaSnapshotDoCatalogo((object) ['garantia_dias' => 0]) === ['garantia_dias_snapshot' => 0], true],
    ['Snapshot do catálogo com prazo personalizado preservado', garantiaSnapshotDoCatalogo((object) ['garantia_dias' => 365]) === ['garantia_dias_snapshot' => 365], true],
    ['Snapshot do catálogo rejeita prazo inválido', garantiaSnapshotDoCatalogo((object) ['garantia_dias' => -1]) === null, true],
    ['Rótulo de item não usa data presumida', garantiaRotuloItem((object) ['garantia_dias_snapshot' => 365]) === '365 dias', true],
    ['Prazo zero tem rótulo objetivo', garantiaAvisoZero() === 'Prazo informado: 0 dias.', true],
];

$falhas = 0;
foreach ($casos as [$nome, $resultado, $esperado]) {
    $passou = $resultado === $esperado;
    printf("%s %s\n", $passou ? 'PASS' : 'FAIL', $nome);
    $falhas += $passou ? 0 : 1;
}
printf("CASES=%d FAILURES=%d\n", count($casos), $falhas);
exit($falhas === 0 ? 0 : 1);