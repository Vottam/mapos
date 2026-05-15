<?php

use Piggly\Pix\StaticPayload;

class Os_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
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
            'num_serie' => $serie !== '' ? strtoupper(preg_replace('/\s+/', ' ', $serie)) : null,
        ];
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

    public function salvarEquipamentoOs($osId, $clienteId, array $dados)
    {
        $dados = $this->normalizarEquipamentoDados($dados);
        $tipo = $dados['equipamento'];
        $marca = $dados['marca'];
        $modelo = $dados['modelo'];
        $serie = $dados['num_serie'];

        if ($tipo === '' && $marca === '' && $modelo === '' && $serie === '') {
            return null;
        }

        $clienteId = (int) $clienteId;

        $this->db->trans_start();

        $marcaId = null;
        if ($marca !== null) {
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

        $this->db->from('equipamentos');
        $this->db->where('clientes_id', $clienteId);
        if ($serie !== null) {
            $this->db->where('num_serie', $serie);
        }
        if ($tipo !== null) {
            $this->db->where('equipamento', $tipo);
        }
        if ($modelo !== null) {
            $this->db->where('modelo', $modelo);
        }
        if ($marcaId !== null) {
            $this->db->where('marcas_id', $marcaId);
        }

        $equipamentoExistente = $this->db->get()->row();

        $equipamentoData = [
            'equipamento' => $tipo,
            'num_serie' => $serie,
            'modelo' => $modelo,
            'marcas_id' => $marcaId,
            'clientes_id' => $clienteId,
        ];

        if ($equipamentoExistente) {
            $equipamentoData = [
                'equipamento' => $tipo !== 'Não informado' ? $tipo : $equipamentoExistente->equipamento,
                'num_serie' => $serie !== null ? $serie : $equipamentoExistente->num_serie,
                'modelo' => $modelo !== null ? $modelo : $equipamentoExistente->modelo,
                'marcas_id' => $marcaId !== null ? $marcaId : $equipamentoExistente->marcas_id,
                'clientes_id' => $clienteId,
            ];

            $this->db->where('idEquipamentos', $equipamentoExistente->idEquipamentos);
            $this->db->update('equipamentos', $equipamentoData);
            $equipamentoId = $equipamentoExistente->idEquipamentos;
        } else {
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
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row_set[] = ['label' => $row['descricao'] . ' | Preço: R$ ' . $row['precoVenda'] . ' | Estoque: ' . $row['estoque'], 'estoque' => $row['estoque'], 'id' => $row['idProdutos'], 'preco' => $row['precoVenda']];
            }
            echo json_encode($row_set);
        }
    }

    public function autoCompleteProdutoSaida($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('codDeBarra', $q);
        $this->db->or_like('descricao', $q);
        $this->db->where('saida', 1);
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
        $this->db->or_like('telefone', $q);
        $this->db->or_like('celular', $q);
        $this->db->or_like('documento', $q);
        $query = $this->db->get('clientes');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row_set[] = ['label' => $row['nomeCliente'] . ' | Telefone: ' . $row['telefone'] . ' | Celular: ' . $row['celular'] . ' | Documento: ' . $row['documento'], 'id' => $row['idClientes']];
            }
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

    public function autoCompleteTermoGarantia($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('LOWER(refGarantia)', $q);
        $query = $this->db->get('garantias');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row_set[] = ['label' => $row['refGarantia'], 'id' => $row['idGarantias']];
            }
            echo json_encode($row_set);
        }
    }

    public function autoCompleteServico($q)
    {
        $this->db->select('*');
        $this->db->limit(25);
        $this->db->like('nome', $q);
        $query = $this->db->get('servicos');
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $row_set[] = ['label' => $row['nome'] . ' | Preço: R$ ' . $row['preco'], 'id' => $row['idServicos'], 'preco' => $row['preco']];
            }
            echo json_encode($row_set);
        }
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
        $totalServico = 0;
        $totalProdutos = 0;
        $valorDesconto = 0;
        if ($servicos = $this->getServicos($id)) {
            foreach ($servicos as $s) {
                $preco = $s->preco ?: $s->precoVenda;
                $totalServico = $totalServico + ($preco * ($s->quantidade ?: 1));
            }
        }
        if ($produtos = $this->getProdutos($id)) {
            foreach ($produtos as $p) {
                $totalProdutos = $totalProdutos + $p->subTotal;
            }
        }
        if ($valorDescontoBD = $this->getById($id)) {
            $valorDesconto = $valorDescontoBD->valor_desconto;
        }

        return ['totalServico' => $totalServico, 'totalProdutos' => $totalProdutos, 'valor_desconto' => $valorDesconto];
    }

    public function isEditable($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return false;
        }
        if ($os = $this->getById($id)) {
            $osT = (int) ($os->status === 'Faturado' || $os->status === 'Cancelado' || $os->faturado == 1);
            if ($osT) {
                return $this->data['configuration']['control_editos'] == '1';
            }
        }

        return true;
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
}
