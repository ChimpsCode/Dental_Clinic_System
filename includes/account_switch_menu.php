<?php
require_once __DIR__ . '/account_switch_helper.php';
$switchTargets = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) $switchTargets = clinicSwitchTargets($pdo, (int)($_SESSION['user_id'] ?? 0));
} catch (Throwable $e) {
    error_log('Could not load linked clinic accounts: ' . $e->getMessage());
}
if ($switchTargets):
    if (empty($_SESSION['clinic_switch_csrf'])) $_SESSION['clinic_switch_csrf'] = bin2hex(random_bytes(32));
    $currentStmt = $pdo->prepare('SELECT id, username, first_name, last_name, role, profile_picture FROM users WHERE id = ? LIMIT 1');
    $currentStmt->execute([(int)$_SESSION['user_id']]);
    $switchCurrent = $currentStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'id' => (int)$_SESSION['user_id'], 'username' => $_SESSION['username'] ?? 'Current account',
        'first_name' => $_SESSION['user_name'] ?? '', 'last_name' => '',
        'role' => $_SESSION['role'] ?? '', 'profile_picture' => $_SESSION['profile_picture'] ?? '',
    ];
    $switchAccounts = array_merge([$switchCurrent], $switchTargets);
?>
<div class="clinic-switch-menu" onclick="event.stopPropagation()">
    <div class="clinic-switch-heading">Switch profile</div>
    <?php foreach ($switchAccounts as $switchAccount):
        $switchRole = switchableClinicRole($switchAccount['role'] ?? null) ?? '';
        $switchName = trim(($switchAccount['first_name'] ?? '') . ' ' . ($switchAccount['last_name'] ?? ''));
        if ($switchRole === 'dentist' && stripos($switchName, 'Dr.') !== 0) $switchName = 'Dr. ' . $switchName;
        if ($switchName === '' || $switchName === 'Dr. ') $switchName = (string)($switchAccount['username'] ?? ucfirst($switchRole));
        $switchPicture = (string)($switchAccount['profile_picture'] ?? '');
        $switchPictureSafe = preg_match('~^(?:assets|uploads)/[A-Za-z0-9_./-]+$~', $switchPicture) && !str_contains($switchPicture, '..');
        $isCurrentSwitch = (int)$switchAccount['id'] === (int)$_SESSION['user_id'];
    ?>
    <button type="button" class="clinic-switch-profile<?php echo $isCurrentSwitch ? ' current' : ''; ?>" <?php if (!$isCurrentSwitch): ?>onclick="switchClinicProfile(event, <?php echo (int)$switchAccount['id']; ?>)"<?php else: ?>disabled aria-current="true"<?php endif; ?>>
        <?php if ($switchPictureSafe): ?><img src="../../<?php echo htmlspecialchars($switchPicture, ENT_QUOTES); ?>" alt="" class="clinic-switch-avatar">
        <?php else: ?><span class="clinic-switch-avatar clinic-switch-initial" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($switchName, 0, 1))); ?></span><?php endif; ?>
        <span class="clinic-switch-identity"><span class="clinic-switch-name"><?php echo htmlspecialchars($switchName); ?></span><small><?php echo htmlspecialchars(ucfirst($switchRole)); ?></small></span>
        <?php if ($isCurrentSwitch): ?><span class="clinic-switch-check" aria-label="Current account">✓</span><?php endif; ?>
    </button>
    <?php endforeach; ?>
    <div class="clinic-switch-error" role="alert" hidden></div>
</div>
<div class="dropdown-divider"></div>
<style>
.clinic-switch-menu { padding: 10px 10px 2px; color: #1f2937; }
.clinic-switch-heading { padding: 4px 8px 7px; color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.clinic-switch-profile { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px; border: 0; border-radius: 10px; background: transparent; color: #1f2937; text-align: left; cursor: pointer; font: inherit; }
.clinic-switch-profile:hover:not(:disabled) { background: #eff6ff; }
.clinic-switch-profile.current { background: #f8fafc; cursor: default; }
.clinic-switch-avatar { width: 36px; height: 36px; flex: 0 0 36px; border-radius: 50%; object-fit: cover; }
.clinic-switch-initial { display: flex; align-items: center; justify-content: center; background: #dbeafe; color: #1d4ed8; font-size: 14px; font-weight: 700; }
.clinic-switch-identity { display: flex; flex: 1; min-width: 0; flex-direction: column; line-height: 1.3; }
.clinic-switch-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; font-weight: 600; }
.clinic-switch-identity small { color: #64748b; font-size: 11px; }
.clinic-switch-check { color: #2563eb; font-weight: 700; }
.clinic-switch-error { margin: 6px 8px; color: #b91c1c; font-size: 12px; }
</style>
<script>
window.switchClinicProfile = async function (event, id) {
    event.stopPropagation();
    const button = event.currentTarget;
    const error = button.closest('.clinic-switch-menu').querySelector('.clinic-switch-error');
    error.hidden = true; button.disabled = true;
    try {
        const body = new FormData();
        body.set('action', 'switch'); body.set('id', String(id));
        body.set('csrf', <?php echo json_encode($_SESSION['clinic_switch_csrf'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        const response = await fetch('../shared/switch_account.php', {method: 'POST', body});
        const data = await response.json();
        if (!response.ok || !data.success || !data.redirect) throw new Error(data.message || 'Could not switch profiles.');
        window.location.assign(data.redirect);
    } catch (e) {
        error.textContent = e.message || 'Could not switch profiles.'; error.hidden = false;
        button.disabled = false;
    }
};
</script>
<?php endif; ?>
