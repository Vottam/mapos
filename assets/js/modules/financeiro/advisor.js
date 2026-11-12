const CENTAVOS = 100;

function centsFromNumber(value) {
  if (!Number.isFinite(value) || value < 0) {
    return null;
  }
  const cents = Math.round(value * CENTAVOS);
  return Number.isSafeInteger(cents) ? cents : null;
}

function centsFromParts(inteiro, decimal = '') {
  const valorInteiro = inteiro === '' ? 0 : Number(inteiro);
  if (!Number.isSafeInteger(valorInteiro) || valorInteiro < 0) {
    return null;
  }
  const centavos = decimal.padEnd(2, '0');
  const valorCentavos = centavos === '' ? 0 : Number(centavos);
  if (!Number.isInteger(valorCentavos) || valorCentavos < 0 || valorCentavos > 99) {
    return null;
  }
  const total = valorInteiro * CENTAVOS + valorCentavos;
  return Number.isSafeInteger(total) ? total : null;
}

/**
 * Converte dinheiro não-negativo para centavos sem apagar pontuação decimal.
 * Aceita pt-BR (90,00; 1.234,56), canônico (1234.56) e inteiros.
 * Formatos ambíguos como 1.234 são rejeitados.
 */
export function parseMonetario(value) {
  if (typeof value === 'number') {
    return centsFromNumber(value);
  }
  if (typeof value !== 'string') {
    return null;
  }

  const raw = value.trim();
  if (raw === '' || /[^0-9.,]/.test(raw)) {
    return null;
  }

  const commas = (raw.match(/,/g) || []).length;
  const dots = (raw.match(/\./g) || []).length;

  if (commas > 1) {
    return null;
  }

  if (commas === 1) {
    const [whole, decimal] = raw.split(',');
    if (!/^\d{1,2}$/.test(decimal)) {
      return null;
    }
    if (dots > 0 && !/^\d{1,3}(?:\.\d{3})+$/.test(whole)) {
      return null;
    }
    return centsFromParts(whole.replaceAll('.', ''), decimal);
  }

  if (dots === 0) {
    return /^\d+$/.test(raw) ? centsFromParts(raw) : null;
  }

  // Dot-only input is canonical decimal only. A dot with three fractional
  // digits is ambiguous with a Brazilian thousands separator and is rejected.
  if (!/^\d+\.\d{1,2}$/.test(raw)) {
    return null;
  }
  const [whole, decimal] = raw.split('.');
  return centsFromParts(whole, decimal);
}

export function parseQuantidade(value, allowFractional = false) {
  if (typeof value === 'number') {
    if (!Number.isFinite(value) || value < 0 || (!allowFractional && !Number.isInteger(value))) {
      return null;
    }
    return value;
  }
  if (typeof value !== 'string') {
    return null;
  }
  const raw = value.trim();
  const pattern = allowFractional ? /^\d+(?:[.,]\d+)?$/ : /^\d+$/;
  if (!pattern.test(raw)) {
    return null;
  }
  const quantidade = Number(raw.replace(',', '.'));
  if (!Number.isFinite(quantidade) || quantidade < 0 || (!allowFractional && !Number.isInteger(quantidade))) {
    return null;
  }
  return quantidade;
}

export function arredondarCentavos(value) {
  if (!Number.isFinite(value)) {
    return null;
  }
  const cents = Math.round(value);
  return Number.isSafeInteger(cents) && cents >= 0 ? cents : null;
}

export function formatarCentavos(cents) {
  if (!Number.isFinite(cents)) {
    return 'Sem base';
  }
  const normalized = Object.is(cents, -0) ? 0 : cents;
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(normalized / CENTAVOS);
}

function toCents(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  return parseMonetario(Number(value));
}

function safeOs(value) {
  return Number.isFinite(Number(value)) && Number(value) >= 0 ? Number(value) : 0;
}

function periodoLabel(radar) {
  const meses = Array.isArray(radar?.janela?.meses) ? radar.janela.meses : [];
  if (meses.length === 0) {
    return 'período selecionado';
  }
  return `${meses[0]} a ${meses[meses.length - 1]}`;
}

