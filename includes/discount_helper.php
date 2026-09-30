<?php
/**
 * Senior / PWD Discount Helper (RA 9994 - Expanded Senior Citizens Act, RA 10754 - PWD Act)
 *
 * Fixed 20% discount on dental services.
 * - Senior: government ID type + number recorded, and age >= 60 when DOB is known.
 *   Age alone does not grant the official VAT-exempt senior discount.
 * - PWD: official PWD ID number recorded (RA 10754). Other IDs do not substitute.
 * - If both qualify, SENIOR wins (discounts cannot be stacked)
 * - ID type + number + holder name are pulled from the patient profile onto the billing row
 */

if (!defined('SC_PWD_DISCOUNT_RATE')) {
    define('SC_PWD_DISCOUNT_RATE', 20.00);
}

/**
 * Accepted government ID types for official senior 20% (RA 9994).
 * Keys are stored in patients.senior_id_type.
 */
function seniorIdTypes(): array {
    return [
        'osca' => 'OSCA ID',
        'philid' => 'PhilSys / National ID',
        'passport' => 'Passport',
        'drivers_license' => "Driver's License",
        'umid' => 'UMID',
        'sss' => 'SSS ID',
        'gsis' => 'GSIS eCard',
        'prc' => 'PRC ID',
        'postal' => 'Postal ID',
        'voters' => "Voter's ID",
        'other' => 'Other government-issued ID',
    ];
}

function seniorIdTypeLabel(?string $type): string {
    $types = seniorIdTypes();
    $key = trim((string)$type);
    return $types[$key] ?? ($key !== '' ? $key : '');
}

/**
 * @return array{0:?string,1:?string} [type, number] or [null, null] if incomplete
 */
function normalizeSeniorIdFields(?string $type, ?string $number): array {
    $type = trim((string)$type);
    $number = trim((string)$number);
    if ($type === '' || $number === '' || !isset(seniorIdTypes()[$type])) {
        return [null, null];
    }
    return [$type, $number];
}

function normalizePwdIdField(bool $isPwd, ?string $pwdId): ?string {
    if (!$isPwd) {
        return null;
    }
    $pwdId = trim((string)$pwdId);
    return $pwdId !== '' ? $pwdId : null;
}

function formatDiscountIdNo(?string $type, ?string $number, string $kind = 'senior'): ?string {
    $number = trim((string)$number);
    if ($number === '') {
        return null;
    }
    if ($kind === 'pwd') {
        return 'PWD ' . $number;
    }
    $label = seniorIdTypeLabel($type);
    return trim($label . ' ' . $number);
}

function patientAgeYears(?string $dob): ?int {
    if (empty($dob)) {
        return null;
    }
    try {
        $d = new DateTime($dob);
        return (int)$d->diff(new DateTime('now'))->y;
    } catch (Exception $e) {
        return null;
    }
}

function seniorIdTypeOptionsHtml(?string $selected = null): string {
    $selected = trim((string)$selected);
    $html = '<option value="">Select ID type</option>';
    foreach (seniorIdTypes() as $key => $label) {
        $sel = ($selected === $key) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
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
        $col = $pdo->query("SHOW COLUMNS FROM patients LIKE 'senior_id_type'")->fetch();
        if (!$col) {
            $after = $pdo->query("SHOW COLUMNS FROM patients LIKE 'senior_citizen_id'")->fetch() ? ' AFTER senior_citizen_id' : '';
            $pdo->exec("ALTER TABLE patients ADD COLUMN senior_id_type VARCHAR(40) DEFAULT NULL" . $after);
        }
        $col = $pdo->query("SHOW COLUMNS FROM patients LIKE 'pwd_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE patients ADD COLUMN pwd_id VARCHAR(30) DEFAULT NULL AFTER senior_id_type");
        }

        $billingCols = [
            'subtotal_amount'      => "ALTER TABLE billing ADD COLUMN subtotal_amount DECIMAL(10,2) DEFAULT NULL AFTER total_amount",
            'discount_type'        => "ALTER TABLE billing ADD COLUMN discount_type ENUM('none','senior','pwd') NOT NULL DEFAULT 'none' AFTER subtotal_amount",
            'discount_rate'        => "ALTER TABLE billing ADD COLUMN discount_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER discount_type",
            'discount_amount'      => "ALTER TABLE billing ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_rate",
            'discount_id_no'       => "ALTER TABLE billing ADD COLUMN discount_id_no VARCHAR(80) DEFAULT NULL AFTER discount_amount",
            'discount_holder_name' => "ALTER TABLE billing ADD COLUMN discount_holder_name VARCHAR(150) DEFAULT NULL AFTER discount_id_no",
            'discount_reason'      => "ALTER TABLE billing ADD COLUMN discount_reason VARCHAR(255) DEFAULT NULL AFTER discount_holder_name",
        ];
        foreach ($billingCols as $colName => $ddl) {
            $col = $pdo->query("SHOW COLUMNS FROM billing LIKE '" . $colName . "'")->fetch();
            if (!$col) {
                $pdo->exec($ddl);
            }
        }
        $typeCol = $pdo->query("SHOW COLUMNS FROM billing LIKE 'discount_type'")->fetch(PDO::FETCH_ASSOC);
        if ($typeCol && stripos((string)$typeCol['Type'], "'custom'") === false) {
            $pdo->exec("ALTER TABLE billing MODIFY COLUMN discount_type ENUM('none','senior','pwd','custom') NOT NULL DEFAULT 'none'");
        }
    } catch (Exception $e) {
        error_log('Discount schema ensure error: ' . $e->getMessage());
    }
}

