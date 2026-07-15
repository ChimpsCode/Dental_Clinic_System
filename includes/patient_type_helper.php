<?php
/**
 * Checks if a patient should be transitioned from 'New Patient' to 'Returning Patient'.
 * Called after a new appointment is created for a patient.
 *
 * Logic: If the patient is currently 'New Patient' and has 2+ total appointments,
 * automatically update their patient_type to 'Returning Patient'.
 *
 * @param PDO    $pdo          Database connection
 * @param int    $patientId    The patient's ID
 * @return bool  Whether the patient was transitioned
 */
function maybeTransitionToReturningPatient($pdo, $patientId) {
    if (!$patientId) {
        return false;
    }

    try {
        // Check if the patient is currently 'New Patient'
        $stmt = $pdo->prepare("SELECT patient_type FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $currentType = $stmt->fetchColumn();

        if ($currentType !== 'New Patient') {
            return false;
        }

        // Count total appointments for this patient (including the one just created)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $appointmentCount = $stmt->fetchColumn();

        // If they have 2 or more appointments, transition to Returning Patient
        if ($appointmentCount >= 2) {
            $update = $pdo->prepare("UPDATE patients SET patient_type = 'Returning Patient' WHERE id = ? AND patient_type = 'New Patient'");
            $update->execute([$patientId]);
            return $update->rowCount() > 0;
        }
    } catch (Exception $e) {
        error_log("Patient type transition error: " . $e->getMessage());
    }

    return false;
}
