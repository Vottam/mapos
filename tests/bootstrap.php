<?php

declare(strict_types=1);

if (! function_exists('html_escape')) {
    function html_escape($var, bool $double_encode = true): string
    {
        return htmlspecialchars((string) $var, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $double_encode);
    }
}

require_once __DIR__ . '/../application/helpers/escape_helper.php';
require_once __DIR__ . '/../application/helpers/clientes_helper.php';
