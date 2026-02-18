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
    <div id="firstLoginModal" class="first-login-overlay" aria-hidden="true">
        <div class="first-login-modal" role="dialog" aria-modal="true" aria-labelledby="firstLoginTitle">
            <h2 id="firstLoginTitle">Welcome to the System!</h2>
            <p>This appears to be your first login. For security purposes, please update your password and review your account details before proceeding.</p>
            <button type="button" class="btn-primary" id="firstLoginOkBtn">Okay</button>
        </div>
    </div>
</body>
</html>
<?php
ob_end_flush();
?>
