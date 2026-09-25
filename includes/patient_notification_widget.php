<?php
/**
 * Patient Notification Bell Widget
 * Include this file inside .header-right to render a functional notification bell with dropdown.
 * Requires: $pdo (PDO), $_SESSION['user_id'] already set.
 */
require_once __DIR__ . '/notification_functions.php';
ensureNotificationSchema($pdo);

$notifUserId = (int)($_SESSION['user_id'] ?? 0);
$notifCount = $notifUserId ? getUnreadCount($pdo, $notifUserId) : 0;
$notifInitial = $notifUserId ? getNotifications($pdo, $notifUserId, 8) : [];
$notifLatestAt = !empty($notifInitial[0]['created_at']) ? $notifInitial[0]['created_at'] : null;
$notifSeenAt = $_SESSION['notif_panel_seen_at'] ?? null;
$notifSeenAt = (is_string($notifSeenAt) && trim($notifSeenAt) !== '') ? $notifSeenAt : null;
$notifUnseen = !$notifSeenAt || ($notifLatestAt && strtotime((string)$notifLatestAt) > strtotime((string)$notifSeenAt));
$notifRinging = $notifUnseen && $notifLatestAt && (time() - strtotime($notifLatestAt) < 60);
$notifShowBadge = $notifCount > 0 && $notifUnseen;
?>

