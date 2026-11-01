<?php

class Equipamentos extends MY_Controller
{
    public const FILTROS = ['pesquisa' => 'texto'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('equipamentos_model');
    }

    public function index()
    {
        return $this->gerenciar();
    }

    public function gerenciar()
    {
        if (! $this->permite('vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar equipamentos.');
            return redirect(base_url());
        }
        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->uri->segment(3);
        $total = $this->equipamentos_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->equipamentos_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao((string) (parse_url(site_url('equipamentos/gerenciar'), PHP_URL_PATH) ?: '/'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aOs'),
            'editar' => $this->permite('eOs'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo equipamento', 'icon' => 'plus', 'href' => (string) (parse_url(site_url('equipamentos/adicionar'), PHP_URL_PATH) ?: '/')];
        }
        $this->data['view'] = 'equipamentos/equipamentos';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->permite('aOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar equipamentos.');
            return redirect(base_url());
        }
        return $this->formulario(null);
    }

    public function editar()
    {
        if (! $this->permite('eOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar equipamentos.');
            return redirect(base_url());
        }
        $id = is_numeric($this->uri->segment(3)) ? (int) $this->uri->segment(3) : 0;
        $equipamento = $id > 0 ? $this->equipamentos_model->getById($id) : null;
        if (! $equipamento) {
            $this->session->set_flashdata('error', 'Equipamento não encontrado ou parâmetro inválido.');
            return redirect('equipamentos');
        }

        return $this->formulario($equipamento);
    }

    public function visualizar()
    {
        if (! $this->permite('vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar equipamentos.');
            return redirect(base_url());
        }
        $id = is_numeric($this->uri->segment(3)) ? (int) $this->uri->segment(3) : 0;
        $equipamento = $id > 0 ? $this->equipamentos_model->getById($id) : null;
        if (! $equipamento) {
            $this->session->set_flashdata('error', 'Equipamento não encontrado ou parâmetro inválido.');
            return redirect('equipamentos');
        }

        $this->data['equipamento'] = $equipamento;
        $this->data['historico'] = $equipamento->num_serie
            ? $this->equipamentos_model->getHistoricoPorSerial((string) $equipamento->num_serie, null, 50)
            : [];
        $this->data['pode_editar'] = $this->permite('eOs');
        $this->data['view'] = 'equipamentos/visualizar';

        return $this->layout();
    }

    public function porCliente()
    {
        if (! $this->permite('vOs')) {
            return $this->json(403, ['message' => 'Você não tem permissão para consultar equipamentos.']);
        }

        $clienteId = (int) $this->uri->segment(3);
        if ($clienteId <= 0) {
            return $this->json(400, ['message' => 'Cliente inválido.']);
        }

        $itens = [];
        foreach ($this->equipamentos_model->getForClient($clienteId) as $item) {
            $partes = array_filter([(string) $item->marca, (string) $item->modelo, (string) $item->num_serie]);
            $itens[] = [
                'id' => (int) $item->idEquipamentos,
                'equipamento' => (string) $item->equipamento,
                'marca' => (string) ($item->marca ?? ''),
                'modelo' => (string) ($item->modelo ?? ''),
                'num_serie' => (string) ($item->num_serie ?? ''),
                'label' => (string) $item->equipamento . (count($partes) ? ' — ' . implode(' · ', $partes) : ''),
            ];
        }

        return $this->json(200, $itens);
    }

    /**
     * Etiqueta 40x25mm do EQUIPAMENTO (Code128 do número de série).
     *
     * A rota ancora no equipamento, não na OS: a etiqueta identifica o
     * equipamento, e um mesmo serial pode estar em várias OS. A OS de
     * origem é opcional (?os=) e entra só como texto de referência, sem
     * alterar o payload do código de barras.
     */
    public function imprimirEtiqueta()
    {
        if (! $this->permite('vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar equipamentos.');
            return redirect(base_url());
        }

        $id = is_numeric($this->uri->segment(3)) ? (int) $this->uri->segment(3) : 0;
        $equipamento = $id > 0 ? $this->equipamentos_model->getById($id) : null;
        if (! $equipamento) {
            $this->session->set_flashdata('error', 'Equipamento não encontrado ou parâmetro inválido.');
            return redirect('equipamentos');
        }

        $osId = is_numeric($this->input->get('os')) ? (int) $this->input->get('os') : null;
        if ($osId !== null && $osId > 0 && ! $this->equipamentoPertenceAos($equipamento->idEquipamentos, $osId)) {
            // A OS informada não é deste equipamento: a referência seria falsa.
            $osId = null;
        }

        $this->load->helper('etiquetas');
        etiquetaGerarPdf(
            etiquetaHtmlSerial(
                (string) ($equipamento->num_serie ?? ''),
                $osId,
                'Equipamento #' . (int) $equipamento->idEquipamentos
            ),
            'etiqueta_equipamento_' . (int) $equipamento->idEquipamentos
        );
    }

    /** O equipamento está vinculado à OS informada? */
    private function equipamentoPertenceAos(int $equipamentoId, int $osId): bool
    {
        foreach ($this->equipamentos_model->listarPorOs($osId) as $vinculo) {
            if ((int) $vinculo->idEquipamentos === $equipamentoId) {
                return true;
            }
        }

        return false;
    }

    public function historicoSerial()
    {
        if (! $this->permite('vOs')) {
            return $this->json(403, ['message' => 'Você não tem permissão para visualizar o histórico da OS.']);
        }

        $serial = trim((string) $this->input->get('serial'));
        $base = [
            'found' => false,
            'status' => $serial === '' ? 'empty' : 'not_found',
            'serial' => $serial,
            'serialConsulta' => $this->normalizarSerialConsulta($serial),
            'equipamento' => null,
            'clienteNecessario' => false,
            'total' => 0,
            'historico' => [],
            'garantiaCritica' => null,
        ];
        if ($serial === '') {
            return $this->json(200, $base);
        }
        if (mb_strlen($serial) > 80) {
            $base['status'] = 'invalid';
            return $this->json(200, $base);
        }

        $osId = is_numeric($this->input->get('os_id'))
            ? (int) $this->input->get('os_id')
            : (is_numeric($this->input->get('idOs')) ? (int) $this->input->get('idOs') : null);
        $osId = $osId !== null && $osId > 0 ? $osId : null;
        $clienteId = is_numeric($this->input->get('clientes_id')) ? (int) $this->input->get('clientes_id') : 0;
        $equipamentoId = is_numeric($this->input->get('equipamento_id')) ? (int) $this->input->get('equipamento_id') : 0;

        // Quando o operador escolheu explicitamente no dropdown, o ID é a
        // autoridade da seleção; isso mantém o caminho existente válido mesmo
        // que haja serial duplicado. O backend ainda valida serial e cliente.
        $equipamentos = $equipamentoId > 0
            ? array_values(array_filter([$this->equipamentos_model->getById($equipamentoId)]))
            : $this->equipamentos_model->localizarPorSerial($serial);
        if ($equipamentos === []) {
            return $this->json(200, $base);
        }

        $equipamento = null;
        if ($equipamentoId > 0) {
            $candidato = $equipamentos[0];
            if ($this->normalizarSerialConsulta((string) ($candidato->num_serie ?? '')) !== $base['serialConsulta']) {
                return $this->json(200, $base);
            }
            if ($clienteId > 0 && (int) ($candidato->clientes_id ?? 0) !== $clienteId) {
                $base['status'] = 'ownership_mismatch';
                return $this->json(200, $base);
            }
            $equipamento = $candidato;
        } elseif ($clienteId > 0) {
            $mesmoCliente = array_values(array_filter(
                $equipamentos,
                static fn (object $item): bool => (int) ($item->clientes_id ?? 0) === $clienteId
            ));
            if (count($equipamentos) > 1 || count($mesmoCliente) > 1) {
                $base['status'] = 'ambiguous';
                return $this->json(200, $base);
            }
            if ($mesmoCliente === []) {
                $base['status'] = 'ownership_mismatch';
                return $this->json(200, $base);
            }
            $equipamento = $mesmoCliente[0];
        } else {
            if (count($equipamentos) !== 1) {
                $base['status'] = 'ambiguous';
                return $this->json(200, $base);
            }
            $equipamento = $equipamentos[0];
        }

        $historico = [];
        $clienteConfirmado = $clienteId > 0 && (int) ($equipamento->clientes_id ?? 0) === $clienteId;
        if ($clienteConfirmado) {
            $historico = $this->equipamentos_model->getHistoricoPorEquipamento(
                (int) $equipamento->idEquipamentos,
                $osId,
                50,
                $clienteId
            );
        }

        $itens = [];
        foreach ($historico as $item) {
            $itens[] = $this->formatarHistoricoSerialItem($item);
        }
        $base['found'] = true;
        $base['status'] = 'found';
        $base['equipamento'] = [
            'id' => (int) $equipamento->idEquipamentos,
            'equipamento' => $this->resumirTextoHistorico($equipamento->equipamento ?? null, 200),
            'marca' => $this->resumirTextoHistorico($equipamento->marca_nome ?? null, 200),
            'modelo' => $this->resumirTextoHistorico($equipamento->modelo ?? null, 200),
            'num_serie' => $this->resumirTextoHistorico($equipamento->num_serie ?? null, 200),
        ];
        $base['clienteNecessario'] = ! $clienteConfirmado;
        $base['total'] = count($itens);
        $base['historico'] = $itens;
        $base['garantiaCritica'] = $historico !== []
            ? $this->equipamentos_model->calcularCriticaGarantiaSerial($historico[0])
            : null;

        return $this->json(200, $base);
    }

    private function normalizarSerialConsulta(string $serial): string
    {
        $serial = preg_replace('/\s+/u', ' ', trim($serial)) ?? trim($serial);

        return function_exists('mb_strtoupper') ? mb_strtoupper($serial, 'UTF-8') : strtoupper($serial);
    }

    private function formatarHistoricoSerialItem(object $item): array
    {
        $url = (string) (parse_url(site_url('os/visualizar/' . (int) $item->idOs), PHP_URL_PATH) ?: '/');

        return [
            'idOs' => (int) $item->idOs,
            'cliente' => $this->resumirTextoHistorico($item->cliente ?? null, 200),
            'dataInicial' => $this->formatarDataHistorico($item->dataInicial ?? null),
            'dataFinal' => $this->formatarDataHistorico($item->dataFinal ?? null),
            'status' => $this->resumirTextoHistorico($item->status ?? null, 120),
            'equipamento' => $this->resumirTextoHistorico($item->tipo ?? null, 200),
            'marca' => $this->resumirTextoHistorico($item->marca ?? null, 200),
            'modelo' => $this->resumirTextoHistorico($item->modelo ?? null, 200),
            'num_serie' => $this->resumirTextoHistorico($item->num_serie ?? null, 200),
            'descricaoProduto' => $this->resumirTextoHistorico($item->descricaoProduto ?? null, 2000),
            'defeito' => $this->resumirTextoHistorico($item->defeito ?? null, 2000),
            'laudoTecnico' => $this->resumirTextoHistorico($item->laudoTecnico ?? null, 2000),
            'observacoes' => $this->resumirTextoHistorico($item->observacoes ?? null, 2000),
            'garantiaDias' => is_numeric($item->garantia ?? null) ? (int) $item->garantia : 0,
            'garantiaStatus' => (string) ($item->garantia_status ?? 'indeterminada'),
            'diasRestantes' => (int) ($item->dias_restantes ?? 0),
            'diasVencidos' => (int) ($item->dias_vencidos ?? 0),
            'situacaoGarantia' => (string) ($item->situacao_garantia ?? 'Indefinido'),
            'vencimentoGarantia' => (string) ($item->vencimento_garantia ?? ''),
            'urlVisualizarOs' => $url,
        ];
    }

    private function formatarDataHistorico($valor): string
    {
        $texto = trim((string) $valor);
        $base = substr($texto, 0, 10);
        if ($texto === '' || strlen($base) !== 10 || $base === '0000-00-00') {
            return $texto;
        }

        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $base, new DateTimeZone('America/Sao_Paulo'));

        return $data !== false && $data->format('Y-m-d') === $base ? $data->format('d/m/Y') : $texto;
    }

