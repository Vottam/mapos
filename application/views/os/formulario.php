<?php
/**
 * Formulário de OS, para cadastrar e editar os dados (#2842), no padrão dos
 * formulários da v5 (#2851, views/clientes/formulario.php).
 *
 * - POST comum, com o token CSRF; o módulo formulario/padrao valida no
 *   navegador e o servidor valida de novo (Os::formulario()).
 * - Cliente, técnico e termo de garantia são autocompletes (módulo
 *   os/formulario, lib/autocomplete.js): o texto fica no campo visível e o id
 *   escolhido no campo oculto ao lado. Digitar sem escolher da lista deixa o
 *   campo inválido.
 * - Os textos livres são texto simples; a formatação do editor antigo
 *   (Trumbowyg) sai ao salvar, e as quebras de linha ficam.
 * - Produtos, serviços, anexos, anotações, desconto e faturamento ficam na
 *   tela da OS (os/visualizar), linkada no topo ao editar.
 *
 * @var object|null             $os         null ao cadastrar
 * @var array<string, string>   $valores    osValoresDoFormulario()
 * @var array<string, string>   $erros      Por campo; _geral para falha ao salvar
 * @var list<string>            $formatados Campos de texto com formatação do editor antigo
 * @var array<string, bool>     $pode
 * @var object|null              $equipamento
 * @var array<string, string|int> $equipamento_valores
 */
$editando = $os !== null;
$erroGeral = $erros['_geral'] ?? null;
$errosDeCampo = array_diff_key($erros, ['_geral' => true]);

$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$grade = 'mt-4 grid gap-4 sm:grid-cols-2';

/*
 * Autocomplete: input visível (o texto) + oculto (o id), num wrapper que diz
 * ao módulo de onde vêm as opções (data-autocomplete) e qual campo guarda o
 * id (data-autocomplete-alvo).
 */
