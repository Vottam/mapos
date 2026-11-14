<?php

/**
 * Central fail-closed policy for external e-mail delivery.
 *
 * The administrative email_automatico setting is intentionally not consulted.
 * Only an explicit production runtime may deliver; staging, development,
 * test, missing, or malformed environment identity is blocked.
 */
if (! function_exists('mapos_email_external_delivery_forbidden')) {
    function mapos_email_external_delivery_forbidden(): bool
    {
        $read = static function (string $key): string {
            $value = getenv($key);
            if ($value === false && isset($_ENV[$key])) {
                $value = $_ENV[$key];
            }

            return trim((string) $value);
        };

        $policy = strtoupper($read('STAGING_EMAIL_EXTERNAL_DELIVERY'));
        $environment = strtolower($read('APP_ENVIRONMENT'));

        if ($policy !== '' && ! in_array($policy, ['FORBIDDEN', 'ALLOW'], true)) {
            return true;
        }

        if ($policy === 'FORBIDDEN') {
            return true;
        }

        return $environment !== 'production';
    }
}
