<link rel="stylesheet" href="<?php echo base_url(); ?>assets/js/jquery-ui/css/smoothness/jquery-ui-1.9.2.custom.css" />
<script type="text/javascript" src="<?php echo base_url() ?>assets/js/jquery-ui/js/jquery-ui-1.9.2.custom.js"></script>
<script type="text/javascript" src="<?php echo base_url() ?>assets/js/jquery.validate.js"></script>
<script src="<?php echo base_url() ?>assets/js/sweetalert2.all.min.js"></script>

<link rel="stylesheet" href="<?php echo base_url() ?>assets/trumbowyg/ui/trumbowyg.css">
<script type="text/javascript" src="<?php echo base_url() ?>assets/trumbowyg/trumbowyg.js"></script>
<script type="text/javascript" src="<?php echo base_url() ?>assets/trumbowyg/langs/pt_br.js"></script>

<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/custom.css" />

<div class="row-fluid" style="margin-top:0">
    <div class="span12">
        <div class="widget-box">
            <div class="widget-title">
                <h5>Cadastro de OS</h5>
            </div>
            <div class="widget-content nopadding tab-content">
                <div class="span12" id="divProdutosServicos" style=" margin-left: 0">

                    <ul class="nav nav-tabs">
                        <li class="active" id="tabDetalhes"><a href="#tab1" data-toggle="tab">Detalhes da OS</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab1">
                            <div class="span12" id="divCadastrarOs">
                                <?php if ($custom_error == true) { ?>
                                    <div class="span12 alert alert-danger" id="divInfo" style="padding: 1%;">Dados incompletos, verifique os campos com asterisco ou se selecionou corretamente cliente, responsável e garantia.<br />Ou se tem um cliente e um termo de garantia cadastrado.</div>
                                <?php
                                } ?>
                                <form action="<?php echo current_url(); ?>" method="post" id="formOs">
                                    <div class="span12" style="padding: 1%">
                                        <div class="span6">
                                            <label for="cliente">Cliente<span class="required">*</span></label>
                                            <input id="cliente" class="span12" type="text" name="cliente" value="" />
                                            <input id="clientes_id" class="span12" type="hidden" name="clientes_id" value="" />
                                        </div>
                                        <div class="span6">
                                            <label for="tecnico">Técnico / Responsável<span class="required">*</span></label>
                                            <input id="tecnico" class="span12" type="text" name="tecnico" value="<?= $this->session->userdata('nome_admin'); ?>" />
                                            <input id="usuarios_id" class="span12" type="hidden" name="usuarios_id" value="<?= $this->session->userdata('id_admin'); ?>" />
                                        </div>
                                    </div>
                                    <div class="span12" style="padding: 1%; margin-left: 0">
                                        <div class="span3">
                                            <label for="status">Status<span class="required">*</span></label>
                                            <select class="span12" name="status" id="status" value="">
                                                <option value="Aberto">Aberto</option>
                                                <option value="Orçamento">Orçamento</option>
                                                <option value="Negociação">Negociação</option>
                                                <option value="Aprovado">Aprovado</option>
                                                <option value="Aguardando Peças">Aguardando Peças</option>
                                                <option value="Em Andamento">Em Andamento</option>
                                                <option value="Finalizado">Finalizado</option>
                                                <option value="Faturado">Faturado</option>
                                                <option value="Entregue">Entregue</option>
                                                <option value="Cancelado">Cancelado</option>
                                                <option value="Sem Conserto">Sem Conserto</option>
                                            </select>
                                        </div>
                                        <div class="span3">
                                            <label for="dataInicial">Data Inicial<span class="required">*</span></label>
                                            <input id="dataInicial" autocomplete="off" class="span12 datepicker" type="text" name="dataInicial" value="<?php echo date('d/m/Y'); ?>" />
                                        </div>
                                        <div class="span3">
                                            <label for="dataFinal">Data Final<span class="required">*</span></label>
                                            <input id="dataFinal" autocomplete="off" class="span12 datepicker" type="text" name="dataFinal" value="" />
                                        </div>
                                        <div class="span3">
                                            <label for="garantia">Garantia (dias)</label>
                                            <input id="garantia" type="number" placeholder="Status s/g inserir nº/0" min="0" max="9999" class="span12" name="garantia" value="" />
                                            <?php echo form_error('garantia'); ?>
                                            <label for="termoGarantia">Termo Garantia</label>
                                            <input id="termoGarantia" class="span12" type="text" name="termoGarantia" value="" />
                                            <input id="garantias_id" class="span12" type="hidden" name="garantias_id" value="" />
                                            <div id="garantia_retorno_block" class="garantia-retorno-block" style="margin-top:14px; padding:10px 12px; border:1px solid #d9d9d9; border-radius:6px; background:#f9f9f9; width:100%; box-sizing:border-box; clear:both;">
                                                <label for="garantia_retorno" style="display:flex; align-items:center; gap:8px; margin:0; font-weight:bold; cursor:pointer;">
                                                    <input type="checkbox" id="garantia_retorno" name="garantia_retorno" value="1" style="margin:0;" />
                                                    <span>OS em garantia / Retorno de garantia</span>
                                                </label>
                                                <div id="garantia_origem_container" class="hidden" style="margin-top:8px; padding:6px 10px; background:#fff3cd; border:1px solid #ffc107; border-radius:4px; font-size:13px;">
                                                    <span id="garantia_origem_info"></span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="garantia_origem_os_id" name="garantia_origem_os_id" value="" />
                                        </div>
                                    </div>
                                    <div class="span12" style="padding: 1%; margin-left: 0">
                                        <h4>Equipamento</h4>
                                        <div class="span12" style="margin-left: 0">
                                            <label for="equipamento_id">Equipamento existente</label>
                                            <select id="equipamento_id" name="equipamento_id" class="span12">
                                                <option value="">Novo equipamento</option>
                                            </select>
                                        </div>
                                        <div class="span3" style="margin-left: 0">
                                            <label for="equipamento_tipo">Tipo de equipamento</label>
                                            <input id="equipamento_tipo" class="span12" type="text" name="equipamento_tipo" value="<?= html_escape(set_value('equipamento_tipo')) ?>" placeholder="Notebook, Desktop, Monitor, Impressora" />
                                        </div>
                                        <div class="span3">
                                            <label for="equipamento_marca">Marca</label>
                                            <input id="equipamento_marca" class="span12" type="text" name="equipamento_marca" value="<?= html_escape(set_value('equipamento_marca')) ?>" placeholder="Acer, Dell, Lenovo, HP" />
                                        </div>
                                        <div class="span3">
                                            <label for="equipamento_modelo">Modelo</label>
                                            <input id="equipamento_modelo" class="span12" type="text" name="equipamento_modelo" value="<?= html_escape(set_value('equipamento_modelo')) ?>" placeholder="Aspire 5 A315-33" />
                                        </div>
                                        <div class="span3"> 
                                            <label for="equipamento_num_serie">Número de série</label>
                                            <input id="equipamento_num_serie" class="span12" type="text" name="equipamento_num_serie" value="<?= html_escape(set_value('equipamento_num_serie', isset($serialInternoSugerido) ? $serialInternoSugerido : '')) ?>" placeholder="<?= html_escape(isset($serialInternoSugerido) ? $serialInternoSugerido : 'TEST-SERIAL-001') ?>" />
                                            <input type="hidden" id="serial_interno_sugerido" name="serial_interno_sugerido" value="<?= (!empty($serialInternoSugerido) && set_value('equipamento_num_serie', $serialInternoSugerido) === $serialInternoSugerido) ? '1' : '0' ?>" />
                                            <div style="margin-top: 6px;">
                                                <button type="button" class="btn btn-mini" disabled title="Salve a OS para liberar a etiqueta do serial definitivo.">
                                                    Imprimir etiqueta do serial
                                                </button>
                                            </div>
                                            <small class="muted">Salve a OS para imprimir a etiqueta do serial definitivo.</small>
                                        </div>
                                    </div>
                                    <div class="span6" style="padding: 1%; margin-left: 0">
                                        <label for="descricaoProduto">
                                            <h4>Descrição Produto/Serviço</h4>
                                        </label>
                                        <textarea class="span12 editor" name="descricaoProduto" id="descricaoProduto" cols="30" rows="5"></textarea>
                                    </div>
                                    <div class="span6" style="padding: 1%; margin-left: 0">
                                        <label for="defeito">
                                            <h4>Defeito</h4>
                                        </label>
                                        <textarea class="span12 editor" name="defeito" id="defeito" cols="30" rows="5"></textarea>
                                    </div>
                                    <div class="span6" style="padding: 1%; margin-left: 0">
                                        <label for="observacoes">
                                            <h4>Observações</h4>
                                        </label>
                                        <textarea class="span12 editor" name="observacoes" id="observacoes" cols="30" rows="5"></textarea>
                                    </div>
                                    <div class="span6" style="padding: 1%; margin-left: 0">
                                        <label for="laudoTecnico">
                                            <h4>Laudo Técnico</h4>
                                        </label>
                                        <textarea class="span12 editor" name="laudoTecnico" id="laudoTecnico" cols="30" rows="5"></textarea>
                                    </div>
                                    <div class="span12" style="padding: 1%; margin-left: 0">
                                        <div class="span12" style="display:flex; justify-content: center;">
                                            <button class="button btn btn-success" id="btnContinuar">
                                              <span class="button__icon"><i class='bx bx-chevrons-right'></i></span><span class="button__text2">Continuar</span></button>
                                            <a href="<?php echo base_url() ?>index.php/os" class="button btn btn-mini btn-warning" style="max-width: 160px">
                                              <span class="button__icon"><i class="bx bx-undo"></i></span><span class="button__text2">Voltar</span></a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                .
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    $(document).ready(function() {
        var equipamentoEndpoint = "/index.php/os/equipamentosCliente/";
        var historicoSerialEndpoint = "/index.php/os/historicoSerial";
        var osAtualId = 0;
        var serialInternoSugerido = "<?= isset($serialInternoSugerido) ? html_escape($serialInternoSugerido) : '' ?>";
        var serialHistoricoTimer = null;
        var serialHistoricoUltimoConsultado = '';

        function escapeHtml(text) {
            return $('<div>').text(text == null ? '' : String(text)).html();
        }

        function atualizarFlagSerialInterno() {
            if (!serialInternoSugerido) {
                $('#serial_interno_sugerido').val('0');
                return;
            }

            $('#serial_interno_sugerido').val($('#equipamento_num_serie').val() === serialInternoSugerido ? '1' : '0');
        }

        function limparCamposEquipamento() {
            $('#equipamento_tipo').val('');
            $('#equipamento_marca').val('');
            $('#equipamento_modelo').val('');
            $('#equipamento_num_serie').val('');
            $('#equipamento_id').val('');
        }

        function montarResumoHistorico(item) {
            var resumo = '[Histórico anterior por número de série — OS #' + escapeHtml(String(item.idOs)) + ']\n';
            resumo += 'Cliente: ' + escapeHtml(item.cliente || '-') + '\n';
            resumo += 'Data: ' + escapeHtml(item.dataInicial || '-') + (item.dataFinal ? ' até ' + escapeHtml(item.dataFinal) : '') + '\n';
            resumo += 'Status: ' + escapeHtml(item.status || '-') + '\n';
            resumo += 'Garantia: ' + escapeHtml(item.situacaoGarantia || 'Indefinido');
            if (item.vencimentoGarantia) {
                resumo += ' (vence em ' + escapeHtml(item.vencimentoGarantia) + ')';
            }
            resumo += '\n';
            resumo += 'Equipamento: ' + escapeHtml(item.equipamento || '-') + '\n';
            resumo += 'Serial: ' + escapeHtml(item.num_serie || '-') + '\n';
            resumo += 'Descrição:\n' + escapeHtml(item.descricaoProduto || '-') + '\n';
            resumo += 'Defeito:\n' + escapeHtml(item.defeito || '-') + '\n';
            resumo += 'Laudo:\n' + escapeHtml(item.laudoTecnico || '-') + '\n';
            resumo += 'Observações:\n' + escapeHtml(item.observacoes || '-') + '\n';
            return resumo;
        }

        function anexarAoObservacoes(texto) {
            var $obs = $('#observacoes');
            if ($obs.length && $obs.trumbowyg) {
                try {
                    var atual = $obs.trumbowyg('html') || '';
                    // Convert plain text to HTML for appending
                    var textoHtml = $('<div>').text(texto).html().replace(/\n/g, '<br>');
                    if (atual && atual.length && atual !== '<br>') {
                        $obs.trumbowyg('html', atual + '<br><br>' + textoHtml);
                    } else {
                        $obs.trumbowyg('html', textoHtml);
                    }
                    return true;
                } catch(e) {
                    console.warn('Trumbowyg write failed, falling back to textarea', e);
                }
            }
            // Fallback for plain textarea
            try {
                var atual2 = $obs.val() || '';
                if (atual2.length) {
                    $obs.val(atual2 + '\n\n' + texto);
                } else {
                    $obs.val(texto);
                }
                return true;
            } catch(e2) {
                return false;
            }
        }

        function copiarHistoricoObservacoes(item) {
            var resumo = montarResumoHistorico(item);
            var ok = anexarAoObservacoes(resumo);
            $('#garantia_retorno').prop('checked', false);
            $('#garantia_origem_os_id').val('');
            $('#garantia_origem_container').addClass('hidden');
            if (ok) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Histórico copiado para Observações.',
                    showConfirmButton: false,
                    timer: 2000,
                });
            }
        }

        function receberGarantia(item) {
            var resumo = montarResumoHistorico(item);
            var ok = anexarAoObservacoes(resumo);
            $('#garantia_retorno').prop('checked', true);
            $('#garantia_origem_os_id').val(item.idOs);
            $('#garantia_origem_info')
                .html('Origem da garantia: <strong>OS #' + escapeHtml(String(item.idOs)) + '</strong>');
            $('#garantia_origem_container').removeClass('hidden');
            $('#garantia_retorno_block').addClass('garantia-retorno--active');
            if (ok) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Histórico copiado e garantia marcada.',
                    showConfirmButton: false,
                    timer: 2000,
                });
            }
        }

        function renderGarantiaCriticaBox(critica) {
            if (!critica || !critica.status) {
                return '';
            }

            var temas = {
                em_garantia: {
                    bg: '#fff8db',
                    border: '#e3b341',
                    color: '#6b4f00',
                    destaque: '#7a5a00'
                },
                vencida: {
                    bg: '#ffe5e9',
                    border: '#d46a7d',
                    color: '#7a1f31',
                    destaque: '#9c1f3b'
                },
                sem_garantia_registrada: {
                    bg: '#f4f4f4',
                    border: '#c7c7c7',
                    color: '#444444',
                    destaque: '#666666'
                },
                indefinida: {
                    bg: '#fff8e7',
                    border: '#d8c68a',
                    color: '#5c4e1b',
                    destaque: '#6f5e20'
                }
            };

            var tema = temas[critica.status] || temas.indefinida;
            var ultimaOs = critica.baseOsId ? 'OS #' + escapeHtml(String(critica.baseOsId)) : '-';
            var dataBase = critica.data_base_formatada || critica.data_base || '-';
            var vencimento = critica.data_final_garantia_formatada || critica.data_final_garantia || '-';
            var detalhes = [];

            detalhes.push('<div><strong>Última OS considerada:</strong> ' + escapeHtml(ultimaOs) + '</div>');
            detalhes.push('<div><strong>Data final da OS anterior:</strong> ' + escapeHtml(dataBase) + '</div>');
            detalhes.push('<div><strong>Prazo registrado:</strong> ' + escapeHtml(String(critica.garantia_dias || 0)) + ' dia(s)</div>');

            if (critica.status === 'em_garantia' || critica.status === 'vencida') {
                detalhes.push('<div><strong>Vencimento da garantia:</strong> ' + escapeHtml(vencimento) + '</div>');
            }

            return '<div style="margin-top:16px; padding:14px; border-radius:10px; border:1px solid ' + tema.border + '; background:' + tema.bg + '; color:' + tema.color + ';">' +
                '<div style="font-size:14px; font-weight:700; margin-bottom:6px; color:' + tema.destaque + ';">' + escapeHtml(critica.titulo || '') + '</div>' +
                '<div style="font-size:18px; font-weight:700; line-height:1.25; margin-bottom:10px;">' + escapeHtml(critica.contador || '') + '</div>' +
                '<div style="font-size:12px; line-height:1.55;">' + detalhes.join('') + '</div>' +
                '<div style="margin-top:10px; font-size:12px; line-height:1.5;">' + escapeHtml(critica.texto || '') + '</div>' +
            '</div>';
        }

        function exibirHistoricoSerial(items, garantiaCritica) {
            if (!items || !items.length) {
                return;
            }

            var html = '<div style="text-align:left;">';
            html += '<p style="margin-bottom:12px;">Este número de série já apareceu em OS anterior.</p>';
            html += '<div style="max-height:420px; overflow:auto; padding-right:4px;">';

            $.each(items, function(_, item) {
                var itemId = item.idOs;
                html += '<div class="serial-historico-card" data-os-id="' + escapeHtml(String(itemId)) + '" style="border:1px solid #d9d9d9; border-radius:8px; padding:12px; margin-bottom:12px; background:#fafafa;">';
                html += '<div style="margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">';
                html += '<strong>OS #' + escapeHtml(String(itemId)) + '</strong>';
                html += '<a href="' + escapeHtml(item.urlVisualizarOs) + '" target="_blank" rel="noopener noreferrer" class="btn btn-mini btn-primary">Abrir OS</a>';
                html += '</div>';
                html += '<div><strong>Cliente:</strong> ' + escapeHtml(item.cliente || '-') + '</div>';
                html += '<div><strong>Data:</strong> ' + escapeHtml(item.dataInicial || '-') + (item.dataFinal ? ' até ' + escapeHtml(item.dataFinal) : '') + '</div>';
                html += '<div><strong>Status:</strong> ' + escapeHtml(item.status || '-') + '</div>';
                html += '<div><strong>Garantia:</strong> ' + escapeHtml(item.situacaoGarantia || 'Indefinido');
                if (item.vencimentoGarantia) {
                    html += ' <span style="color:#666;">(vence em ' + escapeHtml(item.vencimentoGarantia) + ')</span>';
                }
                html += '</div>';
                html += '<div><strong>Equipamento:</strong> ' + escapeHtml(item.equipamento || '-') + '</div>';
                html += '<div><strong>Série:</strong> ' + escapeHtml(item.num_serie || '-') + '</div>';
                html += '<div style="margin-top:8px;"><strong>Descrição:</strong> ' + escapeHtml(item.descricaoProduto || '-') + '</div>';
                html += '<div><strong>Defeito:</strong> ' + escapeHtml(item.defeito || '-') + '</div>';
                html += '<div><strong>Laudo:</strong> ' + escapeHtml(item.laudoTecnico || '-') + '</div>';
                html += '<div><strong>Observações:</strong> ' + escapeHtml(item.observacoes || '-') + '</div>';
                html += '<div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">';
                html += '<button type="button" class="btn btn-mini btn-info btn-copiar-obs" data-os-id="' + escapeHtml(String(itemId)) + '">Copiar para Observações</button>';
                html += '<button type="button" class="btn btn-mini btn-warning btn-receber-garantia" data-os-id="' + escapeHtml(String(itemId)) + '">Receber em Garantia</button>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';
            html += renderGarantiaCriticaBox(garantiaCritica);
            html += '</div>';

            Swal.fire({
                title: 'Equipamento já possui histórico',
                html: html,
                icon: 'info',
                confirmButtonText: 'Fechar',
                width: 980,
                scrollbarPadding: false,
                showCloseButton: true,
            });

            // Store items for later access
            window.__serialHistoricoItems = {};
            $.each(items, function(_, item) {
                window.__serialHistoricoItems[item.idOs] = item;
            });
        }

        // Event delegation for popup buttons
        $(document).on('click', '.btn-copiar-obs', function() {
            var osId = $(this).data('os-id');
            if (window.__serialHistoricoItems && window.__serialHistoricoItems[osId]) {
                copiarHistoricoObservacoes(window.__serialHistoricoItems[osId]);
            }
        });

        $(document).on('click', '.btn-receber-garantia', function() {
            var osId = $(this).data('os-id');
            if (window.__serialHistoricoItems && window.__serialHistoricoItems[osId]) {
                receberGarantia(window.__serialHistoricoItems[osId]);
            }
        });

        function consultarHistoricoSerial(forcar) {
            var serial = $.trim($('#equipamento_num_serie').val() || '');

            if (!serial) {
                serialHistoricoUltimoConsultado = '';
                return;
            }

            if (!forcar && serial === serialHistoricoUltimoConsultado) {
                return;
            }

            serialHistoricoUltimoConsultado = serial;

            $.getJSON(historicoSerialEndpoint, { serial: serial, idOs: osAtualId })
                .done(function(response) {
                    if (response && response.found && response.historico && response.historico.length) {
                        exibirHistoricoSerial(response.historico, response.garantiaCritica || null);
                    }
                });
        }

        function agendarConsultaHistorico(forcar, delay) {
            clearTimeout(serialHistoricoTimer);
            serialHistoricoTimer = setTimeout(function() {
                consultarHistoricoSerial(!!forcar);
            }, delay || 450);
        }

        function preencherCamposEquipamento(equipamento) {
            $('#equipamento_tipo').val(equipamento.equipamento || '');
            $('#equipamento_marca').val(equipamento.marca || '');
            $('#equipamento_modelo').val(equipamento.modelo || '');
            $('#equipamento_num_serie').val(equipamento.num_serie || '');
            atualizarFlagSerialInterno();
            agendarConsultaHistorico(true, 100);
        }

        function carregarEquipamentosCliente(clienteId, equipamentoSelecionadoId) {
            var $select = $('#equipamento_id');
            limparCamposEquipamento();
            $select.prop('disabled', true).html('<option value="">Carregando...</option>');

            if (!clienteId) {
                $select.prop('disabled', false).html('<option value="">Novo equipamento</option>');
                return;
            }

            $.getJSON(equipamentoEndpoint + clienteId)
                .done(function(response) {
                    var options = '<option value="">Novo equipamento</option>';
                    if (response && response.equipamentos) {
                        $.each(response.equipamentos, function(_, equipamento) {
                            var label = [equipamento.equipamento, equipamento.marca, equipamento.modelo, equipamento.num_serie]
                                .filter(function(item) { return item && item.length; })
                                .join(' | ');
                            options += '<option value="' + equipamento.idEquipamentos + '" data-equipamento="' + (equipamento.equipamento || '') + '" data-marca="' + (equipamento.marca || '') + '" data-modelo="' + (equipamento.modelo || '') + '" data-num_serie="' + (equipamento.num_serie || '') + '">' + label + '</option>';
                        });
                    }
                    $select.html(options).prop('disabled', false);
                    if (equipamentoSelecionadoId) {
                        $select.val(String(equipamentoSelecionadoId)).trigger('change');
                    }
                })
                .fail(function() {
                    $select.html('<option value="">Novo equipamento</option>').prop('disabled', false);
                });
        }

        $('#equipamento_id').on('change', function() {
            var $option = $(this).find('option:selected');
            var equipamentoId = $(this).val();
            if (!equipamentoId) {
                limparCamposEquipamento();
                atualizarFlagSerialInterno();
                serialHistoricoUltimoConsultado = '';
                return;
            }
            preencherCamposEquipamento({
                equipamento: $option.data('equipamento'),
                marca: $option.data('marca'),
                modelo: $option.data('modelo'),
                num_serie: $option.data('num_serie')
            });
        });

        $('#equipamento_num_serie').on('input', function() {
            atualizarFlagSerialInterno();
            agendarConsultaHistorico(false, 500);
        }).on('blur', function() {
            atualizarFlagSerialInterno();
            agendarConsultaHistorico(true, 0);
        });
        atualizarFlagSerialInterno();

        $("#cliente").autocomplete({
            source: "<?php echo base_url(); ?>index.php/os/autoCompleteCliente",
            minLength: 1,
            select: function(event, ui) {
                $("#clientes_id").val(ui.item.id);
                carregarEquipamentosCliente(ui.item.id);
            }
        });
        $("#tecnico").autocomplete({
            source: "<?php echo base_url(); ?>index.php/os/autoCompleteUsuario",
            minLength: 1,
            select: function(event, ui) {
                $("#usuarios_id").val(ui.item.id);
            }
        });
        $("#termoGarantia").autocomplete({
            source: "<?php echo base_url(); ?>index.php/os/autoCompleteTermoGarantia",
            minLength: 1,
            select: function(event, ui) {
                $("#garantias_id").val(ui.item.id);
            }
        });

        $("#formOs").validate({
            rules: {
                cliente: {
                    required: true
                },
                tecnico: {
                    required: true
                },
                dataInicial: {
                    required: true
                },
                dataFinal: {
                    required: true
                }

            },
            messages: {
                cliente: {
                    required: 'Campo Requerido.'
                },
                tecnico: {
                    required: 'Campo Requerido.'
                },
                dataInicial: {
                    required: 'Campo Requerido.'
                },
                dataFinal: {
                    required: 'Campo Requerido.'
                }
            },
            errorClass: "help-inline",
            errorElement: "span",
            highlight: function(element, errorClass, validClass) {
                $(element).parents('.control-group').addClass('error');
            },
            unhighlight: function(element, errorClass, validClass) {
                $(element).parents('.control-group').removeClass('error');
                $(element).parents('.control-group').addClass('success');
            }
        });
        $(".datepicker").datepicker({
            dateFormat: 'dd/mm/yy'
        });
        $('.editor').trumbowyg({
            lang: 'pt_br',
            semantic: { 'strikethrough': 's', }
        });
    });

    // Garantia retorno: toggle visual
    $(document).on('change', '#garantia_retorno', function() {
        var $block = $('#garantia_retorno_block');
        if ($(this).is(':checked')) {
            $block.addClass('garantia-retorno--active');
        } else {
            $block.removeClass('garantia-retorno--active');
            $('#garantia_origem_os_id').val('');
            $('#garantia_origem_container').addClass('hidden');
        }
    });
</script>
