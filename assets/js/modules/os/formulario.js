// Formulário de OS: autocompletes oficiais e vínculo seguro com Equipamentos.
// Toda opção recebida da API é inserida com textContent; nenhuma resposta vira
// HTML executável.

import { iniciarAutocompletes } from '../../lib/autocomplete.js';
import { get as getLocal } from '../../lib/http.js';
import { somenteDigitos } from '../../lib/mascaras.js';

const CAMPOS_EQUIPAMENTO = ['equipamento', 'marca', 'modelo', 'num_serie'];
const CAMPOS_AUTOPREENCHIVEIS = ['equipamento', 'marca', 'modelo'];

const campoPorChave = (raiz, chave) => raiz.querySelector(`[data-equipamento-campo="${chave}"]`);

export function normalizarSerialParaConsulta(valor) {
    const texto = String(valor ?? '').trim().replace(/\s+/gu, ' ');
    return texto.toLocaleUpperCase('pt-BR');
}

export function classificarRespostaSerial(payload) {
    const status = String(payload?.status ?? (payload?.found ? 'found' : 'not_found'));
    const permitidos = new Set(['found', 'not_found', 'empty', 'invalid', 'ambiguous', 'ownership_mismatch']);
    return {
        status: permitidos.has(status) ? status : 'not_found',
        encontrado: status === 'found' && Boolean(payload?.equipamento),
    };
}

export function respostaSerialObsoleta({ sequencia, sequenciaAtual, valorNormalizado, valorAtual, clienteId, clienteAtual }) {
    return sequencia !== sequenciaAtual
        || normalizarSerialParaConsulta(valorAtual) !== valorNormalizado
        || String(clienteAtual ?? '') !== String(clienteId ?? '');
}

function executarSemMarcarManual(raiz, callback) {
    raiz.dataset.equipamentoAplicando = 'true';
    try {
        return callback();
    } finally {
        delete raiz.dataset.equipamentoAplicando;
    }
}

function preencherCampos(raiz, item, { preservarManuais = false, incluirSerial = true, origem = 'select' } = {}) {
    const conflitos = [];
    const campos = incluirSerial ? CAMPOS_EQUIPAMENTO : CAMPOS_AUTOPREENCHIVEIS;
    executarSemMarcarManual(raiz, () => {
        for (const chave of campos) {
            const campo = campoPorChave(raiz, chave);
            if (!campo) {
                continue;
            }
            const valorAtual = String(campo.value ?? '');
            const valorAutopreenchido = String(campo.dataset.equipamentoAutopreenchido ?? '');
            const manual = preservarManuais
                && campo.dataset.equipamentoManual === 'true'
                && valorAtual !== valorAutopreenchido;
            if (manual) {
                conflitos.push(chave);
                continue;
            }
            const valor = String(item?.[chave] ?? '');
            campo.value = valor;
            if (origem === 'lookup' && item) {
                campo.dataset.equipamentoAutopreenchido = valor;
                campo.dataset.equipamentoAutopreenchidoAtivo = 'true';
                delete campo.dataset.equipamentoManual;
            } else {
                delete campo.dataset.equipamentoAutopreenchido;
                delete campo.dataset.equipamentoAutopreenchidoAtivo;
                delete campo.dataset.equipamentoManual;
            }
        }
    });

    return conflitos;
}

function limparCamposAutopreenchidos(raiz) {
    executarSemMarcarManual(raiz, () => {
        for (const chave of CAMPOS_AUTOPREENCHIVEIS) {
            const campo = campoPorChave(raiz, chave);
            if (!campo) {
                continue;
            }
            const valorAutopreenchido = String(campo.dataset.equipamentoAutopreenchido ?? '');
            const manual = campo.dataset.equipamentoManual === 'true' && String(campo.value ?? '') !== valorAutopreenchido;
            if (!manual && campo.dataset.equipamentoAutopreenchidoAtivo === 'true') {
                campo.value = '';
            }
            delete campo.dataset.equipamentoAutopreenchido;
            delete campo.dataset.equipamentoAutopreenchidoAtivo;
            if (!manual) {
                delete campo.dataset.equipamentoManual;
            }
        }
    });
}

