import test from 'node:test';
import assert from 'node:assert/strict';
import {
    classificarRespostaSerial,
    normalizarSerialParaConsulta,
    respostaSerialObsoleta,
} from '../../assets/js/modules/os/formulario.js';

const equipamento39 = {
    id: 39,
    equipamento: 'Notebook',
    marca: 'Hp',
    modelo: '240',
    num_serie: 'SN000053',
};

test('normaliza serial vazio, espaços laterais, espaços repetidos e caixa', () => {
    assert.equal(normalizarSerialParaConsulta('  sn000053  '), 'SN000053');
    assert.equal(normalizarSerialParaConsulta('SN  000053'), 'SN 000053');
    assert.equal(normalizarSerialParaConsulta(''), '');
});

test('classifica ocorrência única e preserva o equipamento para autofill', () => {
    const resposta = classificarRespostaSerial({ status: 'found', found: true, equipamento: equipamento39 });
    assert.deepEqual(resposta, { status: 'found', encontrado: true });
    assert.equal(equipamento39.id, 39);
    assert.equal(equipamento39.equipamento, 'Notebook');
    assert.equal(equipamento39.marca, 'Hp');
    assert.equal(equipamento39.modelo, '240');
});

test('classifica serial inexistente e serial vazio sem inventar dados', () => {
    assert.deepEqual(classificarRespostaSerial({ status: 'not_found', equipamento: null }), { status: 'not_found', encontrado: false });
    assert.deepEqual(classificarRespostaSerial({ status: 'empty', equipamento: null }), { status: 'empty', encontrado: false });
});

test('classifica serial ambíguo e divergência de titularidade', () => {
    assert.equal(classificarRespostaSerial({ status: 'ambiguous' }).status, 'ambiguous');
    assert.equal(classificarRespostaSerial({ status: 'ownership_mismatch' }).status, 'ownership_mismatch');
    assert.equal(classificarRespostaSerial({ status: 'resposta-desconhecida' }).status, 'not_found');
});

test('exige cliente para vínculo e permite apenas autofill técnico sem cliente', () => {
    const resposta = classificarRespostaSerial({
        status: 'found',
        found: true,
        clienteNecessario: true,
        equipamento: equipamento39,
        historico: [],
    });
    assert.equal(resposta.encontrado, true);
    assert.equal(resposta.status, 'found');
});

test('mantém histórico real, garantia informativa e exclusão da OS atual no contrato', () => {
    const payload = {
        status: 'found',
        found: true,
        equipamento: equipamento39,
        total: 1,
        historico: [{ idOs: 53, garantiaStatus: 'vencida', situacaoGarantia: 'Fora de garantia' }],
    };
    assert.equal(classificarRespostaSerial(payload).encontrado, true);
    assert.equal(payload.historico[0].idOs, 53);
    assert.equal(payload.historico[0].garantiaStatus, 'vencida');
    const editado = { ...payload, historico: payload.historico.filter((item) => item.idOs !== 53) };
    assert.equal(editado.historico.some((item) => item.idOs === 53), false);
});

test('aceita estados de garantia sem marcar retorno automaticamente', () => {
    for (const garantiaStatus of ['sem_garantia_registrada', 'vigente', 'vencida', 'indeterminada']) {
        const payload = { status: 'found', found: true, equipamento: equipamento39, garantiaCritica: { status: garantiaStatus } };
        assert.equal(classificarRespostaSerial(payload).encontrado, true);
        assert.equal(Object.hasOwn(payload, 'marcarRetornoGarantia'), false);
    }
});

test('resposta atrasada é ignorada quando serial, cliente ou sequência mudam', () => {
    const base = {
        sequencia: 4,
        sequenciaAtual: 4,
        valorNormalizado: 'SN000053',
        clienteId: '6',
        clienteAtual: '6',
    };
    assert.equal(respostaSerialObsoleta({ ...base, valorAtual: 'SN000053' }), false);
    assert.equal(respostaSerialObsoleta({ ...base, sequenciaAtual: 5, valorAtual: 'SN000053' }), true);
    assert.equal(respostaSerialObsoleta({ ...base, valorAtual: 'SN000054' }), true);
    assert.equal(respostaSerialObsoleta({ ...base, clienteAtual: '7', valorAtual: 'SN000053' }), true);
});

test('consulta consecutiva usa a identidade da nova entrada, inclusive colagem', () => {
    assert.equal(normalizarSerialParaConsulta('SN000053'), 'SN000053');
    assert.equal(normalizarSerialParaConsulta(' sn000054 '), 'SN000054');
    assert.notEqual(normalizarSerialParaConsulta('SN000053'), normalizarSerialParaConsulta('SN000054'));
});

test('contrato do formulário cobre input, blur, debounce, autenticação e ID selecionado', async () => {
    const fs = await import('node:fs/promises');
    const source = await fs.readFile(new URL('../../assets/js/modules/os/formulario.js', import.meta.url), 'utf8');
    assert.match(source, /serial\?\.addEventListener\('input'/);
    assert.match(source, /serial\?\.addEventListener\('blur'/);
    assert.match(source, /window\.setTimeout\(async/);
    assert.match(source, /credentials: 'same-origin'/);
    assert.match(source, /equipamento_id/);
});

test('contrato do backend cobre busca normalizada, duplicidade, vínculo e histórico por ID', async () => {
    const fs = await import('node:fs/promises');
    const model = await fs.readFile(new URL('../../application/models/Equipamentos_model.php', import.meta.url), 'utf8');
    const controller = await fs.readFile(new URL('../../application/controllers/Equipamentos.php', import.meta.url), 'utf8');
    assert.match(model, /public function localizarPorSerial/);
    assert.match(model, /LOWER\(TRIM\(e\.num_serie\)\)/);
    assert.match(model, /Há mais de um equipamento/);
    assert.match(model, /public function getHistoricoPorEquipamento/);
    assert.match(controller, /ownership_mismatch/);
    assert.match(controller, /equipamento_id/);
});
