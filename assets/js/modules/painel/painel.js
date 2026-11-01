// Painel inicial (views/mapos/painel.php, #2847).
//
// - Gráficos (Chart.js 4): balanço financeiro anual com as séries operacionais
//   do LIVE (bruta, líquida, custos fixos pagos/a pagar, despesas e
//   inadimplência) e OS por status. Os números vêm do page_data
//   "painel-dados"; a paleta financeira segue os valores reais do LIVE e as
//   demais cores vêm dos tokens (--color-*), lidas de novo quando o modo
//   claro/escuro muda.
// - Botão de olho (#3022): oculta o dinheiro do painel, preferência deste navegador.
// - Agenda (FullCalendar 6): as OS pela data final, de mapos/calendario, com
//   o filtro de status. Clicar numa OS abre o modal "agenda-os", preenchido
//   com textContent.
//
// As bibliotecas saem de assets/vendor e só são carregadas quando a tela tem
// gráfico ou agenda (o usuário pode não ter permissão para nenhum).

import { lerJson } from '../../lib/dados.js?v=fix-sidebar-install-final-20261031';
import { ErroHttp, get as getPadrao } from '../../lib/http.js?v=fix-sidebar-install-final-20261031';
import { carregarScript } from '../../lib/script.js?v=fix-sidebar-install-final-20261031';
import { mostrarToast } from '../../lib/toast.js?v=fix-sidebar-install-final-20261031';

const VENDOR = new URL('../../../vendor/', import.meta.url);

/** Valor de um token de cor (--color-<nome>) no momento. */
export function corDoToken(nome, estilo = getComputedStyle(document.documentElement)) {
    return estilo.getPropertyValue(`--color-${nome}`).trim();
}

/** R$ 1.234,50 */
export function moeda(valor) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(valor) || 0);
}

/** Date (dia local) → AAAA-MM-DD, o formato que mapos/calendario aceita. */
export function dataIso(data) {
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');

    return `${data.getFullYear()}-${mes}-${dia}`;
}

/** URL de mapos/calendario com o período visível e o status escolhido. */
export function urlDaAgenda(base, inicio, fim, status = '') {
    const url = new URL(base, 'http://x');
    url.searchParams.set('start', dataIso(inicio));
    url.searchParams.set('end', dataIso(fim));
    if (status) {
        url.searchParams.set('status', status);
    }

    return base.startsWith('http') ? url.href : url.pathname + url.search;
}

/**
 * Configuração do gráfico financeiro anual.
 *
 * Há três grupos de barras por mês: bruto, líquido e custos fixos. Os dois
 * últimos datasets são linhas para preservar Despesas/Inadimplência sem criar
 * barras extras; os dois custos fixos compartilham a mesma pilha.
 */