function configurarRastreamentoManual(raiz, select) {
    for (const chave of CAMPOS_AUTOPREENCHIVEIS) {
        const campo = campoPorChave(raiz, chave);
        if (!campo) {
            continue;
        }
        if (String(campo.value ?? '') !== '' && (!select || select.value === '0' || select.value === '')) {
            campo.dataset.equipamentoManual = 'true';
        }
        const marcar = () => {
            if (raiz.dataset.equipamentoAplicando === 'true') {
                return;
            }
            const valorAutopreenchido = String(campo.dataset.equipamentoAutopreenchido ?? '');
            if (String(campo.value ?? '') === valorAutopreenchido && campo.dataset.equipamentoAutopreenchidoAtivo === 'true') {
                delete campo.dataset.equipamentoManual;
            } else {
                campo.dataset.equipamentoManual = 'true';
            }
        };
        campo.addEventListener('input', marcar);
        campo.addEventListener('change', marcar);
    }
}

function limparHistorico(raiz) {
    const alvo = raiz.querySelector('[data-equipamento-historico]');
    if (alvo) {
        alvo.replaceChildren();
    }
}

function valorHistorico(valor, fallback = '-') {
    const texto = String(valor ?? '').trim();
    return texto || fallback;
}

function criarCampoHistorico(label, valor, classe = '') {
    const wrapper = document.createElement('div');
    wrapper.className = classe || 'flex flex-col gap-1';
    const titulo = document.createElement('dt');
    titulo.className = 'text-caption text-muted';
    titulo.textContent = label;
    const conteudo = document.createElement('dd');
    conteudo.className = 'whitespace-pre-wrap break-words text-body-sm text-text';
    conteudo.textContent = valorHistorico(valor);
    wrapper.append(titulo, conteudo);
    return wrapper;
}

function textoParaObservacoes(item) {
    const periodo = `${valorHistorico(item.dataInicial)}${item.dataFinal ? ` até ${item.dataFinal}` : ''}`;
    const garantia = `${valorHistorico(item.situacaoGarantia, 'Indefinida')}${item.vencimentoGarantia ? ` (vence em ${item.vencimentoGarantia})` : ''}`;
    const equipamento = [item.equipamento, item.marca, item.modelo].filter(Boolean).join(' · ');
    const linhas = [
        `[Histórico anterior por número de série — OS #${item.idOs}]`,
        `Cliente: ${valorHistorico(item.cliente)}`,
        `Data: ${periodo}`,
        `Status: ${valorHistorico(item.status)}`,
        `Garantia: ${garantia}`,
        `Equipamento: ${valorHistorico(equipamento)}`,
        `Série: ${valorHistorico(item.num_serie)}`,
        'Descrição:',
        valorHistorico(item.descricaoProduto),
        'Defeito:',
        valorHistorico(item.defeito),
        'Laudo:',
        valorHistorico(item.laudoTecnico),
        'Observações:',
        valorHistorico(item.observacoes),
    ];

    return linhas.join('\n');
}

function copiarParaObservacoes(raiz, item, aviso) {
    const campo = raiz.querySelector('[name="observacoes"]');
    if (!campo) {
        aviso.textContent = 'O campo Observações não está disponível nesta tela.';
        return;
    }

    const bloco = textoParaObservacoes(item);
    const atual = String(campo.value || '').trim();
    if (atual.includes(bloco)) {
        aviso.textContent = `O conteúdo da OS #${item.idOs} já está nas Observações.`;
        return;
    }

    campo.value = atual ? `${atual}\n\n${bloco}` : bloco;
    campo.dispatchEvent(new Event('input', { bubbles: true }));
    campo.dispatchEvent(new Event('change', { bubbles: true }));
    aviso.textContent = `Histórico da OS #${item.idOs} copiado sem salvar a nova OS.`;
}

function selecionarRetornoGarantia(raiz, item, aviso) {
    const toggle = raiz.querySelector('[data-garantia-retorno-toggle]');
    const origem = raiz.querySelector('[data-garantia-origem]');
    const info = raiz.querySelector('[data-garantia-origem-info]');
    if (!toggle || !origem) {
        aviso.textContent = 'O controle de retorno em garantia não está disponível nesta tela.';
        return;
    }

    copiarParaObservacoes(raiz, item, aviso);
    toggle.checked = true;
    origem.value = String(item.idOs);
    const serial = raiz.querySelector('[data-equipamento-serial]');
    if (serial) {
        raiz.dataset.garantiaOrigemSerial = serial.value.trim();
    }
    if (info) {
        info.textContent = `Origem selecionada: OS #${item.idOs}. A nova OS ainda não foi salva.`;
        info.classList.remove('hidden');
    }
    toggle.dispatchEvent(new Event('change', { bubbles: true }));
    aviso.textContent = `Histórico da OS #${item.idOs} copiado e retorno em garantia marcado. Confirme manualmente e salve a nova OS.`;
}

