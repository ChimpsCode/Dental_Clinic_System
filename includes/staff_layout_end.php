<?php


?>
            </div>
        </div>
    </main>


    <script src="../../assets/js/dashboard.js"></script>
    <script src="../../assets/js/notif_bell_ring.js"></script>
    <script>
        // ── Notification actions URL (works from any master/* subdirectory) ──
        const _notifActionsUrl = (function() {
            const parts = window.location.pathname.split('/');
            const masterIdx = parts.lastIndexOf('master');
            if (masterIdx >= 0) {
                parts.splice(masterIdx + 1);
                return parts.join('/') + '/shared/notification_actions.php';
            }
            return 'notification_actions.php';
        })();

    </script>
    <script>
    (function() {
        var btn        = document.getElementById('staffNotifBellBtn');
        var dropdown   = document.getElementById('staffNotifDropdown');
        var badge      = document.getElementById('staffNotifBadge');
        var list       = document.getElementById('staffNotifList');
        var markAllBtn = document.getElementById('staffNotifMarkAllBtn');
        if (!btn || !dropdown) return;

        var actionsUrl     = _notifActionsUrl;
        var lastCount      = parseInt((badge && badge.textContent) || '0', 10) || 0;
        var latestCreatedAt = <?php echo json_encode($notifLatestAt ?? null); ?>;
        var seenLatestAt = <?php echo json_encode($notifSeenAt ?? null); ?>;
        if (seenLatestAt && typeof stopNotifBellRing === 'function') {
            stopNotifBellRing(btn, seenLatestAt);
        }
        if (typeof syncNotifBellRing === 'function') {
            syncNotifBellRing(btn, latestCreatedAt);
        }
        var cachedHtml     = '';   // last rendered list — show instantly on open
        var fetchInFlight  = false;
        var pollInFlight   = false;

        var iconMap = {queue:'fa-layer-group',reminder:'fa-calendar-check',payment:'fa-credit-card',
            inquiry:'fa-comment-dots',appointment:'fa-calendar',cancellation:'fa-times-circle',
            patient:'fa-user-plus',system:'fa-info-circle'};

        function timeAgo(dateStr) {
            var d    = new Date(dateStr + 'Z');
            var diff = (Date.now() - d.getTime()) / 1000;
            if (diff < 60)    return 'Just now';
            if (diff < 3600)  return Math.floor(diff / 60)   + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return Math.floor(diff / 86400) + 'd ago';
        }
        function esc(s) {
            var d = document.createElement('div');
            d.textContent = String(s || '');
            return d.innerHTML;
        }
        function destForType(type) {
            switch (String(type || 'system').toLowerCase()) {
                case 'appointment':
                case 'reminder':
                case 'cancellation':
                    return 'staff_appointments.php';
                case 'patient':
                    return 'staff_patient_records.php';
                case 'queue':
                    return 'staff_queue.php';
                case 'inquiry':
                    return 'staff_inquiries.php';
                case 'payment':
                    return 'staff_payment.php';
                default:
                    return 'staff-dashboard.php';
            }
        }
        function renderList(notifs) {
            if (!notifs.length)
                return '<div class="notif-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>';
            return notifs.map(function(n) {
                var type = n.type || 'system';
                var href = destForType(type);
                return '<a class="notif-item' + (n.is_read == 0 ? ' unread' : '') + '" href="' + href + '" data-notif-id="' + n.id + '" data-notif-type="' + esc(type) + '">'
                     + '<div class="notif-icon ' + type + '"><i class="fas ' + (iconMap[n.type] || 'fa-bell') + '"></i></div>'
                     + '<div class="notif-content"><p class="notif-title">' + esc(n.title) + '</p>'
                     + '<p class="notif-msg">' + esc(n.message) + '</p>'
                     + '<div class="notif-time">' + timeAgo(n.created_at) + '</div></div></a>';
            }).join('');
        }
        function setBadge(c, latestAt) {
            lastCount = c;
            if (latestAt) latestCreatedAt = latestAt;
            if (c > 0 && seenLatestAt && typeof notifCreatedAtIsNewer === 'function' && !notifCreatedAtIsNewer(latestCreatedAt, seenLatestAt)) {
                badge.hidden = true;
                return;
            }
            if (c > 0) { badge.textContent = c > 99 ? '99+' : c; badge.hidden = false; }
            else        { badge.hidden = true; }
        }

        // ── Fetch fresh list from server ──────────────────────────────────
        function fetchList(callback) {
            if (fetchInFlight) return;
            fetchInFlight = true;
            fetch(actionsUrl, { method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=get_notifications'
            }).then(function(r){ return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                var notifs = data.notifications || [];
                var latestAt = notifs.length ? notifs[0].created_at : null;
                if (latestAt) latestCreatedAt = latestAt;
                if (typeof syncNotifBellRing === 'function') {
                    syncNotifBellRing(btn, latestCreatedAt);
                }
                cachedHtml = renderList(notifs);
                var unread = notifs.filter(function(n){ return n.is_read == 0; }).length;
                setBadge(unread, latestAt);
                if (callback) callback(cachedHtml);
                else list.innerHTML = cachedHtml;
            }).catch(function(){})
            .finally(function(){ fetchInFlight = false; });
        }

        // ── Bell click: open immediately with cached content, refresh behind ─
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var opening = !dropdown.classList.contains('open');
            dropdown.classList.toggle('open');
            if (!opening) return;

            seenLatestAt = latestCreatedAt;
            if (typeof stopNotifBellRing === 'function') {
                stopNotifBellRing(btn, seenLatestAt);
            } else {
                btn.classList.remove('ringing');
            }
            if (badge) badge.hidden = true;
            fetch(actionsUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mark_panel_seen&latest_created_at=' + encodeURIComponent(seenLatestAt || '')
            }).catch(function(){});

            // Show cached content right away (zero delay)
            if (cachedHtml) {
                list.innerHTML = cachedHtml;
            }
            // Always fetch fresh in background — replace when ready
            fetchList(function(html) { list.innerHTML = html; });
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !btn.contains(e.target))
                dropdown.classList.remove('open');
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') dropdown.classList.remove('open');
        });

        // ── Item click: mark read + navigate ─────────────────────────────
        list.addEventListener('click', function(e) {
            var item = e.target.closest('.notif-item');
            if (!item) return;
            e.preventDefault();
            var id = item.getAttribute('data-notif-id');
            var href = item.getAttribute('href') || destForType(item.getAttribute('data-notif-type'));
            if (item.classList.contains('unread') && id) {
                item.classList.remove('unread');
                fetch(actionsUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=mark_read&id=' + encodeURIComponent(id)
                }).catch(function(){});
            }
            if (href && href !== '#') {
                dropdown.classList.remove('open');
                window.location.href = href;
            }
        });

        // ── Mark all read ─────────────────────────────────────────────────
        markAllBtn.addEventListener('click', function() {
            fetch(actionsUrl, { method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=mark_all_read'
            }).then(function() {
                list.querySelectorAll('.notif-item.unread').forEach(function(el){ el.classList.remove('unread'); });
                setBadge(0);
                cachedHtml = list.innerHTML;
            });
        });

        // ── Background poll (badge only, every 15 s) ──────────────────────
        function pollBadge() {
            if (pollInFlight) return;
            pollInFlight = true;
            fetch(actionsUrl, { method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=get_unread_count'
            }).then(function(r){ return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                var c = Number(data.count || 0);
                var prev = lastCount;
                setBadge(c, data.latest_created_at || null);
                if (typeof syncNotifBellRing === 'function') {
                    syncNotifBellRing(btn, data.latest_created_at || null);
                }
                if (c !== prev) {
                    if (c > prev && document.hidden && 'Notification' in window && Notification.permission === 'granted') {
                        new Notification('RF Dental Clinic', {
                            body: 'You have ' + c + ' new notification' + (c > 1 ? 's' : ''),
                            icon: '/assets/images/Logo.png'
                        });
                    }
                    fetchList(function(html) {
                        cachedHtml = html;
                        if (dropdown.classList.contains('open')) list.innerHTML = html;
                    });
                }
            }).catch(function(){})
            .finally(function(){ pollInFlight = false; });
        }

        if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();

        // Warm up cache immediately on page load
        fetchList(null);
        setInterval(pollBadge, 3000);
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) pollBadge();
        });
    })();
    </script>
    <?php require __DIR__ . '/session_timeout_script.php'; ?>
    <?php require __DIR__ . '/app_notice_modal_script.php'; ?>
    <script src="../../assets/js/logout_confirm.js?v=3"></script>
    <?php require __DIR__ . '/new_device_login_modal.php'; ?>
</body>
</html>
<?php
ob_end_flush();
?>
