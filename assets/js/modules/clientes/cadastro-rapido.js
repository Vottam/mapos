// Cadastro rápido de cliente em modal (#3003), nas telas da OS e da venda.
//
// A view (views/clientes/partes/cadastro-rapido.php) põe este módulo num
// wrapper em volta do <dialog id="cliente-rapido"> e do <form> oculto a que
// os campos do modal se ligam por form="...".
//
// Fluxo:
// - O rodapé do autocomplete de cliente (data-autocomplete-novo-modal) dispara
//   "autocomplete-novo" no seu wrapper e abre o modal. O formulário é limpo e
//   o texto digitado entra no nome, ou no CPF/CNPJ se forem só números.
// - Ao enviar, valida com as regras do HTML, faz o POST (lib/http.js) e:
//   422 mostra o erro de cada campo; sucesso fecha o modal, mostra o toast e
//   dispara "autocomplete-novo-criado" ({item}) no wrapper de origem, que
//   escolhe o cliente no campo da tela sem perder o que já foi preenchido.
// - Busca de CEP e de CNPJ são as do formulário de cliente (ligarBuscas).

import { get as getPadrao, post as postPadrao } from '../../lib/http.js';
import { marcarCarregando, mensagemDoCampo, mostrarErroDoCampo } from '../../lib/formulario.js';
import { somenteDigitos } from '../../lib/mascaras.js';
import { mostrarToast } from '../../lib/toast.js';
import { aplicarMascara } from '../formulario/padrao.js';
import { ligarBuscas } from './formulario.js';

/** Campo em que o texto digitado no autocomplete entra: só números é documento, o resto é nome. */
export function campoDoTexto(texto) {
    const limpo = String(texto ?? '').trim();
    if (limpo === '') {
        return null;
    }

    return /^[\d.\-/\s]+$/.test(limpo) && somenteDigitos(limpo).length >= 3 ? 'documento' : 'nomeCliente';
}

/** Retorna somente CPF/CNPJ com 11 ou 14 dígitos; não afirma validade matemática. */
export function documentoParaBusca(valor) {
    const documento = somenteDigitos(String(valor ?? ''));

    return [11, 14].includes(documento.length) ? documento : '';
}

/** Normaliza candidatos do backend para o formato seguro do autocomplete. */
export function candidatosDocumento(corpo) {
    const candidatos = Array.isArray(corpo?.clients)
        ? corpo.clients
        : corpo?.client ? [corpo.client] : [];

    return candidatos
        .filter((item) => item && item.id !== undefined && item.id !== null && String(item.id) !== '')
        .map((item) => ({
            id: String(item.id),
            valor: String(item.valor ?? ''),
            detalhe: String(item.detalhe ?? ''),
        }));
}

/** Erros por campo de uma resposta 422, ou {} para qualquer outro corpo. */
export function errosDaResposta(corpo) {
    const erros = corpo && typeof corpo === 'object' ? corpo.erros : null;

    return erros && typeof erros === 'object' && !Array.isArray(erros) ? erros : {};
}

