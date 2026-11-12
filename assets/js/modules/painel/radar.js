// Radar de Rentabilidade — Inteligência de Tickets.
//
// Desenha 3 gráficos Chart.js 4 a partir do page_data "radar-dados"
// (views/mapos/painel.php e views/mapos/inteligencia_tickets.php).
//
// - A. Principal: contribuição média por OS produtiva vs despesa operacional
//   distribuída por OS produtiva, últimos 12 meses. Segmento verde quando a
//   contribuição supera a despesa; vermelho em insuficiência.
// - B. Aproveitamento das OS concluídas: positiva / zero / negativa (contagens).
// - C. Tendência: média móvel ponderada de 3 meses (soma das contribuições /
//   soma das OS produtivas, nunca média simples de tickets médios).
//
// Respeita html[data-valores="ocultos"] (botão de olho): mascara valores
// monetários nos tooltips; preserva contagens e percentuais.

import { lerJson } from '../../lib/dados.js?v=fix-sidebar-install-final-20261031';
import { carregarScript } from '../../lib/script.js?v=fix-sidebar-install-final-20261031';

const VENDOR = new URL('../../../vendor/', import.meta.url);
const CHART_URL = new URL('chart.js/chart.umd.min.js', VENDOR).href;

const OCULTO = () => document.documentElement.getAttribute('data-valores') === 'ocultos';

function corDoToken(nome) {
  return (
    getComputedStyle(document.documentElement).getPropertyValue(`--color-${nome}`).trim() ||
    '#6b7280'
  );
}

function moeda(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
    Number(v) || 0
  );
}

function labelMes(mes) {
  const [ano, m] = String(mes).split('-');
  const nomes = [
    'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
    'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez',
  ];
  return `${nomes[parseInt(m, 10) - 1]}/${String(ano).slice(2)}`;
}

function mesAtualIso() {
  const h = new Date();
  return `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}`;
}

const CORES = {
  texto: corDoToken('text'),
  textoFraco: corDoToken('muted'),
  borda: corDoToken('border'),
  positivo: '#16a34a',
  positivoSuave: 'rgba(22, 163, 74, 0.12)',
  negativo: '#dc2626',
  negativoSuave: 'rgba(220, 38, 38, 0.12)',
  zero: '#ca8a04',
  pendente: '#64748b',
  fundo: corDoToken('surface'),
};

function tooltipBuilder(monetario) {
  return {
    enabled: true,
    mode: 'index',
    intersect: false,
    backgroundColor: 'rgba(20, 20, 20, 0.88)',
    titleColor: '#ffffff',
    bodyColor: '#ffffff',
    borderColor: CORES.borda,
    borderWidth: 1,
    padding: 8,
    boxPadding: 4,
    callbacks: {
      title: (itens) => (itens && itens[0] ? itens[0].label : ''),
      label: (contexto) => {
        const valor = contexto.parsed.y;
        const rotulo = contexto.dataset.label || '';
        if (valor == null) {
          return `${rotulo}: sem base suficiente`;
        }
        if (OCULTO() && monetario) {
          return [rotulo, '—'];
        }
        return `${rotulo}: ${moeda(valor)}`;
      },
    },
  };
}

function opcoesComuns(monetario) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: {
        display: true,
        position: 'bottom',
        labels: { color: CORES.textoFraco, font: { size: 11 }, boxWidth: 14 },
      },
      tooltip: tooltipBuilder(monetario),
    },
    scales: {
      x: {
        ticks: { color: CORES.textoFraco, font: { size: 11 } },
        grid: { color: CORES.borda, drawBorder: false },
      },
      y: {
        ticks: { color: CORES.textoFraco, font: { size: 11 }, callback: (v) => moeda(v) },
        grid: { color: CORES.borda, drawBorder: false },
        beginAtZero: true,
      },
    },
    animation: { duration: 600 },
  };
}

function destruir(canvas) {
  const existente = Chart.getChart(canvas);
  if (existente) existente.destroy();
}

