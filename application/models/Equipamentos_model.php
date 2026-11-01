<?php

class Equipamentos_model extends CI_Model
{
    public function listar(array $filtros, int $limite, int $offset): array
    {
        $this->selectEquipamentos(true);
        $this->aplicarFiltros($filtros);

        return $this->db
            ->order_by('e.idEquipamentos', 'desc')
            ->limit($limite, max(0, $offset))
            ->get()
            ->result();
    }

    public function contar(array $filtros): int
    {
        $this->db->from('equipamentos e');
        $this->db->join('marcas m', 'm.idMarcas = e.marcas_id', 'left');
        $this->db->join('clientes c', 'c.idClientes = e.clientes_id', 'left');
        $this->aplicarFiltros($filtros);

        return (int) $this->db->count_all_results();
    }

    public function getById(int $id): ?object
    {
        $this->selectEquipamentos(true);
        $this->db->where('e.idEquipamentos', $id)->limit(1);

        return $this->db->get()->row() ?: null;
    }

    public function getByIdAndCliente(int $id, int $clienteId): ?object
    {
        $this->selectEquipamentos(false);
        $this->db->where('e.idEquipamentos', $id)->where('e.clientes_id', $clienteId)->limit(1);

        return $this->db->get()->row() ?: null;
    }

    public function getByOs(int $osId): ?object
    {
        $this->db->select('e.*, m.marca AS marca_nome, c.idClientes AS cliente_id, c.nomeCliente, eo.idEquipamentos_os, eo.defeito_declarado, eo.defeito_encontrado, eo.solucao');
        $this->db->from('equipamentos_os eo');
        $this->db->join('equipamentos e', 'e.idEquipamentos = eo.equipamentos_id');
        $this->db->join('marcas m', 'm.idMarcas = e.marcas_id', 'left');
        $this->db->join('clientes c', 'c.idClientes = e.clientes_id', 'left');
        $this->db->where('eo.os_id', $osId);
        $this->db->order_by('eo.idEquipamentos_os', 'asc')->limit(1);

        return $this->db->get()->row() ?: null;
    }

    /**
     * Todos os equipamentos vinculados a uma OS, na ordem do vínculo.
     *
     * getByOs() devolve só o primeiro (LIMIT 1). Para a etiqueta de serial
     * isso seria insuficiente: uma OS pode ter mais de um equipamento, e
     * escolher o primeiro em silêncio rotula a etiqueta com o serial
     * errado. Esta listagem permite ao chamador decidir.
     */
    public function listarPorOs(int $osId): array
    {
        $this->db->select('e.*, m.marca AS marca_nome, c.idClientes AS cliente_id, c.nomeCliente, eo.idEquipamentos_os, eo.defeito_declarado, eo.defeito_encontrado, eo.solucao');
        $this->db->from('equipamentos_os eo');
        $this->db->join('equipamentos e', 'e.idEquipamentos = eo.equipamentos_id');
        $this->db->join('marcas m', 'm.idMarcas = e.marcas_id', 'left');
        $this->db->join('clientes c', 'c.idClientes = e.clientes_id', 'left');
        $this->db->where('eo.os_id', $osId);
        $this->db->order_by('eo.idEquipamentos_os', 'asc');

        return $this->db->get()->result();
    }

    public function marcasAtivas(): array
    {
        return $this->db
            ->select('idMarcas, marca')
            ->where('situacao !=', 0)
            ->order_by('marca', 'asc')
            ->get('marcas')
            ->result();
    }

    public function getForClient(int $clienteId): array
    {
        $this->db->select('e.idEquipamentos, e.equipamento, e.modelo, e.num_serie, m.marca AS marca');
        $this->db->from('equipamentos e');
        $this->db->join('marcas m', 'm.idMarcas = e.marcas_id', 'left');
        $this->db->where('e.clientes_id', $clienteId);
        $this->db->order_by('e.equipamento', 'asc');
        $this->db->order_by('e.modelo', 'asc');
        $this->db->order_by('e.num_serie', 'asc');

        return $this->db->get()->result();
    }

    /**
     * Localiza todos os equipamentos de um serial sem alterar o valor salvo.
     * A comparação ignora apenas espaços laterais, espaços repetidos na
     * entrada e diferença de caixa; o cadastro permanece byte a byte intacto.
     */
    public function localizarPorSerial(string $serial): array
    {
        $comparacao = $this->serialParaComparacao($serial);
        if ($comparacao === '') {
            return [];
        }

        $this->selectEquipamentos(false);
        $this->db->where('LOWER(TRIM(e.num_serie)) = ' . $this->db->escape($comparacao), null, false);
        $this->db->order_by('e.idEquipamentos', 'asc');

        return $this->db->get()->result();
    }