function garantiaVisual(item) {
    const status = String(item.garantiaStatus ?? '').trim();
    const situacao = String(item.situacaoGarantia ?? '').trim().toLowerCase();
    if (status === 'vigente' || situacao === 'em garantia') {
        const vencimento = item.vencimentoGarantia ? `Vencimento: ${item.vencimentoGarantia}.` : 'Prazo calculado a partir da OS anterior.';
        const dias = Number(item.diasRestantes) > 0 ? ` Restam ${Number(item.diasRestantes)} dia(s).` : ' Vence hoje.';
        return {
            chave: 'vigente',
            caixa: 'bg-success-soft',
            texto: 'text-success-ink',
            titulo: 'Dentro do prazo — possível garantia',
            detalhe: `${vencimento}${dias}`,
        };
    }
    if (status === 'vencida' || situacao === 'fora de garantia') {
        const vencimento = item.vencimentoGarantia ? `Vencimento: ${item.vencimentoGarantia}.` : 'Vencimento não informado.';
        const dias = Number(item.diasVencidos) > 0 ? ` Transcorrido: ${Number(item.diasVencidos)} dia(s).` : '';
        return {
            chave: 'vencida',
            caixa: 'bg-danger-soft',
            texto: 'text-danger-ink',
            titulo: 'Prazo vencido',
            detalhe: `${vencimento}${dias}`,
        };
    }
    if (status === 'sem_garantia_registrada' || situacao === 'sem garantia registrada') {
        return {
            chave: 'sem-garantia',
            caixa: 'bg-neutral-soft',
            texto: 'text-neutral-ink',
            titulo: 'Sem prazo de garantia registrado',
            detalhe: 'A consulta não comprova cobertura automática.',
        };
    }
    return {
        chave: 'indeterminada',
        caixa: 'bg-neutral-soft',
        texto: 'text-neutral-ink',
        titulo: 'Prazo indeterminável',
        detalhe: 'Data final ou prazo válido não disponível para cálculo.',
    };
}

function criarSeloGarantia(item, compacto = false) {
    const visual = garantiaVisual(item);
    const selo = document.createElement('div');
    selo.className = `flex flex-col gap-1 rounded-lg border border-current/28 ${compacto ? 'px-3 py-2' : 'mb-4 px-3 py-3'} ${visual.caixa} ${visual.texto}`;
    selo.dataset.garantiaClassificacao = visual.chave;
    const titulo = document.createElement('strong');
    titulo.className = 'text-caption font-semibold';
    titulo.textContent = visual.titulo;
    const detalhe = document.createElement('span');
    detalhe.className = 'text-caption';
    detalhe.textContent = visual.detalhe;
    selo.append(titulo, detalhe);
    return selo;
}

function textoResumoAtendimento(item) {
    const texto = valorHistorico(item.defeito || item.descricaoProduto, 'Sem defeito ou descrição registrada.');
    return texto.length > 260 ? `${texto.slice(0, 257).trimEnd()}…` : texto;
}

function criarCartaoHistorico(raiz, item, critica) {
    const card = document.createElement('article');
    card.className = 'flex flex-col gap-3 rounded-xl border border-border-subtle p-3 sm:p-4';
    card.dataset.historicoOs = String(item.idOs);

    const topo = document.createElement('div');
    topo.className = 'flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between';
    const identificacao = document.createElement('div');
    const os = document.createElement('h3');
    os.className = 'text-body-sm font-semibold text-text';
    os.textContent = `OS #${item.idOs}`;
    const data = document.createElement('p');
    data.className = 'text-caption text-muted';
    data.textContent = `Data do atendimento: ${valorHistorico(item.dataFinal || item.dataInicial, 'não informada')}`;
    identificacao.append(os, data);
    const status = document.createElement('p');
    status.className = 'text-caption font-medium text-text';
    status.textContent = `Status: ${valorHistorico(item.status)}`;
    topo.append(identificacao, status);
    card.append(topo);

    const descricaoGrupo = document.createElement('div');
    descricaoGrupo.className = 'flex flex-col gap-1';
    const descricaoTitulo = document.createElement('span');
    descricaoTitulo.className = 'text-caption text-muted';
    descricaoTitulo.textContent = 'Defeito ou descrição';
    const descricao = document.createElement('p');
    descricao.className = 'whitespace-pre-wrap break-words text-body-sm text-text';
    descricao.textContent = textoResumoAtendimento(item);
    descricaoGrupo.append(descricaoTitulo, descricao);
    card.append(descricaoGrupo, criarSeloGarantia(item, true));

    const acoes = document.createElement('div');
    acoes.className = 'flex flex-wrap gap-2';
    const detalhes = document.createElement('button');
    detalhes.type = 'button';
    detalhes.className = 'rounded-lg border border-border bg-surface px-3 py-2 text-caption font-medium text-link hover:bg-surface-subtle';
    detalhes.textContent = 'Ver detalhes';
    detalhes.addEventListener('click', () => abrirHistoricoModal(raiz, item, critica));
    acoes.append(detalhes);
    card.append(acoes);
    return card;
}

