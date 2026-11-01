<?php
/**
 * Listagem de ordens de serviço (#2842), no padrão das listagens da v5 (#2852,
 * ver views/clientes/clientes.php): filtros em GET, data-table que vira
 * cartões abaixo de 640px, empty-state para "nada cadastrado" e "nada
 * encontrado", paginação que mantém os filtros, "Nova OS" na topbar e exclusão
 * com um modal-confirm só, preenchido pelo gatilho da linha (data-valor-*).
 *
 * @var list<object>          $results
 * @var array<string, string> $filtros
 * @var int                   $total
 * @var bool                  $status_ocultos  Sem filtro, só os status marcados em Configurações aparecem
 * @var bool                  $controle_editos OS faturada/cancelada continua editável
 * @var array                 $paginacao
 * @var array{adicionar: bool, editar: bool, excluir: bool} $pode
 */
$temFiltro = $filtros !== [];
$osUrl = static fn (string $path): string => rtrim((string) (parse_url(site_url(), PHP_URL_PATH) ?: '/index.php'), '/') . '/' . ltrim($path, '/');

// Data abreviada somente para apresentação na listagem (DD/MM/AA), sem alterar o banco.
$dataAbreviada = static function (?string $data): string {
    $data = trim((string) $data);
    if ($data === '') {
        return '';
    }

    // osGarantiaPill() devolve DD/MM/AAAA; encurta sem reparsear a data.
    if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $data)) {
        return substr($data, 0, 6) . substr($data, 8);
    }

    $formatada = dataBr($data);
    return $formatada !== '' ? substr($formatada, 0, 6) . substr($formatada, 8) : '';
};

// Celula de equipamento com truncamento visual e tooltip com o valor integral.
$equipamentoCelula = static function (?string $valor): HtmlSeguro {
    $texto = $valor ?: '—';
    return new HtmlSeguro('<span class="os-col-equipamento" title="' . e($texto) . '">' . e($texto) . '</span>');
};