    /**
     * Histórico de OS do serial, sem misturar clientes quando a consulta vem
     * do formulário de uma nova OS. A tela de cadastro passa o cliente atual;
     * a tela de equipamento, já autorizada pelo próprio cadastro, pode omitir.
     */
    public function getHistoricoPorSerial(string $serial, ?int $osAtualId = null, int $limite = 5, ?int $clienteId = null): array
    {
        if ($this->serialParaComparacao($serial) === '') {
            return [];
        }

        return $this->buscarHistorico(null, $serial, $osAtualId, $limite, $clienteId);
    }

    /** Histórico por vínculo real, sem depender de texto de serial nas OS. */
    public function getHistoricoPorEquipamento(int $equipamentoId, ?int $osAtualId = null, int $limite = 5, ?int $clienteId = null): array
    {
        if ($equipamentoId <= 0) {
            return [];
        }

        return $this->buscarHistorico($equipamentoId, null, $osAtualId, $limite, $clienteId);
    }

    private function buscarHistorico(?int $equipamentoId, ?string $serial, ?int $osAtualId, int $limite, ?int $clienteId): array
    {
        $this->db->select('os.idOs, os.clientes_id, os.dataInicial, os.dataFinal, os.status, os.garantia, os.descricaoProduto, os.defeito, os.laudoTecnico, os.observacoes, c.nomeCliente AS cliente, e.idEquipamentos, e.equipamento AS tipo, m.marca, e.modelo, e.num_serie');
        $this->db->from('equipamentos e');
        $this->db->join('equipamentos_os eo', 'eo.equipamentos_id = e.idEquipamentos');
        $this->db->join('os', 'os.idOs = eo.os_id');
        $this->db->join('clientes c', 'c.idClientes = os.clientes_id', 'left');
        $this->db->join('marcas m', 'm.idMarcas = e.marcas_id', 'left');
        if ($equipamentoId !== null) {
            $this->db->where('e.idEquipamentos', $equipamentoId);
        } elseif ($serial !== null) {
            $comparacao = $this->serialParaComparacao($serial);
            $this->db->where('LOWER(TRIM(e.num_serie)) = ' . $this->db->escape($comparacao), null, false);
        }
        if ($clienteId !== null && $clienteId > 0) {
            $this->db->where('os.clientes_id', $clienteId);
        }
        if ($osAtualId !== null && $osAtualId > 0) {
            $this->db->where('os.idOs !=', $osAtualId);
        }
        // A data final é a cronologia principal; data inicial e idOs fecham
        // empates sem depender da ordem física do banco.
        $this->db
            ->order_by('os.dataFinal', 'desc')
            ->order_by('os.dataInicial', 'desc')
            ->order_by('os.idOs', 'desc')
            ->limit(max(1, min($limite, 50)));

        $rows = $this->db->get()->result();
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $hoje = new DateTimeImmutable('today', $fuso);
        foreach ($rows as $row) {
            $row->vencimento_garantia = null;
            $row->data_final_garantia = null;
            $row->dias_restantes = 0;
            $row->dias_vencidos = 0;
            $row->garantia_status = 'indeterminada';
            $row->situacao_garantia = 'Indefinido';
            $dataFinal = $this->parseDataHistorico($row->dataFinal ?? null, $fuso);
            $dias = is_numeric($row->garantia ?? null) ? (int) $row->garantia : 0;
            if ($dataFinal === null) {
                continue;
            }
            if ($dias <= 0) {
                $row->garantia_status = 'sem_garantia_registrada';
                $row->situacao_garantia = 'Sem garantia registrada';
                continue;
            }

            try {
                $vencimento = $dataFinal->add(new DateInterval('P' . $dias . 'D'));
                $diasRestantes = (int) $hoje->diff($vencimento)->format('%r%a');
                $row->vencimento_garantia = $vencimento->format('d/m/Y');
                $row->data_final_garantia = $vencimento->format('Y-m-d');
                if ($diasRestantes >= 0) {
                    $row->garantia_status = 'vigente';
                    $row->dias_restantes = $diasRestantes;
                    $row->situacao_garantia = 'Em garantia';
                } else {
                    $row->garantia_status = 'vencida';
                    $row->dias_vencidos = abs($diasRestantes);
                    $row->situacao_garantia = 'Fora de garantia';
                }
            } catch (Throwable $erro) {
                // Prazo inválido permanece neutro e informativo.
            }
        }

        return $rows;
    }

