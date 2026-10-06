<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'dentist', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    require_once '../../config/database.php';
require_once __DIR__ . '/../../includes/treatment_plan_workflow.php';
ensureTreatmentPlanWorkflowSchema($pdo);

    $cal = (isset($_GET['cal']) && preg_match('/^\d{4}-\d{2}$/', $_GET['cal'])) ? $_GET['cal'] : date('Y-m');
    $year = (int)substr($cal, 0, 4);
    $monthNum = (int)substr($cal, 5, 2);
    if ($monthNum < 1 || $monthNum > 12) {
        $monthNum = (int)date('n');
        $year = (int)date('Y');
        $cal = sprintf('%04d-%02d', $year, $monthNum);
    }

    $firstDay = sprintf('%04d-%02d-01', $year, $monthNum);
    $lastDay = date('Y-m-t', strtotime($firstDay));
    $prev = date('Y-m', strtotime($firstDay . ' -1 month'));
    $next = date('Y-m', strtotime($firstDay . ' +1 month'));
    $monthLabel = date('F Y', strtotime($firstDay));

    $stmt = $pdo->prepare("
        SELECT a.appointment_date, a.appointment_time, a.status,
               COALESCE(CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name), '') AS patient_name,
               CONCAT(COALESCE(s.name, tp.plan_name, 'Treatment'), CASE WHEN a.treatment_plan_id IS NOT NULL THEN CONCAT(' - Plan session ', a.treatment_plan_session) ELSE '' END) AS service_name
        FROM appointments a
        LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN treatment_plans tp ON tp.id = a.treatment_plan_id
        WHERE a.appointment_date BETWEEN ? AND ?
          AND (a.is_archived = 0 OR a.is_archived IS NULL)
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute([$firstDay, $lastDay]);

    $byDate = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = $row['appointment_date'];
        if (!isset($byDate[$d])) {
            $byDate[$d] = [];
        }
        $byDate[$d][] = $row;
    }

    echo json_encode([
        'success' => true,
        'month' => $cal,
        'month_label' => $monthLabel,
        'prev' => $prev,
        'next' => $next,
        'appointments' => $byDate
    ]);

} catch (Exception $e) {
    error_log("Calendar Data Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
