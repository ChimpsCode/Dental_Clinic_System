<?php
/**
 * TOTP (RFC 6238) helpers for authenticator apps such as Google Authenticator.
 *
 * Secrets are stored and exchanged as RFC 4648 base32 without padding, which is
 * the format authenticator apps expect for manual "setup key" entry.
 */

const TOTP_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;

function totpBase32Encode(string $bytes): string
{
    if ($bytes === '') {
        return '';
    }

    $bits = '';
    for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }

    $bits = str_pad($bits, (int)(ceil(strlen($bits) / 5) * 5), '0', STR_PAD_RIGHT);

    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
        $encoded .= TOTP_ALPHABET[bindec($chunk)];
    }

    return $encoded;
}

function totpBase32Decode(string $secret): string
{
    $secret = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret) ?? '');
    if ($secret === '') {
        return '';
    }

    $bits = '';
    for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
        $index = strpos(TOTP_ALPHABET, $secret[$i]);
        if ($index === false) {
            continue;
        }
        $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
    }

    $bytes = '';
    foreach (str_split($bits, 8) as $chunk) {
        // Trailing bits that do not form a whole byte are padding.
        if (strlen($chunk) === 8) {
            $bytes .= chr(bindec($chunk));
        }
    }

    return $bytes;
}

/**
 * Generate a new base32 secret. 20 random bytes = 160 bits = 32 base32 chars,
 * which is the size recommended by RFC 4226 for HMAC-SHA1.
 */
function totpGenerateSecret(int $byteLength = 20): string
{
    return totpBase32Encode(random_bytes(max(10, $byteLength)));
}

function totpIsValidSecret(?string $secret): bool
{
    if ($secret === null) {
        return false;
    }
    return strlen(totpBase32Decode($secret)) >= 10;
}

/**
 * Compute the TOTP code for a given 30-second counter window.
 */
function totpCodeAt(string $secret, int $counter): string
{
    $key = totpBase32Decode($secret);
    if ($key === '') {
        return '';
    }

    $binCounter = pack('J', $counter); // 64-bit big-endian
    $hash = hash_hmac('sha1', $binCounter, $key, true);

    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);

    $code = $truncated % (10 ** TOTP_DIGITS);

    return str_pad((string)$code, TOTP_DIGITS, '0', STR_PAD_LEFT);
}

function totpCurrentCode(string $secret, ?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    return totpCodeAt($secret, (int)floor($timestamp / TOTP_PERIOD));
}

/**
 * Verify a user-supplied code, tolerating clock drift of $window periods
 * either side of the current window.
 *
 * @return bool
 */
function totpVerify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== TOTP_DIGITS || !totpIsValidSecret($secret)) {
        return false;
    }

    $timestamp = $timestamp ?? time();
    $current = (int)floor($timestamp / TOTP_PERIOD);
    $window = max(0, min(10, $window));

    for ($offset = -$window; $offset <= $window; $offset++) {
        if (hash_equals(totpCodeAt($secret, $current + $offset), $code)) {
            return true;
        }
    }

    return false;
}

/**
 * Build the otpauth:// URI that gets encoded into the enrollment QR code.
 */
function totpProvisioningUri(string $secret, string $accountName, string $issuer): string
{
    $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);

    $params = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => TOTP_DIGITS,
        'period' => TOTP_PERIOD,
    ], '', '&', PHP_QUERY_RFC3986);

    return 'otpauth://totp/' . $label . '?' . $params;
}

/**
 * Format a secret in space-separated groups of 4 so it is easier to type by hand.
 */
function totpFormatSecretForDisplay(string $secret): string
{
    return trim(implode(' ', str_split($secret, 4)));
}
