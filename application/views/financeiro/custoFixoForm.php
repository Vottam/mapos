<?php
$result = $result ?? null;
$action = $action ?? 'financeiro/adicionarCustoFixo';
$button_text = $button_text ?? 'Salvar';
?>
<div class="new122">
    <div class="widget-title" style="margin:-15px -10px 0">
        <h5><?= !empty($result) ? 'Editar Custo Fixo' : 'Novo Custo Fixo' ?></h5>
    </div>

    <?= $custom_error ?? '' ?>

    <form action="<?= site_url($action) ?>" method="post" class="form-horizontal">
        <?php if (!empty($result)): ?>
            <input type="hidden" name="idCustoFixo" value="<?= (int) $result->idCustoFixo ?>">
        <?php endif; ?>
        <div class="widget-box">
            <div class="widget-content nopadding" style="padding: 20px;">
                <div class="row-fluid">
                    <div class="span6">
                        <label>Título</label>
                        <input type="text" name="titulo" class="span12" value="<?= set_value('titulo', $result->titulo ?? '') ?>" maxlength="150" required>
                    </div>
                    <div class="span6">
                        <label>Categoria</label>
                        <input type="text" name="categoria" class="span12" value="<?= set_value('categoria', $result->categoria ?? '') ?>" maxlength="100" required placeholder="Ex.: aluguel, contas, serviços">
                    </div>
                </div>
                <div class="row-fluid" style="margin-top:15px;">
                    <div class="span3">
                        <label>Valor padrão</label>
                        <input type="text" name="valor" class="span12" value="<?= set_value('valor', isset($result->valor) ? number_format((float) $result->valor, 2, ',', '.') : '') ?>" required placeholder="0,00">
                        <small style="color:#888">Valor sugerido para novas competências. Não altera meses já existentes.</small>
                    </div>
                    <div class="span3">
                        <label>Periodicidade</label>
                        <select name="periodicidade" class="span12">
                            <option value="mensal" <?= set_value('periodicidade', $result->periodicidade ?? 'mensal') === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                            <option value="unico" <?= set_value('periodicidade', $result->periodicidade ?? '') === 'unico' ? 'selected' : '' ?>>Único</option>
                        </select>
                    </div>
                    <div class="span2">
                        <label>Dia de vencimento</label>
                        <input type="number" name="dia_vencimento" class="span12" min="1" max="31" value="<?= set_value('dia_vencimento', $result->dia_vencimento ?? 1) ?>" required>
                    </div>
                    <div class="span2">
                        <label>Regra de vencimento</label>
                        <select name="regra_vencimento" class="span12" required>
                            <option value="mes_vencido" <?= set_value('regra_vencimento', $result->regra_vencimento ?? 'mes_vencido') === 'mes_vencido' ? 'selected' : '' ?>>Mês vencido</option>
                            <option value="mes_corrente" <?= set_value('regra_vencimento', $result->regra_vencimento ?? '') === 'mes_corrente' ? 'selected' : '' ?>>Pagamento antecipado / mês corrente</option>
                        </select>
                        <small style="color:#888">Mês vencido: competência 06/2026 vence em 07/2026. Antecipado: vence no próprio mês.</small>
                    </div>
                    <div class="span2">
                        <label>Forma de pagamento</label>
                        <input type="text" name="forma_pagamento" class="span12" value="<?= set_value('forma_pagamento', $result->forma_pagamento ?? '') ?>" maxlength="80" required placeholder="Boleto, PIX, débito...">
                    </div>
                </div>
                <div class="row-fluid" style="margin-top:15px;">
                    <div class="span3">
                        <label>Status</label>
                        <select name="ativo" class="span12" required>
                            <option value="1" <?= set_value('ativo', (string) ($result->ativo ?? '1')) === '1' ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= set_value('ativo', (string) ($result->ativo ?? '1')) === '0' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    <div class="span3">
                        <label>Data início</label>
                        <input type="text" name="data_inicio" class="span12 datepicker" value="<?= set_value('data_inicio', !empty($result->data_inicio) ? date('d/m/Y', strtotime($result->data_inicio)) : '') ?>" placeholder="dd/mm/aaaa">
                    </div>
                    <div class="span3">
                        <label>Data fim</label>
                        <input type="text" name="data_fim" class="span12 datepicker" value="<?= set_value('data_fim', !empty($result->data_fim) ? date('d/m/Y', strtotime($result->data_fim)) : '') ?>" placeholder="dd/mm/aaaa">
                    </div>
                    <div class="span3">
                        <label>Observações</label>
                        <input type="text" name="observacoes" class="span12" value="<?= set_value('observacoes', $result->observacoes ?? '') ?>" maxlength="255" placeholder="Opcional">
                    </div>
                </div>
                <div class="form-actions" style="margin-top:20px;">
                    <a href="<?= site_url('financeiro/custosFixos') ?>" class="button btn btn-warning"><span class="button__icon"><i class='bx bx-arrow-back'></i></span><span class="button__text2">Voltar</span></a>
                    <button type="submit" class="button btn btn-success"><span class="button__icon"><i class='bx bx-save'></i></span><span class="button__text2"><?= html_escape($button_text) ?></span></button>
                </div>
            </div>
        </div>
    </form>
</div>
