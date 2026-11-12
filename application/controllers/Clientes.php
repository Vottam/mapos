<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Clientes extends MY_Controller
{
    public const FILTROS = [
        'pesquisa' => 'texto',
        'tipo' => ['cliente', 'fornecedor'],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->model('clientes_model');
        $this->load->helper('clientes');
        $this->data['menuClientes'] = 'clientes';
    }

    public function index()
    {
        $this->gerenciar();
    }

    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar clientes.');
            redirect(base_url());
        }

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->uri->segment(3);
        $total = $this->clientes_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->clientes_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('clientes/gerenciar'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aCliente'),
            'editar' => $this->permite('eCliente'),
            'excluir' => $this->permite('dCliente'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo cliente', 'icon' => 'plus', 'href' => site_url('clientes/adicionar')];
        }

        $this->data['view'] = 'clientes/clientes';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar clientes.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        $senhaCliente = $this->input->post('senha') ? $this->input->post('senha') : preg_replace('/[^\p{L}\p{N}\s]/', '', set_value('documento'));

        $cpf_cnpj = preg_replace('/[^\p{L}\p{N}\s]/', '', set_value('documento'));

        if (strlen($cpf_cnpj) == 11) {
            $pessoa_fisica = true;
        } else {
            $pessoa_fisica = false;
        }

        if ($this->form_validation->run('clientes') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $email = set_value('email');
            if ($email && $this->clientes_model->emailExists($email)) {
                $this->data['custom_error'] = '<div class="form_error"><p>Este e-mail já está sendo utilizado por outro cliente.</p></div>';
            } else {
                $data = [
                'nomeCliente' => set_value('nomeCliente'),
                'contato' => set_value('contato'),
                'pessoa_fisica' => $pessoa_fisica,
                'documento' => set_value('documento'),
                'telefone' => set_value('telefone'),
                'celular' => set_value('celular'),
                'email' => set_value('email'),
                'senha' => password_hash($senhaCliente, PASSWORD_DEFAULT),
                'rua' => set_value('rua'),
                'numero' => set_value('numero'),
                'complemento' => set_value('complemento'),
                'bairro' => set_value('bairro'),
                'cidade' => set_value('cidade'),
                'estado' => set_value('estado'),
                'cep' => set_value('cep'),
                'dataCadastro' => date('Y-m-d'),
                'fornecedor' => $this->input->post('fornecedor') ? 1 : 0,
            ];

                if ($this->clientes_model->add('clientes', $data) == true) {
                    $this->session->set_flashdata('success', 'Cliente adicionado com sucesso!');
                    log_info('Adicionou um cliente.');
                    redirect(site_url('clientes/'));
                } else {
                    $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro.</p></div>';
                }
            }
        }

        $this->data['cliente'] = null;
        $this->data['valores'] = clienteValoresDoFormulario($this->input->method() === 'post' ? $this->input->post() : null, null);
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['view'] = 'clientes/formulario';

        return $this->layout();
    }

    public function buscarPorCpf()
    {
        $this->output->set_content_type('application/json');

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            return $this->output
                ->set_status_header(403)
                ->set_output(json_encode([
                    'found' => false,
                    'reason' => 'forbidden',
                ]));
        }

        $cpf = $this->input->get_post('cpf', true);
        $cpf = preg_replace('/\D+/', '', (string) $cpf);

        if (strlen($cpf) !== 11) {
            return $this->output->set_output(json_encode([
                'found' => false,
                'reason' => 'invalid_cpf_length',
            ]));
        }

        $clientes = $this->clientes_model->getByCpf($cpf);

        if (empty($clientes)) {
            return $this->output->set_output(json_encode([
                'found' => false,
                'reason' => 'not_found',
            ]));
        }

        $cliente = $clientes[0];
        $payload = [
            'idClientes' => (int) $cliente->idClientes,
            'nomeCliente' => $cliente->nomeCliente,
            'documento' => $cliente->documento,
            'telefone' => $cliente->telefone,
            'celular' => $cliente->celular,
            'email' => $cliente->email,
            'contato' => $cliente->contato,
            'cep' => $cliente->cep,
            'rua' => $cliente->rua,
            'numero' => $cliente->numero,
            'complemento' => $cliente->complemento,
            'bairro' => $cliente->bairro,
            'cidade' => $cliente->cidade,
            'estado' => $cliente->estado,
            'pessoa_fisica' => (int) $cliente->pessoa_fisica,
            'fornecedor' => (int) $cliente->fornecedor,
        ];

        $duplicate = count($clientes) > 1;

        return $this->output->set_output(json_encode([
            'found' => true,
            'duplicate' => $duplicate,
            'total' => count($clientes),
            'cliente' => $payload,
            'message' => $duplicate
                ? 'Há mais de um cliente com este CPF. Revise antes de salvar.'
                : 'Cliente já cadastrado. Dados carregados automaticamente.',
        ]));
    }

    public function buscarPorDocumento()
    {
        $this->output->set_content_type('application/json');

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            return $this->output
                ->set_status_header(403)
                ->set_output(json_encode([
                    'found' => false,
                    'reason' => 'forbidden',
                    'message' => 'Você não tem permissão para consultar clientes.',
                ]));
        }

        $documento = $this->input->get_post('documento', true);
        $documento = preg_replace('/\D+/', '', (string) $documento);
        $tamanhoDocumento = strlen($documento);

        if (! in_array($tamanhoDocumento, [11, 14], true)) {
            return $this->output->set_output(json_encode([
                'found' => false,
                'reason' => 'invalid_length',
                'message' => 'Informe um CPF ou CNPJ válido.',
            ]));
        }

        $type = $tamanhoDocumento === 11 ? 'cpf' : 'cnpj';
        $clientes = $this->clientes_model->getByDocumentoNormalizado($documento);

        if (empty($clientes)) {
            return $this->output->set_output(json_encode([
                'found' => false,
                'type' => $type,
                'documento' => $documento,
                'reason' => 'not_found',
                'message' => 'Cliente não encontrado. Cadastre um novo cliente.',
            ]));
        }

        $clientesFormatados = array_map([$this, 'formatClienteDocumentoLookup'], $clientes);

        if (count($clientesFormatados) > 1) {
            return $this->output->set_output(json_encode([
                'found' => true,
                'duplicate' => true,
                'type' => $type,
                'documento' => $documento,
                'total' => count($clientesFormatados),
                'clients' => $clientesFormatados,
                'message' => 'Foram encontrados vários clientes com este CPF/CNPJ.',
            ]));
        }

        return $this->output->set_output(json_encode([
            'found' => true,
            'duplicate' => false,
            'type' => $type,
            'documento' => $documento,
            'client' => $clientesFormatados[0],
            'message' => 'Cliente encontrado e selecionado.',
        ]));
    }

    public function cadastroRapido()
    {
        return $this->salvarRapido(true);
    }

    public function salvarRapido(bool $contratoAutocompletar = false)
    {
        $this->output->set_content_type('application/json');

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            return $this->output
                ->set_status_header(403)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Você não tem permissão para cadastrar clientes.',
                ]));
        }

        $documentoOriginal = trim((string) $this->input->post('documento', true));
        $documento = preg_replace('/\D+/', '', $documentoOriginal);
        $nomeCliente = trim((string) $this->input->post('nomeCliente', true));
        $email = trim((string) $this->input->post('email', true));

        if ($nomeCliente === '') {
            return $this->output
                ->set_status_header(422)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Informe o nome ou razão social do cliente.',
                ]));
        }

        if ($email !== '' && $this->clientes_model->emailExists($email)) {
            return $this->output
                ->set_status_header(422)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Este e-mail já está sendo utilizado por outro cliente.',
                ]));
        }

        if (in_array(strlen($documento), [11, 14], true)) {
            $clientesExistentes = $this->clientes_model->getByDocumentoNormalizado($documento);
            if (! empty($clientesExistentes)) {
                if ($contratoAutocompletar) {
                    $this->output->set_status_header(409);
                }

                return $this->output->set_output(json_encode([
                    'success' => false,
                    'conflict' => true,
                    'message' => 'Cliente já cadastrado com este CPF/CNPJ.',
                    'clients' => array_map([$this, 'formatClienteDocumentoLookup'], $clientesExistentes),
                ]));
            }
        }

        $pessoaFisica = strlen($documento) === 11 ? 1 : 0;
        $senhaInterna = $documento !== '' ? $documento : bin2hex(random_bytes(8));

        $telefone = trim((string) $this->input->post('telefone', true));
        $celular = trim((string) $this->input->post('celular', true));

        $data = [
            'nomeCliente' => $nomeCliente,
            'contato' => trim((string) $this->input->post('contato', true)),
            'documento' => $documentoOriginal,
            'telefone' => $telefone,
            'celular' => $celular,
            'email' => $email,
            'senha' => password_hash($senhaInterna, PASSWORD_DEFAULT),
            'rua' => trim((string) $this->input->post('rua', true)),
            'numero' => trim((string) $this->input->post('numero', true)),
            'complemento' => trim((string) $this->input->post('complemento', true)),
            'bairro' => trim((string) $this->input->post('bairro', true)),
            'cidade' => trim((string) $this->input->post('cidade', true)),
            'estado' => trim((string) $this->input->post('estado', true)),
            'cep' => trim((string) $this->input->post('cep', true)),
            'dataCadastro' => date('Y-m-d'),
            'pessoa_fisica' => $pessoaFisica,
            'fornecedor' => $this->input->post('fornecedor') ? 1 : 0,
        ];

        $idCliente = $this->clientes_model->add('clientes', $data);

        if (! is_numeric($idCliente)) {
            return $this->output
                ->set_status_header(500)
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Ocorreu um erro ao cadastrar o cliente.',
                ]));
        }

        $cliente = $contratoAutocompletar ? [
            'id' => (string) $idCliente,
            'valor' => $nomeCliente,
            'detalhe' => implode(' · ', array_filter([$documentoOriginal, $celular !== '' ? $celular : $telefone], static fn ($valor) => $valor !== '')),
        ] : [
            'idClientes' => (int) $idCliente,
            'nomeCliente' => $nomeCliente,
            'documento' => $documentoOriginal,
        ];

        $resposta = [
            'success' => true,
            'message' => 'Cliente cadastrado e selecionado.',
        ];
        $resposta[$contratoAutocompletar ? 'cliente' : 'client'] = $cliente;

        return $this->output->set_output(json_encode($resposta));
    }

    private function formatClienteDocumentoLookup($cliente)
    {
        return [
            'id' => (string) $cliente->idClientes,
            'valor' => (string) $cliente->nomeCliente,
            'detalhe' => implode(' · ', array_filter([(string) $cliente->documento, (string) ($cliente->celular ?: $cliente->telefone)], static fn ($v) => $v !== '')),
            'dados' => [
                'nomeCliente' => (string) $cliente->nomeCliente,
                'documento' => (string) $cliente->documento,
                'telefone' => (string) $cliente->telefone,
                'celular' => (string) $cliente->celular,
                'email' => (string) $cliente->email,
                'contato' => (string) $cliente->contato,
                'cep' => (string) $cliente->cep,
                'rua' => (string) $cliente->rua,
                'numero' => (string) $cliente->numero,
                'complemento' => (string) $cliente->complemento,
                'bairro' => (string) $cliente->bairro,
                'cidade' => (string) $cliente->cidade,
                'estado' => (string) $cliente->estado,
                'pessoa_fisica' => (int) $cliente->pessoa_fisica,
                'fornecedor' => (int) $cliente->fornecedor,
            ],
        ];
    }

    public function editar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3)) || ! $this->clientes_model->getById($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Cliente não encontrado ou parâmetro inválido.');
            redirect('clientes/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar clientes.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('clientes') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            
            $email = $this->input->post('email');
            $idCliente = $this->input->post('idClientes');
            if ($email && $this->clientes_model->emailExists($email, $idCliente)) {
                $this->data['custom_error'] = '<div class="form_error"><p>Este e-mail já está sendo utilizado por outro cliente.</p></div>';
            } else {
                $senha = $this->input->post('senha');
                if ($senha != null) {
                    $senha = password_hash($senha, PASSWORD_DEFAULT);

                    $data = [
                        'nomeCliente' => $this->input->post('nomeCliente'),
                        'contato' => $this->input->post('contato'),
                        'documento' => $this->input->post('documento'),
                        'telefone' => $this->input->post('telefone'),
                        'celular' => $this->input->post('celular'),
                        'email' => $this->input->post('email'),
                        'senha' => $senha,
                        'rua' => $this->input->post('rua'),
                        'numero' => $this->input->post('numero'),
                        'complemento' => $this->input->post('complemento'),
                        'bairro' => $this->input->post('bairro'),
                        'cidade' => $this->input->post('cidade'),
                        'estado' => $this->input->post('estado'),
                        'cep' => $this->input->post('cep'),
                        'fornecedor' => (set_value('fornecedor') == true ? 1 : 0),
                    ];
                } else {
                    $data = [
                        'nomeCliente' => $this->input->post('nomeCliente'),
                        'contato' => $this->input->post('contato'),
                        'documento' => $this->input->post('documento'),
                        'telefone' => $this->input->post('telefone'),
                        'celular' => $this->input->post('celular'),
                        'email' => $this->input->post('email'),
                        'rua' => $this->input->post('rua'),
                        'numero' => $this->input->post('numero'),
                        'complemento' => $this->input->post('complemento'),
                        'bairro' => $this->input->post('bairro'),
                        'cidade' => $this->input->post('cidade'),
                        'estado' => $this->input->post('estado'),
                        'cep' => $this->input->post('cep'),
                        'fornecedor' => (set_value('fornecedor') == true ? 1 : 0),
                    ];
                }

                if ($this->clientes_model->edit('clientes', $data, 'idClientes', $this->input->post('idClientes')) == true) {
                    $this->session->set_flashdata('success', 'Cliente editado com sucesso!');
                    log_info('Alterou um cliente. ID' . $this->input->post('idClientes'));
                    redirect(site_url('clientes/editar/') . $this->input->post('idClientes'));
                } else {
                    $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro</p></div>';
                }
            }
        }

        $cliente = $this->clientes_model->getById($this->uri->segment(3));
        $this->data['result'] = $cliente;
        $this->data['cliente'] = $cliente;
        $this->data['valores'] = clienteValoresDoFormulario($this->input->method() === 'post' ? $this->input->post() : null, $cliente);
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['view'] = 'clientes/formulario';

        return $this->layout();
    }

    public function visualizar()
    {
        $cliente = is_numeric($this->uri->segment(3)) ? $this->clientes_model->getById((int) $this->uri->segment(3)) : null;
        if (! $cliente) {
            $this->session->set_flashdata('error', 'Cliente não encontrado ou parâmetro inválido.');
            redirect('clientes/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar clientes.');
            redirect(base_url());
        }

        $id = (int) $cliente->idClientes;
        $aba = listagemFiltros(['aba' => ['dados', 'os', 'vendas']], $this->input->get())['aba'] ?? 'dados';

        $this->data['cliente'] = $cliente;
        $this->data['aba'] = $aba;
        $this->data['total_os'] = $this->clientes_model->contarOsDoCliente($id);
        $this->data['total_vendas'] = $this->clientes_model->contarVendasDoCliente($id);
        // O segmento 3 da URL é o id do cliente: o início da página vai na query string.
        $porPagina = (int) $this->data['configuration']['per_page'];
        $offset = max(0, (int) $this->input->get('inicio'));
        $this->data['os'] = $aba === 'os' ? $this->clientes_model->osDoCliente($id, $porPagina, $offset) : [];
        $this->data['vendas'] = $aba === 'vendas' ? $this->clientes_model->vendasDoCliente($id, $porPagina, $offset) : [];
        $this->data['paginacao'] = $aba === 'dados'
            ? null
            : $this->paginacao(site_url('clientes/visualizar/' . $id), $aba === 'os' ? $this->data['total_os'] : $this->data['total_vendas'], $offset, 'inicio', ['aba' => $aba]);
        $this->data['pode'] = [
            'editar' => $this->permite('eCliente'),
            'excluir' => $this->permite('dCliente'),
            'ver_os' => $this->permite('vOs'),
            'editar_os' => $this->permite('eOs'),
            'ver_venda' => $this->permite('vVenda'),
            'editar_venda' => $this->permite('eVenda'),
        ];

        if ($this->data['pode']['editar']) {
            $this->data['topbar_acao'] = ['label' => 'Editar cliente', 'icon' => 'pencil', 'href' => site_url('clientes/editar/' . $id)];
        }

        $this->data['view'] = 'clientes/visualizar';

        return $this->layout();
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir clientes.');
            redirect(base_url());
        }

        $id = $this->input->post('id');
        if ($id == null) {
            $this->session->set_flashdata('error', 'Erro ao tentar excluir cliente.');
            redirect(site_url('clientes/gerenciar/'));
        }

        $os = $this->clientes_model->getAllOsByClient($id);
        if ($os != null) {
            $this->clientes_model->removeClientOs($os);
        }

        // excluindo Vendas vinculadas ao cliente
        $vendas = $this->clientes_model->getAllVendasByClient($id);
        if ($vendas != null) {
            $this->clientes_model->removeClientVendas($vendas);
        }

        $this->clientes_model->delete('clientes', 'idClientes', $id);
        log_info('Removeu um cliente. ID' . $id);

        $this->session->set_flashdata('success', 'Cliente excluido com sucesso!');
        redirect(site_url('clientes/gerenciar/'));
    }
}
