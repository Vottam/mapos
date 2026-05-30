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
                                    <input type="hidden" name="os_create_token" value="<?php echo html_escape($os_create_token ?? ''); ?>" />
                                    <div class="span12" style="padding: 1%">
                                        <div class="span6">
                                            <div class="control-group" style="margin-bottom:10px;">
                                                <label for="os_cliente_documento">CPF/CNPJ do cliente</label>
                                                <div class="controls" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-left:0;">
                                                    <input id="os_cliente_documento" class="cpfcnpj span8" type="text" name="os_cliente_documento" value="" autocomplete="off" />
                                                    <button id="btn_buscar_cliente_documento" class="btn btn-xs btn-primary" type="button">Buscar</button>
                                                </div>
                                                <div id="os_cliente_documento_msg" class="alert alert-info" style="display:none; margin:8px 0 0;"></div>
                                            </div>
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
                                            <button class="button btn btn-success" id="btnContinuar" type="submit">
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

<div id="modalClienteRapidoOs" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="modalClienteRapidoOsLabel" aria-hidden="true" style="width: 900px; margin-left: -450px;">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="modalClienteRapidoOsLabel">Cadastrar novo cliente</h3>
    </div>
    <div class="modal-body">
        <div id="clienteRapidoOsMensagem" class="alert" style="display:none; margin-bottom:10px;"></div>
        <form id="formClienteRapidoOs" class="form-horizontal" autocomplete="off">
            <div class="row-fluid">
                <div class="span6">
                    <div class="control-group">
                        <label for="os_cliente_modal_documento" class="control-label">CPF/CNPJ</label>
                        <div class="controls">
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <input id="os_cliente_modal_documento" type="text" name="documento" class="cpfcnpj span12" value="" autocomplete="off" style="flex:1 1 auto;" />
                                <button id="btn_buscar_cnpj_modal_os" class="btn btn-xs" type="button">Buscar dados do CNPJ</button>
                            </div>
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_nome" class="control-label">Nome/Razão Social<span class="required">*</span></label>
                        <div class="controls">
                            <input id="os_cliente_modal_nome" type="text" name="nomeCliente" class="span12" value="" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_contato" class="control-label">Contato</label>
                        <div class="controls">
                            <input id="os_cliente_modal_contato" type="text" name="contato" class="span12" value="" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_telefone" class="control-label">Telefone</label>
                        <div class="controls">
                            <input id="os_cliente_modal_telefone" type="text" name="telefone" class="span12" value="" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_celular" class="control-label">Celular</label>
                        <div class="controls">
                            <input id="os_cliente_modal_celular" type="text" name="celular" class="span12" value="" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_email" class="control-label">Email</label>
                        <div class="controls">
                            <input id="os_cliente_modal_email" type="text" name="email" class="span12" value="" autocomplete="off" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label class="control-label">Tipo de Cliente</label>
                        <div class="controls">
                            <label for="os_cliente_modal_fornecedor" class="btn btn-default">Fornecedor
                                <input type="checkbox" id="os_cliente_modal_fornecedor" name="fornecedor" class="badgebox" value="1">
                                <span class="badge">&check;</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="span6">
                    <div class="control-group">
                        <label for="os_cliente_modal_cep" class="control-label">CEP</label>
                        <div class="controls">
                            <input id="os_cliente_modal_cep" type="text" name="cep" class="span12" value="" autocomplete="off" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_rua" class="control-label">Rua</label>
                        <div class="controls">
                            <input id="os_cliente_modal_rua" type="text" name="rua" class="span12" value="" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_numero" class="control-label">Número</label>
                        <div class="controls">
                            <input id="os_cliente_modal_numero" type="text" name="numero" class="span12" value="" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_complemento" class="control-label">Complemento</label>
                        <div class="controls">
                            <input id="os_cliente_modal_complemento" type="text" name="complemento" class="span12" value="" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_bairro" class="control-label">Bairro</label>
                        <div class="controls">
                            <input id="os_cliente_modal_bairro" type="text" name="bairro" class="span12" value="" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_cidade" class="control-label">Cidade</label>
                        <div class="controls">
                            <input id="os_cliente_modal_cidade" type="text" name="cidade" class="span12" value="" />
                        </div>
                    </div>
                    <div class="control-group">
                        <label for="os_cliente_modal_estado" class="control-label">Estado</label>
                        <div class="controls">
                            <select id="os_cliente_modal_estado" name="estado" class="span12">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="modal-footer">
        <button class="btn btn-success" id="btn_salvar_cliente_rapido_os" type="button">Salvar cliente</button>
        <button class="btn" data-dismiss="modal" aria-hidden="true" type="button">Cancelar</button>
    </div>