<style>
    .notif-wrapper { position: relative; }
    .notif-bell-btn {
        position: relative; background: none; border: none; cursor: pointer;
        font-size: 20px; color: #6b7280; padding: 6px; border-radius: 8px;
        transition: background 0.15s, color 0.15s;
    }
    .notif-bell-btn:hover { background: #f3f4f6; color: #1f2937; }
    .notif-bell-btn.ringing > i {
        display: inline-block;
        transform-origin: top center;
        animation: notifBellRing 1.6s ease-in-out infinite;
    }
    @keyframes notifBellRing {
        0%  { transform: rotate(0deg); }
        8%  { transform: rotate(14deg); }
        16% { transform: rotate(-14deg); }
        24% { transform: rotate(14deg); }
        32% { transform: rotate(-14deg); }
        40%, 100% { transform: rotate(0deg); }
    }
    .notif-badge {
        position: absolute; top: 0; right: 0; min-width: 18px; height: 18px;
        background: #ef4444; color: #fff; font-size: 10px; font-weight: 700;
        border-radius: 999px; display: inline-flex; align-items: center;
        justify-content: center; padding: 0 4px; line-height: 1;
        pointer-events: none;
        transform: translate(40%, -30%);
    }
    .notif-badge[hidden] { display: none; }
    .notif-dropdown {
        display: none; position: absolute; top: calc(100% + 8px); right: 0;
        width: 370px; max-height: 440px; background: #fff; border: 1px solid #e5e7eb;
        border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.12); z-index: 99998;
        overflow: hidden; flex-direction: column;
    }
    .notif-dropdown.open { display: flex; }
    .notif-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 16px; border-bottom: 1px solid #f3f4f6;
    }
    .notif-header h4 { margin: 0; font-size: 15px; font-weight: 700; color: #1f2937; }
    .notif-mark-all {
        background: none; border: none; color: #2563eb; font-size: 12px;
        font-weight: 600; cursor: pointer; padding: 0;
    }
    .notif-mark-all:hover { text-decoration: underline; }
    .notif-list { overflow-y: auto; max-height: 370px; }
    .notif-item {
        display: flex; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #f9fafb;
        cursor: pointer; transition: background 0.12s; text-decoration: none; color: inherit;
    }
    .notif-item:hover { background: #f9fafb; }
    .notif-item.unread { background: #eff6ff; }
    .notif-item.unread:hover { background: #dbeafe; }
    .notif-icon {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 14px; color: #fff;
    }
    .notif-icon.queue { background: #f59e0b; }
    .notif-icon.reminder { background: #8b5cf6; }
    .notif-icon.payment { background: #14b8a6; }
    .notif-icon.inquiry { background: #3b82f6; }
    .notif-icon.appointment { background: #10b981; }
    .notif-icon.system { background: #6b7280; }
    .notif-icon.cancellation { background: #dc2626; }
    .notif-icon.patient { background: #06b6d4; }
    .notif-content { flex: 1; min-width: 0; }
    .notif-title { font-size: 13px; font-weight: 600; color: #1f2937; margin: 0; }
    .notif-msg { font-size: 12px; color: #6b7280; margin: 2px 0 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .notif-time { font-size: 11px; color: #9ca3af; margin-top: 3px; }
    .notif-empty {
        text-align: center; padding: 32px 16px; color: #9ca3af; font-size: 13px;
    }
    .notif-empty i { display: block; font-size: 28px; margin-bottom: 8px; color: #d1d5db; }
</style>
<link rel="stylesheet" href="../../assets/css/notif_dropdown_mobile.css">

<div class="notif-wrapper">
    <button type="button" class="notif-bell-btn<?php echo $notifRinging ? ' ringing' : ''; ?>" id="notifBellBtn" aria-label="Notifications">
        <i class="fas fa-bell"></i>
        <span class="notif-badge" id="notifBadge" <?php if (empty($notifShowBadge)) echo 'hidden'; ?>>
            <?php echo $notifCount > 99 ? '99+' : $notifCount; ?>
        </span>
    </button>
    <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
            <h4>Notifications</h4>
            <button type="button" class="notif-mark-all" id="notifMarkAllBtn">Mark all read</button>
        </div>
        <div class="notif-list" id="notifList">
            <?php if (empty($notifInitial)): ?>
                <div class="notif-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>
            <?php else: ?>
                <?php foreach ($notifInitial as $n): ?>
                    <?php
                    $typeClass = htmlspecialchars($n['type'] ?? 'system');
                    $iconMap = ['queue' => 'fa-layer-group', 'reminder' => 'fa-calendar-check', 'payment' => 'fa-credit-card', 'inquiry' => 'fa-comment-dots', 'appointment' => 'fa-calendar', 'cancellation' => 'fa-times-circle', 'patient' => 'fa-user-plus', 'system' => 'fa-info-circle'];
                    $icon = $iconMap[$n['type']] ?? 'fa-bell';
                    if (strcasecmp((string)($n['title'] ?? ''), 'Appointment Declined') === 0) {
                        $typeClass = 'cancellation';
                        $icon = 'fa-times';
                    }
                    $unread = empty($n['is_read']) ? ' unread' : '';
                    $href = !empty($n['action_url']) ? htmlspecialchars($n['action_url']) : '#';
                    $timeAgo = '';
                    $created = strtotime($n['created_at']);
                    $diff = time() - $created;
                    if ($diff < 60) $timeAgo = 'Just now';
                    elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
                    elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
                    else $timeAgo = floor($diff / 86400) . 'd ago';
                    ?>
                    <a class="notif-item<?php echo $unread; ?>" href="<?php echo $href; ?>" data-notif-id="<?php echo (int)$n['id']; ?>">
                        <div class="notif-icon <?php echo $typeClass; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                        <div class="notif-content">
                            <p class="notif-title"><?php echo htmlspecialchars($n['title']); ?></p>
                            <p class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></p>
                            <div class="notif-time"><?php echo $timeAgo; ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../../assets/js/notif_bell_ring.js"></script>
<script>
(function() {
    const btn = document.getElementById('notifBellBtn');
    const dropdown = document.getElementById('notifDropdown');
    const badge = document.getElementById('notifBadge');
    const list = document.getElementById('notifList');
    const markAllBtn = document.getElementById('notifMarkAllBtn');
    if (!btn || !dropdown) return;

    const actionsUrl = '../shared/notification_actions.php';
    var latestCreatedAt = <?php echo json_encode($notifLatestAt); ?>;
    var seenLatestAt = <?php echo json_encode($notifSeenAt); ?>;
    if (seenLatestAt && typeof stopNotifBellRing === 'function') {
        stopNotifBellRing(btn, seenLatestAt);
    }
    if (typeof syncNotifBellRing === 'function') {
        syncNotifBellRing(btn, latestCreatedAt);
    }

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        if (isOpen) {
            seenLatestAt = latestCreatedAt;
            if (typeof stopNotifBellRing === 'function') {
                stopNotifBellRing(btn, seenLatestAt);
            } else {
                btn.classList.remove('ringing');
            }
            badge.hidden = true;
            fetch(actionsUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mark_panel_seen&latest_created_at=' + encodeURIComponent(seenLatestAt || '')
            }).catch(function(){});
            loadNotifications();
        }
    });

    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    list.addEventListener('click', function(e) {
        const item = e.target.closest('.notif-item');
        if (!item) return;
        e.preventDefault();
        const id = item.dataset.notifId;
        if (item.classList.contains('unread')) {
            fetch(actionsUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mark_read&id=' + id
            }).then(function() {
                item.classList.remove('unread');
                updateBadge();
            });
        }
        const href = item.getAttribute('href');
        if (href && href !== '#') {
            dropdown.classList.remove('open');
            window.location.href = href;
        }
    });

    markAllBtn.addEventListener('click', function() {
        fetch(actionsUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mark_all_read'
        }).then(function() {
            list.querySelectorAll('.notif-item.unread').forEach(function(el) {
                el.classList.remove('unread');
            });
            updateBadge();
        });
    });

    function updateBadge() {
        fetch(actionsUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_unread_count'
        }).then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                const c = data.count;
                latestCreatedAt = data.latest_created_at || latestCreatedAt;
                if (typeof syncNotifBellRing === 'function') {
                    syncNotifBellRing(btn, latestCreatedAt);
                }
                if (c > 0 && seenLatestAt && typeof notifCreatedAtIsNewer === 'function' && !notifCreatedAtIsNewer(latestCreatedAt, seenLatestAt)) {
                    badge.hidden = true;
                } else if (c > 0) {
                    badge.textContent = c > 99 ? '99+' : String(c);
                    badge.hidden = false;
                } else {
                    badge.hidden = true;
                }
            }
        }).catch(function(){});
    }

    function loadNotifications() {
        fetch(actionsUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_notifications'
        }).then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.notifications) return;
            var notifs = data.notifications;
            latestCreatedAt = notifs.length ? notifs[0].created_at : latestCreatedAt;
            if (typeof syncNotifBellRing === 'function') {
                syncNotifBellRing(btn, latestCreatedAt);
            }
            if (!notifs.length) {
                list.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>';
                return;
            }
            var iconMap = {queue:'fa-layer-group',reminder:'fa-calendar-check',payment:'fa-credit-card',inquiry:'fa-comment-dots',appointment:'fa-calendar',cancellation:'fa-times-circle',patient:'fa-user-plus',system:'fa-info-circle'};
            list.innerHTML = notifs.map(function(n) {
                var typeClass = n.type || 'system';
                var icon = iconMap[n.type] || 'fa-bell';
                if (String(n.title || '').toLowerCase() === 'appointment declined') {
                    typeClass = 'cancellation';
                    icon = 'fa-times';
                }
                var unread = n.is_read == 0 ? ' unread' : '';
                var href = n.action_url || '#';
                var d = new Date(n.created_at + 'Z');
                var diff = (Date.now() - d.getTime()) / 1000;
                var timeAgo = 'Just now';
                if (diff >= 86400) timeAgo = Math.floor(diff/86400) + 'd ago';
                else if (diff >= 3600) timeAgo = Math.floor(diff/3600) + 'h ago';
                else if (diff >= 60) timeAgo = Math.floor(diff/60) + 'm ago';
                var escDiv = document.createElement('div');
                escDiv.textContent = n.title;
                var title = escDiv.innerHTML;
                escDiv.textContent = n.message;
                var msg = escDiv.innerHTML;
                return '<a class="notif-item' + unread + '" href="' + href + '" data-notif-id="' + n.id + '">'
                    + '<div class="notif-icon ' + typeClass + '"><i class="fas ' + icon + '"></i></div>'
                    + '<div class="notif-content"><p class="notif-title">' + title + '</p>'
                    + '<p class="notif-msg">' + msg + '</p>'
                    + '<div class="notif-time">' + timeAgo + '</div></div></a>';
            }).join('');
        }).catch(function(){});
    }

    setInterval(updateBadge, 30000);
})();
</script>
<?php require __DIR__ . '/new_device_login_modal.php'; ?>
