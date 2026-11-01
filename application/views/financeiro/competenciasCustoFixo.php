<?php
/** @var object $custo */
/** @var list<object> $competencias */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$csrfNome = $this->security->get_csrf_token_name();
$csrfHash = $this->security->get_csrf_hash();
$podeEditar = (bool) ($podeEditar ?? false);
$abrirModalEdicao = (bool) ($abrirModalEdicao ?? false);
$competenciaForm = is_array($competenciaForm ?? null) ? $competenciaForm : [];
$competenciaForm += [
    'competencia' => '',
    'valor_competencia' => '',
    'data_vencimento_competencia' => '',
    'observacoes_competencia' => '',
];
$formCampo = static fn (string $nome): string => (string) ($competenciaForm[$nome] ?? '');
$mostrarData = static function ($data): string {
    $data = (string) ($data ?? '');
    return $data !== '' && $data !== '0000-00-00' ? dataBr($data) : '—';
};
$valorEmCentavos = static function ($valor): int {
    $texto = str_replace(',', '.', trim((string) $valor));
    if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $texto, $partes)) {
        return 0;
    }

    return ((int) $partes[1] * 100) + (int) str_pad($partes[2] ?? '', 2, '0');
};
$totalPagoCentavos = 0;
$totalPendenteCentavos = 0;
foreach ($competencias as $competencia) {
    if ((int) $competencia->pago === 1) {
        $totalPagoCentavos += $valorEmCentavos($competencia->valor);
    } else {
        $totalPendenteCentavos += $valorEmCentavos($competencia->valor);
    }
}
$totalGeralCentavos = $totalPagoCentavos + $totalPendenteCentavos;