function appendCampoHistorico(lista, label, valor) {
    lista.append(criarCampoHistorico(label, valor));
}

function abrirHistoricoModal(raiz, item, critica) {
    let modal = raiz.querySelector('[data-equipamento-historico-modal]');
    if (!modal) {
        modal = document.createElement('dialog');
        modal.dataset.equipamentoHistoricoModal = 'true';
        modal.className = 'm-auto max-h-[92vh] w-[min(760px,calc(100%-1rem))] rounded-xl border border-border bg-surface p-0 text-text shadow-xl backdrop:bg-black/50';
        raiz.append(modal);
    }

    modal.replaceChildren();
    modal.setAttribute('aria-label', `Detalhes da OS #${item.idOs}`);
    const cabecalho = document.createElement('div');
    cabecalho.className = 'flex items-start justify-between gap-4 border-b border-border-subtle p-4 sm:p-6';
    const tituloGrupo = document.createElement('div');
    const titulo = document.createElement('h2');
    titulo.className = 'text-heading-sm text-text';
    titulo.textContent = `Detalhes da OS #${item.idOs}`;
    const subtitulo = document.createElement('p');
    subtitulo.className = 'mt-1 text-caption text-muted';
    subtitulo.textContent = `Atendimento em ${valorHistorico(item.dataFinal || item.dataInicial, 'data não informada')}. Consulta informativa.`;
    tituloGrupo.append(titulo, subtitulo);
    const fechar = document.createElement('button');
    fechar.type = 'button';
    fechar.className = 'rounded-lg border border-border px-3 py-2 text-caption text-muted hover:bg-surface-subtle';
    fechar.textContent = 'Fechar';
    fechar.addEventListener('click', () => modal.close());
    cabecalho.append(tituloGrupo, fechar);
    modal.append(cabecalho);

    const corpo = document.createElement('div');
    corpo.className = 'max-h-[calc(92vh-9rem)] overflow-y-auto p-4 sm:p-6';
    const aviso = document.createElement('p');
    aviso.className = 'mb-4 min-h-5 text-caption text-success';
    corpo.append(aviso, criarSeloGarantia(item));

    if (critica && Number(critica.baseOsId) === Number(item.idOs)) {
        const alerta = document.createElement('div');
        alerta.className = 'mb-4 rounded-lg border border-border-subtle bg-surface-subtle p-3';
        const alertaTitulo = document.createElement('strong');
        alertaTitulo.className = 'text-body-sm text-text';
        alertaTitulo.textContent = valorHistorico(critica.titulo, 'Avaliação da garantia');
        const alertaTexto = document.createElement('p');
        alertaTexto.className = 'mt-1 whitespace-pre-wrap text-caption text-muted';
        alertaTexto.textContent = `${valorHistorico(critica.contador)}. ${valorHistorico(critica.texto)}`;
        alerta.append(alertaTitulo, alertaTexto);
        corpo.append(alerta);
    }

    const dados = document.createElement('dl');
    dados.className = 'grid gap-3 sm:grid-cols-2';
    appendCampoHistorico(dados, 'Cliente', item.cliente);
    appendCampoHistorico(dados, 'Entrada', item.dataInicial);
    appendCampoHistorico(dados, 'Finalização', item.dataFinal);
    appendCampoHistorico(dados, 'Status', item.status);
    appendCampoHistorico(dados, 'Equipamento', [item.equipamento, item.marca, item.modelo, item.num_serie].filter(Boolean).join(' · '));
    appendCampoHistorico(dados, 'Descrição', item.descricaoProduto);
    appendCampoHistorico(dados, 'Defeito', item.defeito);
    appendCampoHistorico(dados, 'Laudo técnico', item.laudoTecnico);
    appendCampoHistorico(dados, 'Observações', item.observacoes);
    corpo.append(dados);

    const acoes = document.createElement('div');
    acoes.className = 'mt-4 flex flex-wrap gap-2';
    const copiar = document.createElement('button');
    copiar.type = 'button';
    copiar.className = 'rounded-lg border border-border px-3 py-2 text-caption text-text hover:bg-surface-subtle';
    copiar.textContent = 'Copiar para Observações';
    copiar.addEventListener('click', () => copiarParaObservacoes(raiz, item, aviso));
    acoes.append(copiar);
    const receber = document.createElement('button');
    receber.type = 'button';
    receber.className = 'rounded-lg bg-primary px-3 py-2 text-caption font-medium text-on-primary hover:opacity-90';
    receber.textContent = 'Receber em Garantia';
    receber.addEventListener('click', () => selecionarRetornoGarantia(raiz, item, aviso));
    acoes.append(receber);
    if (item.urlVisualizarOs) {
        const abrir = document.createElement('a');
        abrir.className = 'rounded-lg border border-border px-3 py-2 text-caption font-medium text-link hover:bg-surface-subtle';
        abrir.href = item.urlVisualizarOs;
        abrir.target = '_blank';
        abrir.rel = 'noopener noreferrer';
        abrir.textContent = 'Abrir OS anterior';
        acoes.append(abrir);
    }
    corpo.append(acoes);
    modal.append(corpo);
    if (typeof modal.showModal === 'function' && ! modal.open) {
        modal.showModal();
    }
}

