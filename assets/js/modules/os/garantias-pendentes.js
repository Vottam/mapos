// Smart Save dos prazos por item na tela de Ordem de Serviço.
// Reutiliza a fila serializada já aprovada em Vendas; não envia o formulário
// completo da OS nem altera quantidade, preço, desconto, estoque ou vínculos.

import { GarantiasPendentes } from '../vendas/garantias-pendentes.js?v=mapos-531-os-smart-save-pdf-layout-20261117-r2';
import { post } from '../../lib/http.js?v=mapos-531-os-smart-save-pdf-layout-20261117-r2';

const SELETOR_FORM = 'form[data-os-garantia-item]';
const SELETOR_CAMPO = '[data-garantia-dias]';

export function rotuloEstadoGarantiaItem(item) {
    if (!item) return 'Salvo';
    if (item.erro !== null) return 'Erro ao salvar';
    if (item.salvando) return 'Salvando...';
    if (item.sujo) return 'Alterações pendentes';
    return 'Salvo';
}

export function rotuloEstadoGarantiaGeral(snapshot, acaoBloqueada = false) {
    if (snapshot.estado === 'salvando') return 'Salvando...';
    if (snapshot.estado === 'erro') {
        const detalhe = snapshot.erros[0]?.erro;
        const prefixo = detalhe ? `Erro ao salvar: ${detalhe}` : 'Erro ao salvar';
        return acaoBloqueada ? `${prefixo}. A ação não foi executada.` : `${prefixo}. Tente novamente.`;
    }
    if (snapshot.estado === 'pendente') return 'Alterações pendentes';
    return 'Salvo';
}

function caminhoDaAcao(form, win) {
    try {
        return new URL(form.action, win.location.href).pathname;
    } catch {
        return '';
    }
}

function atualizarAvisoDePrazoZero(form) {
    const campo = form.querySelector(SELETOR_CAMPO);
    const aviso = form.querySelector('[data-garantia-zero-aviso]');
    if (!campo || !aviso) return;
    const valor = String(campo.value ?? '').trim();
    aviso.hidden = !/^\d+$/.test(valor) || Number(valor) !== 0;
}

