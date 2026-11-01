<?php

declare(strict_types=1);

/**
 * Isolated queue_fence tests.
 *
 * This harness stubs CodeIgniter's DB and SMTP parent class. It never opens a
 * socket and uses only a temporary copy of the candidate library/config.
 */

final class FakeResult
{
    public function __construct(private array $rows) {}

    public function result(): array
    {
        return array_map(static fn (array $row): object => (object) $row, $this->rows);
    }

    public function row(): ?object
    {
        return $this->rows === [] ? null : (object) $this->rows[0];
    }
}

final class FakeLoader
{
    public function database(string $group = 'default'): void {}
}

final class FakeDB
{
    public string $database = 'isolated_queue_fence';
    private array $rows;
    private int $autoIncrement;
    private array $where = [];
    private array $orWhere = [];
    private array $set = [];
    private ?string $fromTable = null;

    public function __construct(array $rows, int $autoIncrement)
    {
        $this->rows = $rows;
        $this->autoIncrement = $autoIncrement;
    }

    public function where(string $key, mixed $value = null): self
    {
        if ($value === null) {
            $this->where[] = ['raw', $key];
        } elseif (preg_match('/^(.*) (<=|>=|<>|=|<|>)$/', $key, $match)) {
            $this->where[] = ['op', trim($match[1]), $match[2], $value];
        } else {
            $this->where[] = ['eq', $key, $value];
        }

        return $this;
    }

    public function or_where(string $condition): self
    {
        $this->orWhere[] = $condition;

        return $this;
    }

    public function select(string $fields): self
    {
        return $this;
    }

    public function from(string $table): self
    {
        $this->fromTable = $table;

        return $this;
    }

    public function limit(int $limit): self
    {
        return $this;
    }

    public function count_all_results(string $table): int
    {
        $count = count(array_filter($this->rows, fn (array $row): bool => $this->matches($row)));
        $this->reset();

        return $count;
    }

    public function get(?string $table = null): FakeResult
    {
        $table ??= $this->fromTable;
        if ($table === 'information_schema.TABLES') {
            $result = new FakeResult([['AUTO_INCREMENT' => $this->autoIncrement]]);
        } else {
            $rows = array_filter($this->rows, fn (array $row): bool => $this->matches($row));
            $result = new FakeResult(array_values($rows));
        }
        $this->reset();

        return $result;
    }

    public function set(string $key, mixed $value): self
    {
        $this->set[$key] = $value;

        return $this;
    }

    public function update(string $table): bool
    {
        foreach ($this->rows as &$row) {
            if ($this->matches($row)) {
                foreach ($this->set as $key => $value) {
                    $row[$key] = $value;
                }
            }
        }
        unset($row);
        $this->reset();

        return true;
    }

    public function rows(): array
    {
        return $this->rows;
    }

    private function matches(array $row): bool
    {
        $base = true;
        foreach ($this->where as $condition) {
            if (!$this->matchesCondition($row, $condition)) {
                $base = false;
                break;
            }
        }

        if ($this->orWhere === []) {
            return $base;
        }

        if ($base) {
            return true;
        }

        foreach ($this->orWhere as $condition) {
            if ($this->matchesRaw($row, $condition)) {
                return true;
            }
        }

        return false;
    }

    private function matchesCondition(array $row, array $condition): bool
    {
        if ($condition[0] === 'raw') {
            return $this->matchesRaw($row, $condition[1]);
        }

        $left = $row[$condition[1]] ?? null;
        $right = $condition[0] === 'eq' ? $condition[2] : $condition[3];
        $operator = $condition[0] === 'eq' ? '=' : $condition[2];

        return match ($operator) {
            '=' => (string) $left === (string) $right,
            '>' => (int) $left > (int) $right,
            '>=' => (int) $left >= (int) $right,
            '<' => (string) $left < (string) $right,
            '<=' => (int) $left <= (int) $right,
            '<>' => (string) $left !== (string) $right,
            default => false,
        };
    }

    private function matchesRaw(array $row, string $raw): bool
    {
        if (preg_match("/^\\(date < '([^']+)' AND status = 'sending'\\)$/", $raw, $match)) {
            return ($row['date'] ?? '') < $match[1] && ($row['status'] ?? '') === 'sending';
        }

        if (preg_match('/^status = \'failed\' AND id > (\\d+)$/', $raw, $match)) {
            return ($row['status'] ?? '') === 'failed' && (int) ($row['id'] ?? 0) > (int) $match[1];
        }

        return false;
    }

