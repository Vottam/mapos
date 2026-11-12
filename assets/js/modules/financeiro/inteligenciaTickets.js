// Inteligência de Tickets — página detalhada (views/mapos/inteligencia_tickets.php).
//
// Desenha 3 gráficos Chart.js 4 ampliados a partir do page_data "inteligencia-dados"
// e executa o simulador do advisor gerencial (apenas no cliente, sem alterar o banco).

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

const CORES = {
  texto: corDoToken('text'),
  textoFraco: corDoToken('muted'),
  borda: corDoToken('border'),
  positivo: '#16a34a',
  positivoSuave: 'rgba(22, 163, 74, 0.12)',
  negativo: '#dc2626',
  negativoSuave: 'rgba(220, 38, 38, 0.12)',
  zero: '#ca8a04',
  fundo: corDoToken('surface'),
};

function destruir(canvas) {
  const existente = Chart.getChart(canvas);
  if (existente) existente.destroy();
}

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
    callbacks: {
      title: (itens) => (itens && itens[0] ? itens[0].label : ''),
      label: (contexto) => {
        const valor = contexto.parsed.y ?? contexto.parsed ?? 0;
        const rotulo = contexto.dataset.label || '';
        if (OCULTO() && monetario) {
          return [rotulo, '—'];
        }
        return `${rotulo}: ${moeda(valor)}`;
      },
    },
  };
}

function escalaY(monetario) {
  return {
    ticks: {
      color: CORES.textoFraco,
      font: { size: 11 },
      callback: monetario ? (v) => moeda(v) : undefined,
    },
    grid: { color: CORES.borda, drawBorder: false },
    beginAtZero: true,
    ...(monetario
      ? {
          title: {
            display: true,
            text: 'R$/OS produtiva',
            color: CORES.textoFraco,
            font: { size: 11 },
          },
        }
      : {}),
  };
}

