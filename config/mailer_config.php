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
    define('MAIL_FROM', (string)mailer_config_get($localConfig, 'MAILER_MAIL_FROM', 'MAIL_FROM', SMTP_USER));
}

if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', (string)mailer_config_get($localConfig, 'MAILER_MAIL_FROM_NAME', 'MAIL_FROM_NAME', 'RF Dental Clinic'));
}

