<?php
/**
 * Caps live Patient Records at 5 pages (7 rows each).
 * Extra eligible patients are moved to Patient Logs (not Archive).
 * Unpaid / partial / overdue patients are skipped. User accounts stay active.
 */

require_once __DIR__ . '/patient_user_sync_helper.php';

if (!defined('PATIENT_LIST_PER_PAGE')) {
    define('PATIENT_LIST_PER_PAGE', 7);
}
if (!defined('PATIENT_LIST_MAX_PAGES')) {
    define('PATIENT_LIST_MAX_PAGES', 5);
}
if (!defined('PATIENT_LIST_MAX_ACTIVE')) {
    define('PATIENT_LIST_MAX_ACTIVE', PATIENT_LIST_PER_PAGE * PATIENT_LIST_MAX_PAGES);
}

function ensurePatientLogColumn(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $col = $pdo->query("SHOW COLUMNS FROM patients LIKE 'in_patient_log'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE patients ADD COLUMN in_patient_log TINYINT(1) NOT NULL DEFAULT 0");
        }
        $done = true;
    } catch (Exception $e) {
        error_log('Patient log column ensure error: ' . $e->getMessage());
    }
}

function patientOpenBalanceSql(string $patientIdExpr = 'p.id'): string
{
    return "
        EXISTS (
            SELECT 1 FROM billing x
            WHERE x.patient_id = {$patientIdExpr}
              AND LOWER(COALESCE(x.payment_status, '')) NOT IN ('paid', 'cancelled', 'canceled', 'voided')
              AND COALESCE(x.balance, 0) > 0
        )
    ";
}

function countLivePatients(PDO $pdo): int
{
    try {
        ensurePatientLogColumn($pdo);
        return (int)$pdo->query("
            SELECT COUNT(*) FROM patients
            WHERE COALESCE(is_archived, 0) = 0
              AND COALESCE(in_patient_log, 0) = 0
        ")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function moveOldestLivePatientsToLog(PDO $pdo, int $limit, bool $paidUpOnly, array $excludeIds = []): int
{
    if ($limit <= 0) {
        return 0;
    }
    $limit = (int)$limit;

    ensurePatientLogColumn($pdo);

    $where = "
        WHERE COALESCE(p.is_archived, 0) = 0
          AND COALESCE(p.in_patient_log, 0) = 0
    ";
    if ($paidUpOnly) {
        $where .= ' AND NOT (' . patientOpenBalanceSql('p.id') . ')';
    }

    $params = [];
    $excludeIds = array_values(array_unique(array_filter(array_map('intval', $excludeIds))));
    if ($excludeIds) {
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $where .= " AND p.id NOT IN ({$placeholders})";
        $params = $excludeIds;
    }

    $select = $pdo->prepare("
        SELECT p.id
        FROM patients p
        {$where}
        ORDER BY p.created_at ASC, p.id ASC
        LIMIT {$limit}
    ");
    $select->execute($params);
    $ids = $select->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) {
        return 0;
    }

    $moved = 0;
    $update = $pdo->prepare("UPDATE patients SET in_patient_log = 1 WHERE id = ?");
    foreach ($ids as $id) {
        $update->execute([(int)$id]);
        $moved++;
    }
    return $moved;
}

function overflowPatientRecordsIfNeeded(PDO $pdo, array $excludeIds = []): int
{
    $excess = countLivePatients($pdo) - PATIENT_LIST_MAX_ACTIVE;
    if ($excess <= 0) {
        return 0;
    }

    return moveOldestLivePatientsToLog($pdo, $excess, true, $excludeIds);
}

function runPatientListOverflow(PDO $pdo, array $excludeIds = []): void
{
    try {
        overflowPatientRecordsIfNeeded($pdo, $excludeIds);
    } catch (Throwable $e) {
        error_log('Patient list overflow: ' . $e->getMessage());
    }
}

function countArchivedPatientsNeededForRestore(PDO $pdo, array $patientIds = [], array $userIds = []): array
{
    $neededIds = [];
    foreach ($patientIds as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
            $neededIds[$pid] = true;
        }
    }
    foreach ($userIds as $uid) {
        $linked = getLinkedPatientId($pdo, (int)$uid);
        if ($linked) {
            $neededIds[(int)$linked] = true;
        }
    }
    if (!$neededIds) {
        return [];
    }

    $ids = array_keys($neededIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT id FROM patients
        WHERE id IN ({$placeholders}) AND COALESCE(is_archived, 0) = 1
    ");
    $stmt->execute($ids);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function makeRoomForPatientRestore(PDO $pdo, array $patientIds = [], array $userIds = []): void
{
    $neededIds = countArchivedPatientsNeededForRestore($pdo, $patientIds, $userIds);
    $needed = count($neededIds);
    if ($needed <= 0) {
        return;
    }

    $excess = (countLivePatients($pdo) + $needed) - PATIENT_LIST_MAX_ACTIVE;
    if ($excess <= 0) {
        return;
    }

    $moved = moveOldestLivePatientsToLog($pdo, $excess, true, $neededIds);
    if ($moved < $excess) {
        moveOldestLivePatientsToLog($pdo, $excess - $moved, false, $neededIds);
    }
}

function clearPatientLogFlag(PDO $pdo, int $patientId): void
{
    if ($patientId <= 0) {
        return;
    }
    ensurePatientLogColumn($pdo);
    $pdo->prepare("UPDATE patients SET in_patient_log = 0 WHERE id = ?")->execute([$patientId]);
}
