<?php
/**
 * Formulário de venda, para cadastrar e editar os dados (#2843), no padrão dos
 * formulários da v5 (#2851, views/clientes/formulario.php e views/os/formulario.php).
 *
 * - POST comum, com o token CSRF; o módulo formulario/padrao valida no
 *   navegador e o servidor valida de novo (Vendas::formulario()).
 * - Cliente e vendedor são autocompletes (módulo vendas/formulario,
 *   lib/autocomplete.js): o texto fica no campo visível e o id escolhido no
 *   campo oculto ao lado. Digitar sem escolher da lista deixa o campo
 *   inválido. As opções vêm de os/autoCompleteCliente e
 *   os/autoCompleteUsuario, que aceitam as permissões de venda.
 * - Os textos livres são texto simples; a formatação do editor antigo
 *   (Trumbowyg) sai ao salvar, e as quebras de linha ficam.
 * - Produtos, desconto e faturamento ficam na tela da venda
 *   (vendas/visualizar), linkada no topo ao editar.
 *
 * @var object|null             $venda      null ao cadastrar
 * @var array<string, string>   $valores    vendaValoresDoFormulario()
 * @var array<string, string>   $erros      Por campo; _geral para falha ao salvar
 * @var list<string>            $formatados Campos de texto com formatação do editor antigo
 * @var array<string, bool>     $pode
 */
$editando = $venda !== null;
$erroGeral = $erros['_geral'] ?? null;
$errosDeCampo = array_diff_key($erros, ['_geral' => true]);

$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$grade = 'mt-4 grid gap-4 sm:grid-cols-2';

/*
 * Autocomplete: input visível (o texto) + oculto (o id), num wrapper que diz
 * ao módulo de onde vêm as opções (data-autocomplete) e qual campo guarda o
 * id (data-autocomplete-alvo).
 */
$visivel = static fn (string $nome, array $props) => $props + [
    'name' => $nome,
    'value' => $valores[$nome] !== '' ? $valores[$nome] : null,
    'error' => $errosDeCampo[$nome] ?? null,
    'autocomplete' => 'off',
];
$wrapper = static fn (string $url, string $oculto, array $extras = []) => ['class' => 'relative', 'data-autocomplete' => $url, 'data-autocomplete-alvo' => componenteId($oculto)] + $extras;
$oculto = static fn (string $nome) => ['type' => 'hidden', 'id' => componenteId($nome), 'name' => $nome, 'value' => $valores[$nome]];

$texto = static fn (string $nome, string $label, ?string $ajuda = null) => [
    'name' => $nome,
    'label' => $label,
    'rows' => 4,
    'value' => $valores[$nome] !== '' ? $valores[$nome] : null,
    'error' => $errosDeCampo[$nome] ?? null,
    'help' => in_array($nome, $formatados, true)
        ? 'A formatação do editor antigo (negrito, listas) sai ao salvar. O texto e as quebras de linha ficam.'
        : $ajuda,
];

