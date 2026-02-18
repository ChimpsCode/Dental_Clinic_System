<?php
/**
 * Admin Layout - End wrapper
 * This file closes the content area and main content tags opened by admin_layout_start.php
 */
?>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/admin.js"></script>
    <?php if (isset($pageScript)) { echo $pageScript; } ?>
    <div id="firstLoginModal" class="first-login-overlay" aria-hidden="true">
        <div class="first-login-modal" role="dialog" aria-modal="true" aria-labelledby="firstLoginTitle">
            <h2 id="firstLoginTitle">Welcome to the System!</h2>
            <p>This appears to be your first login. For security purposes, please update your password and review your account details before proceeding.</p>
            <button type="button" class="btn-primary" id="firstLoginOkBtn">Okay</button>
        </div>
    </div>
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
