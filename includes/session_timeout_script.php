<?php
/**
 * Shared inactivity-timeout script tag.
 * Callers may set $__stoEnabled and $__stoMs before including this file.
 */
if (!isset($__stoEnabled)) {
    $__stoEnabled = true;
}
if (!isset($__stoMs)) {
    $__stoMs = (int)($__sessionTimeoutSeconds ?? 600) * 1000;
}
$__stoLogoutUrl = $__stoLogoutUrl ?? '../../logout.php?reason=inactivity';
$__stoPingUrl = $__stoPingUrl ?? '../../session_ping.php';
$__stoLoginUrl = $__stoLoginUrl ?? '../../master/welcome-page/login.php?expired=1';
?>
<script src="../../assets/js/session_timeout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initSessionTimeout === 'function') {
        initSessionTimeout({
            enabled: <?php echo $__stoEnabled ? 'true' : 'false'; ?>,
            logoutAfterMs: <?php echo (int)$__stoMs; ?>,
            logoutUrl: <?php echo json_encode($__stoLogoutUrl); ?>,
            pingUrl: <?php echo json_encode($__stoPingUrl); ?>,
            loginUrl: <?php echo json_encode($__stoLoginUrl); ?>
        });
    }
});
</script>
