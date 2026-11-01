<?php
/** @var object|null $result */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$errosCustos = $errosCustos ?? [];
$valor = $result ? number_format((float) $result->valor, 2, ',', '.') : '';
$campo = static fn (string $nome, string $padrao = '') => $result && isset($result->{$nome}) ? (string) $result->{$nome} : $padrao;
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text"><?= e($button_text === 'Cadastrar' ? 'Novo custo fixo' : 'Editar custo fixo') ?></h1>
        <p class="text-caption text-muted">Os dados são validados no servidor. Competências existentes não são recriadas ao abrir esta tela.</p>
    </header>

    <?php if (isset($errosCustos['_geral'])) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => $errosCustos['_geral']]) ?>
    <?php } ?>

    <form method="post" action="<?= e(site_url($action)) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <section class="<?= e($caixa) ?>" aria-labelledby="dados-custo-fixo">
            <h2 id="dados-custo-fixo" class="text-heading-sm text-text">Dados do custo fixo</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?= component('input', ['name' => 'titulo', 'label' => 'Título', 'value' => $campo('titulo'), 'required' => true, 'error' => $errosCustos['titulo'] ?? null, 'attrs' => ['maxlength' => 150]]) ?>
                <?= component('input', ['name' => 'categoria', 'label' => 'Categoria', 'value' => $campo('categoria'), 'required' => true, 'error' => $errosCustos['categoria'] ?? null, 'attrs' => ['maxlength' => 100]]) ?>
                <div>
                    <?= component('input', ['name' => 'valor', 'label' => 'Valor padrão', 'value' => $valor, 'required' => true, 'error' => $errosCustos['valor'] ?? null, 'attrs' => ['inputmode' => 'decimal', 'placeholder' => '0,00']]) ?>
                    <p class="mt-1 text-caption text-muted">Valor sugerido para novas competências. Não altera meses já existentes.</p>
                </div>
                <?= component('select', ['name' => 'periodicidade', 'label' => 'Periodicidade', 'options' => ['mensal' => 'Mensal', 'unico' => 'Único'], 'selected' => $campo('periodicidade', 'mensal'), 'error' => $errosCustos['periodicidade'] ?? null]) ?>
                <?= component('input', ['name' => 'dia_vencimento', 'label' => 'Dia de vencimento', 'type' => 'number', 'value' => $campo('dia_vencimento', '1'), 'required' => true, 'error' => $errosCustos['dia_vencimento'] ?? null, 'attrs' => ['min' => 1, 'max' => 31]]) ?>
                <div>
                    <?= component('select', ['name' => 'regra_vencimento', 'label' => 'Regra de vencimento', 'options' => ['mes_vencido' => 'Mês vencido', 'mes_corrente' => 'Pagamento antecipado / mês corrente'], 'selected' => $campo('regra_vencimento', 'mes_vencido'), 'error' => $errosCustos['regra_vencimento'] ?? null]) ?>
                    <p class="mt-1 text-caption text-muted">Mês vencido: competência 06/2026 vence em 07/2026. Antecipado: vence no próprio mês.</p>
                </div>
                <?= component('input', ['name' => 'forma_pagamento', 'label' => 'Forma de pagamento', 'value' => $campo('forma_pagamento'), 'help' => 'Opcional.', 'error' => $errosCustos['forma_pagamento'] ?? null, 'attrs' => ['maxlength' => 80]]) ?>
                <?= component('select', ['name' => 'ativo', 'label' => 'Status', 'options' => ['1' => 'Ativo', '0' => 'Inativo'], 'selected' => $campo('ativo', '1'), 'error' => $errosCustos['ativo'] ?? null]) ?>
                <?= component('input', ['name' => 'data_inicio', 'label' => 'Data inicial', 'type' => 'date', 'value' => $campo('data_inicio'), 'error' => $errosCustos['data_inicio'] ?? null]) ?>
                <?= component('input', ['name' => 'data_fim', 'label' => 'Data final', 'type' => 'date', 'value' => $campo('data_fim'), 'error' => $errosCustos['data_fim'] ?? null]) ?>
                <div class="sm:col-span-2">
                    <?= component('textarea', ['name' => 'observacoes', 'label' => 'Observações', 'value' => $campo('observacoes'), 'rows' => 3, 'attrs' => ['maxlength' => 1000]]) ?>
                </div>
            </div>
        </section>
        <div class="flex flex-wrap gap-2">
            <?= component('button', ['label' => $button_text, 'icon' => 'check', 'type' => 'submit']) ?>
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('financeiro/custosFixos')]) ?>
        </div>
    </form>
</div>