$colunas = [
    ['label' => 'Competência', 'nowrap' => true, 'render' => fn ($c) => dataBr($c->competencia)],
    ['label' => 'Vencimento', 'nowrap' => true, 'render' => fn ($c) => $mostrarData($c->data_vencimento)],
    ['label' => 'Valor efetivo', 'align' => 'right', 'nowrap' => true, 'render' => fn ($c) => dinheiro((float) $c->valor)],
    ['label' => 'Situação', 'nowrap' => true, 'render' => fn ($c) => component('pill-status', (int) $c->pago === 1 ? ['label' => 'Paga', 'variant' => 'success'] : ['label' => 'Pendente', 'variant' => 'danger'])],
    ['label' => 'Data pagamento', 'nowrap' => true, 'hide_until' => 'lg', 'render' => fn ($c) => $mostrarData($c->data_pagamento)],
    ['label' => 'Observações', 'hide_until' => 'xl', 'class' => 'min-w-48 [overflow-wrap:anywhere]', 'render' => function ($c) {
        $observacoes = trim((string) ($c->observacoes ?? ''));

        return $observacoes !== '' ? $observacoes : '—';
    }],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => function ($c) use ($podeEditar, $csrfNome, $csrfHash, $idCustoFixo) {
        if (! $podeEditar) {
            return '—';
        }

        $acoes = [];
        $acoes[] = component('button', [
            'label' => 'Editar competência ' . dataBr($c->competencia),
            'icon' => 'pencil',
            'icon_only' => true,
            'variant' => 'ghost',
            'size' => 'sm',
            'attrs' => [
                'data-modal-abrir' => 'editar-competencia',
                'data-valor-competencia' => (string) $c->competencia,
                'data-valor-valor' => number_format((float) $c->valor, 2, '.', ''),
                'data-valor-vencimento' => (string) ($c->data_vencimento ?? ''),
                'data-valor-observacoes' => (string) ($c->observacoes ?? ''),
            ],
        ]);

        if ((int) $c->pago === 1) {
            $rotulo = 'Reverter pagamento de ' . dataBr($c->competencia);
            $html = '<form method="post" action="' . e(site_url('financeiro/togglePagamentoCompetencia')) . '" class="inline-flex">';
            $html .= '<input type="hidden" name="' . e($csrfNome) . '" value="' . e($csrfHash) . '">';
            $html .= '<input type="hidden" name="idCompetencia" value="' . e((string) $c->idCompetencia) . '">';
            $html .= '<input type="hidden" name="action" value="despagar">';
            $html .= '<input type="hidden" name="custo_fixo_id" value="' . e((string) $idCustoFixo) . '">';
            $html .= '<button type="submit" class="inline-flex size-9 items-center justify-center rounded-md border border-border text-danger-ink hover:bg-surface-subtle max-sm:size-11" title="' . e($rotulo) . '" aria-label="' . e($rotulo) . '">' . (string) icon('history', ['class' => 'size-4']) . '</button></form>';
            $acoes[] = new HtmlSeguro($html);
        } else {
            $acoes[] = component('button', [
                'label' => 'Marcar competência ' . dataBr($c->competencia) . ' como paga',
                'icon' => 'check',
                'icon_only' => true,
                'variant' => 'ghost',
                'size' => 'sm',
                'class' => 'text-success-ink',
                'attrs' => [
                    'data-modal-abrir' => 'modal-pagamento-competencia',
                    'data-valor-id' => (string) $c->idCompetencia,
                    'data-valor-label' => dataBr($c->competencia),
                ],
            ]);
        }

        return $acoes;
    }],
];
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-caption text-muted"><a class="underline" href="<?= e(site_url('financeiro/custosFixos')) ?>">Custos fixos</a> / <?= e($custo->titulo) ?></p>
            <h1 class="mt-1 font-display text-heading-xl text-text">Competências do contrato</h1>
            <p class="text-caption text-muted"><?= e($custo->categoria) ?> · <?= e(dinheiro((float) $custo->valor)) ?> padrão · <?= (int) $custo->ativo === 1 ? 'ativo' : 'inativo' ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($podeEditar) { ?>
                <?= component('button', ['label' => 'Editar cadastro', 'icon' => 'pencil', 'variant' => 'outline', 'href' => site_url('financeiro/editarCustoFixo/' . (int) $idCustoFixo)]) ?>
            <?php } ?>
            <?= component('button', ['label' => 'Voltar', 'icon' => 'chevron-left', 'variant' => 'ghost', 'href' => site_url('financeiro/custosFixos')]) ?>
        </div>
    </header>

    <?php if (isset($errosCustos['_geral']) && ! $abrirModalEdicao) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $errosCustos['_geral']]) ?>
    <?php } ?>

    <section class="<?= e($caixa) ?>">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-caption text-muted">Valor padrão</p><p class="text-heading-sm text-text"><?= e(dinheiro((float) $custo->valor)) ?></p></div>
            <div><p class="text-caption text-muted">Vigência</p><p class="text-body-md text-text"><?= e($mostrarData($custo->data_inicio)) ?> a <?= e($mostrarData($custo->data_fim)) ?></p></div>
            <div><p class="text-caption text-muted">Periodicidade</p><p class="text-body-md text-text"><?= e(ucfirst((string) $custo->periodicidade)) ?></p></div>
            <div><p class="text-caption text-muted">Projeção</p><p class="text-body-md text-text">Competências existentes na janela LIVE</p></div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <?= component('kpi-card', ['label' => 'Pago', 'value' => dinheiro($totalPagoCentavos / 100), 'icon' => 'circle-check', 'caption' => 'Competências marcadas como pagas']) ?>
        <?= component('kpi-card', ['label' => 'A pagar', 'value' => dinheiro($totalPendenteCentavos / 100), 'icon' => 'banknote', 'caption' => 'Competências pendentes']) ?>
        <?= component('kpi-card', ['label' => 'Total previsto', 'value' => dinheiro($totalGeralCentavos / 100), 'icon' => 'chart-column', 'caption' => 'Pago + pendente na projeção']) ?>
    </div>

    <section class="<?= e($caixa) ?>">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-heading-sm text-text">Competências individuais</h2>
                <p class="text-caption text-muted">Cada competência preserva seu valor efetivo, vencimento, pagamento e observações. Use o lápis para editar uma competência sem expor campos em todas as linhas.</p>
            </div>
        </div>
        <?= component('data-table', ['columns' => $colunas, 'rows' => $competencias, 'dense' => true, 'empty' => component('empty-state', ['title' => 'Nenhuma competência cadastrada', 'message' => 'Use o formulário abaixo para criar uma competência adicional.', 'icon' => 'file', 'class' => 'border-0']), 'caption' => 'Competências do custo fixo ' . $custo->titulo]) ?>
    </section>

    <?php if ($podeEditar) { ?>
    <section class="<?= e($caixa) ?>">
        <h2 class="text-heading-sm text-text">Nova competência ou atualização</h2>
        <p class="mt-1 text-caption text-muted">A competência é normalizada para o primeiro dia do mês. Se já existir, o valor/vencimento/observações são atualizados sem duplicar a linha.</p>
        <form method="post" action="<?= e(site_url('financeiro/competenciasCustoFixo/' . (int) $idCustoFixo)) ?>" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="hidden" name="<?= e($csrfNome) ?>" value="<?= e($csrfHash) ?>">
            <input type="hidden" name="salvar_competencia" value="1">
            <input type="hidden" name="origem_competencia" value="nova">
            <?= component('input', ['name' => 'competencia', 'label' => 'Competência', 'type' => 'date', 'value' => $abrirModalEdicao ? '' : $formCampo('competencia'), 'required' => true]) ?>
            <?= component('input', ['name' => 'valor_competencia', 'label' => 'Valor efetivo', 'value' => $abrirModalEdicao ? '' : $formCampo('valor_competencia'), 'required' => true, 'attrs' => ['inputmode' => 'decimal', 'placeholder' => '0,00']]) ?>
            <?= component('input', ['name' => 'data_vencimento_competencia', 'label' => 'Vencimento', 'type' => 'date', 'value' => $abrirModalEdicao ? '' : $formCampo('data_vencimento_competencia')]) ?>
            <?= component('input', ['name' => 'observacoes_competencia', 'label' => 'Observações', 'value' => $abrirModalEdicao ? '' : $formCampo('observacoes_competencia'), 'attrs' => ['maxlength' => 1000]]) ?>
            <div class="sm:col-span-2 lg:col-span-4"><?= component('button', ['label' => 'Salvar competência', 'icon' => 'save', 'type' => 'submit']) ?></div>
        </form>
    </section>
    <?php } ?>
