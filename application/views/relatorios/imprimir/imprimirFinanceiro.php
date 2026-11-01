<?php
$totalReceita = 0;
$totalDespesa = 0;
foreach ($lancamentos as $l) {
    $valor = $l->valor_desconto != 0 ? $l->valor_desconto : $l->valor;
    if ($l->tipo == 'receita') {
        $totalReceita += $valor;
    } else {
        $totalDespesa += $valor;
    }
}
$indicadores = $indicadores_financeiros ?? [];
$gerenciais = $indicadores['gerenciais'] ?? [];
$osOperacionais = $indicadores['os_operacionais'] ?? [];
$resultadoAposFixos = $gerenciais['resultado_apos_custos_fixos'] ?? null;
$resultadoAposFixosComparavel = ($gerenciais['resultado_apos_custos_fixos_comparavel'] ?? false) === true;
$this->load->view('relatorios/imprimir/partes/inicio', ['tituloDoc' => 'Relatório Financeiro']);
?>
    <table class="rel-tabela">
        <thead>
            <tr>
                <th class="rel-th">Cliente/Fornecedor</th>
                <th class="rel-th">Tipo</th>
                <th class="rel-th">Valor</th>
                <th class="rel-th">Desconto</th>
                <th class="rel-th">Valor total</th>
                <th class="rel-th">Vencimento</th>
                <th class="rel-th">Pagamento</th>
                <th class="rel-th">Forma de pgto.</th>
                <th class="rel-th">Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lancamentos as $l) : ?>
                <tr>
                    <td class="rel-td"><?= e($l->cliente_fornecedor) ?></td>
                    <td class="rel-td"><?= e($l->tipo) ?></td>
                    <td class="rel-td">R$ <?= number_format($l->valor, 2, ',', '.') ?></td>
                    <td class="rel-td"><?= e(($l->tipo_desconto == 'real' ? 'R$ ' : '') . number_format($l->desconto, 2, ',', '.') . ($l->tipo_desconto == 'porcento' ? ' %' : '')) ?></td>
                    <td class="rel-td">R$ <?= number_format($l->valor_desconto != 0 ? $l->valor_desconto : $l->valor, 2, ',', '.') ?></td>
                    <td class="rel-td"><?= e(date('d/m/Y', strtotime($l->data_vencimento))) ?></td>
                    <td class="rel-td"><?= e(date('d/m/Y', strtotime($l->data_pagamento))) ?></td>
                    <td class="rel-td"><?= e($l->forma_pgto) ?></td>
                    <td class="rel-td"><?= e($l->baixado == 1 ? 'Pago' : 'Pendente') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td class="rel-total text-right text-success" colspan="5">Total receitas:</td>
                <td class="rel-total text-success" colspan="4">R$ <?= number_format($totalReceita, 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="rel-total text-right text-danger" colspan="5">Total despesas:</td>
                <td class="rel-total text-danger" colspan="4">R$ <?= number_format($totalDespesa, 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="rel-total text-right" colspan="5">Saldo:</td>
                <td class="rel-total" colspan="4">R$ <?= number_format($totalReceita - $totalDespesa, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>

    <?php if ($indicadores !== []) : ?>
        <h2 class="rel-titulo">Indicadores gerenciais do período</h2>
        <p class="text-xs">Escopo dos lançamentos: <?= e((string) ($gerenciais['escopo_lancamentos'] ?? 'não informado')) ?>.</p>
        <p class="text-xs">Escopo dos custos fixos: <?= e((string) ($gerenciais['escopo_custos_fixos'] ?? 'não informado')) ?>.</p>
        <table class="rel-tabela">
            <tbody>
                <tr><td class="rel-td">Custo de componentes em OS</td><td class="rel-td">R$ <?= number_format((float) ($gerenciais['custo_componentes_os'] ?? 0), 2, ',', '.') ?></td></tr>
                <tr><td class="rel-td">Custo de produtos em vendas</td><td class="rel-td">R$ <?= number_format((float) ($gerenciais['custo_produtos_vendas'] ?? 0), 2, ',', '.') ?></td></tr>
                <tr><td class="rel-td">Custo de componentes do período</td><td class="rel-td">R$ <?= number_format((float) ($gerenciais['custo_componentes_periodo'] ?? 0), 2, ',', '.') ?></td></tr>
                <tr><td class="rel-td">Resultado líquido real</td><td class="rel-td">R$ <?= number_format((float) ($gerenciais['resultado_liquido_real'] ?? 0), 2, ',', '.') ?></td></tr>
                <tr><td class="rel-td">Custos fixos do período</td><td class="rel-td">R$ <?= number_format((float) ($gerenciais['custos_fixos_periodo'] ?? 0), 2, ',', '.') ?></td></tr>
                <tr><td class="rel-td">Custos fixos pagos</td><td class="rel-td">R$ <?= number_format((float) ($gerenciais['custos_fixos_pago'] ?? 0), 2, ',', '.') ?></td></tr>
                <tr><td class="rel-td">Custos fixos pendentes</td><td class="rel-td">R$ <?= number_format((float) ($gerenciais['custos_fixos_a_pagar'] ?? 0), 2, ',', '.') ?></td></tr>
                <tr>
                    <td class="rel-td">Resultado após custos fixos</td>
                    <td class="rel-td"><?= $resultadoAposFixosComparavel && $resultadoAposFixos !== null ? 'R$ ' . number_format((float) $resultadoAposFixos, 2, ',', '.') : 'Não comparável com filtros de tipo/situação/pesquisa' ?></td>
                </tr>
            </tbody>
        </table>
        <p class="text-xs">O resultado líquido real é receitas − despesas − custos de componentes. O resultado após custos fixos segue competência e não representa necessariamente caixa realizado. Custos fixos não são somados novamente às despesas de lançamentos.</p>

        <h2 class="rel-titulo">Indicadores operacionais das ordens de serviço</h2>
        <p class="text-xs">Escopo das OS: data de abertura entre <?= e(date('d/m/Y', strtotime((string) ($osOperacionais['data_inicial'] ?? 'now')))) ?> e <?= e(date('d/m/Y', strtotime((string) ($osOperacionais['data_final'] ?? 'now')))) ?>. Estes valores não são somados às receitas/despesas acima.</p>
        <table class="rel-tabela">
            <tbody>
                <tr><td class="rel-td">OS no escopo</td><td class="rel-td"><?= number_format((int) ($osOperacionais['quantidade_os'] ?? 0), 0, ',', '.') ?></td></tr>
                <tr><td class="rel-td">V.T. faturado das OS</td><td class="rel-td">R$ <?= number_format((float) ($osOperacionais['vt_faturado'] ?? 0), 2, ',', '.') ?></td></tr>
                <tr><td class="rel-td">Valor líquido operacional das OS</td><td class="rel-td">R$ <?= number_format((float) ($osOperacionais['valor_liquido'] ?? 0), 2, ',', '.') ?></td></tr>
            </tbody>
        </table>
        <p class="text-xs">Os indicadores operacionais reutilizam a fórmula certificada de Os_model::indicadoresFinanceirosSql()/aplicarIndicadoresFinanceiros().</p>
    <?php endif; ?>
<?php $this->load->view('relatorios/imprimir/partes/fim'); ?>