    /** Confirma que uma OS de origem pertence ao cliente e ao serial atuais. */
    public function garantiaOrigemValida(int $osId, int $clienteId, string $serial): bool
    {
        $serial = trim($serial);
        if ($osId <= 0 || $clienteId <= 0 || $serial === '') {
            return false;
        }

        return $this->db
            ->from('os')
            ->join('equipamentos_os eo', 'eo.os_id = os.idOs')
            ->join('equipamentos e', 'e.idEquipamentos = eo.equipamentos_id')
            ->where('os.idOs', $osId)
            ->where('os.clientes_id', $clienteId)
            ->where('e.num_serie', $serial)
            ->count_all_results() > 0;
    }

    /**
     * Mantém a crítica do LIVE: a OS mais recente é a referência, mas a
     * decisão de receber em garantia permanece manual no formulário.
     */
    public function calcularCriticaGarantiaSerial(?object $row): ?array
    {
        if (! $row) {
            return null;
        }

        $baseOsId = (int) ($row->idOs ?? 0);
        $garantiaDias = is_numeric($row->garantia ?? null) ? (int) $row->garantia : 0;
        $dataBaseRaw = trim((string) ($row->dataFinal ?? ''));
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $dataBase = $this->parseDataHistorico($dataBaseRaw, $fuso);
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

        try {
            $dataFinalGarantia = $dataBase->add(new DateInterval('P' . $garantiaDias . 'D'));
        } catch (Throwable $erro) {
            return [
                'baseOsId' => $baseOsId,
                'status' => 'indefinida',
                'titulo' => 'Não foi possível calcular a garantia',
                'contador' => 'Prazo de garantia inválido',
                'dias_restantes' => 0,
                'dias_vencidos' => 0,
                'data_base' => $dataBase->format('Y-m-d'),
                'data_base_formatada' => $dataBase->format('d/m/Y'),
                'garantia_dias' => $garantiaDias,
                'data_final_garantia' => '',
                'data_final_garantia_formatada' => '',
                'texto' => 'Verifique manualmente a OS anterior antes de decidir.',
            ];
        }

        $hoje = new DateTimeImmutable('today', $fuso);
        $diasRestantes = (int) $hoje->diff($dataFinalGarantia)->format('%r%a');
        $base = [
            'baseOsId' => $baseOsId,
            'dias_restantes' => max(0, $diasRestantes),
            'dias_vencidos' => max(0, -$diasRestantes),
            'data_base' => $dataBase->format('Y-m-d'),
            'data_base_formatada' => $dataBase->format('d/m/Y'),
            'garantia_dias' => $garantiaDias,
            'data_final_garantia' => $dataFinalGarantia->format('Y-m-d'),
            'data_final_garantia_formatada' => $dataFinalGarantia->format('d/m/Y'),
        ];

        if ($diasRestantes >= 0) {
            return $base + [
                'status' => 'em_garantia',
                'titulo' => 'Possível garantia de mão de obra',
                'contador' => 'Restam ' . $diasRestantes . ' dias de garantia',
                'texto' => 'A última OS deste equipamento ainda está dentro do prazo de garantia. Verifique se o defeito atual tem relação com o serviço anterior antes de confirmar atendimento em garantia.',
            ];
        }

        return $base + [
            'status' => 'vencida',
            'titulo' => 'Fora do prazo de garantia',
            'contador' => 'Garantia vencida há ' . abs($diasRestantes) . ' dias',
            'dias_restantes' => 0,
            'texto' => 'Verifique o histórico antes de decidir se haverá atendimento por cortesia ou cobrança normal.',
        ];
    }
    private function parseDataHistorico($valor, DateTimeZone $fuso): ?DateTimeImmutable
    {
        $texto = trim((string) $valor);
        $base = substr($texto, 0, 10);
        if ($texto === '' || $base === '0000-00-00' || strlen($base) !== 10) {
            return null;
        }

        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $base, $fuso);

