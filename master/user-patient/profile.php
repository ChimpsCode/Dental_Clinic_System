<?php
ob_start();

require_once __DIR__ . '/../../includes/session_bootstrap.php';

if (!empty($GLOBALS['APP_SESSION_EXPIRED'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php?expired=1');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$patient_id = $_SESSION['patient_id'] ?? null;
$message = '';
$messageType = '';

require_once '../../config/database.php';
require_once '../../includes/audit_helper.php';
require_once '../../includes/discount_helper.php';
ensureDiscountSchema($pdo);
require_once '../../includes/patient_session_helper.php';
require_once '../../includes/patient_user_sync_helper.php';
$patientSessionSettings = getPatientSessionSettings($pdo, (int)$user_id);

$profileUserPatientJoin = 'LEFT JOIN patients p ON p.user_id = u.id';
$profilePatientUserJoin = 'LEFT JOIN users u ON u.id = p.user_id';
$profileAddressSelect = 'p.street_address as street_address';
$profilePictureSelect = 'u.profile_picture';
try {
    $usersColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    $patientsColumns = $pdo->query('SHOW COLUMNS FROM patients')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('profile_picture', $usersColumns, true)) {
        $profilePictureSelect = 'NULL as profile_picture';
    }
    if (!in_array('street_address', $patientsColumns, true) && in_array('address', $patientsColumns, true)) {
        $profileAddressSelect = 'p.address as street_address';
    }
    if (in_array('patient_id', $usersColumns, true)) {
        $profileUserPatientJoin = 'LEFT JOIN patients p ON p.id = u.patient_id';
        $profilePatientUserJoin = 'LEFT JOIN users u ON u.patient_id = p.id';
    } elseif (!in_array('user_id', $patientsColumns, true)) {
        $profileUserPatientJoin = 'LEFT JOIN patients p ON 1 = 0';
        $profilePatientUserJoin = 'LEFT JOIN users u ON 1 = 0';
    }
} catch (Exception $e) {
    // Keep the current schema's patients.user_id relationship as the default.
}

try {
    // Safely fetch user and patient records, pulling from whichever table has the data
    $stmt = $pdo->prepare("
        SELECT p.*, 
               u.first_name as user_first_name, 
               u.middle_name as user_middle_name, 
               u.last_name as user_last_name,
               COALESCE(u.email, p.email) as email,
               COALESCE(u.phone, p.phone) as phone,
               {$profileAddressSelect},
               u.created_at, {$profilePictureSelect},
               COALESCE(u.gender, p.gender) as gender
        FROM users u 
        {$profileUserPatientJoin}
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fallback if not found by user id, try session patient_id
    if ((!$patient || empty($patient['id'])) && $patient_id) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   u.first_name as user_first_name, 
                   u.middle_name as user_middle_name, 
                   u.last_name as user_last_name,
                   COALESCE(p.email, u.email) as email,
                   COALESCE(p.phone, u.phone) as phone,
                   {$profileAddressSelect},
                   u.created_at, {$profilePictureSelect},
                   COALESCE(p.gender, u.gender) as gender
            FROM patients p 
            {$profilePatientUserJoin}
            WHERE p.id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Final fallback if no link exists yet
    if (!$patient || empty($patient['id'])) {
        $stmt = $pdo->prepare("SELECT *, first_name as user_first_name, middle_name as user_middle_name, last_name as user_last_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    if (!empty($patient['id']) && !$patient_id) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    }

    if (!empty($patient) && !isset($patient['street_address']) && !empty($patient['address'])) {
        $patient['street_address'] = $patient['address'];
    }
    if (!empty($patient) && !isset($patient['street_address'])) {
        $patient['street_address'] = '';
    }

    foreach (['first_name' => 'user_first_name', 'middle_name' => 'user_middle_name', 'last_name' => 'user_last_name'] as $patientField => $userField) {
        if (trim((string)($patient[$patientField] ?? '')) === '') {
            $patient[$patientField] = $patient[$userField] ?? '';
        }
    }
    if (!empty($patient['date_of_birth'])) {
        $patient['date_of_birth'] = substr((string)$patient['date_of_birth'], 0, 10);
    }

    $patientGender = strtolower(trim((string)($patient['gender'] ?? '')));
    if ($patientGender === 'm') {
        $patientGender = 'male';
    } elseif ($patientGender === 'f') {
        $patientGender = 'female';
    }

    $patientAge = null;
    if (!empty($patient['date_of_birth'])) {
        try {
            $birthDate = new DateTime($patient['date_of_birth']);
            $today = new DateTime('today');
            if ($birthDate <= $today) {
                $patientAge = $birthDate->diff($today)->y;
            }
        } catch (Exception $e) {
            $patientAge = null;
        }
    }

    $seniorProfileValue = 'Not provided';
    if (!empty($patient['senior_citizen_id'])) {
        $typeLabel = seniorIdTypeLabel($patient['senior_id_type'] ?? '');
        if ($typeLabel === '' && !empty($patient['senior_citizen_id'])) {
            $typeLabel = 'OSCA ID';
        }
        $seniorProfileValue = trim($typeLabel . ' · ' . $patient['senior_citizen_id']);
    }

    $personalInfoCooldownUntil = null;
    if (!empty($patient_id)) {
        $cooldownStmt = $pdo->prepare("SELECT MAX(created_at) FROM audit_logs WHERE affected_table = 'patients' AND affected_id = ? AND module = 'patients' AND action_type = 'update' AND description LIKE 'patient_self_service_personal_info:%'");
        $cooldownStmt->execute([$patient_id]);
        $lastPersonalInfoChange = $cooldownStmt->fetchColumn();
        if ($lastPersonalInfoChange) {
            $personalInfoCooldownUntil = (new DateTime($lastPersonalInfoChange))->modify('+30 days');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'personal_info_update') {
            if (!$patient_id) {
                throw new Exception('Patient record not found.');
            }
            if ($personalInfoCooldownUntil && new DateTime() < $personalInfoCooldownUntil) {
                throw new Exception('Personal information can be edited again on ' . $personalInfoCooldownUntil->format('F j, Y') . '.');
            }

            $personalFields = [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'middle_name' => trim($_POST['middle_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
                'gender' => strtolower(trim($_POST['gender'] ?? ''))
            ];
            if ($personalFields['first_name'] === '' || $personalFields['last_name'] === '') {
                throw new Exception('First name and last name are required.');
            }
            if ($personalFields['gender'] !== '' && !in_array($personalFields['gender'], ['male', 'female', 'other'], true)) {
                throw new Exception('Please select a valid gender.');
            }
            if ($personalFields['date_of_birth'] !== '') {
                $dob = DateTime::createFromFormat('Y-m-d', $personalFields['date_of_birth']);
                if (!$dob || $dob->format('Y-m-d') !== $personalFields['date_of_birth'] || $dob > new DateTime('today')) {
                    throw new Exception('Please enter a valid date of birth.');
                }
            } else {
                $personalFields['date_of_birth'] = null;
            }

            $oldFields = array_intersect_key($patient, $personalFields);
            $changes = [];
            foreach ($personalFields as $field => $newValue) {
                $oldValue = $oldFields[$field] ?? null;
                if ($field === 'gender') {
                    $normalizedOldGender = strtolower(trim((string)($oldValue ?? '')));
                    $normalizedNewGender = strtolower(trim((string)($newValue ?? '')));
                    $normalizedOldGender = ['m' => 'male', 'f' => 'female'][$normalizedOldGender] ?? $normalizedOldGender;
                    $normalizedNewGender = ['m' => 'male', 'f' => 'female'][$normalizedNewGender] ?? $normalizedNewGender;
                    if ($normalizedOldGender === $normalizedNewGender) continue;
                    $changes[$field] = [$oldValue, $newValue];
                    continue;
                }
                if ($field === 'date_of_birth') {
                    $oldDob = substr(trim((string)($oldValue ?? '')), 0, 10);
                    $newDob = substr(trim((string)($newValue ?? '')), 0, 10);
                    if ($oldDob === $newDob) continue;
                    $changes[$field] = [$oldValue, $newValue];
                    continue;
                }
                if ((string)($oldValue ?? '') !== (string)($newValue ?? '')) {
                    $changes[$field] = [$oldValue, $newValue];
                }
            }
            if (!$changes) {
                throw new Exception('No personal information changes were submitted.');
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE patients SET first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, gender = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([
                    $personalFields['first_name'],
                    $personalFields['middle_name'],
                    $personalFields['last_name'],
                    $personalFields['date_of_birth'],
                    $personalFields['gender'],
                    $patient_id
                ]);

                $auditUser = getUserInfo($pdo, $user_id, $_SESSION['username'] ?? null);
                foreach ($changes as $field => [$oldValue, $newValue]) {
                    logAudit($pdo, $user_id, $auditUser['username'] ?? ($_SESSION['username'] ?? null), $_SESSION['role'] ?? 'user', 'update', 'patients', 'patient_self_service_personal_info:' . $field, $patient_id, 'patients', (string)($oldValue ?? ''), (string)($newValue ?? ''));
                }

                // Keep the linked login account (User Management) identical to this clinical record.
                syncPatientToUser($pdo, (int)$patient_id);

                $pdo->commit();
                $message = 'Personal information updated successfully.';
                $messageType = 'success';
                $personalInfoCooldownUntil = (new DateTime())->modify('+30 days');
                foreach ($personalFields as $field => $newValue) {
                    $patient[$field] = $newValue;
                }
                $patientGender = $personalFields['gender'];
                $patientAge = null;
                if (!empty($patient['date_of_birth'])) {
                    $patientAge = (new DateTime($patient['date_of_birth']))->diff(new DateTime('today'))->y;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'verify_and_update') {
            $change_type = $_POST['change_type'] ?? '';
            $otp_code = trim($_POST['otp_code'] ?? '');

            if ($change_type !== 'address' && empty($otp_code)) {
                throw new Exception('OTP verification is required to update your phone number.');
            }

            // TODO: Validate against your generated OTP code here if needed.
            // if ($change_type !== 'address' && $otp_code !== $_SESSION['active_otp']) {
            //     throw new Exception('Invalid OTP code.');
            // }

            $pdo->beginTransaction();

            try {
                if ($change_type === 'phone') {
                    $phone = trim($_POST['phone'] ?? '');
                    $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
                    $stmt->execute([$phone, $user_id]);
                    if ($patient_id) {
                        $stmt = $pdo->prepare("UPDATE patients SET phone = ? WHERE id = ?");
                        $stmt->execute([$phone, $patient_id]);
                    }
                    $message = 'Phone number successfully updated.';
                } elseif ($change_type === 'address') {
                    $address = trim($_POST['address'] ?? '');
                    $stmt = $pdo->prepare("UPDATE patients SET street_address = ? WHERE id = ?");
                    $stmt->execute([$address, $patient_id]);
                    $message = 'Address successfully updated.';
                } elseif (in_array($change_type, ['emergency_name', 'emergency_phone', 'emergency_relationship'], true)) {
                    $emergencyFieldMap = [
                        'emergency_name' => 'emergency_contact_name',
                        'emergency_phone' => 'emergency_contact_phone',
                        'emergency_relationship' => 'emergency_contact_relationship'
                    ];
                    $field = $emergencyFieldMap[$change_type];
                    $value = trim($_POST[$field] ?? '');
                    $stmt = $pdo->prepare("UPDATE patients SET `{$field}` = ? WHERE id = ?");
                    $stmt->execute([$value, $patient_id]);
                    $message = 'Emergency contact successfully updated.';
                } else {
                    throw new Exception('Invalid update type.');
                }

                $pdo->commit();
                $messageType = 'success';

                $refreshStmt = $pdo->prepare("
                    SELECT p.*, 
                           u.first_name as user_first_name,
                           u.middle_name as user_middle_name,
                           u.last_name as user_last_name,
                           COALESCE(u.email, p.email) as email,
                           COALESCE(u.phone, p.phone) as phone,
                           {$profileAddressSelect},
                           u.created_at, {$profilePictureSelect},
                           COALESCE(u.gender, p.gender) as gender
                    FROM users u
                    {$profileUserPatientJoin}
                    WHERE u.id = ?
                ");
                $refreshStmt->execute([$user_id]);
                $patient = $refreshStmt->fetch(PDO::FETCH_ASSOC);

                if ((!$patient || empty($patient['id'])) && $patient_id) {
                    $refreshStmt = $pdo->prepare("
                        SELECT p.*, 
                               u.first_name as user_first_name,
                               u.middle_name as user_middle_name,
                               u.last_name as user_last_name,
                               COALESCE(p.email, u.email) as email,
                               COALESCE(p.phone, u.phone) as phone,
                               {$profileAddressSelect},
                               u.created_at, {$profilePictureSelect},
                               COALESCE(p.gender, u.gender) as gender
                        FROM patients p
                        {$profilePatientUserJoin}
                        WHERE p.id = ?
                    ");
                    $refreshStmt->execute([$patient_id]);
                    $patient = $refreshStmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = $e->getMessage();
    $messageType = 'error';
}

$pageTitle = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic</title>
    <link rel="icon" type="image/png" href="../../assets/images/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f5f7fa; min-height: 100vh; display: flex; }
        .sidebar { width: 220px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); padding: 32px 0; position: fixed; left: 0; top: 0; height: 100vh; overflow-y: auto; color: white; box-shadow: 2px 0 8px rgba(0,0,0,0.1); z-index: 40; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 24px; margin-bottom: 40px; text-decoration: none; color: white; }
        .sidebar-brand img { height: 32px; width: auto; }
        .sidebar-brand span { font-size: 16px; font-weight: 700; white-space: nowrap; }
        .sidebar-menu { display: flex; flex-direction: column; gap: 8px; padding: 0 12px; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 8px; transition: all 0.2s; cursor: pointer; font-size: 14px; font-weight: 500; }
        .menu-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .menu-item.active { background: rgba(255,255,255,0.2); color: white; font-weight: 600; }
        .menu-item i { width: 18px; text-align: center; }
        .main-wrapper { flex: 1; margin-left: 220px; display: flex; flex-direction: column; min-height: 100vh; }
        .dashboard-header { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 50; height: 70px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .user-profile { position: relative; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #005daa, #0571ca); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0; }
        .user-details { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; font-size: 14px; color: #1a1a2e; }
        .user-role { font-size: 12px; color: #6b7280; }
        .dropdown-toggle { font-size: 16px; color: #6b7280; cursor: pointer; }
        .user-dropdown { position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 180px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 1000; }
        .user-profile:hover .user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #374151; text-decoration: none; transition: background 0.2s; font-size: 14px; }
        .dropdown-item:hover { background: #f3f4f6; }
        .dropdown-item.logout { color: #dc3545; border-top: 1px solid #e5e7eb; }
        .dashboard-main { flex: 1; padding: 24px 32px; max-width: 900px; width: 100%; margin: 0 auto; }
        .section { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .section-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; }
        .profile-header { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; }
        .profile-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #005daa, #0571ca); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 28px; }
        .profile-info h2 { font-size: 24px; color: #1a1a2e; margin-bottom: 4px; }
        .profile-info p { color: #6b7280; font-size: 14px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group.full-width { grid-column: span 2; }
        .form-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; transition: border-color 0.2s; background: #fff; }
        .form-input[readonly] { background: #f3f4f6; color: #4b5563; cursor: not-allowed; border-color: #e5e7eb; }
        .form-input:focus { outline: none; border-color: #005daa; box-shadow: 0 0 0 3px rgba(0,93,170,0.1); }
        .contact-stack { display: flex; flex-direction: column; gap: 18px; }
        .contact-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; }
        .contact-row-label { font-size: 14px; font-weight: 600; color: #374151; min-width: 120px; }
        .contact-row-value { flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 12px; min-width: 0; }
        .contact-row-text { font-size: 15px; color: #1f2937; word-break: break-word; }
        .edit-contact-btn { width: 36px; height: 36px; border: 1px solid #d1d5db; background: white; border-radius: 50%; color: #005daa; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; position: relative; z-index: 5; }
        .edit-contact-btn:hover { background: #eef6ff; border-color: #cfe2ff; }
        .form-select { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white; }
        .form-select[readonly], .form-select:disabled { background: #f3f4f6; color: #4b5563; cursor: not-allowed; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #005daa; color: white; }
        .btn-primary:hover { background: #004080; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-secondary:hover { background: #e5e7eb; }
        .btn-container { display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        /* Modal styling for OTP */
        .otp-modal { display: none; position: fixed; z-index: 1100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .otp-modal-content { background: white; padding: 24px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    </style>
    <link rel="stylesheet" href="../../assets/css/patient_portal.css">
    <?php require __DIR__ . '/../../includes/app_notice_modal_script.php'; ?>
</head>
<body>
    <aside class="sidebar">
        <a href="../../index.php" class="sidebar-brand">
            <img src="../../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental</span>
        </div>
        <nav class="sidebar-menu">
            <a href="patient-dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="inquiry_consultation.php" class="menu-item">
                <i class="fas fa-comments"></i>
                <span>Inquiry / Consultation</span>
            </a>
            <a href="appointments.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i>
                <span>My appointments</span>
            </a>
            <a href="medical_records.php" class="menu-item">
                <i class="fas fa-file-medical"></i>
                <span>Medical records</span>
            </a>
            <a href="billing.php" class="menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Billing</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Account settings</span>
            </a>
        </nav>
    </aside>
    <?php include '../../includes/patient_sidebar_overlay.php'; ?>

    <div class="main-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <?php include '../../includes/patient_menu_toggle.php'; ?>
                <div>
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">My Profile</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">Manage your personal information</p>
                </div>
            </div>
            <div class="header-right">
                <?php include '../../includes/patient_tour_widget.php'; ?>
                <?php include '../../includes/patient_notification_widget.php'; ?>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                            $profPic = $patient['profile_picture'] ?? $_SESSION['profile_picture'] ?? '';
                            $first = ($patient['first_name'] ?? '') ?: ($_SESSION['user_name'] ?? $_SESSION['display_name'] ?? '');
                            $last = $patient['last_name'] ?? '';
                            if (!empty($profPic) && file_exists(dirname(__DIR__, 2) . '/' . $profPic)):
                        ?>
                            <img src="../../<?php echo htmlspecialchars($profPic); ?>" alt="Profile" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                        <?php 
                            else:
                            $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
                        ?>
                            <?php echo htmlspecialchars($initials ?: 'P'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name">
                            <?php 
                                $displayName = trim($first ?: 'Patient');
                                echo htmlspecialchars($displayName);
                            ?>
                        </div>
                        <div class="user-role">Patient</div>
                    </div>
                    <div class="dropdown-toggle">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="user-dropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="../../logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="section">
                <div class="profile-header">
                    <div class="profile-avatar" style="overflow:hidden;">
                        <?php 
                            $profPic = $patient['profile_picture'] ?? $_SESSION['profile_picture'] ?? '';
                            if (!empty($profPic) && file_exists(dirname(__DIR__, 2) . '/' . $profPic)):
                        ?>
                            <img src="../../<?php echo htmlspecialchars($profPic); ?>" alt="Profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                        <?php 
                            else:
                            $initials = strtoupper(substr($patient['first_name'] ?? 'P', 0, 1) . substr($patient['middle_name'] ?? '', 0, 1) . substr($patient['last_name'] ?? '', 0, 1));
                            echo $initials;
                        endif; ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars(trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''))); ?></h2>
                        <p>Patient since <?php echo date('F Y', strtotime($patient['created_at'] ?? 'now')); ?></p>
                    </div>
                </div>

                <div style="margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid #e5e7eb;">
                    <div class="section-title">Profile Photo</div>
                    <form action="../shared/profile_picture_actions.php" method="POST" enctype="multipart/form-data" id="uploadForm" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="upload">
                        <div class="upload-btn-wrapper">
                            <button class="btn btn-primary" id="chooseBtn" type="button">Choose Photo</button>
                            <input type="file" name="profile_picture" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                        </div>
                        <button class="btn btn-primary" id="saveBtn" style="display:none;" type="submit">Save Photo</button>
                        <?php if (!empty($profPic)): ?>
                            <a href="../shared/profile_picture_actions.php?action=delete" class="btn btn-secondary" style="text-decoration:none;color:#dc2626;" onclick="return window.appAskNav ? appAskNav(event, 'Remove profile photo?') : confirm('Remove profile photo?')">Remove Photo</a>
                        <?php endif; ?>
                        <span style="font-size:12px;color:#6b7280;">JPG, PNG, GIF, WebP. Max 2MB.</span>
                    </form>
                </div>

                <form method="POST" action="" id="profileUpdateForm">
                    <input type="hidden" name="action" value="update_contact_request">
                    <input type="hidden" name="change_type" id="changeTypeField" value="">
                    <input type="hidden" name="otp_code" id="hiddenOtpCode" value="">
                    
                    <!-- Personal Information (Read-only / Not Editable) -->
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <div class="section-title">Personal Information</div>
                        <?php if ($personalInfoCooldownUntil && new DateTime() < $personalInfoCooldownUntil): ?>
                            <span style="font-size:12px;color:#6b7280;">Next edit available <?php echo htmlspecialchars($personalInfoCooldownUntil->format('F j, Y')); ?></span>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary" id="editPersonalInfoBtn"><i class="fas fa-pen"></i> Edit Personal Information</button>
                        <?php endif; ?>
                    </div>
                    <div id="personalInfoEditActions" style="display:none;gap:10px;justify-content:flex-end;margin:12px 0;">
                        <button type="button" class="btn btn-secondary" id="cancelPersonalInfoBtn">Cancel</button>
                        <button type="button" class="btn btn-primary" id="savePersonalInfoBtn">Save Changes</button>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-input personal-info-field" id="personalFirstName" name="first_name" value="<?php echo htmlspecialchars($patient['first_name'] ?? ''); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-input personal-info-field" id="personalMiddleName" name="middle_name" value="<?php echo htmlspecialchars($patient['middle_name'] ?? ''); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-input personal-info-field" id="personalLastName" name="last_name" value="<?php echo htmlspecialchars($patient['last_name'] ?? ''); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-input personal-info-field" id="personalDateOfBirth" name="date_of_birth" value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select class="form-select personal-info-field" id="personalGender" name="gender" disabled>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $patientGender === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $patientGender === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $patientGender === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Age</label>
                            <input type="text" class="form-input" value="<?php echo $patientAge !== null ? htmlspecialchars((string)$patientAge) : 'Not provided'; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Senior government ID</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($seniorProfileValue ?? 'Not provided'); ?>" placeholder="Not provided" readonly style="background-color:#f9fafb;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">PWD ID No. <span style="color:#6b7280;font-weight:400;">(Optional)</span></label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($patient['pwd_id'] ?? ''); ?>" placeholder="Not provided" readonly style="background-color:#f9fafb;">
                        </div>
                    </div>

                    <!-- Contact Information & Address -->
                    <div class="section-title" style="margin-top: 24px;">Contact Information & Address</div>
                    <div class="contact-stack">
                        <div class="contact-row">
                            <div class="contact-row-label">Email</div>
                            <div class="contact-row-value">
                                <span class="contact-row-text" id="contact-email-text"><?php echo htmlspecialchars($patient['email'] ?? 'Not provided'); ?></span>
                            </div>
                        </div>
                        <div class="contact-row">
                            <div class="contact-row-label">Phone Number</div>
                            <div class="contact-row-value">
                                <span class="contact-row-text" id="contact-phone-text"><?php echo htmlspecialchars($patient['phone'] ?? 'Not provided'); ?></span>
                                <button type="button" class="edit-contact-btn" data-type="phone" data-current-value="<?php echo htmlspecialchars($patient['phone'] ?? '', ENT_QUOTES); ?>" title="Edit phone number" onclick="window.openContactEditor && window.openContactEditor('phone'); return false;"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                        <div class="contact-row">
                            <div class="contact-row-label">Address / Location</div>
                            <div class="contact-row-value">
                                <span class="contact-row-text" id="contact-address-text"><?php echo htmlspecialchars($patient['street_address'] ?? 'Not provided'); ?></span>
                                <button type="button" class="edit-contact-btn" data-type="address" data-current-value="<?php echo htmlspecialchars($patient['street_address'] ?? '', ENT_QUOTES); ?>" title="Edit address" onclick="window.openContactEditor && window.openContactEditor('address'); return false;"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="section-title" style="margin-top: 24px;">Emergency Contact</div>
                    <div class="contact-stack">
                        <div class="contact-row">
                            <div class="contact-row-label">Contact Name</div>
                            <div class="contact-row-value">
                                <span class="contact-row-text"><?php echo htmlspecialchars($patient['emergency_contact_name'] ?? 'Not provided'); ?></span>
                                <button type="button" class="edit-contact-btn" data-type="emergency_name" data-current-value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? '', ENT_QUOTES); ?>" title="Edit emergency contact name" onclick="window.openContactEditor && window.openContactEditor('emergency_name'); return false;"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                        <div class="contact-row">
                            <div class="contact-row-label">Contact Phone</div>
                            <div class="contact-row-value">
                                <span class="contact-row-text"><?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? 'Not provided'); ?></span>
                                <button type="button" class="edit-contact-btn" data-type="emergency_phone" data-current-value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? '', ENT_QUOTES); ?>" title="Edit emergency contact phone" onclick="window.openContactEditor && window.openContactEditor('emergency_phone'); return false;"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                        <div class="contact-row">
                            <div class="contact-row-label">Relationship</div>
                            <div class="contact-row-value">
                                <span class="contact-row-text"><?php echo htmlspecialchars($patient['emergency_contact_relationship'] ?? 'Not provided'); ?></span>
                                <button type="button" class="edit-contact-btn" data-type="emergency_relationship" data-current-value="<?php echo htmlspecialchars($patient['emergency_contact_relationship'] ?? '', ENT_QUOTES); ?>" title="Edit emergency contact relationship" onclick="window.openContactEditor && window.openContactEditor('emergency_relationship'); return false;"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="phone" id="phoneInput" value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>">
                    <input type="hidden" name="address" id="addressInput" value="<?php echo htmlspecialchars($patient['street_address'] ?? ''); ?>">
                    <input type="hidden" name="emergency_contact_name" id="emergencyNameInput" value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
                    <input type="hidden" name="emergency_contact_phone" id="emergencyPhoneInput" value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>">
                    <input type="hidden" name="emergency_contact_relationship" id="emergencyRelationshipInput" value="<?php echo htmlspecialchars($patient['emergency_contact_relationship'] ?? ''); ?>">
                </form>
            </div>
        </main>
    </div>

    <div id="contactEditorModal" class="otp-modal" style="display:none;">
        <div class="otp-modal-content">
            <h3 id="contactEditorTitle" style="margin-bottom: 12px; font-size: 18px; color: #1a1a2e;">Edit Details</h3>
            <p id="contactEditorDescription" style="font-size: 13px; color: #6b7280; margin-bottom: 16px;">Update this information.</p>
            <div class="form-group">
                <input type="text" id="contactEditorInput" class="form-input" placeholder="Enter value">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" id="cancelContactEditBtn">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendOtpBtn">Send OTP</button>
                <button type="button" class="btn btn-primary" id="saveContactBtn" style="display:none;">Save</button>
            </div>
        </div>
    </div>

    <a href="messages.php" class="floating-message-btn" data-chat-launcher aria-label="Open messages" title="Messages">
        <i class="fas fa-message"></i>
        <span class="unread-badge" id="unreadBadge" style="display: none;">0</span>
    </a>
    
    <script>
        // Existing chat and profile scripts
        (function () {
            const launcher = document.querySelector('[data-chat-launcher]');
            if (!launcher) return;
            const unreadBadge = document.getElementById('unreadBadge');
            let overlay = document.getElementById('floatingChatOverlay');
            let panel = document.getElementById('floatingChatPanel');
            if (!overlay) { overlay = document.createElement('div'); overlay.id = 'floatingChatOverlay'; overlay.className = 'floating-chat-overlay'; document.body.appendChild(overlay); }
            if (!panel) { panel = document.createElement('div'); panel.id = 'floatingChatPanel'; panel.className = 'floating-chat-panel'; panel.innerHTML = `<div class="floating-chat-header"><div class="floating-chat-title"><i class="fas fa-comments"></i><span>Messages</span></div><div class="floating-chat-actions"><button type="button" class="floating-chat-control" data-chat-minimize aria-label="Minimize"><i class="fas fa-minus"></i></button><button type="button" class="floating-chat-control" data-chat-close aria-label="Close"><i class="fas fa-xmark"></i></button></div></div><iframe class="floating-chat-frame" src="messages.php?embed=1" title="Messages"></iframe>`; document.body.appendChild(panel); }
            function closeChat() { overlay.classList.remove('is-open'); panel.classList.remove('is-open'); }
            function openChat(event) {
                event.preventDefault();
                overlay.classList.add('is-open');
                panel.classList.add('is-open');
                const frame = panel.querySelector('.floating-chat-frame');
                if (frame && frame.contentWindow) {
                    window.setTimeout(() => {
                        frame.contentWindow.postMessage({ type: 'patient-chat-opened' }, window.location.origin);
                    }, 120);
                }
            }
            function updateUnreadBadge(count) {
                if (!unreadBadge) return;
                if (count > 0) {
                    unreadBadge.textContent = count > 99 ? '99+' : String(count);
                    unreadBadge.style.display = 'inline-flex';
                } else {
                    unreadBadge.style.display = 'none';
                }
            }
            async function pollUnreadCount() {
                try {
                    const payload = new FormData();
                    payload.append('ajax', '1');
                    payload.append('action', 'get_unread_count');
                    const response = await fetch('messages.php', {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) return;
                    updateUnreadBadge(Number(data.unread_count || 0));
                } catch (_err) {}
            }
            launcher.addEventListener('click', openChat);
            overlay.addEventListener('click', closeChat);
            panel.querySelector('[data-chat-close]').addEventListener('click', closeChat);
            panel.querySelector('[data-chat-minimize]').addEventListener('click', closeChat);
            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin) return;
                if (event.data && event.data.type === 'patient-chat-unread') {
                    updateUnreadBadge(Number(event.data.count || 0));
                }
            });
            pollUnreadCount();
            setInterval(pollUnreadCount, 3000);
        })();
    </script>
    <?php
        $__stoEnabled = !empty($patientSessionSettings['enabled']);
        $__stoMs = (int)($patientSessionSettings['timeout_minutes'] ?? 60) * 60 * 1000;
        require __DIR__ . '/../../includes/session_timeout_script.php';
    ?>
    <script>
        (function() {
            const fileInput = document.getElementById('fileInput');
            const chooseBtn = document.getElementById('chooseBtn');
            const saveBtn = document.getElementById('saveBtn');
            const form = document.getElementById('uploadForm');
            if (!fileInput || !chooseBtn || !saveBtn || !form) return;
            chooseBtn.addEventListener('click', function() { fileInput.click(); });
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) { alert('Please select a valid image file (JPG, PNG, GIF, WebP).'); this.value = ''; return; }
                if (file.size > 2 * 1024 * 1024) { alert('File is too large. Maximum size is 2MB.'); this.value = ''; return; }
                saveBtn.style.display = 'inline-flex';
                chooseBtn.textContent = 'Change Photo';
            });
        })();
    </script>
    <script>
    (function() {
        var alertEl = document.querySelector('.alert-success');
        if (alertEl) {
            setTimeout(function() {
                alertEl.style.transition = 'opacity 0.3s';
                alertEl.style.opacity = '0';
                setTimeout(function() { alertEl.remove(); }, 300);
            }, 2000);
        }
    })();
    </script>
    <!-- OTP Modal Popup -->
    <div id="otpModal" class="otp-modal">
        <div class="otp-modal-content">
            <h3 id="modalTitle" style="margin-bottom: 12px; font-size: 18px; color: #1a1a2e;">Enter Verification OTP</h3>
            <p id="modalDesc" style="font-size: 13px; color: #6b7280; margin-bottom: 16px;">Please enter the verification code sent to update your details.</p>
            <div class="form-group">
                <input type="text" id="otpInputBox" class="form-input" placeholder="Enter 6-digit OTP" maxlength="6">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" id="cancelOtpBtn">Cancel</button>
                <button type="button" class="btn btn-primary" id="verifyOtpBtn">Confirm & Save</button>
            </div>
        </div>
    </div>

    <script>
        const profileUpdateForm = document.getElementById('profileUpdateForm');
        const contactEditorModal = document.getElementById('contactEditorModal');
        const contactEditorTitle = document.getElementById('contactEditorTitle');
        const contactEditorDescription = document.getElementById('contactEditorDescription');
        const contactEditorInput = document.getElementById('contactEditorInput');
        const cancelContactEditBtn = document.getElementById('cancelContactEditBtn');
        const sendOtpBtn = document.getElementById('sendOtpBtn');
        const saveContactBtn = document.getElementById('saveContactBtn');
        const otpModal = document.getElementById('otpModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalDesc = document.getElementById('modalDesc');
        const cancelOtpBtn = document.getElementById('cancelOtpBtn');
        const verifyOtpBtn = document.getElementById('verifyOtpBtn');
        const otpInputBox = document.getElementById('otpInputBox');
        const hiddenOtpCode = document.getElementById('hiddenOtpCode');
        const changeTypeField = document.getElementById('changeTypeField');
        const phoneInput = document.getElementById('phoneInput');
        const addressInput = document.getElementById('addressInput');
        const emergencyNameInput = document.getElementById('emergencyNameInput');
        const emergencyPhoneInput = document.getElementById('emergencyPhoneInput');
        const emergencyRelationshipInput = document.getElementById('emergencyRelationshipInput');
        const editPersonalInfoBtn = document.getElementById('editPersonalInfoBtn');
        const personalInfoEditActions = document.getElementById('personalInfoEditActions');
        const cancelPersonalInfoBtn = document.getElementById('cancelPersonalInfoBtn');
        const savePersonalInfoBtn = document.getElementById('savePersonalInfoBtn');
        const personalInfoFields = Array.from(document.querySelectorAll('.personal-info-field'));
        const personalInfoAction = profileUpdateForm ? profileUpdateForm.querySelector('input[name="action"]') : null;
        const personalInfoOriginalValues = personalInfoFields.map(function(field) { return field.value; });

        let currentEditType = null;

        function setPersonalInfoEditing(isEditing) {
            personalInfoFields.forEach(function(field) {
                if (field.tagName === 'SELECT') field.disabled = !isEditing;
                else field.readOnly = !isEditing;
            });
            if (personalInfoEditActions) personalInfoEditActions.style.display = isEditing ? 'flex' : 'none';
            if (editPersonalInfoBtn) editPersonalInfoBtn.style.display = isEditing ? 'none' : 'inline-flex';
        }

        if (editPersonalInfoBtn) {
            editPersonalInfoBtn.addEventListener('click', function() {
                setPersonalInfoEditing(true);
            });
        }
        if (cancelPersonalInfoBtn) {
            cancelPersonalInfoBtn.addEventListener('click', function() {
                personalInfoFields.forEach(function(field, index) { field.value = personalInfoOriginalValues[index]; });
                setPersonalInfoEditing(false);
            });
        }
        if (savePersonalInfoBtn) {
            savePersonalInfoBtn.addEventListener('click', function() {
                if (!personalInfoAction || !profileUpdateForm) return;
                personalInfoFields.forEach(function(field) {
                    if (field.tagName === 'SELECT') field.disabled = false;
                    else field.readOnly = false;
                });
                personalInfoAction.value = 'personal_info_update';
                profileUpdateForm.submit();
            });
        }

        window.closeContactEditor = function() {
            contactEditorModal.style.display = 'none';
            contactEditorInput.value = '';
            currentEditType = null;
        };

        window.openContactEditor = function(type) {
            currentEditType = type;
            const currentValue = document.querySelector('.edit-contact-btn[data-type="' + type + '"]')?.dataset.currentValue || '';

            if (type === 'phone') {
                contactEditorTitle.textContent = 'Edit Phone Number';
                contactEditorDescription.textContent = 'Enter your new phone number. An OTP will be sent to verify this change.';
                contactEditorInput.type = 'tel';
            } else if (type === 'emergency_name') {
                contactEditorTitle.textContent = 'Edit Emergency Contact Name';
                contactEditorDescription.textContent = 'Enter the name of your emergency contact.';
                contactEditorInput.type = 'text';
            } else if (type === 'emergency_phone') {
                contactEditorTitle.textContent = 'Edit Emergency Contact Phone';
                contactEditorDescription.textContent = 'Enter the phone number of your emergency contact.';
                contactEditorInput.type = 'tel';
            } else if (type === 'emergency_relationship') {
                contactEditorTitle.textContent = 'Edit Emergency Contact Relationship';
                contactEditorDescription.textContent = 'Enter your relationship with this emergency contact.';
                contactEditorInput.type = 'text';
            } else {
                contactEditorTitle.textContent = 'Edit Address / Location';
                contactEditorDescription.textContent = 'Update your current address or location. This change does not require OTP.';
                contactEditorInput.type = 'text';
            }

            contactEditorInput.value = currentValue || '';
            const requiresOtp = type === 'phone';
            sendOtpBtn.style.display = requiresOtp ? 'inline-flex' : 'none';
            saveContactBtn.textContent = type === 'address' ? 'Save Address' : 'Save';
            saveContactBtn.style.display = requiresOtp ? 'none' : 'inline-flex';
            contactEditorModal.style.display = 'flex';
        }

        document.querySelectorAll('.edit-contact-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                window.openContactEditor(button.dataset.type);
            });
        });

        cancelContactEditBtn.addEventListener('click', window.closeContactEditor);

        sendOtpBtn.addEventListener('click', function() {
            if (!currentEditType || currentEditType !== 'phone') return;
            const newValue = contactEditorInput.value.trim();

            if (!newValue) {
                alert('Please enter a value before continuing.');
                return;
            }

            if (!/^\+?[0-9\s\-()]{7,15}$/.test(newValue)) {
                alert('Please enter a valid phone number.');
                return;
            }

            phoneInput.value = newValue;
            changeTypeField.value = 'phone';
            modalTitle.textContent = 'Verify Phone Change';
            modalDesc.textContent = 'Please enter the OTP sent to your mobile number.';

            window.closeContactEditor();
            otpModal.style.display = 'flex';
            otpInputBox.value = '';
            hiddenOtpCode.value = '';
        });

        saveContactBtn.addEventListener('click', function() {
            if (!currentEditType || currentEditType === 'phone') return;
            const newValue = contactEditorInput.value.trim();
            if (!newValue) {
                alert('Please enter a value.');
                return;
            }

            if (currentEditType === 'address') {
                addressInput.value = newValue;
            } else if (currentEditType === 'emergency_name') {
                emergencyNameInput.value = newValue;
            } else if (currentEditType === 'emergency_phone') {
                if (!/^\+?[0-9\s\-()]{7,15}$/.test(newValue)) {
                    alert('Please enter a valid phone number.');
                    return;
                }
                emergencyPhoneInput.value = newValue;
            } else if (currentEditType === 'emergency_relationship') {
                emergencyRelationshipInput.value = newValue;
            }

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'verify_and_update';
            profileUpdateForm.appendChild(actionInput);

            changeTypeField.value = currentEditType;
            hiddenOtpCode.value = 'contact-update';
            window.closeContactEditor();
            profileUpdateForm.submit();
        });

        cancelOtpBtn.addEventListener('click', function() {
            otpModal.style.display = 'none';
            otpInputBox.value = '';
            hiddenOtpCode.value = '';
            changeTypeField.value = '';
        });

        verifyOtpBtn.addEventListener('click', function() {
            const enteredOtp = otpInputBox.value.trim();
            if (!enteredOtp || enteredOtp.length < 4) {
                alert('Please enter a valid OTP code.');
                return;
            }

            hiddenOtpCode.value = enteredOtp;
            otpModal.style.display = 'none';
            profileUpdateForm.submit();
        });
    </script>
</body>
</html>