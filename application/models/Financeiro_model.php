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

    public function getCustosFixos($categoria = '', $status = '', $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $this->db->select('custos_fixos.*, usuarios.nome as usuario_nome');
        $this->db->from('custos_fixos');
        $this->db->join('usuarios', 'usuarios.idUsuarios = custos_fixos.usuarios_id', 'left');

        if ($categoria !== '' && $categoria !== null) {
            $this->db->like('custos_fixos.categoria', $categoria);
        }

        if ($status !== '' && $status !== null) {
            $this->db->where('custos_fixos.ativo', (int) $status);
        }

        $this->db->order_by('custos_fixos.ativo', 'desc');
        $this->db->order_by('custos_fixos.categoria', 'asc');
        $this->db->order_by('custos_fixos.titulo', 'asc');

        if ((int) $perpage > 0) {
            $this->db->limit($perpage, $start);
        }

        $query = $this->db->get();
        return ! $one ? $query->result() : $query->row();
    }

    public function countCustosFixos($categoria = '', $status = '')
    {
        $this->db->from('custos_fixos');

        if ($categoria !== '' && $categoria !== null) {
            $this->db->like('categoria', $categoria);
        }

        if ($status !== '' && $status !== null) {
            $this->db->where('ativo', (int) $status);
        }

        return $this->db->count_all_results();
    }

    public function getCustoFixoById($id)
    {
        $this->db->select('custos_fixos.*, usuarios.nome as usuario_nome');
        $this->db->from('custos_fixos');
        $this->db->join('usuarios', 'usuarios.idUsuarios = custos_fixos.usuarios_id', 'left');
        $this->db->where('custos_fixos.idCustoFixo', (int) $id);
        $this->db->limit(1);

        return $this->db->get()->row();
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

    private function custoFixoAtivoNoMes($custo, DateTime $inicioMes, DateTime $fimMes)
    {
        if ((int) $custo->ativo !== 1) {
            return false;
        }

        if (! empty($custo->periodicidade) && strtolower((string) $custo->periodicidade) !== 'mensal') {
            return false;
        }

        $inicioCompetencia = ! empty($custo->data_inicio) ? $this->normalizarDataCustoFixo($custo->data_inicio) : null;
        $fimCompetencia = ! empty($custo->data_fim) ? $this->normalizarDataCustoFixo($custo->data_fim) : null;

        if ($inicioCompetencia && $inicioCompetencia > $fimMes->format('Y-m-d')) {
            return false;
        }

        if ($fimCompetencia && $fimCompetencia < $inicioMes->format('Y-m-d')) {
            return false;
        }

        return true;
    }

    public function getCustosFixosPeriodo($dataInicial = null, $dataFinal = null)
    {
        $inicio = $this->normalizarDataCustoFixo($dataInicial) ?: date('Y-m-01');
        $fim = $this->normalizarDataCustoFixo($dataFinal) ?: date('Y-m-t');

        $inicioPeriodo = new DateTime($inicio);
        $fimPeriodo = new DateTime($fim);
        $inicioPeriodo->modify('first day of this month');
        $fimPeriodo->modify('first day of this month');

        $custos = $this->db->get_where('custos_fixos', ['ativo' => 1])->result();
        if (! $custos) {
            return 0.0;
        }

        $total = 0.0;
        $cursor = clone $inicioPeriodo;
        while ($cursor <= $fimPeriodo) {
            $mesInicio = new DateTime($cursor->format('Y-m-01'));
            $mesFim = new DateTime($cursor->format('Y-m-t'));
            foreach ($custos as $custo) {
                if ($this->custoFixoAtivoNoMes($custo, $mesInicio, $mesFim)) {
                    $total += (float) $custo->valor;
                }
            }
            $cursor->modify('first day of next month');
        }

        return $total;
    }

    /**
     * Gera competências mensais para um custo fixo
     * Cria registros na tabela custos_fixos_competencias para cada mês de vigência
     */
    public function gerarCompetencias($custoFixoId)
    {
        $custo = $this->getCustoFixoById($custoFixoId);
        if (!$custo || (int) $custo->ativo !== 1) {
            return false;
        }

        $inicio = !empty($custo->data_inicio) ? new DateTime($custo->data_inicio) : new DateTime('first day of this month');
        $fim = !empty($custo->data_fim) ? new DateTime($custo->data_fim) : new DateTime('last day of next year');
        
        // Para periodicidade "único", gera apenas o mês da data_inicio
        if (strtolower((string) $custo->periodicidade) === 'unico') {
            $fim = clone $inicio;
            $fim->modify('last day of this month');
        }

        $cursor = clone $inicio;
        $cursor->modify('first day of this month');
        $fimMes = clone $fim;
        $fimMes->modify('first day of this month');

        while ($cursor <= $fimMes) {
            $competencia = $cursor->format('Y-m-d');
            
            // Verifica se já existe competência para este mês
            $existe = $this->db->get_where('custos_fixos_competencias', [
                'custo_fixo_id' => $custoFixoId,
                'competencia' => $competencia
            ])->row();

            if (!$existe) {
                // Calcula data de vencimento: dia_vencimento do mês de competência
                $vencimento = null;
                if (!empty($custo->dia_vencimento)) {
                    $vencDia = min((int) $custo->dia_vencimento, (int) $cursor->format('t'));
                    $vencimento = $cursor->format('Y-m-') . str_pad($vencDia, 2, '0', STR_PAD_LEFT);
                }

                $this->db->insert('custos_fixos_competencias', [
                    'custo_fixo_id' => $custoFixoId,
                    'competencia' => $competencia,
                    'valor' => (float) $custo->valor,
                    'data_vencimento' => $vencimento,
                    'observacoes' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $cursor->modify('first day of next month');
        }

        // Marca que competências foram geradas
        $this->db->where('idCustoFixo', $custoFixoId);
        $this->db->update('custos_fixos', ['competencias_geradas' => 1]);

        return true;
    }

    /**
     * Busca o valor de custo fixo para uma competência específica.
     * Prioriza o valor da tabela de competências.
     * Se não existir competência, usa o valor padrão (apenas se vigente).
     */
    public function getCustoFixoValorCompetencia($custo, $ano, $mes)
    {
        $competencia = sprintf('%04d-%02d-01', $ano, $mes);
        
        // Busca competência específica
        $competenciaRow = $this->db->get_where('custos_fixos_competencias', [
            'custo_fixo_id' => $custo->idCustoFixo,
            'competencia' => $competencia
        ])->row();

        if ($competenciaRow) {
            return (float) $competenciaRow->valor;
        }

        // Não existe competência: usar valor padrão apenas para meses futuros ou sem histórico
        // Para meses passados já movimentados, retorna 0 (deve ser criada competência manualmente)
        return 0.0;
    }

    /**
     * Calcula total de custos fixos por competência para um período.
     * Usa valores da tabela de competências; fallback para valor padrão.
     */
    public function getCustosFixosCompetenciaPeriodo($dataInicial, $dataFinal)
    {
        $inicio = new DateTime($dataInicial);
        $fim = new DateTime($dataFinal);
        $inicio->modify('first day of this month');
        $fim->modify('first day of this month');

        $custos = $this->db->get_where('custos_fixos', ['ativo' => 1])->result();
        $total = 0.0;

        $cursor = clone $inicio;
        while ($cursor <= $fim) {
            $ano = (int) $cursor->format('Y');
            $mes = (int) $cursor->format('n');

            foreach ($custos as $custo) {
                if (!$this->custoFixoAtivoNoMes($custo, $cursor, (clone $cursor)->modify('last day of this month'))) {
                    continue;
                }
                $total += $this->getCustoFixoValorCompetencia($custo, $ano, $mes);
            }

            $cursor->modify('first day of next month');
        }

        return $total;
    }

    public function getCustosFixosMensais($ano)
    {
        if (! $numbersOnly) {
            $numbersOnly = date('Y');
        }

        $custos = $this->db->get_where('custos_fixos', ['ativo' => 1])->result();
        $series = array_fill(1, 12, 0.0);

        for ($mes = 1; $mes <= 12; $mes++) {
            $mesInicio = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', (int) $numbersOnly, $mes));
            $mesFim = clone $mesInicio;
            $mesFim->modify('last day of this month');

            foreach ($custos as $custo) {
                if ($this->custoFixoAtivoNoMes($custo, $mesInicio, $mesFim)) {
                    $series[$mes] += (float) $custo->valor;
                }
            }
        }

        $obj = new stdClass();
        $meses = [1 => 'JAN', 2 => 'FEV', 3 => 'MAR', 4 => 'ABR', 5 => 'MAI', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SET', 10 => 'OUT', 11 => 'NOV', 12 => 'DEZ'];
        foreach ($meses as $numero => $sigla) {
            $obj->{'VALOR_' . $sigla . '_CUSTO_FIXOS'} = $series[$numero];
        }

        return $obj;
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

    public function getInsertId()
    {
        return $this->db->insert_id();
    }
}