export default function iniciarGarantiasPendentesOs(elemento) {
    const doc = elemento?.ownerDocument ?? document;
    const win = doc.defaultView ?? window;
    const raiz = elemento?.closest?.('[data-os-garantia-root]')
        ?? (elemento?.matches?.('[data-os-garantia-root]') ? elemento : null)
        ?? doc.querySelector('[data-os-garantia-root]');

    if (!raiz || raiz.dataset.osGarantiaAutosaveIniciado === 'true') return;
    raiz.dataset.osGarantiaAutosaveIniciado = 'true';

    const formularios = new Map();
    const linksRetomados = new WeakSet();
    const formulariosRetomados = new WeakSet();
    const botoesRetomados = new WeakSet();
    let acaoEmEspera = false;
    let acaoBloqueada = false;

    const atualizarInterface = (snapshot) => {
        for (const form of raiz.querySelectorAll(SELETOR_FORM)) {
            const chave = form.dataset.osGarantiaId;
            const item = chave ? estado.obter(chave) : null;
            const campo = form.querySelector(SELETOR_CAMPO);
            const situacao = form.querySelector('[data-garantia-item-status]');
            if (!item) continue;

            if (situacao) {
                const rotuloItem = rotuloEstadoGarantiaItem(item);
                if (situacao.textContent !== rotuloItem) situacao.textContent = rotuloItem;
                const detalheItem = item.erro ?? '';
                if (situacao.title !== detalheItem) situacao.title = detalheItem;
            }
            if (campo) {
                if (!item.valido) campo.setAttribute('aria-invalid', 'true');
                else campo.removeAttribute('aria-invalid');
                campo.title = item.erro ?? '';
                atualizarAvisoDePrazoZero(form);
            }
        }

        const situacaoGeral = raiz.querySelector('[data-os-garantia-status]');
        if (situacaoGeral) {
            const rotuloGeral = rotuloEstadoGarantiaGeral(snapshot, acaoBloqueada);
            if (situacaoGeral.textContent !== rotuloGeral) situacaoGeral.textContent = rotuloGeral;
            const detalheGeral = snapshot.erros[0]?.erro ?? '';
            if (situacaoGeral.title !== detalheGeral) situacaoGeral.title = detalheGeral;
        }
    };

    const estado = new GarantiasPendentes({
        atraso: 700,
        aoAtualizar: atualizarInterface,
        salvar: async (chave, dias, meta) => {
            const form = meta.form;
            if (!form?.isConnected) throw new Error('O item da OS não está mais disponível para salvar.');
            const campo = form.querySelector(SELETOR_CAMPO);
            if (!campo) throw new Error('Campo de garantia não encontrado; a alteração continua pendente.');

            const dados = new FormData(form);
            dados.set('dias', dias);
            const resposta = await post(form.action, dados);
            if (!resposta || typeof resposta !== 'object' || resposta.result !== true) {
                throw new Error(resposta?.message || 'O servidor não confirmou o salvamento.');
            }
            return resposta;
        },
    });

    function sincronizarFormularios() {
        const presentes = new Map();
        for (const form of raiz.querySelectorAll(SELETOR_FORM)) {
            const chave = String(form.dataset.osGarantiaId ?? '');
            const campo = form.querySelector(SELETOR_CAMPO);
            if (!chave || !campo) continue;

            const meta = {
                form,
                nome: form.dataset.garantiaNome || `Item ${chave}`,
                tipo: form.dataset.garantiaTipo || '',
            };
            const item = estado.obter(chave);
            if (!item) estado.registrar(chave, campo.value, meta);
            else {
                estado.atualizarMeta(chave, meta);
                // Uma resposta parcial pode recriar o formulário com o valor
                // antigo enquanto a edição local ainda aguarda confirmação.
                // Repor a revisão local evita mostrar um prazo antigo como atual.
                if (item.sujo || item.salvando || item.erro !== null) {
                    campo.value = item.valor;
                }
                estado.sincronizarServidor(chave, campo.value, meta);
            }
            presentes.set(chave, form);
        }

        for (const chave of formularios.keys()) {
            if (!presentes.has(chave)) estado.remover(chave);
        }
        formularios.clear();
        for (const [chave, form] of presentes) formularios.set(chave, form);
        atualizarInterface(estado.snapshot());
    }

    function primeiroFormularioDoEvento(alvo) {
        const campo = alvo?.closest?.(SELETOR_CAMPO);
        const form = campo?.form;
        if (!campo || !form?.matches(SELETOR_FORM) || !raiz.contains(form)) return null;
        return { campo, form, chave: String(form.dataset.osGarantiaId ?? '') };
    }

    function marcarFalhaDeAcao() {
        acaoBloqueada = true;
        atualizarInterface(estado.snapshot());
    }

    async function liberarAcao(aoLiberar) {
        if (acaoEmEspera) return false;
        acaoEmEspera = true;
        acaoBloqueada = false;
        try {
            const salvo = await estado.flush();
            if (!salvo) {
                marcarFalhaDeAcao();
                return false;
            }
            acaoBloqueada = false;
            aoLiberar();
            return true;
        } catch {
            marcarFalhaDeAcao();
            return false;
        } finally {
            acaoEmEspera = false;
        }
    }

    doc.addEventListener('input', (evento) => {
        const dados = primeiroFormularioDoEvento(evento.target);
        if (!dados?.chave) return;
        acaoBloqueada = false;
        estado.alterar(dados.chave, dados.campo.value);
    }, true);

    doc.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Enter' || evento.isComposing) return;
        const dados = primeiroFormularioDoEvento(evento.target);
        if (!dados) return;
        evento.preventDefault();
        if (!evento.repeat) dados.campo.blur();
    }, true);

    doc.addEventListener('blur', (evento) => {
        const dados = primeiroFormularioDoEvento(evento.target);
        if (!dados?.chave) return;
        estado.alterar(dados.chave, dados.campo.value);
        void estado.salvarAgora(dados.chave).catch(() => {});
    }, true);

    doc.addEventListener('click', (evento) => {
        const alvo = evento.target?.closest?.('button, a[href]');
        if (!alvo || !raiz.contains(alvo)) return;

        if (alvo.matches('[data-os-garantia-save-all]')) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            acaoBloqueada = false;
            void estado.flush().then((salvo) => {
                if (!salvo) atualizarInterface(estado.snapshot());
            });
            return;
        }

        if (botoesRetomados.has(alvo)) {
            botoesRetomados.delete(alvo);
            return;
        }
        if (alvo.matches('[data-modal-abrir]') && estado.temPendentes()) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            if (acaoEmEspera) return;
            void liberarAcao(() => {
                if (!alvo.isConnected) return;
                botoesRetomados.add(alvo);
                alvo.click();
            });
            return;
        }

        if (!alvo.matches('a[href]')) return;
        if (linksRetomados.has(alvo)) {
            linksRetomados.delete(alvo);
            return;
        }
        if (!estado.temPendentes()) return;

        let destino;
        try {
            destino = new URL(alvo.href, win.location.href);
        } catch {
            return;
        }
        if (destino.origin !== win.location.origin) return;
        if (destino.pathname === win.location.pathname && destino.search === win.location.search && destino.hash) return;

        evento.preventDefault();
        evento.stopImmediatePropagation();
        if (acaoEmEspera) return;

        const abreNovaAba = alvo.target === '_blank' || (alvo.target && alvo.target !== '_self');
        const popup = abreNovaAba ? win.open('about:blank', alvo.target || '_blank') : null;
        if (popup) popup.opener = null;
        void liberarAcao(() => {
            if (abreNovaAba) {
                if (popup && !popup.closed) popup.location.replace(destino.href);
                else win.location.assign(destino.href);
                return;
            }
            if (!alvo.isConnected) return;
            linksRetomados.add(alvo);
            alvo.click();
        }).then((salvo) => {
            if (!salvo && popup && !popup.closed) popup.close();
        });
    }, true);

    doc.addEventListener('submit', (evento) => {
        const form = evento.target;
        if (!(form instanceof win.HTMLFormElement)) return;
        if (formulariosRetomados.has(form)) {
            formulariosRetomados.delete(form);
            return;
        }
        const acao = caminhoDaAcao(form, win);
        const eAcaoOs = form.matches('form[data-os-acao]') || /(?:^|\/)os\/enviar_email\//.test(acao);
        if (!eAcaoOs || !estado.temPendentes()) return;

        evento.preventDefault();
        evento.stopImmediatePropagation();
        if (acaoEmEspera) return;
        const submitter = evento.submitter;
        void liberarAcao(() => {
            if (!form.isConnected) return;
            formulariosRetomados.add(form);
            if (submitter?.form === form && typeof form.requestSubmit === 'function') form.requestSubmit(submitter);
            else if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else {
                formulariosRetomados.delete(form);
                marcarFalhaDeAcao();
            }
        });
    }, true);

    win.addEventListener('beforeunload', (evento) => {
        if (!estado.temPendentes()) return;
        evento.preventDefault();
        evento.returnValue = '';
    });

    sincronizarFormularios();
    const Observador = win.MutationObserver;
    if (typeof Observador === 'function') {
        const observador = new Observador(() => sincronizarFormularios());
        observador.observe(raiz, { childList: true, subtree: true });
    }
}