export function configuracaoBalanco(balanco, cores) {
    const usaSeriesFinanceiras = ['receita_bruta', 'receita_liquida', 'custos_fixos_pago', 'custos_fixos_a_pagar', 'inadimplencia']
        .some((nome) => Object.hasOwn(balanco, nome));

    if (!usaSeriesFinanceiras) {
        // Compatibilidade com o contrato de dados da release 5.2.0; o LIVE
        // personalizado usa as séries financeiras detalhadas abaixo.
        return {
            type: 'bar',
            data: {
                labels: balanco.meses,
                datasets: [
                    { type: 'bar', label: 'Receitas', data: balanco.receitas, backgroundColor: cores.receitas, borderRadius: 4, order: 2 },
                    { type: 'bar', label: 'Despesas', data: balanco.despesas, backgroundColor: cores.despesas, borderRadius: 4, order: 2 },
                    { type: 'line', label: 'Saldo', data: balanco.saldo, borderColor: cores.saldo, backgroundColor: cores.saldo, pointRadius: 3, tension: 0, order: 1 },
                ],
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false }, ticks: { color: cores.texto } },
                    y: { grid: { color: cores.grade }, ticks: { color: cores.texto, callback: (valor) => moeda(valor) } },
                },
                plugins: {
                    legend: { position: 'bottom', labels: { color: cores.texto, usePointStyle: true } },
                    tooltip: { callbacks: { label: (contexto) => `${contexto.dataset.label}: ${moeda(contexto.parsed.y)}` } },
                },
            },
        };
    }

    return {
        type: 'bar',
        data: {
            labels: balanco.meses,
            datasets: [
                { type: 'bar', label: 'Receita Bruta', data: balanco.receita_bruta ?? balanco.receitas, backgroundColor: cores.receitaBruta, borderColor: cores.receitaBruta, borderWidth: 1, borderRadius: 4, stack: 'receita-bruta', order: 2 },
                { type: 'bar', label: 'Receita Líquida', data: balanco.receita_liquida, backgroundColor: cores.receitaLiquida, borderColor: cores.receitaLiquida, borderWidth: 1, borderRadius: 4, stack: 'receita-liquida', order: 2 },
                { type: 'bar', label: 'Custos Fixos pagos', data: balanco.custos_fixos_pago, backgroundColor: cores.custosFixosPago, borderColor: cores.custosFixosPago, borderWidth: 1, borderRadius: 4, stack: 'custos-fixos', order: 2 },
                { type: 'bar', label: 'Custos Fixos a pagar', data: balanco.custos_fixos_a_pagar, backgroundColor: cores.custosFixosAPagar, borderColor: cores.custosFixosAPagar, borderWidth: 1, borderRadius: 4, stack: 'custos-fixos', order: 2 },
                { type: 'line', label: 'Despesas', data: balanco.despesas, backgroundColor: cores.despesas, borderColor: cores.despesas, pointBackgroundColor: cores.despesas, borderWidth: 2, pointRadius: 3, tension: 0.2, fill: false, order: 1 },
                { type: 'line', label: 'Inadimplência', data: balanco.inadimplencia, backgroundColor: cores.inadimplencia, borderColor: cores.inadimplencia, pointBackgroundColor: cores.inadimplencia, borderWidth: 2, pointRadius: 3, tension: 0.2, fill: false, order: 1 },
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { stacked: true, grid: { display: false }, ticks: { color: cores.texto } },
                y: { stacked: true, grid: { color: cores.grade }, ticks: { color: cores.texto, callback: (valor) => moeda(valor) } },
            },
            plugins: {
                legend: { position: 'bottom', labels: { color: cores.texto, usePointStyle: true, boxWidth: 10, padding: 12 } },
                tooltip: { callbacks: { label: (contexto) => `${contexto.dataset.label}: ${moeda(contexto.parsed.y)}` } },
            },
        },
    };
}

/** Configuração do gráfico de OS por status (rosca), uma cor por variante do pill-status. */
export function configuracaoOs(fatias, corDaVariante, cores) {
    return {
        type: 'doughnut',
        data: {
            labels: fatias.map((fatia) => fatia.status),
            datasets: [{
                data: fatias.map((fatia) => fatia.total),
                backgroundColor: fatias.map((fatia) => corDaVariante(fatia.variante)),
                borderColor: cores.fundo,
                borderWidth: 2,
            }],
        },
        options: {
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: { position: 'bottom', labels: { color: cores.texto, usePointStyle: true } },
            },
        },
    };
}

async function iniciarGraficos(raiz, dados, doc) {
    const telas = [...raiz.querySelectorAll('[data-grafico]')];
    if (telas.length === 0 || !dados) {
        return;
    }

    await carregarScript(new URL('chart.js/chart.umd.min.js', VENDOR).href, doc);
    const { Chart } = globalThis;
    const graficos = [];

    const desenhar = () => {
        graficos.splice(0).forEach((grafico) => grafico.destroy());

        const estilo = getComputedStyle(doc.documentElement);
        const cor = (nome) => corDoToken(nome, estilo);
        const cores = {
            texto: cor('muted'),
            grade: cor('border'),
            fundo: cor('surface'),
            // Valores literais recuperados de application/views/mapos/painel.php do LIVE.
            receitaBruta: 'rgba(255, 206, 86, 0.5)',
            receitaLiquida: 'rgba(75, 192, 192, 0.5)',
            custosFixosPago: 'rgba(186, 140, 255, 0.65)',
            custosFixosAPagar: 'rgba(220, 53, 69, 0.55)',
            despesas: 'rgba(255, 99, 132, 0.5)',
            inadimplencia: 'rgba(54, 162, 235, 0.5)',
        };
        Chart.defaults.font.family = getComputedStyle(doc.body).fontFamily;

        for (const tela of telas) {
            if (tela.dataset.grafico === 'balanco' && dados.balanco) {
                graficos.push(new Chart(tela, configuracaoBalanco(dados.balanco, cores)));
            } else if (tela.dataset.grafico === 'os' && dados.os) {
                graficos.push(new Chart(tela, configuracaoOs(dados.os, cor, cores)));
            }
        }
    };

    desenhar();
    // O modo claro/escuro troca a classe "dark" do <html>.
    new MutationObserver(desenhar).observe(doc.documentElement, { attributes: true, attributeFilter: ['class'] });
}