/** Constrói bases explicitamente separadas por período acumulado, média mensal e mês. */
export function construirBasesTemporais(radar) {
  const total = radar?.totais || {};
  const serie = Array.isArray(radar?.serie) ? radar.serie : [];
  const meses = Array.isArray(radar?.janela?.meses) && radar.janela.meses.length > 0
    ? radar.janela.meses.length
    : serie.length;
  const osTotal = safeOs(total.os_positivas ?? total.os_produtivas);
  const contribuicaoTotal = toCents(total.contribuicao_total) ?? 0;
  const ticketPositivo = toCents(total.ticket_medio_positivo);
  const despesaTotal = toCents(total.despesa_total) ?? 0;
  const mediaContribuicao = ticketPositivo ?? (osTotal > 0 ? Math.round(contribuicaoTotal / osTotal) : null);
  const mediaMensal = meses > 0 ? osTotal / meses : 0;
  const despesaMediaMensal = meses > 0 ? Math.round(despesaTotal / meses) : null;

  const bases = {
    periodo: {
      id: 'periodo',
      tipo: 'período acumulado',
      label: `Período acumulado (${periodoLabel(radar)})`,
      os: osTotal,
      contribCents: mediaContribuicao,
      despesaCents: despesaTotal,
      fonte: 'totais do mesmo período',
    },
    media_mensal: {
      id: 'media_mensal',
      tipo: 'média mensal histórica',
      label: `Média mensal da janela (${meses} meses)`,
      os: mediaMensal,
      contribCents: mediaContribuicao,
      despesaCents: despesaMediaMensal,
      fonte: 'totais do mesmo período divididos pela quantidade de meses',
    },
    meses: {},
  };

  for (const item of serie) {
    const os = safeOs(item.os_positivas ?? item.os_produtivas);
    const ticket = toCents(item.ticket_medio_positivo)
      ?? toCents(item.media_contribuicao_por_os_produtiva)
      ?? (os > 0 ? Math.round((toCents(item.contribuicao_total) ?? 0) / os) : null);
    const contribTotal = toCents(item.contribuicao_total) ?? 0;
    bases.meses[item.mes] = {
      id: item.mes,
      tipo: 'mês selecionado',
      label: item.mes,
      os,
      contribCents: ticket,
      contribTotalCents: contribTotal,
      despesaCents: toCents(item.despesa_total) ?? 0,
      fonte: `série mensal de ${item.mes}`,
    };
  }

  return bases;
}

