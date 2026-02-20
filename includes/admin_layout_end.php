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
</body>
</html>
