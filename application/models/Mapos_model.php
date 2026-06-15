<?php

class Mapos_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    private function lancamentoRealWhere($alias = 'lancamentos')
    {
        $alias = trim((string) $alias);

        return "(({$alias}.descricao LIKE 'Fatura de Venda%' AND {$alias}.vendas_id IS NOT NULL AND EXISTS (
                    SELECT 1 FROM vendas v
                    WHERE v.idVendas = {$alias}.vendas_id AND v.faturado = 1
                )) OR ({$alias}.descricao LIKE 'Fatura de OS%' AND EXISTS (
                    SELECT 1 FROM os o
                    WHERE o.idOs = CAST(TRIM(SUBSTRING_INDEX({$alias}.descricao, ':', -1)) AS UNSIGNED) AND o.faturado = 1
                )))";
    }

    public function get($table, $fields, $where = '', $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $this->db->select($fields);
        $this->db->from($table);
        $this->db->limit($perpage, $start);
        if ($where) {
            $this->db->where($where);
        }

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        return $result;
    }

    public function getById($id)
    {
        $this->db->from('usuarios');
        $this->db->select('usuarios.*, permissoes.nome as permissao');
        $this->db->join('permissoes', 'permissoes.idPermissao = usuarios.permissoes_id', 'left');
        $this->db->where('idUsuarios', $id);
        $this->db->limit(1);

        return $this->db->get()->row();
    }

    public function alterarSenha($senha)
    {
        $this->db->set('senha', password_hash($senha, PASSWORD_DEFAULT));
        $this->db->where('idUsuarios', $this->session->userdata('id_admin'));
        $this->db->update('usuarios');

        if ($this->db->affected_rows() >= 0) {
            return true;
        }

        return false;
    }

    public function pesquisar($termo)
    {
        $data = [];
        // buscando clientes
        $this->db->like('nomeCliente', $termo);
        $this->db->or_like('telefone', $termo);
        $this->db->or_like('celular', $termo);
        $this->db->or_like('documento', $termo);
        $this->db->limit(15);
        $data['clientes'] = $this->db->get('clientes')->result();

        // buscando os
        $this->db->like('idOs', $termo);
        $this->db->or_like('descricaoProduto', $termo);
        $this->db->limit(15);
        $data['os'] = $this->db->get('os')->result();

        // buscando produtos
        $this->db->like('codDeBarra', $termo);
        $this->db->or_like('descricao', $termo);
        $this->db->limit(50);
        $data['produtos'] = $this->db->get('produtos')->result();

        //buscando serviços
        $this->db->like('nome', $termo);
        $this->db->limit(15);
        $data['servicos'] = $this->db->get('servicos')->result();

        return $data;
    }

    public function add($table, $data)
    {
        $this->db->insert($table, $data);
        if ($this->db->affected_rows() == '1') {
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

    public function getOsOrcamentos()
    {
        $this->db->select('os.*, clientes.nomeCliente');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->where('os.status', 'Orçamento');
        $this->db->limit(10);

        return $this->db->get()->result();
    }
    
    public function getOsAbertas()
    {
        $this->db->select('os.*, clientes.nomeCliente');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->where('os.status', 'Aberto');
        $this->db->limit(10);

        return $this->db->get()->result();
    }

    public function getOsFinalizadas()
    {
        $this->db->select('os.*, clientes.nomeCliente');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->where('os.status', 'Finalizado');
        $this->db->order_by('os.idOs', 'DESC');
        $this->db->limit(10);

        return $this->db->get()->result();
    }

    public function getOsAprovadas()
    {
        $this->db->select('os.*, clientes.nomeCliente');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->where('os.status', 'Aprovado');
        $this->db->limit(10);

        return $this->db->get()->result();
    }

    public function getOsAguardandoPecas()
    {
        $this->db->select('os.*, clientes.nomeCliente');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->where('os.status', 'Aguardando Peças');
        $this->db->limit(10);

        return $this->db->get()->result();
    }

    public function getOsAndamento()
    {
        $this->db->select('os.*, clientes.nomeCliente');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->where('os.status', 'Em Andamento');
        $this->db->limit(10);

        return $this->db->get()->result();
    }

    public function getOsStatus($status)
    {
        $this->db->select('os.*, clientes.nomeCliente');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->where_in('os.status', $status);
        $this->db->order_by('os.idOs', 'DESC');
        $this->db->limit(10);

        return $this->db->get()->result();
    }
    
    public function getVendasStatus($vstatus)
    {
        $this->db->select('vendas.*, clientes.nomeCliente');
        $this->db->from('vendas');
        $this->db->join('clientes', 'clientes.idClientes = vendas.clientes_id');
        $this->db->where_in('vendas.status', $vstatus);
        $this->db->order_by('vendas.idVendas', 'DESC');
        $this->db->limit(10);

        return $this->db->get()->result();
    }

    public function getLancamentos()
    {
        $this->db->select('idLancamentos, tipo, cliente_fornecedor, descricao, data_vencimento, forma_pgto, valor_desconto, baixado');
        $this->db->from('lancamentos');
        $this->db->where('baixado', 0);
        $this->db->order_by('idLancamentos', 'DESC');
        $this->db->limit(10);

        $query = $this->db->get();
        return $query->result();
    }

    public function calendario($start, $end, $status = null)
    {
        $this->db->select(
            'os.*,
            clientes.nomeCliente,
            COALESCE((SELECT SUM(produtos_os.preco * produtos_os.quantidade ) FROM produtos_os WHERE produtos_os.os_id = os.idOs), 0) totalProdutos,
            COALESCE((SELECT SUM(servicos_os.preco * servicos_os.quantidade ) FROM servicos_os WHERE servicos_os.os_id = os.idOs), 0) totalServicos'
        );
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->join('produtos_os', 'produtos_os.os_id = os.idOs', 'left');
        $this->db->join('servicos_os', 'servicos_os.os_id = os.idOs', 'left');
        $this->db->where('os.dataFinal >=', $start);
        $this->db->where('os.dataFinal <=', $end);
        $this->db->group_by('os.idOs');

        if (! empty($status)) {
            $this->db->where('os.status', $status);
        }

        return $this->db->get()->result();
    }

    public function getProdutosMinimo()
    {
        $sql = 'SELECT * FROM produtos WHERE estoque <= estoqueMinimo AND estoqueMinimo > 0 LIMIT 10';

        return $this->db->query($sql)->result();
    }

    public function getOsEstatisticas()
    {
        $sql = 'SELECT status, COUNT(status) as total FROM os GROUP BY status ORDER BY status';

        return $this->db->query($sql)->result();
    }

    public function getEstatisticasFinanceiro()
    {
        $sql = "SELECT SUM(CASE WHEN baixado = 1 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor - (IF(tipo_desconto = 'real', desconto, (desconto * valor) / 100))  END) as total_receita,
                       SUM(CASE WHEN baixado = 1 AND tipo = 'despesa' THEN valor END) as total_despesa,
                       SUM(CASE WHEN baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor - (IF(tipo_desconto = 'real', desconto, (desconto * valor) / 100))  END) as total_receita_pendente,
                       SUM(CASE WHEN baixado = 0 AND tipo = 'despesa' THEN valor END) as total_despesa_pendente FROM lancamentos";
        if ($this->db->query($sql) !== false) {
            $row = $this->db->query($sql)->row();
            if (!class_exists('Financeiro_model', false)) {
                $this->load->model('Financeiro_model');
            }
            $row->total_custos_fixos = $this->Financeiro_model->getCustosFixosPeriodo(date('Y-01-01'), date('Y-12-31'));
            return $row;
        }

        return false;
    }

    private function normalizarDataCustoFixoPainel($data)
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

    private function custoFixoAtivoNoMesPainel($custo, DateTime $inicioMes, DateTime $fimMes)
    {
        if ((int) $custo->ativo !== 1) {
            return false;
        }

        if (! empty($custo->periodicidade) && strtolower((string) $custo->periodicidade) !== 'mensal') {
            return false;
        }

        $inicioCompetencia = ! empty($custo->data_inicio) ? $this->normalizarDataCustoFixoPainel($custo->data_inicio) : null;
        $fimCompetencia = ! empty($custo->data_fim) ? $this->normalizarDataCustoFixoPainel($custo->data_fim) : null;

        if ($inicioCompetencia && $inicioCompetencia > $fimMes->format('Y-m-d')) {
            return false;
        }

        if ($fimCompetencia && $fimCompetencia < $inicioMes->format('Y-m-d')) {
            return false;
        }

        return true;
    }

    public function getEstatisticasFinanceiroMes($year)
    {
        $numbersOnly = preg_replace('/[^0-9]/', '', $year);

        if (! $numbersOnly) {
            $numbersOnly = date('Y');
        }

        $ano = (int) $numbersOnly;
        $meses = [
            1 => 'JAN',
            2 => 'FEV',
            3 => 'MAR',
            4 => 'ABR',
            5 => 'MAI',
            6 => 'JUN',
            7 => 'JUL',
            8 => 'AGO',
            9 => 'SET',
            10 => 'OUT',
            11 => 'NOV',
            12 => 'DEZ',
        ];

        $receitas = array_fill(1, 12, 0.0);
        $despesas = array_fill(1, 12, 0.0);
        $custoOs = array_fill(1, 12, 0.0);
        $custoVendas = array_fill(1, 12, 0.0);

        $sqlReceitas = "
            SELECT
                EXTRACT(MONTH FROM data_pagamento) AS mes,
                SUM(CASE WHEN baixado = 1 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor - (IF(tipo_desconto = 'real', desconto, (desconto * valor) / 100)) ELSE 0 END) AS total
            FROM lancamentos
            WHERE EXTRACT(YEAR FROM data_pagamento) = ?
            GROUP BY EXTRACT(MONTH FROM data_pagamento)
        ";

        $sqlDespesas = "
            SELECT
                EXTRACT(MONTH FROM data_pagamento) AS mes,
                SUM(CASE WHEN baixado = 1 AND tipo = 'despesa' THEN valor ELSE 0 END) AS total
            FROM lancamentos
            WHERE EXTRACT(YEAR FROM data_pagamento) = ?
            GROUP BY EXTRACT(MONTH FROM data_pagamento)
        ";

        $sqlCustoOs = "
            SELECT
                EXTRACT(MONTH FROM l.data_pagamento) AS mes,
                COALESCE(SUM(po.custo_total), 0) AS total
            FROM lancamentos l
            INNER JOIN os o ON o.idOs = CAST(TRIM(SUBSTRING_INDEX(l.descricao, ':', -1)) AS UNSIGNED)
            INNER JOIN produtos_os po ON po.os_id = o.idOs
            WHERE EXTRACT(YEAR FROM l.data_pagamento) = ?
                AND l.baixado = 1
                AND l.tipo = 'receita'
                AND l.descricao LIKE 'Fatura de OS%'
            GROUP BY EXTRACT(MONTH FROM l.data_pagamento)
        ";

        $sqlCustoVendas = "
            SELECT
                EXTRACT(MONTH FROM l.data_pagamento) AS mes,
                COALESCE(SUM(iv.custo_total), 0) AS total
            FROM lancamentos l
            INNER JOIN vendas v ON v.lancamentos_id = l.idLancamentos
            INNER JOIN itens_de_vendas iv ON iv.vendas_id = v.idVendas
            WHERE EXTRACT(YEAR FROM l.data_pagamento) = ?
                AND l.baixado = 1
                AND l.tipo = 'receita'
                AND l.descricao LIKE 'Fatura de Venda%'
            GROUP BY EXTRACT(MONTH FROM l.data_pagamento)
        ";

        $fillSeries = static function (&$series, $queryResult): void {
            foreach ($queryResult as $row) {
                $mes = (int) $row->mes;
                if ($mes >= 1 && $mes <= 12) {
                    $series[$mes] = (float) $row->total;
                }
            }
        };

        $fillSeries($receitas, $this->db->query($sqlReceitas, [$ano])->result());
        $fillSeries($despesas, $this->db->query($sqlDespesas, [$ano])->result());
        $fillSeries($custoOs, $this->db->query($sqlCustoOs, [$ano])->result());
        $fillSeries($custoVendas, $this->db->query($sqlCustoVendas, [$ano])->result());

        $custosFixos = $this->db->get_where('custos_fixos', ['ativo' => 1])->result();
        $custoFixosMes = array_fill(1, 12, 0.0);
        for ($mes = 1; $mes <= 12; $mes++) {
            $mesInicio = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $ano, $mes));
            $mesFim = clone $mesInicio;
            $mesFim->modify('last day of this month');
            foreach ($custosFixos as $custo) {
                if ($this->custoFixoAtivoNoMesPainel($custo, $mesInicio, $mesFim)) {
                    $custoFixosMes[$mes] += (float) $custo->valor;
                }
            }
        }

        $financeiroMes = new stdClass();
        foreach ($meses as $numero => $sigla) {
            $financeiroMes->{'VALOR_' . $sigla . '_REC'} = $receitas[$numero];
            $financeiroMes->{'VALOR_' . $sigla . '_DES'} = $despesas[$numero];
            $financeiroMes->{'VALOR_' . $sigla . '_CUSTO_OS'} = $custoOs[$numero];
            $financeiroMes->{'VALOR_' . $sigla . '_CUSTO_VENDAS'} = $custoVendas[$numero];
            $financeiroMes->{'VALOR_' . $sigla . '_CUSTO_FIXOS'} = $custoFixosMes[$numero];
            $financeiroMes->{'VALOR_' . $sigla . '_CUSTO_TOTAL'} = $custoOs[$numero] + $custoVendas[$numero];
        }

        return $financeiroMes;
    }

    public function getEstatisticasFinanceiroDia($year)
    {
        $numbersOnly = preg_replace('/[^0-9]/', '', $year);
        if (! $numbersOnly) {
            $numbersOnly = date('Y');
        }
        $sql = '
            SELECT
                SUM(CASE WHEN (EXTRACT(DAY FROM data_pagamento) = ' . date('d') . ') AND EXTRACT(MONTH FROM data_pagamento) = ' . date('m') . " AND baixado = 1 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor - (IF(tipo_desconto = 'real', desconto, (desconto * valor) / 100))  END) AS VALOR_" . date('m') . '_REC,
                SUM(CASE WHEN (EXTRACT(DAY FROM data_pagamento) = ' . date('d') . ') AND EXTRACT(MONTH FROM data_pagamento) = ' . date('m') . " AND baixado = 1 AND tipo = 'despesa' THEN valor END) AS VALOR_" . date('m') . '_DES
            FROM lancamentos
            WHERE EXTRACT(YEAR FROM data_pagamento) = ?
        ';
        if ($this->db->query($sql, [intval($numbersOnly)]) !== false) {
            return $this->db->query($sql, [intval($numbersOnly)])->row();
        }

        return false;
    }

    public function getEstatisticasFinanceiroMesInadimplencia($year)
    {
        $numbersOnly = preg_replace('/[^0-9]/', '', $year);

        if (! $numbersOnly) {
            $numbersOnly = date('Y');
        }

        $sql = "
            SELECT
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 1) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_JAN_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 1) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_JAN_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 2) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_FEV_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 2) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_FEV_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 3) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_MAR_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 3) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_MAR_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 4) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_ABR_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 4) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_ABR_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 5) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_MAI_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 5) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_MAI_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 6) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_JUN_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 6) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_JUN_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 7) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_JUL_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 7) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_JUL_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 8) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_AGO_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 8) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_AGO_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 9) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_SET_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 9) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_SET_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 10) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_OUT_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 10) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_OUT_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 11) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_NOV_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 11) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_NOV_DES,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 12) AND baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) AS VALOR_DEZ_REC,
                SUM(CASE WHEN (EXTRACT(MONTH FROM data_pagamento) = 12) AND baixado = 0 AND tipo = 'despesa' THEN valor END) AS VALOR_DEZ_DES
            FROM lancamentos
            WHERE EXTRACT(YEAR FROM data_pagamento) = ?
        ";
        if ($this->db->query($sql, [intval($numbersOnly)]) !== false) {
            return $this->db->query($sql, [intval($numbersOnly)])->row();
        }

        return false;
    }

    public function getEmitente()
    {
        return $this->db->get('emitente')->row();
    }

    public function addEmitente($nome, $cnpj, $ie, $cep, $logradouro, $numero, $bairro, $cidade, $uf, $telefone, $email, $logo)
    {
        $this->db->set('nome', $nome);
        $this->db->set('cnpj', $cnpj);
        $this->db->set('ie', $ie);
        $this->db->set('cep', $cep);
        $this->db->set('rua', $logradouro);
        $this->db->set('numero', $numero);
        $this->db->set('bairro', $bairro);
        $this->db->set('cidade', $cidade);
        $this->db->set('uf', $uf);
        $this->db->set('telefone', $telefone);
        $this->db->set('email', $email);
        $this->db->set('url_logo', $logo);

        return $this->db->insert('emitente');
    }

    public function editEmitente($id, $nome, $cnpj, $ie, $cep, $logradouro, $numero, $bairro, $cidade, $uf, $telefone, $email)
    {
        $this->db->set('nome', $nome);
        $this->db->set('cnpj', $cnpj);
        $this->db->set('ie', $ie);
        $this->db->set('cep', $cep);
        $this->db->set('rua', $logradouro);
        $this->db->set('numero', $numero);
        $this->db->set('bairro', $bairro);
        $this->db->set('cidade', $cidade);
        $this->db->set('uf', $uf);
        $this->db->set('telefone', $telefone);
        $this->db->set('email', $email);
        $this->db->where('id', $id);

        return $this->db->update('emitente');
    }

    public function editLogo($id, $logo)
    {
        $this->db->set('url_logo', $logo);
        $this->db->where('id', $id);

        return $this->db->update('emitente');
    }

    public function editImageUser($id, $imageUserPath)
    {
        $this->db->set('url_image_user', $imageUserPath);
        $this->db->where('idUsuarios', $id);

        return $this->db->update('usuarios');
    }

    public function check_credentials($email)
    {
        $this->db->where('email', $email);
        $this->db->where('situacao', 1);
        $this->db->limit(1);

        return $this->db->get('usuarios')->row();
    }

    /**
     * Salvar configurações do sistema
     *
     * @param  array  $data
     * @return bool
     */
    public function saveConfiguracao($data)
    {
        try {
            foreach ($data as $key => $valor) {
                $this->db->set('valor', $valor);
                $this->db->where('config', $key);
                $this->db->update('configuracoes');
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}
