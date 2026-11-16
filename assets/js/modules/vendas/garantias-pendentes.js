// Estado e fila de salvamento dos snapshots de garantia da tela de Vendas.
// As chamadas ficam serializadas para evitar concorrência no token CSRF e no
// bloqueio transacional da venda; cada item mantém sua própria revisão.

const MAX_DIAS = 4294967295;

export function normalizarDiasGarantia(valor) {
    const texto = String(valor ?? '').trim();
    if (!/^\d+$/.test(texto)) return null;

    const numero = Number(texto);
    return Number.isSafeInteger(numero) && numero <= MAX_DIAS ? String(numero) : null;
}

export class GarantiasPendentes {
    #itens = new Map();
    #fila = Promise.resolve(true);
    #flushAtual = null;

    constructor({ salvar, aoAtualizar = () => {}, atraso = 700, agendar = setTimeout, cancelar = clearTimeout } = {}) {
        if (typeof salvar !== 'function') throw new TypeError('A função salvar é obrigatória.');
        this.salvarRemoto = salvar;
        this.aoAtualizar = aoAtualizar;
        this.atraso = atraso;
        this.agendar = agendar.bind(globalThis);
        this.cancelar = cancelar.bind(globalThis);
    }

    registrar(id, valorInicial, meta = {}) {
        const chave = String(id);
        const valor = String(valorInicial ?? '');
        const canonico = normalizarDiasGarantia(valor);
        this.#itens.set(chave, {
            id: chave,
            valor,
            salvo: canonico,
            revisao: 0,
            valido: canonico !== null,
            salvando: false,
            erro: null,
            timer: null,
            meta,
        });
        this.#emitir();
    }

    obter(id) {
        const item = this.#itens.get(String(id));
        if (!item) return null;
        return {
            id: item.id,
            valor: item.valor,
            salvo: item.salvo,
            revisao: item.revisao,
            sujo: this.#sujo(item),
            salvando: item.salvando,
            valido: item.valido,
            erro: item.erro,
            meta: item.meta,
        };
    }

    atualizarMeta(id, meta) {
        const item = this.#itens.get(String(id));
        if (item) item.meta = meta;
    }

    sincronizarServidor(id, valor, meta = {}) {
        const item = this.#itens.get(String(id));
        if (!item || item.revisao !== 0 || item.salvando || item.erro !== null || this.#sujo(item)) return false;
        const texto = String(valor ?? '');
        const canonico = normalizarDiasGarantia(texto);
        const mudou = item.valor !== texto || item.salvo !== canonico || item.valido !== (canonico !== null);
        item.valor = texto;
        item.salvo = canonico;
        item.valido = canonico !== null;
        item.meta = meta;
        if (mudou) this.#emitir();
        return mudou;
    }

    remover(id) {
        const chave = String(id);
        const item = this.#itens.get(chave);
        if (!item) return;
        if (item.timer !== null) this.cancelar(item.timer);
        this.#itens.delete(chave);
        this.#emitir();
    }

    removerAusentes(ids) {
        const manter = new Set([...ids].map(String));
        for (const id of this.#itens.keys()) {
            if (!manter.has(id)) this.remover(id);
        }
    }

    alterar(id, valor) {
        const item = this.#itens.get(String(id));
        if (!item) return;

        const texto = String(valor ?? '');
        if (item.valor === texto) return;
        item.valor = texto;
        item.revisao += 1;
        item.valido = normalizarDiasGarantia(texto) !== null;
        item.erro = null;
        if (item.timer !== null) this.cancelar(item.timer);
        item.timer = null;

        if (item.valido && this.#sujo(item)) {
            item.timer = this.agendar(() => {
                item.timer = null;
                void this.#enfileirar(item.id);
            }, this.atraso);
        }
        this.#emitir();
    }

    async salvarAgora(id) {
        const item = this.#itens.get(String(id));
        if (item?.timer !== null && item?.timer !== undefined) {
            this.cancelar(item.timer);
            item.timer = null;
        }
        return this.#enfileirar(String(id));
    }

    temPendentes() {
        return [...this.#itens.values()].some((item) => this.#sujo(item) || item.salvando || item.erro !== null);
    }

    async flush() {
        if (this.#flushAtual) return this.#flushAtual;
        this.#flushAtual = this.#executarFlush().finally(() => {
            this.#flushAtual = null;
        });
        return this.#flushAtual;
    }

    snapshot() {
        const itens = [...this.#itens.values()].map((item) => ({
            id: item.id,
            nome: item.meta.nome ?? `Item #${item.id}`,
            valor: item.valor,
            salvo: item.salvo,
            sujo: this.#sujo(item),
            salvando: item.salvando,
            valido: item.valido,
            erro: item.erro,
            meta: item.meta,
        }));
        const salvando = itens.some((item) => item.salvando);
        const erros = itens.filter((item) => item.erro !== null);
        const pendentes = itens.some((item) => item.sujo);
        const estado = salvando ? 'salvando' : erros.length > 0 ? 'erro' : pendentes ? 'pendente' : 'salvo';
        return { estado, itens, erros, pendentes: itens.filter((item) => item.sujo).length };
    }

    async #executarFlush() {
        for (const item of this.#itens.values()) {
            if (item.timer !== null) this.cancelar(item.timer);
            item.timer = null;
        }

        while (true) {
            const pendentes = [...this.#itens.values()].filter((item) => this.#sujo(item));
            if (pendentes.length === 0) {
                this.#emitir();
                return ![...this.#itens.values()].some((item) => item.erro !== null);
            }
            if (pendentes.some((item) => !item.valido)) {
                for (const item of pendentes) {
                    if (!item.valido) item.erro = 'Informe um número inteiro não negativo.';
                }
                this.#emitir();
                return false;
            }

            for (const item of pendentes) {
                const sucesso = await this.#enfileirar(item.id);
                if (!sucesso) return false;
            }
        }
    }

    #enfileirar(id) {
        const operacao = this.#fila.then(() => this.#salvarUm(id));
        this.#fila = operacao.catch(() => false);
        return operacao;
    }

    async #salvarUm(id) {
        const item = this.#itens.get(String(id));
        if (!item || !this.#sujo(item)) return true;

        const valor = normalizarDiasGarantia(item.valor);
        if (valor === null) {
            item.valido = false;
            item.erro = 'Informe um número inteiro não negativo.';
            this.#emitir();
            return false;
        }

        const revisao = item.revisao;
        const meta = item.meta;
        item.salvando = true;
        item.erro = null;
        this.#emitir();

        try {
            const resposta = await this.salvarRemoto(item.id, valor, meta);
            if (resposta === false || resposta?.result === false) {
                throw new Error(resposta?.message || 'O servidor não confirmou o salvamento.');
            }
            item.salvo = valor;
            if (item.revisao === revisao) item.erro = null;
            return true;
        } catch (erro) {
            // Mesmo que a resposta pertença a uma revisão anterior, ela não
            // confirmou a gravação. A revisão atual continua intacta e marcada
            // com erro até a próxima tentativa explícita/automática.
            item.erro = erro?.message || 'Não foi possível salvar. Tente novamente.';
            return false;
        } finally {
            item.salvando = false;
            this.#emitir();
        }
    }

    #sujo(item) {
        const atual = item.valido ? normalizarDiasGarantia(item.valor) : null;
        return atual === null || atual !== item.salvo;
    }

    #emitir() {
        this.aoAtualizar(this.snapshot());
    }
}