export default async function initInteligenciaTickets(el) {
  const doc = el.ownerDocument || document;

  try {
    await carregarScript(CHART_URL);
  } catch (erro) {
    console.error('[inteligenciaTickets] falha ao carregar Chart.js', erro);
    return;
  }
  if (typeof Chart === 'undefined') {
    console.error('[inteligenciaTickets] Chart.js não disponível');
    return;
  }

  const dados = lerJson('inteligencia-dados', null, doc);
  if (!dados || !dados.radar || !Array.isArray(dados.radar.serie)) {
    return;
  }

  const radar = dados.radar;
  const serie = radar.serie || [];
  const aproveitamento = radar.aproveitamento || {};
  const mediaMovel3M = radar.mediaMovel3M || [];

  const meses = serie.map((s) => s.mes);
  const contribuicaoMedia = serie.map((s) => Number(s.media_contribuicao_por_os_produtiva) || 0);
  const despesaPorOs = serie.map((s) => Number(s.despesa_por_os_produtiva) || 0);
  const resultado = serie.map((s) => Number(s.resultado_operacional) || 0);

  // --- A. Principal: contribuição média vs despesa distribuída ---
  const canvasA = doc.getElementById('inteligencia-principal');
  if (canvasA) {
    destruir(canvasA);
    new Chart(canvasA.getContext('2d'), {
      type: 'line',
      data: {
        labels: meses.map(labelMes),
        datasets: [
          {
            label: 'Contribuição média por OS produtiva',
            data: contribuicaoMedia,
            borderColor: CORES.positivo,
            backgroundColor: CORES.positivoSuave,
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
            segment: {
              borderColor: (ctx) => {
                const i = ctx.p0Index;
                const c = Number(contribuicaoMedia[i] || 0);
                const d = Number(despesaPorOs[i] || 0);
                return c >= d ? CORES.positivo : CORES.negativo;
              },
            },
          },
          {
            label: 'Despesa operacional distribuída por OS produtiva',
            data: despesaPorOs,
            borderColor: CORES.negativo,
            backgroundColor: CORES.negativoSuave,
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
            segment: {
              borderColor: (ctx) => {
                const i = ctx.p0Index;
                const c = Number(contribuicaoMedia[i] || 0);
                const d = Number(despesaPorOs[i] || 0);
                return c >= d ? CORES.positivo : CORES.negativo;
              },
            },
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: CORES.textoFraco, font: { size: 11 }, boxWidth: 14 },
          },
          tooltip: tooltipBuilder(true),
        },
        scales: {
          x: {
            ticks: { color: CORES.textoFraco, font: { size: 11 } },
            grid: { color: CORES.borda, drawBorder: false },
          },
          y: escalaY(true),
        },
        animation: { duration: 600 },
      },
    });
  }

  // --- B. Aproveitamento das OS concluídas (donut) ---
  const canvasB = doc.getElementById('inteligencia-aproveitamento');
  if (canvasB) {
    destruir(canvasB);
    const positiva = Number(aproveitamento.positiva || 0);
    const zero = Number(aproveitamento.zero || 0);
    const negativa = Number(aproveitamento.negativa || 0);
    const totalComDados = positiva + zero + negativa;
    const container = canvasB.parentElement;
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
            backgroundColor: [CORES.positivo, CORES.zero, CORES.negativo],
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
  const canvasC = doc.getElementById('inteligencia-tendencia');
  if (canvasC) {
    destruir(canvasC);
    new Chart(canvasC.getContext('2d'), {
      type: 'line',
      data: {
        labels: mediaMovel3M.map((m) => labelMes(m.mes)),
        datasets: [
          {
            label: 'Média móvel ponderada 3M',
            data: mediaMovel3M.map((m) => Number(m.media_movel_3m) || 0),
            borderColor: CORES.texto,
            backgroundColor: 'rgba(107, 114, 128, 0.12)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: CORES.textoFraco, font: { size: 11 }, boxWidth: 14 },
          },
          tooltip: tooltipBuilder(true),
        },
        scales: {
          x: {
            ticks: { color: CORES.textoFraco, font: { size: 11 } },
            grid: { color: CORES.borda, drawBorder: false },
          },
          y: escalaY(true),
        },
        animation: { duration: 600 },
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

  // --- Simulador do advisor gerencial (apenas cliente, sem tocar no banco) ---
  const inputOs = doc.getElementById('sim-os');
  const inputContrib = doc.getElementById('sim-contrib');
  const inputDespesa = doc.getElementById('sim-despesa');
  const inputMeta = doc.getElementById('sim-meta');
  const btnCalcular = doc.getElementById('sim-calcular');
  const boxResultado = doc.getElementById('sim-resultado');
  const listaAlternativas = doc.getElementById('sim-alternativas');

  if (!btnCalcular || !boxResultado || !listaAlternativas) {
    return;
  }

  boxResultado.classList.add('hidden');

  function parseNumero(el, padrao = 0) {
    if (!el) return padrao;
    const v = parseFloat(String(el.value).replace('.', '').replace(',', '.'));
    return Number.isFinite(v) ? v : padrao;
  }

  btnCalcular.addEventListener('click', () => {
    const osProdutivas = parseNumero(inputOs, 0);
    const contribMedia = parseNumero(inputContrib, 0);
    const despesas = parseNumero(inputDespesa, 0);
    const meta = parseNumero(inputMeta, 0);

    const resultadoAtual = osProdutivas * contribMedia - despesas;
    const aproveitamentoAtual = osProdutivas > 0 ? ((Number(aproveitamento.positiva || 0) / osProdutivas) * 100).toFixed(1) : '0';

    boxResultado.classList.remove('hidden');
    boxResultado.innerHTML = `
      <div class="flex flex-wrap gap-4 text-sm">
        <div class="flex flex-col gap-1">
          <span class="text-caption text-muted">Resultado atual estimado</span>
          <span class="font-semibold text-text">${moeda(resultadoAtual)}</span>
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-caption text-muted">Meta mensal</span>
          <span class="font-semibold text-text">${moeda(meta)}</span>
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-caption text-muted">Faltam para a meta</span>
          <span class="font-semibold text-text">${moeda(Math.max(0, meta - resultadoAtual))}</span>
        </div>
      </div>
    `;

    listaAlternativas.innerHTML = '';
    const add = (titulo, conta, resultadoAlt) => {
      const li = doc.createElement('li');
      li.className = 'flex items-start gap-2';
      li.innerHTML = `
        <span class="flex-shrink-0 text-caption text-muted">${titulo}.</span>
        <span class="text-text">${conta} — resultado estimado ${moeda(resultadoAlt)}.</span>
      `;
      listaAlternativas.appendChild(li);
    };

    // A. Aumentar volume produtivo (mesma contribuição média e despesas)
    const osNecessariasA = meta > 0 && contribMedia > 0 ? Math.ceil((meta + despesas) / contribMedia) : osProdutivas;
    const resultadoA = osNecessariasA * contribMedia - despesas;
    add('A. Aumentar o volume produtivo', `levar de ${osProdutivas} para ${osNecessariasA} OS produtivas/mês (mesma contribuição média e despesas)`, resultadoA);

    // B. Aumentar contribuição média por OS (mesmo volume e despesas)
    const contribNecessariaB = osProdutivas > 0 ? (meta + despesas) / osProdutivas : 0;
    const resultadoB = osProdutivas * contribNecessariaB - despesas;
    add('B. Aumentar a contribuição média por OS', `de ${moeda(contribMedia)} para ${moeda(contribNecessariaB)} por OS (mesmo volume e despesas)`, resultadoB);

    // C. Melhorar aproveitamento: converter OS não produtivas em produtivas — modelado como
    //    aumento efetivo da contribuição total sem aumentar o número de OS cadastradas.
    const fatorC = osProdutivas > 0 ? (meta + despesas) / (osProdutivas * contribMedia) : 1;
    const resultadoC = osProdutivas * contribMedia * fatorC - despesas;
    add('C. Melhorar o aproveitamento das OS recebidas', `multiplicador efetivo ${fatorC.toFixed(2)}x na contribuição total (mesmo volume cadastrado e despesas)`, resultadoC);

    // D. Reduzir despesas operacionais (mesmo volume e contribuição)
    const despesaNecessariaD = osProdutivas * contribMedia - meta;
    const resultadoD = osProdutivas * contribMedia - Math.max(0, despesaNecessariaD);
    add('D. Reduzir as despesas operacionais', `de ${moeda(despesas)} para ${moeda(Math.max(0, despesaNecessariaD))} (mesmo volume e contribuição média)`, resultadoD);

    const nota = doc.createElement('p');
    nota.className = 'mt-3 text-caption text-muted';
    nota.textContent =
      'Hipóteses: custos variáveis proporcionais, capacidade fixa e nenhuma previsão comprovada de demanda. ' +
      'O simulador não modifica dados do banco e não afirma preços acima ou abaixo do mercado.';
    listaAlternativas.parentNode.appendChild(nota);
  });
}
