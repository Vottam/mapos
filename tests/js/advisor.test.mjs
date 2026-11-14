import test from 'node:test';
import assert from 'node:assert/strict';
import {
    calcularAdvisor,
    construirBasesTemporais,
    formatarCentavos,
    parseMonetario,
    parseQuantidade,
} from '../../assets/js/modules/financeiro/advisor.js';

const meses = [
    '2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05',
    '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11',
];

const radarFixture = {
    janela: { meses },
    totais: {
        os_produtivas: 4,
        contribuicao_total: 360,
        despesa_total: 2350,
        resultado_operacional: -1990,
        media_contribuicao_por_os_produtiva: 90,
    },
    serie: meses.map((mes) => mes === '2026-10'
        ? {
            mes,
            os_produtivas: 4,
            contribuicao_total: 360,
            despesa_total: 250,
            media_contribuicao_por_os_produtiva: 90,
            despesa_por_os_produtiva: 62.5,
            resultado_operacional: 110,
        }
        : {
            mes,
            os_produtivas: 0,
            contribuicao_total: 0,
            despesa_total: mes === '2025-12' ? 0 : 150,
            media_contribuicao_por_os_produtiva: null,
            despesa_por_os_produtiva: null,
            resultado_operacional: -150,
        }),
};

test('parser monetário preserva separadores pt-BR e canônicos em centavos', () => {
    assert.equal(parseMonetario('90,00'), 9000);
    assert.equal(parseMonetario('195,83'), 19583);
    assert.equal(parseMonetario('1.234,56'), 123456);
    assert.equal(parseMonetario('1234.56'), 123456);
    assert.equal(parseMonetario('0,01'), 1);
    assert.equal(parseMonetario('0'), 0);
    assert.equal(parseMonetario(1234.56), 123456);
    assert.equal(formatarCentavos(123456), 'R$ 1.234,56');
});

test('parser rejeita ambiguidade, NaN, Infinity, negativos e formatos malformados', () => {
    for (const value of ['1.234', '12,345', '1.23.4', 'R$ 90,00', '90,00abc', '', '-1,00', NaN, Infinity, null]) {
        assert.equal(parseMonetario(value), null, `valor rejeitado: ${String(value)}`);
    }
    assert.equal(parseQuantidade('-1'), null);
    assert.equal(parseQuantidade('NaN'), null);
    assert.equal(parseQuantidade('0,333', true), 0.333);
});

test('base de 12 meses usa totais coerentes e mês de outubro usa apenas outubro', () => {
    const bases = construirBasesTemporais(radarFixture);
    assert.equal(bases.periodo.os, 4);
    assert.equal(bases.periodo.contribCents, 9000);
    assert.equal(bases.periodo.despesaCents, 235000);
    assert.equal(bases.meses['2026-10'].os, 4);
    assert.equal(bases.meses['2026-10'].contribCents, 9000);
    assert.equal(bases.meses['2026-10'].despesaCents, 25000);
    assert.equal(bases.meses['2026-10'].fonte, 'série mensal de 2026-10');
    assert.equal(bases.media_mensal.os, 1 / 3);
});

test('oráculo independente concilia 12 meses: -1990, 27 OS, R$ 587,50 e despesa máxima R$ 360', () => {
    const resultado = calcularAdvisor({ os: 4, contribCents: 9000, despesaCents: 235000, metaCents: 0 });
    assert.equal(resultado.resultadoCents, -199000);
    assert.equal(resultado.metaAtingida, false);
    assert.equal(resultado.distanciaCents, 199000);
    assert.equal(resultado.alternativas.find((a) => a.id === 'volume').texto.includes('total mínimo necessário = 27 OS'), true);
    assert.equal(resultado.alternativas.find((a) => a.id === 'volume').texto.includes('adicionais = 23 OS'), true);
    assert.equal(resultado.alternativas.find((a) => a.id === 'contribuicao').texto.includes('R$ 587,50'), true);
    assert.equal(resultado.alternativas.find((a) => a.id === 'despesa').texto.includes('R$ 360,00'), true);
    assert.equal(resultado.alternativas.find((a) => a.id === 'despesa').texto.includes('R$ 1.990,00'), true);
});

test('oráculo independente concilia outubro: 4 * 90 - 250 = 110', () => {
    const resultado = calcularAdvisor({ os: 4, contribCents: 9000, despesaCents: 25000, metaCents: 0 });
    assert.equal(resultado.resultadoCents, 11000);
    assert.equal(resultado.metaAtingida, true);
    assert.match(resultado.alternativas[0].texto, /Meta atingida neste cenário/);
});

test('meta atingida não cria aumento nulo nem multiplicador inferior a 1', () => {
    const resultado = calcularAdvisor({ os: 4, contribCents: 9000, despesaCents: 19583, metaCents: 0 });
    assert.equal(resultado.resultadoCents, 16417);
    assert.equal(resultado.metaAtingida, true);
    assert.equal(resultado.alternativas.length, 1);
    assert.doesNotMatch(resultado.alternativas[0].texto, /aumentar|multiplicador/);
});

test('zero OS impede divisão por zero e conversão sem base comparável é explicitamente indisponível', () => {
    const resultado = calcularAdvisor({ os: 0, contribCents: 9000, despesaCents: 10000, metaCents: 0 });
    assert.equal(resultado.resultadoCents, -10000);
    assert.match(resultado.alternativas.find((a) => a.id === 'contribuicao').texto, /quantidade de OS igual a zero/);
    assert.match(resultado.alternativas.find((a) => a.id === 'conversao').texto, /Não aplicável/);
});

test('média móvel ponderada usa soma de contribuições / soma de OS e sem base é null', async () => {
    const janela = [
        { contribuicao: 18000, os: 2 },
        { contribuicao: 9000, os: 1 },
        { contribuicao: 0, os: 0 },
    ];
    const somaContrib = janela.reduce((sum, item) => sum + item.contribuicao, 0);
    const somaOs = janela.reduce((sum, item) => sum + item.os, 0);
    assert.equal(Math.round(somaContrib / somaOs), 9000);
    const fs = await import('node:fs/promises');
    const model = await fs.readFile(new URL('../../application/models/Financeiro_model.php', import.meta.url), 'utf8');
    assert.match(model, /media_movel_3m.*=> null|media_movel_3m.*: null/s);
    assert.match(model, /\$somaOs > 0/);
});
