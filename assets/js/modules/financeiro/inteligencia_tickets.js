// Inteligência de Tickets — página detalhada (views/mapos/inteligencia_tickets.php).
//
// Desenha 3 gráficos Chart.js 4 ampliados a partir do page_data "inteligencia-dados"
// e executa o simulador do advisor gerencial (apenas no cliente, sem alterar o banco).

import { lerJson } from '../../lib/dados.js?v=fix-sidebar-install-final-20261031';
import { carregarScript } from '../../lib/script.js?v=fix-sidebar-install-final-20261031';
import { construirBasesTemporais, calcularAdvisor, formatarCentavos, formatarQuantidade, parseMonetario, parseQuantidade } from './advisor.js?v=fix-advisor-financial-20261102-r4';

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
            text: 'R$/OS reconhecida',
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
  const contribuicaoMedia = serie.map((s) => {
    const valor = Number(s.contribuicao_media_geral);
    return Number(s.os_reconhecidas || 0) > 0 && Number.isFinite(valor) ? valor : null;
  });
  const despesaPorOs = serie.map((s) => {
    const valor = Number(s.despesa_por_os_reconhecida);
    return Number(s.os_reconhecidas || 0) > 0 && Number.isFinite(valor) ? valor : null;
  });
  const resultado = serie.map((s) => {
    const valor = Number(s.resultado_operacional);
    return (Number(s.os_reconhecidas || 0) > 0 || Number(s.despesa_total || 0) > 0) && Number.isFinite(valor) ? valor : null;
  });

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
            label: 'Contribuição média geral por OS reconhecida',
            data: contribuicaoMedia,
            borderColor: CORES.positivo,
            backgroundColor: CORES.positivoSuave,
            fill: true,
            spanGaps: false,
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
            label: 'Despesa operacional por OS reconhecida',
            data: despesaPorOs,
            borderColor: CORES.negativo,
            backgroundColor: CORES.negativoSuave,
            fill: true,
            spanGaps: false,
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
            data: mediaMovel3M.map((m) => {
              const valor = Number(m.media_movel_3m);
              return m.media_movel_3m == null || !Number.isFinite(valor) ? null : valor;
            }),
            borderColor: CORES.texto,
            backgroundColor: 'rgba(107, 114, 128, 0.12)',
            fill: true,
            spanGaps: false,
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
  const inputBase = doc.getElementById('sim-base');
  const inputOs = doc.getElementById('sim-os');
  const inputContrib = doc.getElementById('sim-contrib');
  const inputDespesa = doc.getElementById('sim-despesa');
  const inputMeta = doc.getElementById('sim-meta');
  const descricaoBase = doc.getElementById('sim-periodo-descricao');
  const btnCalcular = doc.getElementById('sim-calcular');
  const boxResultado = doc.getElementById('sim-resultado');
  const listaAlternativas = doc.getElementById('sim-alternativas');

  if (!btnCalcular || !boxResultado || !listaAlternativas || !inputBase) {
    return;
  }

  const bases = construirBasesTemporais(radar);

  function baseSelecionada() {
    return bases[inputBase.value] || bases.meses[inputBase.value] || bases.periodo;
  }

  function valorInput(cents) {
    return cents == null ? '' : (cents / 100).toFixed(2);
  }

  function selecionarBase() {
    const base = baseSelecionada();
    inputOs.value = formatarQuantidade(base.os, 6);
    inputContrib.value = valorInput(base.contribCents);
    inputDespesa.value = valorInput(base.despesaCents);
    if (descricaoBase) {
      descricaoBase.textContent = `${base.label}. Fonte: ${base.fonte}. Valores abaixo podem ser alterados como hipótese.`;
    }
    boxResultado.classList.add('hidden');
    listaAlternativas.replaceChildren();
  }

  function adicionarMetrica(label, valor) {
    const bloco = doc.createElement('div');
    bloco.className = 'flex flex-col gap-1';
    const titulo = doc.createElement('span');
    titulo.className = 'text-caption text-muted';
    titulo.textContent = label;
    const conteudo = doc.createElement('span');
    conteudo.className = 'font-semibold text-text';
    conteudo.textContent = valor;
    bloco.append(titulo, conteudo);
    boxResultado.appendChild(bloco);
  }

  function mostrarErro(mensagem) {
    boxResultado.replaceChildren();
    boxResultado.classList.remove('hidden');
    const erro = doc.createElement('p');
    erro.className = 'text-danger text-sm';
    erro.textContent = mensagem;
    boxResultado.appendChild(erro);
    listaAlternativas.replaceChildren();
  }

  function calcularCenario() {
    const base = baseSelecionada();
    const permiteFracao = base.id === 'media_mensal';
    const osProdutivas = parseQuantidade(inputOs.value, permiteFracao);
    const contribCents = parseMonetario(inputContrib.value);
    const despesaCents = parseMonetario(inputDespesa.value);
    const metaCents = parseMonetario(inputMeta.value);

    if (osProdutivas === null || contribCents === null || despesaCents === null || metaCents === null) {
      mostrarErro('Entrada inválida. Use quantidade não-negativa e valores monetários como 90,00, 1.234,56 ou 1234.56.');
      return;
    }

    const calculo = calcularAdvisor({
      os: osProdutivas,
      contribCents,
      despesaCents,
      metaCents,
      reconhecidas: Number(aproveitamento.reconhecida ?? 0),
      positivas: Number(aproveitamento.positiva ?? osProdutivas),
    });
    boxResultado.replaceChildren();
    boxResultado.classList.remove('hidden');
    adicionarMetrica('Cenário analisado', `${base.label} — simulação condicionada`);
    adicionarMetrica('Resultado estimado', formatarCentavos(calculo.resultadoCents));
    adicionarMetrica('Distância até a meta', formatarCentavos(calculo.distanciaCents));
    adicionarMetrica('Estado da meta', calculo.metaAtingida ? 'Meta atingida neste cenário' : 'Meta não atingida neste cenário');

    listaAlternativas.replaceChildren();
    for (const alternativa of calculo.alternativas) {
      const li = doc.createElement('li');
      li.className = 'flex items-start gap-2';
      const titulo = doc.createElement('span');
      titulo.className = 'flex-shrink-0 text-caption text-muted';
      titulo.textContent = `${alternativa.titulo}:`;
      const texto = doc.createElement('span');
      texto.className = 'text-text';
      texto.textContent = alternativa.resultadoCents == null
        ? `${alternativa.texto}.`
        : `${alternativa.texto} — resultado estimado ${formatarCentavos(alternativa.resultadoCents)}.`;
      li.append(titulo, texto);
      listaAlternativas.appendChild(li);
    }
  }

  inputBase.addEventListener('change', selecionarBase);
  btnCalcular.addEventListener('click', calcularCenario);
  selecionarBase();
}