$statusOpcoes = array_combine(array_keys(OS_STATUS_VARIANTES), array_keys(OS_STATUS_VARIANTES));
$urlPath = static fn (string $url): string => (string) (parse_url($url, PHP_URL_PATH) ?: $url);
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8" <?= js_module('vendas/formulario') ?> data-documento-busca="<?= e($urlPath(site_url('clientes/buscarPorDocumento'))) ?>">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display text-heading-xl text-text"><?= e($editando ? 'Editar venda #' . $venda->idVendas : 'Nova venda') ?></h1>
                <?php if ($editando) { ?>
                    <?= component('pill-status', osStatusPill($venda->status)) ?>
                <?php } ?>
            </div>
            <p class="text-caption text-muted"><?= e($editando ? $venda->nomeCliente : 'Os produtos são adicionados depois de criar a venda.') ?></p>
        </div>
        <?php if ($editando && $pode['ver_venda']) { ?>
            <?= component('button', [
                'label' => 'Ver venda, produtos e faturamento',
                'icon' => 'eye',
                'variant' => 'outline',
                'href' => site_url('vendas/visualizar/' . (int) $venda->idVendas),
            ]) ?>
        <?php } ?>
    </header>

    <?php if ($erroGeral !== null) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erroGeral]) ?>
    <?php } elseif ($errosDeCampo !== []) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => count($errosDeCampo) === 1 ? 'Confira o campo destacado.' : 'Confira os ' . count($errosDeCampo) . ' campos destacados.']) ?>
    <?php } ?>

    <form method="post" action="<?= e(current_url()) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <?php if ($editando) { ?>
            <input type="hidden" name="idVendas" value="<?= (int) $venda->idVendas ?>">
        <?php } ?>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-responsaveis">
            <h2 id="secao-responsaveis" class="text-heading-sm text-text">Cliente e vendedor</h2>
            <div class="<?= e($grade) ?>">
            <div class="sm:col-span-2 rounded-lg border border-border-subtle bg-surface-subtle p-3" data-os-documento>
                <?= component('input', [
                    'name' => 'cliente_documento',
                    'label' => 'CPF ou CNPJ do comprador',
                    'value' => ($valores['cliente_documento'] ?? '') !== '' ? $valores['cliente_documento'] : null,
                    'placeholder' => 'Digite o documento para identificar o cliente',
                    'attrs' => [
                        'data-mascara' => 'documento',
                        'maxlength' => 20,
                        'inputmode' => 'numeric',
                        'autocomplete' => 'off',
                        'autofocus' => ! $editando,
                        'data-msg-invalido' => 'Informe um CPF ou CNPJ com 11 ou 14 dígitos.',
                    ],
                ]) ?>
                <div class="mt-2 flex flex-wrap items-center gap-2" data-documento-status aria-live="polite"></div>
                <?php if ($pode['cadastrar_cliente']) { ?>
                    <?= component('button', ['label' => 'Cadastrar novo cliente', 'icon' => 'plus', 'variant' => 'outline', 'type' => 'button', 'class' => 'hidden', 'attrs' => ['data-documento-novo' => true]]) ?>
                <?php } ?>
            </div>
                <div<?= componenteAtributos($wrapper($urlPath(site_url('os/autoCompleteCliente')), 'clientes_id', $pode['cadastrar_cliente'] ? [
                    'data-autocomplete-novo' => site_url('clientes/adicionar'),
                    'data-autocomplete-novo-modal' => 'cliente-rapido',
                    'data-autocomplete-novo-rotulo' => 'Cadastrar novo cliente',
                ] : [])) ?>>
                    <?= component('input', $visivel('cliente', [
                        'label' => 'Cliente',
                        'required' => true,
                        'placeholder' => 'Nome, documento ou telefone',
                        'attrs' => [
                            'autofocus' => ! $editando,
                            'data-msg-vazio' => 'Escolha o cliente da venda.',
                            'data-msg-invalido' => 'Escolha um cliente da lista.',
                        ],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('clientes_id')) ?>>
                </div>
                <div<?= componenteAtributos($wrapper(site_url('os/autoCompleteUsuario'), 'usuarios_id')) ?>>
                    <?= component('input', $visivel('vendedor', [
                        'label' => 'Vendedor',
                        'required' => true,
                        'placeholder' => 'Nome do vendedor',
                        'attrs' => [
                            'data-msg-vazio' => 'Escolha o vendedor.',
                            'data-msg-invalido' => 'Escolha um vendedor da lista.',
                        ],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('usuarios_id')) ?>>
                </div>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-situacao">
            <h2 id="secao-situacao" class="text-heading-sm text-text"><?= e($editando && ($venda->garantia_modelo ?? null) !== 'por_item' ? 'Situação e garantia histórica' : 'Situação') ?></h2>
            <div class="<?= e($grade) ?>">
                <?= component('select', [
                    'name' => 'status',
                    'label' => 'Status',
                    'required' => true,
                    'options' => $statusOpcoes,
                    'selected' => $valores['status'] !== '' ? $valores['status'] : null,
                    'error' => $errosDeCampo['status'] ?? null,
                    'help' => $editando ? 'Cancelar a venda devolve os produtos ao estoque.' : null,
                ]) ?>
                <?= component('input', [
                    'name' => 'dataVenda',
                    'label' => 'Data da venda',
                    'type' => 'date',
                    'required' => true,
                    'value' => $valores['dataVenda'] !== '' ? $valores['dataVenda'] : null,
                    'error' => $errosDeCampo['dataVenda'] ?? null,
                    'attrs' => ['data-msg-vazio' => 'Informe a data da venda.'],
                ]) ?>
                <?php if ($editando && ($venda->garantia_modelo ?? null) !== 'por_item') { ?>
                    <?= component('input', [
                        'name' => 'garantia',
                        'label' => 'Garantia histórica (dias)',
                        'type' => 'number',
                        'value' => $valores['garantia'] !== '' ? $valores['garantia'] : null,
                        'error' => $errosDeCampo['garantia'] ?? null,
                        'help' => 'Campo legado da venda histórica. Prazos por item não são representados por este valor.',
                        'attrs' => ['min' => 0, 'step' => 1, 'inputmode' => 'numeric'],
                    ]) ?>
                <?php } else { ?>
                    <p class="sm:col-span-2 text-caption text-muted">A garantia comercial é registrada individualmente em cada item. Os direitos legais permanecem aplicáveis e não são somados automaticamente.</p>
                <?php } ?>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-observacoes">
            <h2 id="secao-observacoes" class="text-heading-sm text-text">Observações</h2>
            <div class="<?= e($grade) ?>">
                <?= component('textarea', $texto('observacoes', 'Observações internas', 'O cliente não vê este texto.')) ?>
                <?= component('textarea', $texto('observacoes_cliente', 'Observações ao cliente', 'Aparece nas impressões da venda e na área do cliente.')) ?>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('vendas')]) ?>
            <?= component('button', ['label' => $editando ? 'Salvar alterações' : 'Criar venda', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Salvando…']]) ?>
        </div>
    </form>

    <?php if ($pode['cadastrar_cliente']) { ?>
        <?php $this->load->view('clientes/partes/cadastro-rapido') ?>
    <?php } ?>
</div>
