<?php
if (empty($_SESSION['cancellation_review_csrf'])) $_SESSION['cancellation_review_csrf'] = bin2hex(random_bytes(32));
?>
<script>window.CANCELLATION_REVIEW_CSRF = <?php echo json_encode($_SESSION['cancellation_review_csrf']); ?>;</script>
<link rel="stylesheet" href="../../assets/css/cancellation-requests.css?v=2" data-persist-layout="1">
<script src="../../assets/js/cancellation-requests.js?v=2"></script>
