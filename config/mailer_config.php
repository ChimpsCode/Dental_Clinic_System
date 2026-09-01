<?php

/**
 * Central SMTP configuration.
 *
 * Secrets are intentionally not hardcoded here. Values are resolved in this order:
 * 1) Environment variables
 * 2) config/mailer_config.local.php (local-only, ignored by git)
 * 3) Safe defaults
 */

if (!function_exists('mailer_config_get')) {
    function mailer_config_get(array $local, string $envKey, string $localKey, $default = '')
    {
        $envValue = getenv($envKey);
        if ($envValue !== false && $envValue !== '') {
            return $envValue;
        }

        if (array_key_exists($localKey, $local) && $local[$localKey] !== '' && $local[$localKey] !== null) {
            return $local[$localKey];
        }

        return $default;
    }
}

if (!function_exists('mailer_config_is_empty')) {
    function mailer_config_is_empty($value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}

$localConfig = [];
$localConfigFile = __DIR__ . '/mailer_config.local.php';
if (file_exists($localConfigFile)) {
    $loaded = require $localConfigFile;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', (string)mailer_config_get($localConfig, 'MAILER_SMTP_HOST', 'SMTP_HOST', 'smtp.gmail.com'));
}

if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int)mailer_config_get($localConfig, 'MAILER_SMTP_PORT', 'SMTP_PORT', 587));
}

if (!defined('SMTP_USER')) {
    define('SMTP_USER', (string)mailer_config_get($localConfig, 'MAILER_SMTP_USER', 'SMTP_USER', ''));
}

if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', (string)mailer_config_get($localConfig, 'MAILER_SMTP_PASS', 'SMTP_PASS', ''));
}

if (!defined('MAIL_FROM')) {
    $defaultFrom = SMTP_USER;
    define('MAIL_FROM', (string)mailer_config_get($localConfig, 'MAILER_MAIL_FROM', 'MAIL_FROM', $defaultFrom));
}

if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', (string)mailer_config_get($localConfig, 'MAILER_MAIL_FROM_NAME', 'MAIL_FROM_NAME', 'RF Dental Clinic'));
}

if (!function_exists('mailer_config_validate')) {
    function mailer_config_validate(): array
    {
        $errors = [];

        if (mailer_config_is_empty(SMTP_HOST)) {
            $errors[] = 'SMTP host is missing.';
        }

        if ((int)SMTP_PORT <= 0 || (int)SMTP_PORT > 65535) {
            $errors[] = 'SMTP port is invalid.';
        }

        if (mailer_config_is_empty(SMTP_USER)) {
            $errors[] = 'SMTP username is missing. Set MAILER_SMTP_USER or configure config/mailer_config.local.php.';
        }

        if (mailer_config_is_empty(SMTP_PASS)) {
            $errors[] = 'SMTP password is missing. Use a valid Gmail App Password or set MAILER_SMTP_PASS.';
        }

        if (!empty(MAIL_FROM) && !filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'MAIL_FROM is not a valid email address.';
        }

        return $errors;
    }
}

