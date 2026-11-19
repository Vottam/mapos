<?php

use Piggly\Pix\StaticPayload;

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Vendas_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }



public function listar(array $filtros, int $limite, int $offset): array
    {
        $totalItens = '(SELECT COALESCE(SUM(itens_de_vendas.subTotal), 0) FROM itens_de_vendas WHERE itens_de_vendas.vendas_id = vendas.idVendas)';
        $vencimentoGeral = '(SELECT MAX(DATE_ADD(vendas.dataEntrega, INTERVAL i.garantia_dias_snapshot DAY)) FROM itens_de_vendas i WHERE i.vendas_id = vendas.idVendas AND i.garantia_dias_snapshot > 0 AND vendas.dataEntrega IS NOT NULL)';

        $this->db->select('vendas.idVendas, vendas.dataVenda, vendas.status, vendas.faturado, vendas.garantia, vendas.desconto, vendas.valor_desconto, vendas.clientes_id, vendas.usuarios_id, vendas.statusOperacionalEntrega, vendas.dataEntrega, vendas.garantia_modelo');
        $this->db->select('clientes.nomeCliente, usuarios.nome AS vendedor');
        $this->db->select($totalItens . ' AS totalItens', false);
        $this->db->select($vencimentoGeral . ' AS vencimento_geral', false);

        $this->aplicarFiltros($filtros);

        $linhas = $this->db
            ->order_by('vendas.idVendas', 'desc')
            ->limit($limite, max(0, $offset))
            ->get()
            ->result();

        foreach ($linhas as $linha) {
            $linha->total = (float) $linha->valor_desconto > 0 ? (float) $linha->valor_desconto : (float) $linha->totalItens;
        }

        return $linhas;
    }



    public function contar(array $filtros): int
    {
        $this->aplicarFiltros($filtros);

        return (int) $this->db->count_all_results();
    }



    private function aplicarFiltros(array $filtros): void
    {
        $this->db->from('vendas');
        $this->db->join('clientes', 'clientes.idClientes = vendas.clientes_id', 'left');
        $this->db->join('usuarios', 'usuarios.idUsuarios = vendas.usuarios_id', 'left');

        $pesquisa = $filtros['pesquisa'] ?? '';
        if ($pesquisa !== '') {
            $this->db->group_start()
                ->like('clientes.nomeCliente', $pesquisa)
                ->or_like('clientes.documento', $pesquisa);
            if (ctype_digit($pesquisa)) {
                $this->db->or_where('vendas.idVendas', (int) $pesquisa);
            }
            $this->db->group_end();
        }

        if (($filtros['status'] ?? '') !== '') {
            $this->db->where('vendas.status', $filtros['status']);
        }

        if (($filtros['de'] ?? '') !== '') {
            $this->db->where('vendas.dataVenda >=', $filtros['de']);
        }
        if (($filtros['ate'] ?? '') !== '') {
            $this->db->where('vendas.dataVenda <=', $filtros['ate']);
        }
    }
    public function get($table, $fields, $where = [], $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $lista_clientes = [];
        if ($where) {
            if (array_key_exists('pesquisa', $where)) {
                $this->db->select('idClientes');
                $this->db->like('nomeCliente', $where['pesquisa']);
                $this->db->limit(25);
                $clientes = $this->db->get('clientes')->result();

                foreach ($clientes as $c) {
                    array_push($lista_clientes, $c->idClientes);
                }
            }
        }
        $this->db->select($fields . ', clientes.nomeCliente, clientes.idClientes');
        $this->db->from($table);
        $this->db->limit($perpage, $start);
        $this->db->join('clientes', 'clientes.idClientes = ' . $table . '.clientes_id');
        $this->db->join('usuarios', 'usuarios.idUsuarios = ' . $table . '.usuarios_id');
        $this->db->order_by('idVendas', 'desc');
        
        // condicionais da pesquisa
        if ($where) {
            // condicional de status
            if (array_key_exists('status', $where)) {
                $this->db->where_in('vendas.status', $where['status']);
            }

            // condicional de clientes
            if (array_key_exists('pesquisa', $where)) {
                if ($lista_clientes != null) {
                    $this->db->where_in('vendas.clientes_id', $lista_clientes);
                }
            }

            // condicional data Venda
            if (array_key_exists('de', $where)) {
                $this->db->where('vendas.dataVenda >=', $where['de']);
            }
            // condicional data final
            if (array_key_exists('ate', $where)) {
                $this->db->where('vendas.dataVenda <=', $where['ate']);
            }
        }
        $query = $this->db->get();

        $result = !$one ? $query->result() : $query->row();

        return $result;
    }

    public function getVendas($table, $fields, $where = [], $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $lista_clientes = [];
        if ($where) {
            if (array_key_exists('pesquisa', $where)) {
                $this->db->select('idClientes');
                $this->db->like('nomeCliente', $where['pesquisa']);
                $this->db->limit(25);
                $clientes = $this->db->get('clientes')->result();

                foreach ($clientes as $c) {
                    array_push($lista_clientes, $c->idClientes);
                }
            }
        }

        $this->db->select($fields . ',clientes.idClientes, clientes.nomeCliente, clientes.celular as celular_cliente, usuarios.nome, garantias.*');
        $this->db->from($table);
        $this->db->join('clientes', 'clientes.idClientes = vendas.clientes_id');
        $this->db->join('usuarios', 'usuarios.idUsuarios = vendas.usuarios_id');
        $this->db->join('garantias', 'garantias.idGarantias = vendas.garantias_id', 'left');
        $this->db->join('produtos_vendas', 'produtos_vendas.vendas_id = vendas.idVendas', 'left');
        $this->db->join('servicos_vendas', 'servicos_vendas.vendas_id = vendas.idVendas', 'left');

        // condicionais da pesquisa

        // condicional de status
        if (array_key_exists('status', $where)) {
            $this->db->where_in('status', $where['status']);
        }

        // condicional de clientes
        if (array_key_exists('pesquisa', $where)) {
            if ($lista_clientes != null) {
                $this->db->where_in('vendas.clientes_id', $lista_clientes);
            }
        }

        // condicional data inicial
        if (array_key_exists('de', $where)) {
            $this->db->where('dataInicial >=', $where['de']);
        }
        // condicional data final
        if (array_key_exists('ate', $where)) {
            $this->db->where('dataFinal <=', $where['ate']);
        }

        $this->db->limit($perpage, $start);
        $this->db->order_by('vendas.idVendas', 'desc');
        $this->db->group_by('vendas.idVendas');

        $query = $this->db->get();

        $result = !$one ? $query->result() : $query->row();

        return $result;
    }

    public function getById($id)
    {
        $this->db->select('vendas.*, clientes.*, clientes.contato as contato_cliente, clientes.email as emailCliente, lancamentos.data_vencimento, usuarios.telefone as telefone_usuario, usuarios.email as email_usuario, usuarios.nome as nome');
        $this->db->from('vendas');
        $this->db->join('clientes', 'clientes.idClientes = vendas.clientes_id');
        $this->db->join('usuarios', 'usuarios.idUsuarios = vendas.usuarios_id');
        $this->db->join('lancamentos', 'vendas.idVendas = lancamentos.vendas_id', 'LEFT');
        $this->db->where('vendas.idVendas', $id);
        $this->db->limit(1);

        return $this->db->get()->row();
    }

    public function isEditable($id = null)
    {
        if ($vendas = $this->getById($id)) {
            if ($vendas->faturado) {
                return $this->data['configuration']['control_edit_vendas'] == '1';
            }
        }

        return true;
    }

    public function getByIdCobrancas($id)
    {
        $this->db->select('vendas.*, clientes.*, clientes.email as emailCliente, lancamentos.data_vencimento, usuarios.telefone as telefone_usuario, usuarios.email as email_usuario, usuarios.nome, usuarios.nome, cobrancas.vendas_id,cobrancas.idCobranca,cobrancas.status');
        $this->db->from('vendas');
        $this->db->join('clientes', 'clientes.idClientes = vendas.clientes_id');
        $this->db->join('usuarios', 'usuarios.idUsuarios = vendas.usuarios_id');
        $this->db->join('cobrancas', 'cobrancas.vendas_id = vendas.idVendas');
        $this->db->join('lancamentos', 'vendas.idVendas = lancamentos.vendas_id', 'LEFT');
        $this->db->where('vendas.idVendas', $id);
        $this->db->limit(1);

        return $this->db->get()->row();
    }

    public function getProdutos($id = null)
    {
        $this->db->select('itens_de_vendas.*, produtos.*');
        $this->db->from('itens_de_vendas');
        $this->db->join('produtos', 'produtos.idProdutos = itens_de_vendas.produtos_id');
        $this->db->where('vendas_id', $id);

        return $this->db->get()->result();
    }

    /**
     * Itens de uma venda já bloqueada pelo chamador. A leitura é feita com
     * bloqueio para manter a quantidade real até o fim da transação.
     */
    public function getItensParaMovimentarEstoque(int $idVenda): array
    {
        return $this->db->query(
            'SELECT idItens, produtos_id, quantidade FROM itens_de_vendas WHERE vendas_id = ? ORDER BY produtos_id ASC, idItens ASC FOR UPDATE',
            [$idVenda]
        )->result();
    }

    /**
     * Movimenta o estoque dos itens de uma venda na transação do chamador.
     * Produtos são agregados e bloqueados em ordem determinística para evitar
     * dupla devolução e reduzir deadlocks entre vendas concorrentes.
     */
    public function movimentarEstoqueVenda(array $itens, string $operacao): bool
    {
        if (! in_array($operacao, ['+', '-'], true)) {
            return false;
        }

        $porProduto = [];
        foreach ($itens as $item) {
            $produto = (int) ($item->produtos_id ?? 0);
            $quantidade = (int) ($item->quantidade ?? 0);
            if ($produto <= 0 || $quantidade <= 0) {
                return false;
            }
            $porProduto[$produto] = ($porProduto[$produto] ?? 0) + $quantidade;
        }
        ksort($porProduto, SORT_NUMERIC);

        foreach ($porProduto as $produto => $quantidade) {
            $existente = $this->db->query(
                'SELECT idProdutos FROM produtos WHERE idProdutos = ? FOR UPDATE',
                [$produto]
            )->row();
            if (! $existente) {
                return false;
            }

            if ($operacao === '+') {
                $sucesso = $this->db->query(
                    'UPDATE produtos SET estoque = estoque + ? WHERE idProdutos = ?',
                    [$quantidade, $produto]
                );
            } else {
                $sucesso = $this->db->query(
                    'UPDATE produtos SET estoque = estoque - ? WHERE idProdutos = ? AND estoque >= ?',
                    [$quantidade, $produto, $quantidade]
                );
            }

            if ($sucesso === false || $this->db->affected_rows() !== 1) {
                return false;
            }
        }

        return true;
    }

    public function getCobrancas($id = null)
    {
        $this->db->select('cobrancas.*');
        $this->db->from('cobrancas');
        $this->db->where('vendas_id', $id);

        return $this->db->get()->result();
    }

    public function add($table, $data, $returnId = false)
    {
        $this->db->insert($table, $data);
        if ($this->db->affected_rows() == '1') {
            if ($returnId == true) {
                return $this->db->insert_id($table);
            }

            return true;
        }

        return false;
    }

    public function edit($table, $data, $fieldID, $ID)
    {
        $this->db->where($fieldID, $ID);
        $this->db->update($table, $data);

        if ($this->db->affected_rows() >= 0) {
            return true;
        }

        return false;
    }

    public function delete($table, $fieldID, $ID)
    {
        $this->db->where($fieldID, $ID);
        $this->db->delete($table);
        if ($this->db->affected_rows() == '1') {
            return true;
        }

        return false;
    }

    public function count($table)
    {
        return $this->db->count_all($table);
    }

    public function autoCompleteProduto($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('descricao', $q);
        $query = $this->db->get('produtos');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row_set[] = ['label' => $row['descricao'] . ' | Preço: R$ ' . $row['precoVenda'] . ' | Estoque: ' . $row['estoque'], 'estoque' => $row['estoque'], 'id' => $row['idProdutos'], 'preco' => $row['precoVenda']];
            }
            echo json_encode($row_set);
        }
    }

    public function autoCompleteCliente($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('nomeCliente', $q);
        $this->db->or_like('documento', $q);
        $query = $this->db->get('clientes');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row_set[] = ['label'=>$row['nomeCliente'].' | Celular: '.$row['celular'].' | Documento: '.$row['documento'],'id'=>$row['idClientes']];
            }
            echo json_encode($row_set);
        } else {
            $row_set[] = ['label' => 'Adicionar cliente...', 'id' => null];
            echo json_encode($row_set);
        }
    }

    public function autoCompleteUsuario($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('nome', $q);
        $this->db->where('situacao', 1);
        $query = $this->db->get('usuarios');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row_set[] = ['label' => $row['nome'] . ' | Telefone: ' . $row['telefone'], 'id' => $row['idUsuarios']];
            }
            echo json_encode($row_set);
        }
    }

    public function getQrCode($id, $pixKey, $emitente)
    {
        if (empty($id) || empty($pixKey) || empty($emitente)) {
            return;
        }

        $produtos = $this->getProdutos($id);
        $valorDesconto = $this->getById($id);
        if ($valorDesconto === null) {
            return;
        }

        $totalProdutos = array_reduce(
            $produtos,
            function ($carry, $produto) {
                return $carry + ($produto->quantidade * $produto->preco);
            },
            0
        );
        $amount = $valorDesconto->valor_desconto != 0 ? round(floatval($valorDesconto->valor_desconto), 2) : round(floatval($totalProdutos), 2);

        if ($amount <= 0) {
            return;
        }

        $pix = (new StaticPayload())
            ->setAmount($amount)
            ->setTid($id)
            ->setDescription(sprintf('%s Venda %s', substr($emitente->nome, 0, 18), $id), true)
            ->setPixKey(getPixKeyType($pixKey), $pixKey)
            ->setMerchantName($emitente->nome)
            ->setMerchantCity($emitente->cidade);

        return $pix->getQRCode();
    }

    /**
     * Totais da venda para a tela moderna (osTotais(), sem serviços): produtos,
     * desconto e total, calculados pelos itens e pelo desconto gravado.
     */
    public function totais(object $venda): array
    {
        $this->load->helper(['os', 'vendas', 'general']);

        $soma = (float) ($this->db
            ->select_sum('subTotal')
            ->where('vendas_id', (int) $venda->idVendas)
            ->get('itens_de_vendas')
            ->row()->subTotal ?? 0);

        $descontoItens = osDescontoDosItens($this->getProdutos((int) $venda->idVendas));

        return osTotais($soma, 0.0, $venda->valor_desconto ?? 0, $venda->tipo_desconto ?? null, $venda->desconto ?? 0, $descontoItens);
    }

    /**
     * Diz se as colunas do desconto por item existem. Instalações que ainda
     * não rodaram as migrations continuam funcionando, só sem esse desconto.
     */
    public function descontoPorItemDisponivel(): bool
    {
        return $this->db->field_exists('desconto', 'itens_de_vendas');
    }

    /** Linha de itens_de_vendas que pertence à venda, ou null. */
    public function getProdutoDaVenda(int $idVenda, int $idItem): ?object
    {
        return $this->db->where('idItens', $idItem)->where('vendas_id', $idVenda)->get('itens_de_vendas', 1)->row() ?: null;
    }

    /** Produto do cadastro (para conferir o id escolhido e o estoque), ou null. */
    public function getProdutoCadastro(int $id): ?object
    {
        return $this->db->select('idProdutos, descricao, precoVenda, precoCompra, estoque')->where('idProdutos', $id)->get('produtos', 1)->row() ?: null;
    }

    /**
     * Diz se existe o registro com o id, para conferir os ids escolhidos nos
     * autocompletes antes de gravar a venda. Com $ativo, só usuários ativos.
     */
    public function existe(string $tabela, string $chave, int $id, bool $ativo = false): bool
    {
        $this->db->where($chave, $id);
        if ($ativo) {
            $this->db->where('situacao', 1);
        }

        return $this->db->count_all_results($tabela) > 0;
    }

    /**
     * Tira o desconto da venda. Mudar os itens muda o total, e o desconto
     * antigo (calculado sobre o total anterior) deixaria de bater, como na v4.
     */
    public function zerarDesconto(int $idVenda): void
    {
        $this->db->set('desconto', 0)->set('valor_desconto', 0)->set('tipo_desconto', null)->where('idVendas', $idVenda)->update('vendas');
    }

    /**
     * Código PIX "copia e cola" (BR Code) da venda: o mesmo payload do QR Code
     * de getQrCode().
     */
    public function getPixPayload($id, $pixKey, $emitente): ?string
    {
        return $this->pix($id, $pixKey, $emitente)?->getPixCode();
    }

    /**
     * PIX estático com o total da venda (com o desconto, quando houver); null
     * sem chave, sem emitente ou com total zerado.
     */
    private function pix($id, $pixKey, $emitente): ?StaticPayload
    {
        if (empty($id) || empty($pixKey) || empty($emitente)) {
            return null;
        }

        $this->load->helper(['os', 'vendas', 'general']);

        $venda = $this->getById($id);
        $amount = $venda ? round($this->totais($venda)['total'], 2) : 0.0;

        if ($amount <= 0) {
            return null;
        }

        $pix = new StaticPayload();
        $pix
            ->setAmount($amount)
            ->setTid($id)
            ->setDescription(sprintf('%s Venda %s', substr($emitente->nome, 0, 18), $id), true)
            ->setPixKey(getPixKeyType($pixKey), $pixKey)
            ->setMerchantName($emitente->nome)
            ->setMerchantCity($emitente->cidade);

        return $pix;
    }

    /**
     * Apaga o lançamento da fatura de uma venda excluída: pelo vínculo
     * vendas.lancamentos_id (que o faturar sempre gravou) e pelas descrições
     * conhecidas (ver vendaDescricoesDaFatura()). Chamar depois de apagar a
     * venda: vendas.lancamentos_id tem chave estrangeira para o lançamento.
     */
    public function excluirFatura(int $idVenda, ?int $idLancamento): void
    {
        $this->load->helper('vendas');

        if ($idLancamento) {
            $this->db->where('idLancamentos', $idLancamento)->delete('lancamentos');
        }

        $this->db->where('vendas_id', $idVenda)->where_in('descricao', vendaDescricoesDaFatura($idVenda))->delete('lancamentos');
    }

    public function getTotalVendas($idVendas)
    {
        $produtos = $this->getProdutos($idVendas);
        $total = 0;

        foreach ($produtos as $produto) {
            $total += $produto->quantidade * $produto->preco;
        }

        return $total;
    }
}

/* End of file vendas_model.php */
/* Location: ./application/models/vendas_model.php */
