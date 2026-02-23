<?php
/**
 * Staff Layout - End wrapper
 * This file closes content area and main content tags opened by staff_layout_start.php
 */
?>
            </div>
        </div>
    </main>

    <script>
        // Notification dropdown toggle
        (function() {
            const notificationDropdown = document.getElementById('notificationDropdown');
            if (notificationDropdown) {
                const bellButton = notificationDropdown.querySelector('.notification-bell');
                if (bellButton) {
                    bellButton.addEventListener('click', function(e) {
                        e.stopPropagation();
                        notificationDropdown.classList.toggle('active');
                    });
                }
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!notificationDropdown.contains(e.target)) {
                        notificationDropdown.classList.remove('active');
                    }
                });
                
                // Close dropdown on Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && notificationDropdown.classList.contains('active')) {
                        notificationDropdown.classList.remove('active');
                    }
                });
            }
        })();
    </script>
    <script src="assets/js/dashboard.js"></script>
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
<?php
ob_end_flush();
?>