$colunas = [
    ['id' => 'idOs', 'key' => 'idOs', 'label' => 'Nº', 'selector_label' => 'Nº', 'align' => 'right', 'nowrap' => true, 'default_visible' => true, 'always_visible' => true],
    ['id' => 'cliente', 'label' => 'Cliente', 'selector_label' => 'Cliente', 'class' => 'os-col-cliente', 'default_visible' => true, 'render' => fn ($o) => $o->nomeCliente !== null
        ? component('link', ['label' => nomeClienteExibicao($o->nomeCliente), 'href' => $osUrl('clientes/visualizar/' . $o->clientes_id), 'class' => 'font-medium', 'attrs' => ['title' => nomeClienteExibicao($o->nomeCliente)]])
        : 'Cliente removido'],
    ['id' => 'tipo', 'label' => 'Tipo', 'selector_label' => 'Tipo', 'class' => 'os-col-tipo', 'default_visible' => true, 'render' => fn ($o) => $equipamentoCelula($o->equipamento_tipo)],
    ['id' => 'marca', 'label' => 'Marca', 'selector_label' => 'Marca', 'class' => 'os-col-marca', 'default_visible' => true, 'render' => fn ($o) => $equipamentoCelula($o->equipamento_marca)],
    ['id' => 'modelo', 'label' => 'Modelo', 'selector_label' => 'Modelo', 'class' => 'os-col-modelo', 'default_visible' => true, 'render' => fn ($o) => $equipamentoCelula($o->equipamento_modelo)],
    ['id' => 'responsavel', 'key' => 'responsavel', 'label' => 'Responsável', 'selector_label' => 'Responsável', 'class' => 'os-col-responsavel', 'nowrap' => true, 'default_visible' => false],
    ['id' => 'data_inicial', 'label' => 'Data Inicial', 'selector_label' => 'Data Inicial', 'class' => 'os-col-data', 'align' => 'right', 'nowrap' => true, 'default_visible' => true, 'render' => fn ($o) => $dataAbreviada($o->dataInicial)],
    ['id' => 'data_final', 'label' => 'Data Final', 'selector_label' => 'Data Final', 'class' => 'os-col-data', 'align' => 'right', 'nowrap' => true, 'default_visible' => true, 'render' => fn ($o) => $dataAbreviada($o->dataFinal)],
    ['id' => 'vencimento_garantia', 'label' => 'Venc. Garantia', 'selector_label' => 'Venc. Garantia', 'class' => 'os-col-garantia', 'align' => 'right', 'nowrap' => true, 'default_visible' => true, 'render' => static function ($o) use ($dataAbreviada) {
        $pill = osGarantiaPill($o->dataFinal ?? null, $o->garantia ?? null);
        $label = $pill['label'];
        if ($label === 'Sem garantia') {
            $pill['label'] = 'Sem gar.';
            $pill['attrs'] = ['title' => 'Sem garantia'];
        } else {
            $pill['label'] = $dataAbreviada($label);
            $pill['attrs'] = ['title' => $label];
        }
        return component('pill-status', $pill);
    }],
    ['id' => 'valor_total', 'label' => 'Valor Total', 'selector_label' => 'Valor Total', 'class' => 'os-col-financeiro', 'align' => 'right', 'nowrap' => true, 'default_visible' => true, 'render' => fn ($o) => dinheiro((float) $o->total)],
    ['id' => 'desconto', 'label' => 'Desconto', 'selector_label' => 'Desconto', 'class' => 'os-col-financeiro', 'align' => 'right', 'nowrap' => true, 'default_visible' => false, 'render' => fn ($o) => dinheiro((float) ($o->desconto ?? 0))],
    ['id' => 'valor_com_desconto', 'label' => 'Valor c/ Desconto', 'selector_label' => 'Valor c/ Desconto', 'class' => 'os-col-financeiro', 'align' => 'right', 'nowrap' => true, 'default_visible' => false, 'render' => fn ($o) => (float) ($o->valor_desconto ?? 0) > 0 ? dinheiro((float) $o->valor_desconto) : '—'],
    ['id' => 'vt_faturado', 'label' => 'V.T. Faturado', 'selector_label' => 'V.T. Faturado', 'class' => 'os-col-financeiro', 'align' => 'right', 'nowrap' => true, 'default_visible' => true, 'render' => static function ($o) {
        if ((int) $o->faturado !== 1) {
            return component('pill-status', ['label' => 'Sem faturamento', 'variant' => 'neutral', 'dot' => false, 'class' => 'os-financial-pill']);
        }
        return dinheiro((float) ($o->vtFaturado ?? 0));
    }],
    ['id' => 'valor_liquido', 'label' => 'Valor Líquido', 'selector_label' => 'Valor Líquido', 'class' => 'os-col-financeiro', 'align' => 'right', 'nowrap' => true, 'default_visible' => true, 'render' => static function ($o) {
        $valor = dinheiro((float) ($o->valorLiquido ?? 0));
        return component('pill-status', ['label' => $valor, 'variant' => (int) $o->faturado === 1 ? 'success' : 'neutral', 'dot' => false, 'class' => 'os-financial-pill']);
    }],
    ['id' => 'status', 'label' => 'Status', 'selector_label' => 'Status', 'class' => 'os-col-status', 'nowrap' => true, 'default_visible' => true, 'render' => fn ($o) => component('pill-status', osStatusListPill($o->status))],
    ['id' => 'acoes', 'label' => 'Ações', 'selector_label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'class' => 'os-acoes-cell', 'default_visible' => true, 'always_visible' => true, 'render' => function ($o) use ($pode, $controle_editos, $osUrl) {
        $numero = 'OS ' . $o->idOs;
        $editavel = osEditavel($o, $pode['editar'], $controle_editos);
        $acoes = [
            component('button', ['label' => 'Ver ' . $numero, 'icon' => 'eye', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => $osUrl('os/visualizar/' . $o->idOs)]),
            component('button', ['label' => 'Imprimir A4 ' . $numero, 'icon' => 'printer', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => $osUrl('os/imprimir/' . $o->idOs), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]),
            component('button', ['label' => 'Imprimir térmica ' . $numero, 'icon' => 'receipt', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => $osUrl('os/imprimirTermica/' . $o->idOs), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]),
            component('button', ['label' => 'Etiqueta 40x25 ' . $numero, 'icon' => 'barcode', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => $osUrl('os/imprimirEtiqueta/' . $o->idOs), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]),
        ];
        if ($editavel) {
            $acoes[] = component('button', ['label' => 'Editar ' . $numero, 'icon' => 'pencil', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => $osUrl('os/editar/' . $o->idOs)]);
        }
        // Como na v4: excluir exige dOs e uma OS que ainda pode ser editada.
        if ($pode['excluir'] && $editavel) {
            $acoes[] = component('button', ['label' => 'Excluir ' . $numero, 'icon' => 'trash-2', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'class' => 'hover:text-danger-ink', 'attrs' => [
                'data-modal-abrir' => 'excluir-os',
                'data-valor-id' => (string) $o->idOs,
                'data-valor-numero' => (string) $o->idOs,
                'data-valor-cliente' => nomeClienteExibicao($o->nomeCliente ?? 'cliente removido'),
            ]]);
        }

        return $acoes;
    }],
];

