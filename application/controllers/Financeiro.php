<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Financeiro extends MY_Controller
{
    /** Filtros da listagem, na query string (listagemFiltros()). */
    public const FILTROS = [
        'pesquisa' => 'texto',
        'tipo' => ['receita', 'despesa'],
        'status' => ['pendente', 'pago', 'vencido'],
        'periodo' => ['dia', 'semana', 'mes_anterior', 'mes', 'mes_posterior', 'ano', 'personalizado'],
        'de' => 'texto',
        'ate' => 'texto',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['form', 'financeiro']);
        $this->load->model('financeiro_model');
        $this->data['menuLancamentos'] = 'financeiro';
    }

    public function index()
    {
        $this->lancamentos();
    }

    public function custosFixos()
    {
        if (! $this->permite('vLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para acessar custos fixos.');
            return redirect('');
        }
        $mesAtual = dataIsoParaYmd($this->input->get('de')) ?: date('Y-m-01');
        $mesFim = dataIsoParaYmd($this->input->get('ate')) ?: date('Y-m-t');
        if ($mesAtual > $mesFim) {
            [$mesAtual, $mesFim] = [$mesFim, $mesAtual];
        }
        $competencias = $this->financeiro_model->getCompetenciasDashboard($mesAtual, $mesFim);
        $totalPagoCentavos = 0;
        $totalAPagarCentavos = 0;
        foreach ($competencias as $competencia) {
            if ((int) $competencia->pago === 1) {
                $totalPagoCentavos += $this->valorCustoFixoEmCentavos($competencia->valor);
            } else {
                $totalAPagarCentavos += $this->valorCustoFixoEmCentavos($competencia->valor);
            }
        }
        $totalGeralCentavos = $totalPagoCentavos + $totalAPagarCentavos;
        $categoria = is_scalar($this->input->get('categoria')) ? trim((string) $this->input->get('categoria')) : '';
        $status = in_array((string) $this->input->get('status'), ['', '0', '1'], true) ? (string) $this->input->get('status') : '';
        $this->data += [
            'competencias' => $competencias,
            'mesAtual' => date('m/Y'),
            'periodoDe' => $mesAtual,
            'periodoAte' => $mesFim,
            'totalPago' => $totalPagoCentavos / 100,
            'totalAPagar' => $totalAPagarCentavos / 100,
            'totalGeral' => $totalGeralCentavos / 100,
            'results' => $this->financeiro_model->getCustosFixos($categoria, $status),
            'filtrosCustos' => ['categoria' => $categoria, 'status' => $status],
            'podeCustos' => [
                'adicionar' => $this->permite('aLancamento'),
                'editar' => $this->permite('eLancamento'),
                'status' => $this->permite('dLancamento'),
                'excluir' => $this->permite('dLancamento'),
            ],
            'view' => 'financeiro/custosFixos',
        ];

        return $this->layout();
    }

    public function adicionarCustoFixo()
    {
        if (! $this->permite('aLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para cadastrar custos fixos.');
            return redirect('');
        }
        $erros = [];
        if ($this->input->method() === 'post') {
            [$dados, $erros] = $this->dadosCustoFixoDoPost();
            if ($erros === [] && $this->financeiro_model->adicionarCustoFixo($dados)) {
                $id = (int) $this->financeiro_model->getInsertId();
                if ($id > 0 && (int) $dados['ativo'] === 1) {
                    $this->financeiro_model->gerarCompetencias($id);
                }
                $this->session->set_flashdata('success', 'Custo fixo cadastrado.');
                return redirect('financeiro/custosFixos');
            }
        }
        $this->data += ['result' => null, 'action' => 'financeiro/adicionarCustoFixo', 'button_text' => 'Cadastrar', 'errosCustos' => $erros, 'view' => 'financeiro/custoFixoForm'];

        return $this->layout();
    }

    public function editarCustoFixo()
    {
        $id = (int) $this->uri->segment(3);
        $custo = $id > 0 ? $this->financeiro_model->getCustoFixoById($id) : null;
        if (! $custo) {
            $this->session->set_flashdata('error', 'Custo fixo não encontrado.');
            return redirect('financeiro/custosFixos');
        }
        if (! $this->permite('eLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar custos fixos.');
            return redirect('');
        }
        $erros = [];
        if ($this->input->method() === 'post') {
            [$dados, $erros] = $this->dadosCustoFixoDoPost($id);
            if ($erros === [] && $this->financeiro_model->atualizarCustoFixo($id, $dados)) {
                if ((int) $dados['ativo'] === 1) {
                    $this->financeiro_model->gerarCompetencias($id);
                }
                $this->financeiro_model->recalcularCompetenciasNaoPagas($id);
                $this->session->set_flashdata('success', 'Custo fixo atualizado.');
                return redirect('financeiro/editarCustoFixo/' . $id);
            }
        }
        $this->data += ['result' => $custo, 'action' => 'financeiro/editarCustoFixo/' . $id, 'button_text' => 'Salvar alterações', 'errosCustos' => $erros, 'view' => 'financeiro/custoFixoForm'];

        return $this->layout();
    }

    public function alternarStatusCustoFixo()
    {
        if (! $this->permite('dLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para alterar custos fixos.');
            return redirect('');
        }
        if ($this->input->method() !== 'post') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['result' => false, 'message' => 'Método não permitido.']));
        }
        $id = (int) ($this->input->post('idCustoFixo') ?: $this->uri->segment(3));
        $custo = $id > 0 ? $this->financeiro_model->getCustoFixoById($id) : null;
        if ($custo) {
            $novoAtivo = (int) $custo->ativo === 1 ? 0 : 1;
            $atualizado = $this->financeiro_model->atualizarCustoFixo($id, ['ativo' => $novoAtivo, 'updated_at' => date('Y-m-d H:i:s'), 'usuarios_id' => $this->usuarioLogado()]);
            if ($atualizado && $novoAtivo === 1) {
                // Ativar contrato também materializa a janela de competências LIVE,
                // sem tocar em linhas existentes nem gerar duplicidades.
                $this->financeiro_model->gerarCompetencias($id);
            }
            if ($atualizado) {
                $this->session->set_flashdata('success', 'Status do custo fixo atualizado.');
            } else {
                $this->session->set_flashdata('error', 'Não foi possível atualizar o status do custo fixo.');
            }
        }

        return redirect('financeiro/custosFixos');
    }

    public function excluirCustoFixo()
    {
        if (! $this->permite('dLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir custos fixos.');
            return redirect('');
        }
        if ($this->input->method() !== 'post') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['result' => false, 'message' => 'Método não permitido.']));
        }

        $id = $this->input->post('id');
        if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1) {
            $this->session->set_flashdata('error', 'Custo fixo não encontrado.');
            return redirect('financeiro/custosFixos');
        }
        $id = (int) $id;
        if (! $this->financeiro_model->getCustoFixoById($id)) {
            $this->session->set_flashdata('error', 'Custo fixo não encontrado.');
            return redirect('financeiro/custosFixos');
        }

        if (! $this->financeiro_model->excluirCustoFixo($id)) {
            $this->session->set_flashdata('error', 'Não foi possível excluir o custo fixo.');
            return redirect('financeiro/custosFixos');
        }

        log_info('Removeu um custo fixo. ID: ' . $id);
        $this->session->set_flashdata('success', 'Custo fixo excluído com sucesso.');

        return redirect('financeiro/custosFixos');
    }

    public function competenciasCustoFixo()
    {
        if (! $this->permite('vLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar competências.');
            return redirect('');
        }

        $id = (int) $this->uri->segment(3);
        $custo = $id > 0 ? $this->financeiro_model->getCustoFixoById($id) : null;
        if (! $custo) {
            $this->session->set_flashdata('error', 'Custo fixo não encontrado.');
            return redirect('financeiro/custosFixos');
        }
        $erros = [];
        $competenciaForm = [
            'competencia' => '',
            'valor_competencia' => '',
            'data_vencimento_competencia' => '',
            'observacoes_competencia' => '',
        ];
        $abrirModalEdicao = false;
        if ($this->input->method() === 'post' && $this->input->post('salvar_competencia')) {
            if (! $this->permite('eLancamento')) {
                $this->session->set_flashdata('error', 'Você não tem permissão para editar competências.');
                return redirect('');
            }
            $competenciaForm = [
                'competencia' => trim((string) $this->input->post('competencia')),
                'valor_competencia' => trim((string) $this->input->post('valor_competencia')),
                'data_vencimento_competencia' => trim((string) $this->input->post('data_vencimento_competencia')),
                'observacoes_competencia' => trim((string) $this->input->post('observacoes_competencia')),
            ];
            $abrirModalEdicao = (string) $this->input->post('origem_competencia') === 'modal';
            $competencia = $competenciaForm['competencia'];
            $valor = $competenciaForm['valor_competencia'];
            $vencimento = $competenciaForm['data_vencimento_competencia'];
            if ($competencia === '' || valorDecimal((string) $valor) === null) {
                $erros['_geral'] = 'Informe uma competência e um valor válido.';
            } elseif (! $this->financeiro_model->salvarCompetencia($id, $competencia, $valor, $vencimento, $competenciaForm['observacoes_competencia'])) {
                $erros['_geral'] = 'Não foi possível salvar a competência.';
            } else {
                $this->session->set_flashdata('success', 'Competência salva.');
                return redirect('financeiro/competenciasCustoFixo/' . $id);
            }
        }
        $this->data += ['competencias' => $this->financeiro_model->getCompetenciasCustoFixo($id), 'custo' => $custo, 'idCustoFixo' => $id, 'podeEditar' => $this->permite('eLancamento'), 'errosCustos' => $erros, 'competenciaForm' => $competenciaForm, 'abrirModalEdicao' => $abrirModalEdicao, 'view' => 'financeiro/competenciasCustoFixo'];

        return $this->layout();
    }

    public function togglePagamentoCompetencia()
    {
        if (! $this->permite('eLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para alterar pagamento de competências.');
            return redirect('');
        }
        if ($this->input->method() !== 'post') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['result' => false, 'message' => 'Método não permitido.']));
        }
        $idCompetencia = (int) $this->input->post('idCompetencia');
        $action = (string) $this->input->post('action');
        $competencia = $idCompetencia > 0 ? $this->financeiro_model->getCompetenciaById($idCompetencia) : null;
        if ($competencia && $idCompetencia > 0 && in_array($action, ['pagar', 'despagar'], true)) {
            if ($action === 'pagar') {
                $this->financeiro_model->marcarCompetenciaPaga($idCompetencia, (string) $this->input->post('data_pagamento'), (string) $this->input->post('observacoes_pagamento'));
            } else {
                $this->financeiro_model->desmarcarCompetenciaPaga($idCompetencia);
            }
        }
        $idCusto = $competencia ? (int) $competencia->custo_fixo_id : (int) $this->input->post('custo_fixo_id');

        return redirect('financeiro/competenciasCustoFixo/' . $idCusto);
    }

    private function valorCustoFixoEmCentavos($valor): int
    {
        $texto = trim((string) $valor);
        $texto = str_replace(',', '.', $texto);
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $texto, $partes)) {
            return 0;
        }
        $fracao = str_pad($partes[2] ?? '', 2, '0');

        return ((int) $partes[1] * 100) + (int) $fracao;
    }

    private function dadosCustoFixoDoPost(?int $id = null): array
    {
        $texto = fn (string $nome): string => is_scalar($this->input->post($nome)) ? trim((string) $this->input->post($nome)) : '';
        $valor = valorDecimal($texto('valor'));
        $periodicidade = $texto('periodicidade');
        $dia = (int) $texto('dia_vencimento');
        $regra = $texto('regra_vencimento');
        $erros = [];
        if ($texto('titulo') === '') $erros['titulo'] = 'Informe o título.';
        if ($texto('categoria') === '') $erros['categoria'] = 'Informe a categoria.';
        if ($valor === null || (float) $valor < 0) $erros['valor'] = 'Informe um valor válido.';
        if (! in_array($periodicidade, ['mensal', 'unico'], true)) $erros['periodicidade'] = 'Periodicidade inválida.';
        if ($dia < 1 || $dia > 31) $erros['dia_vencimento'] = 'Informe um dia entre 1 e 31.';
        if (! in_array($regra, ['mes_vencido', 'mes_corrente'], true)) $erros['regra_vencimento'] = 'Regra de vencimento inválida.';
        if (! in_array($texto('ativo'), ['0', '1'], true)) $erros['ativo'] = 'Situação inválida.';
        $inicio = $texto('data_inicio') === '' ? null : dataIsoParaYmd($texto('data_inicio'));
        $fim = $texto('data_fim') === '' ? null : dataIsoParaYmd($texto('data_fim'));
        if ($texto('data_inicio') !== '' && $inicio === null) $erros['data_inicio'] = 'Data inicial inválida.';
        if ($texto('data_fim') !== '' && $fim === null) $erros['data_fim'] = 'Data final inválida.';
        if ($inicio !== null && $fim !== null && $inicio > $fim) $erros['data_fim'] = 'A data final deve ser posterior à inicial.';
        if ($erros !== []) return [[], $erros];
        $dados = ['titulo' => $texto('titulo'), 'categoria' => $texto('categoria'), 'valor' => $valor, 'periodicidade' => $periodicidade, 'dia_vencimento' => $dia, 'regra_vencimento' => $regra, 'forma_pagamento' => $texto('forma_pagamento'), 'ativo' => (int) $texto('ativo'), 'observacoes' => $texto('observacoes'), 'data_inicio' => $inicio, 'data_fim' => $fim, 'usuarios_id' => $this->usuarioLogado(), 'updated_at' => date('Y-m-d H:i:s')];
        if ($id === null) $dados['created_at'] = date('Y-m-d H:i:s');

        return [$dados, []];
    }


    /**
     * Listagem de lançamentos da v5 (#2844), no padrão das listagens (#2852):
     * filtros na URL (período, vencimento, tipo, situação e busca), cards de
     * resumo do que o filtro encontra, paginação que mantém os filtros e
     * exclusão confirmada em modal-confirm.
     */
    public function lancamentos()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar lançamentos.');
            redirect(base_url());
        }

        $hoje = date('Y-m-d');
        $filtros = $this->filtrosDaListagem();
        $offset = (int) $this->uri->segment(3);
        $total = $this->financeiro_model->contar($filtros, $hoje);

        $this->data['hoje'] = $hoje;
        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->financeiro_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset, $hoje);
        $this->data['totais'] = $this->financeiro_model->totais($filtros, $hoje);
        $this->data['indicadores_financeiros'] = $this->financeiro_model->indicadoresGerenciais($filtros, $hoje);
        $this->data['visao_geral'] = $this->financeiro_model->visaoGeral();
        $this->data['paginacao'] = $this->paginacao(site_url('financeiro/lancamentos'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aLancamento'),
            'editar' => $this->permite('eLancamento'),
            'excluir' => $this->permite('dLancamento'),
            'ver_cliente' => $this->permite('vCliente'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo lançamento', 'icon' => 'plus', 'href' => site_url('financeiro/adicionar') . listagemQuery($filtros)];
        }

        $this->data['view'] = 'financeiro/lancamentos';

        return $this->layout();
    }

    /**
     * Filtros válidos da listagem. O período predefinido escolhido (dia,
     * semana, mês...) vale mais que as datas da URL, que então são calculadas
     * dele; "personalizado" usa as datas de/ate (AAAA-MM-DD, de input
     * type=date), e data inválida ou ausente volta ao mês atual. Sem nada na
     * URL, a listagem mostra o mês atual (a v4 mostrava só o dia).
     *
     * @param  array<string, string>|null  $entrada  Filtros a normalizar; sem eles, a query string
     * @return array<string, string>
     */
    private function filtrosDaListagem(?array $entrada = null): array
    {
        $filtros = listagemFiltros(self::FILTROS, $entrada ?? $this->input->get());
        $hoje = new DateTimeImmutable('today');

        $de = dataIsoParaYmd($filtros['de'] ?? null);
        $ate = dataIsoParaYmd($filtros['ate'] ?? null);
        $periodo = $filtros['periodo'] ?? ($de !== null && $ate !== null ? 'personalizado' : 'mes');

        $intervalo = $periodo === 'personalizado' ? null : financeiroPeriodo($periodo, $hoje);
        if ($intervalo === null && $de !== null && $ate !== null) {
            $intervalo = $de <= $ate ? [$de, $ate] : [$ate, $de];
            $periodo = 'personalizado';
        } elseif ($intervalo === null) {
            $intervalo = financeiroPeriodo('mes', $hoje);
            $periodo = 'mes';
        }

        // Período e datas primeiro, nessa ordem, como ficam na URL.
        return ['periodo' => $periodo, 'de' => $intervalo[0], 'ate' => $intervalo[1]]
            + array_diff_key($filtros, ['periodo' => true, 'de' => true, 'ate' => true]);
    }

    /**
     * Filtros de volta da listagem, vindos da URL do formulário. Valem só os
     * da lista de FILTROS (nada de URL digitada), e voltam como query string.
     *
     * @return array<string, string>
     */
    private function filtrosDeRetorno(): array
    {
        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        foreach (['de', 'ate'] as $data) {
            if (isset($filtros[$data]) && dataIsoParaYmd($filtros[$data]) === null) {
                unset($filtros[$data]);
            }
        }

        return $filtros;
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar lançamentos.');
            redirect(base_url());
        }

        return $this->formulario(null);
    }

    public function editar()
    {
        $lancamento = is_numeric($this->uri->segment(3)) ? $this->financeiro_model->getLancamento((int) $this->uri->segment(3)) : null;
        if (! $lancamento) {
            $this->session->set_flashdata('error', 'Lançamento não encontrado ou parâmetro inválido.');
            redirect('financeiro/lancamentos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar lançamentos.');
            redirect(base_url());
        }

        return $this->formulario($lancamento);
    }

    /**
     * Formulário de lançamento da v5 (#2844), para cadastrar e editar, no
     * padrão dos formulários (#2851): POST comum, validação no servidor com os
     * erros em cada campo e redirecionamento com toast. O id editado vem da
     * URL, nunca do POST.
     *
     * Valor líquido, parcelas e datas são calculados aqui (na v4 o navegador
     * mandava o total com desconto). Ao cadastrar, um lançamento parcelado
     * vira uma linha por parcela (mais a entrada, se houver).
     */
    private function formulario(?object $lancamento)
    {
        $editando = $lancamento !== null;
        $erros = [];
        $retorno = $this->filtrosDeRetorno();

        if ($this->input->method() === 'post') {
            $post = $this->input->post();
            $idCliente = is_scalar($post['clientes_id'] ?? null) && ctype_digit((string) $post['clientes_id']) ? (int) $post['clientes_id'] : 0;

            [$dados, $erros] = financeiroLancamentoDoFormulario(
                $post,
                [
                    'hoje' => date('Y-m-d'),
                    'controle_baixa' => ($this->data['configuration']['control_baixa'] ?? '0') == '1',
                    'pagamento_atual' => $editando ? substr((string) $lancamento->data_pagamento, 0, 10) : null,
                ],
                $idCliente > 0 ? $this->financeiro_model->getCliente($idCliente) : null
            );

            $parcelamento = ['parcelas' => 1, 'entrada' => 0, 'data_entrada' => null];
            if (! $editando) {
                [$parcelamento, $errosParcelas] = financeiroParcelamentoDoFormulario($post, $dados);
                $erros += $errosParcelas;
            }

            if ($erros === []) {
                $dados['usuarios_id'] = $this->usuarioLogado();

                if ($editando) {
                    $salvou = $this->financeiro_model->atualizar((int) $lancamento->idLancamentos, $dados);
                    $mensagem = 'Lançamento #' . (int) $lancamento->idLancamentos . ' salvo.';
                } elseif ($parcelamento['parcelas'] > 1) {
                    $linhas = financeiroLinhasDoParcelamento($dados, $parcelamento, $dados['usuarios_id']);
                    $salvou = $this->financeiro_model->adicionarVarios($linhas);
                    $mensagem = ucfirst(FINANCEIRO_TIPOS[$dados['tipo']]) . ' parcelada em ' . $parcelamento['parcelas'] . 'x: ' . count($linhas) . ' lançamentos criados.';
                } else {
                    $salvou = $this->financeiro_model->adicionar($dados) !== null;
                    $mensagem = ucfirst(FINANCEIRO_TIPOS[$dados['tipo']]) . ' lançada com vencimento em ' . dataBr($dados['data_vencimento']) . '.';
                }

                if ($salvou) {
                    log_info(($editando ? 'Alterou um lançamento no financeiro. ID ' . (int) $lancamento->idLancamentos : 'Adicionou um lançamento em Financeiro'));
                    $this->session->set_flashdata('success', $mensagem);

                    return redirect($this->urlDaListagemApos($retorno, (string) $dados['data_vencimento'], (string) $dados['tipo']));
                }

                $erros['_geral'] = 'Não foi possível salvar. Tente de novo.';
            }
        }

        $tipoPadrao = ($retorno['tipo'] ?? 'receita');
        $this->data['lancamento'] = $lancamento;
        $this->data['valores'] = financeiroValoresDoFormulario(
            $this->input->method() === 'post' ? $this->input->post() : null,
            $lancamento,
            ['tipo' => $tipoPadrao, 'data_vencimento' => date('Y-m-d'), 'data_entrada' => date('Y-m-d'), 'data_pagamento' => date('Y-m-d')]
        );
        $this->data['erros'] = $erros;
        $this->data['retorno'] = $retorno;
        $this->data['controle_baixa'] = ($this->data['configuration']['control_baixa'] ?? '0') == '1';
        $this->data['view'] = 'financeiro/formulario';

        return $this->layout();
    }

    /**
     * Listagem para onde o formulário volta, com os filtros de onde veio. O
     * filtro de tipo sai quando o lançamento salvo é do outro tipo, e o
     * período passa a ser o mês do vencimento quando ele fica fora do período
     * da listagem (senão a pessoa não veria o que acabou de lançar).
     *
     * @param  array<string, string>  $retorno
     */
    private function urlDaListagemApos(array $retorno, string $vencimento, string $tipo): string
    {
        if (isset($retorno['tipo']) && $retorno['tipo'] !== $tipo) {
            unset($retorno['tipo']);
        }

        $periodo = $this->filtrosDaListagem($retorno);
        if ($vencimento < $periodo['de'] || $vencimento > $periodo['ate']) {
            $mes = financeiroPeriodo('mes', new DateTimeImmutable($vencimento));
            [$retorno['de'], $retorno['ate']] = $mes;
            $retorno['periodo'] = 'personalizado';
        }

        return site_url('financeiro/lancamentos') . listagemQuery($retorno);
    }

    private function usuarioLogado(): ?int
    {
        $id = (int) $this->session->userdata('id_admin');

        return $id > 0 ? $id : null;
    }

    /**
     * Exclui o lançamento (id no POST). Se ele era a fatura de uma venda ou de
     * uma OS, a venda ou a OS volta a não faturada (Financeiro_model::excluir()).
     */
    public function excluirLancamento()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dLancamento')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir lançamentos.');
            redirect(base_url());
        }

        $id = $this->input->post('id');
        // Volta para a listagem com os mesmos filtros (só os permitidos).
        $listagem = site_url('financeiro/lancamentos') . listagemQuery($this->filtrosDeRetorno());

        if (! is_scalar($id) || ! ctype_digit((string) $id) || ! $this->financeiro_model->getLancamento((int) $id)) {
            $this->session->set_flashdata('error', 'Lançamento não encontrado.');
            redirect($listagem);
        }

        if (! $this->financeiro_model->excluir((int) $id, $this->usuarioLogado())) {
            $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar excluir o lançamento.');
            redirect($listagem);
        }

        log_info('Excluiu um lançamento. ID: ' . (int) $id);
        $this->session->set_flashdata('success', 'Lançamento #' . (int) $id . ' excluído.');
        redirect($listagem);
    }

    /**
     * Sugestões do campo Cliente / Fornecedor: clientes do cadastro (com id,
     * que vincula o lançamento ao cliente) e nomes já usados em lançamentos.
     * Sempre uma lista JSON, vazia sem permissão ou sem termo.
     */
    public function autoCompleteClienteFornecedor()
    {
        $termo = $this->input->get('term');
        $itens = [];

        if ($this->hasAnyPermission(['vLancamento', 'aLancamento', 'eLancamento']) && is_string($termo) && trim($termo) !== '') {
            $termo = trim($termo);
            $clientes = $this->financeiro_model->sugerirClientes($termo);
            $nomes = array_filter(
                $this->financeiro_model->sugerirNomesUsados($termo),
                static fn ($nome) => ! in_array(mb_strtolower($nome['valor']), array_map(static fn ($c) => mb_strtolower($c['valor']), $clientes), true)
            );
            $itens = array_slice(array_merge($clientes, array_values($nomes)), 0, 10);
        }

        $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($itens));
    }
}
