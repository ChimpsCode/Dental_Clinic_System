<?php
?>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/admin.js"></script>
    <?php if (isset($pageScript)) { echo $pageScript; } ?>
    <script>
        (function() {
            const LOGOUT_AFTER_MS = 10 * 60 * 1000;
            let timer;
            const reset = () => {
                clearTimeout(timer);
                timer = setTimeout(() => window.location.href = 'logout.php', LOGOUT_AFTER_MS);
            };
            ['click','mousemove','keydown','scroll','touchstart'].forEach(evt =>
                document.addEventListener(evt, reset, { passive: true })
            );
            reset();
        })();
    </script>
    <script>
        // Ensure user profile dropdown works
        document.addEventListener('DOMContentLoaded', function() {
            const userProfile = document.getElementById('userProfileDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (userProfile) {
                // Toggle dropdown on click
                userProfile.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userProfile.classList.toggle('active');
                    if (notificationDropdown) {
                        notificationDropdown.classList.remove('active');
                    }
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!userProfile.contains(e.target)) {
                        userProfile.classList.remove('active');
                    }
                });
                
                // Close dropdown on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && userProfile.classList.contains('active')) {
                        userProfile.classList.remove('active');
                    }
                });
            }

            if (notificationDropdown) {
                const bellButton = notificationDropdown.querySelector('.notification-bell');
                if (bellButton) {
                    bellButton.addEventListener('click', function(e) {
                        e.stopPropagation();
                        notificationDropdown.classList.toggle('active');
                        if (userProfile) {
                            userProfile.classList.remove('active');
                        }
                    });
                }

                document.addEventListener('click', function(e) {
                    if (!notificationDropdown.contains(e.target)) {
                        notificationDropdown.classList.remove('active');
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && notificationDropdown.classList.contains('active')) {
                        notificationDropdown.classList.remove('active');
                    }
                });
            }

        });
    </script>
    <script>
        // Mark single notification as read
        function markNotificationRead(notificationId, actionUrl, event) {
            if (event) {
                event.stopPropagation();
            }
            fetch('notification_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_read&id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[data-notif-id="${notificationId}"]`);
                    if (item) {
                        item.classList.remove('unread');
                        item.classList.add('read');
                        item.setAttribute('data-is-read', '1');
                        const kebabMenu = item.querySelector('.kebab-menu');
                        if (kebabMenu) {
                            const markReadBtn = kebabMenu.querySelector('button:not(.delete-btn)');
                            if (markReadBtn) {
                                markReadBtn.remove();
                            }
                        }
                        updateNotificationCounts();
                    }
                    if (actionUrl && !event) {
                        window.location.href = actionUrl;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (actionUrl) {
                    window.location.href = actionUrl;
                }
            });
        }

        // Delete notification
        function deleteNotification(notificationId, event) {
            if (event) {
                event.stopPropagation();
            }
            if (!confirm('Are you sure you want to delete this notification?')) {
                return;
            }
            fetch('notification_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete&id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[data-notif-id="${notificationId}"]`);
                    if (item) {
                        item.remove();
                    }
                    updateNotificationCounts();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Toggle kebab menu
        function toggleKebabMenu(event, btn) {
            event.stopPropagation();
            const menu = btn.nextElementSibling;
            document.querySelectorAll('.kebab-menu.show').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            menu.classList.toggle('show');
        }

        // Close kebab menus when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('.kebab-menu.show').forEach(m => {
                m.classList.remove('show');
            });
        });

        // Filter notifications by tab
        function filterNotifications(tab, btn) {
            document.querySelectorAll('.notification-tab').forEach(t => {
                t.classList.remove('active');
            });
            btn.classList.add('active');

            const items = document.querySelectorAll('.notification-item[data-notif-id]');
            items.forEach(item => {
                const isRead = item.getAttribute('data-is-read') === '1';
                if (tab === 'all') {
                    item.style.display = 'flex';
                } else if (tab === 'unread') {
                    item.style.display = isRead ? 'none' : 'flex';
                }
            });
        }

        // Update notification counts
        function updateNotificationCounts() {
            const allItems = document.querySelectorAll('.notification-item[data-notif-id]');
            const unreadItems = document.querySelectorAll('.notification-item[data-notif-id][data-is-read="0"]');
            
            const allTab = document.querySelector('.notification-tab[data-tab="all"] .tab-count');
            const unreadTab = document.querySelector('.notification-tab[data-tab="unread"] .tab-count');
            
            if (allTab) allTab.textContent = allItems.length;
            if (unreadTab) unreadTab.textContent = unreadItems.length;
        }

        // Mark all notifications as read
        function markAllNotificationsRead() {
            fetch('notification_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                location.reload();
            });
        }
    </script>
</body>
</html>
