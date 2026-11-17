<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../../application/helpers/general_helper.php';
require_once __DIR__ . '/../../application/helpers/garantia_helper.php';

$hoje = '2026-11-17';
$entrega = '2026-11-16';

$casos = [];

// A. Apenas um serviço de 90 dias.
$casos[] = ['A: um serviço 90 dias calcula +90', garantiaEstadoPorItem(['garantia_dias_snapshot' => 90], $entrega, $hoje)['data_vencimento'] === '2027-02-14', true];
// B. Apenas um produto de 90 dias.
$casos[] = ['B: um produto 90 dias calcula +90', garantiaEstadoPorItem(['garantia_dias_snapshot' => 90], $entrega, $hoje)['prazo'] === 90, true];
// C. Vários produtos de prazos distintos, agregado MAX.
$produtos = [
    ['garantia_dias_snapshot' => 90, 'tipo' => 'produto', 'nome' => 'Cabo de força'],
    ['garantia_dias_snapshot' => 180, 'tipo' => 'produto', 'nome' => 'Cabo de HDMI'],
    ['garantia_dias_snapshot' => 365, 'tipo' => 'produto', 'nome' => 'Tela'],
];
$casos[] = ['C: MAX entre produtos é 365 dias', garantiaUltimoVencimento($produtos, $entrega, $hoje) === '2027-11-16', true];
// D. Vários serviços de prazos distintos, agregado MAX.
$servicos = [
    ['garantia_dias_snapshot' => 30, 'tipo' => 'servico', 'nome' => 'Limpeza'],
    ['garantia_dias_snapshot' => 180, 'tipo' => 'servico', 'nome' => 'Manutenção'],
    ['garantia_dias_snapshot' => 90, 'tipo' => 'servico', 'nome' => 'Defeito'],
];
$casos[] = ['D: MAX entre serviços é 180 dias', garantiaUltimoVencimento($servicos, $entrega, $hoje) === '2027-05-15', true];
// E. Produtos e serviços misturados.
$misturados = array_merge($produtos, $servicos);
$casos[] = ['E: MAX misto é 365 dias', garantiaUltimoVencimento($misturados, $entrega, $hoje) === '2027-11-16', true];
// F. Garantia zero: não gera vencimento.
$casos[] = ['F: zero não gera data', garantiaEstadoPorItem(['garantia_dias_snapshot' => 0], $entrega, $hoje)['data_vencimento'] === null, true];
$casos[] = ['F: zero entra no agregado como sem data', garantiaUltimoVencimento([['garantia_dias_snapshot' => 0]], $entrega, $hoje) === null, true];
$casos[] = ['F: zero situacao registrada', garantiaEstadoPorItem(['garantia_dias_snapshot' => 0], $entrega, $hoje)['situacao'] === 'zero_registrado', true];
// G. 30 dias.
$casos[] = ['G: 30 dias calculado', garantiaEstadoPorItem(['garantia_dias_snapshot' => 30], $entrega, $hoje)['data_vencimento'] === '2026-12-16', true];
// H. 180 e 365.
$casos[] = ['H: 180 dias', garantiaEstadoPorItem(['garantia_dias_snapshot' => 180], $entrega, $hoje)['data_vencimento'] === '2027-05-15', true];
$casos[] = ['H: 365 dias', garantiaEstadoPorItem(['garantia_dias_snapshot' => 365], $entrega, $hoje)['data_vencimento'] === '2027-11-16', true];
// I. OS sem entrega efetiva.
$semEntrega = garantiaEstadoPorItem(['garantia_dias_snapshot' => 90], null, $hoje);
$casos[] = ['I: sem entrega não gera vencimento', $semEntrega['data_vencimento'] === null, true];
$casos[] = ['I: sem entrega situação aguardando', $semEntrega['situacao'] === 'aguardando_entrega', true];
$casos[] = ['I: sem entrega agregado null', garantiaUltimoVencimento([['garantia_dias_snapshot' => 90]], null, $hoje) === null, true];
// J. Entrega efetiva gera vencimento.
$casos[] = ['J: com entrega gera vencimento', garantiaEstadoPorItem(['garantia_dias_snapshot' => 90], $entrega, $hoje)['data_vencimento'] !== null, true];
// K. Vencimento geral = MAX.
$casos[] = ['K: vencimento geral MAX', garantiaMaiorData('2027-02-14', '2027-11-16') === '2027-11-16', true];
// L. Todos vencidos.
$vencidos = [
    ['garantia_dias_snapshot' => 1, 'tipo' => 'produto', 'nome' => 'Peça'],
    ['garantia_dias_snapshot' => 1, 'tipo' => 'servico', 'nome' => 'Serviço'],
];
$normVenc = garantiaItensNormalizados($vencidos, '2026-11-01', $hoje);
$casos[] = ['L: todos vencidos resumido', garantiaResumoItens($normVenc)['todos_vencidos'] === true, true];
$casos[] = ['L: todos vencidos status vencido', garantiaClassificarStatusItem($normVenc) === 'vencida', true];
// M. Apenas um item vigente.
$misto = [
    ['garantia_dias_snapshot' => 1, 'tipo' => 'produto', 'nome' => 'Peça velha'],
    ['garantia_dias_snapshot' => 365, 'tipo' => 'produto', 'nome' => 'Tela'],
];
$normMisto = garantiaItensNormalizados($misto, '2026-11-01', $hoje);
$casos[] = ['M: mistura vigente+vencido tem item vigente', garantiaResumoItens($normMisto)['tem_vigente'] === true, true];
$casos[] = ['M: mistura vigente+vencido tem item vencido', garantiaResumoItens($normMisto)['tem_vencido'] === true, true];
// N. Todos vigentes.
$todosVig = garantiaItensNormalizados($misturados, $entrega, $hoje);
$casos[] = ['N: todos vigentes não tem vencido', garantiaResumoItens($todosVig)['tem_vencido'] === false, true];
// O. Exatamente no dia do vencimento.
$diaExato = garantiaEstadoPorItem(['garantia_dias_snapshot' => 1], '2026-11-16', '2026-11-17');
$casos[] = ['O: dia exato vence_hoje', $diaExato['situacao'] === 'vence_hoje', true];
$casos[] = ['O: dia exato dias restantes zero', $diaExato['dias_restantes'] === 0, true];
// P. Reabertura sem reiniciar a data: a normalização usa a data passada (data de entrega original), não a data de reabertura.
$reab = garantiaEstadoPorItem(['garantia_dias_snapshot' => 30], '2026-10-01', $hoje);
$casos[] = ['P: reabertura usa data original de entrega', $reab['data_vencimento'] === '2026-10-31', true];
// Q. Histórico legado (snapshot NULL): informação indeterminada, sem prazo inventado.
$legado = garantiaEstadoPorItem(['garantia_dias_snapshot' => null], $entrega, $hoje);
$casos[] = ['Q: legado sem prazo retorna nao_informado', $legado['situacao'] === 'nao_informado', true];
$casos[] = ['Q: legado não gera vencimento', $legado['data_vencimento'] === null, true];
// R. Histórico legado sem prazo calculável / sem entrega.
$casos[] = ['R: legado agregado null', garantiaUltimoVencimento([['garantia_dias_snapshot' => null]], $entrega, $hoje) === null, true];
// S/T/U/V. Consulta por serial: normalização compõe itens mistos independentemente da OS de origem (tipo preservado).
$itensAmbasOs = [
    ['garantia_dias_snapshot' => null, 'tipo' => 'produto', 'nome' => 'Peça antiga', 'idItem' => 1],
    ['garantia_dias_snapshot' => 365, 'tipo' => 'produto', 'nome' => 'Peça nova', 'idItem' => 2],
];
$normMix = garantiaItensNormalizados($itensAmbasOs, $entrega, $hoje);
$casos[] = ['S/T/U: itens mistos preservam tipos e snapshots', count($normMix) === 2 && $normMix[0]['situacao'] === 'nao_informado' && $normMix[1]['situacao'] === 'vigente', true];
// V. Seriais iguais em contextos distintos: o motor não agrupa por OS; cada item mantém seu próprio cálculo.
$casos[] = ['V: itens de seriais diferentes não vazam prazo', garantiaUltimoVencimento([['garantia_dias_snapshot' => 90]], $entrega, $hoje) === '2027-02-14' && garantiaUltimoVencimento([['garantia_dias_snapshot' => null]], $entrega, $hoje) === null, true];

$falhas = 0;
foreach ($casos as [$nome, $resultado, $esperado]) {
    $passou = $resultado === $esperado;
    printf("%s %s\n", $passou ? 'PASS' : 'FAIL', $nome);
    $falhas += $passou ? 0 : 1;
}
printf("CASES=%d FAILURES=%d\n", count($casos), $falhas);
exit($falhas === 0 ? 0 : 1);
