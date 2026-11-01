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
    /** @var object Instância do CodeIgniter, injetada por get_instance() no construtor */
    protected $CI;

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
     * The queue fence protects the legacy backlog by monotonically increasing
     * queue id. Missing or invalid fence data is fail-closed.
     *
     * @return int|null exclusive queue id, or null when sending is blocked
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
            log_message('error', 'Email queue ABORTED (fail-closed): email_queue_fence.json is missing or invalid in '.implode(' | ', $candidates).'. No message was sent.');

            return null;
        }

        $idExclusive = (int) $fence['resume_after_id'];

        if ($idExclusive < 0) {
            log_message('error', 'Email queue ABORTED (fail-closed): invalid resume_after_id in '.$used.'.');

            return null;
        }

        // Guard 1: the frozen legacy block must remain unchanged.
        if (isset($fence['legacy_pending_count'])) {
            $this->CI->db->where('status', 'pending');
            $this->CI->db->where('id <=', $idExclusive);
            $frozen = (int) $this->CI->db->count_all_results($this->table_email_queue);

            if ($frozen !== (int) $fence['legacy_pending_count']) {
                log_message('error', 'Email queue ABORTED (fail-closed): legacy block diverged in '.$used.'. Expected '.$fence['legacy_pending_count'].' pending with id <= '.$idExclusive.', found '.$frozen.'.');

                return null;
            }
        }

        // Guard 2: new queue ids must be strictly above the fence.
        $meta = $this->CI->db->select('AUTO_INCREMENT')
            ->from('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $this->CI->db->database)
            ->where('TABLE_NAME', $this->table_email_queue)
            ->limit(1)
            ->get()
            ->row();

        if ($meta === null || (int) $meta->AUTO_INCREMENT <= $idExclusive) {
            log_message('error', 'Email queue ABORTED (fail-closed): email_queue AUTO_INCREMENT is not above '.$idExclusive.' (fence '.$used.').');

            return null;
        }

        return $idExclusive;
    }

    /**
     * Send queue
     *
     * Send only pending messages above the legacy backlog fence.
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
            log_message('debug', 'Email queue: no eligible messages (fence id > '.$idExclusive.').');

            return;
        }

        log_message('debug', 'Email queue: processing '.count($emails).' message(s) with id > '.$idExclusive.'.');

        // The transition uses the same fence as selection.
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
     * Only explicitly failed rows are retryable. A row in sending has an
     * unknown delivery state and requires manual reconciliation.
     *
     * @return void
     */
    public function retry_queue()
    {
        $idExclusive = $this->queue_fence();

        if ($idExclusive === null) {
            return;
        }

        // Automatic retry is fail-closed for sending: it may have been
        // delivered before a worker crash, so requeueing it can duplicate mail.
        // New failed rows remain eligible after the ID fence is released.
        $this->CI->db->set('status', 'pending');
        $this->CI->db->where('status', 'failed');
        $this->CI->db->where('id >', $idExclusive);
        $this->CI->db->update($this->table_email_queue);

        log_message('debug', 'Email queue retrying failed rows (fence id > '.$idExclusive.'); sending rows require manual reconciliation.');
    }
}
