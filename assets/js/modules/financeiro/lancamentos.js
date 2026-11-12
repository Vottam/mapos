import { ErroHttp, post } from '../../lib/http.js';
import { mostrarToast } from '../../lib/toast.js';

function mensagemDoErro(erro) {
    if (!(erro instanceof ErroHttp)) {
        return 'Não foi possível falar com o servidor. Confira a conexão e tente de novo.';
    }

    if (typeof erro.corpo === 'object' && erro.corpo?.message) {
        return String(erro.corpo.message);
    }

    if (erro.status === 403) {
        return 'Sua sessão ou permissão não permite excluir este lançamento.';
    }

    if (erro.status === 404) {
        return 'Este lançamento não existe mais ou já foi excluído.';
    }

    if (erro.status === 409) {
        return 'Este lançamento possui dependências e não pode ser excluído agora.';
    }

    if (erro.status === 422) {
        return 'Selecione um lançamento válido antes de excluir.';
    }

    return 'Não foi possível excluir o lançamento. Nenhuma alteração foi confirmada.';
}

export default function iniciar(raiz = document) {
    const form = raiz.querySelector('#form-excluir-lancamento') || document.querySelector('#form-excluir-lancamento');
    if (!form) {
        return;
    }

    const dialogo = raiz.querySelector('#excluir-lancamento') || document.querySelector('#excluir-lancamento');
    const botao = dialogo?.querySelector('button[type="submit"]') ?? null;

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        if (form.dataset.financeiroEnviando === '1') {
            return;
        }

        form.dataset.financeiroEnviando = '1';
        form.setAttribute('aria-busy', 'true');
        if (botao) {
            botao.disabled = true;
        }

        try {
            const resposta = await post(form.action, new FormData(form));

            if (resposta?.result !== true) {
                mostrarToast({
                    mensagem: resposta?.message || 'Não foi possível excluir o lançamento.',
                    variante: 'danger',
                    duracao: 8000,
                }, raiz);
                return;
            }

            dialogo?.close();
            // O controller grava a mensagem de sucesso na sessão. O reload
            // preserva os filtros da URL e atualiza a linha, os totais e o toast.
            window.location.reload();
        } catch (erro) {
            mostrarToast({ mensagem: mensagemDoErro(erro), variante: 'danger', duracao: 8000 }, raiz);
            // A confirmação permanece aberta para nova tentativa ou cancelamento.
        } finally {
            delete form.dataset.financeiroEnviando;
            form.removeAttribute('aria-busy');
            if (botao) {
                botao.disabled = false;
            }
        }
    });
}
