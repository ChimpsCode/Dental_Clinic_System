<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
ob_start();
header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/appointment_slots_helper.php';
    ensureAppointmentSlotSchema($pdo);
    echo json_encode(getAppointmentSlots($pdo, $_GET));
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
