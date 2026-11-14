import test from 'node:test';
import assert from 'node:assert/strict';
import { urlDaAgenda } from '../../assets/js/modules/painel/painel.js';

test('agenda preserva caminho same-origin quando o view normaliza a URL', async () => {
    const url = urlDaAgenda(
        '/index.php/mapos/calendario',
        new Date(2026, 9, 1),
        new Date(2026, 10, 1),
        'Aberto',
    );
    assert.equal(url, '/index.php/mapos/calendario?start=2026-10-01&end=2026-11-01&status=Aberto');
    const fs = await import('node:fs/promises');
    const view = await fs.readFile(new URL('../../application/views/mapos/painel.php', import.meta.url), 'utf8');
    assert.match(view, /parse_url\(site_url\('mapos\/calendario'\), PHP_URL_PATH\)/);
});
