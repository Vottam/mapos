<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Financeiro_model extends CI_Model
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
        $this->db->select($fields . ', usuarios.*');
        $this->db->from($table);
        $this->db->join('usuarios', 'usuarios.idUsuarios = usuarios_id', 'left');
        if ($table === 'lancamentos') {
            $this->db->where($this->lancamentoRealWhere('lancamentos'), null, false);
        }
        $this->db->order_by('data_vencimento', 'asc');
        $this->db->limit($perpage, $start);
        if ($where) {
            $this->db->where($where);
        }

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        return $result;
    }

    public function getTotals($where = '')
    {
        $this->db->select("
            SUM(case when tipo = 'despesa' then valor - desconto end) as despesas,
            SUM(case when tipo = 'receita' and (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') and " . $this->lancamentoRealWhere('lancamentos') . " then (IF(valor_desconto = 0, valor, valor_desconto)) end) as receitas
        ");
        $this->db->from('lancamentos');

        if ($where) {
            $this->db->where($where);
        }

        return (array) $this->db->get()->row();
    }

    public function getCustoProdutosPeriodo($vencimento_de, $vencimento_ate, $cliente = '', $tipo = '', $status = '')
    {
        $custoOs = 0.0;
        $custoVendas = 0.0;

        $vencimentoDeObj = DateTime::createFromFormat('d/m/Y', (string) $vencimento_de);
        $vencimentoAteObj = DateTime::createFromFormat('d/m/Y', (string) $vencimento_ate);
        $vencimento_de = $vencimentoDeObj ? $vencimentoDeObj->format('Y-m-d') : date('Y-m-d');
        $vencimento_ate = $vencimentoAteObj ? $vencimentoAteObj->format('Y-m-d') : date('Y-m-d');

        $this->db->select('COALESCE(SUM(po.custo_total), 0) AS total', false);
        $this->db->from('lancamentos l');
        $this->db->join('os o', 'o.idOs = CAST(TRIM(SUBSTRING_INDEX(l.descricao, ":", -1)) AS UNSIGNED)', 'inner');
        $this->db->join('produtos_os po', 'po.os_id = o.idOs', 'inner');
        $this->db->where('l.data_vencimento >=', $vencimento_de);
        $this->db->where('l.data_vencimento <=', $vencimento_ate);
        if ($status !== '' && $status !== null) {
            $this->db->where('l.baixado', $status);
        }
        if ($cliente !== '' && $cliente !== null) {
            $this->db->like('l.cliente_fornecedor', $cliente);
        }
        if ($tipo !== '' && $tipo !== null) {
            $this->db->where('l.tipo', $tipo);
        }
        $this->db->like('l.descricao', 'Fatura de OS', 'after');
        $queryOs = $this->db->get()->row();
        if ($queryOs) {
            $custoOs = (float) $queryOs->total;
        }

        $this->db->select('COALESCE(SUM(iv.custo_total), 0) AS total', false);
        $this->db->from('lancamentos l');
        $this->db->join('vendas v', 'v.lancamentos_id = l.idLancamentos', 'inner');
        $this->db->join('itens_de_vendas iv', 'iv.vendas_id = v.idVendas', 'inner');
        $this->db->where('l.data_vencimento >=', $vencimento_de);
        $this->db->where('l.data_vencimento <=', $vencimento_ate);
        if ($status !== '' && $status !== null) {
            $this->db->where('l.baixado', $status);
        }
        if ($cliente !== '' && $cliente !== null) {
            $this->db->like('l.cliente_fornecedor', $cliente);
        }
        if ($tipo !== '' && $tipo !== null) {
            $this->db->where('l.tipo', $tipo);
        }
        $this->db->like('l.descricao', 'Fatura de Venda', 'after');
        $queryVendas = $this->db->get()->row();
        if ($queryVendas) {
            $custoVendas = (float) $queryVendas->total;
        }

        return (object) [
            'custo_os' => $custoOs,
            'custo_vendas' => $custoVendas,
            'custo_total' => $custoOs + $custoVendas,
        ];
    }

    public function getEstatisticasFinanceiro2()
    {
        $sql = "SELECT SUM(CASE WHEN baixado = 1 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN IF(valor_desconto = 0, valor, valor_desconto) END) as total_receita,
                       SUM(CASE WHEN baixado = 1 AND tipo = 'despesa' THEN valor - desconto END) as total_despesa,
                       SUM(CASE WHEN baixado = 1 AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN desconto END) as total_valor_desconto,
                       SUM(CASE WHEN baixado = 0 AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor - valor_desconto END) as total_valor_desconto_pendente,
                       SUM(CASE WHEN tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor END) as total_receita_sem_desconto,
                       SUM(CASE WHEN tipo = 'despesa' THEN valor END) as total_despesa_sem_desconto,
                       SUM(CASE WHEN baixado = 0 AND tipo = 'receita' AND (descricao LIKE '%Fatura de OS%' OR descricao LIKE '%Fatura de Venda%') AND " . $this->lancamentoRealWhere('lancamentos') . " THEN valor_desconto END) as total_receita_pendente,
                       SUM(CASE WHEN baixado = 0 AND tipo = 'despesa' THEN valor_desconto END) as total_despesa_pendente FROM lancamentos";

        return $this->db->query($sql)->row();
    }

    public function getById($id)
    {
        $this->db->where('idClientes', $id);
        $this->db->limit(1);

        return $this->db->get('clientes')->row();
    }

    public function add($table, $data)
    {
        $this->db->insert($table, $data);
        if ($this->db->affected_rows() == '1') {
            return true;
        }

        return false;
    }

    public function add1($table, $data1)
    {
        $this->db->insert($table, $data1);
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

    public function count($table, $where)
    {
        $this->db->from($table);
        if ($table === 'lancamentos') {
            $this->db->where($this->lancamentoRealWhere('lancamentos'), null, false);
        }
        if ($where) {
            $this->db->where($where);
        }

        return $this->db->count_all_results();
    }

    public function autoCompleteClienteFornecedor($q)
    {
        $this->db->select('DISTINCT(cliente_fornecedor) as cliente_fornecedor');
        $this->db->limit(5);
        $this->db->like('cliente_fornecedor', $q);
        $query = $this->db->get('lancamentos');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row_set[] = ['label' => $row['cliente_fornecedor'], 'id' => $row['cliente_fornecedor']];
            }
            echo json_encode($row_set);
        }
    }

    public function autoCompleteClienteReceita($q)
    {
        $this->db->select('idClientes, nomeCliente');
        $this->db->limit(5);
        $this->db->like('nomeCliente', $q);
        $query = $this->db->get('clientes');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row_set[] = ['label' => $row['nomeCliente'], 'id' => $row['idClientes']];
            }
            echo json_encode($row_set);
        }
    }
}
