<div class="new122">
    <div class="widget-title" style="margin:-15px -10px 0">
        <h5>Competências Mensais — <?= html_escape($custo->titulo) ?></h5>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 20px 0;">
        <a href="<?= site_url('financeiro/custosFixos') ?>" class="button btn btn-warning" style="max-width:180px">
            <span class="button__icon"><i class='bx bx-arrow-back'></i></span><span class="button__text2">Voltar</span>
        </a>
        <a href="<?= site_url('financeiro/editarCustoFixo/' . $idCustoFixo) ?>" class="button btn btn-primary" style="max-width:220px">
            <span class="button__icon"><i class='bx bx-edit'></i></span><span class="button__text2">Editar cadastro</span>
        </a>
    </div>

    <div class="widget-box">
        <div class="widget-content nopadding" style="padding:20px;">
            <div class="alert alert-info" style="margin-bottom:15px;">
                <strong>Valor padrão:</strong> R$ <?= number_format((float) $custo->valor, 2, ',', '.') ?>
                &mdash; Vigência: <?= $custo->data_inicio ? date('d/m/Y', strtotime($custo->data_inicio)) : '—' ?> a <?= $custo->data_fim ? date('d/m/Y', strtotime($custo->data_fim)) : '—' ?>
                &mdash; Periodicidade: <?= ucfirst($custo->periodicidade) ?>
            </div>

            <h6 style="margin:15px 0 10px;">Competências cadastradas</h6>
            <table class="table table-bordered" id="tabela-competencias">
                <thead>
                    <tr>
                        <th>Competência</th>
                        <th>Valor da competência</th>
                        <th>Vencimento</th>
                        <th>Observações</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($competencias)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;color:#888;">Nenhuma competência cadastrada. Use o formulário abaixo para adicionar.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($competencias as $comp): ?>
                        <tr>
                            <td><?= date('m/Y', strtotime($comp->competencia)) ?></td>
                            <td><strong>R$ <?= number_format((float) $comp->valor, 2, ',', '.') ?></strong></td>
                            <td><?= $comp->data_vencimento ? date('d/m/Y', strtotime($comp->data_vencimento)) : '—' ?></td>
                            <td><?= html_escape($comp->observacoes ?? '') ?></td>
                            <td>
                                <button class="btn-nwe3 btn-editar-competencia" title="Editar competência"
                                    data-competencia="<?= $comp->competencia ?>"
                                    data-valor="<?= number_format((float) $comp->valor, 2, ',', '.') ?>"
                                    data-vencimento="<?= $comp->data_vencimento ? date('d/m/Y', strtotime($comp->data_vencimento)) : '' ?>"
                                    data-observacoes="<?= html_escape($comp->observacoes ?? '') ?>">
                                    <i class="bx bx-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h6 style="margin:25px 0 10px;" id="form-title">Nova competência</h6>
            <form action="<?= site_url('financeiro/competenciasCustoFixo/' . $idCustoFixo) ?>" method="post" class="form-horizontal">
                <input type="hidden" name="salvar_competencia" value="1">
                <div class="row-fluid">
                    <div class="span3">
                        <label>Competência</label>
                        <input type="text" name="competencia" id="input-competencia" class="span12" placeholder="YYYY-MM-01" required>
                        <small style="color:#888">Formato: YYYY-MM-01 (ex: 2026-06-01)</small>
                    </div>
                    <div class="span3">
                        <label>Valor da competência</label>
                        <input type="text" name="valor_competencia" id="input-valor" class="span12" placeholder="0,00" required>
                    </div>
                    <div class="span3">
                        <label>Data de vencimento</label>
                        <input type="text" name="data_vencimento_competencia" id="input-vencimento" class="span12 datepicker" placeholder="dd/mm/aaaa">
                    </div>
                    <div class="span3">
                        <label>Observações</label>
                        <input type="text" name="observacoes_competencia" id="input-observacoes" class="span12" maxlength="255" placeholder="Opcional">
                    </div>
                </div>
                <div class="form-actions" style="margin-top:15px;">
                    <button type="submit" class="button btn btn-success">
                        <span class="button__icon"><i class='bx bx-save'></i></span>
                        <span class="button__text2" id="btn-salvar-text">Salvar competência</span>
                    </button>
                    <button type="button" class="button btn btn-default" id="btn-cancelar-edicao" style="display:none;">
                        <span class="button__icon"><i class='bx bx-x'></i></span>
                        <span class="button__text2">Cancelar edição</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-editar-competencia').on('click', function() {
        var btn = $(this);
        $('#input-competencia').val(btn.data('competencia'));
        $('#input-valor').val(btn.data('valor'));
        $('#input-vencimento').val(btn.data('vencimento'));
        $('#input-observacoes').val(btn.data('observacoes'));
        $('#form-title').text('Editar competência — ' + btn.data('competencia'));
        $('#btn-salvar-text').text('Atualizar competência');
        $('#btn-cancelar-edicao').show();
        $('html, body').animate({ scrollTop: $('#form-title').offset().top - 20 }, 300);
    });

    $('#btn-cancelar-edicao').on('click', function() {
        $('#input-competencia').val('');
        $('#input-valor').val('');
        $('#input-vencimento').val('');
        $('#input-observacoes').val('');
        $('#form-title').text('Nova competência');
        $('#btn-salvar-text').text('Salvar competência');
        $(this).hide();
    });
});
</script>
