// Busca de cliente por CPF/CNPJ, compartilhada entre as telas da OS e da venda
// (views/os/formulario.php e views/vendas/formulario.php). Toda opção recebida
// da API é inserida com textContent; nenhuma resposta vira HTML executável.
//
// A view expõe o endpoint em data-documento-busca e o campo
// [name="cliente_documento"]; a busca roda com debounce de 300 ms e AbortController
// (uma consulta por vez; respostas fora de ordem são descartadas). O id do
// registro encontrado entra no autocomplete da tela pelo evento
// "autocomplete-documento-encontrado" (lib/autocomplete.js), sem seleção por
// posição. Quem não é cadastrado vê o aviso e, com a permissão aCliente, o
// botão [data-documento-novo] que abre o modal de cadastro rápido.

import { get as getLocal } from '../../lib/http.js';
import { somenteDigitos } from '../../lib/mascaras.js';

export function normalizarRespostaDocumento(resposta) {
    if (!resposta || typeof resposta !== 'object' || typeof resposta.found !== 'boolean') {
        throw new Error('Resposta inválida do serviço de clientes.');
    }

    if (!resposta.found) {
        if (resposta.reason !== 'not_found') {
            throw new Error('Resposta inválida do serviço de clientes.');
        }
        return [];
    }

    const candidatos = Array.isArray(resposta.clients)
        ? resposta.clients
        : resposta.client ? [resposta.client] : [];
    const normalizados = candidatos.map((cliente) => {
        const id = String(cliente?.id ?? '');
        const valor = String(cliente?.valor ?? '');
        if (!id || !valor) {
            throw new Error('Resposta inválida do serviço de clientes.');
        }
        return {
            id,
            valor,
            detalhe: String(cliente?.detalhe ?? ''),
        };
    });

    if (normalizados.length === 0 || (resposta.duplicate && normalizados.length < 2)) {
        throw new Error('Resposta inválida do serviço de clientes.');
    }

    return normalizados;
}

export function iniciarBuscaDocumento(raiz) {
    const campo = raiz.querySelector('[name="cliente_documento"]');
    const status = raiz.querySelector('[data-documento-status]');
    const novo = raiz.querySelector('[data-documento-novo]');
    const wrapper = raiz.querySelector('[data-autocomplete][data-autocomplete-alvo]');
    const url = raiz.dataset.documentoBusca;
    if (!campo || !status || !wrapper || !url) {
        return;
    }

    let ultimoDocumento = '';
    let espera = null;
    let sequenciaConsulta = 0;
    let controlador = null;

    const invalidarConsulta = () => {
        sequenciaConsulta += 1;
        controlador?.abort();
        controlador = null;
        ultimoDocumento = '';
    };

    const limparSelecaoCliente = () => {
        const campoCliente = wrapper.querySelector('input:not([type="hidden"])');
        const campoId = wrapper.querySelector('input[type="hidden"]');
        if (!campoCliente && !campoId) {
            return;
        }
        if (campoId) {
            campoId.value = '';
            campoId.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (campoCliente) {
            campoCliente.value = '';
            campoCliente.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    const renderizarStatus = (mensagem, variante = 'muted', candidatos = []) => {
        status.replaceChildren();
        status.classList.remove('text-danger', 'text-muted', 'text-success');
        status.classList.add(`text-${variante}`);
        const texto = document.createElement('span');
        texto.textContent = mensagem;
        status.append(texto);
        if (novo) {
            novo.classList.toggle('hidden', variante !== 'success' || candidatos.length > 0);
        }
        if (candidatos.length > 0) {
            const lista = document.createElement('ul');
            lista.className = 'mt-2 flex w-full flex-col gap-1';
            candidatos.forEach((cliente) => {
                const linha = document.createElement('li');
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'font-medium text-link hover:underline';
                botao.textContent = `${cliente.valor || 'Cliente'}${cliente.detalhe ? ` · ${cliente.detalhe}` : ''} (ID ${cliente.id})`;
                botao.addEventListener('click', () => selecionar(cliente));
                linha.append(botao);
                lista.append(linha);
            });
            status.append(lista);
        }
    };

    const abrirCadastro = () => {
        wrapper.dispatchEvent(new CustomEvent('autocomplete-novo', {
            bubbles: true,
            detail: { texto: campo.value },
        }));
        const modal = wrapper.dataset.autocompleteNovoModal
            ? document.getElementById(wrapper.dataset.autocompleteNovoModal)
            : null;
        modal?.showModal?.();
    };

    const selecionar = (cliente) => {
        if (!cliente?.id) {
            return;
        }
        wrapper.dispatchEvent(new CustomEvent('autocomplete-documento-encontrado', {
            bubbles: true,
            detail: {
                item: {
                    id: String(cliente.id),
                    valor: String(cliente.valor ?? ''),
                    detalhe: String(cliente.detalhe ?? ''),
                },
            },
        }));
        renderizarStatus('Cliente identificado e selecionado.', 'success');
    };

    const consultar = async () => {
        const documento = somenteDigitos(campo.value);
        if (![11, 14].includes(documento.length)) {
            invalidarConsulta();
            renderizarStatus('');
            if (novo) {
                novo.classList.add('hidden');
            }
            return;
        }
        if (documento === ultimoDocumento) {
            return;
        }

        const sequencia = ++sequenciaConsulta;
        controlador?.abort();
        const novoControlador = new AbortController();
        controlador = novoControlador;
        ultimoDocumento = documento;
        renderizarStatus('Consultando cadastro local…');
        const tempoLimite = setTimeout(() => novoControlador.abort(), 10000);
        try {
            const resposta = await getLocal(`${url}?documento=${encodeURIComponent(documento)}`, { signal: novoControlador.signal });
            if (sequencia !== sequenciaConsulta || documento !== somenteDigitos(campo.value)) {
                return;
            }
            const candidatos = normalizarRespostaDocumento(resposta);
            if (candidatos.length > 1) {
                renderizarStatus('Há vários clientes com este CPF/CNPJ. Escolha o registro correto:', 'muted', candidatos);
                return;
            }
            if (candidatos.length === 1) {
                selecionar(candidatos[0]);
                return;
            }

            renderizarStatus('Cliente não cadastrado.', 'success');
        } catch (erro) {
            if (sequencia !== sequenciaConsulta || documento !== somenteDigitos(campo.value)) {
                return;
            }
            ultimoDocumento = '';
            renderizarStatus(erro?.status === 403
                ? 'Você não tem permissão para consultar clientes.'
                : 'Não foi possível consultar o cliente agora. Tente novamente.', 'danger');
        } finally {
            clearTimeout(tempoLimite);
            if (controlador === novoControlador) {
                controlador = null;
            }
        }
    };

    campo.addEventListener('input', () => {
        clearTimeout(espera);
        const documento = somenteDigitos(campo.value);
        if (documento !== ultimoDocumento) {
            invalidarConsulta();
            limparSelecaoCliente();
            renderizarStatus('');
        }
        espera = setTimeout(consultar, 300);
    });
    campo.addEventListener('blur', consultar);
    novo?.addEventListener('click', abrirCadastro);
}