$colunaIds = array_values(array_map(static fn (array $coluna): string => $coluna['id'], $colunas));
$colunasPadrao = array_values(array_map(static fn (array $coluna): string => $coluna['id'], array_filter($colunas, static fn (array $coluna): bool => ! empty($coluna['default_visible']))));

$vazio = $temFiltro
    ? component('empty-state', [
        'title' => 'Nenhuma OS encontrada',
        'message' => 'Nada corresponde aos filtros. Confira a busca ou limpe os filtros.',
        'icon' => 'search-x',
        'action' => component('button', ['label' => 'Limpar filtros', 'variant' => 'outline', 'href' => $osUrl('os')]),
        'class' => 'border-0',
    ])
    : component('empty-state', [
        'title' => $status_ocultos ? 'Nenhuma OS nos status exibidos' : 'Nenhuma OS cadastrada',
        'message' => $status_ocultos
            ? 'A listagem mostra só os status marcados em Configurações. Filtre por outro status para ver as demais.'
            : 'As ordens de serviço cadastradas aparecem aqui.',
        'icon' => 'file-text',
        'action' => $pode['adicionar'] ? component('button', ['label' => 'Cadastrar OS', 'icon' => 'plus', 'variant' => 'outline', 'href' => $osUrl('os/adicionar')]) : null,
        'class' => 'border-0',
    ]);

$resumo = ($total === 1 ? '1 ordem de serviço' : number_format($total, 0, ',', '.') . ' ordens de serviço')
    . ($temFiltro ? ($total === 1 ? ' encontrada' : ' encontradas') . ' com os filtros' : '')
    . ($status_ocultos ? ' nos status marcados em Configurações' : '');