function mostrarHistorico(raiz, payload) {
    const alvo = raiz.querySelector('[data-equipamento-historico]');
    if (!alvo) {
        return;
    }
    const itens = Array.isArray(payload) ? payload : (Array.isArray(payload?.historico) ? payload.historico : []);
    const total = Number(payload?.total) > 0 ? Number(payload.total) : itens.length;
    const critica = Array.isArray(payload) ? null : payload?.garantiaCritica;
    alvo.replaceChildren();
    if (!itens.length) {
        const vazio = document.createElement('p');
        vazio.className = 'text-caption text-muted';
        vazio.textContent = 'Nenhum histórico anterior para este número de série.';
        alvo.append(vazio);
        return;
    }

    const resumo = document.createElement('div');
    resumo.className = 'flex flex-col gap-2 rounded-lg border border-border-subtle bg-surface-subtle p-3';
    const texto = document.createElement('p');
    texto.className = 'text-caption font-medium text-text';
    texto.textContent = `${total} atendimento(s) anterior(es) encontrado(s).`;
    const orientacao = document.createElement('p');
    orientacao.className = 'text-caption text-muted';
    orientacao.textContent = 'A situação da garantia é informativa; a cobertura depende da decisão manual do técnico.';
    resumo.append(texto, orientacao);
    alvo.append(resumo);

    const lista = document.createElement('div');
    lista.className = 'mt-3 flex flex-col gap-3';
    const controle = document.createElement('div');
    controle.className = 'flex flex-wrap justify-end';
    const mostrarTodos = { valor: false };
    const renderizar = () => {
        lista.replaceChildren();
        const visiveis = mostrarTodos.valor ? itens : itens.slice(0, 3);
        for (const item of visiveis) {
            lista.append(criarCartaoHistorico(raiz, item, critica));
        }
        controle.replaceChildren();
        if (itens.length > 3) {
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'rounded-lg border border-border px-3 py-2 text-caption font-medium text-link hover:bg-surface-subtle';
            botao.setAttribute('aria-expanded', mostrarTodos.valor ? 'true' : 'false');
            botao.textContent = mostrarTodos.valor
                ? 'Mostrar apenas os 3 atendimentos mais recentes'
                : `Mostrar todos os atendimentos (${total})`;
            botao.addEventListener('click', () => {
                mostrarTodos.valor = !mostrarTodos.valor;
                renderizar();
            });
            controle.append(botao);
        }
    };
    alvo.append(lista, controle);
    renderizar();
}

async function jsonGet(url) {
    const resposta = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
    });
    if (!resposta.ok) {
        throw new Error(`HTTP ${resposta.status}`);
    }
    return resposta.json();
}

