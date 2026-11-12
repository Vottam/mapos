<?php

defined('BASEPATH') or exit('No direct script access allowed');
/**
 * CodeIgniter Email Queue
 *
 * A CodeIgniter library to queue e-mails.
 *
 * @category    Libraries
 *
 * @author      Thaynã Bruno Moretti
 *
 * @link    http://www.meau.com.br/
 *
 * @license http://www.opensource.org/licenses/mit-license.html
 *
 * Updated by @RamonSilva for Map-OS
 */
class MY_Email extends CI_Email
{
    // DB table
    private $table_email_queue = 'email_queue';

    // Main controller
    private $main_controller = 'email/process';

    // PHP Nohup command line
    private $phpcli = 'nohup php';

    private $expiration = null;

    // Status (pending, sending, sent, failed)
    private $status;

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        log_message('debug', 'Email Queue Class Initialized');

        $this->expiration = 60 * 5;
        $this->CI = &get_instance();

        $this->CI->load->database('default');
    }

    public function set_status($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get
     *
     * Get queue emails.
     *
     * @return mixed
     */
    public function get($limit = null, $offset = null)
    {
        if ($this->status != false) {
            $this->CI->db->where('status', $this->status);
        }

        $query = $this->CI->db->get("{$this->table_email_queue}", $limit, $offset);

        return $query->result();
    }

    /**
     * Save
     *
     * Add queue email to database.
     *
     * @return mixed
     */
    public function send($skip_job = false)
    {
        if ($skip_job === true) {
            return parent::send();
        }

        $date = date('Y-m-d H:i:s');

        $to = is_array($this->_recipients) ? implode(', ', $this->_recipients) : $this->_recipients;
        $cc = implode(', ', $this->_cc_array);
        $bcc = implode(', ', $this->_bcc_array);

        $dbdata = [
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'message' => $this->_body,
            'headers' => json_encode($this->_headers),
            'status' => 'pending',
            'date' => $date,
        ];

        return $this->CI->db->insert($this->table_email_queue, $dbdata);
    }

    /**
     * Start process
     *
     * Start php process to send emails
     *
     * @return mixed
     */
    public function start_process()
    {
        $filename = FCPATH . 'index.php';
        $exec = shell_exec("{$this->phpcli} {$filename} {$this->main_controller} > /dev/null &");

        return $exec;
    }

    /**
     * Legacy backlog fence
     *
     * Le a cerca canonica de identidade da fila. A fila ficou parada entre
     * 2026-06-02 e 2026-09-29 porque o cron do host chamava um container
     * removido (mapos_php-fpm), falhando em silencio. Durante a janela
     * congelaram 102 mensagens 'pending' (id 323-424) cujo conteudo divergiria
     * do estado atual do banco e que NUNCA devem ser enviadas automaticamente.
     *
     * A cerca e por identidade (id AUTO_INCREMENT monotonico), nao por data:
     * o campo 'date' e sobrescrito em toda transicao de status e nao serve
     * como criterio de elegibilidade.
     *
     * @return int|null id exclusivo da cerca, ou null em fail-closed
     */
    private function queue_fence()
    {
        $candidates = [
            FCPATH.'application/config/email_queue_fence.json',
            APPPATH.'cache/email_queue_fence.json',
        ];

        $fence = null;
        $used = null;

        foreach ($candidates as $file) {
            if (is_readable($file)) {
                $decoded = json_decode((string) file_get_contents($file), true);

                if (is_array($decoded) && isset($decoded['resume_after_id'])) {
                    $fence = $decoded;
                    $used = $file;
                    break;
                }
            }
        }

        if ($fence === null) {
            log_message('error', 'Email queue ABORTADA (fail-closed): email_queue_fence.json ausente ou invalido em '.implode(' | ', $candidates).'. Nenhuma mensagem foi enviada.');

            return null;
        }

        $idExclusive = (int) $fence['resume_after_id'];

        if ($idExclusive < 0) {
            log_message('error', 'Email queue ABORTADA (fail-closed): resume_after_id invalido em '.$used.'.');

            return null;
        }

        // Guarda 1: o bloco legado precisa continuar integro e congelado.
        if (isset($fence['legacy_pending_count'])) {
            $this->CI->db->where('status', 'pending');
            $this->CI->db->where('id <=', $idExclusive);
            $frozen = (int) $this->CI->db->count_all_results($this->table_email_queue);

            if ($frozen !== (int) $fence['legacy_pending_count']) {
                log_message('error', 'Email queue ABORTADA (fail-closed): bloco legado divergente em '.$used.'. Esperado '.$fence['legacy_pending_count'].' pending com id <= '.$idExclusive.', encontrado '.$frozen.'.');

                return null;
            }
        }

        // Guarda 2: o AUTO_INCREMENT precisa estar acima da cerca (monotonicidade).
        $meta = $this->CI->db->select('AUTO_INCREMENT')
            ->from('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $this->CI->db->database)
            ->where('TABLE_NAME', $this->table_email_queue)
            ->limit(1)
            ->get()
            ->row();

        if ($meta === null || (int) $meta->AUTO_INCREMENT <= $idExclusive) {
            log_message('error', 'Email queue ABORTADA (fail-closed): AUTO_INCREMENT de email_queue nao esta acima de '.$idExclusive.' (fence '.$used.').');

            return null;
        }

        return $idExclusive;
    }

    /**
     * Send queue
     *
     * Send queue emails. Apenas mensagens com id acima da cerca de backlog
     * legado sao selecionadas; o bloco congelado nao e tocado.
     *
     * @return void
     */
    public function send_queue()
    {
        $idExclusive = $this->queue_fence();

        if ($idExclusive === null) {
            return;
        }

        $this->CI->db->where('status', 'pending');
        $this->CI->db->where('id >', $idExclusive);
        $emails = $this->CI->db->get($this->table_email_queue)->result();

        if (empty($emails)) {
            log_message('debug', 'Email queue: nada elegivel (fence id > '.$idExclusive.').');

            return;
        }

        log_message('debug', 'Email queue: processando '.count($emails).' mensagem(ns) com id > '.$idExclusive.'.');

        // A transicao para 'sending' usa exatamente o mesmo filtro da selecao.
        // O UPDATE original marcava TODO o pending, incluindo o backlog legado.
        $this->CI->db->where('status', 'pending');
        $this->CI->db->where('id >', $idExclusive);
        $this->CI->db->set('status', 'sending');
        $this->CI->db->set('date', date('Y-m-d H:i:s'));
        $this->CI->db->update($this->table_email_queue);

        foreach ($emails as $email) {
            $recipients = explode(', ', $email->to);

            $cc = ! empty($email->cc) ? explode(', ', $email->cc) : [];
            $bcc = ! empty($email->bcc) ? explode(', ', $email->bcc) : [];

            $this->_headers = json_decode_legacy($email->headers);

            $this->to($recipients);
            $this->cc($cc);
            $this->bcc($bcc);

            $this->message($email->message);

            if ($this->send(true)) {
                $status = 'sent';
            } else {
                log_message('error', $this->print_debugger());
                $status = 'failed';
            }

            $this->CI->db->where('id', $email->id);

            $this->CI->db->set('status', $status);
            $this->CI->db->set('date', date('Y-m-d H:i:s'));
            $this->CI->db->update($this->table_email_queue);
        }
    }

    /**
     * Retry failed emails
     *
     * Resend failed or expired emails
     *
     * @return void
     */
    public function retry_queue()
    {
        $idExclusive = $this->queue_fence();

        if ($idExclusive === null) {
            return;
        }

        $expire = (time() - $this->expiration);
        $date_expire = date('Y-m-d H:i:s', $expire);

        // A cerca tambem vale para o retry: o backlog legado permanece congelado
        // no status atual (failed/pending) em vez de ser ressuscitado.
        $this->CI->db->set('status', 'pending');
        $this->CI->db->where('id >', $idExclusive);
        $this->CI->db->where("(date < '{$date_expire}' AND status = 'sending')");
        $this->CI->db->or_where("status = 'failed' AND id > {$idExclusive}");

        $this->CI->db->update($this->table_email_queue);

        log_message('debug', 'Email queue retrying (fence id > '.$idExclusive.')...');
    }
}
