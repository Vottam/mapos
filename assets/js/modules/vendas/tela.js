// Tela da venda: aproveita o motor de formulários AJAX da OS e coordena os
// snapshots de garantia editáveis desta página.

import iniciarOsTela, { textoSemHtml } from '../os/tela.js?v=mapos-531-item-warranty-return-20261117-r2';
import { GarantiasPendentes, normalizarDiasGarantia } from './garantias-pendentes.js?v=mapos-531-item-warranty-return-20261117-r2';
import { ErroHttp, post as postPadrao } from '../../lib/http.js?v=mapos-531-item-warranty-return-20261117-r2';
import { mostrarToast } from '../../lib/toast.js?v=mapos-531-item-warranty-return-20261117-r2';

const ROTULOS = {
    salvo: 'SALVO',
    salvando: 'SALVANDO...',
    pendente: 'ALTERAÇÕES PENDENTES',
    erro: 'ERRO AO SALVAR',
};

function mensagemErro(erro) {
    if (erro instanceof ErroHttp) {
        if (erro.corpo && typeof erro.corpo === 'object') {
            return textoSemHtml(erro.corpo.message ?? erro.message) || 'O servidor não confirmou o salvamento.';
        }
        if (erro.status === 403) return 'A sessão ou o token de segurança expirou. Recarregue e tente novamente.';
        return `O servidor respondeu com erro HTTP ${erro.status}.`;
    }
    if (erro instanceof TypeError) return 'Sem conexão com o servidor. A alteração foi mantida; tente novamente.';
    return textoSemHtml(erro?.message) || 'Não foi possível salvar. A alteração foi mantida; tente novamente.';
}

