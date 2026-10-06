<?php
require_once __DIR__ . '/treatment_plan_workflow.php';

function mergeTreatmentPlanQueueItems(PDO $pdo, array $items): array
{
    ensureTreatmentPlanWorkflowSchema($pdo);
    $links = $pdo->query('SELECT id, treatment_plan_id, treatment_plan_session, appointment_date FROM appointments WHERE treatment_plan_id IS NOT NULL')->fetchAll(PDO::FETCH_UNIQUE);
    foreach ($items as &$item) {
        $link = $links[(int)($item['appointment_id'] ?? 0)] ?? null;
        if ($link) {
            $item['treatment_plan_id'] = (int)$link['treatment_plan_id'];
            $item['treatment_plan_session'] = (int)$link['treatment_plan_session'];
        }
        $item['source_type'] = $item['source_type'] ?? 'queue';
        $item['status'] = $item['status_text'] ?? $item['status'] ?? 'waiting';
        $item['status_text'] = $item['status'];
    }
    unset($item);
    $priority = ['in_procedure' => 1, 'waiting' => 2, 'on_hold' => 3, 'scheduled' => 4, 'completed' => 5, 'cancelled' => 6];
    usort($items, function ($a, $b) use ($priority) {
        $difference = ($priority[$a['status']] ?? 9) <=> ($priority[$b['status']] ?? 9);
        return $difference ?: strcmp(($a['scheduled_date'] ?? '') . ($a['display_time'] ?? $a['queue_time'] ?? ''), ($b['scheduled_date'] ?? '') . ($b['display_time'] ?? $b['queue_time'] ?? ''));
    });
    return $items;
}
