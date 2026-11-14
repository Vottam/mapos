<?php

use Piggly\Pix\StaticPayload;

class Os_model extends CI_Model
{
    private ?bool $historicoDisponivel = null;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('os');
    }

    /**
     * Mantém a data prevista separada da data efetiva da entrega.
     * A data efetiva é criada na primeira transição para Entregue, preservada
     * em salvamentos idempotentes e removida ao reverter para outro status.
     */
    public function dadosDaTransicaoDeEntrega(array $dados, ?object $os): array
    {
        return osDadosDaTransicaoDeEntrega($dados, $os);
    }

    /** Compatibilidade para testes e consumidores que consultavam só a data efetiva. */
    public function dataFinalDaEntrega(?string $statusAnterior, ?string $statusNovo): ?string
    {
        return osDataEfetivaDaEntrega($statusAnterior, $statusNovo);
    }

    /** Compatibilidade para consumidores antigos; agora não altera dataFinal. */
    public function dadosComDataFinalDaEntrega(array $dados, ?string $statusAnterior): array
    {
        return $this->dadosDaTransicaoDeEntrega($dados, (object) [
            'status' => $statusAnterior,
            'dataEntrega' => null,
        ]);
    }

    public function normalizarEquipamentoDados(array $dados)
    {
        $tipo = trim((string) ($dados['equipamento'] ?? ''));
        $marca = trim((string) ($dados['marca'] ?? ''));
        $modelo = trim((string) ($dados['modelo'] ?? ''));
        $serie = trim((string) ($dados['num_serie'] ?? ''));

        return [
            'equipamento' => $tipo !== '' ? ucwords(mb_strtolower(preg_replace('/\s+/', ' ', $tipo), 'UTF-8')) : 'Não informado',
            'marca' => $marca !== '' ? ucwords(mb_strtolower(preg_replace('/\s+/', ' ', $marca), 'UTF-8')) : null,
            'modelo' => $modelo !== '' ? preg_replace('/\s+/', ' ', $modelo) : null,
            'num_serie' => $serie !== '' ? $serie : null,
        ];
    }

    public function normalizeSerial($serial)
    {
        return trim((string) $serial);
    }

    public function buildInternalSerial($idOs)
    {
        return 'SN' . str_pad((int) $idOs, 6, '0', STR_PAD_LEFT);
    }

    public function getProximoSerialInternoEstimado()
    {
        $query = $this->db->query("SELECT AUTO_INCREMENT AS proximo_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'os' LIMIT 1");
        $row = $query->row();
        $proximoId = (int) ($row->proximo_id ?? 1);

        if ($proximoId < 1) {
            $proximoId = 1;
        }

        return $this->buildInternalSerial($proximoId);
    }

    public function getEquipamentoBySerial($serial)
    {
        $serial = $this->normalizeSerial($serial);

        if ($serial === '') {
            return null;
        }

        $this->db->select('equipamentos.*, marcas.marca as marca_nome');
        $this->db->from('equipamentos');
        $this->db->join('marcas', 'marcas.idMarcas = equipamentos.marcas_id', 'left');
        $this->db->where('equipamentos.num_serie', $serial);
        $this->db->order_by('equipamentos.idEquipamentos', 'desc');
        $this->db->limit(1);

        return $this->db->get()->row();
    }

    public function get($table, $fields, $where = '', $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $this->db->select($fields . ',clientes.nomeCliente, clientes.celular as celular_cliente');
        $this->db->from($table);
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->limit($perpage, $start);
        $this->db->order_by('idOs', 'desc');
        if ($where) {
            $this->db->where($where);
        }

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        return $result;
    }

    public function getOs($table, $fields, $where = [], $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $pesquisa = trim((string) ($where['pesquisa'] ?? ''));

        $this->db->select($fields . ',clientes.idClientes, clientes.nomeCliente, clientes.celular as celular_cliente, usuarios.nome, garantias.*');
        $this->db->from($table);
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->join('usuarios', 'usuarios.idUsuarios = os.usuarios_id');
        $this->db->join('garantias', 'garantias.idGarantias = os.garantias_id', 'left');
        $this->db->join('produtos_os', 'produtos_os.os_id = os.idOs', 'left');
        $this->db->join('servicos_os', 'servicos_os.os_id = os.idOs', 'left');
        $this->db->join('equipamentos_os', 'equipamentos_os.os_id = os.idOs', 'left');
        $this->db->join('equipamentos', 'equipamentos.idEquipamentos = equipamentos_os.equipamentos_id', 'left');
        $this->db->join('marcas', 'marcas.idMarcas = equipamentos.marcas_id', 'left');

        if (array_key_exists('status', $where)) {
            $this->db->where_in('status', $where['status']);
        }

        if ($pesquisa !== '') {
            $this->db->group_start();
            $this->db->like('clientes.nomeCliente', $pesquisa);
            $this->db->or_like('clientes.documento', $pesquisa);
            $this->db->or_like('os.idOs', $pesquisa);
            $this->db->or_like('os.status', $pesquisa);
            $this->db->or_like('equipamentos.num_serie', $pesquisa);
            $this->db->or_like('equipamentos.modelo', $pesquisa);
            $this->db->or_like('equipamentos.equipamento', $pesquisa);
            $this->db->or_like('marcas.marca', $pesquisa);
            $this->db->group_end();
        }

        if (array_key_exists('de', $where)) {
            $this->db->where('dataInicial >=', $where['de']);
        }
        if (array_key_exists('ate', $where)) {
            $this->db->where('dataFinal <=', $where['ate']);
        }

        $this->db->limit($perpage, $start);
        $this->db->order_by('os.idOs', 'desc');
        $this->db->group_by('os.idOs');

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        foreach ($one ? ($result ? [$result] : []) : $result as $osRow) {
            // Sem Conserto não recebe garantia só por ter dataFinal preenchida.
            if (($osRow->status ?? null) === 'Sem Conserto') {
                $osRow->garantia = 0;
            }
        }

        return $result;
    }

    public function countOs(array $where = [])
    {
        $pesquisa = trim((string) ($where['pesquisa'] ?? ''));

        $this->db->select('COUNT(DISTINCT os.idOs) as total', false);
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->join('usuarios', 'usuarios.idUsuarios = os.usuarios_id');
        $this->db->join('garantias', 'garantias.idGarantias = os.garantias_id', 'left');
        $this->db->join('produtos_os', 'produtos_os.os_id = os.idOs', 'left');
        $this->db->join('servicos_os', 'servicos_os.os_id = os.idOs', 'left');
        $this->db->join('equipamentos_os', 'equipamentos_os.os_id = os.idOs', 'left');
        $this->db->join('equipamentos', 'equipamentos.idEquipamentos = equipamentos_os.equipamentos_id', 'left');
        $this->db->join('marcas', 'marcas.idMarcas = equipamentos.marcas_id', 'left');

        if (array_key_exists('status', $where)) {
            $this->db->where_in('status', $where['status']);
        }

        if ($pesquisa !== '') {
            $this->db->group_start();
            $this->db->like('clientes.nomeCliente', $pesquisa);
            $this->db->or_like('clientes.documento', $pesquisa);
            $this->db->or_like('os.idOs', $pesquisa);
            $this->db->or_like('os.status', $pesquisa);
            $this->db->or_like('equipamentos.num_serie', $pesquisa);
            $this->db->or_like('equipamentos.modelo', $pesquisa);
            $this->db->or_like('equipamentos.equipamento', $pesquisa);
            $this->db->or_like('marcas.marca', $pesquisa);
            $this->db->group_end();
        }

        if (array_key_exists('de', $where)) {
            $this->db->where('dataInicial >=', $where['de']);
        }
        if (array_key_exists('ate', $where)) {
            $this->db->where('dataFinal <=', $where['ate']);
        }

        $row = $this->db->get()->row();

        return (int) ($row->total ?? 0);
    }

    /**
     * Listagem de OS da v5 (#2842), no padrão das listagens (#2852). Os
     * métodos listar() e contar() usam os mesmos filtros para paginação exata.
     */
    public function listar(array $filtros, int $limite, int $offset, ?array $statusVisiveis = null): array
    {
        $totalProdutos = '(SELECT COALESCE(SUM(produtos_os.subTotal), 0) FROM produtos_os WHERE produtos_os.os_id = os.idOs)';
        $valorServico = 'COALESCE(NULLIF(servicos_os.preco, 0), servicos.preco, 0) * COALESCE(NULLIF(servicos_os.quantidade, 0), 1)';
        if ($this->descontoPorItemDisponivel()) {
            $valorServico = "CASE WHEN servicos_os.desconto > 0 THEN servicos_os.subTotal ELSE {$valorServico} END";
        }
        $totalServicos = "(SELECT COALESCE(SUM({$valorServico}), 0) FROM servicos_os LEFT JOIN servicos ON servicos.idServicos = servicos_os.servicos_id WHERE servicos_os.os_id = os.idOs)";

        $this->db->select('os.idOs, os.dataInicial, os.dataFinal, os.descricaoProduto, os.status, os.faturado, os.desconto, os.valor_desconto, os.clientes_id, os.usuarios_id');
        $this->db->select('clientes.nomeCliente, usuarios.nome AS responsavel');
        $this->db->select($totalProdutos . ' AS totalProdutos', false);
        $this->db->select($totalServicos . ' AS totalServicos', false);
        $this->aplicarFiltros($filtros, $statusVisiveis);

        $linhas = $this->db->order_by('os.idOs', 'desc')->limit($limite, max(0, $offset))->get()->result();
        foreach ($linhas as $linha) {
            $bruto = (float) $linha->totalProdutos + (float) $linha->totalServicos;
            $linha->total = (float) $linha->valor_desconto > 0 ? (float) $linha->valor_desconto : $bruto;
        }

        return $linhas;
    }

    public function contar(array $filtros, ?array $statusVisiveis = null): int
    {
        $this->aplicarFiltros($filtros, $statusVisiveis);

        return (int) $this->db->count_all_results();
    }

    private function aplicarFiltros(array $filtros, ?array $statusVisiveis): void
    {
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id', 'left');
        $this->db->join('usuarios', 'usuarios.idUsuarios = os.usuarios_id', 'left');

        $pesquisa = trim((string) ($filtros['pesquisa'] ?? ''));
        if ($pesquisa !== '') {
            $this->db->group_start()
                ->like('clientes.nomeCliente', $pesquisa)
                ->or_like('clientes.documento', $pesquisa)
                ->or_like('os.descricaoProduto', $pesquisa);
            if (ctype_digit($pesquisa)) {
                $this->db->or_where('os.idOs', (int) $pesquisa);
            }
            $this->db->group_end();
        }

        if (($filtros['status'] ?? '') !== '') {
            $grupo = painelGrupoStatus($filtros['status']);
            if ($grupo !== null) {
                $this->db->where_in('os.status', $grupo);
            } else {
                $this->db->where('os.status', $filtros['status']);
            }
        } elseif ($pesquisa === '' && $statusVisiveis !== null) {
            $this->db->where_in('os.status', $statusVisiveis);
        }

        if (($filtros['de'] ?? '') !== '') {
            $this->db->where('os.dataInicial >=', $filtros['de']);
        }
        if (($filtros['ate'] ?? '') !== '') {
            $this->db->where('os.dataFinal <=', $filtros['ate']);
        }
    }

    public function getById($id)
    {
        $this->db->select('os.*, clientes.*, clientes.celular as celular_cliente, clientes.telefone as telefone_cliente, clientes.contato as contato_cliente, garantias.refGarantia, garantias.textoGarantia, usuarios.telefone as telefone_usuario, usuarios.email as email_usuario, usuarios.nome');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->join('usuarios', 'usuarios.idUsuarios = os.usuarios_id');
        $this->db->join('garantias', 'garantias.idGarantias = os.garantias_id', 'left');
        $this->db->where('os.idOs', $id);
        $this->db->limit(1);

        return $this->db->get()->row();
    }

    public function getEquipamentoOs($osId)
    {
        $this->db->select('equipamentos.*, marcas.marca as marca_nome, equipamentos_os.idEquipamentos_os');
        $this->db->from('equipamentos_os');
        $this->db->join('equipamentos', 'equipamentos.idEquipamentos = equipamentos_os.equipamentos_id');
        $this->db->join('marcas', 'marcas.idMarcas = equipamentos.marcas_id', 'left');
        $this->db->where('equipamentos_os.os_id', $osId);
        $this->db->order_by('equipamentos_os.idEquipamentos_os', 'asc');
        $this->db->limit(1);

        return $this->db->get()->row();
    }

    public function getEquipamentoByIdAndCliente($equipamentoId, $clienteId)
    {
        $this->db->select('equipamentos.*, marcas.marca as marca_nome');
        $this->db->from('equipamentos');
        $this->db->join('marcas', 'marcas.idMarcas = equipamentos.marcas_id', 'left');
        $this->db->where('equipamentos.idEquipamentos', (int) $equipamentoId);
        $this->db->where('equipamentos.clientes_id', (int) $clienteId);

        return $this->db->get()->row();
    }

    public function getEquipamentosCliente($clienteId)
    {
        $this->db->select('equipamentos.idEquipamentos, equipamentos.equipamento, marcas.marca as marca, equipamentos.modelo, equipamentos.num_serie');
        $this->db->from('equipamentos');
        $this->db->join('marcas', 'marcas.idMarcas = equipamentos.marcas_id', 'left');
        $this->db->where('equipamentos.clientes_id', (int) $clienteId);
        $this->db->order_by('equipamentos.equipamento', 'asc');
        $this->db->order_by('equipamentos.modelo', 'asc');
        $this->db->order_by('equipamentos.num_serie', 'asc');

        return $this->db->get()->result();
    }

    public function salvarEquipamentoOs($osId, $clienteId, array $dados, $equipamentoAtual = null, $serialInternoSugerido = false)
    {
        $dados = $this->normalizarEquipamentoDados($dados);
        $tipo = trim((string) ($dados['equipamento'] ?? ''));
        $marca = trim((string) ($dados['marca'] ?? ''));
        $modelo = trim((string) ($dados['modelo'] ?? ''));
        $serie = trim((string) ($dados['num_serie'] ?? ''));
        $clienteId = (int) $clienteId;
        $equipamentoAtual = $equipamentoAtual ?: null;
        $equipamentoAtualId = $equipamentoAtual ? (int) ($equipamentoAtual->idEquipamentos ?? 0) : null;

        if ($serialInternoSugerido) {
            $serie = $this->buildInternalSerial($osId);
        }

        if ($tipo === '' && $marca === '' && $modelo === '' && $serie === '' && ! $equipamentoAtualId) {
            $serie = $this->buildInternalSerial($osId);
        }

        if ($tipo === '' && $marca === '' && $modelo === '' && $serie === '' && $equipamentoAtualId) {
            return null;
        }

        $this->db->trans_start();

        $marcaId = null;
        if ($marca !== null && $marca !== '') {
            $marcaExistente = $this->db->get_where('marcas', ['marca' => $marca], 1)->row();
            if ($marcaExistente) {
                $marcaId = $marcaExistente->idMarcas;
            } else {
                $this->db->insert('marcas', [
                    'marca' => $marca,
                    'cadastro' => date('Y-m-d'),
                    'situacao' => 1,
                ]);
                $marcaId = $this->db->insert_id();
            }
        }

        $equipamentoPorSerial = null;
        if ($serie !== '') {
            $equipamentoPorSerial = $this->getEquipamentoBySerial($serie);
        }

        if ($equipamentoPorSerial) {
            $equipamentoPorSerialId = (int) $equipamentoPorSerial->idEquipamentos;
            $equipamentoPorSerialClienteId = (int) ($equipamentoPorSerial->clientes_id ?? 0);

            // O serial identifica o cadastro. Nunca reutilize silenciosamente
            // um cadastro de outro cliente nem troque o equipamento selecionado
            // por outro registro que coincida apenas pelo serial.
            if ($equipamentoPorSerialClienteId !== $clienteId || ($equipamentoAtualId && $equipamentoPorSerialId !== $equipamentoAtualId)) {
                $this->db->trans_rollback();

                return false;
            }

            $equipamentoData = [
                'equipamento' => $tipo !== '' ? $tipo : $equipamentoPorSerial->equipamento,
                'num_serie' => $equipamentoPorSerial->num_serie,
                'modelo' => $modelo !== '' ? $modelo : $equipamentoPorSerial->modelo,
                'marcas_id' => $marcaId !== null ? $marcaId : $equipamentoPorSerial->marcas_id,
                'clientes_id' => $equipamentoPorSerialClienteId,
            ];

            $this->db->where('idEquipamentos', $equipamentoPorSerialId);
            $this->db->update('equipamentos', $equipamentoData);
            $equipamentoId = $equipamentoPorSerialId;
        } elseif ($equipamentoAtualId) {
            $equipamentoData = [
                'equipamento' => $tipo !== '' ? $tipo : $equipamentoAtual->equipamento,
                'num_serie' => $serie !== '' ? $serie : (!empty($equipamentoAtual->num_serie) ? $equipamentoAtual->num_serie : $this->buildInternalSerial($osId)),
                'modelo' => $modelo !== '' ? $modelo : $equipamentoAtual->modelo,
                'marcas_id' => $marcaId !== null ? $marcaId : $equipamentoAtual->marcas_id,
                'clientes_id' => $clienteId,
            ];

            $this->db->where('idEquipamentos', $equipamentoAtualId);
            $this->db->update('equipamentos', $equipamentoData);
            $equipamentoId = $equipamentoAtualId;
        } else {
            if ($serie === '') {
                $serie = $this->buildInternalSerial($osId);
            }

            $equipamentoData = [
                'equipamento' => $tipo !== '' ? $tipo : 'Não informado',
                'num_serie' => $serie,
                'modelo' => $modelo !== '' ? $modelo : null,
                'marcas_id' => $marcaId,
                'clientes_id' => $clienteId,
            ];

            $this->db->insert('equipamentos', $equipamentoData);
            $equipamentoId = $this->db->insert_id();
        }

        $this->db->where('os_id', $osId);
        $this->db->delete('equipamentos_os');

        $this->db->insert('equipamentos_os', [
            'equipamentos_id' => $equipamentoId,
            'os_id' => $osId,
        ]);

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return false;
        }

        return $equipamentoId;
    }

    public function getHistoricoPorSerial($serial, $osAtualId = null, $limit = 5)
    {
        $serial = $this->normalizeSerial($serial);

        if ($serial === '') {
            return [];
        }

        $this->db->select('os.idOs, os.dataInicial, os.dataFinal, os.dataEntrega, os.status, os.garantia, os.descricaoProduto, os.defeito, os.laudoTecnico, os.observacoes, clientes.nomeCliente as cliente, equipamentos.idEquipamentos, equipamentos.equipamento as tipo, marcas.marca as marca, equipamentos.modelo, equipamentos.num_serie');
        $this->db->from('equipamentos');
        $this->db->join('equipamentos_os', 'equipamentos_os.equipamentos_id = equipamentos.idEquipamentos');
        $this->db->join('os', 'os.idOs = equipamentos_os.os_id');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->join('marcas', 'marcas.idMarcas = equipamentos.marcas_id', 'left');
        $this->db->where('equipamentos.num_serie', $serial);

        if ($osAtualId !== null) {
            $this->db->where('os.idOs !=', (int) $osAtualId);
        }

        $this->db->order_by('os.dataFinal', 'desc');
        $this->db->order_by('os.idOs', 'desc');
        $this->db->limit((int) $limit);

        $rows = $this->db->get()->result();
        $hoje = strtotime(date('Y-m-d'));

        foreach ($rows as $row) {
            $row->vencimento_garantia = null;
            $row->situacao_garantia = 'Indefinido';

            $base = osDataBaseDaGarantia($row->dataFinal ?? null, $row->dataEntrega ?? null, $row->status ?? null);
            if ($base !== null && is_numeric($row->garantia) && (int) $row->garantia > 0) {
                $vencimento = strtotime('+' . (int) $row->garantia . ' days', strtotime($base));
                $row->vencimento_garantia = date('d/m/Y', $vencimento);
                $row->situacao_garantia = $hoje <= $vencimento ? 'Em garantia' : 'Fora de garantia';
            }
        }

        return $rows;
    }

    public function calcularCriticaGarantiaSerial($row)
    {
        if (! $row) {
            return null;
        }

        $baseOsId = (int) ($row->idOs ?? 0);
        $garantiaDias = is_numeric($row->garantia ?? null) ? (int) $row->garantia : 0;
        $dataBaseRaw = osDataBaseDaGarantia($row->dataFinal ?? null, $row->dataEntrega ?? null, $row->status ?? null) ?? '';

        $dataBase = $this->parseDataBanco($dataBaseRaw);
        if (! $dataBase) {
            return [
                'baseOsId' => $baseOsId,
                'status' => 'indefinida',
                'titulo' => 'Não foi possível calcular a garantia',
                'contador' => 'Data final da OS anterior ausente ou inválida',
                'dias_restantes' => 0,
                'dias_vencidos' => 0,
                'data_base' => $dataBaseRaw,
                'data_base_formatada' => '',
                'garantia_dias' => $garantiaDias,
                'data_final_garantia' => '',
                'data_final_garantia_formatada' => '',
                'texto' => 'Verifique manualmente a OS anterior antes de decidir.',
            ];
        }

        if ($garantiaDias <= 0) {
            return [
                'baseOsId' => $baseOsId,
                'status' => 'sem_garantia_registrada',
                'titulo' => 'Sem garantia registrada para a última OS',
                'contador' => '0 dias de garantia registrados',
                'dias_restantes' => 0,
                'dias_vencidos' => 0,
                'data_base' => $dataBase->format('Y-m-d'),
                'data_base_formatada' => $dataBase->format('d/m/Y'),
                'garantia_dias' => 0,
                'data_final_garantia' => '',
                'data_final_garantia_formatada' => '',
                'texto' => 'A última OS deste equipamento não possui prazo de garantia registrado.',
            ];
        }

        $dataFinalGarantia = $dataBase->modify('+' . $garantiaDias . ' days');
        $hoje = new DateTimeImmutable('today');
        $diasRestantes = (int) $hoje->diff($dataFinalGarantia)->format('%r%a');

        if ($diasRestantes >= 0) {
            return [
                'baseOsId' => $baseOsId,
                'status' => 'em_garantia',
                'titulo' => '⚠️ Possível garantia de mão de obra',
                'contador' => 'Restam ' . $diasRestantes . ' dias de garantia',
                'dias_restantes' => $diasRestantes,
                'dias_vencidos' => 0,
                'data_base' => $dataBase->format('Y-m-d'),
                'data_base_formatada' => $dataBase->format('d/m/Y'),
                'garantia_dias' => $garantiaDias,
                'data_final_garantia' => $dataFinalGarantia->format('Y-m-d'),
                'data_final_garantia_formatada' => $dataFinalGarantia->format('d/m/Y'),
                'texto' => 'A última OS deste equipamento ainda está dentro do prazo de garantia. Verifique se o defeito atual tem relação com o serviço anterior antes de confirmar atendimento em garantia.',
            ];
        }

        $diasVencidos = abs($diasRestantes);

        return [
            'baseOsId' => $baseOsId,
            'status' => 'vencida',
            'titulo' => '❌ Fora do prazo de garantia',
            'contador' => 'Garantia vencida há ' . $diasVencidos . ' dias',
            'dias_restantes' => 0,
            'dias_vencidos' => $diasVencidos,
            'data_base' => $dataBase->format('Y-m-d'),
            'data_base_formatada' => $dataBase->format('d/m/Y'),
            'garantia_dias' => $garantiaDias,
            'data_final_garantia' => $dataFinalGarantia->format('Y-m-d'),
            'data_final_garantia_formatada' => $dataFinalGarantia->format('d/m/Y'),
            'texto' => 'Verifique o histórico antes de decidir se haverá atendimento por cortesia ou cobrança normal.',
        ];
    }

    private function parseDataBanco($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        try {
            $data = new DateTimeImmutable($value);
            return $data->format('Y-m-d') === substr($value, 0, 10) ? $data : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function vincularEquipamentoOsExistente($osId, $equipamentoId)
    {
        $this->db->trans_start();
        $this->db->where('os_id', (int) $osId);
        $this->db->delete('equipamentos_os');

        $this->db->insert('equipamentos_os', [
            'equipamentos_id' => (int) $equipamentoId,
            'os_id' => (int) $osId,
        ]);

        $this->db->trans_complete();

        return $this->db->trans_status() !== false;
    }

    public function removerEquipamentoOs($osId)
    {
        $this->db->where('os_id', (int) $osId);
        return $this->db->delete('equipamentos_os');
    }

    public function getByIdCobrancas($id)
    {
        $this->db->select('os.*, clientes.*, clientes.celular as celular_cliente, garantias.refGarantia, garantias.textoGarantia, usuarios.telefone as telefone_usuario, usuarios.email as email_usuario, usuarios.nome,cobrancas.os_id,cobrancas.idCobranca,cobrancas.status');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->join('usuarios', 'usuarios.idUsuarios = os.usuarios_id');
        $this->db->join('cobrancas', 'cobrancas.os_id = os.idOs');
        $this->db->join('garantias', 'garantias.idGarantias = os.garantias_id', 'left');
        $this->db->where('os.idOs', $id);
        $this->db->limit(1);

        return $this->db->get()->row();
    }

    public function getProdutos($id = null)
    {
        $this->db->select('produtos_os.*, produtos.*');
        $this->db->from('produtos_os');
        $this->db->join('produtos', 'produtos.idProdutos = produtos_os.produtos_id');
        $this->db->where('os_id', $id);

        return $this->db->get()->result();
    }

    public function getServicos($id = null)
    {
        $this->db->select('servicos_os.*, servicos.nome, servicos.preco as precoVenda');
        $this->db->from('servicos_os');
        $this->db->join('servicos', 'servicos.idServicos = servicos_os.servicos_id');
        $this->db->where('os_id', $id);

        return $this->db->get()->result();
    }

    public function getProdutoCadastro(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        return $this->db->where('idProdutos', $id)->get('produtos')->row() ?: null;
    }

    public function getServicoCadastro(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        return $this->db->where('idServicos', $id)->get('servicos')->row() ?: null;
    }

    public function getProdutoDaOs(int $idOs, int $idItem): ?object
    {
        if ($idOs <= 0 || $idItem <= 0) {
            return null;
        }

        return $this->db
            ->select('produtos_os.*, produtos.descricao, produtos.precoVenda')
            ->from('produtos_os')
            ->join('produtos', 'produtos.idProdutos = produtos_os.produtos_id')
            ->where('produtos_os.os_id', $idOs)
            ->where('produtos_os.idProdutos_os', $idItem)
            ->get()
            ->row() ?: null;
    }

    public function getServicoDaOs(int $idOs, int $idItem): ?object
    {
        if ($idOs <= 0 || $idItem <= 0) {
            return null;
        }

        return $this->db
            ->select('servicos_os.*, servicos.nome, servicos.preco AS precoVenda')
            ->from('servicos_os')
            ->join('servicos', 'servicos.idServicos = servicos_os.servicos_id')
            ->where('servicos_os.os_id', $idOs)
            ->where('servicos_os.idServicos_os', $idItem)
            ->get()
            ->row() ?: null;
    }

    public function atualizarDescontoDoItem(string $tipo, int $idOs, int $idItem, $tipoDescontoItem, $descontoItem): array
    {
        if (! in_array($tipo, ['produto', 'servico'], true) || $idOs <= 0 || $idItem <= 0) {
            return ['ok' => false, 'status' => 422, 'message' => 'Item da OS inválido.'];
        }
        if (! $this->descontoPorItemDisponivel()) {
            return ['ok' => false, 'status' => 422, 'message' => 'O desconto por item precisa da atualização do banco de dados.'];
        }
        if (! $this->db->trans_begin()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível iniciar o desconto do item.'];
        }

        $os = $this->db->query('SELECT idOs, status, faturado, desconto, tipo_desconto, valor_desconto FROM os WHERE idOs = ? FOR UPDATE', [$idOs])->row();
        if (! $os) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 404, 'message' => 'OS não encontrada.'];
        }
        if (! $this->osEditavelNaTransacao($os)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 409, 'message' => 'Esta OS não pode mais ser alterada.'];
        }

        $tabela = $tipo === 'produto' ? 'produtos_os' : 'servicos_os';
        $chave = $tipo === 'produto' ? 'idProdutos_os' : 'idServicos_os';
        $item = $this->db->where($chave, $idItem)->where('os_id', $idOs)->get($tabela)->row();
        if (! $item) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 404, 'message' => 'Item não encontrado nesta OS.'];
        }

        $preco = (float) ($item->preco ?? 0);
        $quantidade = (float) ($item->quantidade ?? 0);
        if ($tipo === 'servico' && $preco <= 0) {
            $cadastro = $this->getServicoCadastro((int) $item->servicos_id);
            $preco = (float) ($cadastro->preco ?? 0);
        }
        if ($quantidade <= 0) {
            $quantidade = 1.0;
        }

        [$desconto, $erros] = osDescontoDoItem(round($preco * $quantidade, 2), $tipoDescontoItem, $descontoItem);
        if ($erros !== []) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 422, 'message' => 'Confira os campos destacados.', 'erros' => $erros];
        }

        $atualizado = $this->db
            ->where($chave, $idItem)
            ->where('os_id', $idOs)
            ->update($tabela, [
                'subTotal' => $desconto['subTotal'],
                'desconto' => $desconto['desconto'],
                'tipo_desconto' => $desconto['tipo_desconto'],
            ]);
        if ($atualizado === false || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível gravar o desconto do item.'];
        }

        $recalculo = $this->recalcularDescontoGeralDaOs($idOs, $os);
        if (! $recalculo['ok']) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'status' => (int) $recalculo['status'],
                'message' => $recalculo['message'],
                'erros' => $recalculo['erros'] ?? [],
            ];
        }

        if (! $this->db->where('idOs', $idOs)->update('os', $recalculo['data']) || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível atualizar os totais da OS.'];
        }

        if (! $this->db->trans_commit()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível confirmar o desconto do item.'];
        }

        return ['ok' => true, 'status' => 200, 'desconto' => $desconto];
    }

    /** Recalcula somente o total líquido do desconto geral após mudar uma linha. */
    private function recalcularDescontoGeralDaOs(int $idOs, object $os): array
    {
        $bruto = 0.0;
        $produtos = $this->db
            ->select('preco, quantidade, desconto, subTotal')
            ->where('os_id', $idOs)
            ->get('produtos_os')
            ->result();
        foreach ($produtos as $item) {
            $linha = (float) ($item->desconto ?? 0) > 0
                ? (float) ($item->subTotal ?? 0)
                : (float) ($item->preco ?? 0) * ((float) ($item->quantidade ?? 0) ?: 1);
            $bruto += round($linha, 2);
        }

        $servicos = $this->db
            ->select('servicos_os.preco, servicos_os.quantidade, servicos_os.desconto, servicos_os.subTotal, servicos.preco AS precoCadastro')
            ->from('servicos_os')
            ->join('servicos', 'servicos.idServicos = servicos_os.servicos_id')
            ->where('servicos_os.os_id', $idOs)
            ->get()
            ->result();
        foreach ($servicos as $item) {
            $preco = (float) ($item->preco ?? 0) ?: (float) ($item->precoCadastro ?? 0);
            $linha = (float) ($item->desconto ?? 0) > 0
                ? (float) ($item->subTotal ?? 0)
                : $preco * ((float) ($item->quantidade ?? 0) ?: 1);
            $bruto += round($linha, 2);
        }
        $bruto = round($bruto, 2);

        $descontoGeral = (float) ($os->desconto ?? 0);
        if ($descontoGeral <= 0) {
            return ['ok' => true, 'data' => ['valor_desconto' => 0.00]];
        }

        [$dados, $erros] = osCalcularDesconto($bruto, $os->tipo_desconto ?? null, $descontoGeral);
        if ($erros !== [] || $dados === null) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'O desconto individual não foi aplicado porque o desconto geral existente ficaria inválido para o novo total da OS.',
                'erros' => ['desconto' => 'Revise ou remova o desconto geral antes de reduzir mais o valor da OS.'],
            ];
        }

        return ['ok' => true, 'data' => ['valor_desconto' => $dados['valor_desconto']]];
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
        $this->db->like('codDeBarra', $q);
        $this->db->or_like('descricao', $q);
        $query = $this->db->get('produtos');
        $row_set = [];
        foreach ($query->result_array() as $row) {
            $row_set[] = ['label' => $row['descricao'] . ' | Preço: R$ ' . $row['precoVenda'] . ' | Estoque: ' . $row['estoque'], 'estoque' => $row['estoque'], 'id' => $row['idProdutos'], 'preco' => $row['precoVenda']];
        }

        return $row_set;
    }

    public function autoCompleteProdutoSaida($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('codDeBarra', $q);
        $this->db->or_like('descricao', $q);
        $this->db->where('saida', 1);
        $query = $this->db->get('produtos');
        $row_set = [];
        foreach ($query->result_array() as $row) {
            $row_set[] = ['label' => $row['descricao'] . ' | Preço: R$ ' . $row['precoVenda'] . ' | Estoque: ' . $row['estoque'], 'estoque' => $row['estoque'], 'id' => $row['idProdutos'], 'preco' => $row['precoVenda']];
        }

        return $row_set;
    }

    public function autoCompleteCliente($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('nomeCliente', $q);
        $this->db->or_like('telefone', $q);
        $this->db->or_like('celular', $q);
        $this->db->or_like('documento', $q);
        $query = $this->db->get('clientes');
        $row_set = [];
        foreach ($query->result_array() as $row) {
            $row_set[] = ['label' => $row['nomeCliente'] . ' | Telefone: ' . $row['telefone'] . ' | Celular: ' . $row['celular'] . ' | Documento: ' . $row['documento'], 'id' => $row['idClientes']];
        }

        return $row_set;
    }

    public function autoCompleteUsuario($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('nome', $q);
        $this->db->where('situacao', 1);
        $query = $this->db->get('usuarios');
        $row_set = [];
        foreach ($query->result_array() as $row) {
            $row_set[] = ['label' => $row['nome'] . ' | Telefone: ' . $row['telefone'], 'id' => $row['idUsuarios']];
        }

        return $row_set;
    }

    public function autoCompleteTermoGarantia($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('LOWER(refGarantia)', $q);
        $query = $this->db->get('garantias');
        $row_set = [];
        foreach ($query->result_array() as $row) {
            $row_set[] = ['label' => $row['refGarantia'], 'id' => $row['idGarantias']];
        }

        return $row_set;
    }

    public function autoCompleteServico($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('nome', $q);
        $query = $this->db->get('servicos');
        $row_set = [];
        foreach ($query->result_array() as $row) {
            $row_set[] = [
                'label' => $row['nome'] . ' | Preço: R$ ' . $row['preco'],
                'valor' => $row['nome'],
                'detalhe' => 'Preço: R$ ' . $row['preco'],
                'id' => $row['idServicos'],
                'preco' => $row['preco'],
            ];
        }

        return $row_set;
    }

    public function anexar($os, $anexo, $url, $thumb, $path)
    {
        $this->db->set('anexo', $anexo);
        $this->db->set('url', $url);
        $this->db->set('thumb', $thumb);
        $this->db->set('path', $path);
        $this->db->set('os_id', $os);

        return $this->db->insert('anexos');
    }

    public function getAnexos($os)
    {
        $this->db->where('os_id', $os);

        return $this->db->get('anexos')->result();
    }

    public function getAnotacoes($os)
    {
        $this->db->where('os_id', $os);
        $this->db->order_by('idAnotacoes', 'desc');

        return $this->db->get('anotacoes_os')->result();
    }

    public function getCobrancas($id = null)
    {
        $this->db->select('cobrancas.*');
        $this->db->from('cobrancas');
        $this->db->where('os_id', $id);

        return $this->db->get()->result();
    }

    public function criarTextoWhats($textoBase, $troca)
    {
        $procura = ['{CLIENTE_NOME}', '{NUMERO_OS}', '{STATUS_OS}', '{VALOR_OS}', '{DESCRI_PRODUTOS}', '{EMITENTE}', '{TELEFONE_EMITENTE}', '{OBS_OS}', '{DEFEITO_OS}', '{LAUDO_OS}', '{DATA_FINAL}', '{DATA_INICIAL}', '{DATA_GARANTIA}'];
        $textoBase = str_replace($procura, $troca, $textoBase);
        $textoBase = strip_tags($textoBase);
        $textoBase = htmlentities(urlencode($textoBase));

        return $textoBase;
    }

    public function valorTotalOS($id = null)
    {
        $totalServico = 0.0;
        $totalProdutos = 0.0;
        $descontoItens = 0.0;
        $valorDesconto = 0.0;
        if ($servicos = $this->getServicos($id)) {
            foreach ($servicos as $s) {
                $preco = (float) ($s->preco ?? 0) ?: (float) ($s->precoVenda ?? 0);
                $bruto = round($preco * ((float) ($s->quantidade ?? 0) ?: 1), 2);
                $liquido = (float) ($s->desconto ?? 0) > 0 ? round((float) ($s->subTotal ?? 0), 2) : $bruto;
                $totalServico += $liquido;
                if ((float) ($s->desconto ?? 0) > 0) {
                    $descontoItens += max(0.0, $bruto - $liquido);
                }
            }
        }
        if ($produtos = $this->getProdutos($id)) {
            foreach ($produtos as $p) {
                $preco = (float) ($p->preco ?? 0) ?: (float) ($p->precoVenda ?? 0);
                $bruto = round($preco * ((float) ($p->quantidade ?? 0) ?: 1), 2);
                $liquido = (float) ($p->desconto ?? 0) > 0 ? round((float) ($p->subTotal ?? 0), 2) : $bruto;
                $totalProdutos += $liquido;
                if ((float) ($p->desconto ?? 0) > 0) {
                    $descontoItens += max(0.0, $bruto - $liquido);
                }
            }
        }
        if ($valorDescontoBD = $this->getById($id)) {
            $valorDesconto = $valorDescontoBD->valor_desconto;
        }

        return ['totalServico' => round($totalServico, 2), 'totalProdutos' => round($totalProdutos, 2), 'valor_desconto' => round((float) $valorDesconto, 2), 'descontoItens' => round($descontoItens, 2)];
    }

    public function isEditable($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return false;
        }
        if ($os = $this->getById($id)) {
            $osT = (int) ($os->status === 'Faturado' || $os->status === 'Entregue' || $os->status === 'Cancelado' || $os->status === 'Sem Conserto' || $os->faturado == 1);
            if ($osT) {
                return $this->data['configuration']['control_editos'] == '1';
            }
        }

        return true;
    }

    private function osEditavelNaTransacao(object $os): bool
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return false;
        }

        $fechada = in_array((string) ($os->status ?? ''), ['Faturado', 'Entregue', 'Cancelado', 'Sem Conserto'], true)
            || (int) ($os->faturado ?? 0) === 1;

        return ! $fechada || (($this->data['configuration']['control_editos'] ?? '0') === '1');
    }

    /**
     * Adiciona produto e debita estoque na mesma transação.
     * A OS e o produto são bloqueados antes de validar/gravar para impedir
     * bypass de editabilidade, saldo negativo e consumo concorrente.
     */
    public function adicionarProdutoAOs(array $data, int $idOs, int $idProduto, int $quantidade, bool $controlaEstoque, $tipoDescontoItem = null, $descontoItem = null): array
    {
        if ($idOs <= 0 || $idProduto <= 0 || $quantidade <= 0) {
            return ['ok' => false, 'status' => 422, 'message' => 'Produto ou quantidade inválida.'];
        }

        $preco = (float) ($data['preco'] ?? 0);
        if ($preco < 0) {
            return ['ok' => false, 'status' => 422, 'message' => 'Preço inválido.'];
        }

        if (! $this->db->trans_begin()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível iniciar a inclusão do produto.'];
        }

        $os = $this->db->query(
            'SELECT idOs, status, faturado FROM os WHERE idOs = ? FOR UPDATE',
            [$idOs]
        )->row();
        if (! $os) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 404, 'message' => 'OS não encontrada.'];
        }

        if (! $this->osEditavelNaTransacao($os)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 409, 'message' => 'Esta OS não pode mais ser alterada.'];
        }

        $produto = $this->db->query(
            'SELECT idProdutos, precoCompra, estoque FROM produtos WHERE idProdutos = ? FOR UPDATE',
            [$idProduto]
        )->row();
        if (! $produto) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 422, 'message' => 'Produto não encontrado.'];
        }

        [$descontoCalculado, $errosDesconto] = osDescontoDoItem(
            round($preco * $quantidade, 2),
            $tipoDescontoItem,
            $descontoItem
        );
        if ($errosDesconto !== []) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 422, 'message' => 'Confira os campos destacados.', 'erros' => $errosDesconto];
        }
        if ($descontoCalculado['desconto'] > 0 && ! $this->descontoPorItemDisponivel()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 422, 'message' => 'O desconto por item precisa da atualização do banco de dados.'];
        }

        if ($controlaEstoque) {
            $debitou = $this->db->query(
                'UPDATE produtos SET estoque = estoque - ? WHERE idProdutos = ? AND estoque >= ?',
                [$quantidade, $idProduto, $quantidade]
            );
            if ($debitou === false) {
                $this->db->trans_rollback();
                return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível debitar o estoque.'];
            }
            if ($this->db->affected_rows() !== 1) {
                $this->db->trans_rollback();
                return ['ok' => false, 'status' => 409, 'message' => 'Estoque insuficiente para esta quantidade.'];
            }
        }

        $data['quantidade'] = $quantidade;
        $data['subTotal'] = $descontoCalculado['subTotal'];
        $data['produtos_id'] = $idProduto;
        $data['preco'] = $preco;
        $data['custo_unitario'] = (float) ($produto->precoCompra ?? 0);
        $data['custo_total'] = round($data['custo_unitario'] * $quantidade, 2);
        $data['os_id'] = $idOs;
        if ($this->descontoPorItemDisponivel()) {
            $data['desconto'] = $descontoCalculado['desconto'];
            $data['tipo_desconto'] = $descontoCalculado['tipo_desconto'];
        } else {
            unset($data['desconto'], $data['tipo_desconto']);
        }

        if (! $this->db->insert('produtos_os', $data) || $this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível salvar o produto na OS.'];
        }
        $itemId = (int) $this->db->insert_id();

        if (! $this->db->where('idOs', $idOs)->update('os', [
            'desconto' => 0.00,
            'valor_desconto' => 0.00,
            'tipo_desconto' => null,
        ]) || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível atualizar os totais da OS.'];
        }

        if (! $this->db->trans_commit()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível confirmar a inclusão do produto.'];
        }

        return ['ok' => true, 'status' => 200, 'item_id' => $itemId, 'message' => 'Produto adicionado à OS.'];
    }

    /** Adiciona serviço e atualiza os totais na mesma transação. */
    public function adicionarServicoAOs(array $data, int $idOs, int $idServico, float $quantidade, $tipoDescontoItem = null, $descontoItem = null): array
    {
        if ($idOs <= 0 || $idServico <= 0 || $quantidade <= 0) {
            return ['ok' => false, 'status' => 422, 'message' => 'Serviço ou quantidade inválida.'];
        }

        $preco = (float) ($data['preco'] ?? 0);
        if ($preco < 0) {
            return ['ok' => false, 'status' => 422, 'message' => 'Preço inválido.'];
        }

        if (! $this->db->trans_begin()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível iniciar a inclusão do serviço.'];
        }

        $os = $this->db->query(
            'SELECT idOs, status, faturado FROM os WHERE idOs = ? FOR UPDATE',
            [$idOs]
        )->row();
        if (! $os) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 404, 'message' => 'OS não encontrada.'];
        }
        if (! $this->osEditavelNaTransacao($os)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 409, 'message' => 'Esta OS não pode mais ser alterada.'];
        }

        $servico = $this->db->query(
            'SELECT idServicos FROM servicos WHERE idServicos = ? FOR UPDATE',
            [$idServico]
        )->row();
        if (! $servico) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 422, 'message' => 'Serviço não encontrado.'];
        }

        [$descontoCalculado, $errosDesconto] = osDescontoDoItem(
            round($preco * $quantidade, 2),
            $tipoDescontoItem,
            $descontoItem
        );
        if ($errosDesconto !== []) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 422, 'message' => 'Confira os campos destacados.', 'erros' => $errosDesconto];
        }
        if ($descontoCalculado['desconto'] > 0 && ! $this->descontoPorItemDisponivel()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 422, 'message' => 'O desconto por item precisa da atualização do banco de dados.'];
        }

        $data['servicos_id'] = $idServico;
        $data['quantidade'] = $quantidade;
        $data['preco'] = $preco;
        $data['os_id'] = $idOs;
        $data['subTotal'] = $descontoCalculado['subTotal'];
        if ($this->descontoPorItemDisponivel()) {
            $data['desconto'] = $descontoCalculado['desconto'];
            $data['tipo_desconto'] = $descontoCalculado['tipo_desconto'];
        } else {
            unset($data['desconto'], $data['tipo_desconto']);
        }

        if (! $this->db->insert('servicos_os', $data) || $this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível salvar o serviço na OS.'];
        }
        $itemId = (int) $this->db->insert_id();

        if (! $this->db->where('idOs', $idOs)->update('os', [
            'desconto' => 0.00,
            'valor_desconto' => 0.00,
            'tipo_desconto' => null,
        ]) || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível atualizar os totais da OS.'];
        }

        if (! $this->db->trans_commit()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível confirmar a inclusão do serviço.'];
        }

        return ['ok' => true, 'status' => 200, 'item_id' => $itemId, 'message' => 'Serviço adicionado à OS.'];
    }

    /** Indica se o histórico de status está disponível neste schema. */
    public function historicoDisponivel(): bool
    {
        return $this->historicoDisponivel ??= $this->db->table_exists('os_historico');
    }

    /**
     * Registra uma mudança de status, sem criar eventos para o mesmo status.
     * A data/hora usa explicitamente o fuso operacional do STAGING.
     */
    public function registrarStatus(int $idOs, ?string $anterior, string $novo, ?int $usuario): void
    {
        if (trim((string) $anterior) === trim($novo) || ! $this->historicoDisponivel()) {
            return;
        }

        $agora = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        $this->db->insert('os_historico', [
            'os_id' => $idOs,
            'status_anterior' => $anterior,
            'status_novo' => $novo,
            'usuarios_id' => $usuario ?: null,
            'data_hora' => $agora->format('Y-m-d H:i:s'),
        ]);
    }

    /** Histórico de status mais recente primeiro. */
    public function getHistorico(int $idOs): array
    {
        if (! $this->historicoDisponivel()) {
            return [];
        }

        return $this->db
            ->select('os_historico.*, usuarios.nome AS autor')
            ->from('os_historico')
            ->join('usuarios', 'usuarios.idUsuarios = os_historico.usuarios_id', 'left')
            ->where('os_historico.os_id', $idOs)
            ->order_by('os_historico.data_hora', 'desc')
            ->order_by('os_historico.idHistorico', 'desc')
            ->get()
            ->result();
    }

    /**
     * Remove um produto pelo vínculo da OS, devolvendo o estoque na mesma
     * transação. O produto e a quantidade vêm da linha bloqueada, nunca do
     * payload do navegador.
     */
    public function excluirProdutoDaOs(int $idOs, int $idItem, bool $controlaEstoque): array
    {
        if ($idOs <= 0 || $idItem <= 0) {
            return ['ok' => false, 'status' => 422, 'message' => 'Produto da OS inválido.'];
        }

        if (! $this->db->trans_begin()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível iniciar a exclusão do produto.'];
        }

        $os = $this->db->query(
            'SELECT idOs FROM os WHERE idOs = ? FOR UPDATE',
            [$idOs]
        )->row();
        if (! $os) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 404, 'message' => 'OS não encontrada.'];
        }

        $item = $this->db->query(
            'SELECT idProdutos_os, produtos_id, quantidade
             FROM produtos_os
             WHERE idProdutos_os = ? AND os_id = ?
             FOR UPDATE',
            [$idItem, $idOs]
        )->row();
        if (! $item) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 404, 'message' => 'Produto não está vinculado a esta OS.'];
        }

        if ($controlaEstoque) {
            $produto = $this->db->query(
                'SELECT idProdutos FROM produtos WHERE idProdutos = ? FOR UPDATE',
                [(int) $item->produtos_id]
            )->row();
            if (! $produto) {
                $this->db->trans_rollback();
                return ['ok' => false, 'status' => 422, 'message' => 'Produto de catálogo não encontrado.'];
            }

            $this->db->query(
                'UPDATE produtos SET estoque = estoque + ? WHERE idProdutos = ?',
                [(int) $item->quantidade, (int) $item->produtos_id]
            );
            if ($this->db->affected_rows() !== 1) {
                $this->db->trans_rollback();
                return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível devolver o produto ao estoque.'];
            }
        }

        $this->db->where('idProdutos_os', (int) $item->idProdutos_os)
            ->where('os_id', $idOs)
            ->delete('produtos_os');
        if ($this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 409, 'message' => 'O produto já foi removido ou mudou de OS.'];
        }

        $this->db->where('idOs', $idOs)->update('os', [
            'desconto' => 0.00,
            'valor_desconto' => 0.00,
            'tipo_desconto' => null,
        ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível concluir a exclusão do produto.'];
        }

        if (! $this->db->trans_commit()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível confirmar a exclusão do produto.'];
        }

        return ['ok' => true, 'status' => 200, 'message' => 'Produto excluído da OS.'];
    }

    /** Remove um serviço pelo vínculo da OS com contrato idempotente. */
    public function excluirServicoDaOs(int $idOs, int $idItem): array
    {
        if ($idOs <= 0 || $idItem <= 0) {
            return ['ok' => false, 'status' => 422, 'message' => 'Serviço da OS inválido.'];
        }

        if (! $this->db->trans_begin()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível iniciar a exclusão do serviço.'];
        }
        $item = $this->db->query(
            'SELECT idServicos_os
             FROM servicos_os
             WHERE idServicos_os = ? AND os_id = ?
             FOR UPDATE',
            [$idItem, $idOs]
        )->row();
        if (! $item) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 404, 'message' => 'Serviço não está vinculado a esta OS.'];
        }

        $this->db->where('idServicos_os', (int) $item->idServicos_os)
            ->where('os_id', $idOs)
            ->delete('servicos_os');
        if ($this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 409, 'message' => 'O serviço já foi removido ou mudou de OS.'];
        }

        $this->db->where('idOs', $idOs)->update('os', [
            'desconto' => 0.00,
            'valor_desconto' => 0.00,
            'tipo_desconto' => null,
        ]);
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível concluir a exclusão do serviço.'];
        }

        if (! $this->db->trans_commit()) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível confirmar a exclusão do serviço.'];
        }
        return ['ok' => true, 'status' => 200, 'message' => 'Serviço excluído da OS.'];
    }

    /** Totais da tela moderna, incluindo descontos individuais quando disponíveis. */
    public function totais(object $os): array
    {
        $produtos = $this->getProdutos((int) $os->idOs);
        $servicos = $this->getServicos((int) $os->idOs);
        $totalProdutos = 0.0;
        $totalServicos = 0.0;
        $descontoItens = 0.0;

        foreach ($produtos as $item) {
            $preco = (float) ($item->preco ?? 0) ?: (float) ($item->precoVenda ?? 0);
            $bruto = round($preco * ((float) ($item->quantidade ?? 0) ?: 1), 2);
            $liquido = (float) ($item->desconto ?? 0) > 0 ? round((float) ($item->subTotal ?? 0), 2) : $bruto;
            $totalProdutos += $liquido;
            if ((float) ($item->desconto ?? 0) > 0) {
                $descontoItens += max(0.0, $bruto - $liquido);
            }
        }
        foreach ($servicos as $item) {
            $preco = (float) ($item->preco ?? 0) ?: (float) ($item->precoVenda ?? 0);
            $bruto = round($preco * ((float) ($item->quantidade ?? 0) ?: 1), 2);
            $liquido = (float) ($item->desconto ?? 0) > 0 ? round((float) ($item->subTotal ?? 0), 2) : $bruto;
            $totalServicos += $liquido;
            if ((float) ($item->desconto ?? 0) > 0) {
                $descontoItens += max(0.0, $bruto - $liquido);
            }
        }

        $bruto = round($totalProdutos + $totalServicos, 2);
        $comDesconto = round((float) ($os->valor_desconto ?? 0), 2);
        $total = $comDesconto > 0 && $comDesconto < $bruto ? $comDesconto : $bruto;
        $temDesconto = $total < $bruto;

        return [
            'produtos' => round($totalProdutos, 2),
            'servicos' => round($totalServicos, 2),
            'bruto' => $bruto,
            'desconto' => round($bruto - $total, 2),
            'total' => $total,
            'tipo' => $temDesconto
                ? ((int) ($os->faturado ?? 0) === 1
                    ? 'real'
                    : (in_array($os->tipo_desconto ?? null, ['real', 'porcento'], true) ? $os->tipo_desconto : 'real'))
                : null,
            'informado' => $temDesconto ? round((float) ($os->desconto ?? 0), 2) : 0.0,
            'descontoItens' => round($descontoItens, 2),
        ];
    }

    /** Código PIX copia e cola correspondente ao mesmo QR Code da tela. */
    public function getPixPayload($id, $pixKey, $emitente): ?string
    {
        if (empty($id) || empty($pixKey) || empty($emitente)) {
            return null;
        }

        $result = $this->valorTotalOS($id);
        $amount = $result['valor_desconto'] != 0
            ? round((float) $result['valor_desconto'], 2)
            : round((float) $result['totalServico'] + (float) $result['totalProdutos'], 2);
        if ($amount <= 0) {
            return null;
        }

        $pix = (new StaticPayload())
            ->setAmount($amount)
            ->setTid($id)
            ->setDescription(sprintf('%s OS %s', substr($emitente->nome, 0, 18), $id), true)
            ->setPixKey(getPixKeyType($pixKey), $pixKey)
            ->setMerchantName($emitente->nome)
            ->setMerchantCity($emitente->cidade);

        return $pix->getPixCode();
    }

    public function getQrCode($id, $pixKey, $emitente)
    {
        if (empty($id) || empty($pixKey) || empty($emitente)) {
            return;
        }

        $result = $this->valorTotalOS($id);
        $amount = $result['valor_desconto'] != 0 ? round(floatval($result['valor_desconto']), 2) : round(floatval($result['totalServico'] + $result['totalProdutos']), 2);

        if ($amount <= 0) {
            return;
        }

        $pix = (new StaticPayload())
            ->setAmount($amount)
            ->setTid($id)
            ->setDescription(sprintf('%s OS %s', substr($emitente->nome, 0, 18), $id), true)
            ->setPixKey(getPixKeyType($pixKey), $pixKey)
            ->setMerchantName($emitente->nome)
            ->setMerchantCity($emitente->cidade);

        return $pix->getQRCode();
    }

    const OS_TERMINAIS = ['Finalizado', 'Faturado', 'Entregue', 'Sem Conserto', 'Cancelado'];

    public function ultimaTransicaoTerminal($osId = null)
    {
        if (! $osId) {
            return null;
        }
        $this->db->select('status_novo AS status, data_hora')
            ->from('os_historico')
            ->where('os_id', $osId)
            ->where_in('status_novo', $this->OS_TERMINAIS)
            ->order_by('data_hora', 'DESC')
            ->limit(1);
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            return $q->row_array();
        }
        return null;
    }

    /** Indica se os descontos individuais estão disponíveis no schema ativo. */
    public function descontoPorItemDisponivel(): bool
    {
        static $disponivel = null;
        if ($disponivel === null) {
            $disponivel = $this->db->field_exists('desconto', 'produtos_os')
                && $this->db->field_exists('desconto', 'servicos_os');
        }

        return $disponivel;
    }

    public function indicadoresFinanceirosSql(): array
    {
        // Mesma regra de valorTotalOS(): serviço sem preço usa o do cadastro, e
        // sem quantidade conta 1.
        $totalProdutos = '(SELECT COALESCE(SUM(produtos_os.subTotal), 0) FROM produtos_os WHERE produtos_os.os_id = os.idOs)';
        $valorServico = 'COALESCE(NULLIF(servicos_os.preco, 0), servicos.preco, 0) * COALESCE(NULLIF(servicos_os.quantidade, 0), 1)';
        if ($this->descontoPorItemDisponivel()) {
            // Serviço com desconto individual (#2880) vale o subTotal gravado.
            $valorServico = "CASE WHEN servicos_os.desconto > 0 THEN servicos_os.subTotal ELSE {$valorServico} END";
        }
        $totalServicos = "(SELECT COALESCE(SUM({$valorServico}), 0)"
            . ' FROM servicos_os LEFT JOIN servicos ON servicos.idServicos = servicos_os.servicos_id WHERE servicos_os.os_id = os.idOs)';

        // Indicadores financeiros: preço x quantidade, custo histórico dos
        // componentes e faturamento por FK ou fallback textual legado exato.
        $brutoProdutosFinanceiro = '(SELECT COALESCE(SUM(COALESCE(produtos_os.preco, 0) * COALESCE(produtos_os.quantidade, 0)), 0) FROM produtos_os WHERE produtos_os.os_id = os.idOs)';
        $brutoServicosFinanceiro = '(SELECT COALESCE(SUM(COALESCE(servicos_os.preco, 0) * COALESCE(servicos_os.quantidade, 0)), 0) FROM servicos_os WHERE servicos_os.os_id = os.idOs)';
        $custoComponentes = $this->db->dbdriver === 'sqlite3'
            ? '(SELECT COALESCE(SUM(CAST(produtos_os.custo_total AS REAL)), 0) FROM produtos_os WHERE produtos_os.os_id = os.idOs)'
            : '(SELECT COALESCE(SUM(CAST(produtos_os.custo_total AS DECIMAL(20, 4))), 0) FROM produtos_os WHERE produtos_os.os_id = os.idOs)';
        $valorLancamento = "CASE WHEN COALESCE(l.valor_desconto, 0) = 0 THEN COALESCE(l.valor, 0) ELSE COALESCE(l.valor_desconto, 0) END";
        $lancamentoVinculadoValido = "EXISTS (SELECT 1 FROM lancamentos l_valid WHERE l_valid.idLancamentos = os.lancamento AND l_valid.tipo = 'receita')";
        $faturaTexto = $this->db->dbdriver === 'sqlite3'
            ? "('Fatura de OS Nº: ' || os.idOs)"
            : "CONCAT('Fatura de OS Nº: ', os.idOs)";
        $vtFaturado = "(CASE WHEN {$lancamentoVinculadoValido} THEN (SELECT COALESCE(SUM({$valorLancamento}), 0) FROM lancamentos l WHERE l.idLancamentos = os.lancamento AND l.tipo = 'receita') ELSE (SELECT COALESCE(SUM({$valorLancamento}), 0) FROM lancamentos l WHERE l.tipo = 'receita' AND l.descricao = {$faturaTexto}) END)";

        return [
            'totalProdutos' => $totalProdutos,
            'totalServicos' => $totalServicos,
            'brutoFinanceiro' => $brutoProdutosFinanceiro . ' + ' . $brutoServicosFinanceiro,
            'custoComponentes' => $custoComponentes,
            'vtFaturado' => $vtFaturado,
        ];
    }

    /** Valor líquido C5B em centavos, com negativos permitidos. */
    public static function valorLiquidoOperacional($brutoFinanceiro, $desconto, $valorDesconto, $custoComponentes): float
    {
        $paraCentavos = static fn ($valor): int => (int) round((float) $valor * 100, 0, PHP_ROUND_HALF_UP);
        $brutoCentavos = $paraCentavos($brutoFinanceiro);
        $baseCentavos = (float) $desconto > 0 ? $paraCentavos($valorDesconto) : $brutoCentavos;

        return ($baseCentavos - $paraCentavos($custoComponentes)) / 100;
    }

    /** Aplica os indicadores C5B a linhas de listagem/relatório em memória. */
    public function aplicarIndicadoresFinanceiros(array $linhas): array
    {
        foreach ($linhas as $linha) {
            $bruto = (float) ($linha->totalProdutos ?? 0) + (float) ($linha->totalServicos ?? 0);
            $linha->total = (float) ($linha->valor_desconto ?? 0) > 0 ? (float) $linha->valor_desconto : $bruto;
            $linha->valorLiquido = self::valorLiquidoOperacional(
                $linha->bruto_financeiro ?? 0,
                $linha->desconto ?? 0,
                $linha->valor_desconto ?? 0,
                $linha->custo_componentes ?? 0
            );
            $linha->vtFaturado = (float) ($linha->vt_faturado ?? 0);
        }

        return $linhas;
    }

    /** Status que encerram a OS (conclusão terminal). Uma mesma OS é contada uma só vez. */
    /**
     * Projeção financeira dinâmica de todas as OS.
     *
     * A projeção não depende de os_historico para reconhecer resultado: usa o
     * estado atual da OS, o vínculo os.lancamento e, quando necessário, uma
     * associação textual legada exata e unívoca. Cada chamada relê as fontes;
     * portanto faturamento, estorno, correção de data, custo e reabertura são
     * refletidos na próxima consulta sem cron, cache ou escrita gerencial.
     *
     * @return list<array<string, mixed>>
     */
    public function radarOsDetalhes(): array
    {
        $sql = "SELECT os.idOs, os.status, os.dataInicial, os.dataFinal, os.faturado,
                       os.desconto, os.valor_desconto, os.valorTotal, os.lancamento,
                       COALESCE(SUM(CAST(produtos_os.custo_total AS DECIMAL(20, 4))), 0) AS custo_componentes
                FROM os
                LEFT JOIN produtos_os ON produtos_os.os_id = os.idOs
                GROUP BY os.idOs, os.status, os.dataInicial, os.dataFinal, os.faturado,
                         os.desconto, os.valor_desconto, os.valorTotal, os.lancamento
                ORDER BY os.idOs ASC";
        $osRows = $this->db->query($sql)->result();
        $lancamentos = $this->db->query(
            'SELECT idLancamentos, descricao, valor, desconto, valor_desconto, data_pagamento, baixado, tipo, data_vencimento '
            . 'FROM lancamentos ORDER BY idLancamentos ASC'
        )->result();

        $porId = [];
        $legados = [];
        foreach ($lancamentos as $lancamento) {
            $idLancamento = (int) ($lancamento->idLancamentos ?? 0);
            $porId[$idLancamento] = $lancamento;
            if ((string) ($lancamento->tipo ?? '') !== 'receita') {
                continue;
            }
            $descricao = trim((string) ($lancamento->descricao ?? ''));
            $idOs = null;
            if (preg_match('/^Fatura de OS Nº:\\s*(\\d+)\\s*$/u', $descricao, $match)) {
                $idOs = (int) $match[1];
            } elseif (preg_match('/^Fatura de OS - #\\s*(\\d+)\\s*$/u', $descricao, $match)) {
                $idOs = (int) $match[1];
            }
            if ($idOs !== null) {
                $legados[$idOs][] = $lancamento;
            }
        }

        $resultado = [];
        foreach ($osRows as $os) {
            $idOs = (int) ($os->idOs ?? 0);
            $status = trim((string) ($os->status ?? ''));
            $dataInicial = $this->radarDataValida($os->dataInicial ?? null) ? substr((string) $os->dataInicial, 0, 10) : null;
            $dataFinal = $this->radarDataValida($os->dataFinal ?? null) ? substr((string) $os->dataFinal, 0, 10) : null;
            $custo = (float) round((float) ($os->custo_componentes ?? 0), 2);
            $faturado = (int) ($os->faturado ?? 0);
            $estado = 'pendente';
            $motivo = 'Sem recebimento efetivo nem encerramento reconhecível.';
            $tipoReconhecimento = null;
            $fonteFinanceira = 'nenhuma';
            $dataReconhecimento = null;
            $receita = 0.0;
            $lancamentoId = null;
            $lancamentoBaixado = null;
            $lancamentoDataPagamento = null;
            $associados = [];

            if ($os->lancamento !== null && $os->lancamento !== '') {
                $lancamentoId = (int) $os->lancamento;
                $associados = isset($porId[$lancamentoId]) ? [$porId[$lancamentoId]] : [];
                $fonteFinanceira = 'os.lancamento';
                if ($associados === []) {
                    $estado = 'excecao';
                    $motivo = 'os.lancamento aponta para lançamento inexistente.';
                }
            } elseif (count($legados[$idOs] ?? []) === 1) {
                $associados = $legados[$idOs];
                $lancamentoId = (int) $associados[0]->idLancamentos;
                $fonteFinanceira = 'legado_exato_unico';
            } elseif (count($legados[$idOs] ?? []) > 1) {
                $associados = $legados[$idOs];
                $fonteFinanceira = 'legado_ambiguo';
                $estado = 'excecao';
                $motivo = 'Mais de um lançamento legado de receita casa com a OS; nenhum foi associado.';
            }

            if ($estado !== 'excecao' && $associados !== []) {
                $lancamento = $associados[0];
                $lancamentoBaixado = $lancamento->baixado === null ? null : (int) $lancamento->baixado;
                $lancamentoDataPagamento = $this->radarDataValida($lancamento->data_pagamento ?? null)
                    ? substr((string) $lancamento->data_pagamento, 0, 10)
                    : null;
                if ((string) ($lancamento->tipo ?? '') !== 'receita') {
                    $estado = 'excecao';
                    $motivo = 'O vínculo da OS não aponta para um lançamento de receita.';
                } elseif ($lancamentoBaixado === 1 && $lancamentoDataPagamento === null) {
                    $estado = 'excecao';
                    $motivo = 'Baixa válida sem data_pagamento válida; a data não foi inventada.';
                } elseif ($lancamentoBaixado === 1 && $lancamentoDataPagamento !== null) {
                    $estado = 'reconhecida';
                    $tipoReconhecimento = 'recebimento_efetivo';
                    $dataReconhecimento = $lancamentoDataPagamento;
                    $fonteFinanceira = $fonteFinanceira;
                    $receita = $this->radarValorLancamento($lancamento);
                    $motivo = 'Receita vinculada, baixada e com data de pagamento válida.';
                } else {
                    $estado = 'pendente';
                    $motivo = 'Existe valor a receber, mas o lançamento ainda não está baixado.';
                }
            } elseif ($estado !== 'excecao' && $associados === []) {
                if ($faturado === 1) {
                    $estado = 'excecao';
                    $motivo = 'Flag faturado=1 sem lançamento de receita direto ou legado unívoco.';
                } elseif (in_array($status, ['Entregue', 'Sem Conserto'], true) && $dataFinal !== null) {
                    $estado = 'reconhecida';
                    $tipoReconhecimento = 'encerramento_sem_receita';
                    $dataReconhecimento = $dataFinal;
                    $motivo = 'OS encerrada sem receita e sem valor a receber; contribuição = 0 - custo histórico.';
                } elseif ($status === 'Cancelado') {
                    $motivo = 'OS cancelada sem recebimento; não foi tratada como entrega operacional.';
                }
            }

            $contribuicao = $estado === 'reconhecida' ? (float) round($receita - $custo, 2) : null;
            $classificacao = null;
            if ($contribuicao !== null) {
                $classificacao = $contribuicao > 0 ? 'positiva' : ($contribuicao < 0 ? 'negativa' : 'zero');
            }
            $resultado[] = [
                'idOs' => $idOs,
                'status' => $status,
                'faturado' => $faturado,
                'dataInicial' => $dataInicial,
                'dataFinal' => $dataFinal,
                'concluida' => in_array($status, self::OS_TERMINAIS, true),
                'dataConclusao' => $dataFinal,
                'dataConclusaoVerificavel' => $dataFinal !== null,
                'dataReconhecimento' => $dataReconhecimento,
                'estado' => $estado,
                'tipoReconhecimento' => $tipoReconhecimento,
                'motivo' => $motivo,
                'fonteFinanceira' => $fonteFinanceira,
                'lancamentoId' => $lancamentoId,
                'lancamentoBaixado' => $lancamentoBaixado,
                'lancamentoDataPagamento' => $lancamentoDataPagamento,
                'recebimentoValido' => $estado === 'reconhecida' && $tipoReconhecimento === 'recebimento_efetivo',
                'receitaEfetiva' => (float) round($receita, 2),
                'custoComponentes' => $custo,
                'vtFaturado' => (float) round($receita, 2),
                'contribuicao' => $contribuicao,
                'classificacao' => $classificacao,
            ];
        }

        return $resultado;
    }

    private function radarDataValida($data): bool
    {
        $data = substr(trim((string) $data), 0, 10);
        if (! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $data) || $data === '0000-00-00') {
            return false;
        }
        $dt = DateTime::createFromFormat('!Y-m-d', $data);
        $erros = DateTime::getLastErrors();

        return $dt instanceof DateTime && ($erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0)) && $dt->format('Y-m-d') === $data;
    }

    private function radarValorLancamento(object $lancamento): float
    {
        $valor = (float) ($lancamento->valor ?? 0);
        $desconto = (float) ($lancamento->desconto ?? 0);
        $valorDesconto = $lancamento->valor_desconto === null ? null : (float) $lancamento->valor_desconto;
        $liquido = $valorDesconto !== null && $valorDesconto > 0 ? $valorDesconto : $valor - $desconto;

        return (float) round($liquido, 2);
    }
}
