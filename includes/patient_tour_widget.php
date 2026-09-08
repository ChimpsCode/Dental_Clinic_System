<?php
/**
 * Patient account tour help button + Driver.js assets.
 * Include inside .header-right, before the notification widget.
 */
if (!isset($_SESSION['user_id'])) {
    return;
}

$patientTourUserId = (int)($_SESSION['user_id'] ?? 0);
$patientTourScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$patientTourIsDashboard = ($patientTourScript === 'patient-dashboard.php');
$patientTourForceStart = $patientTourIsDashboard && isset($_GET['tour']);
$patientTourAutoStart = $patientTourIsDashboard && (
    !empty($_SESSION['is_new_patient'])
    || !empty($_SESSION['first_login'])
    || !empty($_SESSION['new_registration'])
);
$patientTourAssetBase = '../../assets';
$patientTourAnimationUrl = $patientTourAssetBase . '/js/' . rawurlencode('Doctor welcoming pacient.json');
?>

<button type="button" class="patient-tour-help-btn" id="patientTourHelpBtn"
    aria-label="Take a tour of your account" title="Take a tour of your account">
    <i class="fas fa-question-circle"></i>
</button>

<?php if (!defined('PATIENT_TOUR_ASSETS_LOADED')): ?>
<?php define('PATIENT_TOUR_ASSETS_LOADED', true); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.5/dist/driver.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($patientTourAssetBase, ENT_QUOTES, 'UTF-8'); ?>/css/patient_tour.css">
<script>
window.PATIENT_TOUR = {
    userId: <?php echo $patientTourUserId; ?>,
    autoStart: <?php echo $patientTourAutoStart ? 'true' : 'false'; ?>,
    forceStart: <?php echo $patientTourForceStart ? 'true' : 'false'; ?>,
    isDashboard: <?php echo $patientTourIsDashboard ? 'true' : 'false'; ?>,
    dashboardUrl: 'patient-dashboard.php?tour=1',
    animationUrl: <?php echo json_encode($patientTourAnimationUrl); ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.5/dist/driver.js.iife.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js" defer></script>
<script src="<?php echo htmlspecialchars($patientTourAssetBase, ENT_QUOTES, 'UTF-8'); ?>/js/patient_tour.js" defer></script>

<div class="patient-tour-intro-overlay" id="patientTourIntroModal" hidden>
    <div class="patient-tour-intro-card" role="dialog" aria-modal="true" aria-labelledby="patientTourIntroTitle">
        <div class="patient-tour-intro-body">
            <div class="patient-tour-intro-lottie" id="patientTourIntroLottie" aria-hidden="true"></div>
            <div class="patient-tour-intro-copy">
                <h2 id="patientTourIntroTitle">Welcome to your patient account</h2>
                <p>Would you like a quick tour of where to click? We will walk you through booking, records, billing, and more.</p>
            </div>
        </div>
        <div class="patient-tour-intro-footer">
            <button type="button" class="patient-tour-intro-skip" id="patientTourIntroSkip">Skip</button>
            <button type="button" class="patient-tour-intro-next" id="patientTourIntroNext">Next</button>
        </div>
    </div>
</div>
<?php endif; ?>
