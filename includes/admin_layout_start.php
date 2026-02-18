<?php
/**
 * Admin Layout - Centralized layout wrapper for all admin pages
 * Ensures strict role-based separation: only admins can access admin pages
 * and the Admin Sidebar is always displayed regardless of which admin page is loaded.
 */

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

// Get user info from session
$username = $_SESSION['username'] ?? 'Admin';
$fullName = $_SESSION['full_name'] ?? 'Administrator';

// Display only first name in header
$displayNameSource = trim($fullName) !== '' ? $fullName : $username;
$firstName = preg_split('/\s+/', trim($displayNameSource))[0] ?? $displayNameSource;
 
// Header notifications (lightweight counts)
$newAppointmentsToday = 0;
$pendingPaymentsCount = 0;
try {
    if (isset($pdo)) {
        $newAppointmentsToday = (int)($pdo->query("
            SELECT COUNT(*)
            FROM appointments
            WHERE appointment_date = CURDATE()
        ")->fetchColumn() ?? 0);

        $pendingPaymentsCount = (int)($pdo->query("
            SELECT COUNT(*)
            FROM billing
            WHERE payment_status IN ('pending', 'unpaid', 'partial')
               OR (balance IS NOT NULL AND balance > 0)
        ")->fetchColumn() ?? 0);
    }
} catch (Exception $e) {
    $newAppointmentsToday = 0;
    $pendingPaymentsCount = 0;
}

$notificationTotal = $newAppointmentsToday + $pendingPaymentsCount;

// Get page title for the header
$pageTitle = $pageTitle ?? 'Admin Dashboard';

// Helper function to check if current page matches
function isActivePage($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return $currentPage === $page;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic Admin</title>
    <link rel="icon" type="image/png" href="assets/images/Logo.png">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body data-user-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>" data-first-login="<?php echo !empty($_SESSION['first_login']) ? '1' : '0'; ?>">
    <!-- Add/Edit User Modal -->
    <div id="userModal" class="modal-overlay">
        <div class="modal user-modal">
            <h2 id="modalTitle">Add New User</h2>
            <p class="modal-subtitle">Enter user details to add a new system user</p>
            
            <form id="userForm">
                <input type="hidden" id="userId">
                
                <div class="form-grid">
                    <input type="text" id="firstName" class="form-control" placeholder="First Name" required>
                    <input type="text" id="middleName" class="form-control" placeholder="Middle Name (Optional)">
                </div>

                <input type="text" id="lastName" class="form-control" placeholder="Last Name" required>

                <div class="form-grid">
                    <input type="text" id="username" class="form-control" placeholder="Username" required>
                    <input type="email" id="email" class="form-control" placeholder="Email" required>
                </div>
                
                <div class="form-grid">
                    <div class="input-group">
                        <input type="password" id="password" class="form-control" placeholder="Password">
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility" style="display: none;">
                            <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="input-group">
                        <input type="password" id="confirmPassword" class="form-control" placeholder="Confirm Password">
                        <button type="button" class="toggle-password" id="toggleConfirmPassword" aria-label="Toggle confirm password visibility" style="display: none;">
                            <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <select id="role" class="form-control" required>
                    <option value="">Select Role</option>
                    <option value="dentist">Dentist</option>
                    <option value="staff">Staff</option>
                </select>

                <select id="status" class="form-control" required data-edit-only="true">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Save User</button>
                    <button type="button" class="btn-cancel" onclick="closeUserModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Left Sidebar - Admin Navigation -->
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-logo">
            <img src="assets/images/Logo.png" alt="RF Logo">
            <span>RF Dental Clinic</span>
        </div>
        
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item <?php echo isActivePage('admin_dashboard.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M3 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm0 8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm10 0a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1zm1-17a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/>
                    </svg>
                </span>
                <span>Dashboard</span>
            </a>
            
            <a href="admin_users.php" class="nav-item <?php echo isActivePage('admin_users.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </span>
                <span>User Management</span>
            </a>

            <a href="admin_queue.php" class="nav-item <?php echo isActivePage('admin_queue.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M4 6H2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm6 12v-2H8v2h2zm2-2h2v2h-2v-2zm0-3a1 1 0 0 0 1-1H7a1 1 0 0 0-1 1v3h10V7a1 1 0 0 0-1-1h-5a1 1 0 0 0-1 1z"/>
                    </svg>
                </span>
                <span>Queue Management</span>
            </a>
            
            <a href="admin_patients.php" class="nav-item <?php echo isActivePage('admin_patients.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5S5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05c1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5"/>
                    </svg>
                </span>
                <span>Patient Records</span>
            </a>
            
            <a href="admin_appointments.php" class="nav-item <?php echo isActivePage('admin_appointments.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
                    </svg>
                </span>
                <span>Appointments</span>
            </a>
            
            <a href="admin_payment.php" class="nav-item <?php echo isActivePage('admin_payment.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                    </svg>  
                </span>
                <span>Payment</span>
            </a>
            
            <a href="admin_services.php" class="nav-item <?php echo isActivePage('admin_services.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/>
                    </svg>
                </span>
                <span>Services List</span>
            </a>
            
            <a href="admin_analytics.php" class="nav-item <?php echo isActivePage('admin_analytics.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M3 22V8h4v14zm7 0V2h4v20zm7 0v-8h4v8z"/>
                    </svg>
                </span>
                <span>Analytics</span>
            </a>
            
            <a href="admin_reports.php" class="nav-item <?php echo isActivePage('admin_reports.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                    </svg>
                </span>
                <span>Reports</span>
            </a>
            
        
            
            <a href="admin_audit_trail.php" class="nav-item <?php echo isActivePage('admin_audit_trail.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                </span>
                <span>Audit Trail</span>
            </a>

            <a href="admin_archive.php" class="nav-item <?php echo isActivePage('admin_archive.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5zM5.12 5l.81-1h12l.94 1H5.12z"/>
                    </svg>
                </span>
                <span>Archive</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Sidebar Overlay for mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle sidebar" aria-controls="adminSidebar">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="header-title">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p>Admin Panel - RF Dental Clinic</p>
                </div>
            </div>
            
            <div class="header-right">
                <div class="header-notifications" id="notificationDropdown">
                    <button class="notification-bell" type="button" aria-label="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2zm6-6V11a6 6 0 1 0-12 0v5L4 18v1h16v-1z"/>
                        </svg>
                        <?php if ($notificationTotal > 0): ?>
                            <span class="notification-badge"><?php echo $notificationTotal; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <span class="notification-count"><?php echo $notificationTotal; ?></span>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item">
                                <div class="notification-icon">📅</div>
                                <div class="notification-text"><?php echo $newAppointmentsToday; ?> new appointments today</div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-icon">⚠️</div>
                                <div class="notification-text"><?php echo $pendingPaymentsCount; ?> pending payments require attention</div>
                            </div>
                        </div>
                        <button class="see-all-btn" type="button">See all notifications</button>
                    </div>
                </div>
                <div class="header-user-summary">
                    <div class="header-user-name"><?php echo htmlspecialchars($firstName); ?></div>
                    <div class="header-user-role"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'admin')); ?></div>
                </div>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="user-profile-info">
                        <div class="user-avatar">
                            <img src="assets/images/profile.png" alt="Profile" />
                        </div>
                    </div>
                    <div class="user-profile-dropdown">
                        <div class="dropdown-divider"></div>
                        <a href="admin_settings.php" class="dropdown-item settings">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.64l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.49-.41h-3.84c-.25 0-.45.17-.49.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.09-.47 0-.59.22L2.74 8.87c-.12.22-.07.5.12.64l2.03 1.58c-.05.3-.07.62-.07.94 0 .33.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.64l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.49.41h3.84c.25 0 .45-.17.49-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.09.47 0 .59-.22l1.92-3.32c.12-.22.07-.5-.12-.64l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                            </svg>
                            <span>Settings</span>
                        </a>
                        <a href="logout.php" class="dropdown-item logout">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