</div>

<div id="modalClientesDuplicadosOs" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="modalClientesDuplicadosOsLabel" aria-hidden="true" style="width: 900px; margin-left: -450px;">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="modalClientesDuplicadosOsLabel">Foram encontrados vários clientes com este CPF/CNPJ</h3>
    </div>
    <div class="modal-body">
        <div id="clientesDuplicadosMensagem" class="alert alert-warning" style="margin-bottom:10px;">Selecione o cliente correto para vincular à OS.</div>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-condensed">
                <thead>
                    <tr>
                        <th>Nome/Razão Social</th>
                        <th>CPF/CNPJ</th>
                        <th>Contato</th>
                        <th>Telefone/Celular</th>
                        <th>Email</th>
                        <th>ID</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody id="clientesDuplicadosLista"></tbody>
            </table>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true" type="button">Fechar</button>
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
        var clientesDuplicadosOs = [];
        var estadosClienteRapidoOsCarregados = false;
        var estadoPendenteClienteRapidoOs = '';

        function normalizarDocumentoOs(valor) {
            return (valor || '').toString().replace(/\D+/g, '');
        }

        function formatarDocumentoOs(valor) {
            var documento = normalizarDocumentoOs(valor);

            if (documento.length === 11) {
                return documento.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
            }

            if (documento.length === 14) {
                return documento.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            }

            return valor || '';
        }

        function mostrarMensagemDocumentoOs(mensagem, classe) {
            var $box = $('#os_cliente_documento_msg');
            $box
                .removeClass('alert-info alert-success alert-warning alert-error')
                .addClass('alert-' + (classe || 'info'))
                .text(mensagem || '')
                .show();
        }

        function ocultarMensagemDocumentoOs() {
            $('#os_cliente_documento_msg')
                .removeClass('alert-info alert-success alert-warning alert-error')
                .hide()
                .text('');
        }

        function mostrarMensagemModalClienteRapidoOs(mensagem, classe) {
            var $box = $('#clienteRapidoOsMensagem');
            $box
                .removeClass('alert-info alert-success alert-warning alert-error')
                .addClass('alert-' + (classe || 'info'))
                .text(mensagem || '')
                .show();
        }

        function limparMensagemModalClienteRapidoOs() {
            $('#clienteRapidoOsMensagem')
                .removeClass('alert-info alert-success alert-warning alert-error')
                .hide()
                .text('');
        }

        function carregarEstadosClienteRapidoOs() {
            if (estadosClienteRapidoOsCarregados) {
                return;
            }

            $.getJSON('<?php echo base_url() ?>assets/json/estados.json', function(data) {
                var $estado = $('#os_cliente_modal_estado');
                $estado.find('option:not(:first)').remove();

                if (data && data.estados) {
                    $.each(data.estados, function(i, item) {
                        $estado.append(new Option(item.nome, item.sigla));
                    });
                }

                if (estadoPendenteClienteRapidoOs && $.trim($estado.val()) === '') {
                    $estado.val(estadoPendenteClienteRapidoOs);
                    estadoPendenteClienteRapidoOs = '';
                }

                estadosClienteRapidoOsCarregados = true;
            });
        }

        function preencherCampoSeVazioClienteRapidoOs(selector, valor) {
            if (valor === undefined || valor === null) {
                return;
            }

            var $campo = $(selector);
            if ($.trim($campo.val()) === '') {
                $campo.val(valor);
            }
        }

        function formatarCnpjExternoClienteRapidoOs(valor) {
            var documento = normalizarDocumentoOs(valor);
            if (documento.length === 14) {
                return documento.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            }
            return valor || '';
        }

        function capitalizarTextoClienteRapidoOs(valor) {
            if (typeof capital_letter === 'function') {
                return capital_letter(valor || '');
            }
            return valor || '';
        }

        function preencherEnderecoPorCepClienteRapidoOs(cep) {
            var cepLimpo = normalizarDocumentoOs(cep);

            if (cepLimpo.length !== 8) {
                return;
            }

            $.getJSON('https://viacep.com.br/ws/' + cepLimpo + '/json/?callback=?', function(data) {
                if (!data || data.erro) {
                    return;
                }

                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_rua', data.logradouro || '');
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_bairro', data.bairro || '');
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_cidade', data.localidade || '');
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_estado', data.uf || '');
            });
        }

        function consultarCnpjExternoClienteRapidoOs(auto) {
            var documento = normalizarDocumentoOs($('#os_cliente_modal_documento').val());

            if (documento.length !== 14) {
                mostrarMensagemModalClienteRapidoOs('Informe um CNPJ válido com 14 dígitos para consultar os dados externos.', 'warning');
                return;
            }

            mostrarMensagemModalClienteRapidoOs(auto ? 'CNPJ não encontrado no cadastro local. Consultando dados externos...' : 'Consultando dados externos do CNPJ...', 'info');

            $.ajax({
                url: 'https://www.receitaws.com.br/v1/cnpj/' + documento,
                dataType: 'jsonp',
                crossDomain: true,
                contentType: 'text/javascript'
            }).done(function(dados) {
                if (!dados || dados.status !== 'OK') {
                    mostrarMensagemModalClienteRapidoOs('CNPJ não encontrado no cadastro local. Não foi possível consultar dados externos; preencha manualmente.', 'warning');
                    return;
                }

                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_nome', capitalizarTextoClienteRapidoOs(dados.nome || ''));
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_contato', dados.fantasia || '');
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_telefone', (dados.telefone || '').split('/')[0].replace(/\s+/g, ''));
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_email', (dados.email || '').toLowerCase());
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_cep', (dados.cep || '').replace(/\./g, ''));
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_rua', capitalizarTextoClienteRapidoOs(dados.logradouro || ''));
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_numero', dados.numero || '');
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_complemento', capitalizarTextoClienteRapidoOs(dados.complemento || ''));
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_bairro', capitalizarTextoClienteRapidoOs(dados.bairro || ''));
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_cidade', capitalizarTextoClienteRapidoOs(dados.municipio || ''));
                preencherCampoSeVazioClienteRapidoOs('#os_cliente_modal_estado', dados.uf || '');
                estadoPendenteClienteRapidoOs = dados.uf || '';

                if ((dados.cep || '').replace(/\D+/g, '').length === 8) {
                    preencherEnderecoPorCepClienteRapidoOs(dados.cep);
                }

                mostrarMensagemModalClienteRapidoOs('CNPJ não encontrado no cadastro local. Dados externos carregados para revisão.', 'success');
            }).fail(function() {
                mostrarMensagemModalClienteRapidoOs('CNPJ não encontrado no cadastro local. Não foi possível consultar dados externos; preencha manualmente.', 'warning');
            });
        }

        function limparFormularioClienteRapidoOs() {
            $('#formClienteRapidoOs')[0].reset();
            limparMensagemModalClienteRapidoOs();
        }

        function preencherClienteNaOS(cliente) {
            if (!cliente) {
                return;
            }

            $('#cliente').val(cliente.nomeCliente || '').trigger('change').trigger('input');
            $('#clientes_id').val(cliente.idClientes || '').trigger('change');
            ocultarMensagemDocumentoOs();
            $('#modalClienteRapidoOs').modal('hide');
            $('#modalClientesDuplicadosOs').modal('hide');

            if (typeof carregarEquipamentosCliente === 'function' && cliente.idClientes) {
                carregarEquipamentosCliente(cliente.idClientes);
            }
        }

        function abrirModalClienteRapidoOs(documento, mensagem, consultarExterno) {
            limparFormularioClienteRapidoOs();
            $('#os_cliente_modal_documento').val(formatarDocumentoOs(documento));
            if (mensagem) {
                mostrarMensagemModalClienteRapidoOs(mensagem, 'info');
            }
            carregarEstadosClienteRapidoOs();
            $('#modalClienteRapidoOs').modal('show');
            setTimeout(function() {
                $('#os_cliente_modal_nome').focus();
            }, 150);

            if (consultarExterno && normalizarDocumentoOs(documento).length === 14) {
                setTimeout(function() {
                    consultarCnpjExternoClienteRapidoOs(true);
                }, 250);
            }
        }

        function abrirModalClientesDuplicadosOs(clientes, documento, mensagem) {
            clientesDuplicadosOs = clientes || [];
            var html = '';

            if (mensagem) {
                $('#clientesDuplicadosMensagem').text(mensagem).show();
            }

            $.each(clientesDuplicadosOs, function(index, cliente) {
                html += '<tr>' +
                    '<td>' + escapeHtml(cliente.nomeCliente || '-') + '</td>' +
                    '<td>' + escapeHtml(formatarDocumentoOs(cliente.documento || '')) + '</td>' +
                    '<td>' + escapeHtml(cliente.contato || '-') + '</td>' +
                    '<td>' + escapeHtml([cliente.telefone || '', cliente.celular || ''].filter(Boolean).join(' / ') || '-') + '</td>' +
                    '<td>' + escapeHtml(cliente.email || '-') + '</td>' +
                    '<td>' + escapeHtml(String(cliente.idClientes || '-')) + '</td>' +
                    '<td><button type="button" class="btn btn-mini btn-primary btn-usar-cliente-duplicado-os" data-index="' + index + '">Usar este cliente</button></td>' +
                '</tr>';
            });

            if (!html) {
                html = '<tr><td colspan="7">Nenhum cliente disponível para seleção.</td></tr>';
            }

            $('#clientesDuplicadosLista').html(html);
            $('#modalClientesDuplicadosOs').modal('show');
        }

        function buscarClientePorDocumentoOs() {
            var documento = normalizarDocumentoOs($('#os_cliente_documento').val());

            if (documento.length !== 11 && documento.length !== 14) {
                mostrarMensagemDocumentoOs('Informe um CPF ou CNPJ válido.', 'warning');
                return;
            }

            mostrarMensagemDocumentoOs('Consultando cliente local...', 'info');

            $.ajax({
                url: '<?php echo site_url('clientes/buscarPorDocumento'); ?>',
                type: 'GET',
                dataType: 'json',
                data: {
                    documento: documento
                }
            }).done(function(response) {
                if (response && response.found && response.duplicate && response.clients) {
                    abrirModalClientesDuplicadosOs(response.clients, documento, response.message || 'Foram encontrados vários clientes com este CPF/CNPJ.');
                    mostrarMensagemDocumentoOs(response.message || 'Foram encontrados vários clientes com este CPF/CNPJ.', 'warning');
                    return;
                }

                if (response && response.found && response.client) {
                    preencherClienteNaOS(response.client);
                    mostrarMensagemDocumentoOs(response.message || 'Cliente encontrado e selecionado.', 'success');
                    return;
                }

                if (response && response.reason === 'invalid_length') {
                    mostrarMensagemDocumentoOs(response.message || 'Informe um CPF ou CNPJ válido.', 'warning');
                    return;
                }

                if (response && response.reason === 'not_found') {
                    mostrarMensagemDocumentoOs(response.message || 'Cliente não encontrado. Cadastre um novo cliente.', 'info');
                    abrirModalClienteRapidoOs(documento, response.message || 'Cliente não encontrado. Cadastre um novo cliente.', response.type === 'cnpj');
                    return;
                }

                mostrarMensagemDocumentoOs('Não foi possível localizar o cliente informado.', 'warning');
            }).fail(function() {
                mostrarMensagemDocumentoOs('Não foi possível consultar o cliente agora.', 'warning');
            });
        }

        function salvarClienteRapidoOs() {
            var documento = normalizarDocumentoOs($('#os_cliente_modal_documento').val());
            var payload = {
                documento: documento,
                nomeCliente: $.trim($('#os_cliente_modal_nome').val()),
                contato: $.trim($('#os_cliente_modal_contato').val()),
                telefone: $.trim($('#os_cliente_modal_telefone').val()),
                celular: $.trim($('#os_cliente_modal_celular').val()),
                email: $.trim($('#os_cliente_modal_email').val()),
                cep: $.trim($('#os_cliente_modal_cep').val()),
                rua: $.trim($('#os_cliente_modal_rua').val()),
                numero: $.trim($('#os_cliente_modal_numero').val()),
                complemento: $.trim($('#os_cliente_modal_complemento').val()),
                bairro: $.trim($('#os_cliente_modal_bairro').val()),
                cidade: $.trim($('#os_cliente_modal_cidade').val()),
                estado: $.trim($('#os_cliente_modal_estado').val()),
                fornecedor: $('#os_cliente_modal_fornecedor').is(':checked') ? 1 : 0
            };

            if (!payload.nomeCliente) {
                mostrarMensagemModalClienteRapidoOs('Informe o nome ou razão social do cliente.', 'warning');
                return;
            }

            mostrarMensagemModalClienteRapidoOs('Salvando cliente...', 'info');

            $.ajax({
                url: '<?php echo site_url('clientes/salvarRapido'); ?>',
                type: 'POST',
                dataType: 'json',
                data: payload
            }).done(function(response) {
                if (response && response.success && response.client) {
                    $('#modalClienteRapidoOs').modal('hide');
                    preencherClienteNaOS(response.client);
                    mostrarMensagemDocumentoOs(response.message || 'Cliente cadastrado e selecionado.', 'success');
                    $('#os_cliente_documento').val(formatarDocumentoOs(documento));
                    return;
                }

                if (response && response.conflict && response.clients) {
                    $('#modalClienteRapidoOs').modal('hide');
                    abrirModalClientesDuplicadosOs(response.clients, documento, response.message || 'Cliente já cadastrado com este CPF/CNPJ.');
                    mostrarMensagemDocumentoOs(response.message || 'Cliente já cadastrado com este CPF/CNPJ.', 'warning');
                    return;
                }

                mostrarMensagemModalClienteRapidoOs((response && response.message) ? response.message : 'Não foi possível cadastrar o cliente.', 'warning');
            }).fail(function(xhr) {
                var mensagem = 'Não foi possível cadastrar o cliente.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    mensagem = xhr.responseJSON.message;
                }
                mostrarMensagemModalClienteRapidoOs(mensagem, 'warning');
            });
        }

        function aplicarCEPClienteRapidoOs() {
            var cep = normalizarDocumentoOs($('#os_cliente_modal_cep').val());

            if (cep.length !== 8) {
                return;
            }

            $.getJSON('https://viacep.com.br/ws/' + cep + '/json/?callback=?', function(data) {
                if (!data || data.erro) {
                    mostrarMensagemModalClienteRapidoOs('CEP não localizado.', 'warning');
                    return;
                }

                $('#os_cliente_modal_rua').val(data.logradouro || '');
                $('#os_cliente_modal_bairro').val(data.bairro || '');
                $('#os_cliente_modal_cidade').val(data.localidade || '');
                $('#os_cliente_modal_estado').val(data.uf || '');
            }).fail(function() {
                mostrarMensagemModalClienteRapidoOs('Não foi possível consultar o CEP agora.', 'warning');
            });
        }

        function selecionarClienteDuplicadoOs(index) {
            var cliente = clientesDuplicadosOs[index];
            if (!cliente) {
                return;
            }

            preencherClienteNaOS(cliente);
            mostrarMensagemDocumentoOs('Cliente selecionado manualmente.', 'success');
        }

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

        $('#btn_buscar_cnpj_modal_os').on('click', function() {
            consultarCnpjExternoClienteRapidoOs(false);
        });

        $('#btn_buscar_cliente_documento').on('click', function() {
            buscarClientePorDocumentoOs();
        });

        $('#os_cliente_documento').on('keydown', function(event) {
            if (event.which === 13 || event.keyCode === 13) {
                event.preventDefault();
                buscarClientePorDocumentoOs();
            }
        }).on('input', function() {
            if (!$.trim($(this).val()).length) {
                ocultarMensagemDocumentoOs();
            }
        });

        $('#btn_salvar_cliente_rapido_os').on('click', function() {
            salvarClienteRapidoOs();
        });

        $('#formClienteRapidoOs').on('submit', function(event) {
            event.preventDefault();
            salvarClienteRapidoOs();
        });

        $('#os_cliente_modal_cep').on('blur', function() {
            aplicarCEPClienteRapidoOs();
        });

        $(document).on('click', '.btn-usar-cliente-duplicado-os', function() {
            var index = $(this).data('index');
            selecionarClienteDuplicadoOs(index);
            $('#modalClientesDuplicadosOs').modal('hide');
        });

        $('#modalClienteRapidoOs').on('hidden', function() {
            limparMensagemModalClienteRapidoOs();
        });

        $('#modalClientesDuplicadosOs').on('hidden', function() {
            clientesDuplicadosOs = [];
            $('#clientesDuplicadosLista').empty();
        });

        var $formOs = $('#formOs');
        var $btnContinuar = $('#btnContinuar');
        var $btnContinuarTexto = $btnContinuar.find('.button__text2');
        var osCreateSubmitting = false;

        function definirEstadoSubmitOs(emAndamento) {
            osCreateSubmitting = emAndamento;
            $btnContinuar.prop('disabled', emAndamento).toggleClass('disabled', emAndamento);
            $btnContinuarTexto.text(emAndamento ? 'Salvando...' : 'Continuar');
        }

        $formOs.on('submit', function() {
            if (osCreateSubmitting) {
                return false;
            }

            return true;
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
            },
            submitHandler: function(form) {
                definirEstadoSubmitOs(true);
                form.submit();
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
