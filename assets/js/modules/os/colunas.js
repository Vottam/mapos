// Seletor de colunas da listagem de OS — FIX01D.
// A ordem é canônica e segue a tabela LIVE; v2 não reutiliza a preferência
// enxuta da FIX01C, mas preserva a chave antiga sem apagá-la.

export const DEFAULT_VISIBLE = Object.freeze([
    'idOs',
    'cliente',
    'tipo',
    'marca',
    'modelo',
    'data_inicial',
    'data_final',
    'vencimento_garantia',
    'valor_total',
    'vt_faturado',
    'valor_liquido',
    'status',
    'acoes',
]);

function lerJsonLista(valor) {
    try {
        const lista = JSON.parse(valor ?? '[]');
        return Array.isArray(lista) ? lista.filter((item) => typeof item === 'string') : [];
    } catch {
        return [];
    }
}

export function normalizarSelecao(selecao, ids, padrao = DEFAULT_VISIBLE) {
    const permitidos = new Set(ids);
    const sempre = new Set(['idOs', 'acoes'].filter((id) => permitidos.has(id)));
    const origem = Array.isArray(selecao) ? selecao : padrao;
    const solicitados = new Set([...sempre, ...origem]);

    // A seleção nunca muda a ordem canônica declarada pela view.
    return ids.filter((id) => permitidos.has(id) && solicitados.has(id));
}

function lerPreferencia(chave, ids, padrao) {
    try {
        const valor = globalThis.localStorage?.getItem(chave);
        return normalizarSelecao(valor === null ? null : lerJsonLista(valor), ids, padrao);
    } catch {
        return normalizarSelecao(null, ids, padrao);
    }
}

function gravarPreferencia(chave, selecao) {
    try {
        globalThis.localStorage?.setItem(chave, JSON.stringify(selecao));
    } catch {
        // O padrão continua ativo enquanto localStorage estiver indisponível.
    }
}

function aplicarTabela(tabela, ids, selecao) {
    const visiveis = new Set(selecao);

    for (const linha of tabela.rows) {
        if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) {
            continue;
        }
        ids.forEach((id, indice) => {
            const celula = linha.cells[indice];
            if (celula) {
                celula.hidden = !visiveis.has(id);
            }
        });
    }
}

function atualizarCheckboxes(raiz, selecao) {
    const visiveis = new Set(selecao);
    for (const checkbox of raiz.querySelectorAll('input[data-os-coluna]')) {
        checkbox.checked = visiveis.has(checkbox.dataset.osColuna);
    }
}

function atualizarEstado(raiz, tabela, ids, selecao, padrao) {
    aplicarTabela(tabela, ids, selecao);
    atualizarCheckboxes(raiz, selecao);
    raiz.classList.toggle('os-listagem--overflow', selecao.length > padrao.length);

    const status = raiz.querySelector('[data-os-colunas-status]');
    if (status) {
        status.textContent = `${selecao.length} colunas visíveis.`;
    }
}

function configurarPainel(raiz) {
    const seletor = raiz.querySelector('[data-os-colunas]');
    const toggle = seletor?.querySelector('[data-os-colunas-toggle]');
    const painel = seletor?.querySelector('.os-colunas-painel');
    if (!seletor || !toggle || !painel) {
        return () => {};
    }

    let aberto = false;
    const definirAberto = (valor) => {
        aberto = Boolean(valor);
        painel.hidden = !aberto;
        toggle.setAttribute('aria-expanded', String(aberto));
        toggle.querySelector('svg:last-of-type')?.classList.toggle('rotate-180', aberto);
    };

    toggle.addEventListener('click', () => definirAberto(!aberto));
    document.addEventListener('click', (evento) => {
        if (aberto && !seletor.contains(evento.target)) {
            definirAberto(false);
        }
    });
    document.addEventListener('keydown', (evento) => {
        if (aberto && evento.key === 'Escape') {
            definirAberto(false);
            toggle.focus();
        }
    });
    definirAberto(false);
    return definirAberto;
}

export default function iniciar(raiz) {
    const tabela = raiz.querySelector('.os-listagem-tabela table');
    const seletor = raiz.querySelector('[data-os-colunas]');
    if (!tabela || !seletor) {
        return;
    }

    const ids = lerJsonLista(raiz.dataset.osColunaIds);
    const padrao = normalizarSelecao(lerJsonLista(raiz.dataset.osColunasPadrao), ids, DEFAULT_VISIBLE);
    const chave = raiz.dataset.osStorageKey || 'mapos:v5:os:listagem:colunas:v2';
    let selecao = lerPreferencia(chave, ids, padrao);
    const definirPainelAberto = configurarPainel(raiz);

    atualizarEstado(raiz, tabela, ids, selecao, padrao);

    seletor.addEventListener('change', (evento) => {
        const checkbox = evento.target.closest?.('input[data-os-coluna]');
        if (!checkbox || checkbox.disabled) {
            return;
        }

        const id = checkbox.dataset.osColuna;
        const conjunto = new Set(selecao);
        if (checkbox.checked) {
            conjunto.add(id);
        } else {
            conjunto.delete(id);
        }
        selecao = normalizarSelecao([...conjunto], ids, padrao);
        gravarPreferencia(chave, selecao);
        atualizarEstado(raiz, tabela, ids, selecao, padrao);
    });

    raiz.querySelector('[data-os-restaurar]')?.addEventListener('click', () => {
        selecao = normalizarSelecao(padrao, ids, DEFAULT_VISIBLE);
        gravarPreferencia(chave, selecao);
        atualizarEstado(raiz, tabela, ids, selecao, padrao);
        definirPainelAberto(true);
    });
}