    private function resumirTextoHistorico($valor, int $limite = 2000): string
    {
        $texto = trim(strip_tags((string) $valor));
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;
        if ($texto === '') {
            return '';
        }
        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, max(1, $limite - 1))) . '…';
    }

    private function formulario(?object $equipamento)
    {
        $erros = [];
        $post = $this->input->method() === 'post';
        $valores = $this->valoresFormulario($equipamento, $post ? $this->input->post() : null);

        if ($post) {
            $erros = $this->validarFormulario('equipamentos');
            if (isset($erros['clientes_id'])) {
                $erros['cliente'] = $erros['clientes_id'];
                unset($erros['clientes_id']);
            }
            $dados = $this->dadosDoPost();
            $id = $equipamento ? (int) $equipamento->idEquipamentos : null;

            if ($erros === [] && ! $this->clienteExiste($dados['clientes_id'])) {
                $erros['cliente'] = 'Escolha um cliente existente da lista.';
            }
            if ($erros === [] && ! $this->dataValida($dados['data_fabricacao'])) {
                $erros['data_fabricacao'] = 'Informe uma data de fabricação válida.';
            }
            $serialMudou = $equipamento === null || trim((string) ($equipamento->num_serie ?? '')) !== $dados['num_serie'];
            if ($erros === [] && $serialMudou && $this->equipamentos_model->serialDuplicado($dados['num_serie'], $id)) {
                $erros['num_serie'] = 'Já existe outro equipamento com este número de série.';
            }
            if ($erros === [] && $equipamento && $this->equipamentos_model->clienteTemOsDiferente($id, $dados['clientes_id'])) {
                $erros['cliente'] = 'O cliente não pode ser trocado enquanto o equipamento possui OS de outro cliente.';
            }

            if ($erros === []) {
                $salvo = $this->equipamentos_model->salvar($dados, $id);
                if ($salvo !== null) {
                    $this->session->set_flashdata('success', $equipamento === null ? 'Equipamento cadastrado com sucesso.' : 'Alterações do equipamento salvas.');
                    return redirect('equipamentos/editar/' . $salvo);
                }
                $erros['_geral'] = 'Não foi possível salvar o equipamento. Tente novamente.';
            }
            $valores = array_merge($valores, $dados, ['cliente' => trim((string) $this->input->post('cliente'))]);
        }

        $this->data['equipamento'] = $equipamento;
        $this->data['valores'] = $valores;
        $this->data['marcas'] = $this->equipamentos_model->marcasAtivas();
        $this->data['erros'] = $erros;
        $this->data['view'] = 'equipamentos/formulario';

        return $this->layout();
    }

    private function dadosDoPost(): array
    {
        return [
            'equipamento' => trim((string) $this->input->post('equipamento')),
            'num_serie' => trim((string) $this->input->post('num_serie')),
            'modelo' => trim((string) $this->input->post('modelo')),
            'cor' => trim((string) $this->input->post('cor')),
            'descricao' => trim((string) $this->input->post('descricao')),
            'tensao' => trim((string) $this->input->post('tensao')),
            'potencia' => trim((string) $this->input->post('potencia')),
            'voltagem' => trim((string) $this->input->post('voltagem')),
            'data_fabricacao' => trim((string) $this->input->post('data_fabricacao')),
            'marca' => trim((string) $this->input->post('marca')),
            'clientes_id' => (int) $this->input->post('clientes_id'),
        ];
    }

    private function valoresFormulario(?object $equipamento, ?array $post): array
    {
        if ($post !== null) {
            return [
                'equipamento' => (string) ($post['equipamento'] ?? ''),
                'num_serie' => (string) ($post['num_serie'] ?? ''),
                'modelo' => (string) ($post['modelo'] ?? ''),
                'cor' => (string) ($post['cor'] ?? ''),
                'descricao' => (string) ($post['descricao'] ?? ''),
                'tensao' => (string) ($post['tensao'] ?? ''),
                'potencia' => (string) ($post['potencia'] ?? ''),
                'voltagem' => (string) ($post['voltagem'] ?? ''),
                'data_fabricacao' => (string) ($post['data_fabricacao'] ?? ''),
                'marca' => (string) ($post['marca'] ?? ''),
                'clientes_id' => (string) ($post['clientes_id'] ?? ''),
                'cliente' => (string) ($post['cliente'] ?? ''),
            ];
        }
        if ($equipamento === null) {
            return array_fill_keys(['equipamento', 'num_serie', 'modelo', 'cor', 'descricao', 'tensao', 'potencia', 'voltagem', 'data_fabricacao', 'marca', 'clientes_id', 'cliente'], '');
        }

        return [
            'equipamento' => (string) $equipamento->equipamento,
            'num_serie' => (string) ($equipamento->num_serie ?? ''),
            'modelo' => (string) ($equipamento->modelo ?? ''),
            'cor' => (string) ($equipamento->cor ?? ''),
            'descricao' => (string) ($equipamento->descricao ?? ''),
            'tensao' => (string) ($equipamento->tensao ?? ''),
            'potencia' => (string) ($equipamento->potencia ?? ''),
            'voltagem' => (string) ($equipamento->voltagem ?? ''),
            'data_fabricacao' => (string) ($equipamento->data_fabricacao ?? ''),
            'marca' => (string) ($equipamento->marca_nome ?? ''),
            'clientes_id' => (string) ($equipamento->cliente_id ?? ''),
            'cliente' => (string) ($equipamento->nomeCliente ?? ''),
        ];
    }

    private function clienteExiste(int $id): bool
    {
        return $id > 0 && $this->db->where('idClientes', $id)->count_all_results('clientes') > 0;
    }

    private function dataValida(string $valor): bool
    {
        if ($valor === '') {
            return true;
        }
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return $data !== false && $data->format('Y-m-d') === $valor;
    }

    private function json(int $status, array $corpo)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
