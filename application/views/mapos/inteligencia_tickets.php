<?php
/**
 * Inteligência de Tickets — Radar de Rentabilidade (detalhado).
 *
 * Página do grupo Financeiro. Somente leitura; não altera dados do banco.
 * Exige permissão de visualização financeira (rFinanceiro) e de OS (vOs).
 *
 * @var array $radar   Resultado de Financeiro_model::radarRentabilidade()
 * @var string $janela '12m', '3m', 'mes' ou 'civil'
 * @var int $ano       Ano civil exibido (quando $janela === 'civil')
 * @var string $mes    Mês selecionado (quando $janela === 'mes')
 * @var string $hoje   Data de referência (AAAA-MM-DD)
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
// O estado "ocultar valores" vive no atributo data-valores do <html>; a view
// apenas renderiza os números e o JS/CSS mascaram quando estiver oculto.
?>
<div class="flex flex-col gap-4 pt-2 pb-8" data-module="financeiro/inteligencia_tickets">
  <input type="hidden" id="inteligencia-csrf" value="">
  <?= page_data('inteligencia-dados', [
    'janela' => $janela,
    'ano' => $ano,
    'radar' => $radar,
    'urls' => [
      'dados' => site_url('mapos/inteligenciaTicketsDados'),
      'pagina' => site_url('mapos/inteligenciaTickets'),
    ],
  ]) ?>

  <header class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <h1 class="font-display text-heading-xl text-text">Inteligência de Tickets</h1>
      <p class="text-caption text-muted">
        Radar de rentabilidade das ordens de serviço: contribuição operacional
        (receita efetiva da OS menos custo histórico dos componentes) versus
        despesa operacional distribuída.
      </p>
    </div>
    <div class="flex max-w-full flex-wrap items-end gap-2">
      <form method="get" action="<?= e(site_url('mapos/inteligenciaTickets')) ?>" class="flex max-w-full flex-wrap items-end gap-2">
        <select name="janela" id="janela" class="rounded-md border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:outline-none">
          <option value="12m" <?= $janela === '12m' ? 'selected' : '' ?>>Últimos 12 meses</option>
          <option value="3m" <?= $janela === '3m' ? 'selected' : '' ?>>Últimos 3 meses</option>
          <option value="mes" <?= $janela === 'mes' ? 'selected' : '' ?>>Mês selecionado</option>
          <option value="civil" <?= $janela === 'civil' ? 'selected' : '' ?>>Ano civil</option>
        </select>
        <input type="month" name="mes" value="<?= e($mes ?? date('Y-m')) ?>"
          class="rounded-md border border-border bg-surface px-3 py-2 text-sm text-text w-36 focus:border-primary focus:outline-none">
        <input type="number" name="ano" min="2000" max="2100" value="<?= (int) $ano ?>"
          class="rounded-md border border-border bg-surface px-3 py-2 text-sm text-text w-20 text-right focus:border-primary focus:outline-none"
          <?= $janela !== 'civil' ? 'disabled' : '' ?>>
        <button type="submit" class="rounded-md bg-primary text-on-primary px-3 py-2 text-sm font-medium hover:bg-primary-hover transition-colors">Aplicar</button>
      </form>
      <a href="<?= e(site_url('mapos')) ?>" class="rounded-md border border-border bg-surface px-3 py-2 text-sm text-text hover:bg-surface-subtle transition-colors">Voltar ao painel</a>
    </div>
  </header>

  <?php if (! empty($radar['cobertura']['mensagem'] ?? '')) { ?>
    <div class="rounded-lg border border-border bg-amber-tint p-3 text-amber-900 text-sm">
      <?= e($radar['cobertura']['mensagem']) ?>
    </div>
  <?php } ?>

  <!-- Resumo do período -->
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
    <?php
    $resumo = [
      ['label' => 'OS recebidas', 'valor' => (int) ($radar['totais']['os_recebidas'] ?? 0), 'icone' => 'archive', 'cap' => 'entradas pela data inicial'],
      ['label' => 'OS reconhecidas', 'valor' => (int) ($radar['totais']['os_reconhecidas'] ?? 0), 'icone' => 'circle-check', 'cap' => 'resultado financeiro ou encerramento sem receita'],
      ['label' => 'Ticket médio positivo', 'valor' => ($radar['totais']['ticket_medio_positivo'] ?? null) === null ? 'N/D' : dinheiro((float) $radar['totais']['ticket_medio_positivo']), 'icone' => 'percent', 'cap' => 'contribuições positivas / OS positivas'],
      ['label' => 'Contribuição média geral', 'valor' => ($radar['totais']['contribuicao_media_geral'] ?? null) === null ? 'N/D' : dinheiro((float) $radar['totais']['contribuicao_media_geral']), 'icone' => 'plus', 'cap' => 'contribuição / OS reconhecidas'],
      ['label' => 'Resultado operacional', 'valor' => dinheiro((float) ($radar['totais']['resultado_operacional'] ?? 0)), 'icone' => 'chevron-down', 'cap' => 'contribuição menos despesa operacional'],
    ];
    foreach ($resumo as $item) {
      $sensivel = in_array($item['label'], ['Ticket médio positivo', 'Contribuição média geral', 'Resultado operacional'], true);
      echo component('kpi-card', [
        'label' => $item['label'],
        'value' => $item['valor'],
        'icon' => $item['icone'],
        'caption' => $item['cap'],
        'sensitive' => $sensivel ? true : null,
      ]);
    }
    ?>
  </div>

  <!-- Gráficos ampliados -->
  <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-graficos-detalhe">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
      <div>
        <h2 id="titulo-graficos-detalhe" class="text-heading-sm text-text">Gráficos</h2>
        <p class="text-caption text-muted">Valores monetários seguem o controle "Ocultar valores" do painel.</p>
      </div>
    </div>

    <div class="grid gap-4 grid-cols-1 lg:grid-cols-2" data-module="financeiro/inteligencia_tickets">
      <!-- A. Principal 12 meses -->
      <div class="lg:col-span-2">
        <h3 class="text-caption text-muted mb-3 flex items-center gap-1.5">
          <?= icon('chart-column', ['class' => 'size-4 text-muted']) ?> A. Rentabilidade mensal (contribuição média geral vs despesa por OS reconhecida)
        </h3>
        <div class="relative h-72" style="height: clamp(260px, 23.5vw, 320px);">
          <canvas id="inteligencia-principal" aria-label="Rentabilidade mensal: contribuição média geral e despesa operacional por OS reconhecida"></canvas>
        </div>
        <p class="text-caption text-muted mt-2">
          Contribuição média geral = contribuição total / OS reconhecidas; despesa por OS reconhecida = despesa operacional / OS reconhecidas. Meses sem base financeira ficam sem linha.
        </p>
      </div>

      <!-- B. Aproveitamento -->
      <div class="lg:col-span-1">
        <h3 class="text-caption text-muted mb-3 flex items-center gap-1.5">
          <?= icon('chart-pie', ['class' => 'size-4 text-muted']) ?> B. Aproveitamento financeiro das OS
        </h3>
        <div class="relative h-72" style="height: clamp(220px, 18vw, 280px);">
          <canvas id="inteligencia-aproveitamento" aria-label="Aproveitamento financeiro das OS concluídas"></canvas>
        </div>
      </div>

      <!-- C. Tendência 3M -->
      <div class="lg:col-span-1">
        <h3 class="text-caption text-muted mb-3 flex items-center gap-1.5">
          <?= icon('chart-column', ['class' => 'size-4 text-muted']) ?> C. Tendência (média móvel 3M)
        </h3>
        <div class="relative h-72" style="height: clamp(220px, 16vw, 260px);">
          <canvas id="inteligencia-tendencia" aria-label="Tendência: média móvel ponderada de 3 meses"></canvas>
        </div>
      </div>
    </div>
  </section>

  <!-- Tabelas de dados -->
  <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-tabelas">
    <h2 id="titulo-tabelas" class="text-heading-sm text-text mb-3">Tabelas de dados</h2>

    <div class="grid gap-4 grid-cols-1 lg:grid-cols-2">
      <!-- Série mensal -->
      <div class="min-w-0 lg:col-span-2">
        <h3 class="text-caption text-muted mb-2">Série mensal e conferência</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm border-collapse">
            <thead>
              <tr class="border-b border-border text-caption text-muted">
                <th class="pb-2 pr-4">Mês</th>
                <th class="pb-2 pr-4 text-right">Recebidas</th>
                <th class="pb-2 pr-4 text-right">Reconhecidas</th>
                <th class="pb-2 pr-4 text-right">+ / 0 / −</th>
                <th class="pb-2 pr-4 text-right">Pendentes</th>
                <th class="pb-2 pr-4 text-right">Exceções</th>
                <th class="pb-2 pr-4 text-right">Contribuição</th>
                <th class="pb-2 pr-4 text-right">Despesa</th>
                <th class="pb-2 pr-4 text-right">Resultado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ((array) ($radar['serie'] ?? []) as $linha) { ?>
                <tr class="border-b border-surface hover:bg-surface-subtle transition-colors">
                  <td class="py-2 pr-4 text-text"><?= e(labelMesPhp($linha['mes'] ?? '')) ?><?= ! empty($linha['mes_atual_parcial']) ? ' <span class="text-caption text-muted">(parcial)</span>' : '' ?></td>
                  <td class="py-2 pr-4 text-right text-text tabular-nums"><?= (int) ($linha['os_recebidas'] ?? 0) ?></td>
                  <td class="py-2 pr-4 text-right text-text tabular-nums"><?= (int) ($linha['os_reconhecidas'] ?? 0) ?></td>
                  <td class="py-2 pr-4 text-right text-text tabular-nums"><?= (int) ($linha['os_positivas'] ?? 0) ?> / <?= (int) ($linha['os_neutras'] ?? 0) ?> / <?= (int) ($linha['os_negativas'] ?? 0) ?></td>
                  <td class="py-2 pr-4 text-right text-text tabular-nums"><?= (int) ($linha['os_pendentes'] ?? 0) ?></td>
                  <td class="py-2 pr-4 text-right text-text tabular-nums"><?= (int) ($linha['os_excecoes'] ?? 0) ?></td>
                  <td class="py-2 pr-4 text-right text-text tabular-nums data-valor-sensivel"><?= dinheiro((float) ($linha['contribuicao_total'] ?? 0)) ?></td>
                  <td class="py-2 pr-4 text-right text-text tabular-nums data-valor-sensivel"><?= dinheiro((float) ($linha['despesa_total'] ?? 0)) ?></td>
                  <td class="py-2 pr-4 text-right text-text tabular-nums <?= ((float) ($linha['resultado_operacional'] ?? 0) < 0 ? 'text-danger' : '') ?> data-valor-sensivel"><?= dinheiro((float) ($linha['resultado_operacional'] ?? 0)) ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <?php if (empty($radar['serie'])) { ?>
          <p class="text-caption text-muted mt-2">Nenhum mês no período selecionado.</p>
        <?php } ?>
      </div>

      <!-- Aproveitamento -->
      <div class="min-w-0">
        <h3 class="text-caption text-muted mb-2">Aproveitamento financeiro das OS</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm border-collapse">
            <thead>
              <tr class="border-b border-border text-caption text-muted">
                <th class="pb-2 pr-4">Classificação</th>
                <th class="pb-2 pr-4 text-right">Quantidade</th>
                <th class="pb-2 pr-4 text-right">Percentual</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $ap = $radar['aproveitamento'] ?? [];
              $totalAp = (int) ($ap['reconhecida'] ?? 0);
              $rowsAp = [
                ['Positiva', (int) ($ap['positiva'] ?? 0), 'text-success'],
                ['Neutra', (int) ($ap['zero'] ?? 0), 'text-muted'],
                ['Negativa', (int) ($ap['negativa'] ?? 0), 'text-negativo'],
              ];
              foreach ($rowsAp as [$lbl, $qtd, $cls]) {
                $pct = $totalAp > 0 ? number_format(($qtd / $totalAp) * 100, 1, ',', '.') : 'N/D';
                echo "<tr class='border-b border-surface'><td class='py-2 pr-4 text-text'>$lbl</td><td class='py-2 pr-4 text-right tabular-nums text-text'>$qtd</td><td class='py-2 pr-4 text-right tabular-nums text-text'>$pct" . ($pct === 'N/D' ? '' : '%') . "</td></tr>";
              }
              echo "<tr class='border-b border-border font-semibold'><td class='py-2 pr-4 text-text'>Reconhecidas (base)</td><td class='py-2 pr-4 text-right tabular-nums text-text'>$totalAp</td><td class='py-2 pr-4 text-right tabular-nums text-text'>" . ($totalAp > 0 ? '100%' : 'N/D') . "</td></tr>";
              echo "<tr class='border-b border-surface'><td class='py-2 pr-4 text-text'>Pendentes</td><td class='py-2 pr-4 text-right tabular-nums text-text'>" . (int) ($ap['pendente'] ?? 0) . "</td><td class='py-2 pr-4 text-right tabular-nums text-text'>fora da base</td></tr>";
              echo "<tr class='border-b border-surface'><td class='py-2 pr-4 text-text'>Exceções</td><td class='py-2 pr-4 text-right tabular-nums text-text'>" . (int) ($ap['excecao'] ?? 0) . "</td><td class='py-2 pr-4 text-right tabular-nums text-text'>fora da base</td></tr>";
              ?>
            </tbody>
          </table>
        </div>
        <p class="text-caption text-muted mt-2">As três classificações de resultado são mutuamente exclusivas e somam exatamente as OS reconhecidas; pendentes e exceções continuam fora da base financeira, mas permanecem no universo.</p>
      </div>
    </div>

    <!-- Média móvel 3M -->
    <div class="mt-4">
      <h3 class="text-caption text-muted mb-2">Média móvel ponderada de 3 meses</h3>
      <p class="text-caption text-muted text-xs mb-2">Soma das contribuições do período ÷ soma das quantidades correspondentes (nunca média simples de três tickets médios).</p>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-border text-caption text-muted">
              <th class="pb-2 pr-4">Mês</th>
              <th class="pb-2 pr-4 text-right">Média 3M</th>
              <th class="pb-2 pr-4 text-right">Período completo</th>
            </thead>
            <tbody>
              <?php foreach ((array) ($radar['mediaMovel3M'] ?? []) as $m) { ?>
                <tr class="border-b border-surface">
                  <td class="py-2 pr-4 text-text"><?= e(labelMesPhp($m['mes'] ?? '')) ?></td>
                  <td class="py-2 pr-4 text-right text-text tabular-nums data-valor-sensivel"><?= ($m['media_movel_3m'] ?? null) === null ? 'Sem base' : dinheiro((float) $m['media_movel_3m']) ?></td>
                  <td class="py-2 pr-4 text-right text-text"><?= ($m['periodo_completo'] ?? false) ? 'Sim' : 'Parcial' ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- Conferência por OS: universo completo, sem amostragem -->
  <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-os-radar">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
      <div>
        <h2 id="titulo-os-radar" class="text-heading-sm text-text">OS que compõem os indicadores</h2>
        <p class="text-caption text-muted">Universo completo: <?= (int) ($radar['cobertura']['total_os'] ?? count((array) ($radar['os'] ?? []))) ?> OS. Cada linha pertence a uma única categoria.</p>
      </div>
      <span class="text-caption text-muted">Reconhecida · Pendente · Exceção</span>
    </div>
    <div class="mt-3 max-h-[34rem] overflow-auto">
      <table class="w-full min-w-[980px] text-left text-sm border-collapse">
        <thead class="sticky top-0 bg-surface">
          <tr class="border-b border-border text-caption text-muted">
            <th class="pb-2 pr-3">OS</th><th class="pb-2 pr-3">Status</th><th class="pb-2 pr-3">Categoria</th>
            <th class="pb-2 pr-3">Reconhecimento</th><th class="pb-2 pr-3">Fonte</th>
            <th class="pb-2 pr-3 text-right">Receita</th><th class="pb-2 pr-3 text-right">Custo</th><th class="pb-2 pr-3 text-right">Contribuição</th>
            <th class="pb-2 pr-3">Motivo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ((array) ($radar['os'] ?? []) as $os) {
            $estado = (string) ($os['estado'] ?? 'excecao');
            $categoria = $estado === 'reconhecida' ? 'Reconhecida' : ($estado === 'pendente' ? 'Pendente' : 'Exceção');
            $contrib = $os['contribuicao'] ?? null;
          ?>
            <tr class="border-b border-surface hover:bg-surface-subtle">
              <td class="py-1.5 pr-3 font-medium text-text tabular-nums"><?= (int) ($os['idOs'] ?? 0) ?></td>
              <td class="py-1.5 pr-3 text-text"><?= e((string) ($os['status'] ?? '')) ?></td>
              <td class="py-1.5 pr-3 text-text"><?= e($categoria) ?></td>
              <td class="py-1.5 pr-3 text-text"><?= e((string) ($os['dataReconhecimento'] ?? 'N/D')) ?></td>
              <td class="py-1.5 pr-3 text-text"><?= e((string) ($os['fonteFinanceira'] ?? 'nenhuma')) ?></td>
              <td class="py-1.5 pr-3 text-right text-text tabular-nums data-valor-sensivel"><?= $os['receitaEfetiva'] === null ? 'N/D' : dinheiro((float) $os['receitaEfetiva']) ?></td>
              <td class="py-1.5 pr-3 text-right text-text tabular-nums data-valor-sensivel"><?= dinheiro((float) ($os['custoComponentes'] ?? 0)) ?></td>
              <td class="py-1.5 pr-3 text-right text-text tabular-nums data-valor-sensivel"><?= $contrib === null ? 'N/D' : dinheiro((float) $contrib) ?></td>
              <td class="max-w-[30rem] py-1.5 pr-3 text-text"><?= e((string) ($os['motivo'] ?? '')) ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Qualidade e cobertura dos dados -->
  <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-qualidade">
    <h2 id="titulo-qualidade" class="text-heading-sm text-text mb-3">Qualidade e cobertura dos dados</h2>
    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2">
      <div class="rounded-lg border border-border bg-surface p-3">
        <p class="text-caption text-muted mb-1">Reconhecimento temporal e financeiro</p>
        <p class="text-sm text-text">Toda OS entra pela <code class="text-xs bg-surface-subtle px-1.5 rounded">dataInicial</code>. Receita somente com lançamento válido, baixa e <code class="text-xs bg-surface-subtle px-1.5 rounded">data_pagamento</code> válido; sem receita, uma OS efetivamente encerrada/entregue e sem valor a receber usa a <code class="text-xs bg-surface-subtle px-1.5 rounded">dataFinal</code> efetiva. <code>os_historico</code> é evidência auxiliar, nunca requisito exclusivo.</p>
      </div>
      <div class="rounded-lg border border-border bg-surface p-3">
        <p class="text-caption text-muted mb-1">Cobertura do universo</p>
        <p class="text-sm text-text">
          Reconhecidas: <span class="font-semibold"><?= (int) ($radar['cobertura']['reconhecidas'] ?? 0) ?></span> · pendentes: <span class="font-semibold"><?= (int) ($radar['cobertura']['pendentes'] ?? 0) ?></span> · exceções: <span class="font-semibold"><?= (int) ($radar['cobertura']['excecoes'] ?? 0) ?></span>.
          Partição exata: <span class="font-semibold"><?= ! empty($radar['cobertura']['particao_exata']) ? 'sim' : 'não' ?></span>; sem duplicação: <span class="font-semibold"><?= ! empty($radar['reconciliacao']['sem_duplicacao']) ? 'sim' : 'não' ?></span>.
        </p>
      </div>
    </div>
    <div class="rounded-lg border border-border bg-surface p-3 mt-3">
      <p class="text-caption text-muted mb-1">Dupla contagem</p>
      <p class="text-sm text-text">Custos das peças já são subtraídos das OS (custo histórico). Lançamentos de compra de estoque e faturas de OS/Venda não são somados como despesa operacional desta camada. Transferências, empréstimos e despesas de capital não entram.</p>
    </div>
  </section>

  <!-- Explicação dos cálculos -->
  <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-metodo">
    <h2 id="titulo-metodo" class="text-heading-sm text-text mb-3">Como os valores são calculados</h2>
    <ul class="list-disc list-inside space-y-2 text-sm text-text">
      <li><strong>Receita efetiva da OS:</strong> lançamento válido vinculado à OS, baixado e com data de pagamento válida; associações legadas só entram quando exatas e unívocas.</li>
      <li><strong>Custo histórico dos componentes:</strong> custo de produtos/serviços consumidos na OS (<code>produtos_os.custo_total</code>), sem recalcular com preços atuais do cadastro.</li>
      <li><strong>Contribuição operacional da OS:</strong> receita efetiva − custo histórico dos componentes.</li>
      <li><strong>Ticket médio positivo:</strong> soma das contribuições positivas ÷ quantidade de OS positivas. <strong>Contribuição média geral:</strong> contribuição total reconhecida ÷ OS reconhecidas.</li>
      <li><strong>Despesa operacional:</strong> custos fixos por competência + despesas variáveis elegíveis, separando pagas e pendentes e excluindo faturas de OS/Venda, transferências e empréstimos.</li>
      <li><strong>Resultado operacional gerencial:</strong> contribuição total − despesa operacional; não é lucro contábil nem fluxo de caixa puro.</li>
      <li><strong>Média móvel ponderada 3M:</strong> soma das contribuições reconhecidas nos três meses ÷ soma das OS reconhecidas correspondentes; meses sem base ficam como N/D.</li>
    </ul>
  </section>

  <!-- Simulador do advisor gerencial -->
  <section class="<?= e($caixa) ?> min-w-0" aria-labelledby="titulo-simulador">
    <h2 id="titulo-simulador" class="text-heading-sm text-text mb-3">Advisor gerencial — simulação condicionada</h2>
    <p class="text-caption text-muted mb-3">A simulação separa o período acumulado, a média mensal histórica e um mês selecionado. Ela não altera o banco e não representa previsão garantida de demanda.</p>

    <div class="grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
      <div class="flex flex-col gap-2 sm:col-span-2 lg:col-span-3">
        <label class="text-caption text-muted" for="sim-base">Base temporal da simulação</label>
        <select id="sim-base" class="rounded-md border border-border bg-surface px-3 py-2 text-text focus:border-primary focus:outline-none">
          <option value="periodo">Período acumulado (janela selecionada)</option>
          <option value="media_mensal">Média mensal histórica da janela</option>
          <?php foreach ((array) ($radar['serie'] ?? []) as $linha) { ?>
            <option value="<?= e((string) ($linha['mes'] ?? '')) ?>">Mês selecionado: <?= e(labelMesPhp($linha['mes'] ?? '')) ?></option>
          <?php } ?>
        </select>
        <p id="sim-periodo-descricao" class="text-caption text-muted">A base será preenchida pelos totais do mesmo período mostrado acima.</p>
      </div>
      <div class="flex flex-col gap-2">
        <label class="text-caption text-muted" for="sim-os">OS produtivas na base</label>
        <input type="text" inputmode="decimal" id="sim-os" value="<?= (int) ($radar['totais']['os_positivas'] ?? 0) ?>" min="0" class="rounded-md border border-border bg-surface px-3 py-2 text-text focus:border-primary focus:outline-none">
      </div>
      <div class="flex flex-col gap-2">
        <label class="text-caption text-muted" for="sim-contrib">Ticket médio positivo</label>
        <input type="text" inputmode="decimal" id="sim-contrib" value="<?= ($radar['totais']['ticket_medio_positivo'] ?? null) === null ? '' : dinheiroRaw((float) $radar['totais']['ticket_medio_positivo']) ?>" min="0" class="rounded-md border border-border bg-surface px-3 py-2 text-text focus:border-primary focus:outline-none">
      </div>
      <div class="flex flex-col gap-2">
        <label class="text-caption text-muted" for="sim-despesa">Despesas da base</label>
        <input type="text" inputmode="decimal" id="sim-despesa" value="<?= dinheiroRaw((float) ($radar['totais']['despesa_total'] ?? 0)) ?>" min="0" class="rounded-md border border-border bg-surface px-3 py-2 text-text focus:border-primary focus:outline-none">
      </div>
      <div class="flex flex-col gap-2">
        <label class="text-caption text-muted" for="sim-meta">Meta de resultado da base</label>
        <input type="text" inputmode="decimal" id="sim-meta" value="0" min="0" class="rounded-md border border-border bg-surface px-3 py-2 text-text focus:border-primary focus:outline-none">
      </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
      <button type="button" id="sim-calcular" class="rounded-md bg-primary text-on-primary px-4 py-2 text-sm font-medium hover:bg-primary-hover transition-colors">Calcular cenário</button>
    </div>

    <div id="sim-resultado" class="mt-4 hidden rounded-lg border border-border bg-surface p-3"></div>

    <div class="mt-3 rounded-lg border border-border bg-surface p-3 text-sm text-text">
      <p class="text-caption text-muted mb-1">Alternativas matemáticas condicionais para atingir a meta:</p>
      <ul class="list-disc list-inside space-y-1 text-sm" id="sim-alternativas"></ul>
      <p class="mt-2 text-caption text-muted">Hipóteses e limitações: custos variáveis proporcionais, capacidade fixa e nenhuma previsão comprovada de demanda. Dados históricos e cenários modificados pelo usuário são separados; o simulador não modifica dados do banco.</p>
    </div>
  </section>
</div>

<?php
function labelMesPhp($mes) {
  $nomes = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  $partes = explode('-', (string) $mes);
  if (count($partes) === 2) {
    return $nomes[(int) $partes[1] - 1] . '/' . substr((string) $partes[0], -2);
  }
  return (string) $mes;
}
function dinheiroRaw($v) {
  // Valores monetários entram em texto para aceitar pt-BR e formato canônico;
  // a validação/normalização segura ocorre no módulo do Advisor.
  return number_format((float) $v, 2, '.', '');
}
?>
