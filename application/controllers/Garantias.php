<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Garantias extends MY_Controller
{
    /** Filtros da listagem, na query string (listagemFiltros()). */
    public const FILTROS = ['pesquisa' => 'texto'];

    public function __construct()
    {
        parent::__construct();

        $this->load->helper(['form', 'os']);
        $this->load->model('garantias_model');
        $this->data['menuGarantia'] = 'Garantias';
    }

    public function index()
    {
        $this->gerenciar();
    }

    /**
     * Listagem dos termos de garantia (#2845), no padrão das listagens
     * (#2852).
     */
    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vGarantia')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar Termo de Garantia.');
            redirect(base_url());
        }

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->uri->segment(3);
        $total = $this->garantias_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->garantias_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('garantias/gerenciar'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aGarantia'),
            'editar' => $this->permite('eGarantia'),
            'excluir' => $this->permite('dGarantia'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo termo', 'icon' => 'plus', 'href' => site_url('garantias/adicionar')];
        }

        $this->data['view'] = 'garantias/garantias';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aGarantia')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar Termo de Garantia.');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('garantias') == false) {
            $this->data['custom_error'] = (validation_errors() ? true : false);
        } else {
            $data = [
                'dataGarantia' => date('Y/m/d'),
                'refGarantia' => $this->input->post('refGarantia'),
                'textoGarantia' => $this->input->post('textoGarantia'),
                'usuarios_id' => $this->session->userdata('id_admin'),
            ];

            if (is_numeric($id = $this->garantias_model->add('garantias', $data, true))) {
                log_info('Adicionou uma garantia');
                $this->session->set_flashdata('success', 'Termo de Garantia adicionado com sucesso.');
                redirect(site_url('garantias/editar/') . $id);
            } else {
                $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro.</p></div>';
            }
        }

        $garantia = null;
        $this->data['garantia'] = $garantia;
        $this->data['valores'] = array_merge([
            'refGarantia' => '',
            'textoGarantia' => '',
        ], (array) $this->input->post());
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['view'] = 'garantias/formulario';

        return $this->layout();
    }

    public function editar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3)) || ! $this->garantias_model->getById($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Termo de garantia não encontrado ou parâmetro inválido.');
            redirect('garantias/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eGarantia')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar termo de garantia');
            redirect(base_url());
        }

        $this->load->library('form_validation');
        $this->data['custom_error'] = '';

        if ($this->form_validation->run('garantias') == false) {
            $this->data['custom_error'] = (validation_errors() ? '<div class="form_error">' . validation_errors() . '</div>' : false);
        } else {
            $data = [
                'textoGarantia' => $this->input->post('textoGarantia'),
                'refGarantia' => $this->input->post('refGarantia'),
            ];

            if ($this->garantias_model->edit('garantias', $data, 'idGarantias', $this->input->post('idGarantias')) == true) {
                $this->session->set_flashdata('success', 'Termo de garantia editada com sucesso!');
                log_info('Alterou uma garantia. ID: ' . $this->input->post('idGarantias'));
                redirect(site_url('garantias/editar/') . $this->input->post('idGarantias'));
            } else {
                $this->data['custom_error'] = '<div class="form_error"><p>Ocorreu um erro</p></div>';
            }
        }

        $garantia = $this->garantias_model->getById($this->uri->segment(3));
        $this->data['result'] = $garantia;
        $this->data['garantia'] = $garantia;
        $this->data['valores'] = array_merge([
            'refGarantia' => (string) ($garantia->refGarantia ?? ''),
            'textoGarantia' => (string) ($garantia->textoGarantia ?? ''),
        ], (array) $this->input->post());
        $this->data['erros'] = $this->form_validation->error_array();
        if ($this->data['custom_error'] && empty($this->data['erros'])) {
            $this->data['erros']['_geral'] = strip_tags($this->data['custom_error']);
        }
        $this->data['view'] = 'garantias/formulario';

        return $this->layout();
    }

    public function visualizar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vGarantia')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar o termo de garantia.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->load->model('mapos_model');
        $this->data['result'] = $this->garantias_model->getById($this->uri->segment(3));
        $this->data['emitente'] = $this->mapos_model->getEmitente();

        $this->data['view'] = 'garantias/visualizarGarantia';

        return $this->layout();
    }

    public function imprimir()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vGarantia')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para imprimir o Termo de Garantia.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->load->model('mapos_model');
        $this->data['result'] = $this->garantias_model->getById($this->uri->segment(3));
        if (! $this->data['result']) {
            $this->session->set_flashdata('error', 'Termo de garantia não encontrado.');
            redirect('garantias');
        }
        $this->data['emitente'] = $this->mapos_model->getEmitente();

        $this->load->helper(['mpdf', 'garantias_pdf']);
        $html = garantiaPdfHtml(
            $this->data['emitente'],
            (string) $this->data['result']->textoGarantia
        );
        pdf_create($html, 'garantia_' . (int) $this->uri->segment(3), true);
    }

    public function imprimirGarantiaOs()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vGarantia')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para imprimir o Termo de Garantia.');
            redirect(base_url());
        }

        $this->data['custom_error'] = '';
        $this->load->model('mapos_model');
        $this->data['osGarantia'] = $this->garantias_model->getByIdOsGarantia($this->uri->segment(3));
        if (! $this->data['osGarantia']) {
            $this->session->set_flashdata('error', 'Termo de garantia da OS não encontrado.');
            redirect('os/visualizar/' . (int) $this->uri->segment(3));
        }
        $this->data['emitente'] = $this->mapos_model->getEmitente();

        $this->load->helper(['mpdf', 'garantias_pdf', 'os']);
        $dataBaseGarantia = osDataBaseDaGarantia(
            (string) ($this->data['osGarantia']->osDataFinal ?? ''),
            (string) ($this->data['osGarantia']->osDataEntrega ?? ''),
            (string) ($this->data['osGarantia']->osStatus ?? '')
        );
        $html = garantiaPdfHtml(
            $this->data['emitente'],
            (string) $this->data['osGarantia']->textoGarantia,
            (int) $this->data['osGarantia']->idOs,
            $dataBaseGarantia ?? ''
        );
        pdf_create($html, 'garantia_os_' . (int) $this->uri->segment(3), true);
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dGarantia')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir termo de garantia');
            redirect(base_url());
        }

        $id = $this->input->post('id');
        if ($id == null) {
            $this->session->set_flashdata('error', 'Erro ao tentar excluir termo de garantia.');
            redirect('garantias/gerenciar/');
        }

        $emUso = $this->garantias_model->osVinculadas((int) $id);
        if ($emUso > 0) {
            $this->session->set_flashdata('error', 'Você não pode excluir esse termo de garantia: ' . ($emUso === 1 ? '1 OS usa' : $emUso . ' OS usam') . ' este termo. Troque o termo dela e tente de novo.');
            redirect('garantias' . listagemQuery(listagemFiltros(self::FILTROS, $this->input->get())));
        }

        if ($this->garantias_model->delete('garantias', 'idGarantias', $id) == true) {
            $this->session->set_flashdata('success', 'Termo de garantia excluído com sucesso!');
            log_info('Removeu uma garantia. ID: ' . $id);
        } else {
            $this->session->set_flashdata('error', 'Não foi possível excluir o termo de garantia.');
        }

        redirect('garantias' . listagemQuery(listagemFiltros(self::FILTROS, $this->input->get())));
    }

    public function autoCompleteProduto()
    {
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            $this->vendas_model->autoCompleteProduto($q);
        }
    }

    public function autoCompleteCliente()
    {
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            $this->vendas_model->autoCompleteCliente($q);
        }
    }

    public function autoCompleteUsuario()
    {
        if (isset($_GET['term'])) {
            $q = strtolower($_GET['term']);
            $this->vendas_model->autoCompleteUsuario($q);
        }
    }
}
