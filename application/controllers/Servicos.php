<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Servicos extends MY_Controller
{
    public const FILTROS = ['pesquisa' => 'texto'];
    public function __construct()
    {
        parent::__construct();

        $this->load->helper('form');
        $this->load->model('servicos_model');
        $this->data['menuServicos'] = 'Serviços';
    }

    public function index()
    {
        $this->gerenciar();
    }

    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vServico')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar serviços.');
            redirect(base_url());
        }

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->uri->segment(3);
        $total = $this->servicos_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->servicos_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('servicos/gerenciar'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aServico'),
            'editar' => $this->permite('eServico'),
            'excluir' => $this->permite('dServico'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo serviço', 'icon' => 'plus', 'href' => site_url('servicos/adicionar')];
        }

        $this->data['view'] = 'servicos/servicos';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aServico')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar serviços.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('servicos') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $preco = valorDecimal($this->input->post('preco'));
            $padraoServicoGlobal = garantiaPadraoGlobalDias($this->data['configuration'] ?? null, 'servico');
            [$dadosGarantia, $erroGarantia] = $this->dadosGarantiaServico(null, $padraoServicoGlobal);
            if ($erroGarantia !== null) {
                $this->data['custom_error'] = '<div class="form_error"><p>' . htmlspecialchars($erroGarantia, ENT_QUOTES, 'UTF-8') . '</p></div>';
            } else {
            $data = [
                'nome' => set_value('nome'),
                'descricao' => set_value('descricao'),
                'preco' => $preco,
                'garantia_dias' => $this->input->post('garantia_dias'),
            ];
            $data = array_merge($data, $dadosGarantia);

            if ($this->servicos_model->add('servicos', $data) == true) {
                $this->session->set_flashdata('success', 'Serviço adicionado com sucesso!');
                log_info('Adicionou um serviço');
                redirect(site_url('servicos/adicionar/'));
            } else {
                $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro.</p></div>';
            }
            }
        }
        $this->data['servico'] = null;
        $padraoServicoGlobal = garantiaPadraoGlobalDias($this->data['configuration'] ?? null, 'servico');
        $this->data['valores'] = array_merge([
            'nome' => '',
            'preco' => '',
            'descricao' => '',
            'garantia_dias' => (string) $padraoServicoGlobal,
            'garantia_dias_custom' => '0',
        ], (array) $this->input->post());
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['view'] = 'servicos/formulario';

        return $this->layout();
    }

    public function editar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3)) || ! $this->servicos_model->getById($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Serviço não encontrado ou parâmetro inválido.');
            redirect('servicos/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eServico')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar serviços.');
            redirect(base_url());
        }
        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('servicos') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $preco = valorDecimal($this->input->post('preco'));
            $servicoOriginal = $this->servicos_model->getById($this->input->post('idServicos'));
            $padraoServicoGlobal = garantiaPadraoGlobalDias($this->data['configuration'] ?? null, 'servico');
            [$dadosGarantia, $erroGarantia] = $this->dadosGarantiaServico($servicoOriginal ?: null, $padraoServicoGlobal);
            if ($erroGarantia !== null) {
                $this->data['custom_error'] = '<div class="form_error"><p>' . htmlspecialchars($erroGarantia, ENT_QUOTES, 'UTF-8') . '</p></div>';
            } else {
            $data = [
                'nome' => $this->input->post('nome'),
                'descricao' => $this->input->post('descricao'),
                'preco' => $preco,
                'garantia_dias' => $this->input->post('garantia_dias'),
            ];
            $data = array_merge($data, $dadosGarantia);

            if ($this->servicos_model->edit('servicos', $data, 'idServicos', $this->input->post('idServicos')) == true) {
                $this->session->set_flashdata('success', 'Serviço editado com sucesso!');
                log_info('Alterou um serviço. ID: ' . $this->input->post('idServicos'));
                redirect(site_url('servicos/editar/') . $this->input->post('idServicos'));
            } else {
                $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um errro.</p></div>';
            }
            }
        }

        $servico = $this->servicos_model->getById($this->uri->segment(3));
        $padraoServicoGlobal = garantiaPadraoGlobalDias($this->data['configuration'] ?? null, 'servico');
        $garantiaExibida = garantiaDiasCustomizado($servico)
            ? (string) ($servico->garantia_dias ?? $padraoServicoGlobal)
            : (string) $padraoServicoGlobal;
        $this->data['result'] = $servico;
        $this->data['servico'] = $servico;
        $this->data['valores'] = [
            'nome' => $servico->nome ?? '',
            'preco' => number_format((float) ($servico->preco ?? 0), 2, ',', '.'),
            'descricao' => $this->input->post('descricao') !== null ? $this->input->post('descricao') : ($servico->descricao ?? ''),
            'garantia_dias' => $this->input->post('garantia_dias') !== null ? $this->input->post('garantia_dias') : $garantiaExibida,
            'garantia_dias_custom' => $this->input->post('garantia_dias_custom') !== null ? $this->input->post('garantia_dias_custom') : (garantiaDiasCustomizado($servico) ? '1' : '0'),
        ];
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['view'] = 'servicos/formulario';

        return $this->layout();
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dServico')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir serviços.');
            redirect(base_url());
        }

        $id = $this->input->post('id');
        if ($id == null) {
            $this->session->set_flashdata('error', 'Erro ao tentar excluir serviço.');
            redirect(site_url('servicos/gerenciar/'));
        }

        $this->servicos_model->delete('servicos_os', 'servicos_id', $id);
        $this->servicos_model->delete('servicos', 'idServicos', $id);

        log_info('Removeu um serviço. ID: ' . $id);

        $this->session->set_flashdata('success', 'Serviço excluido com sucesso!');
        redirect(site_url('servicos/gerenciar/'));
    }

    public function validar_garantia_dias($dias): bool
    {
        // Vazio é válido apenas para campos assinalados como permit_empty;
        // os campos obrigatórios já falham antes deste callback pelo 'required'.
        if (trim((string) $dias) === '') {
            return true;
        }
        if (garantiaDiasValido($dias)) {
            return true;
        }

        $mensagem = 'Informe zero ou um número inteiro de dias.';
        $this->form_validation->set_message('validar_garantia_dias', $mensagem);

        return false;
    }

    /**
     * Campos de garantia comercial do serviço a partir do POST.
     * Mesma regra de herança/personalização dos produtos, com o padrão global de serviços.
     *
     * @return array{0: array<string,mixed>, 1: string|null}
     */
    private function dadosGarantiaServico(?object $servico, ?int $padraoGlobal): array
    {
        $postBruto = $this->input->post('garantia_dias');
        $postDias = garantiaNormalizarDias($postBruto);
        if ($postDias === null) {
            return [[], 'Informe zero ou um número inteiro de dias para a garantia do serviço.'];
        }
        $checkedCustom = $this->input->post('garantia_dias_custom') === '1';
        $storedDias = $servico !== null && isset($servico->garantia_dias) ? (int) $servico->garantia_dias : null;
        if ($checkedCustom) {
            $dados = ['garantia_dias_custom' => 1];
        } elseif ($storedDias !== null && $postDias === $storedDias) {
            $dados = ['garantia_dias_custom' => 0]; // sem alteração: herda o padrão
        } elseif ($padraoGlobal !== null && $postDias === $padraoGlobal) {
            $dados = ['garantia_dias_custom' => 0]; // voltou ao padrão: herda
        } else {
            $dados = ['garantia_dias_custom' => 1]; // edição explícita: personalizada
        }
        $dados['garantia_dias'] = $postDias;

        return [$dados, null];
    }
}
