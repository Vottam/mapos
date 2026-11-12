<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Vendas extends MY_Controller
{
    public const FILTROS = [
        'pesquisa' => 'texto',
        'status' => ['Orçamento', 'Negociação', 'Aberto', 'Aprovado', 'Em Andamento', 'Aguardando Peças', 'Finalizado', 'Faturado', 'Cancelado', 'Entregue', 'Sem Conserto'],
        'de' => 'texto',
        'ate' => 'texto',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->helper(['form', 'os', 'vendas', 'clientes']);
        $this->load->model('vendas_model');
        $this->data['menuVendas'] = 'Vendas';
    }

    /**
     * Data da venda em AAAA-MM-DD (contrato do input type=date) ou null quando
     * ausente/inválida. Nunca substitui uma entrada inválida pela data de hoje:
     * o chamador responde com erro de validação.
     */
    private function normalizarDataVenda($valor): ?string
    {
        return dataIsoParaYmd(trim((string) $valor));
    }

    private function vendaOuRedirecionar($id)
    {
        $venda = $this->vendas_model->getById($id);
        if ($venda === null) {
            $this->session->set_flashdata('error', 'Venda não encontrada ou parâmetro inválido.');
            redirect('vendas/gerenciar');
            return null;
        }

        return $venda;
    }

    public function index()
    {
        $this->gerenciar();
    }

    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar vendas.');
            redirect(base_url());
        }

        $filtros = $this->filtrosDaListagem();
        $offset = (int) $this->uri->segment(3);
        $total = $this->vendas_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->vendas_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('vendas/gerenciar'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aVenda'),
            'editar' => $this->permite('eVenda'),
            'excluir' => $this->permite('dVenda'),
        ];
        $this->data['controle_edicao'] = ($this->data['configuration']['control_edit_vendas'] ?? '0') == '1';

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Nova venda', 'icon' => 'plus', 'href' => site_url('vendas/adicionar')];
        }

        $this->data['view'] = 'vendas/vendas';

        return $this->layout();
    }