/** Mensagem do toast quando o erro não é de campo (resposta que não é JSON, como a 403 do CSRF vencido). */
export function mensagemDeErro(erro, erros = {}) {
    if (erro?.corpo === null || typeof erro?.corpo !== 'object') {
        return erro?.status === 403
            ? 'A página ficou muito tempo aberta e expirou. Recarregue e tente de novo.'
            : 'Não foi possível cadastrar agora. Tente de novo.';
    }

    return String(erros._geral ?? erro.corpo.message ?? '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
        || 'Não foi possível cadastrar agora. Tente de novo.';
}

const camposDoFormulario = (formulario) => [...formulario.elements].filter((campo) => campo.willValidate && campo.type !== 'hidden' && campo.id);

export default function iniciar(raiz, { get = getPadrao, post = postPadrao, doc = document } = {}) {
    const formulario = raiz.querySelector('form');
    const dialogo = raiz.querySelector('dialog');
    if (!formulario || !dialogo) {
        return;
    }

    const botao = raiz.querySelector(`[type=submit][form="${formulario.id}"]`);
    const campoDocumento = formulario.elements.namedItem('documento');
    const statusDocumento = raiz.querySelector('[data-documento-status]');
    const listaCandidatos = raiz.querySelector('[data-documento-candidatos]');
    const buscas = ligarBuscas(formulario, raiz);
    let origem = null;
    let esperaBuscaDocumento = null;
    let pedidoBuscaDocumento = 0;

    for (const campo of raiz.querySelectorAll('[data-mascara]')) {
        aplicarMascara(campo);
        campo.addEventListener('input', (evento) => aplicarMascara(campo, evento));
    }

    const ocultarStatusDocumento = () => {
        pedidoBuscaDocumento++;
        if (statusDocumento) {
            statusDocumento.classList.add('hidden');
            statusDocumento.textContent = '';
        }
        listaCandidatos?.replaceChildren();
    };

    const escolherDocumento = (item) => {
        if (!item?.id || !origem) {
            return;
        }
        origem.dispatchEvent(new CustomEvent('autocomplete-documento-encontrado', {
            bubbles: true,
            detail: { item },
        }));
        dialogo.close();
        mostrarToast({ variante: 'success', mensagem: 'Cliente existente selecionado.' });
    };

    const mostrarStatusDocumento = (mensagem, variante = 'muted', candidatos = []) => {
        if (!statusDocumento) {
            return;
        }
        statusDocumento.classList.remove('hidden');
        statusDocumento.classList.toggle('text-danger', variante === 'danger');
        statusDocumento.classList.toggle('text-muted', variante !== 'danger');
        statusDocumento.textContent = mensagem;
        listaCandidatos?.replaceChildren(...candidatos.map((item) => {
            const botaoCandidato = doc.createElement('button');
            botaoCandidato.type = 'button';
            botaoCandidato.className = 'flex w-full flex-col items-start rounded-lg border border-border px-3 py-2 text-left hover:bg-surface-subtle';
            botaoCandidato.addEventListener('click', () => escolherDocumento(item));
            const valor = doc.createElement('span');
            valor.className = 'text-label-md text-text';
            valor.textContent = item.valor;
            botaoCandidato.append(valor);
            if (item.detalhe) {
                const detalhe = doc.createElement('span');
                detalhe.className = 'text-caption text-muted';
                detalhe.textContent = item.detalhe;
                botaoCandidato.append(detalhe);
            }
            return botaoCandidato;
        }));
    };

    const buscarDocumento = async () => {
        const documento = documentoParaBusca(campoDocumento?.value);
        if (!documento || !raiz.dataset.documentoBusca) {
            ocultarStatusDocumento();
            return;
        }

        const numero = ++pedidoBuscaDocumento;
        mostrarStatusDocumento('Consultando cadastro existente…');
        try {
            const resposta = await get(`${raiz.dataset.documentoBusca}?documento=${encodeURIComponent(documento)}`);
            if (numero !== pedidoBuscaDocumento) {
                return;
            }
            const candidatos = candidatosDocumento(resposta);
            if (candidatos.length > 1) {
                mostrarStatusDocumento('Há vários clientes com este documento. Escolha um registro:', 'muted', candidatos);
            } else if (candidatos.length === 1) {
                mostrarStatusDocumento('Este documento já está cadastrado. Escolha o cliente:', 'muted', candidatos);
            } else {
                mostrarStatusDocumento('Nenhum cliente encontrado. Você pode continuar o cadastro.');
            }
        } catch (erro) {
            if (numero !== pedidoBuscaDocumento) {
                return;
            }
            mostrarStatusDocumento(erro?.status === 403
                ? 'Você não tem permissão para consultar clientes.'
                : 'Não foi possível consultar o cadastro agora. Você ainda pode tentar novamente.', 'danger');
        }
    };

    const programarBuscaDocumento = () => {
        clearTimeout(esperaBuscaDocumento);
        esperaBuscaDocumento = setTimeout(buscarDocumento, 300);
    };

    campoDocumento?.addEventListener('input', programarBuscaDocumento);
    campoDocumento?.addEventListener('blur', buscarDocumento);
    doc.addEventListener('autocomplete-novo', (evento) => {
        if (evento.target?.dataset?.autocompleteNovoModal !== dialogo.id) {
            return;
        }

        origem = evento.target;
        for (const campo of camposDoFormulario(formulario)) {
            mostrarErroDoCampo(campo, '', doc);
        }
        formulario.reset();
        buscas.reiniciar();
        ocultarStatusDocumento();

        const alvo = campoDoTexto(evento.detail?.texto);
        if (alvo) {
            const campo = formulario.elements.namedItem(alvo);
            campo.value = evento.detail.texto.trim();
            aplicarMascara(campo);
        }
        // O foco vai para o campo que o texto digitado não preencheu.
        formulario.elements.namedItem(alvo === 'nomeCliente' ? 'documento' : 'nomeCliente')?.focus();
        if (alvo === 'documento') {
            programarBuscaDocumento();
        }
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        let primeiro = null;
        for (const campo of camposDoFormulario(formulario)) {
            const mensagem = mensagemDoCampo(campo);
            mostrarErroDoCampo(campo, mensagem, doc);
            primeiro ??= mensagem ? campo : null;
        }
        if (primeiro) {
            primeiro.focus();
            return;
        }

        marcarCarregando(botao, true);
        try {
            const resposta = await post(formulario.action, new FormData(formulario));

            dialogo.close();
            mostrarToast({ variante: 'success', mensagem: resposta.message || 'Cliente cadastrado.' });
            origem?.dispatchEvent(new CustomEvent('autocomplete-novo-criado', { bubbles: true, detail: { item: resposta.cliente } }));
        } catch (erro) {
            if (erro?.status === 409) {
                const candidatos = candidatosDocumento(erro.corpo);
                if (candidatos.length === 0) {
                    mostrarToast({ variante: 'danger', mensagem: mensagemDeErro(erro) });
                    return;
                }
                mostrarStatusDocumento(candidatos.length > 1
                    ? 'Há vários clientes com este documento. Escolha um registro:'
                    : 'Este documento já está cadastrado. Escolha o cliente:', 'muted', candidatos);
                return;
            }
            const erros = errosDaResposta(erro?.corpo);
            let focado = null;
            for (const [nome, mensagem] of Object.entries(erros)) {
                const campo = nome === '_geral' ? null : formulario.elements.namedItem(nome);
                const alvo = campo instanceof RadioNodeList ? campo[0] : campo;
                if (alvo?.id) {
                    mostrarErroDoCampo(alvo, String(mensagem), doc);
                    focado ??= alvo;
                }
            }

            if (focado) {
                focado.focus();
            } else {
                mostrarToast({ variante: 'danger', mensagem: mensagemDeErro(erro, erros) });
            }
        } finally {
            marcarCarregando(botao, false);
        }
    });
}