/**
 * Get discount eligibility for a patient.
 * Returns ['eligible'=>bool, 'type'=>'senior'|'pwd'|null, 'rate'=>float,
 *          'label'=>string, 'id_no'=>?string, 'holder_name'=>string,
 *          'age'=>?int, 'ineligible_reason'=>string]
 */
function getPatientDiscount(PDO $pdo, int $patientId): array {
    ensureDiscountSchema($pdo);

    $result = [
        'eligible'          => false,
        'type'              => null,
        'rate'              => 0.0,
        'label'             => '',
        'id_no'             => null,
        'holder_name'       => '',
        'age'               => null,
        'ineligible_reason' => '',
    ];

    try {
        $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, suffix, date_of_birth, senior_citizen_id, senior_id_type, pwd_id
            FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            $result['ineligible_reason'] = 'Patient not found';
            return $result;
        }

        $name = trim($p['first_name'] . ' ' . ($p['middle_name'] ? $p['middle_name'] . ' ' : '') . $p['last_name']
            . ($p['suffix'] ? ' ' . $p['suffix'] : ''));
        $result['holder_name'] = $name;

        $age = patientAgeYears($p['date_of_birth'] ?? null);
        $result['age'] = $age;

        $idNumber = trim((string)($p['senior_citizen_id'] ?? ''));
        $idType = trim((string)($p['senior_id_type'] ?? ''));
        // Legacy rows saved an OSCA number before senior_id_type existed.
        if ($idNumber !== '' && $idType === '') {
            $idType = 'osca';
        }

        $hasSeniorId = $idNumber !== '' && isset(seniorIdTypes()[$idType]);
        $ageOkForSenior = ($age === null) ? $hasSeniorId : ($age >= 60);

        if ($hasSeniorId && $ageOkForSenior) {
            $result['eligible'] = true;
            $result['type']     = 'senior';
            $result['label']    = 'Senior Citizen';
            $result['id_no']    = formatDiscountIdNo($idType, $idNumber, 'senior');
        } elseif (!empty($p['pwd_id'])) {
            $result['eligible'] = true;
            $result['type']     = 'pwd';
            $result['label']    = 'PWD';
            $result['id_no']    = formatDiscountIdNo(null, $p['pwd_id'], 'pwd');
        } else {
            if ($age !== null && $age >= 60) {
                $result['ineligible_reason'] = 'Patient is 60+ but no government ID is on file. Record OSCA or another government-issued ID to apply the official 20% senior discount.';
            } else {
                $result['ineligible_reason'] = 'Patient is not eligible for Senior/PWD discount. Seniors need a government ID on file (age 60+). PWD discount requires a PWD ID number.';
            }
        }

        $result['rate'] = $result['eligible'] ? SC_PWD_DISCOUNT_RATE : 0.0;
    } catch (Exception $e) {
        error_log('getPatientDiscount error: ' . $e->getMessage());
        $result['ineligible_reason'] = 'Unable to check discount eligibility';
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
