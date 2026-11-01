<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Clientes extends MY_Controller
{
    /** Filtros da listagem, na query string (listagemFiltros()). */
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

    /**
     * Listagem de clientes e fornecedores, a primeira tela migrada para os
     * componentes da v5 (#2841, #2852): filtros na URL, paginação que os
     * mantém e exclusão confirmada em modal-confirm.
     */
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

        return $this->formulario(null);
    }

    public function editar()
    {
        $cliente = is_numeric($this->uri->segment(3)) ? $this->clientes_model->getById((int) $this->uri->segment(3)) : null;
        if (! $cliente) {
            $this->session->set_flashdata('error', 'Cliente não encontrado ou parâmetro inválido.');
            redirect('clientes/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar clientes.');
            redirect(base_url());
        }

        return $this->formulario($cliente);
    }

    /**
     * Formulário de cliente da v5 (#2851), para adicionar ($cliente null) e
     * editar. Padrão dos formulários: POST comum; com erro, a tela volta com
     * os valores digitados e cada erro no seu campo (validarFormulario()); com
     * sucesso, redireciona com o toast.
     *
     * O id editado é sempre o da URL, nunca um campo do POST.
     */
    private function formulario(?object $cliente)
    {
        $erros = [];

        if ($this->input->method() === 'post') {
            [$erros, $id] = $this->salvarCliente($cliente);
            if ($erros === []) {
                $this->session->set_flashdata('success', $cliente === null ? 'Cliente cadastrado com sucesso!' : 'Alterações salvas.');

                return redirect($cliente === null ? 'clientes' : 'clientes/editar/' . $id);
            }
        }

        $this->data['cliente'] = $cliente;
        $this->data['valores'] = clienteValoresDoFormulario($this->input->method() === 'post' ? $this->input->post() : null, $cliente);
        $this->data['erros'] = $erros;
        $this->data['pode_editar_cliente'] = $this->permite('eCliente');
        $this->data['view'] = 'clientes/formulario';

        return $this->layout();
    }

    /**
     * Valida o POST e grava o cliente (novo, ou o editado). É o que o
     * formulário e o cadastro rápido (modal da OS e da venda) têm em comum.
     *
     * @param  bool  $aceitaSenha  false ignora a senha do POST (o cadastro rápido não tem o campo)
     * @return array{0: array<string, string>, 1: int|null} erros por campo ('_geral' para falha ao gravar) e o id gravado
     */
    private function salvarCliente(?object $cliente, bool $aceitaSenha = true, bool $documentoJaVerificado = false): array
    {
        $erros = $this->validarFormulario('clientes');
        if ($erros !== []) {
            return [$erros, null];
        }

        $email = trim((string) $this->input->post('email'));
        if ($email !== '' && $this->clientes_model->emailExists($email, $cliente ? (int) $cliente->idClientes : null)) {
            return [['email' => 'Este e-mail já está sendo usado por outro cliente.'], null];
        }

        $post = $this->input->post();
        if (! $aceitaSenha && is_array($post)) {
            unset($post['senha']);
        }
        $dados = clienteDadosDoFormulario($post, $cliente === null);
        $bloqueioDocumento = null;

        if (! $documentoJaVerificado) {
            $documentoNormalizado = preg_replace('/\D+/', '', (string) ($dados['documento'] ?? ''));
            if (in_array(strlen($documentoNormalizado), [11, 14], true)) {
                $bloqueioDocumento = $this->adquirirBloqueioDocumento($documentoNormalizado);
                if ($bloqueioDocumento === false) {
                    return [['documento' => 'Outro cadastro deste documento está em andamento. Tente novamente.'], null];
                }

                $existentes = $this->clientes_model->getByDocumentoNormalizado(
                    $documentoNormalizado,
                    $cliente ? (int) $cliente->idClientes : null
                );
                if ($existentes !== []) {
                    $this->liberarBloqueioDocumento($bloqueioDocumento);

                    return [['documento' => 'Este CPF/CNPJ já está cadastrado. Consulte o cliente existente antes de salvar.'], null];
                }
            }
        }

        if ($cliente === null) {
            $id = $this->clientes_model->add('clientes', $dados + ['dataCadastro' => date('Y-m-d')]);
            $salvou = (bool) $id;
            $id = $salvou ? (int) $id : null;
        } else {
            $id = (int) $cliente->idClientes;
            $salvou = $this->clientes_model->edit('clientes', $dados, 'idClientes', $id);
        }

        $this->liberarBloqueioDocumento($bloqueioDocumento);

        if (! $salvou) {
            return [['_geral' => 'Não foi possível salvar. Tente de novo.'], null];
        }

        log_info($cliente === null ? 'Adicionou um cliente.' : 'Alterou um cliente. ID ' . $id);

        return [[], $id];
    }

    /**
     * Cadastro rápido de cliente, no modal da tela da OS e da venda (#3003).
     * POST sem recarregar, com os mesmos campos e regras do formulário, mas
     * sem a senha da área do cliente: ela é gerada a partir do documento.
     *
     * 201 {result, message, cliente: {id, valor, detalhe}} (o formato do
     * autocomplete), 422 {result: false, erros: {campo: mensagem}} ou 403.
     */
    public function cadastroRapido()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            $this->responderJson(403, ['result' => false, 'message' => 'Você não tem permissão para adicionar clientes.']);

            return;
        }

        if ($this->input->method() !== 'post') {
            $this->responderJson(405, ['result' => false, 'message' => 'Método não permitido.']);

            return;
        }

        $documentoPost = trim((string) ($this->input->post('documento', true) ?? ''));
        $documentoNormalizado = preg_replace('/\D+/', '', $documentoPost);
        $bloqueioDocumento = null;
        if ($documentoNormalizado !== '' && in_array(strlen($documentoNormalizado), [11, 14], true) && ! in_array((string) ($this->db->dbdriver ?? ''), ['sqlite', 'sqlite3'], true)) {
            // Sem índice único (há duplicidades históricas), serializa o cadastro
            // de qualquer fluxo que grava este documento no MySQL.
            $bloqueioDocumento = 'mapos-c61-' . substr(hash('sha256', $documentoNormalizado), 0, 48);
            $bloqueado = $this->db->query('SELECT GET_LOCK(?, 5) AS bloqueado', [$bloqueioDocumento])->row();
            if (! $bloqueado || (int) $bloqueado->bloqueado !== 1) {
                $this->responderJson(423, ['result' => false, 'message' => 'Outro cadastro deste documento está em andamento. Tente novamente.']);

                return;
            }
        }

        $clientesExistentes = $this->clientes_model->getByDocumentoNormalizado($documentoNormalizado);
            if (! empty($clientesExistentes)) {
                $clientesFormatados = [];
                foreach ($clientesExistentes as $cliente) {
                    $clientesFormatados[] = [
                        'id' => (string) $cliente->idClientes,
                        'valor' => (string) $cliente->nomeCliente,
                        'detalhe' => implode(' · ', array_filter([(string) $cliente->documento, (string) ($cliente->celular ?: $cliente->telefone)], static fn ($v) => $v !== '')),
                    ];
                }
                if (count($clientesFormatados) > 1) {
                    $this->responderJson(409, [
                        'result' => false,
                        'message' => 'Foram encontrados vários clientes com este documento.',
                        'conflict' => true,
                        'duplicate' => true,
                        'clients' => $clientesFormatados,
                    ]);
                    $this->liberarBloqueioDocumento($bloqueioDocumento);

                    return;
                }
                $this->responderJson(409, [
                    'result' => false,
                    'message' => 'Este documento já está cadastrado.',
                    'conflict' => true,
                    'duplicate' => false,
                    'client' => $clientesFormatados[0],
                ]);
                $this->liberarBloqueioDocumento($bloqueioDocumento);

                return;
            }

        [$erros, $id] = $this->salvarCliente(null, false, true);
        if ($erros !== []) {
            $this->responderJson(422, ['result' => false, 'message' => 'Confira os campos destacados.', 'erros' => $erros]);
            $this->liberarBloqueioDocumento($bloqueioDocumento);

            return;
        }

        $cliente = $this->clientes_model->getById($id);

        $this->responderJson(201, [
            'result' => true,
            'message' => 'Cliente cadastrado e escolhido.',
            'cliente' => [
                'id' => (string) $id,
                'valor' => (string) $cliente->nomeCliente,
                'detalhe' => implode(' · ', array_filter([(string) $cliente->documento, (string) ($cliente->celular ?: $cliente->telefone)], static fn ($v) => $v !== '')),
            ],
        ]);
        $this->liberarBloqueioDocumento($bloqueioDocumento);
    }

    /**
     * Busca por documento normalizado (CPF/CNPJ) para o modal de cadastro rápido.
     * A consulta aceita somente 11 ou 14 dígitos; não faz validação matemática
     * de CPF/CNPJ, portanto o resultado é uma correspondência histórica, não
     * uma prova de validade do documento.
     */
    public function buscarPorDocumento()
    {
        $this->output->set_content_type('application/json');

        if (! $this->hasAnyPermission(['vCliente', 'aCliente', 'aOs', 'eOs', 'rOs', 'aVenda', 'eVenda', 'rVenda'])) {
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
                'message' => 'Informe um CPF ou CNPJ com 11 ou 14 dígitos para consultar.',
            ]));
        }

        $type = $tamanhoDocumento === 11 ? 'cpf' : 'cnpj';
        $clientes = $this->clientes_model->getByDocumentoNormalizado($documento);
        $this->output->set_content_type('application/json', 'utf-8');

        $clientesFormatados = [];
        foreach ($clientes as $cliente) {
            $clientesFormatados[] = $this->clienteLookupPayload($cliente);
        }

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

        if (! empty($clientesFormatados)) {
            return $this->output->set_output(json_encode([
                'found' => true,
                'duplicate' => false,
                'type' => $type,
                'documento' => $documento,
                'client' => $clientesFormatados[0],
                'message' => 'Cliente encontrado e selecionado.',
            ]));
        }

        return $this->output->set_output(json_encode([
            'found' => false,
            'type' => $type,
            'documento' => $documento,
            'reason' => 'not_found',
            'message' => 'Cliente não encontrado. Cadastre um novo cliente.',
        ]));
    }

    /** Resposta curta e dados do formulário para a busca local de documento. */
    private function clienteLookupPayload(object $cliente): array
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

    /** Adquire o lock por documento para salvar pelo formulário principal. */
    private function adquirirBloqueioDocumento(string $documento): string|false|null
    {
        if (! in_array(strlen($documento), [11, 14], true)) {
            return null;
        }

        if (in_array((string) ($this->db->dbdriver ?? ''), ['sqlite', 'sqlite3'], true)) {
            // SQLite não oferece GET_LOCK/RELEASE_LOCK; os testes usam banco descartável
            // e não precisam de serialização entre processos.
            return null;
        }

        $nome = 'mapos-c61-' . substr(hash('sha256', $documento), 0, 48);
        $bloqueado = $this->db->query('SELECT GET_LOCK(?, 5) AS bloqueado', [$nome])->row();

        return $bloqueado && (int) $bloqueado->bloqueado === 1 ? $nome : false;
    }

    /** Resposta JSON dos endpoints do cadastro rápido. */
    private function responderJson(int $status, array $corpo): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo));
    }

    /** Libera o lock de documento usado pelo cadastro rápido, quando adquirido. */
    private function liberarBloqueioDocumento(?string $nome): void
    {
        if ($nome !== null) {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$nome]);
        }
    }

    /**
     * Ficha do cliente (#2841): dados, ordens de serviço e vendas em abas por
     * link (?aba=dados|os|vendas), sem JavaScript.
     */
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
            redirect('clientes/gerenciar/');
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

        $this->session->set_flashdata('success', 'Cliente excluído com sucesso!');
        // Volta para a listagem com os mesmos filtros (só os permitidos).
        redirect('clientes' . listagemQuery(listagemFiltros(self::FILTROS, $this->input->get())));
    }
}
