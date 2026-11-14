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

        [$vendedorPadrao, $vendedorIdPadrao] = $this->vendedorPadrao();
        $this->data['venda'] = null;
        $this->data['valores'] = vendaValoresDoFormulario(
            $this->input->method() === 'post' ? $this->input->post() : null,
            null,
            [
                'vendedor' => $vendedorPadrao,
                'usuarios_id' => $vendedorIdPadrao,
                'dataVenda' => date('Y-m-d'),
            ]
        );
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

        $idVendaRota = (int) $this->uri->segment(3);
        $idVendaPost = (int) $this->input->post('idVendas');
        $idVendaAtual = $idVendaPost > 0 ? $idVendaPost : $idVendaRota;
        $this->data['editavel'] = $idVendaAtual > 0 && $this->vendas_model->isEditable($idVendaAtual);
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
                $idVenda = $idVendaAtual;
                $data = [
                    'dataVenda' => $dataVenda,
                    'observacoes' => $this->input->post('observacoes'),
                    'observacoes_cliente' => $this->input->post('observacoes_cliente'),
                    'usuarios_id' => $this->input->post('usuarios_id'),
                    'clientes_id' => $this->input->post('clientes_id'),
                    'status' => $this->input->post('status'),
                    'garantia' => $this->input->post('garantia')
                ];

                $transacaoOk = $this->db->trans_begin();
                $vendaBloqueada = $transacaoOk
                    ? $this->db->query('SELECT idVendas, status, faturado FROM vendas WHERE idVendas = ? FOR UPDATE', [$idVenda])->row()
                    : null;
                $estoqueOk = $vendaBloqueada !== null;

                if ($vendaBloqueada) {
                    $statusAnteriorCancelado = strcasecmp(trim((string) $vendaBloqueada->status), 'Cancelado') === 0;
                    $statusNovoCancelado = strcasecmp(trim((string) $data['status']), 'Cancelado') === 0;
                    if ($statusAnteriorCancelado !== $statusNovoCancelado && ($this->data['configuration']['control_estoque'] ?? false)) {
                        $itens = $this->vendas_model->getItensParaMovimentarEstoque($idVenda);
                        $estoqueOk = $this->vendas_model->movimentarEstoqueVenda($itens, $statusNovoCancelado ? '+' : '-');
                    }
                    $atualizada = $this->db->where('idVendas', $idVenda)->update('vendas', $data);
                    $transacaoOk = $transacaoOk && $atualizada && $estoqueOk && $this->db->trans_status() !== false;
                } else {
                    $transacaoOk = false;
                }

                if (! $transacaoOk || ! $this->db->trans_commit()) {
                    $this->db->trans_rollback();
                    $this->data['custom_error'] = '<div class="form_error"><p>Não foi possível atualizar a venda com segurança. Nenhuma alteração foi confirmada.</p></div>';
                } else {
                    $this->session->set_flashdata('success', 'Venda editada com sucesso!');
                    log_info('Alterou uma venda. ID: ' . $idVenda);
                    redirect(site_url('vendas/editar/') . $idVenda);
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

        $venda = $this->vendas_model->getById((int) $this->uri->segment(3));
        if ($venda === null) {
            $this->session->set_flashdata('error', 'Venda não encontrada ou parâmetro inválido.');
            redirect('vendas/gerenciar');

            return null;
        }

        $this->data = array_merge($this->data, $this->dadosDaTela($venda));
        $this->data['result'] = $venda;
        if (! isset($this->data['emitente'])) {
            $this->load->model('mapos_model');
            $this->data['emitente'] = $this->mapos_model->getEmitente();
        }
        $this->data['qrCode'] = $this->vendas_model->getQrCode(
            (int) $this->uri->segment(3),
            $this->data['configuration']['pix_key'] ?? null,
            $this->data['emitente']
        );
        $this->data['chaveFormatada'] = $this->formatarChave($this->data['configuration']['pix_key'] ?? null);
        $this->data['modalGerarPagamento'] = $this->load->view(
            'cobrancas/modalGerarPagamento',
            [
                'id' => (int) $this->uri->segment(3),
                'tipo' => 'venda',
            ],
            true
        );
        $this->data['clienteId'] = (int) $venda->clientes_id;

        $this->load->model('clientes_model');
        $cliente = $this->clientes_model->getById($venda->clientes_id);

        $this->data['cliente'] = $cliente;
        $this->data['zapnumber'] = preg_replace('/[^0-9]/', '', $cliente->telefone ?? '');
        $this->data['idVenda'] = (int) $venda->idVendas;
        $this->data['gateways'] = [];
        if ($this->data['pode']['cobrar'] ?? false) {
            $this->load->config('payment_gateways');
            $this->data['gateways'] = osOpcoesDeCobranca($this->config->item('payment_gateways'));
        }
        $this->data['chave'] = $this->data['chaveFormatada'];
        $this->data['cancelada'] = $venda->status === 'Cancelado';
        $this->data['fechada'] = (int) $venda->faturado === 1 || $venda->status === 'Faturado' || $this->data['cancelada'];
        if (($this->data['pode']['editar'] ?? false)) {
            $this->data['topbar_acao'] = ['label' => 'Editar venda', 'icon' => 'pencil', 'href' => site_url('vendas/editar/' . $this->data['idVenda'])];
        }

        $this->data['view'] = 'vendas/visualizar';

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
        $id = (int) $this->input->post('id');
        if ($id <= 0) {
            $this->session->set_flashdata('error', 'Venda inválida.');
            redirect(site_url('vendas/gerenciar/'));
        }

        if (! $this->db->trans_begin()) {
            $this->session->set_flashdata('error', 'Não foi possível iniciar a exclusão com segurança.');
            redirect(site_url('vendas/gerenciar/'));
        }

        $venda = $this->db->query(
            'SELECT idVendas, status, faturado, lancamentos_id FROM vendas WHERE idVendas = ? FOR UPDATE',
            [$id]
        )->row();
        $erro = null;

        if (! $venda) {
            $erro = 'Erro ao tentar excluir venda.';
        } elseif ((int) $venda->faturado === 1 && ($this->data['configuration']['control_edit_vendas'] ?? '0') !== '1') {
            $erro = 'Erro ao tentar excluir. Venda já faturada';
        }

        $cobrancas = $erro === null
            ? $this->db->query('SELECT idCobranca FROM cobrancas WHERE vendas_id = ? FOR UPDATE', [$id])->result()
            : [];
        if ($erro === null && $cobrancas !== []) {
            $erro = 'Existe uma cobrança associada a esta venda, deve cancelar e/ou excluir a cobrança primeiro!';
        }

        $lancamento = null;
        if ($erro === null && (int) $venda->faturado === 1 && $venda->lancamentos_id) {
            $lancamento = $this->db->query(
                'SELECT idLancamentos, baixado FROM lancamentos WHERE idLancamentos = ? FOR UPDATE',
                [(int) $venda->lancamentos_id]
            )->row();
            if ($lancamento && (int) $lancamento->baixado === 1) {
                $erro = 'A venda possui recebimento confirmado e não pode ser excluída automaticamente.';
            }
        }

        if ($erro === null) {
            $itens = $this->vendas_model->getItensParaMovimentarEstoque($id);
            $cancelada = strcasecmp(trim((string) $venda->status), 'Cancelado') === 0;
            $estoqueOk = ! ($this->data['configuration']['control_estoque'] ?? false)
                || $cancelada
                || $this->vendas_model->movimentarEstoqueVenda($itens, '+');
            $itensExcluidos = $this->db->where('vendas_id', $id)->delete('itens_de_vendas');
            $financeiroOk = true;
            if ($lancamento) {
                $this->db->set('lancamentos_id', null)->where('idVendas', $id)->update('vendas');
                $financeiroOk = $this->db->trans_status() !== false
                    && $this->db->where('idLancamentos', (int) $lancamento->idLancamentos)->delete('lancamentos');
            }
            $vendaExcluida = $this->db->where('idVendas', $id)->delete('vendas');
            if (! $estoqueOk || $itensExcluidos === false || ! $financeiroOk || $vendaExcluida === false || $this->db->trans_status() === false) {
                $erro = 'Não foi possível excluir a venda com segurança. Nenhuma alteração foi confirmada.';
            }
        }

        if ($erro !== null || ! $this->db->trans_commit()) {
            $this->db->trans_rollback();
            $this->session->set_flashdata('error', $erro ?? 'Não foi possível confirmar a exclusão.');
            redirect(site_url('vendas/gerenciar/'));
        }

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

    /**
     * Tela da venda (#2843): tudo o que a view moderna e os trechos
     * (views/vendas/partes/*) usam. Os endpoints devolvem os trechos
     * renderizados de novo depois de gravar, sem recarregar a página.
     */
    private function dadosDaTela(object $venda): array
    {
        $idVenda = (int) $venda->idVendas;
        $this->load->model('mapos_model');
        $emitente = $this->mapos_model->getEmitente();
        $configuracao = $this->data['configuration'];

        $totais = $this->vendas_model->totais($venda);
        $editavel = $this->permite('eVenda') && $this->vendas_model->isEditable($idVenda);
        $cobrancas = $this->vendas_model->getCobrancas($idVenda);

        $pixPayload = $this->vendas_model->getPixPayload($idVenda, $configuracao['pix_key'] ?? null, $emitente);
        $telefone = osTelefoneWhatsApp($venda->celular ?: $venda->telefone);

        return [
            'venda' => $venda,
            'totais' => $totais,
            'produtos' => $this->vendas_model->getProdutos($idVenda),
            'cobranca' => $cobrancas[0] ?? null,
            'emitente' => $emitente,
            'controle_estoque' => (bool) ($configuracao['control_estoque'] ?? false),
            'pix' => $pixPayload === null ? null : [
                'payload' => $pixPayload,
                'qr' => $this->vendas_model->getQrCode($idVenda, $configuracao['pix_key'] ?? null, $emitente),
                'chave' => $this->formatarChave($configuracao['pix_key'] ?? ''),
            ],
            'whatsapp' => $telefone === null ? null : ['telefone' => $telefone],
            'garantia_ate' => vendaGarantiaAte($venda->dataVenda, $venda->garantia),
            'pode' => [
                'editar' => $editavel,
                'itens' => $editavel && $venda->status !== 'Cancelado',
                'faturar' => $editavel && (int) $venda->faturado === 0 && $venda->status !== 'Cancelado',
                'excluir' => $this->permite('dVenda') && $this->vendas_model->isEditable($idVenda),
                'cobrar' => $this->permite('aCobranca') && ! $cobrancas && $totais['total'] > 0,
                'ver_cobranca' => $this->permite('vCobranca'),
                'ver_cliente' => $this->permite('vCliente'),
            ],
        ];
    }

    /** Trechos da tela renderizados de novo, para os endpoints AJAX. */
    private function partesDaTela(int $idVenda, array $partes): array
    {
        $venda = $this->vendas_model->getById($idVenda);
        if ($venda === null) {
            return [];
        }

        $dados = $this->dadosDaTela($venda);

        $html = [];
        foreach ($partes as $parte) {
            $html[$parte] = $this->load->view('vendas/partes/' . $parte, $dados, true);
        }

        return $html;
    }

    /** Resposta JSON dos endpoints da tela da venda. */
    private function responderJson(int $status, array $corpo): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo));
    }

    /**
     * Venda do POST (idVendas) que pode ser alterada, ou null com a resposta de
     * erro (404/403) já montada. $mexeNosItens recusa também venda cancelada.
     */
    private function vendaParaAlterar(bool $mexeNosItens = false): ?object
    {
        $id = (int) $this->input->post('idVendas');
        $venda = $id > 0 ? $this->vendas_model->getById($id) : null;

        if (! $venda) {
            $this->responderJson(404, ['result' => false, 'message' => 'Venda não encontrada. Recarregue a página.']);

            return null;
        }

        if (! $this->vendas_model->isEditable($id)) {
            $this->responderJson(403, ['result' => false, 'message' => 'Esta venda está faturada ou cancelada e não pode mais ser alterada.']);

            return null;
        }

        if ($mexeNosItens && $venda->status === 'Cancelado') {
            $this->responderJson(403, ['result' => false, 'message' => 'Esta venda está cancelada: reabra a venda (status) para mexer nos produtos.']);

            return null;
        }

        return $venda;
    }

    /** Resposta de sucesso com os trechos que a mudança afeta. */
    private function responderTrechos(int $idVenda, string $tipo, string $mensagem, int $status = 200, array $extras = []): void
    {
        $partes = match ($tipo) {
            'produto' => ['produtos', 'totais', 'desconto', 'pix', 'faturar'],
            'desconto' => ['totais', 'desconto', 'pix', 'faturar'],
            default => [],
        };

        $venda = $this->vendas_model->getById($idVenda);

        $this->responderJson($status, [
            'result' => $status < 400,
            'message' => $mensagem,
            'html' => $this->partesDaTela($idVenda, $partes),
            'totais' => $venda === null ? null : $this->vendas_model->totais($venda),
        ] + $extras);
    }

    /** 422 de instalação sem a migration do desconto por item (#2880). */
    private function responderDescontoPorItemIndisponivel(): void
    {
        $mensagem = 'O desconto por item precisa da atualização do banco de dados (Configurações > Atualizar banco).';
        $this->responderJson(422, ['result' => false, 'message' => $mensagem, 'erros' => ['_geral' => $mensagem]]);
    }

    /**
     * Produto novo na venda: id do cadastro conferido no banco, estoque baixado
     * quando o controle está ligado, e desconto da venda removido (o total muda).
     */
    public function adicionarProduto()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->responderJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar vendas.']);

            return;
        }

        $venda = $this->vendaParaAlterar(true);
        if (! $venda) {
            return;
        }

        // Politica comercial de vendas: novos descontos por item nao sao mais
        // aceitos; desconto geral, sim. Zeros explicitos passam (equivalem a
        // "sem desconto"); qualquer valor nao-zero, mesmo vindo de cliente
        // adulterado, e recusado aqui no servidor.
        $descontoItem = trim((string) $this->input->post('descontoItem'));
        if ($descontoItem !== '') {
            $descontoItemValor = valorDecimal($descontoItem);
            if ($descontoItemValor === null || abs((float) $descontoItemValor) > 0.0) {
                $mensagem = 'O desconto por item não é mais aplicado a vendas. Use o desconto geral.';
                $this->responderJson(422, ['result' => false, 'message' => $mensagem, 'erros' => ['descontoItem' => $mensagem]]);

                return;
            }
        }

        $idCadastro = (int) $this->input->post('idProduto');
        $cadastro = $idCadastro > 0 ? $this->vendas_model->getProdutoCadastro($idCadastro) : null;
        $controleEstoque = (bool) ($this->data['configuration']['control_estoque'] ?? false);

        [$dados, $erros] = osItemDoFormulario($this->input->post(), 'produto', $cadastro, $controleEstoque);
        if ($erros !== []) {
            $this->responderJson(422, ['result' => false, 'message' => 'Confira os campos destacados.', 'erros' => $erros]);

            return;
        }

        $idVenda = (int) $venda->idVendas;
        if (isset($dados['desconto']) && ! $this->vendas_model->descontoPorItemDisponivel()) {
            $this->responderDescontoPorItemIndisponivel();

            return;
        }

        $this->db->trans_start();

        if (! $this->vendas_model->add('itens_de_vendas', $dados + ['vendas_id' => $idVenda])) {
            $this->db->trans_rollback();
            $this->responderJson(500, ['result' => false, 'message' => 'Não foi possível adicionar. Tente de novo.']);

            return;
        }

        if ($controleEstoque && ! $this->vendas_model->movimentarEstoqueVenda([(object) $dados], '-')) {
            $this->db->trans_rollback();
            $this->responderJson(409, ['result' => false, 'message' => 'Estoque insuficiente ou indisponível. Nenhuma alteração foi confirmada.']);

            return;
        }

        $tinhaDesconto = (float) $venda->valor_desconto > 0;
        $this->vendas_model->zerarDesconto($idVenda);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            $this->responderJson(500, ['result' => false, 'message' => 'Não foi possível adicionar. Tente de novo.']);

            return;
        }

        $this->db->trans_complete();

        log_info('Adicionou produto à venda com ID: ' . $idVenda);

        $descricao = $cadastro->descricao ?? 'Produto';
        $this->responderTrechos($idVenda, 'produto', $descricao . ' adicionado à venda.' . ($tinhaDesconto ? ' O desconto foi removido porque o total mudou.' : ''));
    }

    /**
     * Tira um produto da venda. Item, quantidade e produto vêm do banco (na v4
     * a quantidade a devolver ao estoque vinha do navegador).
     */
    public function excluirProduto()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->responderJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar vendas.']);

            return;
        }

        $venda = $this->vendaParaAlterar(true);
        if (! $venda) {
            return;
        }

        $idVenda = (int) $venda->idVendas;
        $idItem = (int) $this->input->post('idItem');
        $item = $idItem > 0 ? $this->vendas_model->getProdutoDaVenda($idVenda, $idItem) : null;

        if (! $item) {
            $this->responderJson(404, ['result' => false, 'message' => 'Produto não encontrado nesta venda. Recarregue a página.']);

            return;
        }

        $this->db->trans_start();

        $vendaBloqueada = $this->db->query('SELECT idVendas FROM vendas WHERE idVendas = ? FOR UPDATE', [$idVenda])->row();
        $itemBloqueado = $this->db->query(
            'SELECT idItens, produtos_id, quantidade FROM itens_de_vendas WHERE idItens = ? AND vendas_id = ? FOR UPDATE',
            [$idItem, $idVenda]
        )->row();
        if (! $vendaBloqueada || ! $itemBloqueado || ! $this->vendas_model->delete('itens_de_vendas', 'idItens', $idItem)) {
            $this->db->trans_rollback();
            $this->responderJson(409, ['result' => false, 'message' => 'O produto já foi removido ou a venda mudou. Recarregue a página.']);

            return;
        }

        if (($this->data['configuration']['control_estoque'] ?? false) && ! $this->vendas_model->movimentarEstoqueVenda([$itemBloqueado], '+')) {
            $this->db->trans_rollback();
            $this->responderJson(500, ['result' => false, 'message' => 'Não foi possível devolver o estoque. Nenhuma alteração foi confirmada.']);

            return;
        }

        $tinhaDesconto = (float) $venda->valor_desconto > 0;
        $this->vendas_model->zerarDesconto($idVenda);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            $this->responderJson(500, ['result' => false, 'message' => 'Não foi possível excluir. Tente de novo.']);

            return;
        }

        $this->db->trans_complete();

        log_info('Removeu produto da venda. ID da Venda: ' . $idVenda . ', ID do Produto: ' . $item->produtos_id);

        $this->responderTrechos($idVenda, 'produto', 'Produto removido da venda.' . ($tinhaDesconto ? ' O desconto foi removido porque o total mudou.' : ''));
    }

    /**
     * Desconto da venda em R$ ou %, calculado no servidor sobre o total dos
     * produtos (vendaCalcularDesconto()). 0 remove o desconto.
     */
    public function adicionarDesconto()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->responderJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar vendas.']);

            return;
        }

        $venda = $this->vendaParaAlterar();
        if (! $venda) {
            return;
        }

        $idVenda = (int) $venda->idVendas;
        $totais = $this->vendas_model->totais($venda);
        [$dados, $erros] = vendaCalcularDesconto($totais['bruto'], $this->input->post('tipoDesconto'), $this->input->post('desconto'));

        if ($erros !== []) {
            $this->responderJson(422, ['result' => false, 'message' => reset($erros), 'erros' => $erros]);

            return;
        }

        // Politica comercial de vendas: venda com desconto por item (historico
        // ou legado) nao aceita desconto geral cumulativo. Zerar o desconto
        // geral e sempre permitido.
        if ($totais['descontoItens'] > 0 && (float) $dados['desconto'] > 0) {
            $mensagem = 'Esta venda tem descontos por item (total de ' . dinheiro($totais['descontoItens']) . '). Remova os descontos dos itens antes de aplicar um desconto geral.';
            $this->responderJson(422, ['result' => false, 'message' => $mensagem, 'erros' => ['desconto' => $mensagem]]);

            return;
        }

        if (! $this->vendas_model->edit('vendas', $dados, 'idVendas', $idVenda)) {
            $this->responderJson(500, ['result' => false, 'message' => 'Não foi possível gravar o desconto. Tente de novo.']);

            return;
        }

        log_info(($dados['valor_desconto'] > 0 ? 'Adicionou um desconto' : 'Removeu o desconto') . ' na Venda. ID: ' . $idVenda);

        $this->responderTrechos($idVenda, 'desconto', $dados['valor_desconto'] > 0 ? 'Desconto aplicado à venda.' : 'Desconto removido da venda.');
    }

    /**
     * Desconto individual de um produto já na venda (#2880), em R$ ou %. O
     * item vem do banco e tem de ser da venda; o valor com desconto é
     * calculado aqui sobre o preço x quantidade gravados (0 tira o desconto).
     * Como o total muda, o desconto geral da venda é removido, como ao
     * adicionar ou excluir produtos.
     */
    public function descontoProduto()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->responderJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar vendas.']);

            return;
        }

        $venda = $this->vendaParaAlterar(true);
        if (! $venda) {
            return;
        }

        $idVenda = (int) $venda->idVendas;
        $idItem = (int) $this->input->post('idItem');
        $item = $idItem > 0 ? $this->vendas_model->getProdutoDaVenda($idVenda, $idItem) : null;

        if (! $item) {
            $this->responderJson(404, ['result' => false, 'message' => 'Produto não encontrado nesta venda. Recarregue a página.']);

            return;
        }

        if (! $this->vendas_model->descontoPorItemDisponivel()) {
            $this->responderDescontoPorItemIndisponivel();

            return;
        }

        [$desconto, $erros] = osDescontoDoItem(osValorBrutoDoItem($item), $this->input->post('tipoDescontoItem'), $this->input->post('descontoItem'));
        if ($erros === [] && trim((string) $this->input->post('descontoItem')) === '') {
            $erros['descontoItem'] = 'Informe o desconto, ou 0 para tirar.';
        }

        // Politica comercial de vendas: so e aceita a remocao explicita de um
        // desconto por item ja existente (valor 0), para migrar a operacao
        // para desconto geral. Valores nao-zero nao sao aceitos.
        if ($erros === [] && (float) $desconto['desconto'] > 0) {
            $mensagem = 'Novos descontos por item não são aceitos em vendas. Informe 0 para remover o desconto do item e use o desconto geral.';
            $this->responderJson(422, ['result' => false, 'message' => $mensagem, 'erros' => ['descontoItem' => $mensagem]]);

            return;
        }

        if ($erros !== []) {
            $this->responderJson(422, ['result' => false, 'message' => 'Confira os campos destacados.', 'erros' => $erros]);

            return;
        }

        if (! $this->vendas_model->edit('itens_de_vendas', $desconto, 'idItens', $idItem)) {
            $this->responderJson(500, ['result' => false, 'message' => 'Não foi possível aplicar o desconto. Tente de novo.']);

            return;
        }

        $tinhaDesconto = (float) $venda->valor_desconto > 0;
        $this->vendas_model->zerarDesconto($idVenda);
        log_info('Alterou o desconto de um produto da venda. ID da Venda: ' . $idVenda);

        $this->responderTrechos($idVenda, 'produto', ($desconto['desconto'] > 0 ? 'Desconto do produto aplicado.' : 'Desconto do produto removido.') . ($tinhaDesconto ? ' O desconto geral da venda foi removido porque o total mudou.' : ''));
    }

    /**
     * Vendedor padrão da nova venda: o usuário autenticado na sessão, desde
     * que seja um usuário ativo do cadastro (senão o campos fica vazio e a
     * pessoa escolhe no autocomplete). O nome visível e o id oculto saem da
     * mesma sessão; nunca vem do navegador.
     */
    private function vendedorPadrao(): array
    {
        $id = (int) $this->session->userdata('id_admin');
        $nome = trim((string) $this->session->userdata('nome_admin'));
        if ($id <= 0 || $nome === '') {
            return ['', ''];
        }

        if (! $this->vendas_model->existe('usuarios', 'idUsuarios', $id, true)) {
            return ['', ''];
        }

        return [$nome, (string) $id];
    }

    /** Usuário logado, para o vendas_id/lancamentos.usuarios_id. */
    private function usuarioLogado(): ?int
    {
        $id = (int) $this->session->userdata('id_admin');

        return $id > 0 ? $id : null;
    }

    public function faturar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eVenda')) {
            $this->responderJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar vendas.']);

            return;
        }

        $venda = $this->vendaParaAlterar();
        if (! $venda) {
            return;
        }

        $idVenda = (int) $venda->idVendas;

        // Guarda transacional contra faturamento duplicado: trava a linha da
        // venda (SELECT ... FOR UPDATE) dentro da transação antes de validar e
        // faturar. Duplicidade simultânea cai no constraint/update condicional.
        $this->db->trans_start();

        $travada = $this->db->query('SELECT idVendas, faturado, status FROM vendas WHERE idVendas = ? FOR UPDATE', [$idVenda])->row();

        if ($travada === null) {
            $this->db->trans_rollback();
            $this->responderJson(404, ['result' => false, 'message' => 'Venda não encontrada.']);

            return;
        }

        if ((int) $travada->faturado === 1) {
            $this->db->trans_rollback();
            $this->responderJson(422, ['result' => false, 'message' => 'Venda já faturada.', 'erros' => ['_geral' => 'Esta venda já foi faturada. Nenhuma nova receita foi criada.']]);

            return;
        }

        if (is_string($travada->status) && strcasecmp(trim($travada->status), 'Cancelado') === 0) {
            $this->db->trans_rollback();
            $this->responderJson(422, ['result' => false, 'message' => 'Venda cancelada não pode ser faturada.', 'erros' => ['_geral' => 'Esta venda está cancelada.']]);

            return;
        }

        $totais = $this->vendas_model->totais($venda);
        [$lancamento, $erros] = vendaFaturaDoFormulario($this->input->post(), $venda, $totais, $this->usuarioLogado());

        if ($erros !== []) {
            $this->db->trans_rollback();
            $this->responderJson(422, ['result' => false, 'message' => $erros['_geral'] ?? 'Confira os campos destacados.', 'erros' => $erros]);

            return;
        }

        $idLancamento = $this->vendas_model->add('lancamentos', $lancamento, true);
        if (! is_numeric($idLancamento) || $idLancamento <= 0) {
            $this->db->trans_rollback();
            $this->responderJson(500, ['result' => false, 'message' => 'Não foi possível criar o lançamento. Tente de novo.']);

            return;
        }

        // Update condicional: só vira Faturado quem ainda estiver com faturado = 0.
        $this->db->set('faturado', 1);
        $this->db->set('valorTotal', $totais['bruto']);
        $this->db->set('valor_desconto', $totais['total']);
        $this->db->set('lancamentos_id', (int) $idLancamento);
        $this->db->set('status', 'Faturado');
        $this->db->where('idVendas', $idVenda);
        $this->db->where('faturado', 0);
        $this->db->update('vendas');
        $atualizadas = $this->db->affected_rows();

        if ($atualizadas !== 1) {
            $this->db->trans_rollback();
            $this->responderJson(422, ['result' => false, 'message' => 'Venda já faturada.', 'erros' => ['_geral' => 'Esta venda já foi faturada. Nenhuma nova receita foi criada.']]);

            return;
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->responderJson(500, ['result' => false, 'message' => 'Não foi possível faturar a venda. Tente de novo.']);

            return;
        }

        log_info('Faturou a venda com ID.' . $idVenda . ', lançamento: ' . $idLancamento);
        $this->session->set_flashdata('success', 'Venda #' . $idVenda . ' faturada. Lançamento de ' . dinheiro($totais['total']) . ' criado no financeiro.');

        $this->responderJson(200, [
            'result' => true,
            'message' => 'Venda faturada.',
            'redirecionar' => site_url('vendas/visualizar/' . $idVenda),
        ]);
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
