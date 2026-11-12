<?php
/**
 * Painel inicial (#2847), com os componentes da v5.
 *
 * - Botão de olho no cabeçalho (#3022): oculta os valores em dinheiro
 *   (saldo, balanço, lançamentos), só neste navegador.
 * - Cards de resumo (kpi-card): OS em andamento, orçamentos, vendas em aberto
 *   e saldo do mês. Cada um só com a permissão de ver o módulo.
 * - Agenda das entregas de OS (FullCalendar 6), com filtro de status; o
 *   evento abre um modal com os dados da OS.
 * - Balanço do ano (receitas, despesas e saldo por mês) e OS por status,
 *   em Chart.js 4. Os números vão para o módulo em page_data e também numa
 *   tabela para leitores de tela.
 * - Listas do que pede atenção: OS em andamento, vendas em aberto,
 *   lançamentos a vencer e estoque baixo.
 *
 * O módulo painel/painel carrega o Chart.js e o FullCalendar de
 * assets/vendor só quando a tela tem gráfico ou agenda.
 *
 * @var string              $hoje
 * @var array<string, bool> $pode
 * @var bool                 $instalador_no_servidor Pasta install/ ainda existe (aviso a quem tem cSistema)
 * @var list<string>         $avisos_sistema Migrations pendentes e .env com valores antigos (só para quem tem cSistema)
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';

$kpis = [];
if ($pode['os']) {
    $aguardando = (int) ($os_por_status['Aguardando Peças'] ?? 0);
    $kpis[] = component('kpi-card', [
        'label' => 'OS em andamento',
        'value' => painelSomar($os_por_status, PAINEL_OS_ANDAMENTO),
        'icon' => 'wrench',
        'caption' => $aguardando === 0 ? 'Nenhuma aguardando peças' : $aguardando . ' aguardando peças',
    ]);
    $kpis[] = component('kpi-card', [
        'label' => 'Orçamentos',
        'value' => painelSomar($os_por_status, PAINEL_OS_ORCAMENTO),
        'icon' => 'file-text',
        'caption' => 'Em orçamento ou negociação',
    ]);
}
if ($pode['vendas']) {
    $kpis[] = component('kpi-card', [
        'label' => 'Vendas em aberto',
        'value' => painelSomar($vendas_por_status, PAINEL_VENDAS_ABERTAS),
        'icon' => 'shopping-cart',
        'caption' => 'Ainda não faturadas',
    ]);
}
if ($pode['lancamentos']) {
    $kpis[] = component('kpi-card', [
        'label' => 'Saldo do mês',
        'value' => dinheiro($mes['saldo']),
        'icon' => 'circle-dollar-sign',
        'caption' => 'A receber: ' . dinheiro($visao_geral['a_receber']),
        'sensitive' => true,
    ]);
}

$dadosDoModulo = [];
if ($pode['balanco']) {
    $dadosDoModulo['balanco'] = $balanco;
}
if ($pode['os']) {
    $dadosDoModulo['os'] = $os_grafico;
}

$radarSerie = is_array($radar ?? null) ? ($radar['serie'] ?? []) : [];
$radarTotais = is_array($radar ?? null) ? ($radar['totais'] ?? []) : [];
$radarAproveitamento = is_array($radar ?? null) ? ($radar['aproveitamento'] ?? []) : [];
$radarOsRecebidas = (int) ($radarTotais['os_recebidas'] ?? 0);
$radarOsReconhecidas = (int) ($radarTotais['os_reconhecidas'] ?? 0);
$radarOsProdutivas = (int) ($radarTotais['os_positivas'] ?? $radarTotais['os_produtivas'] ?? 0);
$radarContribuicaoTotal = (float) ($radarTotais['contribuicao_total'] ?? 0);
$radarBaseAproveitamento = $radarOsReconhecidas;
$radarTicketMedio = ($radarTotais['ticket_medio_positivo'] ?? null);
$radarContribuicaoMedia = ($radarTotais['contribuicao_media_geral'] ?? null);
$radarDespesaPorOs = ($radarTotais['despesa_por_os_reconhecida'] ?? null);
$radarResultado = (float) ($radarTotais['resultado_operacional'] ?? 0);
$radarAproveitamentoPct = ($radarTotais['aproveitamento'] ?? null) === null ? null : (float) $radarTotais['aproveitamento'] * 100;
$radarDinheiro = static fn ($valor) => $valor === null ? 'N/D' : dinheiro($valor);
$radarPeriodo = $radar_periodo ?? '12m';
$radarMes = $radar_mes ?? date('Y-m');
?>
<div class="flex flex-col gap-4 pt-2 pb-8" <?= js_module('painel/painel') ?>>
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-display text-heading-xl text-text">Painel</h1>
            <p class="text-caption text-muted"><?= e(ucfirst(painelDataPorExtenso($hoje))) ?></p>
        </div>
        <?php if ($pode['lancamentos'] || $pode['balanco']) { ?>
            <?php // Os dois botões se alternam por CSS (html[data-valores]); o módulo só troca o atributo e guarda a escolha no navegador.?>
            <div>
                <?= component('button', ['label' => 'Ocultar valores', 'icon' => 'eye-off', 'variant' => 'ghost', 'class' => 'painel-valores-ocultar', 'attrs' => ['data-valores-alternar' => true]]) ?>
                <?= component('button', ['label' => 'Mostrar valores', 'icon' => 'eye', 'variant' => 'ghost', 'class' => 'painel-valores-mostrar', 'attrs' => ['data-valores-alternar' => true]]) ?>
            </div>
        <?php } ?>
    </header>

    <?php foreach ($avisos_sistema ?? [] as $aviso) { ?>
        <?= component('alert', ['variant' => 'warning', 'message' => $aviso]) ?>
    <?php } ?>

    <?php if (! empty($instalador_no_servidor)) { ?>
        <?= component('alert', ['variant' => 'warning', 'message' => 'A pasta install ainda está no servidor. Ela já não funciona, mas apague-a do servidor para não deixar arquivos desnecessários expostos.']) ?>
    <?php } ?>

    <?php if ($kpis !== []) { ?>
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4"><?= componenteConteudo($kpis) ?></div>
    <?php } ?>


    <?php if ($pode['os']) { ?>
        <div class="grid gap-4 xl:grid-cols-3">
            <section class="<?= e($caixa) ?> min-w-0 xl:col-span-2" aria-labelledby="titulo-agenda">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 id="titulo-agenda" class="text-heading-sm text-text">Agenda de entregas</h2>
                        <p class="text-caption text-muted">OS pela data final. Clique numa OS para ver os detalhes.</p>
                    </div>
                    <div class="sm:w-56">
                        <?= component('select', [
                            'name' => 'status',
                            'id' => 'agenda-status',
                            'label' => 'Status',
                            'placeholder' => 'Todos os status',
                            'options' => array_combine(array_keys(OS_STATUS_VARIANTES), array_keys(OS_STATUS_VARIANTES)),
                            'attrs' => ['data-agenda-status' => true],
                        ]) ?>
                    </div>
                </div>
                <div class="painel-agenda mt-4 min-h-96" data-agenda="<?= e((string) parse_url(site_url('mapos/calendario'), PHP_URL_PATH)) ?>" aria-live="polite">
                    <p class="text-caption text-muted" data-agenda-carregando>Carregando a agenda…</p>
                </div>
            </section>

            <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-os-status">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="titulo-os-status" class="text-heading-sm text-text">OS por status</h2>
                    <?= component('button', ['label' => 'Ver OS', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('os')]) ?>
                </div>
                <?php if ($os_grafico === []) { ?>
                    <?= component('empty-state', ['title' => 'Nenhuma OS cadastrada', 'message' => 'As OS aparecem aqui por status.', 'icon' => 'chart-pie', 'class' => 'border-0']) ?>
                <?php } else { ?>
                    <div class="relative mx-auto mt-4 aspect-square max-w-72">
                        <canvas data-grafico="os" role="img" aria-label="Gráfico de OS por status"></canvas>
                    </div>
                    <div class="sr-only"><table>
                        <caption>OS por status</caption>
                        <thead><tr><th scope="col">Status</th><th scope="col">OS</th></tr></thead>
                        <tbody>
                            <?php foreach ($os_grafico as $fatia) { ?>
                                <tr><td><?= e($fatia['status']) ?></td><td><?= e($fatia['total']) ?></td></tr>
                            <?php } ?>
                        </tbody>
                    </table></div>
                <?php } ?>
            </section>
        </div>
    <?php } ?>

    <?php if (! empty($radar ?? null)) { ?>
    <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-radar">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div>
                <h2 id="titulo-radar" class="text-heading-sm text-text">Produção, Ticket Médio e Rentabilidade</h2>
                <p class="text-caption text-muted">Todas as OS do universo aparecem como recebidas, reconhecidas, pendentes ou exceções. A competência financeira usa recebimento efetivo; o mês corrente pode estar parcial.</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <form method="get" action="<?= e(site_url('mapos')) ?>" class="flex flex-wrap items-end gap-2" aria-label="Período do Radar">
                    <label class="text-caption text-muted">Período
                        <select name="radar_periodo" class="ml-1 rounded-md border border-border bg-surface px-2 py-1.5 text-sm text-text">
                            <option value="12m" <?= $radarPeriodo === '12m' ? 'selected' : '' ?>>Últimos 12 meses</option>
                            <option value="3m" <?= $radarPeriodo === '3m' ? 'selected' : '' ?>>Últimos 3 meses</option>
                            <option value="mes" <?= $radarPeriodo === 'mes' ? 'selected' : '' ?>>Mês selecionado</option>
                        </select>
                    </label>
                    <label class="text-caption text-muted">Mês/ano
                        <input type="month" name="radar_mes" value="<?= e($radarMes) ?>" class="ml-1 rounded-md border border-border bg-surface px-2 py-1 text-sm text-text">
                    </label>
                    <button type="submit" class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-on-primary hover:bg-primary-hover">Aplicar</button>
                </form>
                <a href="<?= e(site_url('mapos/inteligenciaTickets')) ?>" class="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-2 text-sm text-text hover:bg-surface-subtle transition-colors" title="Análise detalhada">
                    Análise detalhada
                    <?= icon('chevron-right', ['class' => 'size-4']) ?>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2" data-module="painel/radar">
            <?= page_data('radar-dados', $radar) ?>

            <!-- A. Gráfico principal: contribuição média geral vs despesa por OS reconhecida -->
            <div class="min-w-0">
                <div class="relative h-72" style="height: clamp(280px, 25vw, 340px);">
                    <canvas id="radar-principal" aria-label="Rentabilidade mensal: contribuição média geral e despesa operacional por OS reconhecida"></canvas>
                </div>
                <p class="mt-1 text-caption text-muted">Comparação ponderada da contribuição média geral com a despesa por OS reconhecida. Meses sem base financeira ficam sem linha; o mês corrente pode estar parcial.</p>
            </div>

            <!-- B. Aproveitamento financeiro das OS concluídas -->
            <div class="min-w-0 rounded-lg border border-border bg-surface-subtle p-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="flex items-center gap-1.5 text-caption text-muted">
                            <?= icon('chart-pie', ['class' => 'size-4 text-muted']) ?> Aproveitamento das OS
                        </h3>
                        <p class="mt-1 text-caption text-muted"><?= $radarBaseAproveitamento > 0 && $radarBaseAproveitamento < 10 ? 'Amostra pequena: ' . $radarBaseAproveitamento . ' OS reconhecidas.' : $radarBaseAproveitamento . ' OS reconhecidas no período.' ?></p>
                    </div>
                    <span class="rounded-full bg-primary-tint px-2 py-1 text-caption font-semibold text-primary-strong" data-radar-aproveitamento-centro-label><?= $radarAproveitamentoPct === null ? 'Sem base' : e(number_format($radarAproveitamentoPct, 0, ',', '.') . '% das classificadas') ?></span>
                </div>
                <div class="relative mt-2 h-64" style="height: clamp(240px, 22vw, 300px);">
                    <canvas id="radar-aproveitamento" aria-label="Aproveitamento financeiro das OS concluídas"></canvas>
                    <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-heading-sm font-semibold text-text" data-radar-aproveitamento-centro aria-hidden="true"><?= $radarAproveitamentoPct === null ? 'Sem base' : e(number_format($radarAproveitamentoPct, 0, ',', '.') . '% das classificadas') ?></span>
                </div>
            </div>

            <!-- C. Tendência de três meses (média móvel ponderada) -->
            <div class="min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="flex items-center gap-1.5 text-caption text-muted">
                        <?= icon('chart-column', ['class' => 'size-4 text-muted']) ?> Tendência (média móvel 3M)
                    </h3>
                    <span class="text-caption text-muted">Sem base = ausência de linha</span>
                </div>
                <div class="relative mt-2 h-64" style="height: clamp(240px, 20vw, 280px);">
                    <canvas id="radar-tendencia" aria-label="Tendência da contribuição: média móvel ponderada de 3 meses"></canvas>
                </div>
            </div>

            <!-- D. Síntese gerencial do período -->
            <aside class="min-w-0 rounded-lg border border-border bg-surface-subtle p-4" aria-labelledby="titulo-radar-resumo">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="titulo-radar-resumo" class="text-heading-sm text-text">Resumo gerencial</h3>
                        <p class="mt-1 text-caption text-muted">Síntese da mesma base exibida no Radar.</p>
                    </div>
                    <?= icon('percent', ['class' => 'size-5 shrink-0 text-primary']) ?>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-2">
                    <div class="rounded-md border border-border bg-surface p-3">
                        <dt class="text-caption text-muted">Ticket médio positivo</dt>
                        <dd class="mt-1 text-body-md font-semibold tabular-nums text-text" data-valor-sensivel><?= e($radarDinheiro($radarTicketMedio)) ?></dd>
                    </div>
                    <div class="rounded-md border border-border bg-surface p-3">
                        <dt class="text-caption text-muted">Contribuição média geral</dt>
                        <dd class="mt-1 text-body-md font-semibold tabular-nums text-text" data-valor-sensivel><?= e($radarDinheiro($radarContribuicaoMedia)) ?></dd>
                    </div>
                    <div class="rounded-md border border-border bg-surface p-3">
                        <dt class="text-caption text-muted">Contribuição total</dt>
                        <dd class="mt-1 text-body-md font-semibold tabular-nums text-text" data-valor-sensivel><?= e($radarDinheiro($radarContribuicaoTotal)) ?></dd>
                    </div>
                    <div class="rounded-md border border-border bg-surface p-3">
                        <dt class="text-caption text-muted">Despesa / OS reconhecida</dt>
                        <dd class="mt-1 text-body-md font-semibold tabular-nums text-text" data-valor-sensivel><?= e($radarDinheiro($radarDespesaPorOs)) ?></dd>
                    </div>
                    <div class="rounded-md border border-border bg-surface p-3">
                        <dt class="text-caption text-muted">Resultado operacional</dt>
                        <dd class="mt-1 text-body-md font-semibold tabular-nums text-text" data-valor-sensivel><?= e($radarDinheiro($radarResultado)) ?></dd>
                    </div>
                    <div class="rounded-md border border-border bg-surface p-3">
                        <dt class="text-caption text-muted">Aproveitamento</dt>
                        <dd class="mt-1 text-body-md font-semibold tabular-nums text-text"><?= $radarAproveitamentoPct === null ? 'N/D' : e(number_format($radarAproveitamentoPct, 1, ',', '.') . '%') ?></dd>
                    </div>
                    <div class="col-span-2 rounded-md border border-border bg-surface p-3 sm:col-span-3 lg:col-span-2">
                        <dt class="text-caption text-muted">Cobertura do universo</dt>
                        <dd class="mt-1 text-body-md font-semibold text-text"><?= e($radarOsRecebidas . ' recebidas · ' . $radarOsReconhecidas . ' reconhecidas · ' . ($radarTotais['os_pendentes'] ?? 0) . ' pendentes · ' . ($radarTotais['os_excecoes'] ?? 0) . ' exceções') ?></dd>
                        <p class="mt-1 text-caption text-muted">Positivas: <?= (int) ($radarTotais['os_positivas'] ?? 0) ?> · neutras: <?= (int) ($radarTotais['os_neutras'] ?? 0) ?> · negativas: <?= (int) ($radarTotais['os_negativas'] ?? 0) ?>. A partição completa permanece visível na análise detalhada.</p>
                    </div>
                </dl>
            </aside>
        </div>
    </section>
    <?php } ?>
    <?php if ($pode['balanco']) { ?>
        <?php $temMovimentoFinanceiro = array_sum($balanco['receita_bruta']) + array_sum($balanco['despesas']) + array_sum($balanco['custos_fixos_pago']) + array_sum($balanco['custos_fixos_a_pagar']) + array_sum($balanco['inadimplencia']) > 0; ?>
        <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-balanco">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="titulo-balanco" class="text-heading-sm text-text"><?= e('Balanço de ' . $ano) ?></h2>
                    <p class="text-caption text-muted">Faturamento real pago, líquido do custo de componentes/itens; custos fixos por competência.</p>
                </div>
                <form method="get" action="<?= e(site_url('mapos')) ?>" class="flex items-end gap-2">
                    <div class="w-32">
                        <?= component('select', [
                            'name' => 'ano',
                            'id' => 'balanco-ano',
                            'label' => 'Ano',
                            'options' => array_combine(array_map('strval', $anos), array_map('strval', $anos)),
                            'selected' => (string) $ano,
                        ]) ?>
                    </div>
                    <?= component('button', ['label' => 'Ver', 'variant' => 'outline', 'type' => 'submit']) ?>
                </form>
            </div>
            <?php if (! $temMovimentoFinanceiro) { ?>
                <?= component('empty-state', ['title' => 'Nada pago em ' . $ano, 'message' => 'Faturamentos, despesas, custos fixos e inadimplência aparecem aqui, mês a mês.', 'icon' => 'chart-column', 'class' => 'border-0']) ?>
            <?php } else { ?>
                <div class="relative mt-4 h-72" data-valor-grafico>
                    <canvas data-grafico="balanco" role="img" aria-label="<?= e('Gráfico financeiro anual de ' . $ano) ?>"></canvas>
                </div>
                <p class="painel-valores-aviso mt-4 h-72 items-center justify-center text-caption text-muted">Valores ocultos.</p>
                <div class="sr-only"><table>
                    <caption><?= e('Balanço financeiro de ' . $ano) ?></caption>
                    <thead><tr><th scope="col">Mês</th><th scope="col">Receita Bruta</th><th scope="col">Receita Líquida</th><th scope="col">Custos Fixos pagos</th><th scope="col">Custos Fixos a pagar</th><th scope="col">Despesas</th><th scope="col">Inadimplência</th></tr></thead>
                    <tbody>
                        <?php foreach ($balanco['meses'] as $i => $mesNome) { ?>
                            <tr><td><?= e($mesNome) ?></td><td><?= e(dinheiro($balanco['receita_bruta'][$i])) ?></td><td><?= e(dinheiro($balanco['receita_liquida'][$i])) ?></td><td><?= e(dinheiro($balanco['custos_fixos_pago'][$i])) ?></td><td><?= e(dinheiro($balanco['custos_fixos_a_pagar'][$i])) ?></td><td><?= e(dinheiro($balanco['despesas'][$i])) ?></td><td><?= e(dinheiro($balanco['inadimplencia'][$i])) ?></td></tr>
                        <?php } ?>
                    </tbody>
                </table></div>
            <?php } ?>
        </section>
    <?php } ?>

    <div class="grid gap-4 2xl:grid-cols-2">
        <?php if ($pode['os']) { ?>
            <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-lista-os">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="titulo-lista-os" class="text-heading-sm text-text">OS em andamento</h2>
                    <?= component('button', ['label' => 'Ver todas', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('os')]) ?>
                </div>
                <div class="mt-3">
                    <?= component('data-table', [
                        'caption' => 'OS em andamento, pela data de entrega',
                        'dense' => true,
                        'rows' => $os_lista,
                        'columns' => [
                            ['label' => 'Nº', 'align' => 'right', 'nowrap' => true, 'render' => fn ($o) => component('link', ['label' => (string) $o->idOs, 'href' => site_url('os/visualizar/' . (int) $o->idOs)])],
                            ['label' => 'Cliente', 'class' => 'min-w-0 max-w-48', 'truncate' => true, 'render' => fn ($o) => nomeClienteExibicao($o->nomeCliente ?? 'Cliente removido')],
                            ['label' => 'Entrega', 'align' => 'right', 'nowrap' => true, 'render' => fn ($o) => $o->dataFinal !== null && substr((string) $o->dataFinal, 0, 10) < $hoje
                                ? component('pill-status', ['label' => 'Atrasada · ' . dataBr($o->dataFinal), 'variant' => 'warning'])
                                : ($o->dataFinal !== null ? dataBr($o->dataFinal) : '—')],
                            ['label' => 'Status', 'nowrap' => true, 'render' => fn ($o) => component('pill-status', osStatusPill($o->status))],
                        ],
                        'empty' => component('empty-state', ['title' => 'Nenhuma OS em andamento', 'message' => 'OS abertas, aprovadas, em andamento ou aguardando peças aparecem aqui.', 'icon' => 'wrench', 'class' => 'border-0']),
                    ]) ?>
                </div>
            </section>
        <?php } ?>

        <?php if ($pode['vendas']) { ?>
            <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-lista-vendas">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="titulo-lista-vendas" class="text-heading-sm text-text">Vendas em aberto</h2>
                    <?= component('button', ['label' => 'Ver todas', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('vendas')]) ?>
                </div>
                <div class="mt-3">
                    <?= component('data-table', [
                        'caption' => 'Vendas em aberto, da mais recente',
                        'dense' => true,
                        'rows' => $vendas_lista,
                        'columns' => [
                            ['label' => 'Nº', 'align' => 'right', 'nowrap' => true, 'render' => fn ($v) => component('link', ['label' => (string) $v->idVendas, 'href' => site_url('vendas/visualizar/' . (int) $v->idVendas)])],
                            ['label' => 'Cliente', 'class' => 'min-w-0 max-w-48', 'truncate' => true, 'render' => fn ($v) => nomeClienteExibicao($v->nomeCliente ?? 'Cliente removido')],
                            ['label' => 'Data', 'align' => 'right', 'nowrap' => true, 'render' => fn ($v) => dataBr($v->dataVenda)],
                            ['label' => 'Status', 'nowrap' => true, 'render' => fn ($v) => component('pill-status', osStatusPill($v->status))],
                        ],
                        'empty' => component('empty-state', ['title' => 'Nenhuma venda em aberto', 'message' => 'Vendas ainda não faturadas aparecem aqui.', 'icon' => 'shopping-cart', 'class' => 'border-0']),
                    ]) ?>
                </div>
            </section>
        <?php } ?>

        <?php if ($pode['lancamentos']) { ?>
            <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-lista-lancamentos">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="titulo-lista-lancamentos" class="text-heading-sm text-text">Lançamentos a vencer</h2>
                    <?= component('button', ['label' => 'Ver todos', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('financeiro/lancamentos') . listagemQuery(['periodo' => 'ano', 'status' => 'pendente'])]) ?>
                </div>
                <div class="mt-3">
                    <?= component('data-table', [
                        'caption' => 'Lançamentos em aberto, do vencimento mais antigo',
                        'dense' => true,
                        'rows' => $lancamentos_lista,
                        'columns' => [
                            ['label' => 'Vencimento', 'align' => 'right', 'nowrap' => true, 'render' => fn ($l) => substr((string) $l->data_vencimento, 0, 10) < $hoje
                                ? component('pill-status', ['label' => 'Vencido · ' . dataBr($l->data_vencimento), 'variant' => 'warning'])
                                : dataBr($l->data_vencimento)],
                            ['label' => 'Descrição', 'class' => 'min-w-40 [overflow-wrap:anywhere]', 'render' => fn ($l) => trim((string) $l->descricao . ((string) $l->cliente_fornecedor !== '' ? ' · ' . $l->cliente_fornecedor : ''))],
                            ['label' => 'Tipo', 'nowrap' => true, 'render' => fn ($l) => component('pill-status', $l->tipo === 'receita' ? ['label' => 'Receita', 'variant' => 'info'] : ['label' => 'Despesa', 'variant' => 'neutral'])],
                            ['label' => 'Valor', 'align' => 'right', 'nowrap' => true, 'render' => fn ($l) => component('valor-sensivel', ['value' => dinheiro($l->liquido)])],
                        ],
                        'empty' => component('empty-state', ['title' => 'Nenhum lançamento em aberto', 'message' => 'Receitas a receber e despesas a pagar aparecem aqui.', 'icon' => 'circle-dollar-sign', 'class' => 'border-0']),
                    ]) ?>
                </div>
            </section>
        <?php } ?>

        <?php if ($pode['produtos']) { ?>
            <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-lista-estoque">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="titulo-lista-estoque" class="text-heading-sm text-text">Estoque baixo</h2>
                    <?= component('button', ['label' => 'Ver todos', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('produtos') . listagemQuery(['estoque' => 'baixo'])]) ?>
                </div>
                <div class="mt-3">
                    <?= component('data-table', [
                        'caption' => 'Produtos no estoque mínimo ou abaixo',
                        'dense' => true,
                        'rows' => $estoque_lista,
                        'columns' => [
                            ['label' => 'Produto', 'class' => 'min-w-40 [overflow-wrap:anywhere]', 'render' => fn ($p) => component('link', ['label' => (string) $p->descricao, 'href' => site_url('produtos/visualizar/' . (int) $p->idProdutos)])],
                            ['label' => 'Estoque', 'align' => 'right', 'nowrap' => true, 'render' => fn ($p) => (string) (int) $p->estoque],
                            ['label' => 'Mínimo', 'align' => 'right', 'nowrap' => true, 'render' => fn ($p) => (string) (int) $p->estoqueMinimo],
                            ['label' => 'Situação', 'nowrap' => true, 'render' => fn ($p) => component('pill-status', (int) $p->estoque <= 0 ? ['label' => 'Sem estoque', 'variant' => 'warning'] : ['label' => 'Baixo', 'variant' => 'warning'])],
                        ],
                        'empty' => component('empty-state', ['title' => 'Nenhum produto com estoque baixo', 'message' => 'Produtos no estoque mínimo ou abaixo aparecem aqui.', 'icon' => 'package', 'class' => 'border-0']),
                    ]) ?>
                </div>
            </section>
        <?php } ?>
    </div>

    <?php if ($pode === array_fill_keys(array_keys($pode), false)) { ?>
        <?= component('empty-state', ['title' => 'Bem-vindo ao Map-OS', 'message' => 'Seu usuário ainda não tem permissão para ver os módulos do painel. Fale com o administrador.', 'icon' => 'house']) ?>
    <?php } ?>
</div>

<?php if ($dadosDoModulo !== []) { ?>
    <?= page_data('painel-dados', $dadosDoModulo) ?>
<?php } ?>

<?php if ($pode['os']) { ?>
    <?= component('modal', [
        'id' => 'agenda-os',
        'title' => 'OS',
        'size' => 'md',
        // Marcação estática; os dados entram por textContent (módulo
        // painel/painel). Um pill-status por status, escondidos: o módulo
        // mostra o da OS clicada.
        'body' => [
            new HtmlSeguro('<div class="flex flex-col gap-3"><div>'),
            array_map(static fn ($status) => component('pill-status', osStatusPill($status) + ['attrs' => ['data-agenda-pill' => $status, 'hidden' => true]]), array_keys(OS_STATUS_VARIANTES)),
            new HtmlSeguro('</div>'
            . '<dl class="grid gap-3 sm:grid-cols-2">'
            . '<div class="sm:col-span-2"><dt class="text-caption text-muted">Cliente</dt><dd class="text-body-md [overflow-wrap:anywhere] text-text" data-agenda-campo="cliente"></dd></div>'
            . '<div><dt class="text-caption text-muted">Entrada</dt><dd class="text-body-md tabular-nums text-text" data-agenda-campo="dataInicial"></dd></div>'
            . '<div><dt class="text-caption text-muted">Entrega</dt><dd class="text-body-md tabular-nums text-text" data-agenda-campo="dataFinal"></dd></div>'
            . '<div class="sm:col-span-2"><dt class="text-caption text-muted">Equipamento</dt><dd class="text-body-md [overflow-wrap:anywhere] text-text" data-agenda-campo="equipamento"></dd></div>'
            . '<div><dt class="text-caption text-muted">Total</dt><dd class="text-body-md tabular-nums text-text" data-agenda-campo="total"></dd></div>'
            . '<div><dt class="text-caption text-muted">Faturada</dt><dd class="text-body-md text-text" data-agenda-campo="faturado"></dd></div>'
            . '</dl></div>'),
        ],
        'footer' => [
            component('button', ['label' => 'Fechar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
            component('button', ['label' => 'Editar OS', 'icon' => 'pencil', 'variant' => 'outline', 'href' => site_url('os'), 'attrs' => ['data-agenda-link' => 'urlEditar', 'hidden' => true]]),
            component('button', ['label' => 'Ver OS', 'icon' => 'eye', 'variant' => 'outline', 'href' => site_url('os'), 'attrs' => ['data-agenda-link' => 'url']]),
        ],
    ]) ?>
<?php } ?>
