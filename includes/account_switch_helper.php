<?php
function switchableClinicRole(?string $role): ?string {
    $role = strtolower(trim((string)$role));
    if ($role === 'administrator') $role = 'admin';
    return in_array($role, ['admin', 'dentist'], true) ? $role : null;
}

function clinicAccountDashboard(string $role): string {
    return match ($role) {
        'admin' => '../admin/admin_dashboard.php',
        'dentist' => '../dentist/dentist_dashboard.php',
        default => throw new InvalidArgumentException('This account cannot use clinic account switching.'),
    };
}

// New policy: only Admin and Dentist accounts. Earlier links may include Staff.
const CLINIC_SWITCH_SETTING = 'clinic_switch_links_v2';

function clinicSwitchLinks(PDO $pdo, bool $lock = false): array {
    $sql = 'SELECT setting_value FROM settings WHERE setting_key = ?';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([CLINIC_SWITCH_SETTING]);
    $decoded = json_decode((string)$stmt->fetchColumn(), true);
    if (!is_array($decoded)) return [];
    $links = [];
    foreach ($decoded as $adminId => $ids) {
        if ((int)$adminId <= 0 || !is_array($ids)) continue;
        $links[(int)$adminId] = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0 && $id !== (int)$adminId)));
    }
    return $links;
}

function clinicSwitchTargets(PDO $pdo, int $userId): array {
    if ($userId <= 0) return [];
    $sourceStmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND COALESCE(is_archived, 0) = 0 AND (status IS NULL OR LOWER(status) = 'active') LIMIT 1");
    $sourceStmt->execute([$userId]);
    if (!switchableClinicRole($sourceStmt->fetchColumn() ?: null)) return [];
    $ids = [];
    foreach (clinicSwitchLinks($pdo) as $adminId => $linked) {
        $group = array_merge([$adminId], $linked);
        if (!in_array($userId, $group, true)) continue;
        $adminStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND LOWER(role) = 'admin' AND COALESCE(is_archived, 0) = 0 AND (status IS NULL OR LOWER(status) = 'active') LIMIT 1");
        $adminStmt->execute([$adminId]);
        if (!$adminStmt->fetchColumn()) continue;
        $ids = array_merge($ids, $group);
    }
    $ids = array_values(array_diff(array_unique($ids), [$userId]));
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, role, profile_picture FROM users WHERE id IN ($placeholders) AND COALESCE(is_archived, 0) = 0 AND (status IS NULL OR LOWER(status) = 'active')");
    $stmt->execute($ids);
    return array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), static fn($row) => switchableClinicRole($row['role'] ?? null) !== null));
}

function clinicSwitchAuthorized(PDO $pdo, int $fromId, int $toId): bool {
    foreach (clinicSwitchTargets($pdo, $fromId) as $target) {
        if ((int)$target['id'] === $toId) return true;
    }
    return false;
}

function completeClinicAccountSwitch(PDO $pdo, array $target): string {
    if (!switchableClinicRole($_SESSION['role'] ?? null)) {
        throw new RuntimeException('This account cannot use clinic account switching.');
    }
    $role = switchableClinicRole($target['role'] ?? null);
    if ($role === null || (int)($target['is_archived'] ?? 0) !== 0
        || strcasecmp((string)($target['status'] ?? 'Active'), 'Active') !== 0) {
        throw new RuntimeException('This clinic account is unavailable.');
    }
    $fromId = (int)($_SESSION['user_id'] ?? 0);
    $fromName = (string)($_SESSION['username'] ?? 'unknown');
    $fromRole = (string)($_SESSION['role'] ?? 'unknown');
    if (!session_regenerate_id(true)) throw new RuntimeException('Could not refresh the session. Please try again.');
    $_SESSION = [];
    $_SESSION['user_id'] = (int)$target['id'];
    $_SESSION['username'] = (string)$target['username'];
    $_SESSION['email'] = (string)($target['email'] ?? '');
    $_SESSION['profile_picture'] = $target['profile_picture'] ?? null;
    $_SESSION['role'] = $role;
    $_SESSION['user_role'] = $role;
    $_SESSION['auth_version'] = (int)($target['auth_version'] ?? 0);
    $_SESSION['user_name'] = trim((string)($target['first_name'] ?? ''));
    $first = trim(explode(' ', $_SESSION['user_name'])[0]);
    $_SESSION['display_name'] = $role === 'dentist' ? 'Dr. ' . ($first ?: 'Dentist') : ($first ?: ucfirst($role));
    $_SESSION['first_login'] = (int)($target['first_login'] ?? 0);
    $_SESSION['clinic_switch_csrf'] = bin2hex(random_bytes(32));
    if (!function_exists('logAudit')) require_once __DIR__ . '/audit_helper.php';
    logAudit($pdo, (int)$target['id'], (string)$target['username'], $role, 'login', 'users',
        "Account switch from {$fromRole} account {$fromName} (ID {$fromId}) to {$role} account (ID {$target['id']})");
    return clinicAccountDashboard($role);
}