?>
<div class="os-listagem flex flex-col gap-4 pt-2 pb-8" <?= js_module('os/colunas') ?> data-os-coluna-ids="<?= e(json_encode($colunaIds, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>" data-os-colunas-padrao="<?= e(json_encode($colunasPadrao, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>" data-os-storage-key="mapos:v5:os:listagem:colunas:v2">
    <header>
        <h1 class="font-display text-heading-xl text-text">Ordens de serviço</h1>
        <p class="text-caption text-muted" aria-live="polite"><?= e($resumo) ?></p>
    </header>

    <form method="get" action="<?= e($osUrl('os')) ?>" role="search" aria-label="Filtrar ordens de serviço" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_12rem_10rem_10rem_auto]">
        <?= component('input', [
            'name' => 'pesquisa',
            'label' => 'Buscar',
            'type' => 'search',
            'value' => $filtros['pesquisa'] ?? null,
            'placeholder' => 'Nº, cliente, documento ou equipamento',
            'attrs' => ['maxlength' => 100],
        ]) ?>
        <?= component('select', [
            'name' => 'status',
            'label' => 'Status',
            'placeholder' => 'Todos',
            'options' => array_combine(array_keys(OS_STATUS_VARIANTES), array_keys(OS_STATUS_VARIANTES)),
            'selected' => $filtros['status'] ?? null,
        ]) ?>
        <?= component('input', [
            'name' => 'de',
            'label' => 'Entrada a partir de',
            'type' => 'date',
            'value' => $filtros['de'] ?? null,
        ]) ?>
        <?= component('input', [
            'name' => 'ate',
            'label' => 'Entrega até',
            'type' => 'date',
            'value' => $filtros['ate'] ?? null,
        ]) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Filtrar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if ($temFiltro) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => $osUrl('os')]) ?>
            <?php } ?>
        </div>
    </form>

    <div class="os-colunas-barra flex items-center justify-end gap-3">
        <p class="sr-only" data-os-colunas-status aria-live="polite"></p>
        <div class="os-colunas relative" data-os-colunas>
            <button type="button" class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-md border border-hairline-cool bg-transparent px-3 py-2 text-button-cap-light uppercase text-text hover:bg-surface-subtle focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-ring" data-os-colunas-toggle aria-expanded="false" aria-controls="os-colunas-painel">
                <?= icon('settings', ['class' => 'size-4']) ?>
                <span>Colunas</span>
                <?= icon('chevron-down', ['class' => 'size-4']) ?>
            </button>
            <div id="os-colunas-painel" class="os-colunas-painel absolute right-0 z-20 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-border bg-surface p-4 text-left shadow-overlay" role="region" aria-label="Colunas visíveis" hidden>
                <fieldset class="flex flex-col gap-1">
                    <legend class="mb-2 text-sm font-semibold text-text">Colunas visíveis</legend>
                    <?php foreach ($colunas as $coluna) { ?>
                        <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-text hover:bg-surface-subtle">
                            <input id="os-coluna-<?= e($coluna['id']) ?>" type="checkbox" data-os-coluna="<?= e($coluna['id']) ?>"<?= ! empty($coluna['default_visible']) ? ' checked' : '' ?><?= ! empty($coluna['always_visible']) ? ' disabled' : '' ?> class="size-4 accent-primary focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-ring">
                            <span><?= e($coluna['selector_label']) ?><?= ! empty($coluna['always_visible']) ? ' (fixa)' : '' ?></span>
                        </label>
                    <?php } ?>
                </fieldset>
                <div class="mt-3 flex items-center justify-between gap-2 border-t border-border pt-3">
                    <span class="text-caption text-muted">Nº e Ações ficam sempre disponíveis.</span>
                    <?= component('button', ['label' => 'Restaurar padrão', 'variant' => 'ghost', 'size' => 'sm', 'attrs' => ['type' => 'button', 'data-os-restaurar' => true]]) ?>
                </div>
            </div>
        </div>
    </div>

    <?= component('data-table', [
        'columns' => $colunas,
        'rows' => $results,
        'empty' => $vazio,
        'caption' => 'Ordens de serviço',
        'dense' => true,
        'class' => 'os-listagem-tabela',
    ]) ?>

    <?= component('pagination', $paginacao) ?>
</div>

<?php if ($pode['excluir']) { ?>
    <form id="form-excluir-os" method="post" action="<?= e($osUrl('os/excluir') . listagemQuery($filtros)) ?>" hidden>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="id" value="" data-modal-de="excluir-os" data-modal-valor="id">
    </form>
    <?= component('modal-confirm', [
        'id' => 'excluir-os',
        'title' => 'Excluir OS?',
        // Nº e cliente entram pelo modal.js (textContent) a partir do
        // data-valor-* do botão da linha; aqui só os marcadores vazios.
        'message' => [
            'A OS ',
            new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="numero"></strong>'),
            ' de ',
            new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="cliente"></strong>'),
            ' será removida com os produtos, serviços, anexos e anotações. O estoque dos produtos volta e a fatura, se houver, é excluída. Essa ação não pode ser desfeita.',
        ],
        'confirm_label' => 'Excluir',
        'confirm_attrs' => ['form' => 'form-excluir-os'],
    ]) ?>
<?php } ?>
