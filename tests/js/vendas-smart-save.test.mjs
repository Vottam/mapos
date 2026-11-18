import test from 'node:test';
import assert from 'node:assert/strict';
import { GarantiasPendentes, normalizarDiasGarantia } from '../../assets/js/modules/vendas/garantias-pendentes.js';

const aguardar = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const adiar = () => {
    let resolve;
    const promise = new Promise((r) => { resolve = r; });
    return { promise, resolve };
};

test('usa o receptor global ao agendar e cancelar timers em browser', async () => {
    const receptores = [];
    const estado = new GarantiasPendentes({
        salvar: async () => ({ result: true }),
        atraso: 1000,
        agendar: function(callback, atraso) {
            receptores.push(this);
            return globalThis.setTimeout(callback, atraso);
        },
        cancelar: function(timer) {
            receptores.push(this);
            globalThis.clearTimeout(timer);
        },
    });
    estado.registrar('timers', '90');
    estado.alterar('timers', '120');
    estado.alterar('timers', '180');
    assert.equal(await estado.flush(), true);
    assert.ok(receptores.length >= 2);
    assert.ok(receptores.every((receptor) => receptor === globalThis));
});
test('normaliza zero e inteiros não negativos sem converter zero em valor ausente', () => {
    assert.equal(normalizarDiasGarantia('0'), '0');
    assert.equal(normalizarDiasGarantia('030'), '30');
    assert.equal(normalizarDiasGarantia('365'), '365');
    assert.equal(normalizarDiasGarantia('-1'), null);
    assert.equal(normalizarDiasGarantia('1.5'), null);
    assert.equal(normalizarDiasGarantia(''), null);
    assert.equal(normalizarDiasGarantia('4294967296'), null);
});

test('debounce salva somente após a pausa e não envia por tecla', async () => {
    const chamadas = [];
    const estado = new GarantiasPendentes({ salvar: async (...args) => chamadas.push(args), atraso: 35 });
    estado.registrar('11', '90', { nome: 'Memória RAM' });
    estado.alterar('11', '1');
    estado.alterar('11', '12');
    estado.alterar('11', '120');
    await aguardar(15);
    assert.equal(chamadas.length, 0);
    await aguardar(40);
    assert.deepEqual(chamadas.map(([, dias]) => dias), ['120']);
    assert.equal(estado.snapshot().estado, 'salvo');
});

test('blur/flush grava zero imediatamente e os itens são serializados', async () => {
    const chamadas = [];
    let emAndamento = 0;
    let maximo = 0;
    const estado = new GarantiasPendentes({
        atraso: 1000,
        salvar: async (id, dias) => {
            emAndamento += 1;
            maximo = Math.max(maximo, emAndamento);
            chamadas.push([id, dias]);
            await aguardar(5);
            emAndamento -= 1;
            return { result: true };
        },
    });
    estado.registrar('11', '90');
    estado.registrar('12', '365');
    estado.alterar('11', '0');
    assert.equal(await estado.salvarAgora('11'), true);
    assert.deepEqual(chamadas, [['11', '0']]);
    estado.alterar('12', '30');
    assert.equal(await estado.flush(), true);
    assert.deepEqual(chamadas, [['11', '0'], ['12', '30']]);
    assert.equal(maximo, 1);
    assert.equal(estado.temPendentes(), false);
});

test('flush espera resposta antiga e persiste o valor mais recente sem falso salvo', async () => {
    const primeira = adiar();
    const chamadas = [];
    const estados = [];
    const estado = new GarantiasPendentes({
        atraso: 5,
        aoAtualizar: (s) => estados.push(s.estado),
        salvar: async (id, dias) => {
            chamadas.push([id, dias]);
            if (chamadas.length === 1) return primeira.promise;
            return { result: true };
        },
    });
    estado.registrar('17', '90');
    estado.alterar('17', '120');
    await aguardar(15);
    assert.deepEqual(chamadas, [['17', '120']]);
    estado.alterar('17', '365');
    const flush = estado.flush();
    primeira.resolve({ result: true });
    assert.equal(await flush, true);
    assert.deepEqual(chamadas, [['17', '120'], ['17', '365']]);
    assert.equal(estado.snapshot().itens[0].salvo, '365');
    assert.equal(estado.snapshot().estado, 'salvo');
    assert.ok(estados.includes('salvando'));
});

test('403, 500 e falha de rede deixam alteração pendente; nova tentativa recupera', async () => {
    for (const mensagem of ['HTTP 403', 'HTTP 500', 'Sem conexão']) {
        let tentativas = 0;
        const estado = new GarantiasPendentes({
            atraso: 1000,
            salvar: async () => {
                tentativas += 1;
                if (tentativas === 1) throw new Error(mensagem);
                return { result: true };
            },
        });
        estado.registrar(mensagem, '90');
        estado.alterar(mensagem, '180');
        assert.equal(await estado.flush(), false);
        assert.equal(estado.snapshot().estado, 'erro');
        assert.equal(estado.temPendentes(), true);
        assert.equal(estado.snapshot().itens[0].valor, '180');
        assert.equal(await estado.flush(), true);
        assert.equal(tentativas, 2);
        assert.equal(estado.snapshot().itens[0].salvo, '180');
        assert.equal(estado.snapshot().estado, 'salvo');
    }
});

test('prazo inválido bloqueia flush sem requisição', async () => {
    let chamadas = 0;
    const estado = new GarantiasPendentes({ salvar: async () => { chamadas += 1; } });
    estado.registrar('31', '90');
    estado.alterar('31', '-2');
    assert.equal(estado.snapshot().estado, 'pendente');
    assert.equal(estado.obter('31').erro, null);
    assert.equal(await estado.flush(), false);
    assert.equal(chamadas, 0);
    assert.equal(estado.snapshot().estado, 'erro');
    assert.equal(estado.snapshot().itens[0].valor, '-2');
});

test('reconcilia novo estado do servidor antes da edição e preserva revisão local', () => {
    const estado = new GarantiasPendentes({ salvar: async () => ({ result: true }), atraso: 10000 });
    estado.registrar('45', '90');
    assert.equal(estado.sincronizarServidor('45', '120'), true);
    assert.equal(estado.obter('45').valor, '120');
    assert.equal(estado.obter('45').salvo, '120');
    estado.alterar('45', '365');
    assert.equal(estado.sincronizarServidor('45', '30'), false);
    assert.equal(estado.obter('45').valor, '365');
    assert.equal(estado.obter('45').sujo, true);
    estado.remover('45');
});

test('não repete uma requisição quando o mesmo valor já foi confirmado', async () => {
    const chamadas = [];
    const estado = new GarantiasPendentes({
        salvar: async (id, dias) => { chamadas.push([id, dias]); return { result: true }; },
        atraso: 10000,
    });
    estado.registrar('46', '90');
    estado.alterar('46', '120');
    assert.equal(await estado.flush(), true);
    estado.alterar('46', '120');
    assert.equal(await estado.flush(), true);
    assert.deepEqual(chamadas, [['46', '120']]);
});