    private function reset(): void
    {
        $this->where = [];
        $this->orWhere = [];
        $this->set = [];
        $this->fromTable = null;
    }
}

class CI_Email
{
    public static int $sendCount = 0;
    protected array $_recipients = [];
    protected array $_cc_array = [];
    protected array $_bcc_array = [];
    protected array $_headers = [];
    protected string $_body = '';

    public function __construct(array $config = []) {}

    public function send($skip_job = false)
    {
        self::$sendCount++;

        return true;
    }

    public function to(array|string $value): self { $this->_recipients = (array) $value; return $this; }
    public function cc(array|string $value): self { $this->_cc_array = (array) $value; return $this; }
    public function bcc(array|string $value): self { $this->_bcc_array = (array) $value; return $this; }
    public function message(string $value): self { $this->_body = $value; return $this; }
    public function print_debugger(): string { return 'isolated stub'; }
}

function &get_instance(): object
{
    return $GLOBALS['CI_INSTANCE'];
}

function log_message(string $level, string $message): void
{
    $GLOBALS['QUEUE_LOG'][] = [$level, $message];
}

function json_decode_legacy(?string $value): mixed
{
    return json_decode((string) $value, true) ?? [];
}

function fail(string $message): never
{
    fwrite(STDERR, "FAIL {$message}\n");
    exit(1);
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

$scenario = $argv[1] ?? '';
$root = dirname(__DIR__, 2);
$source = $root.'/application/libraries/MY_Email.php';
$fence = $root.'/application/config/email_queue_fence.json';
$tmpBase = getenv('TMPDIR') ?: '/tmp';
$tmp = $tmpBase.'/mapos-queue-fence-test-'.($scenario ?: 'suite').'-'.bin2hex(random_bytes(4));
mkdir($tmp.'/application/config', 0700, true);
mkdir($tmp.'/application/libraries', 0700, true);
copy($source, $tmp.'/application/libraries/MY_Email.php');

if ($scenario !== 'missing') {
    if ($scenario === 'invalid') {
        file_put_contents($tmp.'/application/config/email_queue_fence.json', '{}');
    } else {
        copy($fence, $tmp.'/application/config/email_queue_fence.json');
    }
}

if (!defined('BASEPATH')) define('BASEPATH', 'isolated');
if (!defined('FCPATH')) define('FCPATH', $tmp.'/');
if (!defined('APPPATH')) define('APPPATH', $tmp.'/application/');

require $tmp.'/application/libraries/MY_Email.php';

$baseRows = [
    ['id' => 318, 'status' => 'failed', 'date' => '2026-05-28 13:24:10', 'to' => 'qa@example.invalid', 'cc' => '', 'bcc' => '', 'message' => 'old failed', 'headers' => '{}'],
    ['id' => 319, 'status' => 'failed', 'date' => '2026-05-28 16:18:10', 'to' => 'qa@example.invalid', 'cc' => '', 'bcc' => '', 'message' => 'old failed', 'headers' => '{}'],
    ['id' => 427, 'status' => 'failed', 'date' => '2026-09-29 02:52:01', 'to' => 'qa@example.invalid', 'cc' => '', 'bcc' => '', 'message' => 'quarantined failed', 'headers' => '{}'],
    ['id' => 450, 'status' => 'pending', 'date' => '2026-10-29 01:00:00', 'to' => 'qa@example.invalid', 'cc' => '', 'bcc' => '', 'message' => 'new pending 1', 'headers' => '{}'],
    ['id' => 451, 'status' => 'pending', 'date' => '2026-10-29 01:01:00', 'to' => 'qa@example.invalid', 'cc' => '', 'bcc' => '', 'message' => 'new pending 2', 'headers' => '{}'],
    ['id' => 453, 'status' => 'sending', 'date' => '2026-10-19 11:24:12', 'to' => 'qa@example.invalid', 'cc' => '', 'bcc' => '', 'message' => 'new sending', 'headers' => '{}'],
    ['id' => 454, 'status' => 'failed', 'date' => '2026-10-29 01:02:00', 'to' => 'qa@example.invalid', 'cc' => '', 'bcc' => '', 'message' => 'new failed', 'headers' => '{}'],
];

if ($scenario === 'missing' || $scenario === 'invalid') {
    $db = new FakeDB($baseRows, 466);
    $GLOBALS['CI_INSTANCE'] = (object) ['db' => $db, 'load' => new FakeLoader()];
    $GLOBALS['QUEUE_LOG'] = [];
    CI_Email::$sendCount = 0;
    $email = new MY_Email();
    $before = $db->rows();
    $email->send_queue();
    $email->retry_queue();
    assert_true(CI_Email::$sendCount === 0, 'fail-closed must not call SMTP parent');
    assert_true($db->rows() === $before, 'fail-closed must not mutate queue rows');
} elseif ($scenario === 'legacy-mismatch') {
    $rows = $baseRows;
    $rows[] = ['id' => 323, 'status' => 'pending', 'date' => '2026-06-02 16:33:56', 'to' => 'qa@example.invalid', 'cc' => '', 'bcc' => '', 'message' => 'legacy pending', 'headers' => '{}'];
    $db = new FakeDB($rows, 466);
    $GLOBALS['CI_INSTANCE'] = (object) ['db' => $db, 'load' => new FakeLoader()];
    $GLOBALS['QUEUE_LOG'] = [];
    CI_Email::$sendCount = 0;
    $email = new MY_Email();
    $before = $db->rows();
    $email->send_queue();
    assert_true(CI_Email::$sendCount === 0, 'legacy mismatch must block sending');
    assert_true($db->rows() === $before, 'legacy mismatch must not mutate rows');
} elseif ($scenario === 'auto-increment') {
    $db = new FakeDB($baseRows, 427);
    $GLOBALS['CI_INSTANCE'] = (object) ['db' => $db, 'load' => new FakeLoader()];
    $GLOBALS['QUEUE_LOG'] = [];
    CI_Email::$sendCount = 0;
    $email = new MY_Email();
    $before = $db->rows();
    $email->send_queue();
    assert_true(CI_Email::$sendCount === 0, 'invalid AUTO_INCREMENT must block sending');
    assert_true($db->rows() === $before, 'invalid AUTO_INCREMENT must not mutate rows');
} elseif ($scenario === 'valid') {
    $db = new FakeDB($baseRows, 466);
    $GLOBALS['CI_INSTANCE'] = (object) ['db' => $db, 'load' => new FakeLoader()];
    $GLOBALS['QUEUE_LOG'] = [];
    CI_Email::$sendCount = 0;
    $email = new MY_Email();
    $email->send_queue();
    $email->send_queue();
    $rows = $db->rows();
    $byId = [];
    foreach ($rows as $row) $byId[$row['id']] = $row;
    assert_true(CI_Email::$sendCount === 2, 'second send_queue call must not duplicate sent messages');
    assert_true($byId[318]['status'] === 'failed' && $byId[319]['status'] === 'failed' && $byId[427]['status'] === 'failed', 'historical failed/quarantined rows must stay failed');
    assert_true($byId[450]['status'] === 'sent' && $byId[451]['status'] === 'sent', 'eligible pending rows must be sent by the isolated parent stub');
    assert_true($byId[453]['status'] === 'sending', 'sending row must not be changed by send_queue');
    $email->retry_queue();
    $rows = $db->rows();
    foreach ($rows as $row) $byId[$row['id']] = $row;
    assert_true($byId[453]['status'] === 'sending', 'sending row above fence must remain manual-only');
    assert_true($byId[454]['status'] === 'pending', 'new failed row above fence must remain retryable');
    $email->send_queue();
    $rows = $db->rows();
    foreach ($rows as $row) $byId[$row['id']] = $row;
    assert_true($byId[454]['status'] === 'sent', 'new failed row must remain eligible after retry release');
    assert_true(CI_Email::$sendCount === 3, 'only two pending and one explicitly failed row may use the sink');
    assert_true($byId[427]['status'] === 'failed', 'failed row at fence must remain frozen');
} else {
    fail('unknown scenario');
}

$log = json_encode($GLOBALS['QUEUE_LOG'], JSON_UNESCAPED_SLASHES);
assert_true(!str_contains($log, 'smtp') && !str_contains($log, 'curl'), 'test must not use external transport');

fwrite(STDOUT, "PASS {$scenario} smtp_calls=".CI_Email::$sendCount."\n");

function remove_tree(string $path): void
{
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = $path.'/'.$entry;
        is_dir($full) ? remove_tree($full) : unlink($full);
    }
    rmdir($path);
}

remove_tree($tmp);
