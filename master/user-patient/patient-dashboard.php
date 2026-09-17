<?php
ob_start();

require_once __DIR__ . '/../../includes/session_bootstrap.php';

// Session already expired on entry (inactivity window elapsed) -> bounce to login
if (!empty($GLOBALS['APP_SESSION_EXPIRED'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php?expired=1');
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

// Verify this is a patient (user role)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

// Get patient info
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

// Patient_id might not be set yet, we'll get it from the database
$patient_id = $_SESSION['patient_id'] ?? null;

// Get patient details from database
require_once '../../config/database.php';
require_once '../../includes/patient_session_helper.php';
require_once '../../includes/patient_dashboard_status_helper.php';
$patientSessionSettings = getPatientSessionSettings($pdo, (int)($_SESSION['user_id'] ?? 0));
require_once '../../includes/inquiry_module_helper.php';
ensureInquiryModuleSchema($pdo);

$inquiryActiveId = null;
$inquiryMessages = [];
$inquiryStatus = null;
$inquiryClinicName = 'RF Dental Clinic';
$inquiryClinicAvatar = '../../assets/images/Logo.png';

try {
    $userStmt = $pdo->prepare("
        SELECT id, first_name, middle_name, last_name, email, phone
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $hasPatientUserId = (bool)$pdo->query("SHOW COLUMNS FROM patients LIKE 'user_id'")->fetch(PDO::FETCH_ASSOC);

    if ($hasPatientUserId) {
        $stmt = $pdo->prepare("
            SELECT p.*, COALESCE(u.first_name, p.first_name) AS first_name, COALESCE(u.middle_name, p.middle_name) AS middle_name, COALESCE(u.last_name, p.last_name) AS last_name, u.email, u.phone, u.profile_picture
            FROM patients p 
            LEFT JOIN users u ON u.id = p.user_id 
            WHERE p.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
    } elseif (!empty($patient_id)) {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   ? AS first_name,
                   ? AS middle_name,
                   ? AS last_name,
                   ? AS email,
                   ? AS phone
            FROM patients p
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([
            $user['first_name'] ?? null,
            $user['middle_name'] ?? null,
            $user['last_name'] ?? null,
            $user['email'] ?? null,
            $user['phone'] ?? null,
            (int)$patient_id,
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   ? AS first_name,
                   ? AS middle_name,
                   ? AS last_name,
                   ? AS email,
                   ? AS phone
            FROM patients p
            WHERE p.email = ?
            LIMIT 1
        ");
        $stmt->execute([
            $user['first_name'] ?? null,
            $user['middle_name'] ?? null,
            $user['last_name'] ?? null,
            $user['email'] ?? null,
            $user['phone'] ?? null,
            $user['email'] ?? '',
        ]);
    }

    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        throw new Exception('Patient not found');
    }
    
    // Save patient_id to session if it's not set
    if (!$patient_id) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    }
    
    // Get appointments - only upcoming ones
    $stmt = $pdo->prepare("
        SELECT a.*, a.status as status_label
        FROM appointments a 
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $upcomingCount = count($appointments);
    $nextAppointment = $appointments[0] ?? null;
    $activeInquiryCount = 0;
    $queueStatusText = 'No active queue today';
    $queueNumberText = 'None';
    $billingSummaryText = 'No balance';
    $billingDueText = '';
    $recentActivity = [];

    $hasInquiries = (bool)$pdo->query("SHOW TABLES LIKE 'inquiries'")->fetch(PDO::FETCH_ASSOC);
    if ($hasInquiries) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM inquiries 
            WHERE patient_id = ? 
              AND COALESCE(is_archived, 0) = 0
              AND LOWER(COALESCE(status, 'pending')) NOT IN ('resolved', 'closed', 'completed', 'cancelled')
        ");
        $stmt->execute([$patient_id]);
        $activeInquiryCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT id, created_at, concern, inquiry_message, status
            FROM inquiries
            WHERE patient_id = ? AND COALESCE(is_archived, 0) = 0
            ORDER BY created_at DESC, id DESC
            LIMIT 3
        ");
        $stmt->execute([$patient_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $recentActivity[] = [
                'title' => 'Inquiry submitted',
                'date' => $row['created_at'] ?? '',
                'details' => trim((string)($row['concern'] ?: ($row['inquiry_message'] ?: ($row['status'] ?: 'Consultation request sent.')))),
                'icon' => 'fas fa-comments'
            ];
        }
    }

    // Inquiry conversation data for floating chat
    $inquirySetStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $inquiryClinicSettings = [];
    while ($inquiryRow = $inquirySetStmt->fetch(PDO::FETCH_ASSOC)) {
        $inquiryClinicSettings[$inquiryRow['setting_key']] = $inquiryRow['setting_value'];
    }
    $inquiryClinicName = $inquiryClinicSettings['clinic_name'] ?? 'RF Dental Clinic';
    $inquiryClinicLogo = $inquiryClinicSettings['welcome_logo'] ?? '';
    $inquiryLogoFull = $inquiryClinicLogo ? '../../' . $inquiryClinicLogo : '../../assets/images/Logo.png';
    $inquiryAdminPicStmt = $pdo->prepare("SELECT profile_picture FROM users WHERE role = 'Admin' ORDER BY id ASC LIMIT 1");
    $inquiryAdminPicStmt->execute();
    $inquiryAdminPicPath = $inquiryAdminPicStmt->fetchColumn();
    $inquiryClinicAvatar = ($inquiryAdminPicPath && file_exists(dirname(__DIR__, 2) . '/' . $inquiryAdminPicPath)) ? '../../' . $inquiryAdminPicPath : $inquiryLogoFull;

    if ($hasInquiries && !empty($patient_id)) {
        $inquiryConvStmt = $pdo->prepare("
            SELECT i.*, s.name AS service_name
            FROM inquiries i
            LEFT JOIN services s ON s.id = i.service_id
            WHERE i.patient_id = ? AND COALESCE(i.is_archived, 0) = 0
            ORDER BY i.created_at DESC, i.id DESC
            LIMIT 1
        ");
        $inquiryConvStmt->execute([(int)$patient_id]);
        $activeInquiry = $inquiryConvStmt->fetch(PDO::FETCH_ASSOC);

        if ($activeInquiry) {
            $inquiryActiveId = (int)$activeInquiry['id'];
            $inquiryStatus = $activeInquiry['status'] ?? '';

            $concernText = $activeInquiry['concern'] ?: ($activeInquiry['inquiry_message'] ?? '');
            if ($concernText !== '') {
                $inquiryMessages[] = [
                    'type' => 'patient',
                    'message' => $concernText,
                    'created_at' => $activeInquiry['created_at'],
                ];
            }

            $inquiryReplies = fetchInquiryReplies($pdo, $inquiryActiveId);
            foreach ($inquiryReplies as $reply) {
                $inquiryMessages[] = [
                    'id' => $reply['id'],
                    'type' => $reply['sender_type'] === 'staff' ? 'clinic' : 'patient',
                    'message' => $reply['message'],
                    'attachment' => $reply['attachment'] ?? null,
                    'created_at' => $reply['created_at'],
                ];
            }

            usort($inquiryMessages, function ($a, $b) {
                return strtotime($a['created_at']) - strtotime($b['created_at']);
            });
        }
    }

    $dashboardStatus = getPatientDashboardStatusPayload($pdo, $patient_id);
    $queueNumberText = $dashboardStatus['queue_number'];
    $queueStatusText = $dashboardStatus['queue_status'];
    $billingSummaryText = $dashboardStatus['billing_summary'];
    $billingDueText = $dashboardStatus['billing_due'] ?? '';

    if (count($recentActivity) < 4) {
        foreach ($appointments as $apt) {
            $recentActivity[] = [
                'title' => 'Appointment scheduled',
                'date' => $apt['created_at'] ?? ($apt['appointment_date'] ?? ''),
                'details' => (!empty($apt['appointment_date']) ? date('M j, Y', strtotime($apt['appointment_date'])) : 'Upcoming appointment')
                    . (!empty($apt['appointment_time']) ? ' at ' . date('g:i A', strtotime($apt['appointment_time'])) : ''),
                'icon' => 'fas fa-calendar-check'
            ];
            if (count($recentActivity) >= 4) {
                break;
            }
        }
    }
    
} catch (Exception $e) {
    $patient = [];
    $appointments = [];
    $upcomingCount = 0;
    $nextAppointment = null;
    $activeInquiryCount = 0;
    $queueStatusText = 'No active queue today';
    $queueNumberText = 'None';
    $billingSummaryText = 'No balance';
    $billingDueText = '';
    $recentActivity = [];
    $inquiryActiveId = null;
    $inquiryMessages = [];
    $inquiryStatus = null;
}

$pageTitle = 'Patient Dashboard';
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
        /* Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 32px 0;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            color: white;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
            z-index: 40;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px;
            margin-bottom: 40px;
            text-decoration: none;
            color: white;
        }

        .sidebar-brand img {
            height: 32px;
            width: auto;
        }

        .sidebar-brand span {
            font-size: 16px;
            font-weight: 700;
            white-space: nowrap;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 0 12px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
        }

        .menu-item i {
            width: 18px;
            text-align: center;
        }

        /* Main Content Area */
        .main-wrapper {
            flex: 1;
            margin-left: 260px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header */
        .dashboard-header {
            background: white;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 0;
            z-index: 50;
            height: 70px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-profile {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #005daa, #0571ca);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: #1a1a2e;
        }

        .user-role {
            font-size: 12px;
            color: #6b7280;
        }

        .dropdown-toggle {
            font-size: 16px;
            color: #6b7280;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1000;
        }

        .user-profile:hover .user-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            transition: background 0.2s;
            font-size: 14px;
        }

        .dropdown-item:hover {
            background: #f3f4f6;
        }

        .dropdown-item.logout {
            color: #dc3545;
            border-top: 1px solid #e5e7eb;
        }

        /* Main Content */
        .dashboard-main {
            flex: 1;
            padding: 24px 32px 40px;
            max-width: 1500px;
            width: 100%;
            margin: 0 auto;
        }

        /* Welcome Section */
        .welcome-section {
            background: url('../../assets/images/banner.jpg') center/cover no-repeat;
            color: white;
            padding: 48px 32px;
            border-radius: 12px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            min-height: 200px;
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(30, 60, 114, 0.85) 0%, rgba(42, 82, 152, 0.7) 100%);
            border-radius: 12px;
        }
        
        .welcome-section > * {
            position: relative;
            z-index: 1;
        }

        .welcome-content h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-content p {
            opacity: 0.95;
            font-size: 15px;
            max-width: 500px;
        }

        .welcome-section .btn {
            padding: 12px 24px;
            background: white;
            color: #005daa;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .welcome-section .btn:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .summary-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .summary-card-title {
            font-size: 13px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .summary-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #005daa;
        }

        .summary-card-value {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 6px;
        }

        .summary-card-due {
            font-size: 13px;
            font-weight: 600;
            color: #ea580c;
            margin: -2px 0 8px;
        }

        .summary-card-subtext {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        /* Calendar Section */
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .card-title-small {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 16px;
        }

        .calendar-nav {
            display: flex;
            gap: 8px;
        }

        .calendar-nav button {
            background: #f0f0f0;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: background 0.2s;
        }

        .calendar-nav button:hover {
            background: #e0e0e0;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .calendar-day-header {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            padding: 12px 8px;
            text-transform: uppercase;
        }

        .calendar-day {
            aspect-ratio: 1.2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            color: #6b7280;
            font-weight: 500;
        }

        .calendar-day:hover {
            background: #f0f0f0;
        }

        .calendar-day.other-month {
            opacity: 0.4;
            cursor: default;
        }

        .calendar-day.today {
            background: #dcf0ff;
            color: #005daa;
            font-weight: 600;
        }

        .calendar-day.has-appointment {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .calendar-day.has-appointment::after {
            content: '';
            position: absolute;
            bottom: 6px;
            width: 5px;
            height: 5px;
            background: #2e7d32;
            border-radius: 50%;
        }

        /* Sidebar Card Sections */
        .sidebar-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .sidebar-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .view-link {
            font-size: 13px;
            color: #005daa;
            text-decoration: none;
            font-weight: 600;
        }

        .view-link:hover {
            text-decoration: underline;
        }

        .appointment-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .appointment-item {
            padding: 12px;
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            background: #f9fafb;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
        }

        .appointment-info {
            flex: 1;
        }

        .appointment-date {
            font-weight: 600;
            color: #005daa;
            display: block;
            margin-bottom: 2px;
        }

        .appointment-type {
            color: #6b7280;
            font-size: 12px;
        }

        .appointment-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
            text-transform: uppercase;
        }

        .status-scheduled {
            background: #e0f2fe;
            color: #0369a1;
        }

        .status-in-queue {
            background: #fef3c7;
            color: #92400e;
        }

        .status-pending-approval {
            background: #fef3c7;
            color: #b45309;
        }

        .status-pending-payment {
            background: #fed7aa;
            color: #c2410c;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-message {
            color: #9ca3af;
            font-size: 13px;
            padding: 12px 0;
        }

        .empty-message a {
            color: #005daa;
            text-decoration: none;
        }

        .empty-message a:hover {
            text-decoration: underline;
        }

        /* User Information */
        .user-info-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #1a1a2e;
        }

        .info-value {
            color: #6b7280;
            text-align: right;
            flex: 1;
            margin-left: 12px;
            word-break: break-word;
        }

        /* Oral Health Insights */
        .insights-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .insight-icon {
            font-size: 48px;
            line-height: 1;
        }

        .insight-text {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.5;
        }

        .insight-text strong {
            color: #1a1a2e;
            font-weight: 600;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            color: #1a1a2e;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .quick-action-btn:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #005daa;
        }

        .activity-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .activity-icon-wrap {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #eff6ff;
            color: #005daa;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-content strong {
            display: block;
            font-size: 13px;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .activity-content span,
        .activity-content small {
            display: block;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-main {
                padding: 20px 24px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .welcome-section {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }
        }

        @media (max-width: 768px) {
            .main-wrapper {
                margin-left: 0;
            }

            .dashboard-header {
                padding: 12px 16px;
                height: 60px;
            }

            .user-details {
                display: none;
            }

            .dashboard-main {
                padding: 16px;
            }

            .summary-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .card {
                padding: 16px;
            }

            .calendar-grid {
                gap: 4px;
            }

            .calendar-day {
                font-size: 12px;
            }

            .welcome-section {
                padding: 20px;
            }

            .welcome-content h1 {
                font-size: 20px;
            }

            .welcome-section .btn {
                padding: 10px 16px;
                font-size: 14px;
            }
        }

        @media (max-width: 640px) {
            .main-wrapper {
                margin-left: 0;
            }

            .calendar-grid {
                gap: 2px;
            }

            .calendar-day {
                font-size: 11px;
                aspect-ratio: 1;
            }

            .calendar-day-header {
                font-size: 10px;
                padding: 6px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .summary-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .summary-card {
                padding: 14px;
            }

            .summary-card-title {
                font-size: 10px;
                letter-spacing: 0.03em;
            }

            .summary-card-value {
                font-size: 1.35rem;
            }

            .summary-card-due {
                font-size: 11px;
            }

            .summary-card-subtext {
                font-size: 11px;
            }

            .header-right {
                gap: 12px;
            }

            .user-avatar {
                width: 36px;
                height: 36px;
                font-size: 12px;
            }
        }
    </style>
    <link rel="stylesheet" href="../../assets/css/patient_portal.css?v=7">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="patientSidebar">
        <div class="sidebar-brand">
            <img src="../../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental Clinic</span>
        </div>

        <nav class="sidebar-menu">
                <a href="#" class="menu-item active" data-page="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="menu-item" data-page="inquiry" data-tour="inquiry">
                    <i class="fas fa-comments"></i>
                    <span>Inquiry / Consultation</span>
                </a>
                <a href="#" class="menu-item" data-page="appointments" data-tour="appointments">
                    <i class="fas fa-calendar-alt"></i>
                    <span>My appointments</span>
                </a>
            <a href="#" class="menu-item" data-page="records" data-tour="records">
                <i class="fas fa-file-medical"></i>
                <span>Medical records</span>
            </a>
            <a href="#" class="menu-item" data-page="billing" data-tour="billing">
                <i class="fas fa-credit-card"></i>
                <span>Billing</span>
            </a>
            <a href="#" class="menu-item" data-page="settings" data-tour="settings">
                <i class="fas fa-cog"></i>
                <span>Account settings</span>
            </a>
        </nav>
    </aside>
    <?php include '../../includes/patient_sidebar_overlay.php'; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <?php include '../../includes/patient_menu_toggle.php'; ?>
                <div style="font-family: sans-serif;">
  <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">
    Dashboard
  </h1>
  <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">
    A quick overview of your oral wellness journey and upcoming appointments
  </p>
</div>
                </div>
            <div class="header-right">
                <?php include '../../includes/patient_tour_widget.php'; ?>
                <?php include '../../includes/patient_notification_widget.php'; ?>
                <div class="user-profile" data-tour="profile">
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
                        <div class="user-name"><?php echo htmlspecialchars(trim($first ?: 'Patient')); ?></div>
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

        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Welcome Section -->
            <section class="welcome-section" data-tour="welcome">
                <div class="welcome-content">
                    <h1><?php echo (!empty($_SESSION['is_new_patient']) || !empty($_SESSION['new_registration'])) ? 'Welcome' : 'Welcome Back'; ?>, <?php echo htmlspecialchars($patient['first_name'] ?? 'Patient'); ?>!</h1>
                    <?php if (!empty($_SESSION['new_registration'])) unset($_SESSION['new_registration']); ?>
                    <p>Track your oral wellness journey and manage your dental plan details below.</p>
                </div>
                <a href="appointments.php" class="btn" data-tour="book">
                    <i class="fas fa-calendar-plus"></i>
                    Book a New Appointment
                </a>
            </section>

            <section class="summary-cards">
                <article class="summary-card">
                    <div class="summary-card-top">
                        <div class="summary-card-title">Upcoming Appointments</div>
                        <div class="summary-card-icon"><i class="fas fa-calendar-alt"></i></div>
                    </div>
                    <div class="summary-card-value"><?php echo (int)$upcomingCount; ?></div>
                    <div class="summary-card-subtext">
                        <?php echo $nextAppointment ? htmlspecialchars(date('M j, Y', strtotime($nextAppointment['appointment_date'])) . ' at ' . date('g:i A', strtotime($nextAppointment['appointment_time']))) : 'No upcoming appointment scheduled.'; ?>
                    </div>
                </article>

                <article class="summary-card">
                    <div class="summary-card-top">
                        <div class="summary-card-title">Active Inquiries</div>
                        <div class="summary-card-icon"><i class="fas fa-comments"></i></div>
                    </div>
                    <div class="summary-card-value"><?php echo (int)$activeInquiryCount; ?></div>
                    <div class="summary-card-subtext">Open consultation requests waiting for clinic updates.</div>
                </article>

                <article class="summary-card">
                    <div class="summary-card-top">
                        <div class="summary-card-title">Queue Status</div>
                        <div class="summary-card-icon"><i class="fas fa-ticket-alt"></i></div>
                    </div>
                    <div class="summary-card-value" id="queueNumberValue"><?php echo htmlspecialchars($queueNumberText); ?></div>
                    <div class="summary-card-subtext" id="queueStatusSubtext"><?php echo htmlspecialchars($queueStatusText); ?></div>
                </article>

                <article class="summary-card">
                    <div class="summary-card-top">
                        <div class="summary-card-title">Billing Summary</div>
                        <div class="summary-card-icon"><i class="fas fa-credit-card"></i></div>
                    </div>
                    <div class="summary-card-value" id="billingSummaryValue"><?php echo htmlspecialchars($billingSummaryText); ?></div>
                    <div class="summary-card-due" id="billingDueValue"<?php echo $billingDueText === '' ? ' hidden' : ''; ?>><?php echo htmlspecialchars($billingDueText); ?></div>
                    <div class="summary-card-subtext">Latest balance overview from your billing records.</div>
                </article>
            </section>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Main Content Area -->
                <div>
                    <!-- Calendar Section -->
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title" id="currentMonthYear">March 2026</h2>
                            <div class="calendar-nav">
                                <button type="button" id="prevMonth" title="Previous month">&lt;</button>
                                <button type="button" id="nextMonth" title="Next month">&gt;</button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="calendarGrid">
                            <!-- Calendar will be generated by JavaScript -->
                        </div>
                    </section>
                </div>

                <!-- Right Sidebar -->
                <div>
                    <section class="sidebar-card">
                        <h3 class="sidebar-card-title">Quick Actions</h3>
                        <div class="quick-actions">
                            <a href="appointments.php" class="quick-action-btn">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Book Appointment</span>
                            </a>
                            <a href="inquiry_consultation.php" class="quick-action-btn">
                                <i class="fas fa-comments"></i>
                                <span>Submit Inquiry</span>
                            </a>
                            <a href="medical_records.php" class="quick-action-btn">
                                <i class="fas fa-file-medical"></i>
                                <span>View Medical Records</span>
                            </a>
                        </div>
                    </section>

                    <section class="sidebar-card">
                        <h3 class="sidebar-card-title">Recent Activity</h3>
                        <?php if (!empty($recentActivity)): ?>
                            <ul class="activity-list">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <li class="activity-item">
                                        <div class="activity-icon-wrap"><i class="<?php echo htmlspecialchars($activity['icon']); ?>"></i></div>
                                        <div class="activity-content">
                                            <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                            <span><?php echo htmlspecialchars($activity['details']); ?></span>
                                            <?php if (!empty($activity['date'])): ?>
                                                <small><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($activity['date']))); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="empty-message">No recent activity yet.</div>
                        <?php endif; ?>
                    </section>

                    <!-- Upcoming Appointments -->
                    <section class="sidebar-card">
                        <div class="sidebar-card-title">
                            <span>Upcoming Appointments</span>
                            <a href="appointments.php" class="view-link">View All</a>
                        </div>
                        <?php if (!empty($appointments)): ?>
                            <ul class="appointment-list">
                                <?php foreach ($appointments as $apt): ?>
                                    <li class="appointment-item">
                                        <div class="appointment-info">
                                            <span class="appointment-date">
                                                <?php echo date('M jS', strtotime($apt['appointment_date'])); ?>
                                            </span>
                                            <span class="appointment-type">
                                                <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?> • <?php echo htmlspecialchars($apt['treatment'] ?? 'Routine Cleaning'); ?>
                                            </span>
                                        </div>
                                        <?php 
                                            $status = $apt['status_label'] ?? $apt['status'] ?? 'Scheduled';
                                            $statusClass = 'status-scheduled';
                                            if (stripos($status, 'pending approval') !== false) $statusClass = 'status-pending-approval';
                                            elseif (stripos($status, 'queue') !== false) $statusClass = 'status-in-queue';
                                            elseif (stripos($status, 'pending') !== false) $statusClass = 'status-pending-payment';
                                            elseif (stripos($status, 'complete') !== false) $statusClass = 'status-completed';
                                            elseif (stripos($status, 'cancel') !== false) $statusClass = 'status-cancelled';
                                        ?>
                                        <span class="appointment-status <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="empty-message">No upcoming appointments. <a href="#book-appointment">Book one now!</a></div>
                        <?php endif; ?>
                    </section>

                    <!-- Your Information -->
                    <section class="sidebar-card">
                        <h3 class="sidebar-card-title">Your Information</h3>
                        <div class="user-info-group">
                            <div class="info-item">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['phone'] ?? '+1 (555) 123-4567'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($patient['email'] ?? 'j.smith@email.com'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Insurance:</span>
                                <span class="info-value">Aetna Platinum</span>
                            </div>
                        </div>
                    </section>

                    <!-- Oral Health Insights -->
                    <section class="sidebar-card">
                        <h3 class="sidebar-card-title">Oral Health Insights</h3>
                        <div class="insights-content">
                            <div class="insight-icon">😊</div>
                            <div class="insight-text">
                                <strong>Next Check-up due in 3 months.</strong>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarGrid = document.getElementById('calendarGrid');
            const currentMonthYear = document.getElementById('currentMonthYear');
            const prevMonthBtn = document.getElementById('prevMonth');
            const nextMonthBtn = document.getElementById('nextMonth');
            
            let currentDate = new Date();
            
            // Generate calendar
            function renderCalendar() {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                
                currentMonthYear.textContent = new Date(year, month).toLocaleDateString('en-US', {
                    month: 'long',
                    year: 'numeric'
                });
                
                calendarGrid.innerHTML = '';
                
                // Add day headers
                const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                dayHeaders.forEach(day => {
                    const header = document.createElement('div');
                    header.className = 'calendar-day-header';
                    header.textContent = day;
                    calendarGrid.appendChild(header);
                });
                
                const firstDay = new Date(year, month, 1).getDay();
                const totalDays = new Date(year, month + 1, 0).getDate();
                const prevMonthDays = new Date(year, month, 0).getDate();
                
                // Previous month days
                for (let i = firstDay - 1; i >= 0; i--) {
                    const day = document.createElement('div');
                    day.className = 'calendar-day other-month';
                    day.textContent = prevMonthDays - i;
                    calendarGrid.appendChild(day);
                }
                
                // Current month days
                const today = new Date();
                for (let i = 1; i <= totalDays; i++) {
                    const day = document.createElement('div');
                    day.className = 'calendar-day';
                    day.textContent = i;
                    
                    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                    
                    // Check if it's today
                    if (today.getFullYear() === year && today.getMonth() === month && today.getDate() === i) {
                        day.classList.add('today');
                    }
                    
                    calendarGrid.appendChild(day);
                }
                
                // Next month days
                const remainingDays = 42 - (firstDay + totalDays);
                for (let i = 1; i <= remainingDays; i++) {
                    const day = document.createElement('div');
                    day.className = 'calendar-day other-month';
                    day.textContent = i;
                    calendarGrid.appendChild(day);
                }
            }
            
            // Navigation
            prevMonthBtn.addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar();
            });
            
            nextMonthBtn.addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });

            // Sidebar menu navigation
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = this.getAttribute('data-page');
                    
                    // Remove active class from all items
                    menuItems.forEach(m => m.classList.remove('active'));
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Navigate based on page
                    switch(page) {
                        case 'dashboard':
                            // Stay on dashboard
                            break;
                        case 'appointments':
                            window.location.href = './appointments.php';
                            break;
                        case 'inquiry':
                            window.location.href = './inquiry_consultation.php';
                            break;
                        case 'records':
                            window.location.href = './medical_records.php';
                            break;
                        case 'profile':
                            window.location.href = './profile.php';
                            break;
                        case 'billing':
                            window.location.href = './billing.php';
                            break;
                        case 'settings':
                            window.location.href = './settings.php';
                            break;
                    }
                });
            });
            
            // Initial render
            renderCalendar();
        });
    </script>
    <a href="#" class="floating-message-btn" data-inquiry-launcher data-tour="messages" aria-label="Open messages" title="Messages">
        <i class="fas fa-paper-plane"></i>
        <span class="unread-badge" id="inquiryUnreadBadge" style="display: none;">0</span>
    </a>

    <div id="inquiryChatOverlay" class="floating-chat-overlay"></div>

    <div id="inquiryChatPanel" class="floating-chat-panel">
        <div class="floating-chat-header">
            <button type="button" class="floating-chat-control chat-header-back" data-inquiry-close aria-label="Back">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="floating-chat-title">
                <span class="patient-chat-header-avatar">
                    <img src="<?php echo htmlspecialchars($inquiryClinicAvatar); ?>" alt="<?php echo htmlspecialchars($inquiryClinicName); ?>">
                </span>
                <span><?php echo htmlspecialchars($inquiryClinicName); ?></span>
            </div>
            <button type="button" class="chat-info-button" id="patientChatInfoBtn" aria-label="Conversation information" title="Conversation information">
                <span aria-hidden="true">i</span>
            </button>
        </div>
        <div id="inquiryMessagesList" class="inquiry-messages-body">
            <?php if (empty($inquiryMessages)): ?>
            <div class="inquiry-empty-state">
                <i class="fas fa-comments" style="font-size:48px;margin-bottom:16px;display:block;color:#d1d5db;"></i>
                <p>Send a message to start a conversation with the clinic.</p>
            </div>
            <?php else: ?>
                <?php foreach ($inquiryMessages as $msg): ?>
                <?php $ts = date('h:i A', strtotime($msg['created_at'] ?? 'now')); ?>
                <?php $att = $msg['attachment'] ?? null; ?>
                <?php $isImage = $att && preg_match('/\.(jpe?g|png|gif|webp)$/i', $att); ?>
                <?php if ($msg['type'] === 'clinic'): ?>
                <div class="message-row left"<?php echo !empty($msg['id']) ? ' data-reply-id="' . (int)$msg['id'] . '"' : ''; ?>>
                    <img src="<?php echo htmlspecialchars($inquiryClinicAvatar); ?>" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;">
                    <div>
                        <div class="bubble bubble-left">
                            <?php echo renderInquiryQuotedMessageHtml((string)($msg['message'] ?? '')); ?>
                            <?php if ($att): ?>
                                <?php if ($isImage): ?><img src="../<?php echo htmlspecialchars($att); ?>" class="inquiry-chat-image" alt="Attachment">
                                <?php else: ?><a href="../<?php echo htmlspecialchars($att); ?>" target="_blank" class="inquiry-chat-file"><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars(basename($att)); ?></a><?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="ts"><?php echo $ts; ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="message-row right"<?php echo !empty($msg['id']) ? ' data-reply-id="' . (int)$msg['id'] . '"' : ''; ?>>
                    <div>
                        <div class="bubble bubble-right">
                            <?php echo renderInquiryQuotedMessageHtml((string)($msg['message'] ?? '')); ?>
                            <?php if ($att): ?>
                                <?php if ($isImage): ?><img src="../<?php echo htmlspecialchars($att); ?>" class="inquiry-chat-image" alt="Attachment">
                                <?php else: ?><a href="../<?php echo htmlspecialchars($att); ?>" target="_blank" class="inquiry-chat-file"><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars(basename($att)); ?></a><?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="ts" style="text-align:right;"><?php echo $ts; ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <div id="patientDashboardStaffTyping" class="message-row left typing-message-row" style="display:none;" aria-hidden="true">
                <img src="<?php echo htmlspecialchars($inquiryClinicAvatar); ?>" alt="">
                <div class="typing-bubble">
                    <span class="typing-dots" aria-label="Staff is typing"><span></span><span></span><span></span></span>
                </div>
            </div>
        </div>
        <form id="inquiryChatForm">
            <input type="hidden" name="action" value="create">
            <div class="conv-input">
                <button type="button" class="chat-input-icon" id="inquiryAttachFile" aria-label="Attach file"><i class="fas fa-paperclip"></i></button>
                <button type="button" class="chat-input-icon" id="inquiryAttachImage" aria-label="Attach image"><i class="far fa-image"></i></button>
                <input type="file" id="inquiryFileInput" hidden>
                <input id="inquiryChatInput" name="concern" class="conv-text" placeholder="Type your message...">
                <button type="submit" class="send-btn" aria-label="send"><i class="fas fa-paper-plane"></i></button>
            </div>
        </form>
        <div class="patient-chat-info-panel" id="patientChatInfoPanel" aria-hidden="true">
            <div class="patient-chat-info-header">
                <button type="button" class="floating-chat-control chat-header-back" id="patientChatInfoBack" aria-label="Back to conversation">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <strong>Conversation info</strong>
            </div>
            <div class="patient-chat-info-profile">
                <img src="<?php echo htmlspecialchars($inquiryClinicAvatar); ?>" alt="<?php echo htmlspecialchars($inquiryClinicName); ?>">
                <div><?php echo htmlspecialchars($inquiryClinicName); ?></div>
            </div>
            <button type="button" class="patient-media-toggle" id="patientMediaToggle" aria-expanded="false">
                <span>Media &amp; Files</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="patient-media-content" id="patientMediaContent">
                <div class="patient-media-gallery" id="patientMediaGallery"></div>
            </div>
        </div>
    </div>

    <style>
        #inquiryChatPanel {
            position: fixed !important;
            top: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            left: auto !important;
            width: 400px !important;
            max-width: 100vw !important;
            height: 100vh !important;
            height: 100dvh !important;
            border: none !important;
            border-left: 1px solid #e5e7eb !important;
            border-radius: 0 !important;
            box-shadow: -8px 0 30px rgba(15, 23, 42, 0.16) !important;
        }
        #inquiryChatPanel .inquiry-messages-body {
            flex: 1; overflow-y: auto; padding: 16px; background: #fff;
            display: flex; flex-direction: column; gap: 16px; min-height: 0;
        }
        #inquiryChatPanel .inquiry-empty-state {
            text-align: center; color: #9ca3af; padding: 40px 16px; font-size: 14px; line-height: 1.5;
        }
        #inquiryChatPanel .message-row {
            display: flex; gap: 12px; align-items: flex-end;
        }
        #inquiryChatPanel .message-row.left { justify-content: flex-start; }
        #inquiryChatPanel .message-row.right { justify-content: flex-end; }
        #inquiryChatPanel .message-row > div:last-child { max-width: 75%; }
        #inquiryChatPanel .bubble {
            padding: 14px 16px; border-radius: 12px; max-width: 100%; width: fit-content;
            font-size: 14px; line-height: 1.5; word-wrap: break-word;
        }
        #inquiryChatPanel .bubble-left {
            background: #f3f4f6; border: 1px solid #e5e7eb; color: #1f2937; border-bottom-left-radius: 4px;
        }
        #inquiryChatPanel .bubble-right {
            background: #5b6ee6; color: #fff; border-bottom-right-radius: 4px;
        }
        #inquiryChatPanel .ts {
            font-size: 11px; color: #9ca3af; margin-top: 6px;
        }
        #inquiryChatPanel .conv-input {
            display:flex; gap:6px; align-items:center; min-height:58px; margin:12px;
            padding:6px; border:1px solid #2563eb; border-radius:999px; background:#fff;
            box-shadow:0 0 0 3px rgba(37,99,235,.08);
        }
        #inquiryChatPanel .conv-text {
            flex:1; min-width:0; height:44px; border:none; padding:0 8px; background:transparent;
            font:inherit; font-size:14px; outline:none;
        }
        #inquiryChatPanel .conv-text:focus {
            border:none; box-shadow:none;
        }
        #inquiryChatPanel .send-btn {
            width:46px; height:46px; flex:0 0 46px; padding:0; border:none; border-radius:50%;
            display:inline-flex; align-items:center; justify-content:center; cursor:pointer; font-size:18px;
            color:#fff; background:linear-gradient(135deg,#2563eb,#1d4ed8);
            box-shadow:0 4px 12px rgba(37,99,235,.25);
        }
        #inquiryChatPanel .send-btn:hover { transform:scale(1.04); }
        #inquiryChatPanel .chat-input-icon {
            width:42px; height:42px; flex:0 0 42px; display:inline-flex; align-items:center;
            justify-content:center; padding:0; border:none; border-radius:50%; background:transparent;
            color:#64748b; font-size:18px; cursor:pointer;
        }
        #inquiryChatPanel .chat-input-icon:hover { background:#e8eef7; color:#2563eb; }
        #inquiryChatPanel .typing-message-row { align-items:flex-end; gap:8px; }
        #inquiryChatPanel .typing-message-row > img { width:34px!important; height:34px!important; flex:0 0 34px; border-radius:50%; object-fit:cover; border:1px solid #dbe3ee; background:#fff; }
        #inquiryChatPanel .typing-bubble { display:flex; align-items:center; min-width:54px; min-height:38px; padding:10px 14px; border-radius:19px; border-bottom-left-radius:6px; background:#e2e8f0; }
        #inquiryChatPanel .typing-dots { display:inline-flex; gap:4px; align-items:center; }
        #inquiryChatPanel .typing-dots span { width:7px; height:7px; border-radius:50%; background:#64748b; animation:patientDashTyping 1.1s infinite ease-in-out; }
        #inquiryChatPanel .typing-dots span:nth-child(2) { animation-delay:.15s; }
        #inquiryChatPanel .typing-dots span:nth-child(3) { animation-delay:.3s; }
        @keyframes patientDashTyping { 0%,60%,100% { transform:translateY(0); opacity:.45; } 30% { transform:translateY(-3px); opacity:1; } }
        #inquiryChatPanel .inquiry-chat-image { display:block; max-width:220px; max-height:220px; margin-top:6px; border-radius:8px; object-fit:cover; }
        #inquiryChatPanel .inquiry-chat-file { display:inline-flex; align-items:center; gap:6px; margin-top:6px; color:inherit; }
        #inquiryChatPanel.is-open { display: flex; flex-direction: column; }
        #inquiryChatPanel .floating-chat-header {
            min-height:92px; height:auto; padding:18px 20px; border-bottom:1px solid #e5e7eb;
            justify-content:flex-start; gap:14px; background:#fff;
        }
        #inquiryChatPanel .floating-chat-title {
            flex:1; min-width:0; display:flex; align-items:center; gap:14px;
            color:#0f172a; font-size:18px; font-weight:800;
        }
        #inquiryChatPanel .patient-chat-header-avatar {
            width:56px; height:56px; flex:0 0 56px; display:inline-flex; align-items:center;
            justify-content:center; overflow:hidden; border-radius:50%;
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            box-shadow:0 2px 8px rgba(37,99,235,.18);
        }
        #inquiryChatPanel .patient-chat-header-avatar img {
            width:100%; height:100%; display:block; border-radius:50%; object-fit:contain;
            padding:6px; background:#fff;
        }
        #inquiryChatPanel .floating-chat-title span {
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        #inquiryChatPanel .floating-chat-title .patient-chat-header-avatar {
            overflow:hidden;
        }
        #inquiryChatPanel .chat-header-back {
            width:48px; height:48px; flex:0 0 48px; background:#eff6ff; color:#2563eb;
            font-size:18px;
        }
        #inquiryChatPanel .chat-info-button {
            width:48px; height:48px; flex:0 0 48px; display:inline-flex; align-items:center;
            justify-content:center; border:none; border-radius:50%; background:#eff6ff; color:#2563eb;
            font:700 22px/1 Georgia, serif; cursor:pointer; transition:background .2s, transform .2s;
        }
        #inquiryChatPanel .chat-info-button:hover { background:#dbeafe; transform:scale(1.04); }
        #inquiryChatPanel .patient-chat-info-panel {
            position:absolute; inset:0; z-index:30; display:flex; flex-direction:column; overflow-y:auto;
            background:#fff; transform:translateX(100%); visibility:hidden;
            transition:transform .25s ease, visibility .25s;
        }
        #inquiryChatPanel .patient-chat-info-panel.open { transform:translateX(0); visibility:visible; }
        #inquiryChatPanel .patient-chat-info-header {
            min-height:72px; display:flex; align-items:center; gap:14px; padding:16px 20px;
            border-bottom:1px solid #e5e7eb; color:#0f172a;
        }
        #inquiryChatPanel .patient-chat-info-profile {
            display:flex; flex-direction:column; align-items:center; gap:12px; padding:28px 20px;
            border-bottom:1px solid #eef2f7; color:#0f172a; font-size:17px; font-weight:700;
        }
        #inquiryChatPanel .patient-chat-info-profile img {
            width:76px; height:76px; border-radius:50%; object-fit:cover;
        }
        #inquiryChatPanel .patient-media-toggle {
            width:100%; min-height:58px; display:flex; align-items:center; justify-content:space-between;
            padding:16px 20px; border:none; border-bottom:1px solid #eef2f7; background:#fff;
            color:#0f172a; font:inherit; font-weight:700; cursor:pointer;
        }
        #inquiryChatPanel .patient-media-toggle:hover { background:#f8fafc; }
        #inquiryChatPanel .patient-media-toggle.open i { transform:rotate(180deg); }
        #inquiryChatPanel .patient-media-toggle i { transition:transform .2s; }
        #inquiryChatPanel .patient-media-content { display:none; padding:12px; }
        #inquiryChatPanel .patient-media-content.open { display:block; }
        #inquiryChatPanel .patient-media-gallery {
            display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:6px;
        }
        #inquiryChatPanel .patient-media-item {
            aspect-ratio:1; overflow:hidden; padding:0; border:none; border-radius:8px;
            background:#e2e8f0; cursor:pointer;
        }
        #inquiryChatPanel .patient-media-item img { width:100%; height:100%; display:block; object-fit:cover; }
        #inquiryChatPanel .patient-media-empty {
            grid-column:1 / -1; padding:36px 16px; color:#64748b; font-size:13px; text-align:center;
        }
        .patient-chat-lightbox {
            position:fixed; inset:0; z-index:999999; display:none; align-items:center; justify-content:center;
            background:rgba(0,0,0,.85); cursor:pointer;
        }
        .patient-chat-lightbox img { max-width:92vw; max-height:92vh; border-radius:10px; }
        @media (max-width: 640px) {
            #inquiryChatPanel {
                width: 100vw !important;
            }
            #inquiryChatPanel .floating-chat-header {
                min-height:84px; padding:16px 18px; gap:12px;
            }
            #inquiryChatPanel .floating-chat-title {
                gap:12px; font-size:17px;
            }
            #inquiryChatPanel .patient-chat-header-avatar {
                width:48px; height:48px; flex-basis:48px;
            }
            #inquiryChatPanel .chat-header-back,
            #inquiryChatPanel .chat-info-button {
                width:44px; height:44px; flex-basis:44px;
            }
        }
        #inquiryChatPanel .inq-quote {
            font-size: 12px;
            line-height: 1.4;
            padding: 8px 10px;
            margin-bottom: 8px;
            border-radius: 8px;
            border-left: 3px solid #2563eb;
            background: #e5e7eb;
            color: #374151;
        }
        #inquiryChatPanel .bubble-right .inq-quote {
            background: rgba(255,255,255,.16);
            border-left-color: rgba(255,255,255,.75);
            color: rgba(255,255,255,.95);
        }
    </style>

    <script src="../../assets/js/inquiry_quote.js"></script>
    <script>
        (function () {
            var launcher = document.querySelector('[data-inquiry-launcher]');
            if (!launcher) return;
            var unreadBadge = document.getElementById('inquiryUnreadBadge');
            var overlay = document.getElementById('inquiryChatOverlay');
            var panel = document.getElementById('inquiryChatPanel');
            var messagesList = document.getElementById('inquiryMessagesList');
            var chatForm = document.getElementById('inquiryChatForm');
            var chatInput = document.getElementById('inquiryChatInput');
            var fileInput = document.getElementById('inquiryFileInput');
            var attachFileBtn = document.getElementById('inquiryAttachFile');
            var attachImageBtn = document.getElementById('inquiryAttachImage');
            var activeInquiryId = <?php echo $inquiryActiveId ?: 'null'; ?>;
            var inquiryStatus = '<?php echo addslashes($inquiryStatus ?: ''); ?>';
            var lastMessageCount = <?php echo count($inquiryMessages); ?>;
            var conversationMedia = <?php echo json_encode($inquiryMessages, JSON_UNESCAPED_SLASHES); ?>;
            var typingState = 0;
            var typingTimer = null;
            var typingSentAt = 0;
            var conversationPollTimer = null;
            var infoBtn = document.getElementById('patientChatInfoBtn');
            var infoPanel = document.getElementById('patientChatInfoPanel');
            var infoBack = document.getElementById('patientChatInfoBack');
            var mediaToggle = document.getElementById('patientMediaToggle');
            var mediaContent = document.getElementById('patientMediaContent');
            var mediaGallery = document.getElementById('patientMediaGallery');

            function closeChat() {
                clearTimeout(typingTimer);
                sendTypingState(0);
                closeInfoPanel();
                overlay.classList.remove('is-open');
                panel.classList.remove('is-open');
            }

            function openChat(e) {
                e.preventDefault();
                overlay.classList.add('is-open');
                panel.classList.add('is-open');
                scrollToBottom();
                if (activeInquiryId) {
                    markInquiryRead();
                }
            }

            function scrollToBottom() {
                if (messagesList) {
                    messagesList.scrollTop = messagesList.scrollHeight;
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

            function escapeHtml(text) {
                var d = document.createElement('div');
                d.appendChild(document.createTextNode(text));
                return d.innerHTML;
            }

            function mergeConversationMedia(replies) {
                var map = new Map();
                conversationMedia.forEach(function(item) {
                    var key = item.id ? 'id-' + item.id : [item.type, item.message, item.attachment, item.created_at].join('|');
                    map.set(key, item);
                });
                (replies || []).forEach(function(reply) {
                    var type = reply.sender_type === 'staff' ? 'clinic' : 'patient';
                    var key = reply.id ? 'id-' + reply.id : [type, reply.message, reply.attachment, reply.created_at].join('|');
                    map.set(key, {
                        id: reply.id,
                        type: type,
                        message: reply.message || '',
                        attachment: reply.attachment || '',
                        created_at: reply.created_at || ''
                    });
                });
                conversationMedia = Array.from(map.values());
            }

            function renderMediaGallery() {
                var images = (conversationMedia || []).filter(function(item) {
                    return item.attachment && /\.(jpe?g|png|gif|webp)$/i.test(item.attachment);
                });
                if (!images.length) {
                    mediaGallery.innerHTML = '<div class="patient-media-empty">No photos have been shared in this conversation.</div>';
                    return;
                }
                mediaGallery.innerHTML = images.map(function(item) {
                    var src = '../' + item.attachment;
                    return '<button type="button" class="patient-media-item" data-media-src="' + escapeHtml(src) + '">'
                        + '<img src="' + escapeHtml(src) + '" alt="Shared image" loading="lazy">'
                        + '</button>';
                }).join('');
                mediaGallery.querySelectorAll('[data-media-src]').forEach(function(button) {
                    button.addEventListener('click', function() {
                        openPatientChatLightbox(button.getAttribute('data-media-src'));
                    });
                });
            }

            function openPatientChatLightbox(src) {
                var lightbox = document.getElementById('patientChatLightbox');
                if (!lightbox) {
                    lightbox = document.createElement('div');
                    lightbox.id = 'patientChatLightbox';
                    lightbox.className = 'patient-chat-lightbox';
                    lightbox.innerHTML = '<img alt="Shared image">';
                    lightbox.addEventListener('click', function() { lightbox.style.display = 'none'; });
                    document.body.appendChild(lightbox);
                }
                lightbox.querySelector('img').src = src;
                lightbox.style.display = 'flex';
            }

            async function openInfoPanel() {
                if (!infoPanel) return;
                renderMediaGallery();
                infoPanel.classList.add('open');
                infoPanel.setAttribute('aria-hidden', 'false');
                if (!activeInquiryId) return;
                try {
                    var fd = new FormData();
                    fd.append('action', 'get_conversation');
                    fd.append('id', activeInquiryId);
                    var resp = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: fd });
                    var data = await resp.json();
                    if (resp.ok && data.success) {
                        mergeConversationMedia(data.replies || []);
                        renderMediaGallery();
                    }
                } catch (_) {}
            }

            function closeInfoPanel() {
                if (!infoPanel) return;
                infoPanel.classList.remove('open');
                infoPanel.setAttribute('aria-hidden', 'true');
            }

            function toggleMediaPanel() {
                if (!mediaContent || !mediaToggle) return;
                var isOpen = mediaContent.classList.toggle('open');
                mediaToggle.classList.toggle('open', isOpen);
                mediaToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                if (isOpen) renderMediaGallery();
            }

            function appendMessage(type, message, timestamp, attachment, replyId) {
                var avatar = '<?php echo addslashes($inquiryClinicAvatar); ?>';
                var ts = timestamp || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                var div = document.createElement('div');
                div.className = 'message-row ' + (type === 'clinic' ? 'left' : 'right');
                if (replyId) {
                    div.setAttribute('data-reply-id', String(replyId));
                }
                var attachmentHtml = '';
                if (attachment) {
                    var attachmentUrl = '../' + attachment;
                    attachmentHtml = /\.(jpe?g|png|gif|webp)$/i.test(attachment)
                        ? '<img src="' + escapeHtml(attachmentUrl) + '" class="inquiry-chat-image" alt="Attachment">'
                        : '<a href="' + escapeHtml(attachmentUrl) + '" target="_blank" class="inquiry-chat-file"><i class="fas fa-paperclip"></i> ' + escapeHtml(attachment.split('/').pop()) + '</a>';
                }
                if (type === 'clinic') {
                    var messageHtml = (typeof inquiryQuotedMessageHtml === 'function')
                        ? inquiryQuotedMessageHtml(message)
                        : escapeHtml(message).replace(/\n/g, '<br>');
                    div.innerHTML = '<img src="' + avatar + '" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;"><div><div class="bubble bubble-left">' + messageHtml + attachmentHtml + '</div><div class="ts">' + ts + '</div></div>';
                } else {
                    var patientMessageHtml = (typeof inquiryQuotedMessageHtml === 'function')
                        ? inquiryQuotedMessageHtml(message)
                        : escapeHtml(message).replace(/\n/g, '<br>');
                    div.innerHTML = '<div><div class="bubble bubble-right">' + patientMessageHtml + attachmentHtml + '</div><div class="ts" style="text-align:right;">' + ts + '</div></div>';
                }
                var emptyState = messagesList.querySelector('.inquiry-empty-state');
                if (emptyState) emptyState.remove();
                var typingRow = document.getElementById('patientDashboardStaffTyping');
                if (typingRow) {
                    messagesList.insertBefore(div, typingRow);
                } else {
                    messagesList.appendChild(div);
                }
                scrollToBottom();
            }

            function getLastReplyId() {
                var ids = Array.from(messagesList.querySelectorAll('[data-reply-id]'))
                    .map(function(el) { return Number(el.getAttribute('data-reply-id')); })
                    .filter(function(id) { return Number.isFinite(id) && id > 0; });
                return ids.length ? Math.max.apply(null, ids) : 0;
            }

            function appendRealtimeReply(reply) {
                var id = Number(reply.id || 0);
                if (id && messagesList.querySelector('[data-reply-id="' + id + '"]')) return;
                mergeConversationMedia([reply]);
                appendMessage(reply.sender_type === 'staff' ? 'clinic' : 'patient', reply.message || '', reply.created_at ? new Date(reply.created_at.replace(' ', 'T')).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '', reply.attachment || '');
                if (id && messagesList.lastElementChild) messagesList.lastElementChild.setAttribute('data-reply-id', String(id));
                if (infoPanel && infoPanel.classList.contains('open')) renderMediaGallery();
            }

            function showStaffTyping(visible) {
                var indicator = document.getElementById('patientDashboardStaffTyping');
                if (!indicator) return;
                indicator.style.display = visible ? 'flex' : 'none';
                indicator.setAttribute('aria-hidden', visible ? 'false' : 'true');
                if (visible) {
                    messagesList.appendChild(indicator);
                    scrollToBottom();
                }
            }

            async function sendTypingState(isTyping) {
                if (!activeInquiryId) return;
                var now = Date.now();
                if (typingState === isTyping && (!isTyping || now - typingSentAt < 2500)) return;
                typingState = isTyping;
                typingSentAt = now;
                var fd = new FormData();
                fd.append('action', 'set_typing');
                fd.append('id', activeInquiryId);
                fd.append('is_typing', isTyping ? '1' : '0');
                try {
                    await fetch('../shared/inquiry_actions.php', { method: 'POST', body: fd });
                } catch (_) {}
            }

            async function markInquiryRead() {
                try {
                    var fd = new FormData();
                    fd.append('action', 'mark_read');
                    fd.append('id', activeInquiryId);
                    await fetch('../shared/inquiry_actions.php', { method: 'POST', body: fd });
                } catch (_) {}
            }

            if (chatForm) {
                attachFileBtn.addEventListener('click', function() {
                    fileInput.accept = '.pdf,.doc,.docx,.xls,.xlsx,.txt,image/*';
                    fileInput.click();
                });
                attachImageBtn.addEventListener('click', function() {
                    fileInput.accept = 'image/*';
                    fileInput.click();
                });
                chatInput.addEventListener('input', function() {
                    if (!chatInput.value.trim()) {
                        clearTimeout(typingTimer);
                        sendTypingState(0);
                        return;
                    }
                    sendTypingState(1);
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function() { sendTypingState(0); }, 3000);
                });
                chatInput.addEventListener('blur', function() {
                    clearTimeout(typingTimer);
                    sendTypingState(0);
                });
                chatForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    var message = chatInput.value.trim();
                    var selectedFile = fileInput.files[0] || null;
                    if (!message && !selectedFile) return;
                    chatInput.disabled = true;
                    var fd = new FormData();
                    fd.append('action', 'create');
                    fd.append('concern', message);
                    if (selectedFile) fd.append('attachment', selectedFile);
                    try {
                        var resp = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: fd });
                        var data = await resp.json();
                        if (data.success) {
                            chatInput.value = '';
                            fileInput.value = '';
                            sendTypingState(0);
                            if (data.inquiry_id) {
                                activeInquiryId = Number(data.inquiry_id);
                                startConversationPolling();
                            }
                            inquiryStatus = 'Unread';
                            appendMessage('patient', message, new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), data.attachment || '', data.reply_id || null);
                            updateUnreadBadge(0);
                        }
                    } catch (_) {}
                    chatInput.disabled = false;
                });
            }

            async function pollConversation() {
                if (!activeInquiryId) return;
                try {
                    var fd = new FormData();
                    fd.append('action', 'conversation_updates');
                    fd.append('id', activeInquiryId);
                    fd.append('last_reply_id', String(getLastReplyId()));
                    var resp = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: fd });
                    var data = await resp.json();
                    if (!data.success) return;
                    var replies = data.replies || [];
                    replies.forEach(appendRealtimeReply);
                    if (replies.some(function(reply) { return reply.sender_type === 'staff'; })) {
                        updateUnreadBadge(panel.classList.contains('is-open') ? 0 : 1);
                    }
                    showStaffTyping(!!data.other_is_typing);
                } catch (_) {}
            }

            if (inquiryStatus === 'Replied') {
                updateUnreadBadge(1);
            }

            launcher.addEventListener('click', openChat);
            overlay.addEventListener('click', closeChat);
            var closeBtn = panel.querySelector('[data-inquiry-close]');
            var minimizeBtn = panel.querySelector('[data-inquiry-minimize]');
            if (closeBtn) closeBtn.addEventListener('click', closeChat);
            if (minimizeBtn) minimizeBtn.addEventListener('click', closeChat);
            if (infoBtn) infoBtn.addEventListener('click', openInfoPanel);
            if (infoBack) infoBack.addEventListener('click', closeInfoPanel);
            if (mediaToggle) mediaToggle.addEventListener('click', toggleMediaPanel);

            function startConversationPolling() {
                if (conversationPollTimer || !activeInquiryId) return;
                pollConversation();
                conversationPollTimer = setInterval(pollConversation, 2000);
            }

            startConversationPolling();
        })();

        (function () {
            var queueNumberEl = document.getElementById('queueNumberValue');
            var queueStatusEl = document.getElementById('queueStatusSubtext');
            var billingEl = document.getElementById('billingSummaryValue');
            var billingDueEl = document.getElementById('billingDueValue');
            if (!queueNumberEl || !queueStatusEl) return;

            function applyDashboardStatus(data) {
                if (!data || !data.success) return;
                if (typeof data.queue_number === 'string') {
                    queueNumberEl.textContent = data.queue_number;
                }
                if (typeof data.queue_status === 'string') {
                    queueStatusEl.textContent = data.queue_status;
                }
                if (billingEl && typeof data.billing_summary === 'string') {
                    billingEl.textContent = data.billing_summary;
                }
                if (billingDueEl && typeof data.billing_due === 'string') {
                    billingDueEl.textContent = data.billing_due;
                    billingDueEl.hidden = data.billing_due === '';
                }
            }

            async function pollDashboardStatus() {
                if (document.visibilityState !== 'visible') return;
                try {
                    var resp = await fetch('dashboard_status.php', { credentials: 'same-origin' });
                    var data = await resp.json();
                    applyDashboardStatus(data);
                } catch (_) {}
            }

            pollDashboardStatus();
            setInterval(pollDashboardStatus, 8000);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') {
                    pollDashboardStatus();
                }
            });
        })();
    </script>
    <?php
        $__stoEnabled = !empty($patientSessionSettings['enabled']);
        $__stoMs = (int)($patientSessionSettings['timeout_minutes'] ?? 60) * 60 * 1000;
        require __DIR__ . '/../../includes/session_timeout_script.php';
    ?>
</body>
</html>