function iniciarBuscaDocumento(raiz) {
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
            ultimoDocumento = '';
            renderizarStatus('');
            if (novo) {
                novo.classList.add('hidden');
            }
            return;
        }
        if (documento === ultimoDocumento) {
            return;
        }

        ultimoDocumento = documento;
        renderizarStatus('Consultando cadastro local…');
        try {
            const resposta = await getLocal(`${url}?documento=${encodeURIComponent(documento)}`);
            if (documento !== ultimoDocumento) {
                return;
            }
            const candidatos = Array.isArray(resposta?.clients)
                ? resposta.clients
                : resposta?.client ? [resposta.client] : [];
            if (resposta?.duplicate || candidatos.length > 1) {
                renderizarStatus('Há vários clientes com este CPF/CNPJ. Escolha o registro correto:', 'muted', candidatos);
                return;
            }
            if (candidatos.length === 1) {
                selecionar(candidatos[0]);
                return;
            }

            renderizarStatus('Cliente não cadastrado.', 'success');
        } catch (erro) {
            if (documento !== ultimoDocumento) {
                return;
            }
            renderizarStatus(erro?.status === 403
                ? 'Você não tem permissão para consultar clientes.'
                : 'Não foi possível consultar o cliente agora. Tente novamente.', 'danger');
        }
    };

    campo.addEventListener('input', () => {
        clearTimeout(espera);
        if (somenteDigitos(campo.value) !== ultimoDocumento && somenteDigitos(campo.value).length < 11) {
            ultimoDocumento = '';
            renderizarStatus('');
        }
        espera = setTimeout(consultar, 300);
    });
    campo.addEventListener('blur', consultar);
    novo?.addEventListener('click', abrirCadastro);
}
function iniciarEquipamentos(raiz) {
    const cliente = raiz.querySelector('[name="clientes_id"]');
    const select = raiz.querySelector('[data-equipamento-select]');
    const status = raiz.querySelector('[data-equipamento-status]');
    const serial = raiz.querySelector('[data-equipamento-serial]');
    const retorno = raiz.querySelector('[data-garantia-retorno-toggle]');
    const origem = raiz.querySelector('[data-garantia-origem]');
    const origemInfo = raiz.querySelector('[data-garantia-origem-info]');
    if (!cliente || !select) {
        return;
    }

    configurarRastreamentoManual(raiz, select);
    const urlBase = raiz.dataset.equipamentosUrl;
    const historicoUrl = raiz.dataset.historicoUrl;
    const osId = raiz.dataset.osId || '0';
    let carregamento = 0;
    let timer = null;
    let sequenciaConsulta = 0;
    let ultimaChave = '';
    let consultaAgendadaChave = '';
    const historicoCache = new Map();
    let serialDaOrigem = '';

    const informar = (mensagem) => {
        if (status) {
            status.textContent = mensagem;
        }
    };

    const invalidarConsulta = () => {
        sequenciaConsulta += 1;
        window.clearTimeout(timer);
        timer = null;
        ultimaChave = '';
        consultaAgendadaChave = '';
        limparHistorico(raiz);
    };

    const limparOrigem = () => {
        if (origem) {
            origem.value = '';
        }
        if (retorno) {
            retorno.checked = false;
        }
        if (origemInfo) {
            origemInfo.textContent = '';
            origemInfo.classList.add('hidden');
        }
        delete raiz.dataset.garantiaOrigemSerial;
        serialDaOrigem = '';
    };

    const limparSelecao = () => {
        if (select.value !== '0') {
            select.value = '0';
        }
        delete raiz.dataset.equipamentoSelecao;
    };

    const opcaoSelecionadaCorresponde = (valorNormalizado) => {
        const option = select.selectedOptions[0];
        return Boolean(option && option.value !== '0'
            && normalizarSerialParaConsulta(option.dataset.numSerie || '') === valorNormalizado);
    };

    const selecionarEquipamentoEncontrado = (item) => {
        if (!item?.id || !cliente.value) {
            return;
        }
        let option = [...select.options].find((candidato) => candidato.value === String(item.id));
        if (!option) {
            option = new Option(
                [item.equipamento, item.marca, item.modelo, item.num_serie].filter(Boolean).join(' · ') || `Equipamento #${item.id}`,
                String(item.id)
            );
            option.dataset.equipamento = item.equipamento || '';
            option.dataset.marca = item.marca || '';
            option.dataset.modelo = item.modelo || '';
            option.dataset.numSerie = item.num_serie || '';
            select.append(option);
        }
        select.value = String(item.id);
        raiz.dataset.equipamentoSelecao = 'lookup';
    };

    retorno?.addEventListener('change', () => {
        if (!retorno.checked) {
            limparOrigem();
            return;
        }
        if (origem?.value && origemInfo) {
            origemInfo.textContent = `Origem selecionada: OS #${origem.value}. A nova OS ainda não foi salva.`;
            origemInfo.classList.remove('hidden');
        }
    });

    const carregar = async (clienteId, preservar = false) => {
        const sequencia = ++carregamento;
        invalidarConsulta();
        const serialAtual = serial?.value.trim() || '';
        if (!clienteId) {
            select.replaceChildren(new Option('Escolha um cliente primeiro', '0'));
            if (!serialAtual) {
                preencherCampos(raiz, null);
            } else {
                limparCamposAutopreenchidos(raiz);
            }
            limparHistorico(raiz);
            limparOrigem();
            informar('');
            return;
        }
        informar('Carregando equipamentos…');
        try {
            const itens = await jsonGet(`${urlBase}/${encodeURIComponent(clienteId)}`);
            if (sequencia !== carregamento) {
                return;
            }
            const idAtual = select.value;
            select.replaceChildren(new Option('Informar novo equipamento', '0'));
            for (const item of itens) {
                const option = new Option(item.label, String(item.id));
                option.dataset.equipamento = item.equipamento || '';
                option.dataset.marca = item.marca || '';
                option.dataset.modelo = item.modelo || '';
                option.dataset.numSerie = item.num_serie || '';
                select.append(option);
            }
            if (preservar && idAtual && [...select.options].some((option) => option.value === idAtual)) {
                select.value = idAtual;
            } else if (!preservar) {
                select.value = '0';
                if (serialAtual) {
                    limparCamposAutopreenchidos(raiz);
                } else {
                    preencherCampos(raiz, null);
                }
                limparOrigem();
            }
            informar(itens.length ? `${itens.length} equipamento(s) disponível(is) para este cliente.` : 'Nenhum equipamento cadastrado para este cliente.');
            if (serial?.value.trim()) {
                serial.dispatchEvent(new Event('input', { bubbles: true }));
            }
        } catch (erro) {
            if (sequencia === carregamento) {
                select.replaceChildren(new Option('Não foi possível carregar equipamentos', '0'));
                informar('A lista de equipamentos não pôde ser carregada. Você ainda pode informar um novo.');
            }
        }
    };

    const aplicarResposta = (payload, chave, valorNormalizado) => {
        const classificacao = classificarRespostaSerial(payload);
        const statusResposta = classificacao.status;
        if (classificacao.encontrado && payload?.equipamento) {
            const conflitos = preencherCampos(raiz, payload.equipamento, {
                preservarManuais: true,
                incluirSerial: false,
                origem: 'lookup',
            });
            if (cliente.value && !opcaoSelecionadaCorresponde(valorNormalizado)) {
                selecionarEquipamentoEncontrado(payload.equipamento);
            }
            mostrarHistorico(raiz, payload);
            const identificacao = `Equipamento encontrado (#${payload.equipamento.id}).`;
            if (payload.clienteNecessario) {
                informar(`${identificacao} Selecione o cliente para validar o vínculo e carregar o histórico.`);
            } else if (conflitos.length) {
                informar(`${identificacao} Campos alterados manualmente foram preservados: ${conflitos.join(', ')}.`);
            } else if (Number(payload.total) > 0) {
                informar(`${identificacao} Histórico anterior carregado.`);
            } else {
                informar(`${identificacao} Nenhum atendimento anterior encontrado.`);
            }
            return;
        }

        limparCamposAutopreenchidos(raiz);
        limparHistorico(raiz);
        if (statusResposta === 'ownership_mismatch') {
            limparSelecao();
            informar('Equipamento encontrado, mas pertence a outro cliente. Nenhum vínculo foi criado.');
        } else if (statusResposta === 'ambiguous') {
            limparSelecao();
            informar('Serial ambíguo: há mais de um equipamento. Escolha o cadastro correto no dropdown.');
        } else if (statusResposta === 'invalid') {
            limparSelecao();
            informar('Número de série inválido para consulta.');
        } else if (statusResposta === 'empty') {
            limparSelecao();
            informar('');
        } else {
            limparSelecao();
            informar('Equipamento não encontrado. Você pode cadastrar um novo equipamento.');
        }
    };

    const consultarSerial = () => {
        const valorOriginal = serial?.value.trim() || '';
        const valorNormalizado = normalizarSerialParaConsulta(valorOriginal);
        const clienteId = cliente.value || '';
        const equipamentoId = select.value && select.value !== '0' ? select.value : '';
        const chave = `${clienteId}|${valorNormalizado}|${osId}|${equipamentoId}`;
        if (chave === ultimaChave || chave === consultaAgendadaChave) {
            return;
        }

        const sequencia = ++sequenciaConsulta;
        window.clearTimeout(timer);
        timer = null;
        ultimaChave = '';
        consultaAgendadaChave = '';
        limparHistorico(raiz);
        if (origem?.value && (serialDaOrigem || raiz.dataset.garantiaOrigemSerial)
            && valorOriginal !== (serialDaOrigem || raiz.dataset.garantiaOrigemSerial)) {
            limparOrigem();
        }
        if (equipamentoId && !opcaoSelecionadaCorresponde(valorNormalizado)) {
            limparSelecao();
        }
        if (!valorNormalizado) {
            limparCamposAutopreenchidos(raiz);
            informar('');
            return;
        }
        if (!historicoUrl) {
            informar('Consulta por serial indisponível nesta tela.');
            return;
        }

        informar('Pesquisando equipamento…');
        const cached = historicoCache.get(chave);
        if (cached) {
            ultimaChave = chave;
            aplicarResposta(cached, chave, valorNormalizado);
            return;
        }

        consultaAgendadaChave = chave;
        timer = window.setTimeout(async () => {
            try {
                const url = new URL(historicoUrl, window.location.origin);
                url.searchParams.set('serial', valorNormalizado);
                url.searchParams.set('os_id', osId);
                url.searchParams.set('clientes_id', clienteId);
                if (select.value && select.value !== '0' && opcaoSelecionadaCorresponde(valorNormalizado)) {
                    url.searchParams.set('equipamento_id', select.value);
                }
                const payload = await jsonGet(url.toString());
                if (respostaSerialObsoleta({
                    sequencia,
                    sequenciaAtual: sequenciaConsulta,
                    valorNormalizado,
                    valorAtual: serial.value,
                    clienteId,
                    clienteAtual: cliente.value,
                })) {
                    return;
                }
                historicoCache.set(chave, payload);
                consultaAgendadaChave = '';
                ultimaChave = chave;
                aplicarResposta(payload, chave, valorNormalizado);
            } catch (erro) {
                if (sequencia !== sequenciaConsulta) {
                    return;
                }
                consultaAgendadaChave = '';
                ultimaChave = '';
                limparHistorico(raiz);
                informar(erro?.message === 'HTTP 403'
                    ? 'Você não tem permissão para consultar o equipamento.'
                    : 'Falha temporária ao consultar o equipamento. Tente novamente.');
            }
        }, 300);
    };

    cliente.addEventListener('change', () => carregar(cliente.value, false));
    select.addEventListener('change', () => {
        const option = select.selectedOptions[0];
        invalidarConsulta();
        if (!option || option.value === '0') {
            delete raiz.dataset.equipamentoSelecao;
            preencherCampos(raiz, null);
            limparOrigem();
            informar('');
            return;
        }
        raiz.dataset.equipamentoSelecao = 'manual';
        preencherCampos(raiz, {
            equipamento: option.dataset.equipamento,
            marca: option.dataset.marca,
            modelo: option.dataset.modelo,
            num_serie: option.dataset.numSerie,
        });
        if (serial) {
            serial.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    serial?.addEventListener('input', consultarSerial);
    serial?.addEventListener('blur', consultarSerial);

    if (cliente.value) {
        carregar(cliente.value, true);
    }
    if (serial?.value && origem?.value) {
        serialDaOrigem = serial.value.trim();
        if (origemInfo) {
            origemInfo.textContent = `Origem selecionada: OS #${origem.value}. A nova OS ainda não foi salva.`;
            origemInfo.classList.remove('hidden');
        }
    }
    if (serial?.value) {
        serial.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

export default function iniciar(elemento) {
    iniciarBuscaDocumento(elemento);
    iniciarAutocompletes(elemento);
    iniciarEquipamentos(elemento);
}
