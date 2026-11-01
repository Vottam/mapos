<?php
/** @var object|null $equipamento @var array<string,string> $valores @var array<string,string> $erros @var list<object> $marcas */
$editando = $equipamento !== null;
$erroGeral = $erros['_geral'] ?? null;
$errosDeCampo = array_diff_key($erros, ['_geral' => true]);
$campo = static fn (string $nome, array $props) => $props + [
    'name' => $nome,
    'value' => ($valores[$nome] ?? '') !== '' ? $valores[$nome] : null,
    'error' => $errosDeCampo[$nome] ?? null,
];
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$grade = 'mt-4 grid gap-4 sm:grid-cols-2';
$pathUrl = static function (string $caminho): string {
    $path = parse_url(site_url($caminho), PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/';
};
$formAction = (string) (parse_url(current_url(), PHP_URL_PATH) ?: '/');
?>
<div class="flex max-w-5xl flex-col gap-4 pt-2 pb-8" <?= js_module('equipamentos/formulario') ?>>
    <header>
        <h1 class="font-display text-heading-xl text-text"><?= e($editando ? 'Editar equipamento' : 'Novo equipamento') ?></h1>
        <p class="text-caption text-muted"><?= e($editando ? 'Atualize os dados sem alterar automaticamente o histórico das OS.' : 'Cadastre o equipamento e associe-o a um cliente.') ?></p>
    </header>
    <?php if ($erroGeral !== null) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $erroGeral]) ?>
    <?php } elseif ($errosDeCampo !== []) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => count($errosDeCampo) === 1 ? 'Confira o campo destacado.' : 'Confira os campos destacados.']) ?>
    <?php } ?>
    <form method="post" action="<?= e($formAction) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <section class="<?= e($caixa) ?>" aria-labelledby="secao-identificacao">
            <h2 id="secao-identificacao" class="text-heading-sm text-text">Identificação</h2>
            <div class="<?= e($grade) ?>">
                <?= component('input', $campo('equipamento', ['label' => 'Tipo de equipamento', 'required' => true, 'attrs' => ['maxlength' => 150, 'autofocus' => ! $editando, 'data-msg-vazio' => 'Informe o tipo de equipamento.']])) ?>
                <?= component('input', $campo('modelo', ['label' => 'Modelo', 'attrs' => ['maxlength' => 80]])) ?>
                <?= component('input', $campo('num_serie', ['label' => 'Número de série', 'attrs' => ['maxlength' => 80]])) ?>
                <div>
                    <?= component('input', $campo('marca', ['label' => 'Marca', 'attrs' => ['maxlength' => 100, 'list' => 'marcas-equipamentos']])) ?>
                    <datalist id="marcas-equipamentos">
                        <?php foreach ($marcas as $marca) { ?><option value="<?= e($marca->marca) ?>"><?php } ?>
                    </datalist>
                </div>
                <?= component('input', $campo('cor', ['label' => 'Cor', 'attrs' => ['maxlength' => 45]])) ?>
                <?= component('input', $campo('descricao', ['label' => 'Descrição', 'attrs' => ['maxlength' => 150]])) ?>
            </div>
        </section>
        <section class="<?= e($caixa) ?>" aria-labelledby="secao-tecnico">
            <h2 id="secao-tecnico" class="text-heading-sm text-text">Especificações técnicas</h2>
            <div class="<?= e($grade) ?>">
                <?= component('input', $campo('tensao', ['label' => 'Tensão', 'attrs' => ['maxlength' => 45]])) ?>
                <?= component('input', $campo('potencia', ['label' => 'Potência', 'attrs' => ['maxlength' => 45]])) ?>
                <?= component('input', $campo('voltagem', ['label' => 'Voltagem', 'attrs' => ['maxlength' => 45]])) ?>
                <?= component('input', $campo('data_fabricacao', ['label' => 'Data de fabricação', 'type' => 'date'])) ?>
            </div>
        </section>
        <section class="<?= e($caixa) ?>" aria-labelledby="secao-cliente">
            <h2 id="secao-cliente" class="text-heading-sm text-text">Cliente</h2>
            <div class="mt-4 relative" <?= componenteAtributos(['data-autocomplete' => $pathUrl('os/autoCompleteCliente'), 'data-autocomplete-alvo' => 'equipamento-cliente-id']) ?>>
                <?= component('input', ['name' => 'cliente', 'id' => 'equipamento-cliente', 'label' => 'Cliente', 'value' => $valores['cliente'] !== '' ? $valores['cliente'] : null, 'required' => true, 'autocomplete' => 'off', 'error' => $errosDeCampo['cliente'] ?? null, 'attrs' => ['data-msg-vazio' => 'Escolha um cliente da lista.', 'maxlength' => 255]]) ?>
                <input type="hidden" id="equipamento-cliente-id" name="clientes_id" value="<?= e($valores['clientes_id']) ?>">
            </div>
            <p class="mt-2 text-caption text-muted">O cliente é validado no servidor. A troca é bloqueada quando houver OS de outro cliente vinculada.</p>
        </section>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => $pathUrl('equipamentos')]) ?>
            <?= component('button', ['label' => $editando ? 'Salvar alterações' : 'Cadastrar equipamento', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Salvando…']]) ?>
        </div>
    </form>
</div>
