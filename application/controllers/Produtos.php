<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Produtos extends MY_Controller
{
    public const FILTROS = [
        'pesquisa' => 'texto',
        'estoque' => ['baixo'],
    ];
    public function __construct()
    {
        parent::__construct();

        $this->load->helper('form');
        $this->load->model('produtos_model');
        $this->load->helper('produtos');
        $this->data['menuProdutos'] = 'Produtos';
    }

    public function index()
    {
        $this->gerenciar();
    }

    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar produtos.');
            redirect(base_url());
        }

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->uri->segment(3);
        $total = $this->produtos_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->produtos_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('produtos/gerenciar'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aProduto'),
            'editar' => $this->permite('eProduto'),
            'excluir' => $this->permite('dProduto'),
            'etiquetas' => $this->permite('rProduto'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo produto', 'icon' => 'plus', 'href' => site_url('produtos/adicionar')];
        }

        $this->data['view'] = 'produtos/produtos';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar produtos.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('produtos') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $precoCompra = valorDecimal($this->input->post('precoCompra'));
            $precoVenda = valorDecimal($this->input->post('precoVenda'));
            [$dadosGarantia, $erroGarantia] = $this->dadosGarantiaProdutos(null);
            if ($erroGarantia !== null) {
                $this->data['custom_error'] = '<div class="form_error"><p>' . htmlspecialchars($erroGarantia, ENT_QUOTES, 'UTF-8') . '</p></div>';
            } else {
            $data = [
                'codDeBarra' => set_value('codDeBarra'),
                'descricao' => set_value('descricao'),
                'unidade' => set_value('unidade'),
                'precoCompra' => $precoCompra,
                'precoVenda' => $precoVenda,
                'estoque' => set_value('estoque'),
                'estoqueMinimo' => set_value('estoqueMinimo'),
                'saida' => set_value('saida'),
                'entrada' => set_value('entrada'),
                'garantia_dias' => $this->input->post('garantia_dias'),
            ];
            $data = array_merge($data, $dadosGarantia);

            if ($this->produtos_model->add('produtos', $data) == true) {
                $this->session->set_flashdata('success', 'Produto adicionado com sucesso!');
                log_info('Adicionou um produto');
                redirect(site_url('produtos/adicionar/'));
            } else {
                $this->data['custom_error'] = '<div class="form_error"><p>An Error Occured.</p></div>';
            }
            }
        }
        $this->data['produto'] = null;
        $padraoProdutoGlobal = garantiaPadraoGlobalDias($this->data['configuration'] ?? null, 'produto');
        $this->data['valores'] = array_merge([
            'descricao' => '',
            'unidade' => '',
            'codDeBarra' => '',
            'precoCompra' => '',
            'precoVenda' => '',
            'estoque' => '',
            'estoqueMinimo' => '',
            'entrada' => '',
            'saida' => '',
            'garantia_dias' => (string) $padraoProdutoGlobal,
            'garantia_dias_custom' => '0',
            'supplier_warranty_days' => '',
            'supplier_warranty_pass_through' => '0',
        ], (array) $this->input->post());
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['view'] = 'produtos/formulario';

        return $this->layout();
    }

    public function editar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3)) || ! $this->produtos_model->getById($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Produto não encontrado ou parâmetro inválido.');
            redirect('produtos/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar produtos.');
            redirect(base_url());
        }
        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('produtos') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $precoCompra = valorDecimal($this->input->post('precoCompra'));
            $precoVenda = valorDecimal($this->input->post('precoVenda'));
            $produtoOriginal = $this->produtos_model->getById($this->input->post('idProdutos'));
            [$dadosGarantia, $erroGarantia] = $this->dadosGarantiaProdutos($produtoOriginal ?: null);
            if ($erroGarantia !== null) {
                $this->data['custom_error'] = '<div class="form_error"><p>' . htmlspecialchars($erroGarantia, ENT_QUOTES, 'UTF-8') . '</p></div>';
            } else {
            $data = [
                'codDeBarra' => set_value('codDeBarra'),
                'descricao' => $this->input->post('descricao'),
                'unidade' => $this->input->post('unidade'),
                'precoCompra' => $precoCompra,
                'precoVenda' => $precoVenda,
                'estoque' => $this->input->post('estoque'),
                'estoqueMinimo' => $this->input->post('estoqueMinimo'),
                'saida' => set_value('saida'),
                'entrada' => set_value('entrada'),
                'garantia_dias' => $this->input->post('garantia_dias'),
            ];
            $data = array_merge($data, $dadosGarantia);

            if ($this->produtos_model->edit('produtos', $data, 'idProdutos', $this->input->post('idProdutos')) == true) {
                $this->session->set_flashdata('success', 'Produto editado com sucesso!');
                log_info('Alterou um produto. ID: ' . $this->input->post('idProdutos'));
                redirect(site_url('produtos/editar/') . $this->input->post('idProdutos'));
            } else {
                $this->data['custom_error'] = '<div class="form_error"><p>An Error Occured</p></div>';
            }
            }
        }

        $produto = $this->produtos_model->getById($this->uri->segment(3));
        $padraoProdutoGlobal = garantiaPadraoGlobalDias($this->data['configuration'] ?? null, 'produto');
        // Exibe a base efetiva: personalizada quando o produto a tem; senão o padrão global vigente.
        $garantiaExibida = garantiaDiasCustomizado($produto)
            ? (string) ($produto->garantia_dias ?? $padraoProdutoGlobal)
            : (string) $padraoProdutoGlobal;
        $this->data['result'] = $produto;
        $this->data['produto'] = $produto;
        $this->data['valores'] = array_merge([
            'descricao' => $produto->descricao ?? '',
            'unidade' => $produto->unidade ?? '',
            'codDeBarra' => $produto->codDeBarra ?? '',
            'precoCompra' => number_format((float) ($produto->precoCompra ?? 0), 2, ',', '.'),
            'precoVenda' => number_format((float) ($produto->precoVenda ?? 0), 2, ',', '.'),
            'estoque' => (string) ($produto->estoque ?? ''),
            'estoqueMinimo' => (string) ($produto->estoqueMinimo ?? ''),
            'entrada' => $produto->entrada ?? '',
            'saida' => $produto->saida ?? '',
            'garantia_dias' => $this->input->post('garantia_dias') !== null ? $this->input->post('garantia_dias') : $garantiaExibida,
            'garantia_dias_custom' => $this->input->post('garantia_dias_custom') !== null ? $this->input->post('garantia_dias_custom') : (garantiaDiasCustomizado($produto) ? '1' : '0'),
            'supplier_warranty_days' => $this->input->post('supplier_warranty_days') !== null ? $this->input->post('supplier_warranty_days') : ($produto->supplier_warranty_days !== null ? (string) (int) $produto->supplier_warranty_days : ''),
            'supplier_warranty_pass_through' => $this->input->post('supplier_warranty_pass_through') !== null ? $this->input->post('supplier_warranty_pass_through') : (string) (int) ($produto->supplier_warranty_pass_through ?? 0),
        ], []);
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['view'] = 'produtos/formulario';

        return $this->layout();
    }

    public function visualizar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar produtos.');
            redirect(base_url());
        }

        $this->data['result'] = $this->produtos_model->getById($this->uri->segment(3));

        if ($this->data['result'] == null) {
            $this->session->set_flashdata('error', 'Produto não encontrado.');
            redirect(site_url('produtos/editar/') . $this->input->post('idProdutos'));
        }

        $this->data['view'] = 'produtos/visualizarProduto';

        return $this->layout();
    }

    /**
     * Etiqueta individual 40x25mm do produto, gerada no servidor como PDF.
     * O payload QR permanece PROD:{id com quatro dígitos}.
     */
    public function imprimirEtiqueta()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar produtos.');
            redirect(base_url());
        }

        $id = $this->uri->segment(3);
        if (! is_numeric($id) || (int) $id <= 0) {
            $this->session->set_flashdata('error', 'Produto não encontrado ou parâmetro inválido.');
            redirect('produtos/gerenciar');
        }

        $produto = $this->produtos_model->getById((int) $id);
        if (! $produto) {
            $this->session->set_flashdata('error', 'Produto não encontrado ou parâmetro inválido.');
            redirect('produtos/gerenciar');
        }

        $this->load->helper('etiquetas');
        etiquetaGerarPdf(etiquetaHtmlProduto($produto), 'etiqueta_produto_' . (int) $produto->idProdutos);
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir produtos.');
            redirect(base_url());
        }

        $id = $this->input->post('id');
        if ($id == null) {
            $this->session->set_flashdata('error', 'Erro ao tentar excluir produto.');
            redirect(base_url() . 'index.php/produtos/gerenciar/');
        }

        $this->produtos_model->delete('produtos_os', 'produtos_id', $id);
        $this->produtos_model->delete('itens_de_vendas', 'produtos_id', $id);
        $this->produtos_model->delete('produtos', 'idProdutos', $id);

        log_info('Removeu um produto. ID: ' . $id);

        $this->session->set_flashdata('success', 'Produto excluido com sucesso!');
        redirect(site_url('produtos/gerenciar/'));
    }

    public function atualizar_estoque()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para atualizar estoque de produtos.');
            redirect(base_url());
        }

        $idProduto = $this->input->post('id');
        $novoEstoque = $this->input->post('estoque');
        $estoqueAtual = $this->input->post('estoqueAtual');

        $estoque = $estoqueAtual + $novoEstoque;

        $data = [
            'estoque' => $estoque,
        ];

        if ($this->produtos_model->edit('produtos', $data, 'idProdutos', $idProduto) == true) {
            $this->session->set_flashdata('success', 'Estoque de Produto atualizado com sucesso!');
            log_info('Atualizou estoque de um produto. ID: ' . $idProduto);
            redirect(site_url('produtos/visualizar/') . $idProduto);
        } else {
            $this->data['custom_error'] = '<div class="alert">Ocorreu um erro.</div>';
        }
    }

    public function validar_garantia_dias($dias): bool
    {
        // Campos opcionais (permit_empty): vazio é válido; o campo comercial
        // mantém 'required' antes deste callback, então vazio ali já falha.
        if (trim((string) $dias) === '') {
            return true;
        }
        if (garantiaDiasValido($dias)) {
            return true;
        }

        $mensagem = 'Informe zero ou um número inteiro de dias.';
        $this->form_validation->set_message('validar_garantia_dias', $mensagem);

        return false;
    }

    /**
     * Campos de garantia (comercial + fornecedor) a partir do POST do formulário de produto.
     *
     * Regras:
     * - garantia_dias: sempre inteiro ≥ 0 (validação de formulário já exigiu o campo).
     * - garantia_dias_custom: marcado explicitamente; edição que diverge do valor armazenado
     *   e do padrão global também vira personalizada (edição explícita vence a herança).
     * - supplier_warranty_days: vazio = não informada; prazo novo/alterado reinicia a
     *   contagem a partir de hoje (America/Sao_Paulo); igual ao armazenado mantém a data;
     *   "limpar" (esvaziar) remove o controle; nunca reinicia por terceiros campos.
     * - supplier_warranty_pass_through: booleano do checkbox de repasse.
     *
     * @param object|null $produto produto armazenado (null na criação)
     * @return array{0: array<string,mixed>, 1: string|null} [dados para o model, erro ou null]
     */
    private function dadosGarantiaProdutos(?object $produto): array
    {
        $hoje = garantiaHojeYmd();
        $configuracao = $this->data['configuration'] ?? null;
        $padraoGlobal = garantiaPadraoGlobalDias($configuracao, 'produto');
        $dados = [];

        // Garantia comercial: personalizada explicitamente, edição divergente, ou herda o padrão.
        $postBruto = $this->input->post('garantia_dias');
        $postDias = garantiaNormalizarDias($postBruto);
        if ($postDias === null) {
            return [[], 'Informe zero ou um número inteiro de dias para a garantia do produto.'];
        }
        $checkedCustom = $this->input->post('garantia_dias_custom') === '1';
        $storedDias = $produto !== null && isset($produto->garantia_dias) ? (int) $produto->garantia_dias : null;
        if ($checkedCustom) {
            $dados['garantia_dias_custom'] = 1;
        } elseif ($storedDias !== null && $postDias === $storedDias) {
            $dados['garantia_dias_custom'] = 0; // sem alteração: herda o padrão
        } elseif ($postDias === $padraoGlobal) {
            $dados['garantia_dias_custom'] = 0; // voltou ao padrão: herda
        } else {
            $dados['garantia_dias_custom'] = 1; // edição explícita: personalizada
        }
        $dados['garantia_dias'] = $postDias;

        // Garantia do fornecedor (produtos): decisão pura e auditável.
        $supplierBruto = $this->input->post('supplier_warranty_days');
        $storedSupplier = $produto !== null && $produto->supplier_warranty_days !== null ? (int) $produto->supplier_warranty_days : null;
        $decisao = garantiaFornecedorDecisaoSalvar($storedSupplier, $supplierBruto, false, $hoje);
        if ($decisao['acao'] === 'invalido') {
            return [[], 'Informe zero ou um número inteiro de dias para a garantia do fornecedor, ou deixe em branco.'];
        }

        switch ($decisao['acao']) {
            case 'limpar':
                $dados['supplier_warranty_days'] = null;
                $dados['supplier_warranty_start_date'] = null;
                break;
            case 'atualizar':
                $dados['supplier_warranty_days'] = $decisao['days'];
                $dados['supplier_warranty_start_date'] = $decisao['start_date'];
                break;
            case 'manter':
                if ($decisao['days'] !== null) {
                    $dados['supplier_warranty_days'] = $decisao['days'];
                    // start_date inalterado de propósito
                } elseif ($produto === null) {
                    $dados['supplier_warranty_days'] = null;
                    $dados['supplier_warranty_start_date'] = null;
                }
                break;
        }
        $dados['supplier_warranty_pass_through'] = $this->input->post('supplier_warranty_pass_through') === '1' ? 1 : 0;

        return [$dados, null];
    }

    /**
     * Ação explícita "Renovar contagem da garantia" do fornecedor (mesmo prazo).
     * Só POST, com CSRF e permissão de edição de produto. Reinicia a contagem hoje;
     * nunca cria remessa, unidade ou etiqueta nova.
     */
    public function renovarGarantiaFornecedor()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar produtos.');
            redirect(base_url());
        }
        if (strtoupper($this->input->server('REQUEST_METHOD') ?: 'GET') !== 'POST') {
            redirect(base_url('index.php/produtos/gerenciar'));
        }
        $idProduto = (int) $this->input->post('idProdutos');
        $produto = $idProduto > 0 ? $this->produtos_model->getById($idProduto) : null;
        if ($produto === null) {
            $this->session->set_flashdata('error', 'Produto não encontrado.');
            redirect(base_url('index.php/produtos/gerenciar'));
        }
        if ($produto->supplier_warranty_days === null) {
            $this->session->set_flashdata('error', 'Configure o prazo da garantia do fornecedor antes de renovar a contagem.');
            redirect(site_url('produtos/editar/' . $idProduto));
        }
        $hoje = garantiaHojeYmd();
        $ok = $this->produtos_model->edit('produtos', ['supplier_warranty_start_date' => $hoje], 'idProdutos', $idProduto);
        if ($ok == true) {
            $this->session->set_flashdata('success', 'Contagem da garantia do fornecedor renovada a partir de ' . date('d/m/Y', strtotime($hoje)) . '.');
            log_info('Renovou a contagem da garantia do fornecedor. ID: ' . $idProduto);
        } else {
            $this->session->set_flashdata('error', 'Não foi possível renovar a contagem da garantia do fornecedor.');
        }
        redirect(site_url('produtos/editar/' . $idProduto));
    }
}
