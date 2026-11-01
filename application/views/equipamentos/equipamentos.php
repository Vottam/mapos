<?php
/** @var list<object> $results @var array<string,string> $filtros @var int $total @var array $pode @var array $paginacao */
$temFiltro = ($filtros['pesquisa'] ?? '') !== '';
$pathUrl = static function (string $caminho): string {
    $path = parse_url(site_url($caminho), PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/';
};
$colunas = [
    ['key' => 'idEquipamentos', 'label' => 'Nº', 'align' => 'right', 'nowrap' => true],
    ['label' => 'Tipo', 'class' => 'min-w-32', 'render' => static function ($item) use ($pathUrl) {
        $tipo = trim((string) ($item->equipamento ?? ''));
        if ($tipo === '') {
            return '';
        }

        return component('link', ['label' => $tipo, 'href' => $pathUrl('equipamentos/visualizar/' . (int) $item->idEquipamentos), 'class' => 'font-medium']);
    }],
    ['label' => 'Modelo', 'class' => 'min-w-32', 'render' => static fn ($item) => (string) ($item->modelo ?? '')],
    ['label' => 'Número de série', 'class' => 'max-w-48', 'render' => static fn ($item) => $item->num_serie ?: 'Não informado'],
    ['label' => 'Marca', 'hide_until' => 'lg', 'render' => static fn ($item) => $item->marca_nome ?: 'Não informada'],
    ['label' => 'Cliente', 'class' => 'min-w-48', 'render' => static function ($item) use ($pathUrl) {
        if (! $item->nomeCliente) {
            return 'Cliente removido';
        }
        return component('link', ['label' => nomeClienteExibicao($item->nomeCliente), 'href' => $pathUrl('clientes/visualizar/' . (int) $item->cliente_id), 'class' => 'font-medium']);
    }],
    ['label' => 'OS vinculadas', 'align' => 'right', 'nowrap' => true, 'render' => static fn ($item) => number_format((int) $item->total_os, 0, ',', '.')],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => static function ($item) use ($pode, $pathUrl) {
        $acoes = [component('button', ['label' => 'Ver equipamento', 'icon' => 'eye', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => $pathUrl('equipamentos/visualizar/' . (int) $item->idEquipamentos)])];
        if ($pode['editar']) {
            $acoes[] = component('button', ['label' => 'Editar equipamento', 'icon' => 'pencil', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => $pathUrl('equipamentos/editar/' . (int) $item->idEquipamentos)]);
        }
        return $acoes;
    }],
];
$vazio = $temFiltro
    ? component('empty-state', ['title' => 'Nenhum equipamento encontrado', 'message' => 'Nada corresponde à busca. Confira o texto ou limpe o filtro.', 'icon' => 'search-x', 'action' => component('button', ['label' => 'Limpar filtro', 'variant' => 'outline', 'href' => $pathUrl('equipamentos')]), 'class' => 'border-0'])
    : component('empty-state', ['title' => 'Nenhum equipamento cadastrado', 'message' => 'Os equipamentos cadastrados aparecem aqui.', 'icon' => 'monitor', 'action' => $pode['adicionar'] ? component('button', ['label' => 'Cadastrar equipamento', 'icon' => 'plus', 'variant' => 'outline', 'href' => $pathUrl('equipamentos/adicionar')]) : null, 'class' => 'border-0']);
$resumo = number_format($total, 0, ',', '.') . ($total === 1 ? ' equipamento' : ' equipamentos') . ($temFiltro ? ' encontrados' : '');
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text">Equipamentos</h1>
        <p class="text-caption text-muted" aria-live="polite"><?= e($resumo) ?></p>
    </header>
    <form method="get" action="<?= e($pathUrl('equipamentos')) ?>" role="search" aria-label="Buscar equipamentos" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_auto]">
        <?= component('input', ['name' => 'pesquisa', 'label' => 'Buscar', 'type' => 'search', 'value' => $filtros['pesquisa'] ?? null, 'placeholder' => 'Tipo, série, modelo, marca ou cliente', 'attrs' => ['maxlength' => 100]]) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Buscar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if ($temFiltro) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => $pathUrl('equipamentos')]) ?>
            <?php } ?>
        </div>
    </form>
    <?= component('data-table', ['columns' => $colunas, 'rows' => $results, 'empty' => $vazio, 'caption' => 'Equipamentos']) ?>
    <?= component('pagination', $paginacao) ?>
</div>
