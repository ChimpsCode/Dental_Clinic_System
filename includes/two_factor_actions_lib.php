<?php

require_once __DIR__ . '/../lib/security/totp_lib.php';
require_once __DIR__ . '/../lib/security/qrcode_lib.php';

function fetchUserTwoFactorState(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT two_factor_enabled, two_factor_secret FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['enabled' => false, 'secret' => null];
    }

    return [
        'enabled' => (int)($row['two_factor_enabled'] ?? 0) === 1,
        'secret' => $row['two_factor_secret'] ?? null,
    ];
}

function getTwoFactorIssuer(PDO $pdo): string
{
    $issuer = 'RF Dental Clinic';
    try {
        $issuerStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'clinic_name' LIMIT 1");
        $issuerStmt->execute();
        $clinicName = trim((string)$issuerStmt->fetchColumn());
        if ($clinicName !== '') {
            $issuer = $clinicName;
        }
    } catch (Throwable $e) {
        // Use default issuer.
    }

    return $issuer;
}

/**
 * Handle a 2FA action for the authenticated user.
 *
 * @return array|null Response payload, or null when the action is not a 2FA action.
 */
function handleTwoFactorAction(
    PDO $pdo,
    string $action,
    int $userId,
    string $username,
    string $role,
    array $options = []
): ?array {
    $syncGlobalSetting = (bool)($options['sync_global_setting'] ?? false);
    $auditCallback = $options['audit_callback'] ?? null;

    if ($action === 'get_2fa_status') {
        $state = fetchUserTwoFactorState($pdo, $userId);
        return [
            'success' => true,
            'enabled' => $state['enabled'],
            'has_secret' => totpIsValidSecret($state['secret']),
        ];
    }

    if ($action === 'start_2fa_setup') {
        $state = fetchUserTwoFactorState($pdo, $userId);
        if ($state['enabled']) {
            return ['success' => false, 'message' => 'Two-factor authentication is already enabled.'];
        }

        $secret = totpGenerateSecret();
        $_SESSION['pending_2fa_secret'] = $secret;
        $_SESSION['pending_2fa_started_at'] = time();

        $accountName = trim((string)($_SESSION['email'] ?? ''));
        if ($accountName === '') {
            $accountName = (string)($_SESSION['username'] ?? $username);
        }

        $otpauthUri = totpProvisioningUri($secret, $accountName, getTwoFactorIssuer($pdo));

        try {
            $qrSvg = qrCodeSvg($otpauthUri, 'M', 6, 4);
            $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
        } catch (QrCodeException $e) {
            unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);
            return ['success' => false, 'message' => 'Could not generate QR code. Please try again.'];
        }

        return [
            'success' => true,
            'secret' => $secret,
            'secret_display' => totpFormatSecretForDisplay($secret),
            'otpauth_uri' => $otpauthUri,
            'qr_svg' => $qrSvg,
            'qr_data_uri' => $qrDataUri,
            'account' => $accountName,
            'issuer' => getTwoFactorIssuer($pdo),
        ];
    }

    if ($action === 'confirm_2fa_setup') {
        $code = trim((string)($_POST['code'] ?? ''));
        $pendingSecret = (string)($_SESSION['pending_2fa_secret'] ?? '');
        $startedAt = (int)($_SESSION['pending_2fa_started_at'] ?? 0);

        if ($pendingSecret === '' || $startedAt === 0) {
            return ['success' => false, 'message' => 'No setup in progress. Please start again.'];
        }

        if ((time() - $startedAt) > 900) {
            unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);
            return ['success' => false, 'message' => 'Setup expired. Please start again.'];
        }

        if (!totpVerify($pendingSecret, $code, 1)) {
            return ['success' => false, 'message' => 'Invalid verification code. Please try again.'];
        }

        $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ? LIMIT 1");
        $stmt->execute([$pendingSecret, $userId]);

        if ($syncGlobalSetting && is_callable($auditCallback)) {
            $auditCallback('enable');
        }

        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);

        return ['success' => true, 'message' => 'Two-factor authentication enabled.'];
    }

    if ($action === 'cancel_2fa_setup') {
        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);
        return ['success' => true];
    }

    if ($action === 'disable_2fa') {
        $state = fetchUserTwoFactorState($pdo, $userId);
        if (!$state['enabled']) {
            return ['success' => true, 'message' => 'Two-factor authentication is already disabled.'];
        }

        $code = trim((string)($_POST['code'] ?? ''));
        if (!totpIsValidSecret($state['secret']) || !totpVerify((string)$state['secret'], $code, 1)) {
            return ['success' => false, 'message' => 'Enter a valid authenticator code to disable 2FA.'];
        }

        $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);

        if ($syncGlobalSetting && is_callable($auditCallback)) {
            $auditCallback('disable');
        }

        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);

        return ['success' => true, 'message' => 'Two-factor authentication disabled.'];
    }

    return null;
}