export default async function initRadar(el) {
  const doc = el.ownerDocument || document;

  try {
    await carregarScript(CHART_URL);
  } catch (erro) {
    console.error('[radar] falha ao carregar Chart.js', erro);
    return;
  }
  if (typeof Chart === 'undefined') {
    console.error('[radar] Chart.js não disponível');
    return;
  }

  const dados = lerJson('radar-dados', null, doc);
  if (!dados || !Array.isArray(dados.serie)) {
    return;
  }

  const serie = dados.serie || [];
  const totais = dados.totais || {};
  const aproveitamento = dados.aproveitamento || {};
  const mediaMovel3M = dados.mediaMovel3M || [];
  const cobertura = dados.cobertura || {};

  const meses = serie.map((s) => s.mes);
  const contribuicaoMedia = serie.map((s) => Number(s.os_reconhecidas || 0) > 0
    ? (s.contribuicao_media_geral == null ? null : Number(s.contribuicao_media_geral))
    : null);
  const despesaPorOs = serie.map((s) => Number(s.os_reconhecidas || 0) > 0
    ? (s.despesa_por_os_reconhecida == null ? null : Number(s.despesa_por_os_reconhecida))
    : null);
  const resultado = serie.map((s) => Number(s.os_reconhecidas || 0) > 0 || Number(s.despesa_total || 0) > 0
    ? Number(s.resultado_operacional)
    : null);
  const tendenciaData = mediaMovel3M.map((m) => m.media_movel_3m == null ? null : Number(m.media_movel_3m));

  // --- A. Principal: contribuição média vs despesa distribuída ---
  const canvasA = doc.getElementById('radar-principal');
  if (canvasA) {
    destruir(canvasA);
    new Chart(canvasA.getContext('2d'), {
      type: 'line',
      data: {
        labels: meses.map(labelMes),
        datasets: [
          {
            label: 'Contribuição média geral por OS reconhecida',
            data: contribuicaoMedia,
            borderColor: CORES.positivo,
            backgroundColor: CORES.positivoSuave,
            spanGaps: false,
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
            segment: {
              borderColor: (ctx) => {
                const i = ctx.p0Index;
                const c = contribuicaoMedia[i];
                const d = despesaPorOs[i];
                return c == null || d == null ? CORES.pendente : (c >= d ? CORES.positivo : CORES.negativo);
              },
              borderDash: (ctx) => {
                const i = ctx.p0Index;
                return meses[i] === mesAtualIso() ? [4, 3] : [];
              },
            },
          },
          {
            label: 'Despesa operacional por OS reconhecida',
            data: despesaPorOs,
            borderColor: CORES.negativo,
            backgroundColor: CORES.negativoSuave,
            spanGaps: false,
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
            segment: {
              borderColor: (ctx) => {
                const i = ctx.p0Index;
                const c = contribuicaoMedia[i];
                const d = despesaPorOs[i];
                return c == null || d == null ? CORES.pendente : (c >= d ? CORES.positivo : CORES.negativo);
              },
              borderDash: (ctx) => {
                const i = ctx.p0Index;
                return meses[i] === mesAtualIso() ? [4, 3] : [];
              },
            },
          },
        ],
      },
      options: {
        ...opcoesComuns(true),
        scales: {
          ...opcoesComuns(true).scales,
          y: {
            ...opcoesComuns(true).scales.y,
            title: {
              display: true,
              text: 'R$/OS produtiva',
              color: CORES.textoFraco,
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // --- B. Aproveitamento das OS concluídas (donut) ---
  const canvasB = doc.getElementById('radar-aproveitamento');
  if (canvasB) {
    destruir(canvasB);
    const positiva = Number(aproveitamento.positiva || 0);
    const zero = Number(aproveitamento.zero || 0);
    const negativa = Number(aproveitamento.negativa || 0);
    const totalComDados = positiva + zero + negativa;
    const container = canvasB.parentElement;
    const percentualAproveitamento = totalComDados > 0 ? Math.round((positiva / totalComDados) * 100) : null;
    const textoAproveitamento = percentualAproveitamento == null ? 'Sem base' : `${percentualAproveitamento}% das classificadas`;
    container?.querySelector('[data-radar-aproveitamento-centro]')?.replaceChildren(doc.createTextNode(textoAproveitamento));
    container?.querySelector('[data-radar-aproveitamento-centro-label]')?.replaceChildren(doc.createTextNode(textoAproveitamento));
    container?.querySelector('[data-radar-vazio]')?.remove();
    canvasB.hidden = false;
    if (totalComDados === 0) {
      canvasB.hidden = true;
      const vazio = doc.createElement('p');
      vazio.dataset.radarVazio = 'true';
      vazio.className = 'text-caption text-muted flex h-full items-center justify-center px-4 text-center';
      vazio.textContent = 'Dados insuficientes para exibir o aproveitamento.';
      container?.appendChild(vazio);
    } else {
      new Chart(canvasB.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Positiva', 'Zero', 'Negativa'],
        datasets: [
          {
            data: [positiva, zero, negativa],
            backgroundColor: [
              CORES.positivo,
              CORES.zero,
              CORES.negativo,
            ],
            borderColor: CORES.fundo,
            borderWidth: 2,
            hoverOffset: 16,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: CORES.textoFraco, font: { size: 11 }, boxWidth: 14 },
          },
          tooltip: {
            enabled: true,
            backgroundColor: 'rgba(20, 20, 20, 0.88)',
            titleColor: '#ffffff',
            bodyColor: '#ffffff',
            borderColor: CORES.borda,
            callbacks: {
              label: (contexto) => {
                const valor = contexto.parsed ?? 0;
                const pct = totalComDados > 0 ? ((valor / totalComDados) * 100).toFixed(1) : '0';
                return `${contexto.label}: ${valor} OS (${pct}%)`;
              },
              title: () => 'Aproveitamento',
            },
          },
        },
      },
    });
    }
  }

  // --- C. Tendência: média móvel ponderada de 3 meses ---
  const canvasC = doc.getElementById('radar-tendencia');
  if (canvasC) {
    destruir(canvasC);
    new Chart(canvasC.getContext('2d'), {
      type: 'line',
      data: {
        labels: mediaMovel3M.map((m) => labelMes(m.mes)),
        datasets: [
          {
            label: 'Média móvel ponderada 3M',
            data: tendenciaData,
            borderColor: CORES.texto,
            backgroundColor: 'rgba(107, 114, 128, 0.12)',
            spanGaps: false,
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            segment: {
              borderDash: (ctx) => {
                const i = ctx.p0Index;
                const item = mediaMovel3M[i];
                return item && !item.periodo_completo ? [4, 3] : [];
              },
            },
          },
        ],
      },
      options: {
        ...opcoesComuns(true),
        scales: {
          ...opcoesComuns(true).scales,
          y: {
            ...opcoesComuns(true).scales.y,
            title: {
              display: true,
              text: 'R$/OS produtiva (3M)',
              color: CORES.textoFraco,
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // Reage ao toggle de ocultar valores sem recarregar a página.
  const observer = new MutationObserver(() => {
    const instancias = Chart.instances instanceof Map
      ? Chart.instances.values()
      : Object.values(Chart.instances || {});
    for (const instancia of instancias) {
      instancia.update();
    }
  });
  observer.observe(doc.documentElement, { attributes: true, attributeFilter: ['data-valores'] });
}
