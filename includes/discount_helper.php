<?php
/**
 * Senior / PWD Discount Helper (RA 9994 - Expanded Senior Citizens Act, RA 10754 - PWD Act)
 *
 * Fixed 20% discount on dental services.
 * - Senior: age >= 60 (from date_of_birth) OR a senior citizen (OSCA) ID is saved
 * - PWD: a PWD ID is saved
 * - If both qualify, SENIOR wins (discounts cannot be stacked)
 * - ID number + holder name are pulled from the patient profile onto the billing row
 */

if (!defined('SC_PWD_DISCOUNT_RATE')) {
    define('SC_PWD_DISCOUNT_RATE', 20.00);
}

/**
 * Ensure patients + billing discount columns exist (runtime auto-migration).
 */
function ensureDiscountSchema(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;
    $ready = true;

    try {
        $col = $pdo->query("SHOW COLUMNS FROM patients LIKE 'senior_citizen_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE patients ADD COLUMN senior_citizen_id VARCHAR(30) DEFAULT NULL AFTER date_of_birth");
        }
        $col = $pdo->query("SHOW COLUMNS FROM patients LIKE 'pwd_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE patients ADD COLUMN pwd_id VARCHAR(30) DEFAULT NULL AFTER senior_citizen_id");
        }

        $billingCols = [
            'subtotal_amount'      => "ALTER TABLE billing ADD COLUMN subtotal_amount DECIMAL(10,2) DEFAULT NULL AFTER total_amount",
            'discount_type'        => "ALTER TABLE billing ADD COLUMN discount_type ENUM('none','senior','pwd') NOT NULL DEFAULT 'none' AFTER subtotal_amount",
            'discount_rate'        => "ALTER TABLE billing ADD COLUMN discount_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER discount_type",
            'discount_amount'      => "ALTER TABLE billing ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_rate",
            'discount_id_no'       => "ALTER TABLE billing ADD COLUMN discount_id_no VARCHAR(50) DEFAULT NULL AFTER discount_amount",
            'discount_holder_name' => "ALTER TABLE billing ADD COLUMN discount_holder_name VARCHAR(150) DEFAULT NULL AFTER discount_id_no",
        ];
        foreach ($billingCols as $colName => $ddl) {
            $col = $pdo->query("SHOW COLUMNS FROM billing LIKE '" . $colName . "'")->fetch();
            if (!$col) {
                $pdo->exec($ddl);
            }
        }
    } catch (Exception $e) {
        error_log('Discount schema ensure error: ' . $e->getMessage());
    }
}

/**
 * Get discount eligibility for a patient.
 * Returns ['eligible'=>bool, 'type'=>'senior'|'pwd'|null, 'rate'=>float,
 *          'label'=>string, 'id_no'=>?string, 'holder_name'=>string]
 */
function getPatientDiscount(PDO $pdo, int $patientId): array {
    ensureDiscountSchema($pdo);

    $result = [
        'eligible'    => false,
        'type'        => null,
        'rate'        => 0.0,
        'label'       => '',
        'id_no'       => null,
        'holder_name' => '',
    ];

    try {
        $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, suffix, date_of_birth, senior_citizen_id, pwd_id
            FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) return $result;

        $name = trim($p['first_name'] . ' ' . ($p['middle_name'] ? $p['middle_name'] . ' ' : '') . $p['last_name']
            . ($p['suffix'] ? ' ' . $p['suffix'] : ''));
        $result['holder_name'] = $name;

        // Senior: OSCA ID present OR age >= 60
        $isSenior = false;
        if (!empty($p['senior_citizen_id'])) {
            $isSenior = true;
        } elseif (!empty($p['date_of_birth'])) {
            $dob = new DateTime($p['date_of_birth']);
            $age = (int)$dob->diff(new DateTime('now'))->y;
            if ($age >= 60) $isSenior = true;
        }

        if ($isSenior) {
            $result['eligible'] = true;
            $result['type']     = 'senior';
            $result['label']    = 'Senior Citizen';
            $result['id_no']    = !empty($p['senior_citizen_id']) ? $p['senior_citizen_id'] : null;
        } elseif (!empty($p['pwd_id'])) {
            $result['eligible'] = true;
            $result['type']     = 'pwd';
            $result['label']    = 'PWD';
            $result['id_no']    = $p['pwd_id'];
        }

        $result['rate'] = $result['eligible'] ? SC_PWD_DISCOUNT_RATE : 0.0;
    } catch (Exception $e) {
        error_log('getPatientDiscount error: ' . $e->getMessage());
    }

    return $result;
}

/**
 * Compute discounted totals for an amount.
 * Returns ['subtotal'=>f, 'discount_amount'=>f, 'total'=>f] (total = payable).
 */
function computeDiscountedTotals(float $amount, array $discount): array {
    $amount = round($amount, 2);
    if (empty($discount['eligible']) || $amount <= 0) {
        return ['subtotal' => $amount, 'discount_amount' => 0.0, 'total' => max(0.0, $amount)];
    }
    $discountAmount = round($amount * ($discount['rate'] / 100), 2);
    if ($discountAmount > $amount) $discountAmount = $amount;
    return [
        'subtotal'        => $amount,
        'discount_amount' => $discountAmount,
        'total'           => round($amount - $discountAmount, 2),
    ];
}

/**
 * Log a discount grant/removal to audit_logs (best-effort; never throws).
 */
function logDiscountAudit(PDO $pdo, string $actionType, int $billingId, string $details): void {
    try {
        if (!function_exists('logAudit')) {
            require_once __DIR__ . '/audit_helper.php';
        }
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        logAudit(
            $pdo,
            $userId,
            $_SESSION['username'] ?? 'system',
            $_SESSION['role'] ?? 'system',
            'discount',
            'Billing',
            $details,
            $billingId,
            'billing'
        );
    } catch (Exception $e) {
        error_log('logDiscountAudit error: ' . $e->getMessage());
    }
}
