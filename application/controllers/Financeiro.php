<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Financeiro extends MY_Controller
{
    public const FILTROS = [
        'pesquisa' => 'texto',
        'tipo' => ['receita', 'despesa'],
        'status' => ['pendente', 'pago', 'vencido'],
        'periodo' => ['dia', 'semana', 'mes_anterior', 'mes', 'mes_posterior', 'ano', 'personalizado'],
        'de' => 'texto',
        'ate' => 'texto',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('financeiro_model');
        $this->load->helper(['form', 'financeiro', 'codegen_helper']);
        $this->data['menuLancamentos'] = 'financeiro';
    }

    public function index()
    {
        $this->lancamentos();
    }

    public function lancamentos()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar lançamentos.');
            redirect(base_url());
        }

        $hoje = date('Y-m-d');
        $filtros = $this->filtrosDaListagem();
        $offset = (int) $this->uri->segment(3);
        $total = $this->financeiro_model->contar($filtros, $hoje);

        $this->data['hoje'] = $hoje;
        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->financeiro_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset, $hoje);
        $this->data['totais'] = $this->financeiro_model->totais($filtros, $hoje);
        $this->data['indicadores_financeiros'] = $this->financeiro_model->indicadoresGerenciais($filtros, $hoje);
        $this->data['visao_geral'] = $this->financeiro_model->visaoGeral();
        $this->data['paginacao'] = $this->paginacao(site_url('financeiro/lancamentos'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aLancamento'),
            'editar' => $this->permite('eLancamento'),
            'excluir' => $this->permite('dLancamento'),
            'ver_cliente' => $this->permite('vCliente'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo lançamento', 'icon' => 'plus', 'href' => site_url('financeiro/adicionar') . listagemQuery($filtros)];
        }

        $this->data['view'] = 'financeiro/lancamentos';

        return $this->layout();
    }

    private function filtrosDaListagem(?array $entrada = null): array
    {
        $filtros = listagemFiltros(self::FILTROS, $entrada ?? $this->input->get());
        $hoje = new DateTimeImmutable('today');

        $de = dataIsoParaYmd($filtros['de'] ?? null);
        $ate = dataIsoParaYmd($filtros['ate'] ?? null);
        $periodo = $filtros['periodo'] ?? ($de !== null && $ate !== null ? 'personalizado' : 'mes');

        $intervalo = $periodo === 'personalizado' ? null : financeiroPeriodo($periodo, $hoje);
        if ($intervalo === null && $de !== null && $ate !== null) {
            $intervalo = $de <= $ate ? [$de, $ate] : [$ate, $de];
            $periodo = 'personalizado';
        } elseif ($intervalo === null) {
            $intervalo = financeiroPeriodo('mes', $hoje);
            $periodo = 'mes';
        }

        // Período e datas primeiro, nessa ordem, como ficam na URL.
        return ['periodo' => $periodo, 'de' => $intervalo[0], 'ate' => $intervalo[1]]
            + array_diff_key($filtros, ['periodo' => true, 'de' => true, 'ate' => true]);
    }

    public function valorMonetario($valor)
    {
        if ($this->normalizarValorMonetario($valor) === null) {
            $this->form_validation->set_message('valorMonetario', 'O campo {field} deve conter um valor monetário válido.');
            return false;
        }

        return true;
    }

    private function normalizarDataCustoFixo($data)
    {
        $data = trim((string) $data);
        if ($data === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $formato) {
            $dt = DateTime::createFromFormat($formato, $data);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }

        $timestamp = strtotime($data);
        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    private function normalizarValorMonetario($valor)
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $valor = preg_replace('/[^0-9,.]/', '', $valor);
        if ($valor === '') {
            return null;
        }

        $temVirgula = strpos($valor, ',') !== false;
        $quantidadePontos = substr_count($valor, '.');
        $quantidadeVirgulas = substr_count($valor, ',');

        if ($quantidadeVirgulas > 1) {
            return null;
        }

        if ($temVirgula) {
            // pt-BR: pontos podem ser separadores de milhar e a vírgula é decimal.
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } elseif ($quantidadePontos > 1) {
            // Mais de um ponto sem vírgula não é um decimal canônico válido.
            return null;
        }

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $valor)) {
            return null;
        }

        return (float) $valor;
    }

    private function montarDadosCustoFixo($id = null)
    {
        $valor = $this->normalizarValorMonetario($this->input->post('valor'));
        $data = [
            'titulo' => trim((string) $this->input->post('titulo')),
            'categoria' => trim((string) $this->input->post('categoria')),
            'valor' => $valor !== null ? number_format($valor, 2, '.', '') : null,
            'periodicidade' => $this->input->post('periodicidade') ?: 'mensal',
            'dia_vencimento' => (int) $this->input->post('dia_vencimento'),
            'regra_vencimento' => $this->input->post('regra_vencimento') ?: 'mes_vencido',
            'forma_pagamento' => trim((string) $this->input->post('forma_pagamento')),
            'ativo' => (int) $this->input->post('ativo'),
            'observacoes' => trim((string) $this->input->post('observacoes')),
            'data_inicio' => $this->normalizarDataCustoFixo($this->input->post('data_inicio')),
            'data_fim' => $this->normalizarDataCustoFixo($this->input->post('data_fim')),
            'usuarios_id' => $this->session->userdata('id_admin'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id === null) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $data;
    }

    private function valorCustoFixoEmCentavos($valor): int
    {
        $texto = trim((string) $valor);
        $texto = str_replace(',', '.', $texto);
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $texto, $partes)) {
            return 0;
        }
        $fracao = str_pad($partes[2] ?? '', 2, '0');

        return ((int) $partes[1] * 100) + (int) $fracao;
    }

    public function custosFixos()
    {
        if (! $this->permite('vLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para acessar custos fixos.');
            return redirect('');
        }

        $mesAtual = dataIsoParaYmd($this->input->get('de')) ?: date('Y-m-01');
        $mesFim = dataIsoParaYmd($this->input->get('ate')) ?: date('Y-m-t');
        if ($mesAtual > $mesFim) {
            [$mesAtual, $mesFim] = [$mesFim, $mesAtual];
        }

        $competencias = $this->financeiro_model->getCompetenciasDashboard($mesAtual, $mesFim);
        $totalPagoCentavos = 0;
        $totalAPagarCentavos = 0;
        foreach ($competencias as $competencia) {
            $centavos = $this->valorCustoFixoEmCentavos($competencia->valor ?? null);
            if ((int) ($competencia->pago ?? 0) === 1) {
                $totalPagoCentavos += $centavos;
            } else {
                $totalAPagarCentavos += $centavos;
            }
        }

        $categoria = is_scalar($this->input->get('categoria')) ? trim((string) $this->input->get('categoria')) : '';
        $status = in_array((string) $this->input->get('status'), ['', '0', '1'], true) ? (string) $this->input->get('status') : '';
        $this->data += [
            'competencias' => $competencias,
            'mesAtual' => date('m/Y'),
            'periodoDe' => $mesAtual,
            'periodoAte' => $mesFim,
            'totalPago' => $totalPagoCentavos / 100,
            'totalAPagar' => $totalAPagarCentavos / 100,
            'totalGeral' => ($totalPagoCentavos + $totalAPagarCentavos) / 100,
            'results' => $this->financeiro_model->getCustosFixos($categoria, $status),
            'filtrosCustos' => ['categoria' => $categoria, 'status' => $status],
            'podeCustos' => [
                'adicionar' => $this->permite('aLancamento'),
                'editar' => $this->permite('eLancamento'),
                'status' => $this->permite('dLancamento'),
                'excluir' => $this->permite('dLancamento'),
            ],
            'view' => 'financeiro/custosFixos',
        ];

        return $this->layout();
    }

    public function adicionarCustoFixo()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para cadastrar custos fixos.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';
        $this->form_validation->set_rules('titulo', 'Título', 'trim|required');
        $this->form_validation->set_rules('categoria', 'Categoria', 'trim|required');
        $this->form_validation->set_rules('valor', 'Valor', 'trim|required|callback_valorMonetario');
        $this->form_validation->set_rules('periodicidade', 'Periodicidade', 'trim|required|in_list[mensal,unico]');
        $this->form_validation->set_rules('dia_vencimento', 'Dia de vencimento', 'trim|required|integer|greater_than[0]|less_than[32]');
        $this->form_validation->set_rules('forma_pagamento', 'Forma de pagamento', 'trim|required');
        $this->form_validation->set_rules('ativo', 'Ativo', 'trim|required|in_list[0,1]');
        $this->form_validation->set_rules('regra_vencimento', 'Regra de vencimento', 'trim|required|in_list[mes_vencido,mes_corrente]');

        if ($this->form_validation->run() == false) {
            $this->data['custom_error'] = validation_errors() ? '<div class="alert alert-danger">' . validation_errors() . '</div>' : false;
            $this->data['errosCustos'] = $this->form_validation->error_array();
            if ($this->data['custom_error'] && empty($this->data['errosCustos'])) {
                $this->data['errosCustos']['_geral'] = strip_tags($this->data['custom_error']);
            }
        } else {
            $data = $this->montarDadosCustoFixo();
            if ($this->financeiro_model->adicionarCustoFixo($data) == true) {
                $novoId = $this->financeiro_model->getInsertId();
                if ($novoId) {
                    $this->financeiro_model->gerarCompetencias($novoId);
                }
                $this->session->set_flashdata('success', 'Custo fixo cadastrado com sucesso!');
                log_info('Adicionou um custo fixo.');
                redirect(site_url('financeiro/custosFixos'));
                return;
            }

            $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro ao cadastrar o custo fixo.</p></div>';
            $this->data['errosCustos'] = ['_geral' => strip_tags($this->data['custom_error'])];
        }

        $this->data['result'] = null;
        $this->data['action'] = 'financeiro/adicionarCustoFixo';
        $this->data['button_text'] = 'Cadastrar';
        $this->data['menuCustosFixos'] = 'financeiro';
        $this->data['view'] = 'financeiro/custoFixoForm';

        return $this->layout();
    }

    public function editarCustoFixo()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3)) || ! $this->financeiro_model->getCustoFixoById($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Custo fixo não encontrado ou parâmetro inválido.');
            redirect(site_url('financeiro/custosFixos'));
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar custos fixos.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';
        $this->form_validation->set_rules('titulo', 'Título', 'trim|required');
        $this->form_validation->set_rules('categoria', 'Categoria', 'trim|required');
        $this->form_validation->set_rules('valor', 'Valor', 'trim|required|callback_valorMonetario');
        $this->form_validation->set_rules('periodicidade', 'Periodicidade', 'trim|required|in_list[mensal,unico]');
        $this->form_validation->set_rules('dia_vencimento', 'Dia de vencimento', 'trim|required|integer|greater_than[0]|less_than[32]');
        $this->form_validation->set_rules('forma_pagamento', 'Forma de pagamento', 'trim|required');
        $this->form_validation->set_rules('ativo', 'Ativo', 'trim|required|in_list[0,1]');
        $this->form_validation->set_rules('regra_vencimento', 'Regra de vencimento', 'trim|required|in_list[mes_vencido,mes_corrente]');

        if ($this->form_validation->run() == false) {
            $this->data['custom_error'] = validation_errors() ? '<div class="alert alert-danger">' . validation_errors() . '</div>' : false;
            $this->data['errosCustos'] = $this->form_validation->error_array();
            if ($this->data['custom_error'] && empty($this->data['errosCustos'])) {
                $this->data['errosCustos']['_geral'] = strip_tags($this->data['custom_error']);
            }
        } else {
            $id = (int) $this->input->post('idCustoFixo');
            $data = $this->montarDadosCustoFixo($id);
            if ($this->financeiro_model->atualizarCustoFixo($id, $data) == true) {
                // Recalcula vencimentos das competências não pagas
                $this->financeiro_model->recalcularCompetenciasNaoPagas($id);
                $this->session->set_flashdata('success', 'Custo fixo editado com sucesso!');
                log_info('Alterou um custo fixo. ID: ' . $id);
                redirect(site_url('financeiro/editarCustoFixo/' . $id));
                return;
            }

            $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro ao editar o custo fixo.</p></div>';
            $this->data['errosCustos'] = ['_geral' => strip_tags($this->data['custom_error'])];
        }

        $this->data['result'] = $this->financeiro_model->getCustoFixoById($this->uri->segment(3));
        $this->data['action'] = 'financeiro/editarCustoFixo/' . $this->uri->segment(3);
        $this->data['button_text'] = 'Salvar alterações';
        $this->data['menuCustosFixos'] = 'financeiro';
        $this->data['view'] = 'financeiro/custoFixoForm';

        return $this->layout();
    }

    public function competenciasCustoFixo()
    {
        if (! $this->permite('vLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar competências.');
            return redirect('');
        }

        $id = (int) $this->uri->segment(3);
        $custo = $id > 0 ? $this->financeiro_model->getCustoFixoById($id) : null;
        if (! $custo) {
            $this->session->set_flashdata('error', 'Custo fixo não encontrado.');
            return redirect('financeiro/custosFixos');
        }
        $erros = [];
        $competenciaForm = [
            'competencia' => '',
            'valor_competencia' => '',
            'data_vencimento_competencia' => '',
            'observacoes_competencia' => '',
        ];
        $abrirModalEdicao = false;
        if ($this->input->method() === 'post' && $this->input->post('salvar_competencia')) {
            if (! $this->permite('eLancamento')) {
                $this->session->set_flashdata('error', 'Você não tem permissão para editar competências.');
                return redirect('');
            }
            $competenciaForm = [
                'competencia' => trim((string) $this->input->post('competencia')),
                'valor_competencia' => trim((string) $this->input->post('valor_competencia')),
                'data_vencimento_competencia' => trim((string) $this->input->post('data_vencimento_competencia')),
                'observacoes_competencia' => trim((string) $this->input->post('observacoes_competencia')),
            ];
            $abrirModalEdicao = (string) $this->input->post('origem_competencia') === 'modal';
            $competencia = $competenciaForm['competencia'];
            $valor = $competenciaForm['valor_competencia'];
            $vencimento = $competenciaForm['data_vencimento_competencia'];
            if ($competencia === '' || valorDecimal((string) $valor) === null) {
                $erros['_geral'] = 'Informe uma competência e um valor válido.';
            } elseif (! $this->financeiro_model->salvarCompetencia($id, $competencia, $valor, $vencimento, $competenciaForm['observacoes_competencia'])) {
                $erros['_geral'] = 'Não foi possível salvar a competência.';
            } else {
                $this->session->set_flashdata('success', 'Competência salva.');
                return redirect('financeiro/competenciasCustoFixo/' . $id);
            }
        }
        $this->data += ['competencias' => $this->financeiro_model->getCompetenciasCustoFixo($id), 'custo' => $custo, 'idCustoFixo' => $id, 'podeEditar' => $this->permite('eLancamento'), 'errosCustos' => $erros, 'competenciaForm' => $competenciaForm, 'abrirModalEdicao' => $abrirModalEdicao, 'view' => 'financeiro/competenciasCustoFixo'];

        return $this->layout();
    }

    public function togglePagamentoCompetencia()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para alterar pagamento de competências.');
            redirect(base_url());
        }

        $idCompetencia = (int) $this->input->post('idCompetencia');
        $action = $this->input->post('action'); // 'pagar' ou 'despagar'
        $dataPagamento = $this->input->post('data_pagamento') ?: null;
        $obsPagamento = $this->input->post('observacoes_pagamento') ?: null;

        if ($idCompetencia && in_array($action, ['pagar', 'despagar'])) {
            if ($action === 'pagar') {
                $this->financeiro_model->marcarCompetenciaPaga($idCompetencia, $dataPagamento, $obsPagamento);
                $this->session->set_flashdata('success', 'Competência marcada como paga.');
            } else {
                $this->financeiro_model->desmarcarCompetenciaPaga($idCompetencia);
                $this->session->set_flashdata('success', 'Competência marcada como pendente.');
            }
        }

        $custoFixoId = (int) $this->input->post('custo_fixo_id');
        redirect(site_url('financeiro/competenciasCustoFixo/' . ($custoFixoId ?: $this->uri->segment(3))));
    }

    public function alternarStatusCustoFixo()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para alterar o status de custos fixos.');
            redirect(base_url());
        }

        $id = (int) ($this->input->post('idCustoFixo') ?: $this->uri->segment(3));
        $custo = $this->financeiro_model->getCustoFixoById($id);
        if (! $custo) {
            $this->session->set_flashdata('error', 'Custo fixo não encontrado.');
            redirect(site_url('financeiro/custosFixos'));
        }

        $ativo = (int) ($custo->ativo ? 0 : 1);
        if ($this->financeiro_model->atualizarCustoFixo($id, ['ativo' => $ativo, 'updated_at' => date('Y-m-d H:i:s'), 'usuarios_id' => $this->session->userdata('id_admin')]) == true) {
            $this->session->set_flashdata('success', $ativo ? 'Custo fixo ativado com sucesso!' : 'Custo fixo inativado com sucesso!');
            log_info('Alterou o status de um custo fixo. ID: ' . $id);
        } else {
            $this->session->set_flashdata('error', 'Ocorreu um erro ao alterar o status do custo fixo.');
        }

        redirect(site_url('financeiro/custosFixos'));
    }

    public function excluirCustoFixo()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir custos fixos.');
            redirect(base_url());
        }

        $id = (int) $this->input->post('id');
        if (! $id) {
            $id = (int) $this->uri->segment(3);
        }

        $custo = $this->financeiro_model->getCustoFixoById($id);
        if (! $custo) {
            $this->session->set_flashdata('error', 'Custo fixo não encontrado.');
            redirect(site_url('financeiro/custosFixos'));
        }

        if ($this->financeiro_model->delete('custos_fixos', 'idCustoFixo', $id) == true) {
            $this->session->set_flashdata('success', 'Custo fixo excluído com sucesso!');
            log_info('Removeu um custo fixo. ID: ' . $id);
        } else {
            $this->session->set_flashdata('error', 'Ocorreu um erro ao excluir o custo fixo.');
        }

        redirect(site_url('financeiro/custosFixos'));
    }

    public function adicionarReceita()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'aLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar lançamentos.');
            redirect(base_url());
        }
        $this->load->library('form_validation');
        $this->data['custom_error'] = '';
        $urlAtual = $this->input->post('urlAtual');
        if ($this->form_validation->run('receita') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $vencimento = $this->input->post('vencimento');
            $recebimento = $this->input->post('recebimento');
            if ($recebimento != null) {
                $recebimento = explode('/', $recebimento);
                $recebimento = $recebimento[2] . '-' . $recebimento[1] . '-' . $recebimento[0];
            }
            if ($vencimento == null) {
                $vencimento = date('d/m/Y');
            }
            try {
                $vencimento = explode('/', $vencimento);
                $vencimento = $vencimento[2] . '-' . $vencimento[1] . '-' . $vencimento[0];
            } catch (Exception $e) {
                $vencimento = date('Y/m/d');
            }
            // Formatação correta dos valores
            $valor = str_replace(',', '.', $this->input->post('valor'));
            $valor_desconto = floatval(str_replace(',', '.', $this->input->post('valor_desconto')));
            $desconto = $valor_desconto;
            $total_sem_desconto = $valor + $valor_desconto;
            $valor = $total_sem_desconto;
            $total_com_desconto = $valor - $valor_desconto;
            $valor_desconto = $total_com_desconto;
            // Verifica se o valor está em formato monetário
            if (!is_numeric($valor_desconto)) {
                $valor_desconto = str_replace([',', '.'], ['', ''], $valor_desconto);
            }
            if (!is_numeric($valor)) {
                $valor = str_replace([',', '.'], ['', ''], $valor);
            }
            // Criação do array de dados
            $data = [
                'descricao' => set_value('descricao'),
                'valor' => number_format($valor, 2, '.', ''), // Formatação para garantir 2 casas decimais
                'valor_desconto' => number_format($valor_desconto, 2, '.', ''), // Formatação para garantir 2 casas decimais
                'desconto' => $desconto,
                'tipo_desconto' => 'real',
                'data_vencimento' => $vencimento,
                'data_pagamento' => $recebimento != null ? $recebimento : date('Y-m-d'),
                'baixado' => $this->input->post('recebido') ?: 0,
                'cliente_fornecedor' => set_value('cliente'),
                'forma_pgto' => $this->input->post('formaPgto'),
                'tipo' => set_value('tipo'),
                'observacoes' => set_value('observacoes'),
                'usuarios_id' => $this->session->userdata('id_admin'),
                  'contas_id' => (int)($this->input->post('contas_id') ?: 1),
                  'categorias_id' => (int)($this->input->post('categorias_id') ?: (set_value('tipo') === 'despesa' ? 2 : 1)),
            ];
            if (set_value('idFornecedor')) {
                $data['clientes_id'] = set_value('idFornecedor');
            }
            if (set_value('idCliente')) {
                $data['clientes_id'] = set_value('idCliente');
            }
            // Inserção dos dados no banco
            if ($this->financeiro_model->add('lancamentos', $data) == true) {
                $this->session->set_flashdata('success', 'Lançamento adicionado com sucesso!');
                log_info('Adicionou um lançamento em Financeiro');
                redirect($urlAtual);
            } else {
                $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro.</p></div>';
            }
        }
        $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar adicionar o lançamento.');
        redirect($urlAtual);
    }

    public function adicionarReceita_parc()
    {
        //$this->load->library('form_validation');
        //$this->data['custom_error'] = '';
        $urlAtual = $this->input->post('urlAtual');
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar lançamentos.');
            redirect(base_url());
        } else {

            $valor_desconto = $this->input->post('desconto_parc') ?: 0;
            $entrada = $this->input->post('entrada') ?: 0;
            $valor_desconto = str_replace(',', '.', $valor_desconto);

            $qtdparcelas_parc = $this->input->post('qtdparcelas_parc') ?: 1; //4x
            $valor_parc = $this->input->post('valor_parc'); //450
            $valorparcelas = ($valor_parc - $entrada) / $qtdparcelas_parc;

            $desconto_por_parcela = $valor_desconto > 0 ? ($valor_desconto / $qtdparcelas_parc) : 0;

            //para por na descrição, valor total sem desconto e sem parcelamento
            $descricao_parc_valor = $valor_parc + $valor_desconto;

            //cria variavel para pegar o valor total ja com o desconto e diminui com o desconto
            $total_com_desconto = $valorparcelas + $desconto_por_parcela;

            if ($entrada >= $valor_parc) {
                $this->session->set_flashdata('error', 'O valor da entrada não pode ser maior ou igual ao valor total da receita/Despesa!');
                redirect($urlAtual);
            }

            $dia_pgto = $this->input->post('dia_pgto');
            $dia_base_pgto = $this->input->post('dia_base_pgto');
            $recebimento = $this->input->post('recebimento');

            try {
                $dia_pgto = explode('/', $dia_pgto);
                $dia_pgto = $dia_pgto[2] . '-' . $dia_pgto[1] . '-' . $dia_pgto[0];

                $dia_base_pgto = explode('/', $dia_base_pgto);
                $dia_base_pgto = $dia_base_pgto[2] . '-' . $dia_base_pgto[1] . '-' . $dia_base_pgto[0];
            } catch (Exception $e) {
                $dia_pgto = date('Y/m/d');
                $dia_base_pgto = date('Y/m/d');
            }

            if ($recebimento) {
                try {

                    $recebimento = explode('/', $recebimento);
                    $recebimento = $recebimento[2] . '-' . $recebimento[1] . '-' . $recebimento[0];
                } catch (Exception) {
                }
            }

            $comissao = $this->input->post('comissao');

            if (! validate_money($comissao)) {
                $comissao = str_replace([',', '.'], ['', ''], $comissao);
            }

            if ($entrada == 0) {
                $loops = 1;
                while ($loops <= $qtdparcelas_parc) {
                    $myDateTimeISO = $dia_base_pgto;
                    $loopsmes = $loops - 1;
                    $addThese = $loopsmes;
                    $myDateTime = new DateTime($myDateTimeISO);
                    $myDayOfMonth = date_format($myDateTime, 'j');
                    date_modify($myDateTime, "+$addThese months");

                    //Descobre se o dia do mês caiu
                    $myNewDayOfMonth = date_format($myDateTime, 'j');
                    if ($myDayOfMonth > 28 && $myNewDayOfMonth < 4) {
                        //Em caso afirmativo, corrija voltando o número de dias que transbordaram
                        date_modify($myDateTime, "-$myNewDayOfMonth days");
                    }

                    $data = [
                        'descricao' => $this->input->post('descricao_parc') . ' - Parcelamento de R$' . $descricao_parc_valor . '  [' . $loops . '/' . $qtdparcelas_parc . ']',
                        'valor' => $total_com_desconto,
                        'desconto' => $desconto_por_parcela,
                        'tipo_desconto' => 'real',
                        'valor_desconto' => $valorparcelas,
                        'data_vencimento' => date_format($myDateTime, 'Y-m-d'),
                        'data_pagamento' => $recebimento ?: date_format($myDateTime, 'Y-m-d'),
                        'baixado' => 0,
                        'cliente_fornecedor' => $this->input->post('cliente_parc'),
                        'clientes_id' => $this->input->post('idCliente_parc'),
                        'observacoes' => $this->input->post('observacoes_parc'),
                        'forma_pgto' => $this->input->post('formaPgto_parc'),
                        'tipo' => $this->input->post('tipo_parc'),
                        'usuarios_id' => $this->session->userdata('id_admin'),
                    ];

                    if ($this->financeiro_model->add('lancamentos', $data) == true) {
                        $this->session->set_flashdata('success', 'Lançamento adicionado com sucesso!');
                        log_info('Adicionou um lançamento em Financeiro');
                    } else {
                        $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro.</p></div>';
                    }
                    $loops++;
                }

                redirect($urlAtual);
            } else {
                $desconto_entrada = '0';
                $data1 = [
                    'descricao' => $this->input->post('descricao_parc') . ' - Entrada do parc. de R$' . $descricao_parc_valor . ' ',
                    'valor' => $entrada,
                    'desconto' => $desconto_entrada,
                    'valor_desconto' => $entrada,
                    'tipo_desconto' => 'real',
                    'data_vencimento' => $dia_pgto,
                    'data_pagamento' => $dia_pgto != null ? $dia_pgto : date_format('Y-m-d'),
                    'baixado' => 1,
                    'cliente_fornecedor' => $this->input->post('cliente_parc'),
                    'clientes_id' => $this->input->post('idCliente_parc'),
                    'observacoes' => $this->input->post('observacoes_parc'),
                    'forma_pgto' => $this->input->post('formaPgto_parc'),
                    'tipo' => $this->input->post('tipo_parc'),
                    'usuarios_id' => $this->session->userdata('id_admin'),
                ];
                // if (empty($data['valor_desconto'])) {
                //     $data['valor_desconto'] =  "0";
                // }

                $this->financeiro_model->add1('lancamentos', $data1);

                $loops = 1;
                while ($loops <= $qtdparcelas_parc) {
                    $myDateTimeISO = $dia_base_pgto;
                    $loopsmes = $loops - 1;
                    $addThese = $loopsmes;
                    $myDateTime = new DateTime($myDateTimeISO);
                    $myDayOfMonth = date_format($myDateTime, 'j');
                    date_modify($myDateTime, "+$addThese months");

                    //Find out if the day-of-month has dropped
                    $myNewDayOfMonth = date_format($myDateTime, 'j');
                    if ($myDayOfMonth > 28 && $myNewDayOfMonth < 4) {
                        //If so, fix by going back the number of days that have spilled over
                        date_modify($myDateTime, "-$myNewDayOfMonth days");
                    }

                    $data = [
                        'descricao' => $this->input->post('descricao_parc') . ' - Parcelamento de R$' . $descricao_parc_valor . ' [' . $loops . '/' . $qtdparcelas_parc . ']',
                        'valor' => $total_com_desconto,
                        'desconto' => $desconto_por_parcela,
                        'tipo_desconto' => 'real',
                        'valor_desconto' => $valorparcelas,
                        'data_vencimento' => date_format($myDateTime, 'Y-m-d'),
                        'data_pagamento' => date_format($myDateTime, 'Y-m-d'),
                        'baixado' => 0,
                        'cliente_fornecedor' => $this->input->post('cliente_parc'),
                        'observacoes' => $this->input->post('observacoes_parc'),
                        'forma_pgto' => $this->input->post('formaPgto_parc'),
                        'tipo' => $this->input->post('tipo_parc'),
                        'usuarios_id' => $this->session->userdata('id_admin'),

                    ];

                    // if (empty($data['valor_desconto'])) {
                    //     $data['valor_desconto'] =  "0";
                    // }

                    if ($this->financeiro_model->add('lancamentos', $data) == true) {
                        $this->session->set_flashdata('success', 'Lançamento adicionado com sucesso!');
                        log_info('Adicionou um lançamento em Financeiro');
                    } else {
                        $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro.</p></div>';
                    }
                    $loops++;
                }

                redirect($urlAtual);
            }
        }

        $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar adicionar o lançamento');
        redirect($urlAtual);
    }

    public function adicionarDespesa()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar lançamentos.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';
        $urlAtual = $this->input->post('urlAtual');
        if ($this->form_validation->run('despesa') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $vencimento = $this->input->post('vencimento');
            $pagamento = $this->input->post('pagamento');

            if ($pagamento != null) {
                $pagamento = explode('/', $pagamento);
                $pagamento = $pagamento[2] . '-' . $pagamento[1] . '-' . $pagamento[0];
            }

            if ($vencimento == null) {
                $vencimento = date('d/m/Y');
            }

            try {
                $vencimento = explode('/', $vencimento);
                $vencimento = $vencimento[2] . '-' . $vencimento[1] . '-' . $vencimento[0];
            } catch (Exception $e) {
                $vencimento = date('Y/m/d');
            }

            $valor = $this->input->post('valor');

            if (! validate_money($valor)) {
                $valor = str_replace([',', '.'], ['', ''], $valor);
            }

            $data = [
                'descricao' => set_value('descricao'),
                'valor' => $valor,
                'data_vencimento' => $vencimento,
                'data_pagamento' => $pagamento != null ? $pagamento : date('Y-m-d'),
                'baixado' => $this->input->post('pago') ?: 0,
                'cliente_fornecedor' => set_value('fornecedor'),
                'forma_pgto' => $this->input->post('formaPgto'),
                'tipo' => set_value('tipo'),
                'observacoes' => set_value('observacoes'),
                'usuarios_id' => $this->session->userdata('id_admin'),
            ];

            if (set_value('idFornecedor')) {
                $data['clientes_id'] = set_value('idFornecedor');
            }
            if (set_value('idCliente')) {
                $data['clientes_id'] = set_value('idCliente');
            }
            if ($this->financeiro_model->add('lancamentos', $data) == true) {
                $this->session->set_flashdata('success', 'Despesa adicionada com sucesso!');
                log_info('Adicionou uma despesa');
                redirect($urlAtual);
            } else {
                $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar adicionar despesa!');
                redirect($urlAtual);
            }
        }

        $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar adicionar despesa.');
        redirect($urlAtual);
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar lançamentos.');
            redirect(base_url());
        }

        return $this->formulario(null);
    }

    public function editar()
    {
        $lancamento = is_numeric($this->uri->segment(3)) ? $this->financeiro_model->getLancamento((int) $this->uri->segment(3)) : null;
        if (! $lancamento) {
            $this->session->set_flashdata('error', 'Lançamento não encontrado ou parâmetro inválido.');
            redirect('financeiro/lancamentos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar lançamentos.');
            redirect(base_url());
        }

        return $this->formulario($lancamento);
    }

    private function filtrosDeRetorno(): array
    {
        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        foreach (['de', 'ate'] as $data) {
            if (isset($filtros[$data]) && dataIsoParaYmd($filtros[$data]) === null) {
                unset($filtros[$data]);
            }
        }

        return $filtros;
    }

    private function formulario(?object $lancamento)
    {
        $editando = $lancamento !== null;
        $erros = [];
        $retorno = $this->filtrosDeRetorno();

        if ($this->input->method() === 'post') {
            $post = $this->input->post();
            $idCliente = is_scalar($post['clientes_id'] ?? null) && ctype_digit((string) $post['clientes_id']) ? (int) $post['clientes_id'] : 0;

            [$dados, $erros] = financeiroLancamentoDoFormulario(
                $post,
                [
                    'hoje' => date('Y-m-d'),
                    'controle_baixa' => ($this->data['configuration']['control_baixa'] ?? '0') == '1',
                    'pagamento_atual' => $editando ? substr((string) $lancamento->data_pagamento, 0, 10) : null,
                ],
                $idCliente > 0 ? $this->financeiro_model->getCliente($idCliente) : null
            );

            $parcelamento = ['parcelas' => 1, 'entrada' => 0, 'data_entrada' => null];
            if (! $editando) {
                [$parcelamento, $errosParcelas] = financeiroParcelamentoDoFormulario($post, $dados);
                $erros += $errosParcelas;
            }

            if ($erros === []) {
                $dados['usuarios_id'] = $this->usuarioLogado();

                if ($editando) {
                    $salvou = $this->financeiro_model->atualizar((int) $lancamento->idLancamentos, $dados);
                    $mensagem = 'Lançamento #' . (int) $lancamento->idLancamentos . ' salvo.';
                } elseif ($parcelamento['parcelas'] > 1) {
                    $linhas = financeiroLinhasDoParcelamento($dados, $parcelamento, $dados['usuarios_id']);
                    $salvou = $this->financeiro_model->adicionarVarios($linhas);
                    $mensagem = ucfirst(FINANCEIRO_TIPOS[$dados['tipo']]) . ' parcelada em ' . $parcelamento['parcelas'] . 'x: ' . count($linhas) . ' lançamentos criados.';
                } else {
                    $salvou = $this->financeiro_model->adicionar($dados) !== null;
                    $mensagem = ucfirst(FINANCEIRO_TIPOS[$dados['tipo']]) . ' lançada com vencimento em ' . dataBr($dados['data_vencimento']) . '.';
                }

                if ($salvou) {
                    log_info(($editando ? 'Alterou um lançamento no financeiro. ID ' . (int) $lancamento->idLancamentos : 'Adicionou um lançamento em Financeiro'));
                    $this->session->set_flashdata('success', $mensagem);

                    return redirect($this->urlDaListagemApos($retorno, (string) $dados['data_vencimento'], (string) $dados['tipo']));
                }

                $erros['_geral'] = 'Não foi possível salvar. Tente de novo.';
            }
        }

        $tipoPadrao = ($retorno['tipo'] ?? 'receita');
        $this->data['lancamento'] = $lancamento;
        $this->data['valores'] = financeiroValoresDoFormulario(
            $this->input->method() === 'post' ? $this->input->post() : null,
            $lancamento,
            ['tipo' => $tipoPadrao, 'data_vencimento' => date('Y-m-d'), 'data_entrada' => date('Y-m-d'), 'data_pagamento' => date('Y-m-d')]
        );
        $this->data['erros'] = $erros;
        $this->data['retorno'] = $retorno;
        $this->data['controle_baixa'] = ($this->data['configuration']['control_baixa'] ?? '0') == '1';
        $this->data['view'] = 'financeiro/formulario';

        return $this->layout();
    }

    private function urlDaListagemApos(array $retorno, string $vencimento, string $tipo): string
    {
        if (isset($retorno['tipo']) && $retorno['tipo'] !== $tipo) {
            unset($retorno['tipo']);
        }

        $periodo = $this->filtrosDaListagem($retorno);
        if ($vencimento < $periodo['de'] || $vencimento > $periodo['ate']) {
            $mes = financeiroPeriodo('mes', new DateTimeImmutable($vencimento));
            [$retorno['de'], $retorno['ate']] = $mes;
            $retorno['periodo'] = 'personalizado';
        }

        return site_url('financeiro/lancamentos') . listagemQuery($retorno);
    }

    private function usuarioLogado(): ?int
    {
        $id = (int) $this->session->userdata('id_admin');

        return $id > 0 ? $id : null;
    }

    /**
     * Padrão exato da descrição criada por Os::faturar() para uma OS:
     * 'Fatura de OS Nº: ' . $osId. O match é por igualdade, nunca por LIKE,
     * para impedir que a OS 114 case com a fatura da OS 1145.
     */
    private function descricaoFaturaOs($osId)
    {
        return 'Fatura de OS Nº: ' . (int) $osId;
    }

    /**
     * Classifica o lançamento como faturamento oficial de uma OS.
     * Só retorna um idOs quando o lançamento corresponde exatamente ao padrão
     * gravado por Os::faturar(), é do tipo receita e a OS existe.
     * Qualquer outro lançamento (receita manual, despesa, Fatura de Venda)
     * retorna null e mantém o comportamento anterior.
     */
    private function identificarFaturaOs($lancamento)
    {
        if (empty($lancamento) || $lancamento->tipo !== 'receita') {
            return null;
        }

        if (! preg_match('/^Fatura de OS Nº: (\d+)$/', trim((string) $lancamento->descricao), $matches)) {
            return null;
        }

        $osId = (int) $matches[1];

        if ($osId <= 0) {
            return null;
        }

        // Só trata como faturamento de OS se a OS realmente existir.
        if (! $this->os_model->getById($osId)) {
            return null;
        }

        return $osId;
    }

    private function responderExclusaoLancamento(int $status, array $corpo): void
    {
        $this->output
            ->set_content_type('application/json')
            ->set_status_header($status)
            ->set_output(json_encode($corpo, JSON_UNESCAPED_UNICODE));
    }

    public function excluirLancamento()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dLancamento')) {
            $this->responderExclusaoLancamento(403, ['result' => false, 'message' => 'Você não tem permissão para excluir lançamentos.']);

            return;
        }

        $this->load->model('os_model');

        $id = filter_var($this->input->post('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->responderExclusaoLancamento(422, ['result' => false, 'message' => 'Informe um lançamento válido para excluir.']);

            return;
        }

        if (! $this->db->trans_begin()) {
            $this->responderExclusaoLancamento(500, ['result' => false, 'message' => 'Não foi possível iniciar a exclusão com segurança.']);

            return;
        }

        // O lançamento é bloqueado antes de classificar ou alterar vínculos.
        $lancamento = $this->db->query('SELECT * FROM lancamentos WHERE idLancamentos = ? FOR UPDATE', [$id])->row();
        if (! $lancamento) {
            $this->db->trans_rollback();
            $this->responderExclusaoLancamento(404, ['result' => false, 'message' => 'Lançamento não encontrado ou já excluído.']);

            return;
        }

        $osIdFatura = $this->identificarFaturaOs($lancamento);
        $osVinculos = $this->db
            ->select('idOs')
            ->where('lancamento', $id)
            ->get('os')
            ->result();

        if (count($osVinculos) > 1 || ($osVinculos !== [] && $osIdFatura !== null && (int) $osVinculos[0]->idOs !== $osIdFatura)) {
            $this->db->trans_rollback();
            $this->responderExclusaoLancamento(409, ['result' => false, 'message' => 'Este lançamento possui um vínculo de OS que precisa ser resolvido antes da exclusão.']);

            return;
        }

        // Mantém a regra legada de vendas, mas verifica a execução dentro da transação.
        $vendasAtualizadas = $this->db
            ->set('lancamentos_id', null)
            ->set('faturado', 0)
            ->set('status', 'Finalizado')
            ->where('lancamentos_id', $id)
            ->update('vendas');
        if (! $vendasAtualizadas) {
            $this->db->trans_rollback();
            $this->responderExclusaoLancamento(500, ['result' => false, 'message' => 'Não foi possível desvincular a venda com segurança.']);

            return;
        }

        // A FK os.lancamento aponta para lancamentos. O vínculo precisa ser
        // nulificado antes do DELETE, na mesma transação, ou o MySQL rejeita a
        // exclusão com 1451 e o operador recebe apenas uma falha genérica.
        if ($osVinculos !== []) {
            $osDesvinculada = $this->db
                ->set('lancamento', null)
                ->where('idOs', (int) $osVinculos[0]->idOs)
                ->where('lancamento', $id)
                ->update('os');
            if (! $osDesvinculada || $this->db->affected_rows() !== 1) {
                $this->db->trans_rollback();
                $this->responderExclusaoLancamento(409, ['result' => false, 'message' => 'Não foi possível desvincular a OS deste lançamento.']);

                return;
            }
        }

        $result = $this->financeiro_model->delete('lancamentos', 'idLancamentos', $id);
        if (! $result) {
            $erroBanco = $this->db->error();
            $this->db->trans_rollback();
            $status = ((int) ($erroBanco['code'] ?? 0) === 1451) ? 409 : 500;
            $mensagem = $status === 409
                ? 'Este lançamento ainda possui registros dependentes e não pode ser excluído.'
                : 'Não foi possível excluir o lançamento. Nenhuma alteração foi confirmada.';
            $this->responderExclusaoLancamento($status, ['result' => false, 'message' => $mensagem]);

            return;
        }

        if ($osVinculos !== []) {
            $atualizarFaturado = true;

            if ($osIdFatura !== null) {
                $outras = $this->db
                    ->where('tipo', 'receita')
                    ->where('descricao', $this->descricaoFaturaOs($osIdFatura))
                    ->count_all_results('lancamentos');
                $atualizarFaturado = $outras === 0;
            }

            if ($atualizarFaturado && ! $this->db->set('faturado', 0)->where('idOs', (int) $osVinculos[0]->idOs)->update('os')) {
                $this->db->trans_rollback();
                $this->responderExclusaoLancamento(500, ['result' => false, 'message' => 'A exclusão não foi confirmada porque a OS não pôde ser atualizada.']);

                return;
            }
        } elseif ($osIdFatura !== null) {
            // Mantém a recuperação legada para uma OS faturada cujo vínculo FK
            // já estivesse inconsistente antes desta requisição.
            $outras = $this->db
                ->where('tipo', 'receita')
                ->where('descricao', $this->descricaoFaturaOs($osIdFatura))
                ->count_all_results('lancamentos');

            if ($outras === 0 && ! $this->db->set('faturado', 0)->where('idOs', $osIdFatura)->update('os')) {
                $this->db->trans_rollback();
                $this->responderExclusaoLancamento(500, ['result' => false, 'message' => 'A exclusão não foi confirmada porque a OS não pôde ser atualizada.']);

                return;
            }
        }

        if ($this->db->trans_status() === false || ! $this->db->trans_commit()) {
            $this->db->trans_rollback();
            $this->responderExclusaoLancamento(500, ['result' => false, 'message' => 'A exclusão falhou e foi revertida.']);

            return;
        }

        log_info('Excluiu um lançamento. ID: ' . $id);
        $this->session->set_flashdata('success', 'Lançamento excluído com sucesso!');
        $this->responderExclusaoLancamento(200, ['result' => true, 'message' => 'Lançamento excluído com sucesso.']);
    }
    
    public function autoCompleteClienteFornecedor()
    {
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            $this->financeiro_model->autoCompleteClienteFornecedor($q);
        }
    }

    public function autoCompleteClienteAddReceita()
    {
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            $this->financeiro_model->autoCompleteClienteReceita($q);
        }
    }

    protected function getThisYear()
    {
        $dias = date('z');
        $primeiro = date('Y-m-d', strtotime('-' . ($dias) . ' day'));
        $ultimo = date('Y-m-d', strtotime('+' . (364 - $dias) . ' day'));

        return [$primeiro, $ultimo];
    }

    protected function getThisWeek()
    {
        return [date('Y/m/d', strtotime('last sunday', strtotime('now'))), date('Y/m/d', strtotime('next saturday', strtotime('now')))];
    }

    protected function getLastSevenDays()
    {
        return [date('Y-m-d', strtotime('-7 day', strtotime('now'))), date('Y-m-d', strtotime('now'))];
    }

    protected function getThisMonth()
    {
        $mes = date('m');
        $ano = date('Y');
        $qtdDiasMes = date('t');
        $inicia = $ano . '-' . $mes . '-01';

        $ate = $ano . '-' . $mes . '-' . $qtdDiasMes;

        return [$inicia, $ate];
    }
}