export default function iniciar(raiz, opcoes = {}) {
    const doc = opcoes.doc ?? document;
    const win = opcoes.win ?? window;
    const post = opcoes.post ?? postPadrao;
    const acoesOsEmAndamento = new Set();
    const bloqueiosGarantia = new WeakMap();
    function bloquearCamposGarantia(bloquear) {
        for (const input of raiz.querySelectorAll?.('[data-garantia-dias]') ?? []) {
            if (bloquear) {
                if (!bloqueiosGarantia.has(input)) bloqueiosGarantia.set(input, input.disabled);
                input.disabled = true;
            } else if (bloqueiosGarantia.has(input)) {
                input.disabled = bloqueiosGarantia.get(input);
                bloqueiosGarantia.delete(input);
            }
        }
    }
    const postOs = (...argumentos) => {
        let operacao;
        try {
            bloquearCamposGarantia(true);
            operacao = Promise.resolve(post(...argumentos));
        } catch (erro) {
            operacao = Promise.reject(erro);
        }
        acoesOsEmAndamento.add(operacao);
        const finalizar = () => {
            acoesOsEmAndamento.delete(operacao);
            if (acoesOsEmAndamento.size === 0) bloquearCamposGarantia(false);
        };
        void operacao.then(finalizar, finalizar);
        return operacao;
    };
    iniciarOsTela(raiz, { ...opcoes, post: postOs });

    const statusGlobal = raiz.querySelector('[data-garantia-estado]');
    const mensagemGlobal = raiz.querySelector('[data-garantia-mensagem]');
    const botaoSalvar = raiz.querySelector('[data-garantia-salvar]');
    const containerProdutos = raiz.querySelector('[data-os-parte="produtos"]');
    if (!statusGlobal || !containerProdutos) return;

    const atualPorId = new Map();
    let ultimaMensagemErro = '';
    let acaoEmAndamento = false;
    const submitsRetomados = new WeakSet();
    const cliquesRetomados = new WeakSet();

    function renderizarEstadoItem(item) {
        const atual = atualPorId.get(item.id);
        if (!atual) return;
        const estado = item.salvando ? 'salvando' : item.erro ? 'erro' : item.sujo ? 'pendente' : 'salvo';
        const rotulo = item.salvando ? 'Salvando…' : item.erro ? 'Erro ao salvar' : item.sujo ? 'Alterações pendentes' : 'Salvo';
        if (atual.form.dataset.garantiaEstado !== estado) atual.form.dataset.garantiaEstado = estado;
        if (atual.estado.textContent !== rotulo) atual.estado.textContent = rotulo;
        if (atual.estado.title !== (item.erro || '')) atual.estado.title = item.erro || '';
        const ariaInvalid = item.valido && !item.erro ? 'false' : 'true';
        if (atual.input.getAttribute('aria-invalid') !== ariaInvalid) atual.input.setAttribute('aria-invalid', ariaInvalid);
    }

    const garantias = new GarantiasPendentes({
        atraso: 700,
        salvar: async (idItem, dias, meta) => {
            try {
                const resposta = await post(meta.form.action, {
                    idVendas: raiz.dataset.vendaId,
                    idItem,
                    dias,
                });
                if (resposta?.result !== true) {
                    throw new Error(textoSemHtml(resposta?.message) || 'O servidor não confirmou o salvamento.');
                }
                return resposta;
            } catch (erro) {
                throw new Error(mensagemErro(erro));
            }
        },
        aoAtualizar: (snapshot) => {
            statusGlobal.textContent = ROTULOS[snapshot.estado] ?? ROTULOS.erro;
            statusGlobal.dataset.garantiaEstado = snapshot.estado;
            statusGlobal.setAttribute('aria-busy', snapshot.estado === 'salvando' ? 'true' : 'false');
            if (botaoSalvar) botaoSalvar.disabled = snapshot.estado === 'salvando';

            for (const item of snapshot.itens) renderizarEstadoItem(item);

            if (snapshot.estado === 'erro') {
                const primeiro = snapshot.erros[0];
                const texto = primeiro
                    ? `${primeiro.nome}: ${primeiro.erro} A alteração foi mantida; tente salvar novamente.`
                    : 'Há uma alteração pendente que precisa de nova tentativa.';
                if (mensagemGlobal) mensagemGlobal.textContent = texto;
                if (texto !== ultimaMensagemErro) {
                    mostrarToast({ mensagem: texto, variante: 'danger', duracao: 8000 }, doc);
                    ultimaMensagemErro = texto;
                }
            } else {
                if (mensagemGlobal) mensagemGlobal.textContent = '';
                ultimaMensagemErro = '';
            }
        },
    });

    function sincronizarCampos() {
        const presentes = new Set();
        for (const form of containerProdutos.querySelectorAll('form[data-garantia-autosave]')) {
            const id = String(form.dataset.garantiaId ?? '');
            const input = form.querySelector('[data-garantia-dias]');
            const estado = form.querySelector('[data-garantia-item-status]');
            if (!id || !input || !estado) continue;
            presentes.add(id);
            const meta = { form, input, estado, nome: form.dataset.garantiaNome || `Item #${id}` };
            const anterior = garantias.obter(id);
            atualPorId.set(id, meta);
            if (!anterior) {
                garantias.registrar(id, input.value, meta);
                continue;
            }

            if (anterior.revisao === 0 && !anterior.salvando && anterior.erro === null && !anterior.sujo) {
                garantias.sincronizarServidor(id, input.value, meta);
                continue;
            }

            // A resposta de outra operação AJAX pode conter HTML criado antes
            // da última edição/salvamento. Não deixe esse fragmento regredir
            // um valor local que já foi alterado nesta tela.
            if (anterior.revisao > 0 && input.value !== anterior.valor) input.value = anterior.valor;
            const aviso = form.querySelector('[data-garantia-zero-aviso]');
            if (aviso) aviso.hidden = normalizarDiasGarantia(input.value) !== '0';
            garantias.atualizarMeta(id, meta);
            renderizarEstadoItem({ ...anterior, meta });
        }
        for (const id of atualPorId.keys()) {
            if (!presentes.has(id)) atualPorId.delete(id);
        }
        garantias.removerAusentes(presentes);
    }

    sincronizarCampos();
    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(sincronizarCampos).observe(containerProdutos, { childList: true, subtree: true });
    }

    raiz.addEventListener('input', (evento) => {
        const input = evento.target.closest?.('[data-garantia-dias]');
        if (!input) return;
        const form = input.closest('form[data-garantia-autosave]');
        if (form) {
            garantias.alterar(form.dataset.garantiaId, input.value);
            const aviso = form.querySelector('[data-garantia-zero-aviso]');
            if (aviso) aviso.hidden = normalizarDiasGarantia(input.value) !== '0';
        }
    });

    doc.addEventListener('change', (evento) => {
        const input = evento.target.closest?.('[data-garantia-dias]');
        if (!input) return;
        const form = input.closest('form[data-garantia-autosave]');
        if (form) garantias.alterar(form.dataset.garantiaId, input.value);
        const aviso = form?.querySelector('[data-garantia-zero-aviso]');
        if (aviso) aviso.hidden = normalizarDiasGarantia(input.value) !== '0';
    }, true);

    doc.addEventListener('focusout', (evento) => {
        const input = evento.target.closest?.('[data-garantia-dias]');
        if (!input) return;
        const form = input.closest('form[data-garantia-autosave]');
        if (form) void garantias.salvarAgora(form.dataset.garantiaId);
    }, true);

    async function aguardarAcoesOs() {
        while (acoesOsEmAndamento.size > 0) {
            await Promise.allSettled([...acoesOsEmAndamento]);
        }
    }

    async function flushPendingChanges() {
        await aguardarAcoesOs();
        return garantias.flush();
    }
    // Útil para os fluxos da própria tela e para uma inspeção autenticada sem
    // introduzir um segundo endpoint ou regravar snapshots financeiros.
    raiz.flushPendingChanges = flushPendingChanges;

    async function executarComFlush(aoSucesso, popup = null) {
        if (acaoEmAndamento) {
            popup?.close();
            mostrarToast({ mensagem: 'Aguarde a gravação atual terminar.', variante: 'warning' }, doc);
            return false;
        }
        acaoEmAndamento = true;
        try {
            const sucesso = await flushPendingChanges();
            if (!sucesso) {
                popup?.close();
                return false;
            }
            await aoSucesso?.(popup);
            return true;
        } finally {
            acaoEmAndamento = false;
        }
    }

    function linkDeImpressao(alvo) {
        if (!(alvo instanceof HTMLAnchorElement) || alvo.target !== '_blank') return false;
        let caminho;
        try {
            caminho = new URL(alvo.href, doc.location.href).pathname;
        } catch {
            return false;
        }
        const id = String(raiz.dataset.vendaId ?? '');
        return new RegExp(`/vendas/(?:imprimir|imprimirVendaOrcamento|imprimirTermica)/${id}/?$`).test(caminho);
    }

    doc.addEventListener('click', (evento) => {
        const alvo = evento.target.closest?.('a,button');
        if (!alvo) return;

        if (cliquesRetomados.has(alvo)) {
            cliquesRetomados.delete(alvo);
            return;
        }

        if (alvo.matches('[data-garantia-salvar]')) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            const tinhaPendentes = garantias.temPendentes();
            void executarComFlush(async () => {
                const texto = tinhaPendentes ? 'Alterações salvas.' : 'Todas as alterações estão salvas.';
                if (mensagemGlobal) mensagemGlobal.textContent = texto;
                mostrarToast({ mensagem: texto, variante: 'success' }, doc);
            });
            return;
        }

        if (linkDeImpressao(alvo) && (garantias.temPendentes() || acoesOsEmAndamento.size > 0)) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            const popup = win.open('about:blank', '_blank');
            if (!popup) {
                mostrarToast({ mensagem: 'Permita a abertura de uma nova aba para imprimir após salvar.', variante: 'danger', duracao: 8000 }, doc);
                return;
            }
            try { popup.opener = null; } catch { /* navegador pode bloquear */ }
            void executarComFlush(async (janela) => {
                janela.location.replace(alvo.href);
            }, popup);
            return;
        }

        const modal = alvo.closest('[data-modal-abrir]');
        if (modal && ['faturar-venda', 'gerar-cobranca', 'excluir-venda'].includes(modal.dataset.modalAbrir)
            && (garantias.temPendentes() || acoesOsEmAndamento.size > 0)
        ) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            void executarComFlush(() => {
                cliquesRetomados.add(modal);
                modal.click();
            });
        }
    }, true);

    function formularioDeAcao(form) {
        if (form.matches('form[data-os-acao]')) return true;
        const caminho = form.action ? new URL(form.action, doc.location.href).pathname : '';
        return /\/vendas\/excluir\/?$/.test(caminho);
    }

    doc.addEventListener('submit', (evento) => {
        const form = evento.target;
        if (!(form instanceof HTMLFormElement)) return;

        // O listener de vendas roda em capture antes do módulo da OS. Não
        // enfileire/reenvie um segundo submit enquanto o POST financeiro atual
        // ainda está em andamento: isso poderia criar uma cobrança duplicada.
        if (form.dataset.osEnviando === '1') {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            return;
        }

        if (form.matches('form[data-garantia-autosave]')) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            void garantias.salvarAgora(form.dataset.garantiaId);
            return;
        }

        if (submitsRetomados.has(form)) {
            submitsRetomados.delete(form);
            return;
        }
        if (!formularioDeAcao(form)) return;
        if (acaoEmAndamento) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            mostrarToast({ mensagem: 'Aguarde a gravação atual terminar.', variante: 'warning' }, doc);
            return;
        }
        if (!garantias.temPendentes() && acoesOsEmAndamento.size === 0) return;

        evento.preventDefault();
        evento.stopImmediatePropagation();
        const submitter = evento.submitter;
        void executarComFlush(() => {
            submitsRetomados.add(form);
            if (submitter && submitter.form === form) form.requestSubmit(submitter);
            else form.requestSubmit();
        });
    }, true);

    win.addEventListener('beforeunload', (evento) => {
        if (!garantias.temPendentes() && acoesOsEmAndamento.size === 0) return;
        evento.preventDefault();
        evento.returnValue = '';
    });
}