private function filtrosDaListagem(): array
    {
        $filtros = listagemFiltros(self::FILTROS, $this->input->get());

        foreach (['de', 'ate'] as $data) {
            if (isset($filtros[$data])) {
                $valida = dataIsoParaYmd($filtros[$data]);
                if ($valida === null) {
                    unset($filtros[$data]);
                } else {
                    $filtros[$data] = $valida;
                }
            }
        }

        return $filtros;
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar Vendas.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('vendas') == false) {
            $this->data['custom_error'] = (validation_errors() ? true : false);
        } else {
            $dataVenda = $this->normalizarDataVenda($this->input->post('dataVenda'));
            if ($dataVenda === null) {
                $this->data['custom_error'] = '<div class="form_error"><p>Data da venda inválida. Informe uma data válida.</p></div>';
            } else {
                $data = [
                    'dataVenda' => $dataVenda,
                    'observacoes' => $this->input->post('observacoes'),
                    'observacoes_cliente' => $this->input->post('observacoes_cliente'),
                    'clientes_id' => $this->input->post('clientes_id'),
                    'usuarios_id' => $this->input->post('usuarios_id'),
                    'faturado' => 0,
                    'status' => $this->input->post('status'),
                    'garantia' => $this->input->post('garantia')
                ];

                $id = $this->vendas_model->add('vendas', $data, true);

                if (is_numeric($id)) {
                    $this->session->set_flashdata('success', 'Venda iniciada com sucesso, adicione os produtos.');
                    log_info('Adicionou uma venda. ID: ' . $id);
                    redirect(site_url('vendas/editar/') . $id);
                } else {
                    $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro.</p></div>';
                }
            }
        }

        $this->data['venda'] = null;
        $this->data['valores'] = vendaValoresDoFormulario($this->input->method() === 'post' ? $this->input->post() : null, null);
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['pode'] = [
            'adicionar' => $this->permite('aVenda'),
            'editar' => $this->permite('eVenda'),
            'excluir' => $this->permite('dVenda'),
            'ver_venda' => $this->permite('vVenda'),
            'cadastrar_cliente' => $this->permite('aCliente'),
        ];
        $this->data['formatados'] = [];
        $this->data['view'] = 'vendas/formulario';

        return $this->layout();
    }

    public function editar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3)) || ! $this->vendas_model->getById($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Venda não encontrada ou parâmetro inválido.');
            redirect('vendas/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar vendas');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        $this->data['editavel'] = $this->vendas_model->isEditable($this->input->post('idVendas'));
        if (! $this->data['editavel']) {
            $this->session->set_flashdata('error', 'Essa Venda já tem seu status Faturada e não pode ser alterado e nem suas informações atualizadas. Por favor abrir uma nova Venda.');

            redirect(site_url('vendas'));
        }

        if ($this->form_validation->run('vendas') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $dataVenda = $this->normalizarDataVenda($this->input->post('dataVenda'));
            if ($dataVenda === null) {
                $this->data['custom_error'] = '<div class="form_error"><p>Data da venda inválida. Informe uma data válida.</p></div>';
            } else {
                $data = [
                    'dataVenda' => $dataVenda,
                    'observacoes' => $this->input->post('observacoes'),
                    'observacoes_cliente' => $this->input->post('observacoes_cliente'),
                    'usuarios_id' => $this->input->post('usuarios_id'),
                    'clientes_id' => $this->input->post('clientes_id'),
                    'status' => $this->input->post('status'),
                    'garantia' => $this->input->post('garantia')
                ];

                if ($this->vendas_model->edit('vendas', $data, 'idVendas', $this->input->post('idVendas')) == true) {
                    $this->session->set_flashdata('success', 'Venda editada com sucesso!');
                    log_info('Alterou uma venda. ID: ' . $this->input->post('idVendas'));
                    redirect(site_url('vendas/editar/') . $this->input->post('idVendas'));
                } else {
                    $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro</p></div>';
                }
            }
        }

        $venda = $this->vendas_model->getById($this->uri->segment(3));
        $this->data['result'] = $venda;
        $this->data['venda'] = $venda;
        $this->data['produtos'] = $this->vendas_model->getProdutos($this->uri->segment(3));
        $this->data['valores'] = vendaValoresDoFormulario($this->input->method() === 'post' ? $this->input->post() : null, $venda);
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['pode'] = [
            'adicionar' => $this->permite('aVenda'),
            'editar' => $this->permite('eVenda'),
            'excluir' => $this->permite('dVenda'),
            'ver_venda' => $this->permite('vVenda'),
            'cadastrar_cliente' => $this->permite('aCliente'),
        ];
        $this->data['formatados'] = [];
        $this->data['view'] = 'vendas/formulario';

        return $this->layout();
    }

    public function visualizar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar vendas.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->load->model('mapos_model');
        $this->data['result'] = $this->vendaOuRedirecionar($this->uri->segment(3));
        $this->data['produtos'] = $this->vendas_model->getProdutos($this->uri->segment(3));
        $this->data['emitente'] = $this->mapos_model->getEmitente();
        $this->data['qrCode'] = $this->vendas_model->getQrCode(
            $this->uri->segment(3),
            $this->data['configuration']['pix_key'],
            $this->data['emitente']
        );
        $this->data['chaveFormatada'] = $this->formatarChave($this->data['configuration']['pix_key']);
        $this->data['modalGerarPagamento'] = $this->load->view(
            'cobrancas/modalGerarPagamento',
            [
                'id' => $this->uri->segment(3),
                'tipo' => 'venda',
            ],
            true
        );

        $clienteId = $this->data['result']->clientes_id;
        $this->load->model('clientes_model');
        $cliente = $this->clientes_model->getById($clienteId);

        $zapnumber = preg_replace('/[^0-9]/', '', $cliente->telefone ?? '');
        $this->data['zapnumber'] = $zapnumber;
        $this->data['view'] = 'vendas/visualizarVenda';

        return $this->layout();
    }

    public function imprimir()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar vendas.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->load->model('mapos_model');
        $this->data['result'] = $this->vendaOuRedirecionar($this->uri->segment(3));
        $this->data['produtos'] = $this->vendas_model->getProdutos($this->uri->segment(3));
        $this->data['emitente'] = $this->mapos_model->getEmitente();
        $this->data['qrCode'] = $this->vendas_model->getQrCode(
            $this->uri->segment(3),
            $this->data['configuration']['pix_key'],
            $this->data['emitente']
        );
        $this->data['chaveFormatada'] = $this->formatarChave($this->data['configuration']['pix_key']);

        $this->load->view('vendas/imprimirVenda', $this->data);
    }

    public function imprimirTermica()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar vendas.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->load->model('mapos_model');
        $this->data['result'] = $this->vendaOuRedirecionar($this->uri->segment(3));
        $this->data['produtos'] = $this->vendas_model->getProdutos($this->uri->segment(3));
        $this->data['emitente'] = $this->mapos_model->getEmitente();
        $this->data['qrCode'] = $this->vendas_model->getQrCode(
            $this->uri->segment(3),
            $this->data['configuration']['pix_key'],
            $this->data['emitente']
        );
        
        $this->data['chaveFormatada'] = $this->formatarChave($this->data['configuration']['pix_key']);

        $this->load->view('vendas/imprimirVendaTermica', $this->data);
    }

    public function imprimirVendaOrcamento()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar vendas.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->load->model('mapos_model');
        $this->data['result'] = $this->vendaOuRedirecionar($this->uri->segment(3));
        $this->data['produtos'] = $this->vendas_model->getProdutos($this->uri->segment(3));
        $this->data['emitente'] = $this->mapos_model->getEmitente();
        $this->data['qrCode'] = $this->vendas_model->getQrCode(
            $this->uri->segment(3),
            $this->data['configuration']['pix_key'],
            $this->data['emitente']
        );
        
        $this->data['chaveFormatada'] = $this->formatarChave($this->data['configuration']['pix_key']);
        $this->load->view('vendas/imprimirVendaOrcamento', $this->data);
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir vendas');
            redirect(base_url());
        }

        $this->load->model('vendas_model');

        $id = $this->input->post('id');

        $editavel = $this->vendas_model->isEditable($id);
        if (! $editavel) {
            $this->session->set_flashdata('error', 'Erro ao tentar excluir. Venda já faturada');
            redirect(site_url('vendas/gerenciar/'));
        }

        $venda = $this->vendas_model->getByIdCobrancas($id);
        if ($venda == null) {
            $venda = $this->vendas_model->getById($id);
            if ($venda == null) {
                $this->session->set_flashdata('error', 'Erro ao tentar excluir venda.');
                redirect(site_url('vendas/gerenciar/'));
            }
        }

        if (isset($venda->idCobranca) != null) {
            if ($venda->status == 'canceled') {
                $this->vendas_model->delete('cobrancas', 'vendas_id', $id);
            } else {
                $this->session->set_flashdata('error', 'Existe uma cobrança associada a esta venda, deve cancelar e/ou excluir a cobrança primeiro!');
                redirect(site_url('vendas/gerenciar/'));
            }
        }

        if ((int) $venda->faturado === 1) {
            $this->db->set('lancamentos_id', null);
            $this->db->where('idVendas', $id);
            $this->db->update('vendas');

            $this->vendas_model->delete('lancamentos', 'vendas_id', $id);
        }
        $this->vendas_model->delete('itens_de_vendas', 'vendas_id', $id);
        $this->vendas_model->delete('vendas', 'idVendas', $id);

        log_info('Removeu uma venda. ID: ' . $id);

        $this->session->set_flashdata('success', 'Venda excluída com sucesso!');
        redirect(site_url('vendas/gerenciar/'));
    }

    public function autoCompleteProduto()
    {
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            $this->vendas_model->autoCompleteProduto($q);
        }
    }

    public function autoCompleteCliente()
    {
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            $this->vendas_model->autoCompleteCliente($q);
        }
    }

    public function autoCompleteUsuario()
    {
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            $this->vendas_model->autoCompleteUsuario($q);
        }
    }

    public function adicionarProduto()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar vendas.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules('quantidade', 'Quantidade', 'trim|required');
        $this->form_validation->set_rules('idProduto', 'Produto', 'trim|required');
        $this->form_validation->set_rules('idVendasProduto', 'Vendas', 'trim|required');

        $idVenda = $this->input->post('idVendasProduto');
        $editavel = $this->vendas_model->isEditable($idVenda);
        if (!$editavel) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(422)
                ->set_output(json_encode(['result' => false, 'messages' => '<br /><br /> <strong>Motivo:</strong> Venda já faturada']));
        }

        if ($this->form_validation->run() == false) {
            echo json_encode(['result' => false]);
        } else {
            $preco = $this->input->post('preco');
            $quantidade = $this->input->post('quantidade');
            $subtotal = $preco * $quantidade;
            $produto = $this->input->post('idProduto');

            $this->load->model('produtos_model');
            $produtoData = $this->produtos_model->getById($produto);
            $custoUnitario = $produtoData && isset($produtoData->precoCompra) ? (float) $produtoData->precoCompra : 0.0;
            $custoTotal = $custoUnitario * (float) $quantidade;

            $data = [
                'quantidade' => $quantidade,
                'subTotal' => $subtotal,
                'produtos_id' => $produto,
                'preco' => $preco,
                'custo_unitario' => $custoUnitario,
                'custo_total' => $custoTotal,
                'vendas_id' => $idVenda,
            ];

            if ($this->vendas_model->add('itens_de_vendas', $data) == true) {
                if ($this->data['configuration']['control_estoque']) {
                    $this->produtos_model->updateEstoque($produto, $quantidade, '-');
                }

                // Atualiza o desconto da venda
                $this->db->set('desconto', 0.00);
                $this->db->set('valor_desconto', 0.00);
                $this->db->set('tipo_desconto', null);
                $this->db->where('idVendas', $idVenda);
                $this->db->update('vendas');

                // Registra a ação nos logs com o ID da venda
                log_info('Adicionou produto à venda com ID: ' . $idVenda);

                echo json_encode(['result' => true]);
            } else {
                echo json_encode(['result' => false]);
            }
        }
    }

    public function excluirProduto()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar Vendas.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules('idProduto', 'Produto', 'trim|required');
        $this->form_validation->set_rules('idVendas', 'Venda', 'trim|required');
        $this->form_validation->set_rules('quantidade', 'Quantidade', 'trim|required');
        $this->form_validation->set_rules('produto', 'Produto', 'trim|required');

        if ($this->form_validation->run() == false) {
            echo json_encode(['result' => false, 'messages' => 'Dados inválidos']);
            return;
        }

        $idProduto = $this->input->post('idProduto');
        $idVendas = $this->input->post('idVendas');
        $quantidade = $this->input->post('quantidade');
        $produto = $this->input->post('produto');

        $editavel = $this->vendas_model->isEditable($idVendas);
        if (!$editavel) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(422)
                ->set_output(json_encode(['result' => false, 'messages' => '<br /><br /> <strong>Motivo:</strong> Venda já faturada']));
        }

        $this->db->trans_start();

        $this->vendas_model->delete('itens_de_vendas', 'idItens', $idProduto);

        if ($this->data['configuration']['control_estoque']) {
            $this->load->model('produtos_model');
            $this->produtos_model->updateEstoque($produto, $quantidade, '+');
        }

        $this->db->set('desconto', 0.00);
        $this->db->set('valor_desconto', 0.00);
        $this->db->set('tipo_desconto', null);
        $this->db->where('idVendas', $idVendas);
        $this->db->update('vendas');

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            echo json_encode(['result' => false, 'messages' => 'Erro ao excluir o produto']);
        } else {
            $this->db->trans_complete();
            log_info('Removeu produto da venda. ID da Venda: ' . $idVendas . ', ID do Produto: ' . $idProduto);
            echo json_encode(['result' => true, 'messages' => 'Produto removido com sucesso']);
        }
    }

    public function adicionarDesconto()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar Vendas.');
            redirect(base_url());
        }

        if ($this->input->post('desconto') == '') {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(400)
                ->set_output(json_encode(['messages' => 'Campo desconto vazio']));
        } else {
            $idVendas = $this->input->post('idVendas');
            $data = [
                'desconto' => $this->input->post('desconto'),
                'tipo_desconto' => $this->input->post('tipoDesconto'),
                'valor_desconto' => $this->input->post('resultado'),
            ];
            $editavel = $this->vendas_model->isEditable($idVendas);
            if (! $editavel) {
                return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(400)
                    ->set_output(json_encode(['result' => false, 'messages', 'Desconto não pode ser adiciona. Venda não ja Faturada/Cancelada']));
            }
            if ($this->vendas_model->edit('vendas', $data, 'idVendas', $idVendas) == true) {
                log_info('Adicionou um desconto na Venda. ID: ' . $idVendas);

                return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(201)
                    ->set_output(json_encode(['result' => true, 'messages' => 'Desconto adicionado com sucesso!']));
            } else {
                log_info('Ocorreu um erro ao tentar adiciona desconto a Venda: ' . $idVendas);

                return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(400)
                    ->set_output(json_encode(['result' => false, 'messages', 'Ocorreu um erro ao tentar adiciona desconto a Venda.']));
            }
        }

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(400)
            ->set_output(json_encode(['result' => false, 'messages', 'Ocorreu um erro ao tentar adiciona desconto a OS.']));
    }

    public function faturar()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar Vendas');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('receita') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $venda_id = $this->input->post('vendas_id');
            $vencimento = $this->input->post('vencimento');
            $recebimento = $this->input->post('recebimento');

            try {
                $vencimento = explode('/', $vencimento);
                $vencimento = $vencimento[2] . '-' . $vencimento[1] . '-' . $vencimento[0];

                if ($recebimento != null) {
                    $recebimento = explode('/', $recebimento);
                    $recebimento = $recebimento[2] . '-' . $recebimento[1] . '-' . $recebimento[0];
                }
            } catch (Exception $e) {
                $vencimento = date('Y-m-d');
            }

            // Guarda transacional contra faturamento duplicado: trava a linha
            // da venda (SELECT ... FOR UPDATE) antes de validar e faturar.
            // Mesmo contrato de resposta (JSON {result: bool}); sucesso só após commit.
            $this->db->trans_start();

            $travada = $this->db->query('SELECT idVendas, faturado, status FROM vendas WHERE idVendas = ? FOR UPDATE', [(int) $venda_id])->row();

            if ($travada === null) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Venda não encontrada.');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['result' => false, 'messages' => 'Venda não encontrada.']);
                exit();
            }

            if ((int) $travada->faturado === 1) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Venda já faturada. Nenhuma nova receita foi criada.');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['result' => false, 'messages' => 'Venda já faturada. Nenhuma nova receita foi criada.']);
                exit();
            }

            if (is_string($travada->status) && strcasecmp(trim($travada->status), 'Cancelado') === 0) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Venda cancelada não pode ser faturada.');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['result' => false, 'messages' => 'Venda cancelada não pode ser faturada.']);
                exit();
            }

            $vendas = $this->vendas_model->getById($venda_id);

            if ($vendas === null || $vendas === false) {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Venda não encontrada.');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['result' => false, 'messages' => 'Venda não encontrada.']);
                exit();
            }

            $valorTotal = getAmount($this->input->post('valor'));
            $tipoDesconto = strtolower(trim((string) $vendas->tipo_desconto));
            $valorDesconto = (float) $vendas->desconto;

            if (in_array($tipoDesconto, ['porcento', 'percentual', 'percent', '%'], true)) {
                $valorDesconto = $valorTotal * ($valorDesconto / 100);
            } elseif ($tipoDesconto === 'real') {
                $valorDesconto = $valorDesconto;
            } else {
                $valorDesconto = 0;
            }

            $valorDesconto = min($valorTotal, $valorDesconto);
            $valorComDesconto = $valorTotal - $valorDesconto;

            $data = [
                'vendas_id' => $venda_id,
                'descricao' => set_value('descricao'),
                'valor' => $valorTotal,
                'desconto' => $valorDesconto,
                'tipo_desconto' => 'real',
                'valor_desconto' => $valorComDesconto,
                'clientes_id' => $this->input->post('clientes_id'),
                'data_vencimento' => $vencimento,
                'data_pagamento' => $recebimento,
                'baixado' => $this->input->post('recebido') == 1 ? true : false,
                'cliente_fornecedor' => set_value('cliente'),
                'forma_pgto' => $this->input->post('formaPgto'),
                'tipo' => 'receita',
                'usuarios_id' => $this->session->userdata('id_admin'),
            ];

            $inserido = $this->db->insert('lancamentos', $data);
            $idLancamentos = $inserido ? (int) $this->db->insert_id() : 0;

            if ($idLancamentos) {
                $this->db->set('faturado', 1);
                $this->db->set('valorTotal', $valorTotal);
                $this->db->set('desconto', $vendas->desconto);
                $this->db->set('valor_desconto', $valorComDesconto);
                $this->db->set('lancamentos_id', $idLancamentos);
                $this->db->set('status', 'Faturado');
                $this->db->where('idVendas', (int) $venda_id);
                $this->db->where('faturado', 0);
                $atualizacaoOk = $this->db->update('vendas');
                $atualizadas = $this->db->affected_rows();

                if ($atualizacaoOk === false || $atualizadas !== 1) {
                    $this->db->trans_rollback();
                    $this->session->set_flashdata('error', 'Venda já faturada. Nenhuma nova receita foi criada.');
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['result' => false, 'messages' => 'Venda já faturada. Nenhuma nova receita foi criada.']);
                    exit();
                }

                log_info('Faturou a venda com ID.' . $venda_id);

                $this->db->trans_complete();

                if ($this->db->trans_status() === false) {
                    $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar faturar venda.');
                    $json = ['result' => false];
                } else {
                    $this->session->set_flashdata('success', 'Venda faturada com sucesso!');
                    $json = ['result' => true];
                }
            } else {
                $this->db->trans_rollback();
                $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar faturar venda.');
                $json = ['result' => false];
            }

            echo json_encode($json);
            exit();
        }

        $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar faturar venda.');
        $json = ['result' => false];
        echo json_encode($json);
    }

    public function validarCPF($cpf)
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1+$/', $cpf)) {
            return false;
        }
        $soma1 = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma1 += $cpf[$i] * (10 - $i);
        }
        $resto1 = $soma1 % 11;
        $dv1 = ($resto1 < 2) ? 0 : 11 - $resto1;
        if ($dv1 != $cpf[9]) {
            return false;
        }
        $soma2 = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma2 += $cpf[$i] * (11 - $i);
        }
        $resto2 = $soma2 % 11;
        $dv2 = ($resto2 < 2) ? 0 : 11 - $resto2;
    
        return $dv2 == $cpf[10];
    }
    
    public function validarCNPJ($cnpj)
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1+$/', $cnpj)) {
            return false;
        }
        $soma1 = 0;
        for ($i = 0, $pos = 5; $i < 12; $i++, $pos--) {
            $pos = ($pos < 2) ? 9 : $pos;
            $soma1 += $cnpj[$i] * $pos;
        }
        $dv1 = ($soma1 % 11 < 2) ? 0 : 11 - ($soma1 % 11);
        if ($dv1 != $cnpj[12]) {
            return false;
        }
        $soma2 = 0;
        for ($i = 0, $pos = 6; $i < 13; $i++, $pos--) {
            $pos = ($pos < 2) ? 9 : $pos;
            $soma2 += $cnpj[$i] * $pos;
        }
        $dv2 = ($soma2 % 11 < 2) ? 0 : 11 - ($soma2 % 11);
    
        return $dv2 == $cnpj[13];
    }
    
    public function formatarChave($chave)
    {
        if ($this->validarCPF($chave)) {
            return substr($chave, 0, 3) . '.' . substr($chave, 3, 3) . '.' . substr($chave, 6, 3) . '-' . substr($chave, 9);
        } elseif ($this->validarCNPJ($chave)) {
            return substr($chave, 0, 2) . '.' . substr($chave, 2, 3) . '.' . substr($chave, 5, 3) . '/' . substr($chave, 8, 4) . '-' . substr($chave, 12);
        } elseif (strlen($chave) === 11) {
            return '(' . substr($chave, 0, 2) . ') ' . substr($chave, 2, 5) . '-' . substr($chave, 7);
        }
        return $chave;
    }

    public function visualizarVenda($id)
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'vVenda')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar Vendas.');
            redirect(base_url());
        }

        redirect('vendas/visualizar/' . rawurlencode((string) $id));
    }

}