        return $data !== false && $data->format('Y-m-d') === $base ? $data : null;
    }

    public function serialDuplicado(string $serial, ?int $ignorarId = null): bool
    {
        $serial = trim($serial);
        if ($serial === '') {
            return false;
        }

        $this->db->from('equipamentos')->where('num_serie', $serial);
        if ($ignorarId !== null) {
            $this->db->where('idEquipamentos !=', $ignorarId);
        }

        return $this->db->count_all_results() > 0;
    }

    public function temVinculos(int $id): bool
    {
        return $this->db->where('equipamentos_id', $id)->count_all_results('equipamentos_os') > 0;
    }

    public function clienteTemOsDiferente(int $equipamentoId, int $clienteId): bool
    {
        $this->db->from('equipamentos_os eo');
        $this->db->join('os', 'os.idOs = eo.os_id');
        $this->db->where('eo.equipamentos_id', $equipamentoId)->where('os.clientes_id !=', $clienteId);

        return $this->db->count_all_results() > 0;
    }

    public function salvar(array $dados, ?int $id = null): ?int
    {
        $this->db->trans_start();
        $marcaId = $this->resolverMarca($dados['marca'] ?? null);
        $registro = [
            'equipamento' => trim((string) ($dados['equipamento'] ?? '')),
            'num_serie' => $this->nuloOuTexto($dados['num_serie'] ?? null),
            'modelo' => $this->nuloOuTexto($dados['modelo'] ?? null),
            'cor' => $this->nuloOuTexto($dados['cor'] ?? null),
            'descricao' => $this->nuloOuTexto($dados['descricao'] ?? null),
            'tensao' => $this->nuloOuTexto($dados['tensao'] ?? null),
            'potencia' => $this->nuloOuTexto($dados['potencia'] ?? null),
            'voltagem' => $this->nuloOuTexto($dados['voltagem'] ?? null),
            'data_fabricacao' => $this->nuloOuTexto($dados['data_fabricacao'] ?? null),
            'marcas_id' => $marcaId,
            'clientes_id' => (int) ($dados['clientes_id'] ?? 0),
        ];

        if ($id === null) {
            $this->db->insert('equipamentos', $registro);
            $id = (int) $this->db->insert_id();
        } else {
            $this->db->where('idEquipamentos', $id)->update('equipamentos', $registro);
        }

        $this->db->trans_complete();

        return $this->db->trans_status() === false || $id <= 0 ? null : $id;
    }

    public function validarVinculoOs(int $clienteId, array $dados): ?string
    {
        $dados = $this->normalizarDados($dados);
        if ($dados['equipamento_id'] > 0 && ! $this->getByIdAndCliente($dados['equipamento_id'], $clienteId)) {
            return 'O equipamento escolhido não pertence ao cliente da OS.';
        }
        if ($dados['equipamento_id'] === 0 && $dados['num_serie'] !== '') {
            $existentes = $this->localizarPorSerial($dados['num_serie']);
            if (count($existentes) > 1) {
                return 'Há mais de um equipamento com este número de série. Escolha o equipamento cadastrado na lista.';
            }
            $existente = $existentes[0] ?? null;
            if ($existente && (int) $existente->clientes_id !== $clienteId) {
                return 'Já existe um equipamento com este número de série vinculado a outro cliente.';
            }
        }

        return null;
    }

    public function salvarVinculoOs(int $osId, int $clienteId, array $dados, ?int $equipamentoAtualId = null): array
    {
        $dados = $this->normalizarDados($dados);
        $selecionadoId = (int) ($dados['equipamento_id'] ?? 0);
        $tipo = $dados['equipamento'];
        $marca = $dados['marca'];
        $modelo = $dados['modelo'];
        $serie = $dados['num_serie'];
        $temDados = $selecionadoId > 0 || $tipo !== '' || $marca !== '' || $modelo !== '' || $serie !== '';

        if (! $temDados) {
            return ['ok' => true, 'id' => $equipamentoAtualId ?: 0];
        }

        $this->db->trans_start();
        $equipamento = null;
        if ($selecionadoId > 0) {
            $equipamento = $this->getByIdAndCliente($selecionadoId, $clienteId);
            if (! $equipamento) {
                $this->db->trans_rollback();

                return ['ok' => false, 'error' => 'O equipamento escolhido não pertence ao cliente da OS.'];
            }
        } elseif ($serie !== '') {
            $existentes = $this->localizarPorSerial($serie);
            if (count($existentes) > 1) {
                $this->db->trans_rollback();

                return ['ok' => false, 'error' => 'Há mais de um equipamento com este número de série. Escolha o equipamento cadastrado na lista.'];
            }
            $equipamento = $existentes[0] ?? null;
            if ($equipamento && (int) $equipamento->clientes_id !== $clienteId) {
                $this->db->trans_rollback();

                return ['ok' => false, 'error' => 'Já existe um equipamento com este número de série vinculado a outro cliente.'];
            }
        }

        $marcaId = $marca !== '' ? $this->resolverMarca($marca) : null;
        if ($equipamento) {
            $registro = [];
            if ($tipo !== '') {
                $registro['equipamento'] = $tipo;
            }
            if ($modelo !== '') {
                $registro['modelo'] = $modelo;
            }
            // O serial já foi usado como identidade do cadastro. A consulta
            // aceita caixa/espaços equivalentes, mas não regrava o valor
            // original só porque uma OS foi salva.
            if ($marcaId !== null) {
                $registro['marcas_id'] = $marcaId;
            }
            if ($registro !== []) {
                $this->db->where('idEquipamentos', (int) $equipamento->idEquipamentos)->update('equipamentos', $registro);
            }
            $equipamentoId = (int) $equipamento->idEquipamentos;
        } else {
            $this->db->insert('equipamentos', [
                'equipamento' => $tipo !== '' ? $tipo : 'Não informado',
                'num_serie' => $serie !== '' ? $serie : $this->serialInterno($osId),
                'modelo' => $modelo !== '' ? $modelo : null,
                'marcas_id' => $marcaId,
                'clientes_id' => $clienteId,
            ]);
            $equipamentoId = (int) $this->db->insert_id();
        }

        if ($equipamentoAtualId !== null && $equipamentoAtualId > 0 && $equipamentoAtualId !== $equipamentoId) {
            $this->db->where('os_id', $osId)->delete('equipamentos_os');
        }
        $this->db->where('os_id', $osId)->where('equipamentos_id', $equipamentoId);
        $jaVinculado = $this->db->count_all_results('equipamentos_os') > 0;
        if (! $jaVinculado) {
            $this->db->insert('equipamentos_os', ['equipamentos_id' => $equipamentoId, 'os_id' => $osId]);
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'error' => 'Não foi possível salvar o vínculo do equipamento.'];
        }

        return ['ok' => true, 'id' => $equipamentoId];
    }

    private function selectEquipamentos(bool $comContagem): void
    {
        $this->db->select('e.*, m.marca AS marca_nome, c.idClientes AS cliente_id, c.nomeCliente');
        if ($comContagem) {
            $this->db->select('(SELECT COUNT(*) FROM equipamentos_os eo WHERE eo.equipamentos_id = e.idEquipamentos) AS total_os', false);
            $this->db->select('(SELECT MAX(eo2.os_id) FROM equipamentos_os eo2 WHERE eo2.equipamentos_id = e.idEquipamentos) AS ultima_os', false);
        }
        $this->db->from('equipamentos e');
        $this->db->join('marcas m', 'm.idMarcas = e.marcas_id', 'left');
        $this->db->join('clientes c', 'c.idClientes = e.clientes_id', 'left');
    }

    private function aplicarFiltros(array $filtros): void
    {
        $pesquisa = trim((string) ($filtros['pesquisa'] ?? ''));
        if ($pesquisa !== '') {
            $this->db->group_start()
                ->like('e.equipamento', $pesquisa)
                ->or_like('e.num_serie', $pesquisa)
                ->or_like('e.modelo', $pesquisa)
                ->or_like('e.cor', $pesquisa)
                ->or_like('m.marca', $pesquisa)
                ->or_like('c.nomeCliente', $pesquisa)
                ->group_end();
        }
    }

    private function getBySerial(string $serial): ?object
    {
        return $this->localizarPorSerial($serial)[0] ?? null;
    }

    private function serialParaComparacao(string $serial): string
    {
        $serial = preg_replace('/\s+/u', ' ', trim($serial)) ?? trim($serial);

        return function_exists('mb_strtolower') ? mb_strtolower($serial, 'UTF-8') : strtolower($serial);
    }

    private function resolverMarca(?string $marca): ?int
    {
        $marca = trim((string) $marca);
        if ($marca === '') {
            return null;
        }

        $existente = $this->db->where('marca', $marca)->limit(1)->get('marcas')->row();
        if ($existente) {
            return (int) $existente->idMarcas;
        }

        $this->db->insert('marcas', ['marca' => $marca, 'cadastro' => date('Y-m-d'), 'situacao' => 1]);

        return (int) $this->db->insert_id() ?: null;
    }

    private function normalizarDados(array $dados): array
    {
        $normalizar = static fn ($valor): string => preg_replace('/\s+/', ' ', trim((string) $valor));

        return [
            'equipamento_id' => (int) ($dados['equipamento_id'] ?? 0),
            'equipamento' => $normalizar($dados['equipamento'] ?? ''),
            'marca' => $normalizar($dados['marca'] ?? ''),
            'modelo' => $normalizar($dados['modelo'] ?? ''),
            'num_serie' => trim((string) ($dados['num_serie'] ?? '')),
        ];
    }

    private function nuloOuTexto($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function serialInterno(int $osId): string
    {
        return 'SN' . str_pad((string) $osId, 6, '0', STR_PAD_LEFT);
    }
}