/** Calcula o Advisor em centavos e nunca cria recomendações regressivas. */
export function calcularAdvisor({ os, contribCents, despesaCents, metaCents, oportunidadesComparaveis = 0, reconhecidas = null, positivas = null }) {
  const volume = safeOs(os);
  const contribuicao = Number.isSafeInteger(contribCents) && contribCents >= 0 ? contribCents : 0;
  const despesa = Number.isSafeInteger(despesaCents) && despesaCents >= 0 ? despesaCents : 0;
  const meta = Number.isSafeInteger(metaCents) && metaCents >= 0 ? metaCents : 0;
  const resultadoCents = Math.round(volume * contribuicao - despesa);
  const distanciaCents = Math.max(0, meta - resultadoCents);
  const metaAtingida = resultadoCents >= meta;
  const alternativas = [];

  if (metaAtingida) {
    alternativas.push({
      id: 'none',
      titulo: 'Sem ação necessária',
      texto: 'Meta atingida neste cenário; nenhuma alteração nula ou regressiva é recomendada',
      resultadoCents,
    });
    return { volume, contribuicao, despesa, meta, resultadoCents, distanciaCents, metaAtingida, alternativas };
  }

  if (contribuicao > 0) {
    const totalNecessario = Math.max(0, Math.ceil((meta + despesa) / contribuicao));
    const adicionais = Math.max(0, totalNecessario - volume);
    alternativas.push({
      id: 'volume',
      titulo: 'A. Aumentar OS produtivas',
      texto: `total mínimo necessário = ${totalNecessario} OS; adicionais = ${adicionais} OS, mantendo ${formatarCentavos(contribuicao)} por OS`,
      resultadoCents: Math.round(totalNecessario * contribuicao - despesa),
    });
  } else {
    alternativas.push({
      id: 'volume',
      titulo: 'A. Aumentar OS produtivas',
      texto: 'Não aplicável enquanto a contribuição média por OS for zero; é necessária uma contribuição positiva',
      resultadoCents: null,
    });
  }

  if (volume > 0) {
    const contribuicaoNecessaria = Math.max(0, Math.ceil((meta + despesa) / volume));
    alternativas.push({
      id: 'contribuicao',
      titulo: 'B. Aumentar contribuição média',
      texto: `contribuição mínima = ${formatarCentavos(contribuicaoNecessaria)} por OS, mantendo ${formatarQuantidade(volume)} OS`,
      resultadoCents: Math.round(volume * contribuicaoNecessaria - despesa),
    });
  } else {
    alternativas.push({
      id: 'contribuicao',
      titulo: 'B. Aumentar contribuição média',
      texto: 'Não aplicável com quantidade de OS igual a zero; não há base para dividir a meta',
      resultadoCents: null,
    });
  }

  if (Number.isFinite(reconhecidas) && reconhecidas > 0) {
    const positivasBase = Number.isFinite(positivas) ? Math.max(0, Math.floor(positivas)) : Math.floor(volume);
    const positivasNecessarias = contribuicao > 0 ? Math.ceil((meta + despesa) / contribuicao) : Infinity;
    if (positivasNecessarias <= reconhecidas && positivasNecessarias > positivasBase) {
      const taxaMeta = (positivasNecessarias / reconhecidas) * 100;
      alternativas.push({
        id: 'aproveitamento',
        titulo: 'C. Melhorar taxa de aproveitamento',
        texto: `elevar para ${positivasNecessarias}/${reconhecidas} OS positivas (${taxaMeta.toFixed(1)}%), sem aumentar o universo reconhecido`,
        resultadoCents: Math.round(positivasNecessarias * contribuicao - despesa),
      });
    } else {
      alternativas.push({
        id: 'aproveitamento',
        titulo: 'C. Melhorar taxa de aproveitamento',
        texto: 'Não aplicável isoladamente: a meta exige mais OS positivas do que a base reconhecida comporta',
        resultadoCents: null,
      });
    }
  } else if (Number.isFinite(oportunidadesComparaveis) && oportunidadesComparaveis > 0) {
    alternativas.push({
      id: 'conversao',
      titulo: 'C. Melhorar conversão',
      texto: 'Base comparável detectada; a taxa deve ser calculada somente com oportunidades e capacidade informadas',
      resultadoCents: null,
    });
  } else {
    alternativas.push({
      id: 'conversao',
      titulo: 'C. Melhorar conversão',
      texto: 'Não aplicável: não há base comparável válida de oportunidades/capacidade no Radar; não é possível afirmar viabilidade',
      resultadoCents: null,
    });
  }

  const despesaMaxima = Math.round(volume * contribuicao - meta);
  if (despesaMaxima >= 0) {
    const reducao = Math.max(0, despesa - despesaMaxima);
    alternativas.push({
      id: 'despesa',
      titulo: 'D. Reduzir despesas',
      texto: `despesa máxima para equilíbrio = ${formatarCentavos(despesaMaxima)}; redução necessária = ${formatarCentavos(reducao)}`,
      resultadoCents: Math.round(volume * contribuicao - Math.max(0, despesa - reducao)),
    });
  } else {
    alternativas.push({
      id: 'despesa',
      titulo: 'D. Reduzir despesas',
      texto: 'Não aplicável isoladamente: a contribuição do cenário é insuficiente para atingir a meta mesmo com despesa zero',
      resultadoCents: null,
    });
  }

  return { volume, contribuicao, despesa, meta, resultadoCents, distanciaCents, metaAtingida, alternativas };
}

export function formatarQuantidade(value, maximumFractionDigits = 2) {
  return new Intl.NumberFormat('pt-BR', { maximumFractionDigits }).format(value);
}