function abrirOs(modal, evento) {
    const dados = evento.extendedProps;
    modal.querySelector('h2').textContent = `OS #${dados.numero}`;

    for (const campo of modal.querySelectorAll('[data-agenda-campo]')) {
        const nome = campo.dataset.agendaCampo;
        campo.textContent = nome === 'faturado' ? (dados.faturado ? 'Sim' : 'Não') : (dados[nome] || '—');
    }

    for (const pill of modal.querySelectorAll('[data-agenda-pill]')) {
        pill.hidden = pill.dataset.agendaPill !== dados.status;
    }

    for (const link of modal.querySelectorAll('[data-agenda-link]')) {
        const url = dados[link.dataset.agendaLink];
        link.hidden = !url;
        if (url) {
            link.href = url;
        }
    }

    modal.showModal();
}

async function iniciarAgenda(raiz, { doc, get }) {
    const alvo = raiz.querySelector('[data-agenda]');
    if (!alvo) {
        return;
    }

    await carregarScript(new URL('fullcalendar/fullcalendar.global.min.js', VENDOR).href, doc);
    await carregarScript(new URL('fullcalendar/locales/pt-br.global.min.js', VENDOR).href, doc);

    const filtro = raiz.querySelector('[data-agenda-status]');
    const modal = doc.getElementById('agenda-os');
    const celular = globalThis.matchMedia?.('(max-width: 639px)').matches ?? false;

    alvo.querySelector('[data-agenda-carregando]')?.remove();

    const agenda = new globalThis.FullCalendar.Calendar(alvo, {
        locale: 'pt-br',
        initialView: celular ? 'listMonth' : 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
        buttonText: { today: 'Hoje', month: 'Mês', list: 'Lista' },
        buttonHints: { prev: 'Mês anterior', next: 'Próximo mês', today: 'Ir para hoje', dayGridMonth: 'Ver o mês', listMonth: 'Ver a lista do mês' },
        height: 'auto',
        dayMaxEvents: 3,
        displayEventTime: false,
        eventClassNames: (info) => [`evento-${info.event.extendedProps.variante || 'neutral'}`],
        events: (periodo, sucesso, falha) => {
            get(urlDaAgenda(alvo.dataset.agenda, periodo.start, periodo.end, filtro?.value ?? ''))
                .then((eventos) => sucesso(Array.isArray(eventos) ? eventos : []))
                .catch((erro) => {
                    mostrarToast({
                        mensagem: erro instanceof ErroHttp && erro.status === 403
                            ? 'Sua sessão expirou. Recarregue a página.'
                            : 'Não foi possível carregar a agenda. Tente de novo.',
                        variante: 'danger',
                    }, doc);
                    falha(erro);
                });
        },
        // Os ícones das setas vêm com role=img sem texto: o nome do botão já está no title (buttonHints).
        datesSet: () => {
            for (const icone of alvo.querySelectorAll('.fc-icon[role="img"]')) {
                icone.setAttribute('aria-hidden', 'true');
                icone.removeAttribute('role');
            }
        },
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            if (modal) {
                abrirOs(modal, info.event);
            }
        },
    });

    agenda.render();
    filtro?.addEventListener('change', () => agenda.refetchEvents());
}

export const CHAVE_VALORES = 'mapos:valores-ocultos';

/** Próximo estado do botão de olho: ocultar ↔ mostrar. */
export function alternarValores(ocultos) {
    return !ocultos;
}

/**
 * Botão de olho (#3022): oculta ou mostra o dinheiro do painel. A escolha vale
 * só neste navegador (localStorage); o tema.js a aplica antes da primeira
 * pintura. Com o localStorage bloqueado, vale até a próxima página.
 */
function iniciarValores(doc, armazem = globalThis.localStorage) {
    const html = doc.documentElement;

    for (const botao of doc.querySelectorAll('[data-valores-alternar]')) {
        botao.addEventListener('click', () => {
            const ocultos = alternarValores(html.getAttribute('data-valores') === 'ocultos');
            if (ocultos) {
                html.setAttribute('data-valores', 'ocultos');
            } else {
                html.removeAttribute('data-valores');
            }

            try {
                if (ocultos) {
                    armazem?.setItem(CHAVE_VALORES, '1');
                } else {
                    armazem?.removeItem(CHAVE_VALORES);
                }
            } catch {
                // localStorage bloqueado: a escolha vale só até a próxima página.
            }
        });
    }
}

export default function iniciar(raiz, { doc = document, get = getPadrao } = {}) {
    const dados = lerJson('painel-dados', null, doc);

    const avisar = (erro) => {
        console.error('[painel]', erro);
    };

    iniciarValores(doc);
    iniciarGraficos(raiz, dados, doc).catch(avisar);
    iniciarAgenda(raiz, { doc, get }).catch(avisar);
}
