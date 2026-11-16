<?php
/**
 * Teste real (SQL real, nao mocks) do schema e da regra de garantia.
 * Uso: DB_HOST=... DB_USER=... DB_PASS=... php garantia-real-test.php
 */
define('BASEPATH', __DIR__ . '/../../');
require_once BASEPATH . 'application/helpers/garantia_helper.php';

$db = new mysqli(getenv('DB_HOST') ?: '192.168.96.2', getenv('DB_USER') ?: 'mapos_staging', getenv('DB_PASS') ?: 'staging_mapos_2026', getenv('DB_NAME') ?: 'mapos_clone_531');
if ($db->connect_errno) { fwrite(STDERR, 'CONNECT_FAIL ' . $db->connect_error . PHP_EOL); exit(2); }
$db->set_charset('utf8mb4');
$PASS = 0; $FAIL = 0; $falhas = [];
function chk($nome, $cond) { global $PASS, $FAIL, $falhas; if ($cond) { $PASS++; echo "PASS  $nome\n"; } else { $FAIL++; $falhas[] = $nome; echo "FAIL  $nome\n"; } }
function q($sql) { global $db; $r = $db->query($sql); if (!$r) { return null; } return $r->fetch_assoc() ?: null; }

echo "== BASELINE ==\n";
$baseline = [];
foreach (['produtos', 'servicos', 'vendas', 'os', 'itens_de_vendas', 'produtos_os', 'servicos_os'] as $t) {
    $r = q("SELECT COUNT(*) c FROM $t"); $baseline[$t] = (int) ($r['c'] ?? -1);
}
chk('produtos=122', $baseline['produtos'] === 122);
chk('servicos=34', $baseline['servicos'] === 34);
chk('vendas=11', $baseline['vendas'] === 11);
chk('os=271', $baseline['os'] === 271);
chk('itens_de_vendas=11', $baseline['itens_de_vendas'] === 11);
chk('produtos_os=132', $baseline['produtos_os'] === 132);
chk('servicos_os=220', $baseline['servicos_os'] === 220);

echo "\n== SNAPSHOTS HISTORICOS NULL ==\n";
chk('itens_de_vendas NULL', (int) (q("SELECT COUNT(*) c FROM itens_de_vendas WHERE garantia_dias_snapshot IS NULL")['c'] ?? -1) === $baseline['itens_de_vendas']);
chk('produtos_os NULL', (int) (q("SELECT COUNT(*) c FROM produtos_os WHERE garantia_dias_snapshot IS NULL")['c'] ?? -1) === $baseline['produtos_os']);
chk('servicos_os NULL', (int) (q("SELECT COUNT(*) c FROM servicos_os WHERE garantia_dias_snapshot IS NULL")['c'] ?? -1) === $baseline['servicos_os']);
chk('vendas sem modelo', (int) (q("SELECT COUNT(*) c FROM vendas WHERE garantia_modelo IS NULL")['c'] ?? -1) === $baseline['vendas']);
chk('os sem modelo', (int) (q("SELECT COUNT(*) c FROM os WHERE garantia_modelo IS NULL")['c'] ?? -1) === $baseline['os']);

echo "\n== DEFAULT CATALOGO ==\n";
chk('produtos default 90', (int) (q("SELECT COUNT(*) c FROM produtos WHERE garantia_dias = 90")['c'] ?? -1) === 122);
chk('servicos default 90', (int) (q("SELECT COUNT(*) c FROM servicos WHERE garantia_dias = 90")['c'] ?? -1) === 34);
chk('migration registrada', (int) (q("SELECT COUNT(*) c FROM migrations WHERE version = 20261115000000")['c'] ?? 0) > 0);

echo "\n== CAMPOS LEGADOS INTACTOS ==\n";
chk('vendas.garantia preservado', (int) (q("SELECT COUNT(*) c FROM vendas WHERE garantia IS NOT NULL")['c'] ?? -1) >= 0);
chk('os.garantia preservado', (int) (q("SELECT COUNT(*) c FROM os WHERE garantia IS NOT NULL")['c'] ?? -1) >= 0);
chk('os.garantias_id preservado', (int) (q("SELECT COUNT(*) c FROM os WHERE garantias_id IS NOT NULL")['c'] ?? -1) >= 0);

echo "\n== VALIDACAO DE ENTRADA ==\n";
chk('0 aceito', garantiaDiasValido('0') === true);
chk('30 aceito', garantiaDiasValido('30') === true);
chk('90 aceito', garantiaDiasValido('90') === true);
chk('180 aceito', garantiaDiasValido('180') === true);
chk('365 aceito', garantiaDiasValido('365') === true);
chk('1095 aceito', garantiaDiasValido('1095') === true);
chk('-1 rejeitado', garantiaDiasValido('-1') === false);
chk('30.5 rejeitado', garantiaDiasValido('30.5') === false);
chk('abc rejeitado', garantiaDiasValido('abc') === false);
chk('4294967296 rejeitado', garantiaDiasValido('4294967296') === false);
chk('4294967295 aceito', garantiaDiasValido('4294967295') === true);
chk('snapshot 0 = 0', garantiaSnapshotDoCatalogo((object) ['garantia_dias' => 0]) === ['garantia_dias_snapshot' => 0]);
chk('snapshot 365 = 365', garantiaSnapshotDoCatalogo((object) ['garantia_dias' => 365]) === ['garantia_dias_snapshot' => 365]);
chk('snapshot invalido null', garantiaSnapshotDoCatalogo((object) ['garantia_dias' => -1]) === null);
chk('rotulo 365 = 365 dias', garantiaRotuloItem((object) ['garantia_dias_snapshot' => 365]) === '365 dias');
chk('rotulo null = nao informado', garantiaRotuloItem((object) ['garantia_dias_snapshot' => null]) === 'Não informado');
chk('aviso zero ok', strpos(garantiaAvisoZero(), 'permanecem aplic') !== false);
chk('aviso legal ok', strpos(garantiaAvisoLegal(), 'legislação de defesa do consumidor') !== false);

echo "\n== CRUD REAL ==\n";
$db->query("INSERT INTO produtos (codDeBarra, descricao, unidade, precoCompra, precoVenda, estoque, estoqueMinimo, saida, entrada, garantia_dias) VALUES ('TESTER531-001', 'Produto garantia 30', 'UN', 100, 150, 10, 0, 1, 1, 30)");
$prodId = (int) $db->insert_id;
chk('INSERT produto 30', $prodId > 0);
$db->query("UPDATE produtos SET garantia_dias = 0 WHERE idProdutos = $prodId");
chk('UPDATE produto para 0', (int) ((q("SELECT garantia_dias FROM produtos WHERE idProdutos = $prodId")['garantia_dias'] ?? -1)) === 0);
$db->query("DELETE FROM produtos WHERE idProdutos = $prodId");
$db->query("INSERT INTO servicos (nome, preco, garantia_dias) VALUES ('TESTER531-SVC', 80, 90)");
$sid = (int) $db->insert_id;
chk('INSERT servico 90', $sid > 0);
$db->query("DELETE FROM servicos WHERE idServicos = $sid");

echo "\nRESULTADO\n";
printf("PASS=%d FAIL=%d\n", $PASS, $FAIL);
foreach ($falhas as $f) { echo "FAILED: $f\n"; }
exit($FAIL === 0 ? 0 : 1);