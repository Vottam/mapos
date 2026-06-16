<style>
.badge-pago { background:#28a745; color:#fff; padding:2px 8px; border-radius:3px; font-size:11px; }
.badge-pendente { background:#dc3545; color:#fff; padding:2px 8px; border-radius:3px; font-size:11px; }
.badge-ativo { background:#00cd00; color:#fff; padding:2px 8px; border-radius:3px; font-size:11px; }
.badge-inativo { background:#ff0000; color:#fff; padding:2px 8px; border-radius:3px; font-size:11px; }
</style>

<div class="new122">
    <!-- DASHBOARD OPERACIONAL DE CONTAS A PAGAR -->
    <div class="widget-title" style="margin:-15px -10px 0">
        <h5>Contas a Pagar — Vencimentos <?= $mesAtual ?></h5>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 20px 0;">
        <a href="<?= site_url('financeiro/adicionarCustoFixo') ?>" class="button btn btn-success" style="max-width:180px">
            <span class="button__icon"><i class='bx bx-plus-circle'></i></span><span class="button__text2">Novo Custo Fixo</span>
        </a>
        <a href="<?= site_url('financeiro/lancamentos') ?>" class="button btn btn-primary" style="max-width:180px">
            <span class="button__icon"><i class='bx bx-arrow-back'></i></span><span class="button__text2">Voltar a Lançamentos</span>
        </a>
    </div>

    <!-- Cards resumo -->
    <div style="display:flex;gap:15px;margin-bottom:20px;flex-wrap:wrap;">
        <div style="background:#f8f9fa;padding:15px 20px;border-radius:5px;border-left:4px solid #28a745;min-width:150px;">
            <small style="color:#666;">Pago (mês corrente)</small><br>
            <strong style="color:#28a745;font-size:20px;">R$ <?= number_format($totalPago, 2, ',', '.') ?></strong>
        </div>
        <div style="background:#f8f9fa;padding:15px 20px;border-radius:5px;border-left:4px solid #dc3545;min-width:150px;">
            <small style="color:#666;">A pagar (mês + atrasados)</small><br>
            <strong style="color:#dc3545;font-size:20px;">R$ <?= number_format($totalAPagar, 2, ',', '.') ?></strong>
        </div>
        <div style="background:#f8f9fa;padding:15px 20px;border-radius:5px;border-left:4px solid #6c757d;min-width:150px;">
            <small style="color:#666;">Total</small><br>
            <strong style="font-size:20px;">R$ <?= number_format($totalGeral, 2, ',', '.') ?></strong>
        </div>
    </div>

    <!-- Tabela de competências -->
    <div class="widget-box">
        <div class="widget-content nopadding tab-content">
            <table class="table table-bordered" id="tabela-competencias">
                <thead>
                    <tr>
                        <th>Competência</th>
                        <th>Título</th>
                        <th>Categoria</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Data pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($competencias)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;color:#888;">Nenhuma competência pendente ou do mês corrente.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($competencias as $comp): ?>
                        <tr>
                            <td><?= date('m/Y', strtotime($comp->competencia)) ?></td>
                            <td><?= html_escape($comp->titulo_custo) ?></td>
                            <td><?= html_escape($comp->categoria_custo) ?></td>
                            <td><strong>R$ <?= number_format((float) $comp->valor, 2, ',', '.') ?></strong></td>
                            <td><?= $comp->data_vencimento ? date('d/m/Y', strtotime($comp->data_vencimento)) : '—' ?></td>
                            <td>
                                <?php if ($comp->pago): ?>
                                    <span class="badge-pago">Pago</span>
                                <?php else: ?>
                                    <span class="badge-pendente">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $comp->data_pagamento && $comp->data_pagamento != '0000-00-00' ? date('d/m/Y', strtotime($comp->data_pagamento)) : '—' ?></td>
                            <td>
                                <?php if ($comp->pago): ?>
                                <form method="post" action="<?= site_url('financeiro/togglePagamentoCompetencia') ?>" style="display:inline;">
                                    <input type="hidden" name="idCompetencia" value="<?= $comp->idCompetencia ?>">
                                    <input type="hidden" name="action" value="despagar">
                                    <input type="hidden" name="custo_fixo_id" value="<?= $comp->idCustoFixo ?>">
                                    <button type="submit" class="btn-nwe4" title="Desmarcar pagamento"><i class="bx bx-undo"></i></button>
                                </form>
                                <?php else: ?>
                                <form method="post" action="<?= site_url('financeiro/togglePagamentoCompetencia') ?>" style="display:inline;">
                                    <input type="hidden" name="idCompetencia" value="<?= $comp->idCompetencia ?>">
                                    <input type="hidden" name="action" value="pagar">
                                    <input type="hidden" name="custo_fixo_id" value="<?= $comp->idCustoFixo ?>">
                                    <input type="hidden" name="data_pagamento" value="<?= date('d/m/Y') ?>">
                                    <button type="submit" class="btn-nwe3" title="Marcar como pago"><i class="bx bx-check"></i></button>
                                </form>
                                <?php endif; ?>
                                <a href="<?= site_url('financeiro/competenciasCustoFixo/' . $comp->idCustoFixo) ?>" class="btn-nwe3" title="Ver competências mensais"><i class="bx bx-calendar"></i></a>
                                <a href="<?= site_url('financeiro/editarCustoFixo/' . $comp->idCustoFixo) ?>" class="btn-nwe3" title="Editar cadastro"><i class="bx bx-edit"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SEÇÃO SECUNDÁRIA: CADASTROS DE CUSTOS FIXOS -->
    <div style="margin-top:30px;">
        <div class="widget-title" style="margin:0 -10px 10px">
            <h5>Cadastros de Custos Fixos</h5>
        </div>

        <div class="span12" style="margin-left: 0; margin-top: 10px;">
            <form action="<?= current_url(); ?>" method="get">
                <div class="span4" style="margin-left: 0;">
                    <label>Categoria</label>
                    <input type="text" name="categoria" class="span12" value="<?= html_escape($this->input->get('categoria')); ?>" placeholder="Ex.: aluguel">
                </div>
                <div class="span3">
                    <label>Status</label>
                    <select name="status" class="span12">
                        <option value="">Todos</option>
                        <option value="1" <?= $this->input->get('status') === '1' ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= $this->input->get('status') === '0' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="span2 pull-right" style="margin-top: 25px;">
                    <button type="submit" class="button btn btn-primary btn-sm" style="min-width: 120px">
                        <span class="button__icon"><i class='bx bx-filter-alt'></i></span><span class="button__text2">Filtrar</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="widget-box" style="margin-top:10px;">
            <div class="widget-content nopadding tab-content">
                <table class="table table-bordered" id="tabela-cadastros">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Valor padrão</th>
                            <th>Periodicidade</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:#888;">Nenhum custo fixo cadastrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($results as $r): ?>
                            <tr>
                                <td><?= (int) $r->idCustoFixo ?></td>
                                <td><?= html_escape($r->titulo) ?></td>
                                <td><?= html_escape($r->categoria) ?></td>
                                <td>R$ <?= number_format((float) $r->valor, 2, ',', '.') ?></td>
                                <td><?= ucfirst(html_escape($r->periodicidade)) ?></td>
                                <td><?= str_pad((string) $r->dia_vencimento, 2, '0', STR_PAD_LEFT) ?></td>
                                <td><span class="badge <?= ((int) $r->ativo === 1) ? 'badge-ativo' : 'badge-inativo' ?>"><?= ((int) $r->ativo === 1) ? 'Ativo' : 'Inativo' ?></span></td>
                                <td>
                                    <a href="<?= site_url('financeiro/competenciasCustoFixo/' . $r->idCustoFixo) ?>" class="btn-nwe3" title="Competências mensais"><i class="bx bx-calendar"></i></a>
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
    </div>
</div>

<!-- Modal excluir -->
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
        if (custo) {
            $('#idCustoFixo').val(custo);
        }
    });
});
</script>
