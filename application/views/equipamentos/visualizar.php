<?php
/** @var object $equipamento @var list<object> $historico @var bool $pode_editar */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$pathUrl = static function (string $caminho): string {
    $path = parse_url(site_url($caminho), PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/';
};
$identificacao = [
    'Tipo' => $equipamento->equipamento,
    'Modelo' => $equipamento->modelo,
    'Número de série' => $equipamento->num_serie,
    'Marca' => $equipamento->marca_nome,
    'Cor' => $equipamento->cor,
    'Descrição' => $equipamento->descricao,
];
$tecnico = [
    'Tensão' => $equipamento->tensao,
    'Potência' => $equipamento->potencia,
    'Voltagem' => $equipamento->voltagem,
    'Fabricação' => $equipamento->data_fabricacao ? dataBr($equipamento->data_fabricacao) : null,
];
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="font-display text-heading-xl text-text"><?= e($equipamento->equipamento) ?></h1>
            <p class="text-caption text-muted">Equipamento #<?= e((string) $equipamento->idEquipamentos) ?><?= $equipamento->modelo ? ' · ' . e($equipamento->modelo) : '' ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?= component('button', ['label' => 'Etiqueta 40x25', 'icon' => 'barcode', 'variant' => 'outline', 'href' => $pathUrl('equipamentos/imprimirEtiqueta/' . (int) $equipamento->idEquipamentos), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]) ?>
            <?php if ($pode_editar) { ?>
                <?= component('button', ['label' => 'Editar equipamento', 'icon' => 'pencil', 'variant' => 'outline', 'href' => $pathUrl('equipamentos/editar/' . (int) $equipamento->idEquipamentos)]) ?>
            <?php } ?>
        </div>
    </header>
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="<?= e($caixa) ?> lg:col-span-2" aria-labelledby="dados-equipamento">
            <h2 id="dados-equipamento" class="text-heading-sm text-text">Dados do equipamento</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <?php foreach ($identificacao as $rotulo => $valor) { ?>
                    <div>
                        <dt class="text-caption text-muted"><?= e($rotulo) ?></dt>
                        <dd class="text-body-md [overflow-wrap:anywhere] <?= $valor ? 'text-text' : 'text-muted' ?>"><?= e($valor ?: 'Não informado') ?></dd>
                    </div>
                <?php } ?>
            </dl>
        </section>
        <section class="<?= e($caixa) ?>" aria-labelledby="cliente-equipamento">
            <h2 id="cliente-equipamento" class="text-heading-sm text-text">Cliente</h2>
            <?php if ($equipamento->nomeCliente) { ?>
                <p class="mt-3 text-body-md"><?= component('link', ['label' => nomeClienteExibicao($equipamento->nomeCliente), 'href' => $pathUrl('clientes/visualizar/' . (int) $equipamento->cliente_id)]) ?></p>
            <?php } else { ?>
                <p class="mt-3 text-body-md text-muted">Cliente removido ou não informado.</p>
            <?php } ?>
            <p class="mt-2 text-caption text-muted"><?= number_format((int) $equipamento->total_os, 0, ',', '.') ?> OS vinculada(s)</p>
        </section>
    </div>
    <section class="<?= e($caixa) ?>" aria-labelledby="especificacoes">
        <h2 id="especificacoes" class="text-heading-sm text-text">Especificações técnicas</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <?php foreach ($tecnico as $rotulo => $valor) { ?>
                <div>
                    <dt class="text-caption text-muted"><?= e($rotulo) ?></dt>
                    <dd class="text-body-md <?= $valor ? 'text-text' : 'text-muted' ?>"><?= e($valor ?: 'Não informado') ?></dd>
                </div>
            <?php } ?>
        </dl>
    </section>
    <section class="<?= e($caixa) ?>" aria-labelledby="historico-equipamento">
        <h2 id="historico-equipamento" class="text-heading-sm text-text">Histórico de OS</h2>
        <?php if ($historico === []) { ?>
            <p class="mt-3 text-body-md text-muted">Nenhuma OS vinculada a este número de série.</p>
        <?php } else { ?>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[42rem] text-left text-body-sm">
                    <thead class="border-b border-border text-label-md text-muted"><tr><th class="px-3 py-2">OS</th><th class="px-3 py-2">Cliente</th><th class="px-3 py-2">Data</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Garantia</th></tr></thead>
                    <tbody class="divide-y divide-border">
                    <?php foreach ($historico as $item) { ?>
                        <tr>
                            <td class="px-3 py-2"><?= component('link', ['label' => '#' . (string) $item->idOs, 'href' => $pathUrl('os/visualizar/' . (int) $item->idOs)]) ?></td>
                            <td class="px-3 py-2"><?= e($item->cliente ?: 'Cliente removido') ?></td>
                            <td class="px-3 py-2 whitespace-nowrap"><?= e(dataBr($item->dataFinal)) ?></td>
                            <td class="px-3 py-2"><?= component('pill-status', osStatusPill($item->status)) ?></td>
                            <td class="px-3 py-2"><?= e($item->situacao_garantia . ($item->vencimento_garantia ? ' · até ' . $item->vencimento_garantia : '')) ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </section>
</div>
