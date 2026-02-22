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
</body>
</html>
<?php
ob_end_flush();
?>
