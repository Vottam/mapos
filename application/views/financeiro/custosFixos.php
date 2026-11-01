<?php
/**
 * Custos fixos: contratos e contas a pagar por vencimento.
 * A consulta só lê competências; geração e alterações são ações POST explícitas.
 * @var list<object> $results
 * @var list<object> $competencias
 * @var array $podeCustos
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$filtros = $filtrosCustos ?? ['categoria' => '', 'status' => ''];
$podeCustos += ['adicionar' => false, 'editar' => false, 'status' => false, 'excluir' => false];
$csrfNome = $this->security->get_csrf_token_name();
$csrfHash = $this->security->get_csrf_hash();
$periodoRotulo = $periodoDe === $periodoAte
    ? dataBr($periodoDe)
    : dataBr($periodoDe) . ' a ' . dataBr($periodoAte);
$mostrarData = static function ($data): string {
    $data = (string) ($data ?? '');
    return $data !== '' && $data !== '0000-00-00' ? dataBr($data) : '—';
};

$contratoColunas = [
    ['label' => 'Título', 'class' => 'min-w-44', 'render' => fn ($c) => (string) $c->titulo],
    ['label' => 'Categoria', 'hide_until' => 'lg', 'render' => fn ($c) => (string) $c->categoria],
    ['label' => 'Valor padrão', 'align' => 'right', 'nowrap' => true, 'render' => fn ($c) => dinheiro((float) $c->valor)],
    ['label' => 'Periodicidade', 'nowrap' => true, 'hide_until' => 'xl', 'render' => fn ($c) => ucfirst((string) $c->periodicidade)],
    ['label' => 'Vencimento', 'align' => 'right', 'nowrap' => true, 'hide_until' => 'xl', 'render' => fn ($c) => (int) $c->dia_vencimento . ' · ' . ((string) ($c->regra_vencimento ?? 'mes_vencido') === 'mes_corrente' ? 'mês corrente' : 'mês vencido')],
    ['label' => 'Status', 'nowrap' => true, 'render' => fn ($c) => component('pill-status', (int) $c->ativo === 1 ? ['label' => 'Ativo', 'variant' => 'success'] : ['label' => 'Inativo', 'variant' => 'neutral'])],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => function ($c) use ($podeCustos) {
        $acoes = [component('button', ['label' => 'Abrir competências de ' . $c->titulo, 'icon' => 'calendar-days', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('financeiro/competenciasCustoFixo/' . (int) $c->idCustoFixo)])];
        if ($podeCustos['editar']) {
            $acoes[] = component('button', ['label' => 'Editar ' . $c->titulo, 'icon' => 'pencil', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('financeiro/editarCustoFixo/' . (int) $c->idCustoFixo)]);
        }
        if ($podeCustos['status']) {
            $ativo = (int) $c->ativo === 1;
            $acoes[] = component('button', ['label' => ($ativo ? 'Inativar ' : 'Ativar ') . $c->titulo, 'icon' => $ativo ? 'toggle-left' : 'toggle-right', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'class' => $ativo ? 'text-danger-ink' : 'text-success-ink', 'attrs' => ['data-modal-abrir' => 'alternar-custo', 'data-valor-id' => (string) $c->idCustoFixo, 'data-valor-titulo' => (string) $c->titulo, 'data-valor-acao' => $ativo ? 'inativado' : 'ativado']]);
        }
        if ($podeCustos['excluir']) {
            $acoes[] = component('button', ['label' => 'Excluir ' . $c->titulo, 'icon' => 'trash-2', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'class' => 'hover:text-danger-ink', 'attrs' => ['data-modal-abrir' => 'excluir-custo', 'data-valor-id' => (string) $c->idCustoFixo, 'data-valor-titulo' => (string) $c->titulo]]);
        }

        return $acoes;
    }],
];

$competenciaColunas = [
    ['label' => 'Competência', 'nowrap' => true, 'render' => fn ($c) => dataBr($c->competencia)],
    ['label' => 'Título', 'class' => 'min-w-44', 'render' => fn ($c) => (string) $c->titulo_custo],
    ['label' => 'Categoria', 'hide_until' => 'lg', 'render' => fn ($c) => (string) $c->categoria_custo],
    ['label' => 'Valor', 'align' => 'right', 'nowrap' => true, 'render' => fn ($c) => dinheiro((float) $c->valor)],
    ['label' => 'Vencimento', 'nowrap' => true, 'render' => fn ($c) => $mostrarData($c->data_vencimento)],
    ['label' => 'Status', 'nowrap' => true, 'render' => fn ($c) => component('pill-status', (int) $c->pago === 1 ? ['label' => 'Paga', 'variant' => 'success'] : ['label' => 'Pendente', 'variant' => 'danger'])],
    ['label' => 'Data pagamento', 'nowrap' => true, 'hide_until' => 'lg', 'render' => fn ($c) => $mostrarData($c->data_pagamento)],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => function ($c) use ($podeCustos, $csrfNome, $csrfHash) {
        if (! $podeCustos['editar']) {
            return component('button', ['label' => 'Ver competências de ' . $c->titulo_custo, 'icon' => 'calendar-days', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('financeiro/competenciasCustoFixo/' . (int) $c->idCustoFixo)]);
        }
        $acao = (int) $c->pago === 1 ? 'despagar' : 'pagar';
        $rotulo = $acao === 'pagar' ? 'Marcar paga' : 'Marcar pendente';
        $classe = $acao === 'pagar' ? 'text-success-ink' : 'text-danger-ink';
        $iconePagamento = $acao === 'pagar'
            ? (string) icon('check', ['class' => 'size-4'])
            : (string) icon('history', ['class' => 'size-4']);
        $html = '<div class="inline-flex flex-wrap items-center justify-end gap-1">';
        $html .= '<form method="post" action="' . e(site_url('financeiro/togglePagamentoCompetencia')) . '" class="inline-flex">';
        $html .= '<input type="hidden" name="' . e($csrfNome) . '" value="' . e($csrfHash) . '">';
        $html .= '<input type="hidden" name="idCompetencia" value="' . e((string) $c->idCompetencia) . '">';
        $html .= '<input type="hidden" name="action" value="' . e($acao) . '">';
        $html .= '<input type="hidden" name="custo_fixo_id" value="' . e((string) $c->idCustoFixo) . '">';
        if ($acao === 'pagar') {
            $html .= '<input type="hidden" name="data_pagamento" value="' . e(date('d/m/Y')) . '">';
        }
        $html .= '<button type="submit" class="inline-flex size-9 items-center justify-center rounded-md border border-border text-xs font-semibold hover:bg-surface-subtle max-sm:size-11 ' . e($classe) . '" title="' . e($rotulo) . '" aria-label="' . e($rotulo) . '">' . $iconePagamento . '</button></form>';
        $html .= '<a href="' . e(site_url('financeiro/competenciasCustoFixo/' . (int) $c->idCustoFixo)) . '" class="inline-flex size-9 items-center justify-center rounded-md border border-border text-text hover:bg-surface-subtle max-sm:size-11" title="Ver competências" aria-label="Ver competências">' . (string) icon('calendar-days', ['class' => 'size-4']) . '</a>';
        $html .= '<a href="' . e(site_url('financeiro/editarCustoFixo/' . (int) $c->idCustoFixo)) . '" class="inline-flex size-9 items-center justify-center rounded-md border border-border text-text hover:bg-surface-subtle max-sm:size-11" title="Editar cadastro" aria-label="Editar cadastro">' . (string) icon('pencil', ['class' => 'size-4']) . '</a>';
        $html .= '</div>';

        return new HtmlSeguro($html);
    }],
];
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-display text-heading-xl text-text">Contas a pagar — Vencimentos <?= e($periodoRotulo) ?></h1>
            <p class="text-caption text-muted">Mostra vencimentos do período e contas anteriores ainda pendentes. Competência e vencimento permanecem separados.</p>
        </div>
        <?php if ($podeCustos['adicionar']) { ?>
            <?= component('button', ['label' => 'Novo custo fixo', 'icon' => 'plus', 'href' => site_url('financeiro/adicionarCustoFixo')]) ?>
        <?php } ?>
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <?= component('kpi-card', ['label' => 'Pago', 'value' => dinheiro($totalPago), 'icon' => 'circle-check', 'accent' => 'success', 'class' => 'border-l-2', 'attrs' => ['style' => 'border-left-color:#28a745'], 'caption' => 'Contas pagas exibidas']) ?>
        <?= component('kpi-card', ['label' => 'A pagar', 'value' => dinheiro($totalAPagar), 'icon' => 'banknote', 'accent' => 'warning', 'class' => 'border-l-2 border-danger', 'caption' => 'Pendentes e vencidas']) ?>
        <?= component('kpi-card', ['label' => 'Total', 'value' => dinheiro($totalGeral), 'icon' => 'chart-column', 'class' => 'border-l-2', 'caption' => 'Consolidado da visão operacional']) ?>
    </div>

    <form method="get" action="<?= e(site_url('financeiro/custosFixos')) ?>" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_12rem_10rem_10rem_auto]">
        <?= component('input', ['name' => 'categoria', 'label' => 'Categoria', 'type' => 'search', 'value' => $filtros['categoria'], 'placeholder' => 'Ex.: aluguel']) ?>
        <?= component('select', ['name' => 'status', 'label' => 'Situação do contrato', 'placeholder' => 'Todas', 'options' => ['1' => 'Ativos', '0' => 'Inativos'], 'selected' => $filtros['status']]) ?>
        <?= component('input', ['name' => 'de', 'label' => 'Vencimento de', 'type' => 'date', 'value' => $periodoDe]) ?>
        <?= component('input', ['name' => 'ate', 'label' => 'Vencimento até', 'type' => 'date', 'value' => $periodoAte]) ?>
        <?= component('button', ['label' => 'Filtrar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
    </form>

    <section class="<?= e($caixa) ?>" aria-labelledby="contas-pagar-titulo">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 id="contas-pagar-titulo" class="text-heading-sm text-text">Contas a pagar</h2>
                <p class="text-caption text-muted">Paga: verde. Pendente/vencida: vermelho. Ações por competência.</p>
            </div>
        </div>
        <?= component('data-table', ['columns' => $competenciaColunas, 'rows' => $competencias, 'dense' => true, 'empty' => component('empty-state', ['title' => 'Nenhuma conta no período', 'message' => 'Não há contas vencendo no intervalo ou pendências anteriores.', 'icon' => 'file', 'class' => 'border-0']), 'caption' => 'Contas a pagar por competência']) ?>
    </section>

    <section class="<?= e($caixa) ?>" aria-labelledby="cadastros-titulo">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 id="cadastros-titulo" class="text-heading-sm text-text">Cadastros de custos fixos</h2>
                <p class="text-caption text-muted">Contratos recorrentes, vigência, periodicidade e histórico de competências.</p>
            </div>
        </div>
        <?= component('data-table', ['columns' => $contratoColunas, 'rows' => $results, 'dense' => true, 'empty' => component('empty-state', ['title' => 'Nenhum custo fixo encontrado', 'message' => 'Cadastre um custo fixo ou limpe os filtros.', 'icon' => 'chart-column', 'class' => 'border-0']), 'caption' => 'Cadastros de custos fixos']) ?>
    </section>
</div>

<?php if ($podeCustos['status']) { ?>
<form id="form-alternar-custo" method="post" action="<?= e(site_url('financeiro/alternarStatusCustoFixo')) ?>" hidden>
    <input type="hidden" name="<?= e($csrfNome) ?>" value="<?= e($csrfHash) ?>">
    <input type="hidden" name="idCustoFixo" value="" data-modal-de="alternar-custo" data-modal-valor="id">
</form>
<?= component('modal-confirm', ['id' => 'alternar-custo', 'title' => 'Alterar situação do custo fixo?', 'message' => [new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="titulo"></strong> será <span data-modal-valor="acao"></span>. As competências históricas não serão recriadas.'),], 'confirm_label' => 'Confirmar', 'confirm_attrs' => ['form' => 'form-alternar-custo']]) ?>
<?php } ?>

<?php if ($podeCustos['excluir']) { ?>
<form id="form-excluir-custo" method="post" action="<?= e(site_url('financeiro/excluirCustoFixo')) ?>" hidden>
    <input type="hidden" name="<?= e($csrfNome) ?>" value="<?= e($csrfHash) ?>">
    <input type="hidden" name="id" value="" data-modal-de="excluir-custo" data-modal-valor="id">
</form>
<?= component('modal-confirm', ['id' => 'excluir-custo', 'title' => 'Excluir este custo fixo?', 'message' => [new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="titulo"></strong> e suas competências serão excluídos. Essa ação é irreversível no banco.'),], 'confirm_label' => 'Excluir', 'icon' => 'triangle-alert', 'confirm_attrs' => ['form' => 'form-excluir-custo']]) ?>
<?php } ?>