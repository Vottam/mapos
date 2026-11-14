<?php
/**
 * Modal "Faturar venda" (#2843), em views/vendas/partes/. O trecho é
 * re-renderizado pelo servidor a cada mudança de produtos ou desconto
 * (Vendas::responderTrechos(), a região [data-os-parte="faturar"] na tela),
 * então o aviso e o estado do botão Faturar refletem sempre o total atual da
 * venda — inclusive quando a inclusão de produtos aconteceu sem recarregar a
 * página. Nada aqui é calculado no navegador; os valores vêm de
 * Vendas_model::totais().
 *
 * Sem permissão para faturar (venda faturada, cancelada ou usuário sem a
 * permissão), o trecho sai vazio.
 *
 * @var object $venda
 * @var array  $totais
 * @var array<string, bool> $pode
 */
if (! ($pode['faturar'] ?? false)) {
    return;
}
?>
<form id="form-faturar" method="post" action="<?= e(site_url('vendas/faturar')) ?>" novalidate hidden data-os-acao="faturar">
    <input type="hidden" name="idVendas" value="<?= e((int) $venda->idVendas) ?>">
</form>
<?= component('modal', [
    'id' => 'faturar-venda',
    'title' => 'Faturar venda #' . (int) $venda->idVendas,
    'body' => [
        component('alert', [
            'variant' => $totais['total'] > 0 ? 'info' : 'warning',
            'message' => $totais['total'] > 0
                ? 'Será lançada uma receita de ' . dinheiro($totais['total']) . ($totais['desconto'] > 0 ? ' (' . dinheiro($totais['bruto']) . ' menos ' . dinheiro($totais['desconto']) . ' de desconto)' : '') . ' para ' . $venda->nomeCliente . '. A venda passa a Faturado e não pode mais ser alterada.'
                : 'Adicione produtos antes de faturar.',
        ]),
        new HtmlSeguro('<div class="mt-4 grid gap-4 sm:grid-cols-2">'),
        new HtmlSeguro('<div class="sm:col-span-2">'),
        component('input', ['name' => 'descricao', 'id' => 'fatura-descricao', 'label' => 'Descrição', 'value' => 'Fatura de Venda Nº: ' . (int) $venda->idVendas, 'required' => true, 'attrs' => ['form' => 'form-faturar', 'maxlength' => '255', 'data-msg-vazio' => 'Informe a descrição do lançamento.']]),
        new HtmlSeguro('</div>'),
        component('input', ['name' => 'vencimento', 'id' => 'fatura-vencimento', 'label' => 'Vencimento', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true, 'attrs' => ['form' => 'form-faturar', 'data-msg-vazio' => 'Informe o vencimento.']]),
        component('select', ['name' => 'formaPgto', 'id' => 'fatura-forma', 'label' => 'Forma de pagamento', 'options' => array_combine(OS_FORMAS_PAGAMENTO, OS_FORMAS_PAGAMENTO), 'placeholder' => 'Escolha', 'required' => true, 'attrs' => ['form' => 'form-faturar', 'data-msg-vazio' => 'Escolha a forma de pagamento.']]),
        component('checkbox', ['name' => 'recebido', 'id' => 'fatura-recebido', 'label' => 'Já foi recebido', 'help' => 'Marca o lançamento como pago.', 'attrs' => ['form' => 'form-faturar', 'data-os-recebido' => 'fatura-recebimento']]),
        component('input', ['name' => 'recebimento', 'id' => 'fatura-recebimento', 'label' => 'Data do recebimento', 'type' => 'date', 'value' => date('Y-m-d'), 'disabled' => true, 'help' => 'Habilitada ao marcar "Já foi recebido".', 'attrs' => ['form' => 'form-faturar', 'data-msg-vazio' => 'Informe a data do recebimento.']]),
        new HtmlSeguro('<div class="sm:col-span-2">'),
        component('textarea', ['name' => 'observacoes', 'id' => 'fatura-observacoes', 'label' => 'Observações', 'rows' => 2, 'attrs' => ['form' => 'form-faturar']]),
        new HtmlSeguro('</div></div>'),
    ],
    'footer' => [
        component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
        component('button', ['label' => 'Faturar', 'icon' => 'circle-dollar-sign', 'type' => 'submit', 'disabled' => $totais['total'] <= 0, 'attrs' => ['form' => 'form-faturar', 'data-rotulo-carregando' => 'Faturando…']]),
    ],
]) ?>