$urlPath = static fn (string $url): string => (string) (parse_url($url, PHP_URL_PATH) ?: $url);
$equipamentoId = (int) ($equipamento_valores['id'] ?? 0);
$serialSalvo = trim((string) ($equipamento_valores['num_serie'] ?? ''));
$etiquetaSerialUrl = $editando && $equipamentoId > 0
    ? $urlPath(site_url('os/imprimirEtiquetaSerial/' . (int) $os->idOs)) . '?equipamento=' . $equipamentoId
    : null;
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
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8" <?= js_module('os/formulario') ?> data-documento-busca="<?= e($urlPath(site_url('clientes/buscarPorDocumento'))) ?>" data-equipamentos-url="<?= e($urlPath(site_url('equipamentos/porCliente'))) ?>" data-historico-url="<?= e($urlPath(site_url('equipamentos/historicoSerial'))) ?>" data-os-id="<?= e((string) ($os->idOs ?? 0)) ?>">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display text-heading-xl text-text"><?= e($editando ? 'Editar OS #' . $os->idOs : 'Nova OS') ?></h1>
                <?php if ($editando) { ?>
                    <?= component('pill-status', osStatusPill($os->status)) ?>
                <?php } ?>
            </div>
            <p class="text-caption text-muted"><?= e($editando ? $os->nomeCliente : 'Os produtos e serviços são adicionados depois de criar a OS.') ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($pode['ver_os']) { ?>
                <?= component('button', [
                    'label' => 'Ver OS, itens e faturamento',
                    'icon' => 'eye',
                    'variant' => 'outline',
                    'href' => site_url('os/visualizar/' . (int) $os->idOs),
                ]) ?>
            <?php } ?>
        </div>
    </header>

    <?php if ($erroGeral !== null) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erroGeral]) ?>
    <?php } elseif ($errosDeCampo !== []) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => count($errosDeCampo) === 1 ? 'Confira o campo destacado.' : 'Confira os ' . count($errosDeCampo) . ' campos destacados.']) ?>
    <?php } ?>

    <form method="post" action="<?= e(current_url()) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-responsaveis">
            <h2 id="secao-responsaveis" class="text-heading-sm text-text">Cliente e responsável</h2>
            <div class="<?= e($grade) ?>">
                <div class="sm:col-span-2 rounded-lg border border-border-subtle bg-surface-subtle p-3" data-os-documento>
                    <?= component('input', [
                        'name' => 'cliente_documento',
                        'label' => 'CPF ou CNPJ do cliente',
                        'value' => $valores['cliente_documento'] !== '' ? $valores['cliente_documento'] : null,
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
                    'data-autocomplete-novo' => $urlPath(site_url('clientes/adicionar')),
                    'data-autocomplete-novo-modal' => 'cliente-rapido',
                    'data-autocomplete-novo-rotulo' => 'Cadastrar novo cliente',
                ] : [])) ?>>
                    <?= component('input', $visivel('cliente', [
                        'label' => 'Cliente',
                        'required' => true,
                        'placeholder' => 'Nome, documento ou telefone',
                        'attrs' => [
                            'autofocus' => ! $editando,
                            'data-msg-vazio' => 'Escolha o cliente da OS.',
                            'data-msg-invalido' => 'Escolha um cliente da lista.',
                        ],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('clientes_id')) ?>>
                </div>
                <div<?= componenteAtributos($wrapper(site_url('os/autoCompleteUsuario'), 'usuarios_id')) ?>>
                    <?= component('input', $visivel('tecnico', [
                        'label' => 'Técnico responsável',
                        'required' => true,
                        'placeholder' => 'Nome do técnico',
                        'attrs' => [
                            'data-msg-vazio' => 'Escolha o técnico responsável.',
                            'data-msg-invalido' => 'Escolha um técnico da lista.',
                        ],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('usuarios_id')) ?>>
                </div>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-situacao">
            <h2 id="secao-situacao" class="text-heading-sm text-text">Situação e prazos</h2>
            <div class="<?= e($grade) ?>">
                <?= component('select', [
                    'name' => 'status',
                    'label' => 'Status',
                    'required' => true,
                    'options' => $statusOpcoes,
                    'selected' => $valores['status'] !== '' ? $valores['status'] : null,
                    'error' => $errosDeCampo['status'] ?? null,
                    'help' => $editando ? 'Cancelar a OS devolve os produtos ao estoque.' : null,
                ]) ?>
                <div class="hidden sm:block" aria-hidden="true"></div>
                <?= component('input', [
                    'name' => 'dataInicial',
                    'label' => 'Data inicial',
                    'type' => 'date',
                    'required' => true,
                    'value' => $valores['dataInicial'] !== '' ? $valores['dataInicial'] : null,
                    'error' => $errosDeCampo['dataInicial'] ?? null,
                    'attrs' => ['data-msg-vazio' => 'Informe a data inicial.'],
                ]) ?>
                <?= component('input', [
                    'name' => 'dataFinal',
                    'label' => 'Data final',
                    'type' => 'date',
                    'required' => true,
                    'value' => $valores['dataFinal'] !== '' ? $valores['dataFinal'] : null,
                    'error' => $errosDeCampo['dataFinal'] ?? null,
                    'help' => 'Previsão de entrega ao cliente.',
                    'attrs' => ['data-msg-vazio' => 'Informe a data final.'],
                ]) ?>
                <?= component('input', [
                    'name' => 'garantia',
                    'label' => 'Garantia (dias)',
                    'type' => 'number',
                    'value' => $valores['garantia'] !== '' ? $valores['garantia'] : null,
                    'error' => $errosDeCampo['garantia'] ?? null,
                    'help' => 'Em branco ou 0 quando não há garantia.',
                    'attrs' => ['min' => 0, 'max' => 9999, 'step' => 1, 'inputmode' => 'numeric', 'data-msg-invalido' => 'Informe a garantia em dias, de 0 a 9999.'],
                ]) ?>
                <div<?= componenteAtributos($wrapper($urlPath(site_url('os/autoCompleteTermoGarantia')), 'garantias_id', ['data-autocomplete-min' => '1'])) ?>>
                    <?= component('input', $visivel('termoGarantia', [
                        'label' => 'Termo de garantia',
                        'placeholder' => 'Opcional',
                        'help' => 'Texto impresso no termo de garantia da OS.',
                        'attrs' => ['data-msg-invalido' => 'Escolha um termo da lista ou deixe o campo em branco.'],
                    ])) ?>
                    <input<?= componenteAtributos($oculto('garantias_id')) ?>>
                </div>
                <div class="sm:col-span-2 rounded-lg border border-border-subtle bg-surface-subtle p-3" data-garantia-retorno>
                    <label class="flex items-start gap-2 text-caption text-text">
                        <input type="checkbox" name="garantia_retorno" value="1" class="mt-1" data-garantia-retorno-toggle <?= ($valores['garantia_retorno'] ?? '') === '1' ? 'checked' : '' ?>>
                        <span><strong>OS em garantia / Retorno de garantia</strong><br><span class="text-muted">Somente marque após revisar o histórico e decidir manualmente.</span></span>
                    </label>
                    <input type="hidden" name="garantia_origem_os_id" value="<?= e($valores['garantia_origem_os_id'] ?? '') ?>" data-garantia-origem>
                    <p class="mt-2 hidden text-caption text-warning" data-garantia-origem-info></p>
                    <?php if (isset($errosDeCampo['garantia_retorno'])) { ?>
                        <p class="mt-2 text-caption text-danger"><?= e($errosDeCampo['garantia_retorno']) ?></p>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-equipamento">
            <h2 id="secao-equipamento" class="text-heading-sm text-text">Equipamento e serviço</h2>
            <p class="mt-1 text-caption text-muted">Vincule um equipamento existente do cliente ou informe os dados para cadastrar um novo. O vínculo não altera status, estoque ou faturamento.</p>
            <div class="<?= e($grade) ?>">
                <?= component('select', [
                    'name' => 'equipamento_id',
                    'label' => 'Equipamento cadastrado',
                    'placeholder' => 'Novo equipamento ou nenhum',
                    'options' => $equipamento_valores['id'] > 0 ? [(string) $equipamento_valores['id'] => trim(implode(' · ', array_filter([$equipamento_valores['equipamento'], $equipamento_valores['modelo'], $equipamento_valores['num_serie']]))) ] : [],
                    'selected' => $equipamento_valores['id'] > 0 ? (string) $equipamento_valores['id'] : null,
                    'attrs' => ['data-equipamento-select' => true, 'data-msg-invalido' => 'Escolha um equipamento da lista ou deixe em novo.'],
                ]) ?>
                <div class="flex items-end text-caption text-muted" data-equipamento-status aria-live="polite"></div>
                <?= component('input', ['name' => 'equipamento', 'label' => 'Tipo de equipamento', 'value' => $equipamento_valores['equipamento'] !== '' ? $equipamento_valores['equipamento'] : null, 'attrs' => ['maxlength' => 150, 'data-equipamento-campo' => 'equipamento']]) ?>
                <?= component('input', ['name' => 'equipamento_marca', 'label' => 'Marca', 'value' => $equipamento_valores['marca'] !== '' ? $equipamento_valores['marca'] : null, 'attrs' => ['maxlength' => 100, 'data-equipamento-campo' => 'marca']]) ?>
                <?= component('input', ['name' => 'equipamento_modelo', 'label' => 'Modelo', 'value' => $equipamento_valores['modelo'] !== '' ? $equipamento_valores['modelo'] : null, 'attrs' => ['maxlength' => 80, 'data-equipamento-campo' => 'modelo']]) ?>
                <div class="flex flex-col gap-2">
                    <?= component('input', ['name' => 'equipamento_num_serie', 'label' => 'Número de série', 'value' => $equipamento_valores['num_serie'] !== '' ? $equipamento_valores['num_serie'] : null, 'attrs' => ['maxlength' => 80, 'data-equipamento-campo' => 'num_serie', 'data-equipamento-serial' => true]]) ?>
                    <?php if ($etiquetaSerialUrl !== null && $serialSalvo !== '') { ?>
                        <?= component('button', [
                            'label' => 'IMPRIMIR ETIQUETA DO SERIAL',
                            'icon' => 'barcode',
                            'variant' => 'outline',
                            'href' => $etiquetaSerialUrl,
                            'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
                        ]) ?>
                    <?php } elseif ($editando) { ?>
                        <p class="text-caption text-muted">A etiqueta ficará disponível depois que o equipamento e o número de série forem salvos.</p>
                    <?php } else { ?>
                        <p class="text-caption text-muted">Salve a OS para gerar o serial definitivo e liberar a etiqueta.</p>
                    <?php } ?>
                </div>
                <?php if ($equipamento !== null) { ?>
                    <p class="text-caption text-muted sm:col-span-2">Equipamento atual: <?= component('link', ['label' => 'ver cadastro #' . (string) $equipamento->idEquipamentos, 'href' => site_url('equipamentos/visualizar/' . (int) $equipamento->idEquipamentos)]) ?></p>
                <?php } ?>
                <div class="sm:col-span-2" data-equipamento-historico aria-live="polite"></div>
                <?= component('textarea', $texto('descricaoProduto', 'Descrição do produto ou serviço', 'Equipamento, acessórios e contexto do atendimento.')) ?>
                <?= component('textarea', $texto('defeito', 'Defeito', 'O que o cliente relatou.')) ?>
                <?= component('textarea', $texto('observacoes', 'Observações')) ?>
                <?= component('textarea', $texto('laudoTecnico', 'Laudo técnico', 'Diagnóstico e o que foi feito.')) ?>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('os')]) ?>
            <?= component('button', ['label' => $editando ? 'Salvar alterações' : 'Criar OS', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Salvando…']]) ?>
        </div>
    </form>

    <?php if ($pode['cadastrar_cliente']) { ?>
        <?php $this->load->view('clientes/partes/cadastro-rapido') ?>
    <?php } ?>
</div>
