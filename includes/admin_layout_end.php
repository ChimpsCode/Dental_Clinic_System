<?php
// Pjax fragment mode: output only the closing content-area div and stop.
// The layout shell (sidebar, header, scripts) is already in the parent document.
$__pjax = isset($_GET['__pjax']) && $_GET['__pjax'] === '1';
if ($__pjax) {
    echo "        </div>\n";
    return;
}
?>
        </div>
    </main>

    <script src="../../assets/js/dashboard.js"></script>
    <script src="../../assets/js/admin.js"></script>
    <script src="../../assets/js/admin_router.js?v=2"></script>
    <?php if (isset($pageScript)) { echo $pageScript; } ?>
    <?php require __DIR__ . '/session_timeout_script.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userProfile = document.getElementById('userProfileDropdown');
            const notifDropdown = document.getElementById('adminNotifDropdown');

            if (userProfile) {
                userProfile.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userProfile.classList.toggle('active');
                    if (notifDropdown) notifDropdown.classList.remove('open');
                });
                document.addEventListener('click', function(e) {
                    if (!userProfile.contains(e.target)) {
                        userProfile.classList.remove('active');
                    }
                });
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && userProfile.classList.contains('active')) {
                        userProfile.classList.remove('active');
                    }
                });
            }
        });
    </script>
    <script>
        const _notifActionsUrl = (function() {
            const parts = window.location.pathname.split('/');
            const masterIdx = parts.lastIndexOf('master');
            if (masterIdx >= 0) {
                parts.splice(masterIdx + 1);
                return parts.join('/') + '/shared/notification_actions.php';
            }
            return '../shared/notification_actions.php';
        })();
    </script>
    <script>
    (function() {
        var btn        = document.getElementById('adminNotifBellBtn');
        var dropdown   = document.getElementById('adminNotifDropdown');
        var badge      = document.getElementById('adminNotifBadge');
        var list       = document.getElementById('adminNotifList');
        var markAllBtn = document.getElementById('adminNotifMarkAllBtn');
        if (!btn || !dropdown) return;

        var actionsUrl    = _notifActionsUrl;
        var lastCount     = parseInt((badge && badge.textContent) || '0', 10) || 0;
        var cachedHtml    = '';
        var fetchInFlight = false;
        var pollInFlight  = false;

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
                    return 'admin_appointments.php';
                case 'patient':
                    return 'admin_patients.php';
                case 'queue':
                    return 'admin_queue.php';
                case 'payment':
                    return 'admin_payment.php';
                default:
                    return 'admin_dashboard.php';
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
        function setBadge(c) {
            lastCount = c;
            if (!badge) return;
            if (c > 0) { badge.textContent = c > 99 ? '99+' : c; badge.hidden = false; }
            else        { badge.hidden = true; }
        }

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
                cachedHtml = renderList(notifs);
                var unread = notifs.filter(function(n){ return n.is_read == 0; }).length;
                setBadge(unread);
                if (callback) callback(cachedHtml);
                else list.innerHTML = cachedHtml;
            }).catch(function(){})
            .finally(function(){ fetchInFlight = false; });
        }

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var userProfile = document.getElementById('userProfileDropdown');
            if (userProfile) userProfile.classList.remove('active');
            var opening = !dropdown.classList.contains('open');
            dropdown.classList.toggle('open');
            if (!opening) return;
            if (cachedHtml) list.innerHTML = cachedHtml;
            fetchList(function(html) { list.innerHTML = html; });
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !btn.contains(e.target))
                dropdown.classList.remove('open');
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') dropdown.classList.remove('open');
        });

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

        if (markAllBtn) {
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
        }

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
                setBadge(c);
                if (c !== prev) {
                    fetchList(function(html) {
                        cachedHtml = html;
                        if (dropdown.classList.contains('open')) list.innerHTML = html;
                    });
                }
            }).catch(function(){})
            .finally(function(){ pollInFlight = false; });
        }

        fetchList(null);
        setInterval(pollBadge, 3000);
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) pollBadge();
        });
    })();
    </script>
</body>
</html>
