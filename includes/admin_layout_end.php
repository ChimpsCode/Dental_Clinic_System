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
    <script>
        // Ensure user profile dropdown works
        document.addEventListener('DOMContentLoaded', function() {
            const userProfile = document.getElementById('userProfileDropdown');
            
            if (userProfile) {
                // Toggle dropdown on click
                userProfile.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userProfile.classList.toggle('active');
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
        });
    </script>
</body>
</html>
