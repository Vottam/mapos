<?php
/**
 * Formulário de produto, para cadastrar e editar (#2841), no padrão dos
 * formulários (#2851).
 *
 * O módulo produtos/formulario (no wrapper) calcula o preço de venda a partir
 * do preço de compra e do lucro, por markup (sobre a compra) ou margem (sobre
 * a venda), como na v4. O lucro não é salvo: só ajuda a chegar no preço.
 *
 * @var object|null                $produto  null ao cadastrar
 * @var array<string, string|bool> $valores
 * @var array<string, string>      $erros    Por campo; _geral para falha ao salvar
 */
$editando = $produto !== null;
$erroGeral = $erros['_geral'] ?? null;
$errosDeCampo = array_diff_key($erros, ['_geral' => true]);

$unidades = unidadesDeMedida();
// Unidade gravada que não está na tabela continua aparecendo.
if ($valores['unidade'] !== '' && ! isset($unidades[$valores['unidade']])) {
    $unidades = [$valores['unidade'] => $valores['unidade']] + $unidades;
}

// Props do input com o valor e o erro do campo, para component('input', $campo(...)).
$campo = static fn (string $nome, array $props) => $props + [
    'name' => $nome,
    'value' => ($valores[$nome] ?? '') !== '' ? $valores[$nome] : null,
    'error' => $errosDeCampo[$nome] ?? null,
];
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8" <?= js_module('produtos/formulario') ?>>
    <header>
        <h1 class="font-display text-heading-xl text-text"><?= e($editando ? 'Editar produto' : 'Novo produto') ?></h1>
        <p class="text-caption text-muted"><?= e($editando ? $produto->descricao : 'Peça, acessório ou item vendido e usado nas ordens de serviço.') ?></p>
    </header>

    <?php if ($erroGeral !== null) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erroGeral]) ?>
    <?php } elseif ($errosDeCampo !== []) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => count($errosDeCampo) === 1 ? 'Confira o campo destacado.' : 'Confira os ' . count($errosDeCampo) . ' campos destacados.']) ?>
    <?php } ?>

    <form method="post" action="<?= e(current_url()) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <?php if ($editando) { ?>
            <input type="hidden" name="idProdutos" value="<?= (int) $produto->idProdutos ?>">
        <?php } ?>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-produto">
            <h2 id="secao-produto" class="text-heading-sm text-text">Produto</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                <?= component('input', $campo('descricao', [
                    'label' => 'Descrição',
                    'required' => true,
                    'attrs' => ['maxlength' => 80, 'autofocus' => ! $editando, 'data-msg-vazio' => 'Informe a descrição do produto.'],
                ])) ?>
                <?= component('select', [
                    'name' => 'unidade',
                    'label' => 'Unidade',
                    'required' => true,
                    'placeholder' => 'Selecione',
                    'options' => $unidades,
                    'selected' => $valores['unidade'] !== '' ? $valores['unidade'] : 'UNID',
                    'error' => $errosDeCampo['unidade'] ?? null,
                ]) ?>
                <?= component('input', $campo('codDeBarra', [
                    'label' => 'Código de barras',
                    'help' => 'EAN, código interno ou deixe em branco.',
                    'attrs' => ['maxlength' => 70, 'autocomplete' => 'off'],
                ])) ?>
                <fieldset class="flex flex-col gap-2">
                    <legend class="text-label-md text-text">Movimenta o estoque</legend>
                    <div class="flex flex-wrap gap-x-6 gap-y-2 pt-1">
                        <?= component('checkbox', ['name' => 'entrada', 'label' => 'Entrada', 'checked' => (bool) $valores['entrada']]) ?>
                        <?= component('checkbox', ['name' => 'saida', 'label' => 'Saída', 'checked' => (bool) $valores['saida']]) ?>
                    </div>
                </fieldset>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-precos">
            <h2 id="secao-precos" class="text-heading-sm text-text">Preços</h2>
            <p class="mt-0.5 text-caption text-muted">Informe o lucro para calcular o preço de venda, ou digite o preço direto.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <?= component('input', $campo('precoCompra', [
                    'label' => 'Preço de compra (R$)',
                    'required' => true,
                    'class' => 'text-right tabular-nums',
                    'attrs' => ['data-mascara' => 'dinheiro', 'inputmode' => 'decimal', 'maxlength' => 20, 'data-msg-vazio' => 'Informe o preço de compra.'],
                ])) ?>
                <?= component('select', [
                    'name' => 'lucro_tipo',
                    'label' => 'Lucro por',
                    'options' => ['markup' => 'Markup', 'margem' => 'Margem'],
                    'selected' => 'markup',
                    'help' => 'Markup: sobre a compra. Margem: sobre a venda.',
                ]) ?>
                <?= component('input', [
                    'name' => 'lucro',
                    'label' => 'Lucro (%)',
                    'type' => 'number',
                    'class' => 'text-right tabular-nums',
                    'attrs' => ['min' => 0, 'max' => 999, 'step' => '0.01', 'inputmode' => 'decimal'],
                ]) ?>
                <?= component('input', $campo('precoVenda', [
                    'label' => 'Preço de venda (R$)',
                    'required' => true,
                    'class' => 'text-right tabular-nums',
                    'attrs' => ['data-mascara' => 'dinheiro', 'inputmode' => 'decimal', 'maxlength' => 20, 'data-msg-vazio' => 'Informe o preço de venda.'],
                ])) ?>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-estoque">
            <h2 id="secao-estoque" class="text-heading-sm text-text">Estoque</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?= component('input', $campo('estoque', [
                    'label' => $editando ? 'Quantidade em estoque' : 'Estoque inicial',
                    'type' => 'number',
                    'required' => true,
                    'help' => $editando ? 'Para entradas e saídas do dia a dia, use "Entrada de estoque" na ficha do produto.' : null,
                    'attrs' => ['min' => 0, 'max' => 99999999, 'step' => 1, 'inputmode' => 'numeric', 'data-msg-vazio' => 'Informe a quantidade em estoque.'],
                ])) ?>
                <?= component('input', $campo('estoqueMinimo', [
                    'label' => 'Estoque mínimo',
                    'type' => 'number',
                    'help' => 'Abaixo disso, o produto aparece como estoque baixo.',
                    'attrs' => ['min' => 0, 'max' => 99999999, 'step' => 1, 'inputmode' => 'numeric'],
                ])) ?>
            </div>
        </section>

        <?php
        $padraoProdutoGlobal = garantiaPadraoGlobalDias($this->data['configuration'] ?? null, 'produto');
        $fornecedorDias = $produto !== null && $produto->supplier_warranty_days !== null ? (int) $produto->supplier_warranty_days : null;
        $fornecedorRestante = $produto !== null ? garantiaFornecedorRestanteDias($produto) : null;
        $fornecedorVencimento = $produto !== null ? garantiaFornecedorVencimento($produto) : null;
        $fornecedorInicio = $fornecedorDias !== null && $produto !== null ? substr(trim((string) ($produto->supplier_warranty_start_date ?? '')), 0, 10) : '';
        ?>
        <section class="<?= e($caixa) ?>" aria-labelledby="secao-garantia" <?= js_module('garantia/catalogo') ?>>
            <h2 id="secao-garantia" class="text-heading-sm text-text">Garantia comercial (dias)</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?= component('input', [
                    'name' => 'garantia_dias',
                    'label' => 'Prazo comercial informado (dias)',
                    'type' => 'number',
                    'required' => true,
                    'value' => $valores['garantia_dias'] !== '' ? $valores['garantia_dias'] : (string) $padraoProdutoGlobal,
                    'error' => $errosDeCampo['garantia_dias'] ?? null,
                    'help' => 'Desmarcado o campo abaixo: acompanha o padrão das Configurações → Vendas (hoje: ' . $padraoProdutoGlobal . ' dias) e passa a sugerir esse prazo para novas operações. Pode informar 0, 30, 180, 365 ou outro inteiro não negativo.',
                    'attrs' => ['min' => 0, 'step' => 1, 'inputmode' => 'numeric'],
                ]) ?>
                <div class="flex flex-col justify-end">
                    <?= component('checkbox', [
                        'name' => 'garantia_dias_custom',
                        'value' => '1',
                        'label' => 'Personalizar garantia deste produto',
                        'help' => 'Marcado: usa o prazo informado acima e não acompanha o padrão global. Desmarcado: herda o padrão das Configurações (o que valer na data da operação).',
                        'checked' => ($valores['garantia_dias_custom'] ?? '0') === '1',
                    ]) ?>
                </div>
            </div>
            <p class="mt-3 text-caption text-warning" data-garantia-zero-aviso hidden aria-live="polite"><?= e(garantiaAvisoZero()) ?></p>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-fornecedor">
            <h2 id="secao-fornecedor" class="text-heading-sm text-text">Garantia do fornecedor</h2>
            <p class="mt-0.5 text-caption text-muted">Prazo recebido do fabricante ou distribuidor. É independente da garantia oferecida ao cliente e serve como controle interno de cobertura. Não exige lote, unidade, serial ou nota fiscal do fornecedor.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?= component('input', [
                    'name' => 'supplier_warranty_days',
                    'label' => 'Garantia recebida do fornecedor (dias)',
                    'type' => 'number',
                    'value' => $valores['supplier_warranty_days'] ?? '',
                    'error' => $errosDeCampo['supplier_warranty_days'] ?? null,
                    'help' => 'Em branco: não informada. Alterar o prazo reinicia a contagem a partir de hoje. Zero é um prazo válido.',
                    'attrs' => ['min' => 0, 'step' => 1, 'inputmode' => 'numeric'],
                ]) ?>
                <div class="flex flex-col justify-center gap-0.5 rounded-lg border border-border bg-surface-subtle px-4 py-3">
                    <span class="text-label-md text-text">Garantia restante do fornecedor</span>
                    <?php if ($fornecedorDias !== null && $fornecedorRestante !== null) { ?>
                        <?php if ($fornecedorRestante > 0) { ?>
                            <span class="text-body-md font-semibold text-text"><?= (int) $fornecedorRestante ?> dias restantes<?= $fornecedorVencimento !== null ? ' (vence em ' . e(date('d/m/Y', strtotime($fornecedorVencimento))) . ')' : '' ?></span>
                        <?php } else { ?>
                            <span class="text-body-md font-semibold text-warning">Vencida: 0 dias restantes<?= $fornecedorVencimento !== null ? ' (venceu em ' . e(date('d/m/Y', strtotime($fornecedorVencimento))) . ')' : '' ?></span>
                        <?php } ?>
                        <?php if ($fornecedorInicio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fornecedorInicio)) { ?>
                            <span class="text-caption text-muted">Contagem iniciada em <?= e(date('d/m/Y', strtotime($fornecedorInicio))) ?>, sobre o prazo original de <?= (int) $fornecedorDias ?> dias.</span>
                        <?php } ?>
                    <?php } else { ?>
                        <span class="text-body-md text-muted">Não informada.</span>
                    <?php } ?>
                </div>
            </div>
            <div class="mt-3">
                <?= component('checkbox', [
                    'name' => 'supplier_warranty_pass_through',
                    'value' => '1',
                    'label' => 'Repassar garantia adicional ao cliente',
                    'help' => 'Marcado: novas vendas e OS sugerem o maior prazo entre a base comercial e o restante do fornecedor. Nunca reduz o prazo base. Desmarque para não repassar.',
                    'checked' => ($valores['supplier_warranty_pass_through'] ?? '0') === '1',
                ]) ?>
            </div>
            <?php if ($editando && $fornecedorDias !== null) { ?>
                <div class="mt-4 border-t border-border pt-4">
                    <?= component('button', ['label' => 'Renovar contagem da garantia', 'variant' => 'outline', 'icon' => 'history', 'attrs' => ['type' => 'button', 'data-modal-abrir' => 'renovar-fornecedor']]) ?>
                    <p class="mt-1 text-caption text-muted">Reinicia a contagem a partir de hoje, mantendo o prazo atual (<?= (int) $fornecedorDias ?> dias). Use ao comprar nova remessa.</p>
                    <?= component('modal-confirm', ['id' => 'renovar-fornecedor', 'title' => 'Renovar contagem da garantia do fornecedor?', 'message' => 'A contagem recomeça hoje com o mesmo prazo de ' . (int) $fornecedorDias . ' dias. Operações já realizadas não são alteradas.', 'confirm_label' => 'Renovar contagem', 'icon' => 'history', 'confirm_attrs' => ['form' => 'form-renovar-fornecedor']]) ?>
                </div>
            <?php } elseif ($editando) { ?>
                <p class="mt-3 text-caption text-muted">Informe o prazo da garantia do fornecedor acima e salve para habilitar a renovação da contagem.</p>
            <?php } else { ?>
                <p class="mt-3 text-caption text-muted">Ao salvar o produto com um prazo informado, a contagem começa na data deste salvamento.</p>
            <?php } ?>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => $editando ? site_url('produtos/visualizar/' . (int) $produto->idProdutos) : site_url('produtos')]) ?>
            <?= component('button', ['label' => $editando ? 'Salvar alterações' : 'Cadastrar produto', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Salvando…']]) ?>
        </div>
    </form>
    <?php if ($editando && ($fornecedorDias ?? null) !== null) { ?>
    <form id="form-renovar-fornecedor" method="post" action="<?= e(site_url('produtos/renovarGarantiaFornecedor')) ?>" hidden>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="idProdutos" value="<?= (int) $produto->idProdutos ?>">
    </form>
    <?php } ?>
</div>
