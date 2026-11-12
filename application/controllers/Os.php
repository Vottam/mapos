<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Os extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'os', 'clientes']);
        $this->load->model('os_model');
        $this->load->model('equipamentos_model');
        $this->data['menuOs'] = 'OS';
    }

    /**
     * No GET o CI_Input::post() devolve um array vazio. Para a tela de edição,
     * array vazio não é POST inválido: é ausência de POST e deve hidratar a OS.
     */
    private function postDoFormulario(): ?array
    {
        return osPostParaFormulario($this->input->post());
    }

    /**
     * O formulário v5 usa input type=date (AAAA-MM-DD). A conversão antiga
     * esperava barras e podia gravar uma data corrompida ao editar.
     */
    private function datasDoFormulario(): ?array
    {
        $dataInicial = osDataParaBanco($this->input->post('dataInicial'));
        $dataFinal = osDataParaBanco($this->input->post('dataFinal'));
        if ($dataInicial === null || ($dataFinal !== null && $dataFinal < $dataInicial)) {
            return null;
        }

        return ['dataInicial' => $dataInicial, 'dataFinal' => $dataFinal];
    }

    private function gerarTokenCriacaoOs()
    {
        $token = bin2hex(random_bytes(16));
        $this->session->set_userdata('os_create_token', $token);

        return $token;
    }

    private function obterTokenCriacaoOs()
    {
        $token = (string) $this->session->userdata('os_create_token');

        if ($token === '') {
            return $this->gerarTokenCriacaoOs();
        }

        return $token;
    }

    private function validarTokenCriacaoOs($tokenRecebido)
    {
        $tokenSessao = (string) $this->session->userdata('os_create_token');

        if ($tokenSessao === '' || $tokenRecebido === '') {
            return false;
        }

        return hash_equals($tokenSessao, $tokenRecebido);
    }

    private function consumirTokenCriacaoOs()
    {
        $this->session->unset_userdata('os_create_token');
    }

    private function equipamentoFromPost()
    {
        $tipo = $this->input->post('equipamento_tipo');
        if ($tipo === null) {
            $tipo = $this->input->post('equipamento');
        }

        return [
            'equipamento' => trim((string) $tipo),
            'marca' => trim((string) $this->input->post('equipamento_marca')),
            'modelo' => trim((string) $this->input->post('equipamento_modelo')),
            'num_serie' => trim((string) $this->input->post('equipamento_num_serie')),
        ];
    }

    private function equipamentoValoresDoFormulario($equipamento = null)
    {
        if ($this->input->method(true) === 'POST') {
            $dados = $this->equipamentoFromPost();

            return [
                'id' => is_numeric($this->input->post('equipamento_id')) ? (int) $this->input->post('equipamento_id') : 0,
                'equipamento' => $dados['equipamento'],
                'marca' => $dados['marca'],
                'modelo' => $dados['modelo'],
                'num_serie' => $dados['num_serie'],
            ];
        }

        return [
            'id' => (int) ($equipamento->idEquipamentos ?? 0),
            'equipamento' => (string) ($equipamento->equipamento ?? ''),
            'marca' => (string) ($equipamento->marca_nome ?? ''),
            'modelo' => (string) ($equipamento->modelo ?? ''),
            'num_serie' => (string) ($equipamento->num_serie ?? ''),
        ];
    }

    private function equipamentoSelecionadoFromPost()
    {
        $equipamentoId = $this->input->post('equipamento_id');

        return is_numeric($equipamentoId) ? (int) $equipamentoId : null;
    }

    private function equipamentoDadosBatemSelecionado(array $equipamentoData, $equipamentoSelecionado)
    {
        if (! $equipamentoSelecionado) {
            return false;
        }

        $postNormalizado = $this->os_model->normalizarEquipamentoDados($equipamentoData);
        $selecionadoNormalizado = $this->os_model->normalizarEquipamentoDados([
            'equipamento' => $equipamentoSelecionado->equipamento ?? '',
            'marca' => $equipamentoSelecionado->marca_nome ?? '',
            'modelo' => $equipamentoSelecionado->modelo ?? '',
            'num_serie' => $equipamentoSelecionado->num_serie ?? '',
        ]);

        return $postNormalizado == $selecionadoNormalizado;
    }

    private function salvarOuVincularEquipamentoOs($osId, $clienteId, array $equipamentoData, $equipamentoSelecionado = null, $equipamentoAtual = null, $serialInternoSugerido = false)
    {
        if ($equipamentoSelecionado) {
            if ($this->equipamentoDadosBatemSelecionado($equipamentoData, $equipamentoSelecionado)) {
                return $this->os_model->vincularEquipamentoOsExistente($osId, $equipamentoSelecionado->idEquipamentos);
            }

            return $this->os_model->salvarEquipamentoOs($osId, $clienteId, $equipamentoData, $equipamentoSelecionado, $serialInternoSugerido);
        }

        if ($this->hasEquipamentoData($equipamentoData) || ! $equipamentoAtual) {
            return $this->os_model->salvarEquipamentoOs($osId, $clienteId, $equipamentoData, $equipamentoAtual, $serialInternoSugerido);
        }

        if ($equipamentoAtual) {
            return $this->os_model->removerEquipamentoOs($osId);
        }

        return true;
    }

    private function hasEquipamentoData(array $dados)
    {
        foreach ($dados as $valor) {
            if (trim((string) $valor) !== '') {
                return true;
            }
        }

        return false;
    }

    private function resumirTextoHistorico($texto, $limite = 140)
    {
        $texto = trim(preg_replace('/\s+/', ' ', strip_tags((string) $texto)));

        if ($texto === '') {
            return '-';
        }

        if (function_exists('mb_strlen') && mb_strlen($texto, 'UTF-8') > $limite) {
            return mb_substr($texto, 0, $limite, 'UTF-8') . '...';
        }

        if (strlen($texto) > $limite) {
            return substr($texto, 0, $limite) . '...';
        }

        return $texto;
    }

    private function formatarHistoricoSerialItem($row)
    {
        $equipamento = trim(implode(' ', array_filter([
            $row->tipo ?? '',
            $row->marca ?? '',
            $row->modelo ?? '',
        ])));

        return [
            'idOs' => (int) ($row->idOs ?? 0),
            'cliente' => $row->cliente ?? '',
            'dataInicial' => ! empty($row->dataInicial) ? date('d/m/Y', strtotime($row->dataInicial)) : '',
            'dataFinal' => ! empty($row->dataFinal) ? date('d/m/Y', strtotime($row->dataFinal)) : '',
            'status' => $row->status ?? '',
            'garantia' => isset($row->garantia) ? (int) $row->garantia : 0,
            'vencimentoGarantia' => $row->vencimento_garantia ?? null,
            'situacaoGarantia' => $row->situacao_garantia ?? 'Indefinido',
            'descricaoProduto' => $this->resumirTextoHistorico($row->descricaoProduto ?? '', 140),
            'defeito' => $this->resumirTextoHistorico($row->defeito ?? '', 140),
            'laudoTecnico' => $this->resumirTextoHistorico($row->laudoTecnico ?? '', 140),
            'observacoes' => $this->resumirTextoHistorico($row->observacoes ?? '', 140),
            'equipamento' => $equipamento !== '' ? $equipamento : 'Não informado',
            'marca' => $row->marca ?? '',
            'modelo' => $row->modelo ?? '',
            'num_serie' => $row->num_serie ?? '',
            'urlVisualizarOs' => site_url('os/visualizar/' . (int) ($row->idOs ?? 0)),
        ];
    }

    public function equipamentosCliente($idCliente = null)
    {
        $this->output->set_content_type('application/json');

        if ($idCliente === null || ! is_numeric($idCliente)) {
            echo json_encode(['equipamentos' => []]);
            return;
        }

        $equipamentos = $this->os_model->getEquipamentosCliente((int) $idCliente);
        echo json_encode(['equipamentos' => $equipamentos]);
    }

    public function index()
    {
        $this->gerenciar();
    }

    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar O.S.');
            redirect(base_url());
        }

        $normalizarData = static function ($valor): ?string {
            $valor = trim((string) $valor);
            if ($valor === '') {
                return null;
            }

            $iso = dataIsoParaYmd($valor);
            if ($iso !== null) {
                return $iso;
            }

            $data = DateTime::createFromFormat('d/m/Y', $valor);

            return $data instanceof DateTime ? $data->format('Y-m-d') : null;
        };

        $pesquisa = is_scalar($this->input->get('pesquisa')) ? trim((string) $this->input->get('pesquisa')) : '';
        $status = is_scalar($this->input->get('status')) ? trim((string) $this->input->get('status')) : '';
        $de = $normalizarData($this->input->get('de') ?: $this->input->get('data'));
        $ate = $normalizarData($this->input->get('ate') ?: $this->input->get('data2'));
        $filtros = [];
        $where_array = [];

        if ($pesquisa !== '') {
            $filtros['pesquisa'] = $pesquisa;
            $where_array['pesquisa'] = $pesquisa;
        }
        if ($status !== '' && isset(OS_STATUS_VARIANTES[$status])) {
            $filtros['status'] = $status;
            $where_array['status'] = [$status];
        }
        if ($de !== null) {
            $filtros['de'] = $de;
            $where_array['de'] = $de;
        }
        if ($ate !== null) {
            $filtros['ate'] = $ate;
            $where_array['ate'] = $ate;
        }

        $statusVisiveis = osStatusVisiveis($this->data['configuration']['os_status_list'] ?? null);
        $semFiltroDeStatus = $pesquisa === '' && $status === '';
        if ($semFiltroDeStatus && $statusVisiveis !== null) {
            $where_array['status'] = $statusVisiveis;
        }

        $total = $this->os_model->countOs($where_array);
        $offset = max(0, (int) $this->uri->segment(3));
        $this->data['configuration']['total_rows'] = $total;
        $this->data['results'] = $this->os_model->getOs(
            'os',
            'os.*,
            COALESCE((SELECT SUM(produtos_os.preco * produtos_os.quantidade ) FROM produtos_os WHERE produtos_os.os_id = os.idOs), 0) totalProdutos,
            COALESCE((SELECT SUM(servicos_os.preco * servicos_os.quantidade ) FROM servicos_os WHERE servicos_os.os_id = os.idOs), 0) totalServicos,
            COALESCE((SELECT SUM(CAST(produtos_os.custo_total AS DECIMAL(20,4))) FROM produtos_os WHERE produtos_os.os_id = os.idOs), 0) custo_componentes,
            (SELECT equipamentos.equipamento FROM equipamentos_os JOIN equipamentos ON equipamentos.idEquipamentos = equipamentos_os.equipamentos_id WHERE equipamentos_os.os_id = os.idOs LIMIT 1) equipamento_tipo,
            (SELECT equipamentos.modelo FROM equipamentos_os JOIN equipamentos ON equipamentos.idEquipamentos = equipamentos_os.equipamentos_id WHERE equipamentos_os.os_id = os.idOs LIMIT 1) equipamento_modelo,
            (SELECT marcas.marca FROM equipamentos_os JOIN equipamentos ON equipamentos.idEquipamentos = equipamentos_os.equipamentos_id JOIN marcas ON marcas.idMarcas = equipamentos.marcas_id WHERE equipamentos_os.os_id = os.idOs LIMIT 1) equipamento_marca,
            COALESCE((SELECT SUM(IF(l.valor_desconto = 0, l.valor, l.valor_desconto)) FROM lancamentos l WHERE l.tipo = \'receita\' AND l.descricao = CONCAT(\'Fatura de OS Nº: \', os.idOs)), 0) valor_faturado_real',
            $where_array,
            (int) $this->data['configuration']['per_page'],
            $offset
        );

        // Calcula em centavos para evitar erros binários de ponto flutuante.
        $paraCentavos = static function ($valor) {
            return (int) round((float) $valor * 100, 0, PHP_ROUND_HALF_UP);
        };
        foreach ($this->data['results'] as $resultado) {
            $valorBrutoCentavos = $paraCentavos($resultado->totalProdutos) + $paraCentavos($resultado->totalServicos);
            $valorFinalRegistradoCentavos = $paraCentavos($resultado->valor_desconto);
            $descontoAplicadoCentavos = $paraCentavos($resultado->desconto);
            $valorBaseCentavos = $descontoAplicadoCentavos > 0 ? $valorFinalRegistradoCentavos : $valorBrutoCentavos;
            $custoComponentesCentavos = $paraCentavos($resultado->custo_componentes);
            $resultado->total = (float) $resultado->totalProdutos + (float) $resultado->totalServicos;
            $resultado->valorLiquido = ($valorBaseCentavos - $custoComponentesCentavos) / 100;
            $resultado->vtFaturado = (float) ($resultado->valor_faturado_real ?? 0);
        }

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['status_ocultos'] = $semFiltroDeStatus && $statusVisiveis !== null
            && array_diff(array_keys(OS_STATUS_VARIANTES), $statusVisiveis) !== [];
        $paginaBase = (string) (parse_url(site_url('os/gerenciar'), PHP_URL_PATH) ?: '/index.php/os/gerenciar');
        $this->data['paginacao'] = $this->paginacao($paginaBase, $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aOs'),
            'editar' => $this->permite('eOs'),
            'excluir' => $this->permite('dOs'),
        ];
        $this->data['controle_editos'] = ($this->data['configuration']['control_editos'] ?? '0') === '1';
        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Nova OS', 'icon' => 'plus', 'href' => site_url('os/adicionar')];
        }

        $this->data['texto_de_notificacao'] = $this->data['configuration']['notifica_whats'];
        $this->load->model('mapos_model');
        $this->data['emitente'] = $this->mapos_model->getEmitente();
        $this->data['view'] = 'os/os';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar O.S.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';
        $this->data['equipamento'] = null;
        $this->data['serialInternoSugerido'] = $this->os_model->getProximoSerialInternoEstimado();
        $this->data['os_create_token'] = $this->obterTokenCriacaoOs();

        if ($this->input->method(true) === 'POST') {
            $tokenRecebido = (string) $this->input->post('os_create_token');

            if (! $this->validarTokenCriacaoOs($tokenRecebido)) {
                $this->consumirTokenCriacaoOs();
                $this->session->set_flashdata('error', 'A solicitação da OS já foi processada ou expirou. Reabra a tela para criar uma nova OS.');
                redirect(site_url('os/adicionar'));
            }
        }

        $postFormulario = $this->postDoFormulario();
        $datasPostadas = $postFormulario !== null ? $this->datasDoFormulario() : null;
        $datasInvalidas = $postFormulario !== null && $datasPostadas === null;
        if ($postFormulario === null || $this->form_validation->run('os') == false || $datasInvalidas) {
            $errosValidacao = (string) validation_errors();
            $this->data['custom_error'] = $errosValidacao !== ''
                ? '<div class="form_error">' . $errosValidacao . '</div>'
                : ($datasInvalidas ? '<div class="form_error"><p>Informe um intervalo de datas válido para a OS.</p></div>' : false);
        } else {
            $dataInicial = $datasPostadas['dataInicial'];
            $dataFinal = $datasPostadas['dataFinal'];
            $termoGarantiaId = $this->input->post('garantias_id') ?: null;
            $equipamentoData = $this->equipamentoFromPost();
            $clienteId = $this->input->post('clientes_id');
            $equipamentoSelecionadoId = $this->equipamentoSelecionadoFromPost();
            $equipamentoSelecionado = $equipamentoSelecionadoId ? $this->os_model->getEquipamentoByIdAndCliente($equipamentoSelecionadoId, $clienteId) : null;
            $serialInternoSugerido = $this->input->post('serial_interno_sugerido') === '1';

            $data = [
                'dataInicial' => $dataInicial,
                'clientes_id' => $clienteId,
                'usuarios_id' => $this->input->post('usuarios_id'),
                'dataFinal' => $dataFinal,
                'garantia' => set_value('garantia'),
                'garantias_id' => $termoGarantiaId,
                'descricaoProduto' => $this->input->post('descricaoProduto'),
                'defeito' => $this->input->post('defeito'),
                'status' => set_value('status'),
                'observacoes' => $this->input->post('observacoes'),
                'laudoTecnico' => $this->input->post('laudoTecnico'),
                'faturado' => 0,
                'garantia_retorno' => $this->input->post('garantia_retorno') ? 1 : 0,
                'garantia_origem_os_id' => is_numeric($this->input->post('garantia_origem_os_id')) ? (int) $this->input->post('garantia_origem_os_id') : null,
            ];
            $data = $this->os_model->dadosDaTransicaoDeEntrega($data, (object) [
                'status' => null,
                'dataEntrega' => null,
            ]);

            $this->consumirTokenCriacaoOs();

            if (is_numeric($id = $this->os_model->add('os', $data, true))) {
                $this->salvarOuVincularEquipamentoOs($id, $clienteId, $equipamentoData, $equipamentoSelecionado, null, $serialInternoSugerido);

                $this->load->model('mapos_model');
                $this->load->model('usuarios_model');

                $idOs = $id;
                $os = $this->os_model->getById($idOs);
                $emitente = $this->mapos_model->getEmitente();

                $tecnico = $this->usuarios_model->getById($os->usuarios_id);

                // Verificar configuração de notificação e whitelist de status
                if (
                    $this->data['configuration']['os_notification'] != 'nenhum'
                    && $this->data['configuration']['email_automatico'] == 1
                    && $this->deveEnviarEmailAutomaticoPorStatus(null, $os->status)
                ) {
                    $remetentes = [];
                    switch ($this->data['configuration']['os_notification']) {
                        case 'todos':
                            array_push($remetentes, $os->email);
                            array_push($remetentes, $tecnico->email);
                            array_push($remetentes, $emitente->email);
                            break;
                        case 'cliente':
                            array_push($remetentes, $os->email);
                            break;
                        case 'tecnico':
                            array_push($remetentes, $tecnico->email);
                            break;
                        case 'emitente':
                            array_push($remetentes, $emitente->email);
                            break;
                        default:
                            array_push($remetentes, $os->email);
                            break;
                    }
                    $this->enviarOsPorEmail($idOs, $remetentes, 'Ordem de Serviço - Criada');
                }

                $this->session->set_flashdata('success', 'OS adicionada com sucesso, você pode adicionar produtos ou serviços a essa OS nas abas de Produtos e Serviços!');
                log_info('Adicionou uma OS. ID: ' . $id);
                redirect(site_url('os/editar/') . $id);
            } else {
                $this->data['custom_error'] = '<div class="alert">Ocorreu um erro.</div>';
            }
        }

        $this->data['os'] = null;
        $this->data['valores'] = osValoresDoFormulario(
            $this->postDoFormulario(),
            null,
            [
                'tecnico' => (string) $this->session->userdata('nome_admin'),
                'usuarios_id' => (string) $this->session->userdata('id_admin'),
                'status' => 'Aberto',
                'dataInicial' => date('Y-m-d'),
            ]
        );
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['pode'] = [
            'ver_os' => $this->permite('vOs'),
            'cadastrar_cliente' => $this->permite('aCliente'),
        ];
        $this->data['equipamento_valores'] = $this->equipamentoValoresDoFormulario(null);
        $this->data['formatados'] = [];
        $this->data['view'] = 'os/formulario';

        return $this->layout();
    }

    public function editar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3)) || ! $this->os_model->getById($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'OS não encontrada ou parâmetro inválido.');
            redirect('os/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar O.S.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';
        $this->data['texto_de_notificacao'] = $this->data['configuration']['notifica_whats'];
        $this->data['equipamento'] = $this->os_model->getEquipamentoOs($this->uri->segment(3));

        $this->data['editavel'] = $this->os_model->isEditable($this->input->post('idOs') ?: $this->uri->segment(3));
        $osParaEdicao = $this->os_model->getById((int) $this->uri->segment(3));
        $statusPostado = trim((string) $this->input->post('status'));
        $entregaDeOsFaturada = $osParaEdicao
            && (int) $osParaEdicao->faturado === 1
            && strtolower(trim((string) $osParaEdicao->status)) === 'faturado'
            && strtolower($statusPostado) === 'entregue';
        if (! $this->data['editavel'] && ! $entregaDeOsFaturada) {
            $this->session->set_flashdata('error', 'Esta OS já e seu status não pode ser alterado e nem suas informações atualizadas. Por favor abrir uma nova OS.');

            redirect(site_url('os'));
        }

        $postFormulario = $this->postDoFormulario();
        $datasPostadas = $postFormulario !== null ? $this->datasDoFormulario() : null;
        $datasInvalidas = $postFormulario !== null && $datasPostadas === null;
        if ($postFormulario === null || $this->form_validation->run('os') == false || $datasInvalidas) {
            $errosValidacao = (string) validation_errors();
            $this->data['custom_error'] = $errosValidacao !== ''
                ? '<div class="form_error">' . $errosValidacao . '</div>'
                : ($datasInvalidas ? '<div class="form_error"><p>Informe um intervalo de datas válido para a OS.</p></div>' : false);
        } else {
            $dataInicial = $datasPostadas['dataInicial'];
            $dataFinal = $datasPostadas['dataFinal'];
            $termoGarantiaId = $this->input->post('garantias_id') ?: null;
            $equipamentoData = $this->equipamentoFromPost();
            $clienteId = $this->input->post('clientes_id');
            $equipamentoSelecionadoId = $this->equipamentoSelecionadoFromPost();
            $equipamentoSelecionado = $equipamentoSelecionadoId ? $this->os_model->getEquipamentoByIdAndCliente($equipamentoSelecionadoId, $clienteId) : null;
            $serialInternoSugerido = $this->input->post('serial_interno_sugerido') === '1';

            $data = [
                'dataInicial' => $dataInicial,
                'dataFinal' => $dataFinal,
                'garantia' => $this->input->post('garantia'),
                'garantias_id' => $termoGarantiaId,
                'descricaoProduto' => $this->input->post('descricaoProduto'),
                'defeito' => $this->input->post('defeito'),
                'status' => $this->input->post('status'),
                'observacoes' => $this->input->post('observacoes'),
                'laudoTecnico' => $this->input->post('laudoTecnico'),
                'usuarios_id' => $this->input->post('usuarios_id'),
                'clientes_id' => $clienteId,
                'garantia_retorno' => $this->input->post('garantia_retorno') ? 1 : 0,
                'garantia_origem_os_id' => is_numeric($this->input->post('garantia_origem_os_id')) ? (int) $this->input->post('garantia_origem_os_id') : null,
            ];
            $os = $this->os_model->getById($this->input->post('idOs'));
            $statusAnterior = $os->status;
            $statusNovo = $this->input->post('status');
            $entregaSomenteStatus = $entregaDeOsFaturada;
            if ($entregaSomenteStatus) {
                // OS faturada: a retirada só pode mudar o status e a data efetiva.
                $data = ['status' => $statusNovo];
            }
            $data = $this->os_model->dadosDaTransicaoDeEntrega($data, $os);

            // Verifica para poder fazer a devolução do produto para o estoque caso OS seja cancelada.

            $this->db->trans_begin();
            $transacaoOk = false;
            if (strtolower($this->input->post('status')) == 'cancelado' && strtolower($os->status) != 'cancelado') {
                $this->devolucaoEstoque($this->input->post('idOs'));
            }

            if (strtolower($os->status) == 'cancelado' && strtolower($this->input->post('status')) != 'cancelado') {
                $this->debitarEstoque($this->input->post('idOs'));
            }

            if ($this->os_model->edit('os', $data, 'idOs', $this->input->post('idOs')) == true) {
                $equipamentoResultado = true;
                if (! $entregaSomenteStatus) {
                    $equipamentoResultado = $this->salvarOuVincularEquipamentoOs($this->input->post('idOs'), $clienteId, $equipamentoData, $equipamentoSelecionado, $this->data['equipamento'], $serialInternoSugerido);
                }

                if ($equipamentoResultado === false) {
                    $this->db->trans_rollback();
                    $this->data['custom_error'] = '<div class="form_error"><p>Não foi possível salvar os dados do equipamento. A OS não foi confirmada.</p></div>';
                } else {
                    $this->os_model->registrarStatus(
                        (int) $this->input->post('idOs'),
                        $statusAnterior,
                        (string) $statusNovo,
                        (int) $this->session->userdata('id_admin') ?: null
                    );

                    if ($this->db->trans_status() === false) {
                        $this->db->trans_rollback();
                        $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro transacional ao salvar a OS.</p></div>';
                    } else {
                        $this->db->trans_commit();
                        $transacaoOk = true;
                    }
                }
            } else {
                $this->db->trans_rollback();
            }

            if ($transacaoOk) {
                $this->load->model('mapos_model');
                $this->load->model('usuarios_model');

                $idOs = $this->input->post('idOs');

                $os = $this->os_model->getById($idOs);
                $emitente = $this->mapos_model->getEmitente();
                $tecnico = $this->usuarios_model->getById($os->usuarios_id);

                // Verificar configuração de notificação e whitelist de status
                if (
                    $this->data['configuration']['os_notification'] != 'nenhum'
                    && $this->data['configuration']['email_automatico'] == 1
                    && $this->deveEnviarEmailAutomaticoPorStatus($statusAnterior, $statusNovo)
                ) {
                    $remetentes = [];
                    switch ($this->data['configuration']['os_notification']) {
                        case 'todos':
                            array_push($remetentes, $os->email);
                            array_push($remetentes, $tecnico->email);
                            array_push($remetentes, $emitente->email);
                            break;
                        case 'cliente':
                            array_push($remetentes, $os->email);
                            break;
                        case 'tecnico':
                            array_push($remetentes, $tecnico->email);
                            break;
                        case 'emitente':
                            array_push($remetentes, $emitente->email);
                            break;
                        default:
                            array_push($remetentes, $os->email);
                            break;
                    }
                    $this->enviarOsPorEmail($idOs, $remetentes, 'Ordem de Serviço - Editada');
                }

                $this->session->set_flashdata('success', 'Os editada com sucesso!');
                log_info('Alterou uma OS. ID: ' . $this->input->post('idOs'));
                redirect(site_url('os/editar/') . $this->input->post('idOs'));
            } else {
                if (! $this->data['custom_error']) {
                    $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro</p></div>';
                }
            }
        }

        $os = $this->os_model->getById($this->uri->segment(3));
        $this->data['result'] = $os;
        $this->data['os'] = $os;
        $this->data['valores'] = osValoresDoFormulario($this->postDoFormulario(), $os);
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['pode'] = [
            'ver_os' => $this->permite('vOs'),
            'cadastrar_cliente' => $this->permite('aCliente'),
        ];
        $this->data['equipamento_valores'] = $this->equipamentoValoresDoFormulario($this->data['equipamento']);
        $this->data['formatados'] = [];
        $this->data['view'] = 'os/formulario';

        return $this->layout();
    }

    public function visualizar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar O.S.');
            redirect(base_url());
        }

        $this->data['result'] = $this->os_model->getById((int) $this->uri->segment(3));

        if (! $this->data['result']) {
            $this->session->set_flashdata('error', 'OS não encontrada ou parâmetro inválido.');
            redirect('os/gerenciar');
        }

        $aba = listagemFiltros(['aba' => OS_ABAS], $this->input->get())['aba'] ?? 'resumo';
        $this->data = array_merge($this->data, $this->dadosDaTela($this->data['result'], $aba));

        if ($this->data['pode']['editar']) {
            $this->data['topbar_acao'] = ['label' => 'Editar OS', 'icon' => 'pencil', 'href' => site_url('os/editar/' . (int) $this->data['result']->idOs)];
        }

        $this->data['gateways'] = [];
        if ($this->data['pode']['cobrar']) {
            $this->load->config('payment_gateways');
            $this->data['gateways'] = osOpcoesDeCobranca($this->config->item('payment_gateways'));
        }

        $this->data['view'] = 'os/visualizar';

        return $this->layout();
    }

    /** Dados usados pela tela moderna da OS e pelos seus componentes. */
    private function dadosDaTela(object $os, string $aba): array
    {
        $idOs = (int) $os->idOs;
        $this->load->model('mapos_model');
        $emitente = $this->mapos_model->getEmitente();
        $configuracao = $this->data['configuration'];

        $totais = $this->os_model->totais($os);
        $editavel = $this->os_model->isEditable($idOs);
        $cobrancas = $this->os_model->getCobrancas($idOs);
        $produtos = $this->os_model->getProdutos($idOs);
        $servicos = $this->os_model->getServicos($idOs);
        $anexos = $this->os_model->getAnexos($idOs);
        $anotacoes = $this->os_model->getAnotacoes($idOs);
        $historico = $this->os_model->getHistorico($idOs);

        $pixPayload = $this->os_model->getPixPayload($idOs, $configuracao['pix_key'] ?? null, $emitente);
        $telefone = osTelefoneWhatsApp($os->celular_cliente ?: $os->telefone_cliente);
        $troca = [
            '{CLIENTE_NOME}' => (string) $os->nomeCliente,
            '{NUMERO_OS}' => (string) $idOs,
            '{STATUS_OS}' => (string) $os->status,
            '{VALOR_OS}' => dinheiro($totais['total']),
            '{DESCRI_PRODUTOS}' => (string) $os->descricaoProduto,
            '{EMITENTE}' => (string) ($emitente->nome ?? ''),
            '{TELEFONE_EMITENTE}' => (string) ($emitente->telefone ?? ''),
            '{OBS_OS}' => (string) $os->observacoes,
            '{DEFEITO_OS}' => (string) $os->defeito,
            '{LAUDO_OS}' => (string) $os->laudoTecnico,
            '{DATA_FINAL}' => dataBr($os->dataFinal),
            '{DATA_INICIAL}' => dataBr($os->dataInicial),
            '{DATA_GARANTIA}' => (string) $os->garantia . ' dias',
        ];

        return [
            'os' => $os,
            'aba' => $aba,
            'totais' => $totais,
            'produtos' => $produtos,
            'servicos' => $servicos,
            'anexos' => $anexos,
            'anotacoes' => $anotacoes,
            'historico' => $historico,
            'equipamento' => $this->equipamentos_model->getByOs($idOs),
            'equipamentosDaOs' => $this->equipamentos_model->listarPorOs($idOs),
            'historico_disponivel' => $this->os_model->historicoDisponivel(),
            'cobranca' => $cobrancas[0] ?? null,
            'emitente' => $emitente,
            'controle_estoque' => (bool) ($configuracao['control_estoque'] ?? false),
            'pix' => $pixPayload === null ? null : [
                'payload' => $pixPayload,
                'qr' => $this->os_model->getQrCode($idOs, $configuracao['pix_key'] ?? null, $emitente),
                'chave' => $this->formatarChave($configuracao['pix_key'] ?? null),
            ],
            'whatsapp' => $telefone === null ? null : [
                'telefone' => $telefone,
                'texto' => osTextoWhatsApp($configuracao['notifica_whats'] ?? '', $troca),
            ],
            'garantia_ate' => osVencimentoGarantia($os->dataFinal, $os->garantia, $os->status, $os->dataEntrega ?? null),
            'pode' => [
                'editar' => $editavel,
                'faturar' => $editavel && (int) $os->faturado === 0 && $os->status !== 'Cancelado',
                'excluir' => $editavel && $this->permite('dOs'),
                'cobrar' => $this->permite('aCobranca') && ! $cobrancas && $totais['total'] > 0,
                'ver_cobranca' => $this->permite('vCobranca'),
                'ver_cliente' => $this->permite('vCliente'),
            ],
        ];
    }

    /**
     * Trechos da tela renderizados de novo depois de uma mutação confirmada.
     * O cliente (assets/js/modules/os/tela.js) troca os que existem na página
     * e ignora o resto. Nunca lança exceção: em falha de render, loga e
     * devolve as partes que saíram.
     */
    private function trechosDaOs(int $idOs, ?string $aba = null): array
    {
        $os = $this->os_model->getById($idOs);
        if ($os === null) {
            return [];
        }

        $aba = is_string($aba) && $aba !== '' ? $aba : 'resumo';
        $dados = array_merge($this->data, $this->dadosDaTela($os, $aba));
        $trechos = [];
        foreach (['produtos', 'servicos', 'anexos', 'anotacoes', 'historico', 'abas', 'totais', 'desconto', 'pix'] as $parte) {
            try {
                $trechos[$parte] = $this->load->view('os/partes/' . $parte, $dados, true);
            } catch (\Throwable $e) {
                log_message('error', 'trechosDaOs: falha ao renderizar a parte ' . $parte . ': ' . $e->getMessage());
            }
        }

        return $trechos;
    }

    /** Resposta JSON com cabeçalho e unicode livres das partes renderizadas. */
    private function responderOsJson(int $status, array $corpo): void
    {
        $this->output
            ->set_content_type('application/json')
            ->set_status_header($status)
            ->set_output(json_encode($corpo, JSON_UNESCAPED_UNICODE));
    }

    public function validarCPF($cpf)
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1+$/', $cpf)) {
            return false;
        }
        $soma1 = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma1 += $cpf[$i] * (10 - $i);
        }
        $resto1 = $soma1 % 11;
        $dv1 = ($resto1 < 2) ? 0 : 11 - $resto1;
        if ($dv1 != $cpf[9]) {
            return false;
        }
        $soma2 = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma2 += $cpf[$i] * (11 - $i);
        }
        $resto2 = $soma2 % 11;
        $dv2 = ($resto2 < 2) ? 0 : 11 - $resto2;

        return $dv2 == $cpf[10];
    }

    public function validarCNPJ($cnpj)
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1+$/', $cnpj)) {
            return false;
        }
        $soma1 = 0;
        for ($i = 0, $pos = 5; $i < 12; $i++, $pos--) {
            $pos = ($pos < 2) ? 9 : $pos;
            $soma1 += $cnpj[$i] * $pos;
        }
        $dv1 = ($soma1 % 11 < 2) ? 0 : 11 - ($soma1 % 11);
        if ($dv1 != $cnpj[12]) {
            return false;
        }
        $soma2 = 0;
        for ($i = 0, $pos = 6; $i < 13; $i++, $pos--) {
            $pos = ($pos < 2) ? 9 : $pos;
            $soma2 += $cnpj[$i] * $pos;
        }
        $dv2 = ($soma2 % 11 < 2) ? 0 : 11 - ($soma2 % 11);

        return $dv2 == $cnpj[13];
    }

    public function formatarChave($chave)
    {
        if ($this->validarCPF($chave)) {
            return substr($chave, 0, 3) . '.' . substr($chave, 3, 3) . '.' . substr($chave, 6, 3) . '-' . substr($chave, 9);
        } elseif ($this->validarCNPJ($chave)) {
            return substr($chave, 0, 2) . '.' . substr($chave, 2, 3) . '.' . substr($chave, 5, 3) . '/' . substr($chave, 8, 4) . '-' . substr($chave, 12);
        } elseif (strlen($chave) === 11) {
            return '(' . substr($chave, 0, 2) . ') ' . substr($chave, 2, 5) . '-' . substr($chave, 7);
        }

        return $chave;
    }

    public function imprimir()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar O.S.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->load->model('mapos_model');
        $this->data['equipamento'] = $this->os_model->getEquipamentoOs($this->uri->segment(3));
        $this->data['result'] = $this->os_model->getById($this->uri->segment(3));
        $this->data['produtos'] = $this->os_model->getProdutos($this->uri->segment(3));
        $this->data['servicos'] = $this->os_model->getServicos($this->uri->segment(3));
        $this->data['anexos'] = $this->os_model->getAnexos($this->uri->segment(3));
        $this->data['emitente'] = $this->mapos_model->getEmitente();
        if ($this->data['configuration']['pix_key']) {
            $this->data['qrCode'] = $this->os_model->getQrCode(
                $this->uri->segment(3),
                $this->data['configuration']['pix_key'],
                $this->data['emitente']
            );
            $this->data['chaveFormatada'] = $this->formatarChave($this->data['configuration']['pix_key']);
        }
        
        $this->data['imprimirAnexo'] = isset($_ENV['IMPRIMIR_ANEXOS']) ? (filter_var($_ENV['IMPRIMIR_ANEXOS'] ?? false, FILTER_VALIDATE_BOOLEAN)) : false;

        $this->load->helper('mpdf');
        $dataPdf = $this->data;
        $dataPdf['configuration']['control_2vias'] = false;
        $dataPdf['imprimirAnexo'] = false;
        $html = $this->load->view('os/imprimirOs', $dataPdf, true);
        $html = str_replace(['<footer>', '</footer>'], ['<div class="pdf-os-footer">', '</div>'], $html);
        $pdfCss = '<style>html,body{width:auto;height:auto;margin:0;padding:0;background:#fff}.main-page{display:block;width:auto;min-height:0;margin:0;padding:0;background:#fff;border:0;border-radius:0;box-shadow:none;page-break-after:avoid}.sub-page{display:block;position:static;min-height:0;page-break-after:avoid}.pdf-os-footer{margin-top:12pt;page-break-before:auto;page-break-after:avoid;page-break-inside:auto}.pdf-os-footer .assinaturas{margin-top:30pt}</style>';
        $html = preg_replace('/<\/head>/i', $pdfCss . '</head>', $html, 1) ?? $html;
        pdf_create($html, 'os_' . (int) $this->uri->segment(3), true);
    }

    /**
     * Etiqueta 40x25mm da OS (Code128 "MT-OS-{id}").
     *
     * Geração server-side pelo mPDF, sem CDN ou acesso à rede, preservando o
     * payload usado pelos leitores existentes.
     */
    public function imprimirEtiqueta()
    {
        $id = $this->uri->segment(3);
        if (! is_numeric($id) || (int) $id <= 0) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar O.S.');
            redirect(base_url());
        }

        $os = $this->os_model->getById((int) $id);
        if (! $os) {
            $this->session->set_flashdata('error', 'Ordem de serviço não encontrada.');
            redirect('os');
        }

        $this->load->helper('etiquetas');
        etiquetaGerarPdf(etiquetaHtmlOs($os), 'etiqueta_os_' . (int) $os->idOs);
    }

    /**
     * Etiqueta 40x25mm do número de série do equipamento ligado à OS.
     * Exige vínculo inequívoco quando a OS tem mais de um equipamento.
     */
    public function imprimirEtiquetaSerial()
    {
        $id = $this->uri->segment(3);
        if (! is_numeric($id) || (int) $id <= 0) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar O.S.');
            redirect(base_url());
        }

        $os = $this->os_model->getById((int) $id);
        if (! $os) {
            $this->session->set_flashdata('error', 'Ordem de serviço não encontrada.');
            redirect('os');
        }

        $equipamento = $this->equipamentoDaOs((int) $os->idOs);
        $serial = $equipamento ? trim((string) $equipamento->num_serie) : '';
        if (! $equipamento || $serial === '') {
            $this->session->set_flashdata('error', 'Esta OS não possui equipamento vinculado com número de série salvo.');
            redirect('os/visualizar/' . (int) $os->idOs);
        }

        $this->load->helper('etiquetas');
        etiquetaGerarPdf(
            etiquetaHtmlSerial(
                $serial,
                (int) $os->idOs,
                'Equipamento #' . (int) $equipamento->idEquipamentos
            ),
            'etiqueta_serial_' . (int) $os->idOs . '_' . (int) $equipamento->idEquipamentos
        );
    }

    /** Equipamento vinculado à OS, sem escolher um vínculo ambíguo em silêncio. */
    private function equipamentoDaOs(int $osId): ?object
    {
        $vinculos = $this->equipamentos_model->listarPorOs($osId);
        if (! $vinculos) {
            return null;
        }

        $pedido = $this->input->get('equipamento');
        if ($pedido !== null) {
            if (! is_numeric($pedido)) {
                return null;
            }

            foreach ($vinculos as $vinculo) {
                if ((int) $vinculo->idEquipamentos === (int) $pedido) {
                    return $vinculo;
                }
            }

            return null;
        }

        return count($vinculos) === 1 ? $vinculos[0] : null;
    }

    public function imprimirTermica()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar O.S.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->load->model('mapos_model');
        $this->data['result'] = $this->os_model->getById($this->uri->segment(3));
        $this->data['produtos'] = $this->os_model->getProdutos($this->uri->segment(3));
        $this->data['servicos'] = $this->os_model->getServicos($this->uri->segment(3));
        $this->data['emitente'] = $this->mapos_model->getEmitente();
        $this->data['qrCode'] = $this->os_model->getQrCode(
            $this->uri->segment(3),
            $this->data['configuration']['pix_key'],
            $this->data['emitente']
        );
        $this->data['chaveFormatada'] = $this->formatarChave($this->data['configuration']['pix_key']);

        $this->load->view('os/imprimirOsTermica', $this->data);
    }

    public function enviar_email()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para enviar O.S. por e-mail.');
            redirect(base_url());
        }

        $this->load->model('mapos_model');
        $this->load->model('usuarios_model');
        $this->data['result'] = $this->os_model->getById($this->uri->segment(3));
        if (! isset($this->data['result']->email)) {
            $this->session->set_flashdata('error', 'O cliente não tem e-mail cadastrado.');
            redirect(site_url('os'));
        }

        $this->data['produtos'] = $this->os_model->getProdutos($this->uri->segment(3));
        $this->data['servicos'] = $this->os_model->getServicos($this->uri->segment(3));
        $this->data['emitente'] = $this->mapos_model->getEmitente();

        if (! isset($this->data['emitente']->email)) {
            $this->session->set_flashdata('error', 'Efetue o cadastro dos dados de emitente');
            redirect(site_url('os'));
        }

        $idOs = $this->uri->segment(3);

        $emitente = $this->data['emitente'];
        $tecnico = $this->usuarios_model->getById($this->data['result']->usuarios_id);

        // Verificar configuração de notificação
        $ValidarEmail = false;
        if ($this->data['configuration']['os_notification'] != 'nenhum') {
            $remetentes = [];
            switch ($this->data['configuration']['os_notification']) {
                case 'todos':
                    array_push($remetentes, $this->data['result']->email);
                    array_push($remetentes, $tecnico->email);
                    array_push($remetentes, $emitente->email);
                    $ValidarEmail = true;
                    break;
                case 'cliente':
                    array_push($remetentes, $this->data['result']->email);
                    $ValidarEmail = true;
                    break;
                case 'tecnico':
                    array_push($remetentes, $tecnico->email);
                    break;
                case 'emitente':
                    array_push($remetentes, $emitente->email);
                    break;
                default:
                    array_push($remetentes, $this->data['result']->email);
                    $ValidarEmail = true;
                    break;
            }

            if ($ValidarEmail) {
                if (empty($this->data['result']->email) || ! filter_var($this->data['result']->email, FILTER_VALIDATE_EMAIL)) {
                    $this->session->set_flashdata('error', 'Por favor preencha o email do cliente');
                    redirect(site_url('os/visualizar/') . $this->uri->segment(3));
                }
            }

            $enviouEmail = $this->enviarOsPorEmail($idOs, $remetentes, 'Ordem de Serviço');

            if ($enviouEmail) {
                $this->session->set_flashdata('success', 'O email está sendo processado e será enviado em breve.');
                log_info('Enviou e-mail para o cliente: ' . $this->data['result']->nomeCliente . '. E-mail: ' . $this->data['result']->email);
                redirect(site_url('os'));
            } else {
                $this->session->set_flashdata('error', 'Ocorreu um erro ao enviar e-mail.');
                redirect(site_url('os'));
            }
        }

        $this->session->set_flashdata('success', 'O sistema está com uma configuração ativada para não notificar. Entre em contato com o administrador.');
        redirect(site_url('os'));
    }

    private function devolucaoEstoque($id)
    {
        if ($produtos = $this->os_model->getProdutos($id)) {
            $this->load->model('produtos_model');
            if ($this->data['configuration']['control_estoque']) {
                foreach ($produtos as $p) {
                    $this->produtos_model->updateEstoque($p->produtos_id, $p->quantidade, '+');
                    log_info('ESTOQUE: Produto id ' . $p->produtos_id . ' voltou ao estoque. Quantidade: ' . $p->quantidade . '. Motivo: Cancelamento/Exclusão');
                }
            }
        }
    }

    private function debitarEstoque($id)
    {
        if ($produtos = $this->os_model->getProdutos($id)) {
            $this->load->model('produtos_model');
            if ($this->data['configuration']['control_estoque']) {
                foreach ($produtos as $p) {
                    $this->produtos_model->updateEstoque($p->produtos_id, $p->quantidade, '-');
                    log_info('ESTOQUE: Produto id ' . $p->produtos_id . ' baixa do estoque. Quantidade: ' . $p->quantidade . '. Motivo: Mudou status que já estava Cancelado para outro');
                }
            }
        }
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir O.S.');
            redirect(base_url());
        }

        $id = $this->input->post('id');
        $os = $this->os_model->getByIdCobrancas($id);
        if ($os == null) {
            $os = $this->os_model->getById($id);
            if ($os == null) {
                $this->session->set_flashdata('error', 'Erro ao tentar excluir OS.');
                redirect(base_url() . 'index.php/os/gerenciar/');
            }
        }

        if (isset($os->idCobranca) != null) {
            if ($os->status == 'canceled') {
                $this->os_model->delete('cobrancas', 'os_id', $id);
            } else {
                $this->session->set_flashdata('error', 'Existe uma cobrança associada a esta OS, deve cancelar e/ou excluir a cobrança primeiro!');
                redirect(site_url('os/gerenciar/'));
            }
        }

        $osStockRefund = $this->os_model->getById($id);
        $anexos = $this->os_model->getAnexos($id);
        $this->db->trans_begin();

        //Verifica para poder fazer a devolução do produto para o estoque caso OS seja excluida.
        if (strtolower($osStockRefund->status) != 'cancelado') {
            $this->devolucaoEstoque($id);
        }

        $this->os_model->delete('servicos_os', 'os_id', $id);
        $this->os_model->delete('produtos_os', 'os_id', $id);
        $this->os_model->delete('anexos', 'os_id', $id);
        $this->os_model->delete('anotacoes_os', 'os_id', $id);
        if ($this->os_model->historicoDisponivel()) {
            $this->os_model->delete('os_historico', 'os_id', $id);
        }
        // A OS pode ter um equipamento vinculado; a FK impede excluir os antes deste vínculo.
        // O cadastro mestre em equipamentos permanece preservado.
        $this->os_model->delete('equipamentos_os', 'os_id', $id);
        $this->os_model->delete('os', 'idOs', $id);
        if ((int) $os->faturado === 1) {
            $this->db->where('descricao', "Fatura de OS Nº: {$id}");
            $this->db->delete('lancamentos');
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            $this->session->set_flashdata('error', 'Não foi possível excluir a OS porque existem vínculos que não puderam ser removidos.');
            redirect(site_url('os/gerenciar/'));
        }

        $this->db->trans_commit();
        foreach (osArquivosDosAnexos($anexos, FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'anexos') as $arquivo) {
            @unlink($arquivo);
        }
        log_info('Removeu uma OS. ID: ' . $id);
        $this->session->set_flashdata('success', 'OS excluída com sucesso!');
        redirect(site_url('os/gerenciar/'));
    }

    public function autoCompleteProduto()
    {
        $q = strtolower((string) $this->input->get('term', true));
        $resultado = $this->os_model->autoCompleteProduto($q);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($resultado, JSON_UNESCAPED_UNICODE));
    }

    public function autoCompleteProdutoSaida()
    {
        $q = strtolower((string) $this->input->get('term', true));
        $resultado = $this->os_model->autoCompleteProdutoSaida($q);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($resultado, JSON_UNESCAPED_UNICODE));
    }

    public function autoCompleteCliente()
    {
        $this->output->set_content_type('application/json');
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            return $this->output->set_output(json_encode($this->os_model->autoCompleteCliente($q)));
        }

        return $this->output->set_output('[]');
    }

    public function autoCompleteUsuario()
    {
        $this->output->set_content_type('application/json');
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            return $this->output->set_output(json_encode($this->os_model->autoCompleteUsuario($q)));
        }

        return $this->output->set_output('[]');
    }

    public function autoCompleteTermoGarantia()
    {
        $this->output->set_content_type('application/json');
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            return $this->output->set_output(json_encode($this->os_model->autoCompleteTermoGarantia($q)));
        }

        return $this->output->set_output('[]');
    }

    public function historicoSerial()
    {
        $this->output->set_content_type('application/json');

        $serial = trim((string) $this->input->get_post('serial', true));
        $idOsAtual = $this->input->get_post('idOs', true);
        $idOsAtual = is_numeric($idOsAtual) ? (int) $idOsAtual : null;

        if ($serial === '') {
            return $this->output->set_output(json_encode([
                'found' => false,
                'serial' => '',
                'total' => 0,
                'historico' => [],
            ]));
        }

        $historico = $this->os_model->getHistoricoPorSerial($serial, $idOsAtual, 5);
        $payload = [];

        foreach ($historico as $row) {
            $payload[] = $this->formatarHistoricoSerialItem($row);
        }

        $garantiaCritica = ! empty($historico)
            ? $this->os_model->calcularCriticaGarantiaSerial($historico[0])
            : null;

        return $this->output->set_output(json_encode([
            'found' => ! empty($payload),
            'serial' => $serial,
            'total' => count($payload),
            'historico' => $payload,
            'garantiaCritica' => $garantiaCritica,
        ]));
    }

    public function autoCompleteServico()
    {
        $q = strtolower((string) $this->input->get('term', true));
        $resultado = $this->os_model->autoCompleteServico($q);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($resultado, JSON_UNESCAPED_UNICODE));
    }

    public function adicionarProduto()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->responderOsJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar O.S.']);
        }

        $idOs = (int) $this->input->post('idOsProduto');
        if ($idOs <= 0) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'OS inválida.']);
        }
        if (! $this->os_model->isEditable($idOs)) {
            return $this->responderOsJson(409, ['result' => false, 'message' => 'Esta OS não pode mais ser alterada.']);
        }

        $this->load->library('form_validation');
        $preco = trim((string) ($this->input->post('preco') ?? ''));
        if (strpos($preco, ',') !== false) {
            $preco = str_replace(',', '.', str_replace('.', '', $preco));
            $_POST['preco'] = $preco;
        }

        if ($this->form_validation->run('adicionar_produto_os') === false) {
            $erros = array_map('strip_tags', $this->form_validation->error_array());

            return $this->responderOsJson(422, ['result' => false, 'erros' => $erros, 'message' => 'Verifique os campos destacados.']);
        }

        $preco = $this->input->post('preco');
        $quantidadeRaw = trim((string) $this->input->post('quantidade'));
        if (! preg_match('/^[1-9][0-9]*$/', $quantidadeRaw)) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'Quantidade inválida.']);
        }
        $quantidade = (int) $quantidadeRaw;
        $precoNumero = valorDecimal($preco);
        if ($precoNumero === null || (float) $precoNumero < 0) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'Preço inválido.']);
        }
        $precoNumero = round((float) $precoNumero, 2);
        [$descontoItem, $errosDesconto] = osDescontoDoItem(
            round($precoNumero * $quantidade, 2),
            $this->input->post('tipoDescontoItem'),
            $this->input->post('descontoItem')
        );
        if ($errosDesconto !== []) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'Confira os campos destacados.', 'erros' => $errosDesconto]);
        }

        $produto = $this->input->post('idProduto');
        $this->load->model('produtos_model');
        $produtoData = $this->produtos_model->getById($produto);
        if ($produtoData === null) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'Produto não encontrado.']);
        }

        $custoUnitario = isset($produtoData->precoCompra) ? (float) $produtoData->precoCompra : 0.0;
        $data = [
            'quantidade' => $quantidade,
            'subTotal' => $descontoItem['subTotal'],
            'produtos_id' => $produto,
            'preco' => $precoNumero,
            'custo_unitario' => $custoUnitario,
            'custo_total' => round($custoUnitario * $quantidade, 2),
            'os_id' => $idOs,
        ];
        if ($descontoItem['desconto'] > 0) {
            $data['desconto'] = $descontoItem['desconto'];
            $data['tipo_desconto'] = $descontoItem['tipo_desconto'];
        }

        $os = $this->os_model->getById($idOs);
        if ($os === null) {
            return $this->responderOsJson(404, ['result' => false, 'message' => 'OS não encontrada.']);
        }

        $resultado = $this->os_model->adicionarProdutoAOs(
            $data,
            $idOs,
            (int) $produto,
            $quantidade,
            (bool) $this->data['configuration']['control_estoque'],
            $this->input->post('tipoDescontoItem'),
            $this->input->post('descontoItem')
        );

        if (! $resultado['ok']) {
            return $this->responderOsJson((int) $resultado['status'], [
                'result' => false,
                'message' => $resultado['message'],
                'erros' => $resultado['erros'] ?? [],
            ]);
        }

        log_info('Adicionou produto a uma OS. ID (OS): ' . $idOs);

        return $this->responderOsJson(200, ['result' => true, 'message' => 'Produto adicionado à OS.', 'html' => $this->trechosDaOs($idOs, $this->input->post('aba'))]);
    }

    public function descontoProduto()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->responderOsJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar O.S.']);
        }

        $this->descontoDoItem('produto');
    }

    public function descontoServico()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->responderOsJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar O.S.']);
        }

        $this->descontoDoItem('servico');
    }

    private function osParaDescontoDoItem(): ?object
    {
        $idOs = (int) $this->input->post('idOs');
        if ($idOs <= 0) {
            $this->responderOsJson(422, ['result' => false, 'message' => 'OS inválida.']);
            return null;
        }

        $os = $this->os_model->getById($idOs);
        if ($os === null) {
            $this->responderOsJson(404, ['result' => false, 'message' => 'OS não encontrada.']);
            return null;
        }
        if (! $this->os_model->isEditable($idOs)) {
            $this->responderOsJson(409, ['result' => false, 'message' => 'Esta OS não pode mais ser alterada.']);
            return null;
        }

        return $os;
    }

    private function descontoDoItem(string $tipo): void
    {
        $os = $this->osParaDescontoDoItem();
        if ($os === null) {
            return;
        }

        $valor = $this->input->post('descontoItem');
        if (! is_scalar($valor) || trim((string) $valor) === '') {
            $this->responderOsJson(422, [
                'result' => false,
                'message' => 'Confira os campos destacados.',
                'erros' => ['descontoItem' => 'Informe o desconto, ou 0 para tirar.'],
            ]);
            return;
        }

        $resultado = $this->os_model->atualizarDescontoDoItem(
            $tipo,
            (int) $os->idOs,
            (int) $this->input->post('idItem'),
            $this->input->post('tipoDescontoItem'),
            $valor
        );
        if (! $resultado['ok']) {
            $this->responderOsJson((int) $resultado['status'], [
                'result' => false,
                'message' => $resultado['message'],
                'erros' => $resultado['erros'] ?? [],
            ]);
            return;
        }

        $mensagem = (float) ($resultado['desconto']['desconto'] ?? 0) > 0
            ? 'Desconto do item aplicado.'
            : 'Desconto do item removido.';
        $this->responderOsJson(200, [
            'result' => true,
            'message' => $mensagem,
            'html' => $this->trechosDaOs((int) $os->idOs, $this->input->post('aba')),
            'totais' => $this->os_model->totais($this->os_model->getById((int) $os->idOs)),
        ]);
    }

    public function excluirProduto()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(403)
                ->set_output(json_encode(['result' => false, 'message' => 'Você não tem permissão para editar O.S.']));
        }

        $idOs = (int) $this->input->post('idOs');
        $idItem = (int) $this->input->post('idItem');
        if ($idOs <= 0 || $idItem <= 0) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(422)
                ->set_output(json_encode(['result' => false, 'message' => 'Produto da OS inválido.']));
        }

        $os = $this->os_model->getById($idOs);
        if ($os === null) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(404)
                ->set_output(json_encode(['result' => false, 'message' => 'OS não encontrada.']));
        }

        if (! $this->os_model->isEditable($idOs)) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(409)
                ->set_output(json_encode(['result' => false, 'message' => 'Esta OS não pode mais ser alterada.']));
        }

        $resultado = $this->os_model->excluirProdutoDaOs(
            $idOs,
            $idItem,
            (bool) $this->data['configuration']['control_estoque']
        );

        if (! $resultado['ok']) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header((int) $resultado['status'])
                ->set_output(json_encode(['result' => false, 'message' => $resultado['message']]));
        }

        log_info('Removeu produto de uma OS. ID (OS): ' . $idOs . ', vínculo: ' . $idItem);

        return $this->responderOsJson(200, ['result' => true, 'message' => $resultado['message'], 'html' => $this->trechosDaOs($idOs, $this->input->post('aba'))]);
    }

    public function adicionarServico()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->responderOsJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar O.S.']);
        }

        $idOsServico = (int) $this->input->post('idOsServico');
        if ($idOsServico <= 0) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'OS inválida.']);
        }
        if (! $this->os_model->isEditable($idOsServico)) {
            return $this->responderOsJson(409, ['result' => false, 'message' => 'Esta OS não pode mais ser alterada.']);
        }

        $this->load->library('form_validation');
        $preco = trim((string) ($this->input->post('preco') ?? ''));
        if (strpos($preco, ',') !== false) {
            $preco = str_replace(',', '.', str_replace('.', '', $preco));
            $_POST['preco'] = $preco;
        }

        if ($this->form_validation->run('adicionar_servico_os') === false) {
            $erros = array_map('strip_tags', $this->form_validation->error_array());

            return $this->responderOsJson(422, ['result' => false, 'erros' => $erros, 'message' => 'Verifique os campos destacados.']);
        }

        $preco = $this->input->post('preco');
        $quantidade = osNumero($this->input->post('quantidade'));
        $precoNumero = valorDecimal($preco);
        if ($quantidade === null || $quantidade <= 0 || $precoNumero === null || (float) $precoNumero < 0) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'Quantidade ou preço inválido.']);
        }
        $quantidade = (float) $quantidade;
        $precoNumero = round((float) $precoNumero, 2);
        [$descontoItem, $errosDesconto] = osDescontoDoItem(
            round($precoNumero * $quantidade, 2),
            $this->input->post('tipoDescontoItem'),
            $this->input->post('descontoItem')
        );
        if ($errosDesconto !== []) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'Confira os campos destacados.', 'erros' => $errosDesconto]);
        }

        $this->load->model('servicos_model');
        $servicoId = $this->input->post('idServico');
        $servicoData = $this->servicos_model->getById($servicoId);
        if ($servicoData === null) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'Serviço não encontrado.']);
        }

        $os = $this->os_model->getById($idOsServico);
        if ($os === null) {
            return $this->responderOsJson(404, ['result' => false, 'message' => 'OS não encontrada.']);
        }

        $data = [
            'servicos_id' => $servicoId,
            'quantidade' => $quantidade,
            'preco' => $precoNumero,
            'os_id' => $idOsServico,
            'subTotal' => $descontoItem['subTotal'],
        ];
        if ($descontoItem['desconto'] > 0) {
            $data['desconto'] = $descontoItem['desconto'];
            $data['tipo_desconto'] = $descontoItem['tipo_desconto'];
        }

        $resultado = $this->os_model->adicionarServicoAOs(
            $data,
            $idOsServico,
            (int) $servicoId,
            $quantidade,
            $this->input->post('tipoDescontoItem'),
            $this->input->post('descontoItem')
        );

        if (! $resultado['ok']) {
            return $this->responderOsJson((int) $resultado['status'], [
                'result' => false,
                'message' => $resultado['message'],
                'erros' => $resultado['erros'] ?? [],
            ]);
        }

        log_info('Adicionou serviço a uma OS. ID (OS): ' . $idOsServico);

        return $this->responderOsJson(200, ['result' => true, 'message' => 'Serviço adicionado à OS.', 'html' => $this->trechosDaOs($idOsServico, $this->input->post('aba'))]);
    }

    public function excluirServico()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(403)
                ->set_output(json_encode(['result' => false, 'message' => 'Você não tem permissão para editar O.S.']));
        }

        $idOs = (int) $this->input->post('idOs');
        $idItem = (int) $this->input->post('idItem');
        if ($idOs <= 0 || $idItem <= 0) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(422)
                ->set_output(json_encode(['result' => false, 'message' => 'Serviço da OS inválido.']));
        }

        $os = $this->os_model->getById($idOs);
        if ($os === null) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(404)
                ->set_output(json_encode(['result' => false, 'message' => 'OS não encontrada.']));
        }

        if (! $this->os_model->isEditable($idOs)) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(409)
                ->set_output(json_encode(['result' => false, 'message' => 'Esta OS não pode mais ser alterada.']));
        }

        $resultado = $this->os_model->excluirServicoDaOs($idOs, $idItem);
        if (! $resultado['ok']) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header((int) $resultado['status'])
                ->set_output(json_encode(['result' => false, 'message' => $resultado['message']]));
        }

        log_info('Removeu serviço de uma OS. ID (OS): ' . $idOs . ', vínculo: ' . $idItem);

        return $this->responderOsJson(200, ['result' => true, 'message' => $resultado['message'], 'html' => $this->trechosDaOs($idOs, $this->input->post('aba'))]);
    }

    public function anexar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->responderOsJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar O.S.']);
        }

        $this->load->library('upload');
        $this->load->library('image_lib');

        // idOsServico compõe o caminho do diretório: sem a normalização para
        // inteiro, "../.." escaparia de assets/anexos.
        $idOsServico = (int) $this->input->post('idOsServico');

        if ($idOsServico <= 0 || ! $this->os_model->getById($idOsServico)) {
            $this->responderOsJson(404, ['result' => false, 'message' => 'OS não encontrada.']);
            exit();
        }

        $directory = FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'anexos' . DIRECTORY_SEPARATOR . date('m-Y') . DIRECTORY_SEPARATOR . 'OS-' . $idOsServico;

        // If it exist, check if it's a directory
        if (! is_dir($directory . DIRECTORY_SEPARATOR . 'thumbs')) {
            // make directory for images and thumbs
            try {
                mkdir($directory . DIRECTORY_SEPARATOR . 'thumbs', 0755, true);
            } catch (Exception $e) {
                $this->responderOsJson(500, ['result' => false, 'message' => 'Não foi possível preparar o diretório de anexos.']);
                exit();
            }
        }

        $upload_conf = [
            'upload_path' => $directory,
            'allowed_types' => 'jpg|png|gif|jpeg|JPG|PNG|GIF|JPEG|pdf|PDF|cdr|CDR|docx|DOCX|txt', // formatos permitidos para anexos de os
            'max_size' => 0,
        ];

        $this->upload->initialize($upload_conf);

        foreach ($_FILES['userfile'] as $key => $val) {
            $i = 1;
            foreach ($val as $v) {
                $field_name = 'file_' . $i;
                $_FILES[$field_name][$key] = $v;
                $i++;
            }
        }
        unset($_FILES['userfile']);

        $error = [];
        $success = [];

        foreach ($_FILES as $field_name => $file) {
            if (! $this->upload->do_upload($field_name)) {
                $error['upload'][] = $this->upload->display_errors();
            } else {
                $upload_data = $this->upload->data();

                // Gera um nome de arquivo aleatório mantendo a extensão original
                $new_file_name = uniqid() . '.' . pathinfo($upload_data['file_name'], PATHINFO_EXTENSION);
                $new_file_path = $upload_data['file_path'] . $new_file_name;

                rename($upload_data['full_path'], $new_file_path);

                if ($upload_data['is_image'] == 1) {
                    $resize_conf = [
                        'source_image' => $new_file_path,
                        'new_image' => $upload_data['file_path'] . 'thumbs' . DIRECTORY_SEPARATOR . 'thumb_' . $new_file_name,
                        'width' => 200,
                        'height' => 125,
                    ];

                    $this->image_lib->initialize($resize_conf);

                    if (! $this->image_lib->resize()) {
                        $error['resize'][] = $this->image_lib->display_errors();
                    } else {
                        $success[] = $upload_data;
                        $this->load->model('Os_model');
                        $result = $this->Os_model->anexar($idOsServico, $new_file_name, base_url('assets' . DIRECTORY_SEPARATOR . 'anexos' . DIRECTORY_SEPARATOR . date('m-Y') . DIRECTORY_SEPARATOR . 'OS-' . $idOsServico), 'thumb_' . $new_file_name, $directory);
                        if (! $result) {
                            $error['db'][] = 'Erro ao inserir no banco de dados.';
                        }
                    }
                } else {
                    $success[] = $upload_data;

                    $this->load->model('Os_model');

                    $result = $this->Os_model->anexar($idOsServico, $new_file_name, base_url('assets' . DIRECTORY_SEPARATOR . 'anexos' . DIRECTORY_SEPARATOR . date('m-Y') . DIRECTORY_SEPARATOR . 'OS-' . $idOsServico), '', $directory);
                    if (! $result) {
                        $error['db'][] = 'Erro ao inserir no banco de dados.';
                    }
                }
            }
        }

        if (count($error) > 0) {
            $this->responderOsJson(422, ['result' => false, 'message' => 'Ocorreu um erro ao processar os arquivos.', 'erros' => $error]);
        } else {
            log_info('Adicionou anexo(s) a uma OS. ID (OS): ' . $idOsServico);
            $this->responderOsJson(200, ['result' => true, 'message' => 'Arquivo(s) anexado(s) com sucesso.', 'html' => $this->trechosDaOs((int) $idOsServico, $this->input->post('aba'))]);
        }
    }

    public function excluirAnexo($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->responderOsJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar O.S.']);
        }

        $anexoId = is_numeric($id) ? (int) $id : (int) $this->input->post('idItem');
        $idOs = (int) $this->input->post('idOs');

        if ($anexoId <= 0) {
            $this->responderOsJson(422, ['result' => false, 'message' => 'Anexo inválido.']);
            return;
        }

        $file = $this->db->where('idAnexos', $anexoId)->get('anexos', 1)->row();
        if ($file === null) {
            $this->responderOsJson(404, ['result' => false, 'message' => 'Anexo não encontrado.']);
            return;
        }

        if (is_file($file->path . DIRECTORY_SEPARATOR . $file->anexo)) {
            @unlink($file->path . DIRECTORY_SEPARATOR . $file->anexo);
        } else {
            log_message('warning', 'excluirAnexo: arquivo ausente no disco para o anexo ' . $anexoId);
        }

        if ($file->thumb != null && is_file($file->path . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . $file->thumb)) {
            @unlink($file->path . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . $file->thumb);
        }

        if ($this->os_model->delete('anexos', 'idAnexos', $anexoId) == true) {
            log_info('Removeu anexo de uma OS. ID (OS): ' . $idOs);
            $this->responderOsJson(200, ['result' => true, 'message' => 'Anexo excluído com sucesso.', 'html' => $this->trechosDaOs($idOs, $this->input->post('aba'))]);
        } else {
            $this->responderOsJson(500, ['result' => false, 'message' => 'Erro ao tentar excluir anexo.']);
        }
    }

    public function downloadanexo($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar O.S.');
            redirect(base_url());
        }

        if ($id != null && is_numeric($id)) {
            $this->db->where('idAnexos', $id);
            $file = $this->db->get('anexos', 1)->row();

            $this->load->library('zip');
            $path = $file->path;
            $this->zip->read_file($path . '/' . $file->anexo);
            $this->zip->download('file' . date('d-m-Y-H.i.s') . '.zip');
        }
    }

    public function adicionarDesconto()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->responderOsJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar O.S.']);
        }

        $idOs = filter_var($this->input->post('idOs'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idOs === false) {
            return $this->responderOsJson(422, ['result' => false, 'erros' => ['idOs' => 'Informe uma OS válida.'], 'message' => 'Informe uma OS válida.']);
        }

        $os = $this->os_model->getById($idOs);
        if ($os === null) {
            return $this->responderOsJson(404, ['result' => false, 'message' => 'OS não encontrada.']);
        }

        if (! $this->os_model->isEditable($idOs)) {
            return $this->responderOsJson(409, ['result' => false, 'message' => 'Esta OS não pode mais ser alterada.', 'erros' => ['_geral' => 'Esta OS não pode mais ser alterada.']]);
        }

        // O total é sempre calculado no servidor a partir dos itens da OS:
        // o campo "resultado" enviado pelo navegador é ignorado, para que um
        // desconto não possa ser maior que o bruto nem calculado errado.
        $totais = $this->os_model->totais($os);
        [$data, $erros] = osCalcularDesconto((float) $totais['bruto'], $this->input->post('tipoDesconto'), $this->input->post('desconto'));
        if ($erros !== []) {
            return $this->responderOsJson(422, ['result' => false, 'erros' => $erros, 'message' => (string) reset($erros)]);
        }

        if ($this->os_model->edit('os', $data, 'idOs', $idOs) == true) {
            log_info('Adicionou um desconto na OS. ID: ' . $idOs);

            return $this->responderOsJson(200, ['result' => true, 'message' => 'Desconto aplicado.', 'html' => $this->trechosDaOs($idOs, $this->input->post('aba'))]);
        }

        log_info('Ocorreu um erro ao tentar adicionar desconto a OS: ' . $idOs);

        return $this->responderOsJson(500, ['result' => false, 'message' => 'Não foi possível salvar o desconto.']);
    }

    public function faturar()
    {
        $responder = function (array $corpo, int $status = 200) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header($status)
                ->set_output(json_encode($corpo, JSON_UNESCAPED_UNICODE));
        };

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $responder([
                'result' => false,
                'message' => 'Você não tem permissão para faturar O.S.',
                'erros' => ['_geral' => 'Você não tem permissão para faturar O.S.'],
            ], 403);
        }

        $post = (array) $this->input->post();
        $osId = filter_var($post['os_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($osId === false) {
            return $responder([
                'result' => false,
                'message' => 'Informe uma OS válida.',
                'erros' => ['os_id' => 'Informe uma OS válida.'],
            ], 422);
        }

        if (! $this->db->trans_begin()) {
            return $responder([
                'result' => false,
                'message' => 'Não foi possível iniciar o faturamento.',
                'erros' => ['_geral' => 'Não foi possível iniciar o faturamento.'],
            ], 500);
        }

        try {
            // Serializa faturamentos concorrentes da mesma OS antes de inserir a receita.
            $osBloqueada = $this->db->query('SELECT * FROM os WHERE idOs = ? FOR UPDATE', [$osId])->row();
            if (! $osBloqueada) {
                $this->db->trans_rollback();
                return $responder([
                    'result' => false,
                    'message' => 'OS não encontrada.',
                    'erros' => ['_geral' => 'OS não encontrada.'],
                ], 404);
            }

            if ((int) $osBloqueada->faturado === 1 || ! empty($osBloqueada->lancamento) || $osBloqueada->status === 'Faturado') {
                $this->db->trans_rollback();
                return $responder([
                    'result' => false,
                    'message' => 'Esta OS já está faturada.',
                    'erros' => ['_geral' => 'Esta OS já está faturada.'],
                ], 409);
            }

            $os = $this->os_model->getById($osId);
            if (! $os) {
                $this->db->trans_rollback();
                return $responder([
                    'result' => false,
                    'message' => 'OS não encontrada.',
                    'erros' => ['_geral' => 'OS não encontrada.'],
                ], 404);
            }

            $totais = $this->os_model->totais($os);
            [$lancamento, $erros] = osFaturaDoFormulario(
                $post,
                $os,
                $totais,
                (int) $this->session->userdata('id_admin')
            );
            if ($erros !== []) {
                $this->db->trans_rollback();
                return $responder([
                    'result' => false,
                    'message' => 'Revise os dados do faturamento.',
                    'erros' => $erros,
                ], 422);
            }

            if (! $this->db->insert('lancamentos', $lancamento)) {
                throw new RuntimeException('Falha ao inserir o lançamento da OS.');
            }
            $idLancamento = (int) $this->db->insert_id();
            if ($idLancamento < 1) {
                throw new RuntimeException('O lançamento da OS não recebeu identificador.');
            }

            $atualizacao = [
                'faturado' => 1,
                'lancamento' => $idLancamento,
                'valorTotal' => $totais['bruto'],
                'desconto' => $totais['desconto'],
                'valor_desconto' => $totais['total'],
                'status' => 'Faturado',
            ];
            if (! $this->db->where('idOs', $osId)->update('os', $atualizacao)) {
                throw new RuntimeException('Falha ao atualizar a OS após inserir o lançamento.');
            }

            $this->os_model->registrarStatus(
                (int) $osId,
                (string) ($osBloqueada->status ?? ''),
                'Faturado',
                (int) $this->session->userdata('id_admin')
            );
            if (! $this->db->trans_status()) {
                throw new RuntimeException('Falha ao registrar a transição de status da OS.');
            }

            $this->db->trans_commit();
            $this->session->set_flashdata('success', 'OS faturada com sucesso!');

            return $responder([
                'result' => true,
                'message' => 'OS faturada com sucesso!',
                'redirecionar' => site_url('os/visualizar/' . $osId),
            ]);
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Falha transacional ao faturar OS ' . (int) $osId . ': ' . $e->getMessage());

            return $responder([
                'result' => false,
                'message' => 'Não foi possível concluir o faturamento. Nenhuma alteração foi mantida.',
                'erros' => ['_geral' => 'Não foi possível concluir o faturamento. Nenhuma alteração foi mantida.'],
            ], 500);
        }
    }

    private function obterListaStatusEmailAutomatico()
    {
        $fallback = ['Orçamento', 'Finalizado', 'Faturado'];
        $config = $this->data['configuration']['os_email_statuses']
            ?? $this->data['configuration']['os_status_email_notify_list']
            ?? null;
        $lista = is_array($config) ? $config : json_decode((string) $config, true);

        if (!is_array($lista)) {
            return $fallback;
        }

        $lista = array_values(array_filter(array_map('trim', $lista), static function ($status) {
            return $status !== '';
        }));

        return empty($lista) ? $fallback : $lista;
    }

    private function statusPermiteEmailAutomatico($status)
    {
        if (! is_string($status) || trim($status) === '') {
            return false;
        }

        return in_array(trim($status), $this->obterListaStatusEmailAutomatico(), true);
    }

    private function deveEnviarEmailAutomaticoPorStatus($statusAnterior, $statusNovo)
    {
        if (! $this->statusPermiteEmailAutomatico($statusNovo)) {
            return false;
        }

        if ($statusAnterior === null) {
            return true;
        }

        return trim((string) $statusAnterior) !== trim((string) $statusNovo);
    }

    private function enviarOsPorEmail($idOs, $remetentes, $assunto)
    {
        $dados = [];

        $this->load->model('mapos_model');
        $dados['result'] = $this->os_model->getById($idOs);
        if (! isset($dados['result']->email)) {
            return false;
        }

        $dados['produtos'] = $this->os_model->getProdutos($idOs);
        $dados['servicos'] = $this->os_model->getServicos($idOs);
        $dados['emitente'] = $this->mapos_model->getEmitente();
        $emitente = $dados['emitente'];
        if (! isset($emitente->email)) {
            return false;
        }

        $html = $this->load->view('os/emails/os', $dados, true);

        $this->load->model('email_model');

        $remetentes = array_unique($remetentes);
        foreach ($remetentes as $remetente) {
            if ($remetente) {
                $headers = ['From' => $emitente->email, 'Subject' => $assunto, 'Return-Path' => ''];
                $email = [
                    'to' => $remetente,
                    'message' => $html,
                    'status' => 'pending',
                    'date' => date('Y-m-d H:i:s'),
                    'headers' => json_encode($headers),
                ];
                $this->email_model->add('email_queue', $email);
            } else {
                log_info('Email não adicionado a Lista de envio de e-mails. Verifique se o remetente esta cadastrado. OS ID: ' . $idOs);
            }
        }

        return true;
    }

    public function adicionarAnotacao()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->responderOsJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar O.S.']);
        }

        $this->load->library('form_validation');
        // O formulário da tela posta "idOs"; a regra histórica da OS chama o
        // campo de "os_id". Normaliza antes de validar para o contrato POST
        // único funcionar nos dois formatos.
        $_POST['os_id'] = $_POST['os_id'] ?? $this->input->post('idOs');

        if ($this->form_validation->run('anotacoes_os') === false) {
            $erros = array_map('strip_tags', $this->form_validation->error_array());

            return $this->responderOsJson(422, ['result' => false, 'erros' => $erros, 'message' => 'Verifique os campos destacados.']);
        }

        $data = [
            'anotacao' => '[' . $this->session->userdata('nome_admin') . '] ' . $this->input->post('anotacao'),
            'data_hora' => date('Y-m-d H:i:s'),
            'os_id' => $this->input->post('os_id'),
        ];

        if ($this->os_model->add('anotacoes_os', $data) == true) {
            log_info('Adicionou anotação a uma OS. ID (OS): ' . $this->input->post('os_id'));

            return $this->responderOsJson(200, ['result' => true, 'message' => 'Anotação adicionada.', 'html' => $this->trechosDaOs((int) $this->input->post('os_id'), $this->input->post('aba'))]);
        }

        return $this->responderOsJson(500, ['result' => false, 'message' => 'Não foi possível salvar a anotação.']);
    }

    public function excluirAnotacao()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eOs')) {
            return $this->responderOsJson(403, ['result' => false, 'message' => 'Você não tem permissão para editar O.S.']);
        }

        // O formulário da tela posta "idItem"; o campo histórico é "idAnotacao".
        $id = (int) ($this->input->post('idItem') ?: $this->input->post('idAnotacao'));
        $idOs = (int) $this->input->post('idOs');

        if ($id <= 0) {
            return $this->responderOsJson(422, ['result' => false, 'message' => 'Anotação inválida.']);
        }

        if ($this->os_model->delete('anotacoes_os', 'idAnotacoes', $id) == true) {
            log_info('Removeu anotação de uma OS. ID (OS): ' . $idOs);

            return $this->responderOsJson(200, ['result' => true, 'message' => 'Anotação excluída.', 'html' => $this->trechosDaOs($idOs, $this->input->post('aba'))]);
        }

        return $this->responderOsJson(500, ['result' => false, 'message' => 'Não foi possível excluir a anotação.']);
    }
}
