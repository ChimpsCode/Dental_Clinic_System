<?php
/**
 * Staff Layout - End wrapper
 * This file closes content area and main content tags opened by staff_layout_start.php
 */
?>
            </div>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
    <script>
        // Auto logout after 10 minutes of inactivity (client-side)
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
</body>
</html>
<?php
ob_end_flush();
?>
