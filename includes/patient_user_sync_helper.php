<?php
/**
 * Patient <-> User Sync Helper
 *
 * A patient exists in two tables:
 *   - users    (login account, shown in User Management)
 *   - patients (clinical record, shown in Patient Records)
 * linked by users.patient_id.
 *
 * This helper keeps the shared identity fields of both rows in sync and
 * archives/restores them as ONE unit so a person only ever appears once
 * in the archive.
 *
 * Shared identity fields: first_name, middle_name, last_name, email,
 * phone, gender, date_of_birth
 */

if (!function_exists('pusync_tableColumn')) {
    function pusync_tableColumn(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        return $cache[$key] = (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('pusync_sharedUserFields')) {
    /** Identity columns present on BOTH tables. */
    function pusync_sharedUserFields(): array
    {
        return ['first_name', 'middle_name', 'last_name', 'email', 'phone', 'gender', 'date_of_birth'];
    }
}

if (!function_exists('getLinkedPatientId')) {
    /**
     * Resolve the patients.id linked to a user account.
     * Uses the users.patient_id FK; falls back to a unique email match
     * for legacy accounts that were never linked (and links them).
     */
    function getLinkedPatientId(PDO $pdo, int $userId): ?int
    {
        $stmt = $pdo->prepare("SELECT patient_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $patientId = $stmt->fetchColumn();

        if ($patientId) {
            return (int)$patientId;
        }

        // Legacy fallback: match by email (only if it maps to exactly one active patient)
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $email = trim((string)$stmt->fetchColumn());

        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ? LIMIT 2");
            $stmt->execute([$email]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($rows) === 1) {
                $patientId = (int)$rows[0];
                if (pusync_tableColumn($pdo, 'users', 'patient_id')) {
                    $pdo->prepare("UPDATE users SET patient_id = ? WHERE id = ? AND (patient_id IS NULL OR patient_id = 0)")
                        ->execute([$patientId, $userId]);
                }
                return $patientId;
            }
        }

        return null;
    }
}

if (!function_exists('getLinkedUserId')) {
    /** Resolve the users.id linked to a patient record (via users.patient_id FK). */
    function getLinkedUserId(PDO $pdo, int $patientId): ?int
    {
        if (!pusync_tableColumn($pdo, 'users', 'patient_id')) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT id FROM users WHERE patient_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$patientId]);
        $userId = $stmt->fetchColumn();
        return $userId ? (int)$userId : null;
    }
}

