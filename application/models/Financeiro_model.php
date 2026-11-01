<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Financeiro_model extends CI_Model
{
    /**
     * Valor líquido de um lançamento no SQL (o que entra ou sai de fato): o
     * valor_desconto quando preenchido; senão, o valor menos o desconto. Mesma
     * regra de financeiroLiquido(), que serve às linhas já carregadas.
     */
    public const LIQUIDO = 'CASE WHEN lancamentos.valor_desconto > 0 THEN lancamentos.valor_desconto ELSE lancamentos.valor - COALESCE(lancamentos.desconto, 0) END';

    /** Model de OS, para registrar no histórico a OS que volta a Finalizada (injetável nos testes). */
    public ?object $osModel = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Listagem de lançamentos da v5 (#2844), no padrão das listagens (#2852):
     * os mesmos filtros em listar() e contar(), para a paginação contar só o
     * que a busca encontra.
     *
     * Cada linha traz o líquido (liquido) e quem alterou por último
     * (modificado_por). Ordem: vencimento mais antigo primeiro.
     *
     * @param  array<string, string>  $filtros  pesquisa, tipo, status, de, ate (já validados)
     * @param  string                 $hoje     AAAA-MM-DD, para o status "vencido"
     */
    public function listar(array $filtros, int $limite, int $offset, string $hoje): array
    {
        $this->db->select('lancamentos.*, usuarios.nome AS modificado_por');
        $this->db->select(self::LIQUIDO . ' AS liquido', false);
        $this->aplicarFiltros($filtros, $hoje);

        return $this->db
            ->join('usuarios', 'usuarios.idUsuarios = lancamentos.usuarios_id', 'left')
            ->order_by('lancamentos.data_vencimento', 'asc')
            ->order_by('lancamentos.idLancamentos', 'asc')
            ->limit($limite, max(0, $offset))
            ->get()
            ->result();
    }

    /** Total da listagem com os mesmos filtros de listar(). */
    public function contar(array $filtros, string $hoje): int
    {
        $this->aplicarFiltros($filtros, $hoje);

        return (int) $this->db->count_all_results();
    }

    /**
     * pesquisa procura no cliente/fornecedor, na descrição e nas observações
     * e, se for número, no Nº do lançamento (o OR fica entre parênteses para
     * não anular os outros filtros); tipo é receita ou despesa; status é
     * pendente, pago ou vencido (pendente com o vencimento antes de hoje); de
     * e ate limitam a data de vencimento.
     */
    private function aplicarFiltros(array $filtros, string $hoje): void
    {
        $this->db->from('lancamentos');

        $pesquisa = $filtros['pesquisa'] ?? '';
        if ($pesquisa !== '') {
            $this->db->group_start()
                ->like('lancamentos.cliente_fornecedor', $pesquisa)
                ->or_like('lancamentos.descricao', $pesquisa)
                ->or_like('lancamentos.observacoes', $pesquisa);
            if (ctype_digit($pesquisa)) {
                $this->db->or_where('lancamentos.idLancamentos', (int) $pesquisa);
            }
            $this->db->group_end();
        }

        if (in_array($filtros['tipo'] ?? '', array_keys(FINANCEIRO_TIPOS), true)) {
            $this->db->where('lancamentos.tipo', $filtros['tipo']);
        }

        switch ($filtros['status'] ?? '') {
            case 'pago':
                $this->db->where('lancamentos.baixado', 1);
                break;
            case 'pendente':
                $this->db->group_start()->where('lancamentos.baixado', 0)->or_where('lancamentos.baixado IS NULL', null, false)->group_end();
                break;
            case 'vencido':
                $this->db->group_start()->where('lancamentos.baixado', 0)->or_where('lancamentos.baixado IS NULL', null, false)->group_end();
                $this->db->where('lancamentos.data_vencimento <', $hoje);
                break;
        }

        if (($filtros['de'] ?? '') !== '') {
            $this->db->where('lancamentos.data_vencimento >=', $filtros['de']);
        }
        if (($filtros['ate'] ?? '') !== '') {
            $this->db->where('lancamentos.data_vencimento <=', $filtros['ate']);
        }
    }

    /**
     * Totais dos lançamentos da listagem (mesmos filtros de listar()), no
     * mesmo critério de líquido para receitas e despesas.
     *
     * @return array<string, float>  receitas, despesas (total), receitas_pagas, despesas_pagas,
     *                               receitas_pendentes, despesas_pendentes, vencidos (quantidade),
     *                               receitas_vencidas, despesas_vencidas
     */
    public function totais(array $filtros, string $hoje): array
    {
        $liquido = self::LIQUIDO;
        $pendente = '(lancamentos.baixado = 0 OR lancamentos.baixado IS NULL)';
        $vencido = $pendente . ' AND lancamentos.data_vencimento < ' . $this->db->escape($hoje);
        $soma = static fn (string $condicao): string => "COALESCE(SUM(CASE WHEN {$condicao} THEN {$liquido} END), 0)";

        $this->db->select($soma("lancamentos.tipo = 'receita'") . ' AS receitas', false);
        $this->db->select($soma("lancamentos.tipo = 'despesa'") . ' AS despesas', false);
        $this->db->select($soma("lancamentos.tipo = 'receita' AND lancamentos.baixado = 1") . ' AS receitas_pagas', false);
        $this->db->select($soma("lancamentos.tipo = 'despesa' AND lancamentos.baixado = 1") . ' AS despesas_pagas', false);
        $this->db->select($soma("lancamentos.tipo = 'receita' AND {$pendente}") . ' AS receitas_pendentes', false);
        $this->db->select($soma("lancamentos.tipo = 'despesa' AND {$pendente}") . ' AS despesas_pendentes', false);
        $this->db->select($soma("lancamentos.tipo = 'receita' AND {$vencido}") . ' AS receitas_vencidas', false);
        $this->db->select($soma("lancamentos.tipo = 'despesa' AND {$vencido}") . ' AS despesas_vencidas', false);
        $this->db->select("COALESCE(SUM(CASE WHEN {$vencido} THEN 1 END), 0) AS vencidos", false);
        $this->aplicarFiltros($filtros, $hoje);

        $linha = (array) $this->db->get()->row();

        return array_map(static fn ($valor) => (float) $valor, $linha);
    }

    /**
     * Agregados financeiros adicionais da tela e dos relatórios.
     *
     * Os totais de lançamentos continuam sendo produzidos exclusivamente por
     * totais(). Os custos de produtos/componentes usam o mesmo intervalo e os
     * mesmos filtros do lançamento-fatura, mas são exibidos como camada
     * gerencial separada. Custos fixos usam exclusivamente competências.
     */
    public function indicadoresGerenciais(array $filtros, string $hoje): array
    {
        $totais = $this->totais($filtros, $hoje);
        $custos = $this->getCustoProdutosPeriodo($filtros, $hoje);
        $custosFixos = $this->getTotaisCustosFixosCompetencia($filtros['de'] ?? null, $filtros['ate'] ?? null);

        $receitasCentavos = $this->centavos($totais['receitas'] ?? 0);
        $despesasCentavos = $this->centavos($totais['despesas'] ?? 0);
        $componentesCentavos = $this->centavos($custos->custo_total ?? 0);
        $liquidoCentavos = $receitasCentavos - $despesasCentavos - $componentesCentavos;

        // Tipo, situação e pesquisa restringem lançamentos, não competências.
        // Não subtrair custos fixos de um universo filtrado incompatível.
        $escopoComparavel = (($filtros['tipo'] ?? '') === '')
            && (($filtros['status'] ?? '') === '')
            && (($filtros['pesquisa'] ?? '') === '')
            && (($filtros['cliente'] ?? '') === '');
        $aposFixosCentavos = $escopoComparavel
            ? $liquidoCentavos - $this->centavos($custosFixos->total ?? 0)
            : null;

        return [
            'receitas' => $this->reais($receitasCentavos),
            'despesas' => $this->reais($despesasCentavos),
            'custo_componentes_os' => $this->reais($this->centavos($custos->custo_os ?? 0)),
            'custo_produtos_vendas' => $this->reais($this->centavos($custos->custo_vendas ?? 0)),
            'custo_componentes_periodo' => $this->reais($componentesCentavos),
            'quantidade_os_com_custo' => (int) ($custos->quantidade_os ?? 0),
            'quantidade_vendas_com_custo' => (int) ($custos->quantidade_vendas ?? 0),
            'resultado_liquido_real' => $this->reais($liquidoCentavos),
            'custos_fixos_periodo' => $this->reais($this->centavos($custosFixos->total ?? 0)),
            'custos_fixos_pago' => $this->reais($this->centavos($custosFixos->pago ?? 0)),
            'custos_fixos_a_pagar' => $this->reais($this->centavos($custosFixos->a_pagar ?? 0)),
            'custos_fixos_competencias_pagas' => (int) ($custosFixos->competencias_pagas ?? 0),
            'custos_fixos_competencias_pendentes' => (int) ($custosFixos->competencias_pendentes ?? 0),
            'resultado_apos_custos_fixos' => $aposFixosCentavos === null ? null : $this->reais($aposFixosCentavos),
            'resultado_apos_custos_fixos_comparavel' => $escopoComparavel,
            'escopo_lancamentos' => 'lançamentos com data de vencimento no período; receitas e despesas pelo líquido oficial',
            'escopo_custos' => 'faturas de OS/Venda associadas no mesmo período pela data de vencimento do lançamento',
            'escopo_custos_fixos' => 'competências pela data de competência; pago/pendente pela coluna pago',
        ];
    }

    /**
     * Custo histórico de componentes de OS e produtos de vendas faturadas.
     * Aceita os filtros normalizados da tela ou os argumentos legados de data.
     * Nenhuma junção de itens é feita antes da pré-agregação por OS/venda.
     */
    public function getCustoProdutosPeriodo($dataInicial = null, $dataFinal = null, $cliente = '', $tipo = '', $status = ''): object
    {
        if (is_array($dataInicial)) {
            $filtros = $dataInicial;
            $hoje = (string) ($dataFinal ?: date('Y-m-d'));
            $de = $filtros['de'] ?? null;
            $ate = $filtros['ate'] ?? null;
        } else {
            $filtros = [
                'de' => $dataInicial,
                'ate' => $dataFinal,
                'pesquisa' => '',
                'cliente' => $cliente,
                'tipo' => $tipo,
                'status' => $status,
            ];
            $hoje = date('Y-m-d');
            $de = $dataInicial;
            $ate = $dataFinal;
        }

        $de = $this->normalizarDataFinanceiro($de);
        $ate = $this->normalizarDataFinanceiro($ate);
        if ($de === null || $ate === null) {
            return (object) ['custo_os' => 0.0, 'custo_vendas' => 0.0, 'custo_total' => 0.0, 'quantidade_os' => 0, 'quantidade_vendas' => 0];
        }
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }
        $filtros['de'] = $de;
        $filtros['ate'] = $ate;
        $filtros['hoje'] = $this->normalizarDataFinanceiro($hoje) ?: date('Y-m-d');
        $whereLancamento = $this->sqlFiltrosCusto($filtros);
        $faturaOs = $this->db->dbdriver === 'sqlite3'
            ? "('Fatura de OS Nº: ' || o.idOs || ' '), ('Fatura de OS Nº: ' || o.idOs), ('Fatura de OS - #' || o.idOs)"
            : "CONCAT('Fatura de OS Nº: ', o.idOs, ' '), CONCAT('Fatura de OS Nº: ', o.idOs), CONCAT('Fatura de OS - #', o.idOs)";
        $tipoDecimal = $this->db->dbdriver === 'sqlite3' ? 'REAL' : 'DECIMAL(20,4)';

        $matchOs = "((o.lancamento IS NOT NULL AND l.idLancamentos = o.lancamento) OR ((o.lancamento IS NULL OR NOT EXISTS (SELECT 1 FROM lancamentos l_fk WHERE l_fk.idLancamentos = o.lancamento AND l_fk.tipo = 'receita')) AND l.descricao IN ({$faturaOs})))";
        $sqlOs = "SELECT COALESCE(SUM(ag.total), 0) AS total, COUNT(*) AS quantidade
            FROM (
                SELECT o.idOs, SUM(CAST(po.custo_total AS {$tipoDecimal})) AS total
                FROM os o
                INNER JOIN produtos_os po ON po.os_id = o.idOs
                WHERE o.faturado = 1
                  AND EXISTS (
                      SELECT 1 FROM lancamentos l
                      WHERE {$whereLancamento} AND l.tipo = 'receita' AND {$matchOs}
                  )
                GROUP BY o.idOs
            ) ag";
        $os = $this->db->query($sqlOs)->row();

        $sqlVendas = "SELECT COALESCE(SUM(ag.total), 0) AS total, COUNT(*) AS quantidade
            FROM (
                SELECT v.idVendas, iv.total
                FROM vendas v
                INNER JOIN (
                    SELECT vendas_id, SUM(CAST(custo_total AS {$tipoDecimal})) AS total
                    FROM itens_de_vendas
                    GROUP BY vendas_id
                ) iv ON iv.vendas_id = v.idVendas
                WHERE v.faturado = 1
                  AND EXISTS (
                      SELECT 1 FROM lancamentos l
                      WHERE {$whereLancamento} AND l.tipo = 'receita'
                        AND l.descricao LIKE 'Fatura de Venda%'
                        AND (l.idLancamentos = v.lancamentos_id OR l.vendas_id = v.idVendas)
                  )
                GROUP BY v.idVendas, iv.total
            ) ag";
        $vendas = $this->db->query($sqlVendas)->row();

        $osCentavos = $this->centavos($os->total ?? 0);
        $vendasCentavos = $this->centavos($vendas->total ?? 0);

        return (object) [
            'custo_os' => $this->reais($osCentavos),
            'custo_vendas' => $this->reais($vendasCentavos),
            'custo_total' => $this->reais($osCentavos + $vendasCentavos),
            'quantidade_os' => (int) ($os->quantidade ?? 0),
            'quantidade_vendas' => (int) ($vendas->quantidade ?? 0),
        ];
    }

    /** Total de competências do período, sem projetar nem gerar registros. */
    public function getCustosFixosCompetenciaPeriodo($dataInicial, $dataFinal): float
    {
        return (float) $this->getTotaisCustosFixosCompetencia($dataInicial, $dataFinal)->total;
    }

    /** Compatibilidade com consumidores legados do relatório financeiro. */
    public function getCustosFixosPeriodo($dataInicial = null, $dataFinal = null): float
    {
        return $this->getCustosFixosCompetenciaPeriodo($dataInicial, $dataFinal);
    }

    /** Soma competências existentes e separa pagas de pendentes. */
    public function getTotaisCustosFixosCompetencia($dataInicial, $dataFinal): object
    {
        $inicio = $this->normalizarDataFinanceiro($dataInicial);
        $fim = $this->normalizarDataFinanceiro($dataFinal);
        if ($inicio === null || $fim === null) {
            return (object) ['pago' => 0.0, 'a_pagar' => 0.0, 'total' => 0.0, 'competencias_pagas' => 0, 'competencias_pendentes' => 0];
        }
        if ($inicio > $fim) {
            [$inicio, $fim] = [$fim, $inicio];
        }
        $inicio = (new DateTimeImmutable($inicio))->modify('first day of this month')->format('Y-m-d');
        $fim = (new DateTimeImmutable($fim))->modify('first day of this month')->format('Y-m-d');
        $inicioSql = $this->db->escape($inicio);
        $fimSql = $this->db->escape($fim);
        $row = $this->db->query("SELECT
                COALESCE(SUM(valor), 0) AS total,
                COALESCE(SUM(CASE WHEN pago = 1 THEN valor ELSE 0 END), 0) AS pago,
                COALESCE(SUM(CASE WHEN pago = 1 THEN 0 ELSE valor END), 0) AS a_pagar,
                COALESCE(SUM(CASE WHEN pago = 1 THEN 1 ELSE 0 END), 0) AS competencias_pagas,
                COALESCE(SUM(CASE WHEN pago = 1 THEN 0 ELSE 1 END), 0) AS competencias_pendentes
            FROM custos_fixos_competencias
            WHERE competencia >= {$inicioSql} AND competencia <= {$fimSql}")->row();
        $totalCentavos = $this->centavos($row->total ?? 0);
        $pagoCentavos = $this->centavos($row->pago ?? 0);
        $aPagarCentavos = $this->centavos($row->a_pagar ?? 0);

        return (object) [
            'pago' => $this->reais($pagoCentavos),
            'a_pagar' => $this->reais($aPagarCentavos),
            'total' => $this->reais($totalCentavos),
            'competencias_pagas' => (int) ($row->competencias_pagas ?? 0),
            'competencias_pendentes' => (int) ($row->competencias_pendentes ?? 0),
        ];
    }

    /** @return string SQL seguro para o alias l dos lançamentos-fatura. */
    private function sqlFiltrosCusto(array $filtros): string
    {
        $where = [];
        $where[] = 'l.data_vencimento >= ' . $this->db->escape($filtros['de']);
        $where[] = 'l.data_vencimento <= ' . $this->db->escape($filtros['ate']);

        $tipo = (string) ($filtros['tipo'] ?? '');
        if (in_array($tipo, ['receita', 'despesa'], true)) {
            $where[] = 'l.tipo = ' . $this->db->escape($tipo);
        }
        $cliente = trim((string) ($filtros['cliente'] ?? ''));
        if ($cliente !== '') {
            $likeCliente = $this->db->escape('%' . $this->db->escape_like_str($cliente) . '%');
            $where[] = "l.cliente_fornecedor LIKE {$likeCliente} ESCAPE '!'";
        }
        $pesquisa = trim((string) ($filtros['pesquisa'] ?? ''));
        if ($pesquisa !== '') {
            $likePesquisa = $this->db->escape('%' . $this->db->escape_like_str($pesquisa) . '%');
            $pesquisaWhere = [
                "l.cliente_fornecedor LIKE {$likePesquisa} ESCAPE '!'",
                "l.descricao LIKE {$likePesquisa} ESCAPE '!'",
                "l.observacoes LIKE {$likePesquisa} ESCAPE '!'",
            ];
            if (ctype_digit($pesquisa)) {
                $pesquisaWhere[] = 'l.idLancamentos = ' . (int) $pesquisa;
            }
            $where[] = '(' . implode(' OR ', $pesquisaWhere) . ')';
        }
        switch ((string) ($filtros['status'] ?? '')) {
            case 'pago':
                $where[] = 'l.baixado = 1';
                break;
            case 'pendente':
                $where[] = '(l.baixado = 0 OR l.baixado IS NULL)';
                break;
            case 'vencido':
                $where[] = '(l.baixado = 0 OR l.baixado IS NULL)';
                $where[] = 'l.data_vencimento < ' . $this->db->escape($filtros['hoje'] ?? date('Y-m-d'));
                break;
        }

        $pesquisa = trim((string) ($filtros['pesquisa'] ?? ''));
        if ($pesquisa !== '') {
            $like = $this->db->escape('%' . $this->db->escape_like_str($pesquisa) . '%');
            $pesquisaWhere = [
                "l.cliente_fornecedor LIKE {$like} ESCAPE '!'",
                "l.descricao LIKE {$like} ESCAPE '!'",
                "l.observacoes LIKE {$like} ESCAPE '!'",
            ];
            if (ctype_digit($pesquisa)) {
                $pesquisaWhere[] = 'l.idLancamentos = ' . (int) $pesquisa;
            }
            $where[] = '(' . implode(' OR ', $pesquisaWhere) . ')';
        }

        return implode(' AND ', $where);
    }

    private function normalizarDataFinanceiro($data): ?string
    {
        $data = trim((string) $data);
        if ($data === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            $dt = DateTime::createFromFormat($formato, $data);
            $erros = DateTime::getLastErrors();
            if ($dt instanceof DateTime && ($erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0)) && $dt->format($formato) === $data) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    private function centavos($valor): int
    {
        return (int) round((float) $valor * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function reais(int $centavos): float
    {
        return $centavos / 100;
    }

    /**
     * Visão geral de todos os lançamentos, sem filtro: o realizado (pago), o
     * que falta receber e pagar e os descontos dados.
     *
     * @return array<string, float>  saldo_realizado, a_receber, a_pagar, descontos
     */
    public function visaoGeral(): array
    {
        $liquido = self::LIQUIDO;
        $pendente = '(lancamentos.baixado = 0 OR lancamentos.baixado IS NULL)';

        $linha = (array) $this->db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN lancamentos.baixado = 1 AND lancamentos.tipo = 'receita' THEN {$liquido} END), 0) AS receitas_pagas,
                COALESCE(SUM(CASE WHEN lancamentos.baixado = 1 AND lancamentos.tipo = 'despesa' THEN {$liquido} END), 0) AS despesas_pagas,
                COALESCE(SUM(CASE WHEN {$pendente} AND lancamentos.tipo = 'receita' THEN {$liquido} END), 0) AS a_receber,
                COALESCE(SUM(CASE WHEN {$pendente} AND lancamentos.tipo = 'despesa' THEN {$liquido} END), 0) AS a_pagar,
                COALESCE(SUM(lancamentos.valor - ({$liquido})), 0) AS descontos
            FROM lancamentos"
        )->row();

        return [
            'saldo_realizado' => round((float) $linha['receitas_pagas'] - (float) $linha['despesas_pagas'], 2),
            'a_receber' => (float) $linha['a_receber'],
            'a_pagar' => (float) $linha['a_pagar'],
            'descontos' => (float) $linha['descontos'],
        ];
    }

    /**
     * Lançamentos em aberto do vencimento mais antigo para o mais novo, com o
     * líquido, para a lista do painel inicial (#2847).
     */
    public function proximosPendentes(int $limite): array
    {
        return $this->db
            ->select('lancamentos.idLancamentos, lancamentos.tipo, lancamentos.descricao, lancamentos.cliente_fornecedor, lancamentos.data_vencimento')
            ->select('(' . self::LIQUIDO . ') AS liquido', false)
            ->from('lancamentos')
            ->group_start()->where('lancamentos.baixado', 0)->or_where('lancamentos.baixado IS NULL', null, false)->group_end()
            ->order_by('lancamentos.data_vencimento', 'ASC')
            ->order_by('lancamentos.idLancamentos', 'ASC')
            ->limit($limite)
            ->get()
            ->result();
    }

    /**
     * Balanço financeiro anual do painel, agrupado por mês.
     *
     * Receita é restrita a faturamentos reais de OS/Venda, como no LIVE; uma
     * receita manual não deve alterar o gráfico operacional. Além das receitas
     * e despesas pagas, as linhas carregam o custo histórico dos itens faturados,
     * custos fixos por competência e receitas reais pendentes.
     *
     * A Receita Líquida é montada no helper como faturamento real - custo real
     * dos itens/componentes. Despesas gerais continuam como série separada.
     */
    public function balancoAnual(int $ano): array
    {
        $ano = (int) $ano;
        $anoAtual = (int) date('Y');
        if ($ano < 2000 || $ano > $anoAtual + 1) {
            $ano = $anoAtual;
        }

        $inicio = sprintf('%04d-01-01', $ano);
        $fim = $ano < $anoAtual ? sprintf('%04d-12-31', $ano) : ($ano === $anoAtual ? date('Y-m-d') : sprintf('%04d-01-01', $ano));
        if ($inicio > $fim) {
            return [];
        }

        $inicioSql = $this->db->escape($inicio);
        $fimSql = $this->db->escape($fim);
        $liquido = $this->liquidoSql('l');
        $tipoDecimalCompetencia = $this->db->dbdriver === 'sqlite3' ? 'REAL' : 'DECIMAL(20, 4)';
        $real = $this->faturamentoRealWhere('l');
        $linhas = [];

        $adicionar = function (string $sql) use (&$linhas): void {
            foreach ($this->db->query($sql)->result() as $linha) {
                $linhas[] = $linha;
            }
        };

        $adicionar("SELECT SUBSTR(l.data_pagamento, 1, 7) AS mes, 'receita' AS tipo,
                ROUND(SUM({$liquido}), 2) AS total
            FROM lancamentos l
            WHERE l.baixado = 1 AND l.tipo = 'receita'
              AND l.data_pagamento >= {$inicioSql} AND l.data_pagamento <= {$fimSql}
              AND {$real}
            GROUP BY mes");

        $adicionar("SELECT SUBSTR(l.data_pagamento, 1, 7) AS mes, 'despesa' AS tipo,
                ROUND(SUM({$liquido}), 2) AS total
            FROM lancamentos l
            WHERE l.baixado = 1 AND l.tipo = 'despesa'
              AND l.data_pagamento >= {$inicioSql} AND l.data_pagamento <= {$fimSql}
            GROUP BY mes");

        $adicionar("SELECT SUBSTR(l.data_vencimento, 1, 7) AS mes, 'inadimplencia' AS tipo,
                ROUND(SUM({$liquido}), 2) AS total
            FROM lancamentos l
            WHERE (l.baixado = 0 OR l.baixado IS NULL) AND l.tipo = 'receita'
              AND l.data_vencimento >= {$inicioSql} AND l.data_vencimento <= {$fimSql}
              AND {$real}
            GROUP BY mes");

        $faturaOsCompetencia = $this->db->dbdriver === 'sqlite3'
            ? "('Fatura de OS Nº: ' || o.idOs || ' '), ('Fatura de OS Nº: ' || o.idOs), ('Fatura de OS - #' || o.idOs)"
            : "CONCAT('Fatura de OS Nº: ', o.idOs, ' '), CONCAT('Fatura de OS Nº: ', o.idOs), CONCAT('Fatura de OS - #', o.idOs)";
        $matchOs = "((o.lancamento IS NOT NULL AND o.lancamento = l.idLancamentos)
            OR ((o.lancamento IS NULL OR NOT EXISTS (
                SELECT 1 FROM lancamentos l_fk
                WHERE l_fk.idLancamentos = o.lancamento AND l_fk.tipo = 'receita'
            )) AND l.descricao IN ({$faturaOsCompetencia})))";
        $adicionar("SELECT SUBSTR(l.data_pagamento, 1, 7) AS mes, 'custo_os' AS tipo,
                ROUND(SUM(CAST(po.custo_total AS {$tipoDecimalCompetencia})), 2) AS total
            FROM lancamentos l
            INNER JOIN os o ON o.faturado = 1 AND {$matchOs}
            INNER JOIN produtos_os po ON po.os_id = o.idOs
            WHERE l.baixado = 1 AND l.tipo = 'receita'
              AND l.data_pagamento >= {$inicioSql} AND l.data_pagamento <= {$fimSql}
              AND l.descricao LIKE 'Fatura de OS%'
            GROUP BY mes");

        $adicionar("SELECT SUBSTR(l.data_pagamento, 1, 7) AS mes, 'custo_vendas' AS tipo,
                ROUND(SUM(CAST(iv.custo_total AS {$tipoDecimalCompetencia})), 2) AS total
            FROM lancamentos l
            INNER JOIN vendas v ON v.faturado = 1
                AND (v.lancamentos_id = l.idLancamentos OR v.idVendas = l.vendas_id)
            INNER JOIN itens_de_vendas iv ON iv.vendas_id = v.idVendas
            WHERE l.baixado = 1 AND l.tipo = 'receita'
              AND l.data_pagamento >= {$inicioSql} AND l.data_pagamento <= {$fimSql}
              AND l.descricao LIKE 'Fatura de Venda%'
            GROUP BY mes");

        $fimCompetencia = $ano < $anoAtual ? sprintf('%04d-12-01', $ano) : ($ano === $anoAtual ? date('Y-m-01') : null);
        if ($fimCompetencia !== null) {
            $fimCompetenciaSql = $this->db->escape($fimCompetencia);
            $adicionar("SELECT SUBSTR(competencia, 1, 7) AS mes,
                    CASE WHEN pago = 1 THEN 'custos_fixos_pago' ELSE 'custos_fixos_a_pagar' END AS tipo,
                    ROUND(SUM(valor), 2) AS total
                FROM custos_fixos_competencias
                WHERE competencia >= {$inicioSql} AND competencia <= {$fimCompetenciaSql}
                GROUP BY mes, tipo");
        }

        return $linhas;
    }

    /**
     * Série dos últimos N meses para os sparklines dos KPIs.
     * Meses sem movimento entram com zero, em ordem cronológica.
     * O contrato da listagem usa o mesmo líquido oficial dos lançamentos.
     *
     * @return array{receitas: list<float>, despesas: list<float>, saldo: list<float>}
     */
    public function serieMensal(int $meses, string $hoje): array
    {
        $meses = max(2, $meses);
        $inicio = date('Y-m-01', strtotime(date('Y-m-01', strtotime($hoje)) . ' -' . ($meses - 1) . ' months'));
        $liquido = $this->liquidoSql('lancamentos');
        $linhas = $this->db
            ->select("SUBSTR(lancamentos.data_pagamento, 1, 7) AS mes, lancamentos.tipo", false)
            ->select("SUM({$liquido}) AS total", false)
            ->from('lancamentos')
            ->where('lancamentos.baixado', 1)
            ->where('lancamentos.data_pagamento >=', $inicio)
            ->where('lancamentos.data_pagamento <=', $hoje)
            ->group_by(['mes', 'lancamentos.tipo'])
            ->get()
            ->result();

        $receitas = [];
        $despesas = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $chave = date('Y-m', strtotime(date('Y-m-01', strtotime($hoje)) . ' -' . $i . ' months'));
            $receitas[$chave] = 0.0;
            $despesas[$chave] = 0.0;
        }
        foreach ($linhas as $linha) {
            if (! array_key_exists((string) $linha->mes, $receitas)) {
                continue;
            }
            if ($linha->tipo === 'receita') {
                $receitas[(string) $linha->mes] = (float) $linha->total;
            } else {
                $despesas[(string) $linha->mes] = (float) $linha->total;
            }
        }

        $rec = array_values($receitas);
        $des = array_values($despesas);

        return [
            'receitas' => $rec,
            'despesas' => $des,
            'saldo' => array_map(static fn ($r, $d) => round($r - $d, 2), $rec, $des),
        ];
    }

    /** SQL do líquido oficial de um lançamento, para um alias explícito. */
    private function liquidoSql(string $alias): string
    {
        return "CASE WHEN {$alias}.valor_desconto > 0 THEN {$alias}.valor_desconto ELSE {$alias}.valor - COALESCE({$alias}.desconto, 0) END";
    }

    /** SQL que identifica somente faturas reais de OS/Venda faturadas. */
    private function faturamentoRealWhere(string $alias): string
    {
        $faturaOs = $this->db->dbdriver === 'sqlite3'
            ? "('Fatura de OS Nº: ' || o_real.idOs || ' '), ('Fatura de OS Nº: ' || o_real.idOs), ('Fatura de OS - #' || o_real.idOs)"
            : "CONCAT('Fatura de OS Nº: ', o_real.idOs, ' '), CONCAT('Fatura de OS Nº: ', o_real.idOs), CONCAT('Fatura de OS - #', o_real.idOs)";

        return "(({$alias}.descricao LIKE 'Fatura de OS%' AND EXISTS (
                    SELECT 1 FROM os o_real
                    WHERE o_real.faturado = 1
                      AND ((o_real.lancamento IS NOT NULL AND o_real.lancamento = {$alias}.idLancamentos)
                        OR ((o_real.lancamento IS NULL OR NOT EXISTS (
                            SELECT 1 FROM lancamentos l_fk_real
                            WHERE l_fk_real.idLancamentos = o_real.lancamento AND l_fk_real.tipo = 'receita'
                        )) AND {$alias}.descricao IN ({$faturaOs})))
                )) OR ({$alias}.descricao LIKE 'Fatura de Venda%' AND EXISTS (
                    SELECT 1 FROM vendas v_real
                    WHERE v_real.faturado = 1
                      AND (v_real.lancamentos_id = {$alias}.idLancamentos OR v_real.idVendas = {$alias}.vendas_id)
                )))";
    }

    /** Lançamento com o nome de quem o alterou por último, ou null. */
    public function getLancamento(int $id): ?object
    {
        return $this->db
            ->select('lancamentos.*, usuarios.nome AS modificado_por')
            ->from('lancamentos')
            ->join('usuarios', 'usuarios.idUsuarios = lancamentos.usuarios_id', 'left')
            ->where('lancamentos.idLancamentos', $id)
            ->limit(1)
            ->get()
            ->row() ?: null;
    }

    /** Cliente do cadastro (para conferir o id escolhido na sugestão), ou null. */
    public function getCliente(int $id): ?object
    {
        return $this->db->select('idClientes, nomeCliente')->where('idClientes', $id)->get('clientes', 1)->row() ?: null;
    }

    /** Grava um lançamento e devolve o id, ou null se não gravou. */
    public function adicionar(array $dados): ?int
    {
        $this->db->insert('lancamentos', $dados);

        return $this->db->affected_rows() === 1 ? (int) $this->db->insert_id() : null;
    }

    /**
     * Grava várias linhas (entrada e parcelas) de uma vez: ou entram todas ou
     * nenhuma.
     *
     * @param  list<array<string, mixed>>  $linhas
     */
    public function adicionarVarios(array $linhas): bool
    {
        $this->db->trans_begin();

        foreach ($linhas as $linha) {
            $this->db->insert('lancamentos', $linha);
            if ($this->db->affected_rows() !== 1) {
                $this->db->trans_rollback();

                return false;
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return false;
        }

        $this->db->trans_commit();

        return true;
    }

    /** Atualiza o lançamento (linha existente) e devolve se deu certo. */
    public function atualizar(int $id, array $dados): bool
    {
        $this->db->where('idLancamentos', $id)->update('lancamentos', $dados);

        return $this->db->affected_rows() >= 0;
    }

    /**
     * Exclui o lançamento. Se ele é a fatura de uma venda ou de uma OS, o
     * vínculo cai antes (a chave estrangeira impediria a exclusão) e a venda
     * ou a OS voltam a não faturadas, com status Finalizado; a mudança da OS
     * entra no histórico dela. Tudo numa transação.
     */
    public function excluir(int $id, ?int $usuario): bool
    {
        $this->db->trans_begin();

        $this->db->set('lancamentos_id', null)->set('faturado', 0)->set('status', 'Finalizado')
            ->where('lancamentos_id', $id)->update('vendas');

        $faturadas = $this->db->select('idOs, status')->where('lancamento', $id)->get('os')->result();
        foreach ($faturadas as $os) {
            $this->osModel()->registrarStatus((int) $os->idOs, $os->status, 'Finalizado', $usuario);
        }
        $this->db->set('lancamento', null)->set('faturado', 0)->set('status', 'Finalizado')
            ->where('lancamento', $id)->update('os');

        $this->db->where('idLancamentos', $id)->delete('lancamentos');
        $apagou = $this->db->affected_rows() === 1;

        if (! $apagou || $this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return false;
        }

        $this->db->trans_commit();

        return true;
    }

    public function getCustosFixos($categoria = '', $status = '', $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $this->db->select('custos_fixos.*, usuarios.nome AS usuario_nome')
            ->from('custos_fixos')
            ->join('usuarios', 'usuarios.idUsuarios = custos_fixos.usuarios_id', 'left');
        if ($categoria !== '' && $categoria !== null) {
            $this->db->like('custos_fixos.categoria', $categoria);
        }
        if ($status !== '' && $status !== null) {
            $this->db->where('custos_fixos.ativo', (int) $status);
        }
        $this->db->order_by('custos_fixos.ativo', 'desc')
            ->order_by('custos_fixos.categoria', 'asc')
            ->order_by('custos_fixos.titulo', 'asc');
        if ((int) $perpage > 0) {
            $this->db->limit($perpage, $start);
        }
        $query = $this->db->get();

        return ! $one ? $query->result() : $query->row();
    }

    public function getCustoFixoById(int $id): ?object
    {
        return $this->db->select('custos_fixos.*, usuarios.nome AS usuario_nome')
            ->from('custos_fixos')
            ->join('usuarios', 'usuarios.idUsuarios = custos_fixos.usuarios_id', 'left')
            ->where('custos_fixos.idCustoFixo', $id)
            ->limit(1)
            ->get()
            ->row() ?: null;
    }

    private function normalizarDataCustoFixo($data): ?string
    {
        $data = trim((string) $data);
        if ($data === '') {
            return null;
        }
        foreach (['d/m/Y', 'Y-m-d'] as $formato) {
            $dt = DateTime::createFromFormat($formato, $data);
            if ($dt instanceof DateTime && $dt->format($formato) === $data) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    public function custoFixoAtivoNoMes(object $custo, DateTime $inicioMes, DateTime $fimMes): bool
    {
        if ((int) $custo->ativo !== 1 || strtolower((string) ($custo->periodicidade ?? 'mensal')) !== 'mensal') {
            return false;
        }
        $inicio = $this->normalizarDataCustoFixo($custo->data_inicio ?? null);
        $fim = $this->normalizarDataCustoFixo($custo->data_fim ?? null);

        return ! (($inicio && $inicio > $fimMes->format('Y-m-d')) || ($fim && $fim < $inicioMes->format('Y-m-d')));
    }

    public function calcularDataVencimento(DateTime $competencia, int $dia, string $regra): ?string
    {
        if ($dia < 1) {
            return null;
        }
        $mes = clone $competencia;
        $mes->modify($regra === 'mes_corrente' ? 'first day of this month' : 'first day of next month');
        $dia = min($dia, (int) $mes->format('t'));

        return $mes->format('Y-m-') . str_pad((string) $dia, 2, '0', STR_PAD_LEFT);
    }

    public function gerarCompetencias(int $custoFixoId): bool
    {
        $custo = $this->getCustoFixoById($custoFixoId);
        if (! $custo || (int) $custo->ativo !== 1) {
            return false;
        }
        $inicio = new DateTime($custo->data_inicio ?: 'first day of this month');
        if (! empty($custo->data_fim)) {
            $fim = new DateTime($custo->data_fim);
        } else {
            // Contrato indeterminado: materializa uma janela de projeção de 24 meses,
            // sem transformar essa janela em data_fim persistida do contrato.
            $fim = clone $inicio;
            $fim->modify('+23 months')->modify('last day of this month');
        }
        if (strtolower((string) $custo->periodicidade) === 'unico') {
            $fim = clone $inicio;
        }
        $cursor = new DateTime($inicio->format('Y-m-01'));
        $fimMes = new DateTime($fim->format('Y-m-01'));
        $agora = date('Y-m-d H:i:s');

        // A geração só é chamada por POST de cadastro. A transação mantém a
        // janela idempotente: a UNIQUE (custo_fixo_id, competencia) impede
        // duplicidade mesmo se duas requisições concorrentes chegarem juntas.
        $this->db->trans_begin();
        while ($cursor <= $fimMes) {
            $competencia = $cursor->format('Y-m-d');
            $existe = $this->db->where(['custo_fixo_id' => $custoFixoId, 'competencia' => $competencia])->count_all_results('custos_fixos_competencias');
            if (! $existe) {
                $this->db->insert('custos_fixos_competencias', [
                    'custo_fixo_id' => $custoFixoId,
                    'competencia' => $competencia,
                    'valor' => number_format((float) $custo->valor, 2, '.', ''),
                    'data_vencimento' => $this->calcularDataVencimento($cursor, (int) $custo->dia_vencimento, (string) ($custo->regra_vencimento ?: 'mes_vencido')),
                    'pago' => 0,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
            $cursor->modify('first day of next month');
        }
        $this->db->where('idCustoFixo', $custoFixoId)->update('custos_fixos', ['competencias_geradas' => 1, 'updated_at' => $agora]);
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return false;
        }
        $this->db->trans_commit();

        return true;
    }

    public function getCompetenciasCustoFixo(int $custoFixoId): array
    {
        return $this->db->where('custo_fixo_id', $custoFixoId)->order_by('competencia', 'asc')->get('custos_fixos_competencias')->result();
    }

    public function getCompetenciaById(int $id): ?object
    {
        return $this->db->where('idCompetencia', $id)->limit(1)->get('custos_fixos_competencias')->row() ?: null;
    }

    public function salvarCompetencia(int $custoFixoId, string $competencia, $valor, ?string $dataVencimento = null, ?string $observacoes = null): bool
    {
        $valorNormalizado = valorDecimal((string) $valor);
        $competenciaNormalizada = $this->normalizarDataCustoFixo($competencia);
        if ($valorNormalizado === null || $competenciaNormalizada === null) {
            return false;
        }
        $competenciaNormalizada = (new DateTime($competenciaNormalizada))->modify('first day of this month')->format('Y-m-d');
        $data = ['valor' => $valorNormalizado, 'observacoes' => $observacoes, 'updated_at' => date('Y-m-d H:i:s')];
        $vencimento = $this->normalizarDataCustoFixo($dataVencimento);
        $existing = $this->db->where(['custo_fixo_id' => $custoFixoId, 'competencia' => $competenciaNormalizada])->get('custos_fixos_competencias')->row();
        if ($vencimento !== null) {
            $data['data_vencimento'] = $vencimento;
        } elseif (! $existing) {
            $custo = $this->getCustoFixoById($custoFixoId);
            if (! $custo) {
                return false;
            }
            $data['data_vencimento'] = $this->calcularDataVencimento(new DateTime($competenciaNormalizada), (int) $custo->dia_vencimento, (string) ($custo->regra_vencimento ?: 'mes_vencido'));
        }
        if ($existing) {
            // Editar valor/vencimento/observações não pode apagar o histórico de pagamento.
            $data['pago'] = (int) $existing->pago;
            $data['data_pagamento'] = $existing->data_pagamento;
            $data['observacoes_pagamento'] = $existing->observacoes_pagamento;
            $this->db->where('idCompetencia', (int) $existing->idCompetencia)->update('custos_fixos_competencias', $data);
        } else {
            $data += ['custo_fixo_id' => $custoFixoId, 'competencia' => $competenciaNormalizada, 'pago' => 0, 'created_at' => date('Y-m-d H:i:s')];
            $this->db->insert('custos_fixos_competencias', $data);
        }

        return $this->db->trans_status() !== false;
    }

    public function recalcularCompetenciasNaoPagas(int $custoFixoId): int
    {
        $custo = $this->getCustoFixoById($custoFixoId);
        if (! $custo) {
            return 0;
        }
        $competencias = $this->db->where(['custo_fixo_id' => $custoFixoId, 'pago' => 0])->get('custos_fixos_competencias')->result();
        foreach ($competencias as $competencia) {
            $vencimento = $this->calcularDataVencimento(new DateTime($competencia->competencia), (int) $custo->dia_vencimento, (string) ($custo->regra_vencimento ?: 'mes_vencido'));
            $this->db->where('idCompetencia', (int) $competencia->idCompetencia)->update('custos_fixos_competencias', ['data_vencimento' => $vencimento, 'updated_at' => date('Y-m-d H:i:s')]);
        }

        return count($competencias);
    }

    public function marcarCompetenciaPaga(int $idCompetencia, ?string $dataPagamento = null, ?string $observacoes = null): bool
    {
        $data = $this->normalizarDataCustoFixo($dataPagamento) ?: date('Y-m-d');
        $this->db->where('idCompetencia', $idCompetencia)->update('custos_fixos_competencias', ['pago' => 1, 'data_pagamento' => $data, 'observacoes_pagamento' => $observacoes, 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->db->trans_status() !== false;
    }

    public function desmarcarCompetenciaPaga(int $idCompetencia): bool
    {
        $this->db->where('idCompetencia', $idCompetencia)->update('custos_fixos_competencias', ['pago' => 0, 'data_pagamento' => null, 'observacoes_pagamento' => null, 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->db->trans_status() !== false;
    }

    public function getCompetenciasDashboard(string $mesAtual, string $mesFim): array
    {
        $inicio = new DateTime($mesAtual);
        $fim = new DateTime($mesFim);
        $custos = $this->db->where('ativo', 1)->get('custos_fixos')->result();
        $result = [];
        foreach ($custos as $custo) {
            $competencias = $this->db->where('custo_fixo_id', (int) $custo->idCustoFixo)->order_by('data_vencimento', 'asc')->get('custos_fixos_competencias')->result();
            foreach ($competencias as $competencia) {
                if (! $competencia->data_vencimento || $competencia->data_vencimento === '0000-00-00') {
                    continue;
                }
                $vencimento = new DateTime($competencia->data_vencimento);
                $noMes = $vencimento >= $inicio && $vencimento <= $fim;
                $atrasadoPendente = $vencimento < $inicio && (int) $competencia->pago === 0;
                if (! $noMes && ! $atrasadoPendente) {
                    continue;
                }
                $competencia->titulo_custo = $custo->titulo;
                $competencia->categoria_custo = $custo->categoria;
                $competencia->idCustoFixo = $custo->idCustoFixo;
                $result[] = $competencia;
            }
        }
        usort($result, static fn ($a, $b) => strcmp($a->data_vencimento, $b->data_vencimento));

        return $result;
    }

    public function adicionarCustoFixo(array $dados): bool
    {
        $this->db->insert('custos_fixos', $dados);

        return $this->db->affected_rows() === 1;
    }

    public function getInsertId(): int
    {
        return (int) $this->db->insert_id();
    }

    public function atualizarCustoFixo(int $id, array $dados): bool
    {
        $this->db->where('idCustoFixo', $id)->update('custos_fixos', $dados);

        return $this->db->affected_rows() >= 0;
    }

    /** Exclui cadastro e competências em uma transação; a FK faz o cascade. */
    public function excluirCustoFixo(int $id): bool
    {
        $this->db->trans_begin();
        $this->db->where('idCustoFixo', $id)->delete('custos_fixos');
        $excluiu = $this->db->affected_rows() === 1;
        if (! $excluiu || $this->db->trans_status() === false) {
            $this->db->trans_rollback();

            return false;
        }
        $this->db->trans_commit();

        return true;
    }

    private function osModel(): object
    {
        if ($this->osModel === null) {
            $this->load->model('os_model');
            $this->osModel = $this->os_model;
        }

        return $this->osModel;
    }

    // Sugestões do formulário e do filtro. Sempre devolvem a lista (vazia
    // quando nada casa); o controller responde em JSON.

    /**
     * Clientes do cadastro que casam com o termo, com o id para o vínculo.
     *
     * @return list<array{id: int, label: string, valor: string, detalhe: string}>
     */
    public function sugerirClientes(string $termo): array
    {
        $this->db->select('idClientes, nomeCliente, documento, celular');
        $this->db->group_start()->like('nomeCliente', $termo)->or_like('documento', $termo)->group_end();
        $this->db->order_by('nomeCliente', 'asc')->limit(8);

        $itens = [];
        foreach ($this->db->get('clientes')->result_array() as $linha) {
            $detalhe = array_filter([$linha['documento'], $linha['celular']], static fn ($v) => (string) $v !== '');
            $itens[] = [
                'id' => (int) $linha['idClientes'],
                'label' => $linha['nomeCliente'],
                'valor' => $linha['nomeCliente'],
                'detalhe' => implode(' · ', $detalhe),
            ];
        }

        return $itens;
    }

    /**
     * Nomes já usados em lançamentos (fornecedores e clientes avulsos), sem
     * id: servem só para completar o texto.
     *
     * @return list<array{id: null, label: string, valor: string, detalhe: string}>
     */
    public function sugerirNomesUsados(string $termo): array
    {
        $linhas = $this->db->distinct()->select('cliente_fornecedor')
            ->like('cliente_fornecedor', $termo)
            ->where('cliente_fornecedor IS NOT NULL', null, false)
            ->order_by('cliente_fornecedor', 'asc')
            ->limit(8)
            ->get('lancamentos')
            ->result_array();

        return array_map(static fn ($linha) => [
            'id' => null,
            'label' => $linha['cliente_fornecedor'],
            'valor' => $linha['cliente_fornecedor'],
            'detalhe' => 'Já usado em lançamentos',
        ], $linhas);
    }
}
