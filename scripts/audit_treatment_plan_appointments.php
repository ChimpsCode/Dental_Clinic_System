<?php
// Read-only report. Never creates appointments or changes historical plans.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/treatment_plan_workflow.php';

$issues = [];
foreach ($pdo->query('SELECT id FROM treatment_plans ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $planId) {
    $plan = syncTreatmentPlanProgress($pdo, (int)$planId, false);
    if (empty($plan['missing_appointments'])) continue;
    $missing = [];
    foreach (json_decode($plan['sessions_data'], true) ?: [] as $index => $session) {
        if (($session['status'] ?? '') !== 'Appointment missing') continue;
        $missing[] = ['session' => $index + 1, 'date' => $session['date'] ?? null, 'time' => $session['time'] ?? null];
    }
    $issues[] = ['plan_id' => (int)$planId, 'patient_id' => (int)$plan['patient_id'],
        'missing_appointments' => $plan['missing_appointments'], 'sessions' => $missing];
}
echo json_encode(['plans_needing_review' => $issues], JSON_PRETTY_PRINT) . PHP_EOL;
