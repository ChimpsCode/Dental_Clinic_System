<?php
/**
 * Dentist Layout Usage Guide
 * 
 * This guide shows how to create new dentist pages using the centralized layout.
 * 
 * ================================================
 * HOW TO CREATE A NEW DENTIST PAGE:
 * ================================================
 * 
 * 1. Create a new PHP file (e.g., dentist_appointments.php)
 * 
 * 2. Set the page title (optional, defaults to "Dentist Dashboard")
 *    $pageTitle = 'Appointments Management';
 * 
 * 3. Include the start layout
 *    require_once 'includes/dentist_layout_start.php';
 * 
 * 4. Add your page content here
 *    <div class="section-card">
 *        <h2 class="section-title">Your Content</h2>
 *        <!-- Your content goes here -->
 *    </div>
 * 
 * 5. Include the end layout
 *    require_once 'includes/dentist_layout_end.php';
 * 
 * ================================================
 * EXAMPLE - Complete Page Template:
 * ================================================
 * 
 * <?php
 * $pageTitle = 'Appointments Management';
 * require_once 'includes/dentist_layout_start.php';
 * ?>
 * 
 * <div class="summary-cards">
 *     <div class="summary-card">
 *         <div class="summary-icon blue">📋</div>
 *         <div class="summary-info">
 *             <h3>10</h3>
 *             <p>Total Appointments</p>
 *         </div>
 *     </div>
 * </div>
 * 
 * <?php require_once 'includes/dentist_layout_end.php'; ?>
 * 
 * ================================================
 * NAVIGATION TABS IN SIDEBAR:
 * ================================================
 * 
 * The following tabs are automatically included in the sidebar:
 * 
 * - Dashboard          (dentist_dashboard.php)
 * - Appointments        (dentist_appointments.php)
 * - Patient Records     (dentist_patients.php)
 * - Treatments         (dentist_treatments.php)
 * - Prescriptions      (dentist_prescriptions.php)
 * - Inquiries          (dentist_inquiries.php)
 * - Schedule           (dentist_schedule.php)
 * 
 * To add a new tab:
 * 1. Open: includes/dentist_layout_start.php
 * 2. Find the <nav class="sidebar-nav"> section
 * 3. Add a new <a> link with your page file
 * 4. Use isActivePage('your_file.php') to highlight active tab
 * 
 * Example:
 * <a href="dentist_reports.php" class="nav-item <?php echo isActivePage('dentist_reports.php') ? 'active' : ''; ?>">
 *     <span class="nav-item-icon">📊</span>
 *     <span>Reports</span>
 * </a>
 * 
 * ================================================
 * BENEFITS OF USING LAYOUT FILES:
 * ================================================
 * 
 * 1. Uniform Navigation: Same sidebar across all dentist pages
 * 2. No Shaking: Layout stays consistent when switching tabs
 * 3. Easy Updates: Change sidebar/header once, affects all pages
 * 4. Role Security: Automatic authentication check
 * 5. Easy to Add Tabs: Add once in layout, appears on all pages
 * 6. Active State: isActivePage() function automatically highlights current tab
 * 
 * ================================================
 * STYLING HELPERS:
 * ================================================
 * 
 * Available CSS classes:
 * 
 * - summary-cards: Grid container for stat cards
 * - summary-card: Individual stat card
 * - summary-icon: Icon container (add color modifiers: blue, green, yellow, red)
 * - summary-info: Text container for stat cards
 * 
 * - section-card: Card container for content sections
 * - section-title: Section heading
 * 
 * - patient-list: Container for patient items
 * - patient-item: Individual patient row
 * - patient-name: Bold name styling
 * - patient-treatment: Treatment description
 * - patient-details: Status and time badges
 * 
 * - two-column: Two column grid layout
 * - left-column: Main content area
 * - right-column: Sidebar area
 * 
 * - notification-box: Container for notifications
 * - notification-item: Individual notification
 * 
 * - btn-primary: Primary action button
 * - btn-cancel: Secondary/cancel button
 * 
 * ================================================
 * AVAILABLE SVG ICONS:
 * ================================================
 * 
 * The sidebar includes SVG icons for each navigation item.
 * You can copy these for use in your pages.
 * 
 */