</div>

<?php if ($podeEditar) { ?>
<dialog id="editar-competencia" data-module="componentes/modal" data-modal-aberto="<?= $abrirModalEdicao ? 'true' : 'false' ?>" aria-labelledby="editar-competencia-titulo" class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-xl border border-border bg-surface p-0 text-text shadow-overlay backdrop:bg-backdrop backdrop:backdrop-blur-[1px]" style="width:calc(100% - 2rem);max-width:42rem;">
    <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
        <h2 id="editar-competencia-titulo" class="text-lg leading-snug font-semibold text-text">Editar competência</h2>
        <button type="button" data-modal-fechar class="-m-1 inline-flex size-8 items-center justify-center rounded-md text-muted hover:bg-surface-subtle hover:text-text focus-visible:outline-3 focus-visible:outline-ring">
            <?= icon('x', ['class' => 'size-5']) ?>
            <span class="sr-only">Fechar edição</span>
        </button>
    </div>
    <div class="px-6 py-5 text-body-md">
        <?php if ($abrirModalEdicao && isset($errosCustos['_geral'])) { ?>
            <?= component('alert', ['variant' => 'danger', 'message' => $errosCustos['_geral']]) ?>
        <?php } ?>
        <form method="post" action="<?= e(site_url('financeiro/competenciasCustoFixo/' . (int) $idCustoFixo)) ?>" class="grid gap-3 sm:grid-cols-2">
            <input type="hidden" name="<?= e($csrfNome) ?>" value="<?= e($csrfHash) ?>">
            <input type="hidden" name="salvar_competencia" value="1">
            <input type="hidden" name="origem_competencia" value="modal">
            <?= component('input', ['id' => 'modal-competencia-data', 'name' => 'competencia', 'label' => 'Competência', 'type' => 'date', 'value' => $abrirModalEdicao ? $formCampo('competencia') : '', 'required' => true, 'attrs' => ['data-modal-valor' => 'competencia']]) ?>
            <?= component('input', ['id' => 'modal-competencia-valor', 'name' => 'valor_competencia', 'label' => 'Valor efetivo', 'value' => $abrirModalEdicao ? $formCampo('valor_competencia') : '', 'required' => true, 'attrs' => ['data-modal-valor' => 'valor', 'inputmode' => 'decimal', 'placeholder' => '0,00']]) ?>
            <?= component('input', ['id' => 'modal-competencia-vencimento', 'name' => 'data_vencimento_competencia', 'label' => 'Vencimento', 'type' => 'date', 'value' => $abrirModalEdicao ? $formCampo('data_vencimento_competencia') : '', 'attrs' => ['data-modal-valor' => 'vencimento']]) ?>
            <?= component('textarea', ['id' => 'modal-competencia-observacoes', 'name' => 'observacoes_competencia', 'label' => 'Observações', 'value' => $abrirModalEdicao ? $formCampo('observacoes_competencia') : '', 'rows' => 3, 'attrs' => ['data-modal-valor' => 'observacoes', 'maxlength' => 1000, 'class' => '[overflow-wrap:anywhere]']]) ?>
            <div class="flex flex-wrap justify-end gap-2 sm:col-span-2">
                <button type="button" data-modal-fechar class="inline-flex h-11 items-center rounded-md border border-border px-4 text-sm font-semibold text-text hover:bg-surface-subtle">Cancelar</button>
                <?= component('button', ['label' => 'Salvar competência', 'icon' => 'save', 'type' => 'submit', 'variant' => 'primary']) ?>
            </div>
        </form>
    </div>
</dialog>

<dialog id="modal-pagamento-competencia" data-module="componentes/modal" aria-labelledby="modal-pagamento-titulo" class="m-auto w-[calc(100%-2rem)] max-w-xl rounded-xl border border-border bg-surface p-0 text-text shadow-overlay backdrop:bg-backdrop backdrop:backdrop-blur-[1px]" style="width:calc(100% - 2rem);max-width:36rem;">
    <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
        <h2 id="modal-pagamento-titulo" class="text-lg leading-snug font-semibold text-text">Marcar competência como paga</h2>
        <button type="button" data-modal-fechar class="-m-1 inline-flex size-8 items-center justify-center rounded-md text-muted hover:bg-surface-subtle hover:text-text focus-visible:outline-3 focus-visible:outline-ring">
            <?= icon('x', ['class' => 'size-5']) ?>
            <span class="sr-only">Fechar</span>
        </button>
    </div>
    <div class="px-6 py-5 text-body-md">
        <p class="mb-4 text-caption text-muted">Competência: <span data-modal-valor="label"></span></p>
        <form method="post" action="<?= e(site_url('financeiro/togglePagamentoCompetencia')) ?>" class="flex flex-col gap-3">
            <input type="hidden" name="<?= e($csrfNome) ?>" value="<?= e($csrfHash) ?>">
            <input type="hidden" name="idCompetencia" data-modal-valor="id" value="">
            <input type="hidden" name="action" value="pagar">
            <input type="hidden" name="custo_fixo_id" value="<?= e((string) $idCustoFixo) ?>">
            <?= component('input', ['name' => 'data_pagamento', 'label' => 'Data do pagamento', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true]) ?>
            <?= component('textarea', ['name' => 'observacoes_pagamento', 'label' => 'Observações do pagamento', 'rows' => 3, 'attrs' => ['maxlength' => 1000]]) ?>
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" data-modal-fechar class="inline-flex h-11 items-center rounded-md border border-border px-4 text-sm font-semibold text-text hover:bg-surface-subtle">Cancelar</button>
                <?= component('button', ['label' => 'Confirmar pagamento', 'icon' => 'check', 'type' => 'submit', 'variant' => 'primary']) ?>
            </div>
        </form>
    </div>
</dialog>
<?php } ?>