<?php
$selectedCategoria = $this->input->get('categoria');
$selectedStatus = $this->input->get('status');
?>
<style>
    .badge-ativo {
        background: #00cd00;
        color: #fff;
    }
    .badge-inativo {
        background: #ff0000;
        color: #fff;
    }
</style>
<div class="new122">
    <div class="widget-title" style="margin:-15px -10px 0">
        <h5>Custos Fixos</h5>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 20px 0;">
        <a href="<?= site_url('financeiro/adicionarCustoFixo') ?>" class="button btn btn-success" style="max-width: 180px">
            <span class="button__icon"><i class='bx bx-plus-circle'></i></span><span class="button__text2">Novo Custo Fixo</span>
        </a>
        <a href="<?= site_url('financeiro/lancamentos') ?>" class="button btn btn-primary" style="max-width: 180px">
            <span class="button__icon"><i class='bx bx-arrow-back'></i></span><span class="button__text2">Voltar a Lançamentos</span>
        </a>
    </div>

    <div class="span12" style="margin-left: 0; margin-top: 0;">
        <form action="<?= current_url(); ?>" method="get">
            <div class="span4" style="margin-left: 0;">
                <label>Categoria</label>
                <input type="text" name="categoria" class="span12" value="<?= html_escape($selectedCategoria); ?>" placeholder="Ex.: aluguel">
            </div>
            <div class="span3">
                <label>Status</label>
                <select name="status" class="span12">
                    <option value="">Todos</option>
                    <option value="1" <?= $selectedStatus === '1' ? 'selected' : '' ?>>Ativo</option>
                    <option value="0" <?= $selectedStatus === '0' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
            <div class="span2 pull-right" style="margin-top: 25px;">
                <button type="submit" class="button btn btn-primary btn-sm" style="min-width: 120px">
                    <span class="button__icon"><i class='bx bx-filter-alt'></i></span><span class="button__text2">Filtrar</span>
                </button>
            </div>
        </form>
    </div>

    <div class="widget-box">
        <div class="widget-content nopadding tab-content">
            <table class="table table-bordered" id="tabela">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Título</th>
                        <th>Categoria</th>
                        <th>Valor</th>
                        <th>Periodicidade</th>
                        <th>Vencimento</th>
                        <th>Forma de Pagamento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="9">Nenhum custo fixo cadastrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $r): ?>
                            <?php $statusBadge = ((int) $r->ativo === 1) ? 'badge-ativo' : 'badge-inativo'; ?>
                            <tr>
                                <td><?= (int) $r->idCustoFixo ?></td>
                                <td><?= html_escape($r->titulo) ?></td>
                                <td><?= html_escape($r->categoria) ?></td>
                                <td>R$ <?= number_format((float) $r->valor, 2, ',', '.') ?></td>
                                <td><?= ucfirst(html_escape($r->periodicidade)) ?></td>
                                <td><?= str_pad((string) $r->dia_vencimento, 2, '0', STR_PAD_LEFT) ?></td>
                                <td><?= html_escape($r->forma_pagamento) ?></td>
                                <td><span class="badge <?= $statusBadge ?>"><?= ((int) $r->ativo === 1) ? 'Ativo' : 'Inativo' ?></span></td>
                                <td>
                                    <a href="<?= site_url('financeiro/editarCustoFixo/' . $r->idCustoFixo) ?>" class="btn-nwe3" title="Editar"><i class="bx bx-edit"></i></a>
                                    <a href="<?= site_url('financeiro/alternarStatusCustoFixo/' . $r->idCustoFixo) ?>" class="btn-nwe4" title="<?= ((int) $r->ativo === 1) ? 'Inativar' : 'Ativar' ?>"><i class="bx bx-toggle-<?= ((int) $r->ativo === 1) ? 'left' : 'right' ?>"></i></a>
                                    <a href="#modal-excluir" role="button" data-toggle="modal" custo-fixo="<?= $r->idCustoFixo ?>" class="btn-nwe4" title="Excluir"><i class="bx bx-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= $this->pagination->create_links(); ?>
</div>

<div id="modal-excluir" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <form action="<?= site_url('financeiro/excluirCustoFixo') ?>" method="post">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
            <h5 id="myModalLabel">Excluir Custo Fixo</h5>
        </div>
        <div class="modal-body">
            <input type="hidden" id="idCustoFixo" name="id" value="" />
            <h5 style="text-align:center">Deseja realmente excluir este custo fixo?</h5>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:center">
            <button class="button btn btn-warning" data-dismiss="modal" aria-hidden="true"><span class="button__icon"><i class="bx bx-x"></i></span><span class="button__text2">Cancelar</span></button>
            <button class="button btn btn-danger"><span class="button__icon"><i class='bx bx-trash'></i></span> <span class="button__text2">Excluir</span></button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $(document).on('click', 'a', function() {
        var custo = $(this).attr('custo-fixo');
        $('#idCustoFixo').val(custo);
    });
});
</script>