if (!function_exists('syncPatientToUser')) {
    /**
     * Push identity fields from the patients row to the linked users row.
     * Call after the PATIENT edits their own profile so User Management
     * immediately reflects the same information.
     */
    function syncPatientToUser(PDO $pdo, int $patientId): bool
    {
        if (!pusync_tableColumn($pdo, 'users', 'patient_id')) {
            return false;
        }

        $fields = array_values(array_filter(pusync_sharedUserFields(), function ($f) use ($pdo) {
            return pusync_tableColumn($pdo, 'patients', $f) && pusync_tableColumn($pdo, 'users', $f);
        }));
        if (!$fields) {
            return false;
        }

        // Normalize gender to the users enum values (Male/Female); drop others.
        $selectFields = $fields;
        $hasGender = in_array('gender', $fields, true);
        if ($hasGender) {
            $selectFields = array_map(function ($f) {
                return $f === 'gender' ? "CASE WHEN LOWER(COALESCE(gender,'')) = 'male' THEN 'Male' WHEN LOWER(COALESCE(gender,'')) = 'female' THEN 'Female' ELSE NULL END AS gender" : $f;
            }, $fields);
        }

        $stmt = $pdo->prepare("SELECT " . implode(', ', $selectFields) . " FROM patients WHERE id = ? LIMIT 1");
        $stmt->execute([$patientId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            return false;
        }
        if ($hasGender && !in_array($data['gender'] ?? null, ['Male', 'Female'], true)) {
            unset($data['gender']);
        }

        $userSet = [];
        foreach (array_keys($data) as $f) {
            $userSet[] = "$f = :" . $f;
        }
        $sql = "UPDATE users u JOIN (" .
            "SELECT id FROM users WHERE patient_id = :pid ORDER BY id ASC LIMIT 1" .
            ") link ON u.id = link.id SET " . implode(', ', $userSet);

        $stmt = $pdo->prepare($sql);
        foreach ($data as $f => $v) {
            $stmt->bindValue(':' . $f, $v);
        }
        $stmt->bindValue(':pid', $patientId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}

if (!function_exists('syncUserToPatient')) {
    /**
     * Push identity fields from the users row to the linked patients row.
     * Call after an ADMIN edits a patient account in User Management so
     * Patient Records shows the same information.
     */
    function syncUserToPatient(PDO $pdo, int $userId): bool
    {
        $patientId = getLinkedPatientId($pdo, $userId);
        if (!$patientId) {
            return false;
        }

        $fields = array_values(array_filter(pusync_sharedUserFields(), function ($f) use ($pdo) {
            return pusync_tableColumn($pdo, 'patients', $f) && pusync_tableColumn($pdo, 'users', $f);
        }));
        if (!$fields) {
            return false;
        }

        $hasGender = in_array('gender', $fields, true);
        $selectFields = array_map(function ($f) use ($hasGender) {
            if ($f === 'gender' && $hasGender) {
                return "CASE WHEN LOWER(COALESCE(gender,'')) = 'male' THEN 'Male' WHEN LOWER(COALESCE(gender,'')) = 'female' THEN 'Female' ELSE NULL END AS gender";
            }
            return $f;
        }, $fields);

        $stmt = $pdo->prepare("SELECT " . implode(', ', $selectFields) . " FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            return false;
        }
        if ($hasGender && !in_array($data['gender'] ?? null, ['Male', 'Female'], true)) {
            unset($data['gender']);
        }

        $set = [];
        foreach (array_keys($data) as $f) {
            $set[] = "`$f` = :$f";
        }
        $set[] = "updated_at = NOW()";

        $sql = "UPDATE patients SET " . implode(', ', $set) . " WHERE id = :pid";
        $stmt = $pdo->prepare($sql);
        foreach ($data as $f => $v) {
            $stmt->bindValue(':' . $f, $v);
        }
        $stmt->bindValue(':pid', $patientId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}

if (!function_exists('archivePatientWithUser')) {
    /**
     * Archive or restore a patient TOGETHER with its linked user account
     * so the person appears exactly once in the archive.
     */
    function archivePatientWithUser(PDO $pdo, int $patientId, bool $archived): void
    {
        if ($archived) {
            $pdo->prepare("UPDATE patients SET is_archived = 1, deleted_at = NOW() WHERE id = ?")
                ->execute([$patientId]);
        } else {
            $pdo->prepare("UPDATE patients SET is_archived = 0, deleted_at = NULL WHERE id = ?")
                ->execute([$patientId]);
        }

        if (pusync_tableColumn($pdo, 'users', 'patient_id')) {
            $hasArchivedAt = pusync_tableColumn($pdo, 'users', 'archived_at');
            $userSet = $archived
                ? ($hasArchivedAt ? "is_archived = 1, archived_at = NOW()" : "is_archived = 1")
                : ($hasArchivedAt ? "is_archived = 0, archived_at = NULL" : "is_archived = 0");
            $pdo->prepare("UPDATE users SET $userSet WHERE patient_id = ?")->execute([$patientId]);
        } elseif (pusync_tableColumn($pdo, 'patients', 'user_id')) {
            $hasArchivedAt = pusync_tableColumn($pdo, 'users', 'archived_at');
            $userSet = $archived
                ? ($hasArchivedAt ? "is_archived = 1, archived_at = NOW()" : "is_archived = 1")
                : ($hasArchivedAt ? "is_archived = 0, archived_at = NULL" : "is_archived = 0");
            $pdo->prepare("UPDATE users SET $userSet WHERE id = (SELECT user_id FROM patients WHERE id = ? LIMIT 1)")->execute([$patientId]);
        }
    }
}

if (!function_exists('archiveUserWithPatient')) {
    /**
     * Archive or restore a user account TOGETHER with its linked patient
     * record so the person appears exactly once in the archive.
     */
    function archiveUserWithPatient(PDO $pdo, int $userId, bool $archived): void
    {
        $hasArchivedAt = pusync_tableColumn($pdo, 'users', 'archived_at');
        $userSet = $archived
            ? ($hasArchivedAt ? "is_archived = 1, archived_at = NOW()" : "is_archived = 1")
            : ($hasArchivedAt ? "is_archived = 0, archived_at = NULL" : "is_archived = 0");
        $pdo->prepare("UPDATE users SET $userSet WHERE id = ?")->execute([$userId]);

        $patientId = getLinkedPatientId($pdo, $userId);
        if ($patientId) {
            if ($archived) {
                $pdo->prepare("UPDATE patients SET is_archived = 1, deleted_at = NOW() WHERE id = ?")
                    ->execute([$patientId]);
            } else {
                $pdo->prepare("UPDATE patients SET is_archived = 0, deleted_at = NULL WHERE id = ?")
                    ->execute([$patientId]);
            }
        }
    }
}
