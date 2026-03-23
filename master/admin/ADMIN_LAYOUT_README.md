# Admin Layout System - Documentation

## Overview

This document describes the new Admin Layout system implemented for strict role-based navigation separation.

## Critical Requirement Met

**Strict Separation of Concerns**: The layout system ensures that when an Admin clicks any shared feature (like 'Patient Records'), the application retains the Admin Sidebar and Navbar. It never accidentally loads the Staff or Dentist sidebar.

The layout acts as a persistent wrapper based on the logged-in user's role (`session.role`), regardless of which internal page is loaded.

## File Structure

```
Dental_Clinic_System/
├── admin_dashboard.php          # Main admin dashboard
├── admin_users.php              # User Management
├── admin_patients.php           # Patient Records
├── admin_appointments.php       # Appointments
├── admin_billing.php            # Billing (Paid/Unpaid status)
├── admin_services.php           # Services List
├── admin_analytics.php          # Analytics
├── admin_reports.php            # Reports (Printable)
├── admin_audit_trail.php        # Audit Trail
├── admin_settings.php           # Settings
├── includes/
│   ├── admin_layout_start.php   # Layout wrapper start
│   └── admin_layout_end.php     # Layout wrapper end
└── assets/
    ├── css/
    │   └── admin.css            # Admin-specific styles
    └── js/
        └── admin.js             # Admin-specific JavaScript
```

## Admin Sidebar Navigation

The Admin sidebar includes the following menu items:

1. **Dashboard** - Main admin overview with statistics and quick actions
2. **User Management** - Add, edit, and manage system users
3. **Patient Records** - View and manage all patient records
4. **Appointments** - View and manage appointments
5. **Billing** - Simple Paid/Unpaid status tracking
6. **Services List** - Manage dental services and pricing
7. **Analytics** - Clinic performance metrics and charts
8. **Reports** - Printable reports (Patients, Appointments, Billing, Revenue, etc.)
9. **Audit Trail** - System activity logs
10. **Settings** - System configuration

## How It Works

### 1. Role-Based Access Control

Every admin page starts with strict role validation:

```php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
```

### 2. Centralized Layout System

Admin pages use a two-part layout system:

**Layout Start** (`includes/admin_layout_start.php`):
- Validates admin session
- Outputs the Admin Sidebar with all menu items
- Outputs the top header with user info
- Includes proper CSS and JavaScript

**Layout End** (`includes/admin_layout_end.php`):
- Closes the content area and main tags
- Includes page-specific JavaScript

### 3. Page Usage

Each admin page follows this pattern:

```php
<?php
$pageTitle = 'Page Title';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>

<!-- Page-specific content here -->

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
```

### 4. Active Menu Item Highlighting

The layout automatically highlights the current page in the sidebar using:

```php
function isActivePage($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return $currentPage === $page;
}
```

### 5. Login Redirect Logic

The login system now properly redirects users based on their role:

- **Admin** → `admin_dashboard.php`
- **Staff** → `staff-dashboard.php`
- **Dentist/Other** → `dashboard.php`

### 6. Dashboard Protection

Each dashboard has proper role protection:

**dashboard.php** (Dentist/Default):
- Redirects admins to `admin_dashboard.php`
- Redirects staff to `staff-dashboard.php`

**staff-dashboard.php**:
- Redirects admins to `admin_dashboard.php`
- Redirects non-staff to `dashboard.php`

**admin_dashboard.php** (via layout):
- Only accessible by admins

## Features

### User Management
- Add, edit, delete users
- Role assignment (Admin, Dentist, Staff)
- Status toggle (Active/Inactive)

### Billing
- Simple Paid/Unpaid status display
- Invoice tracking
- Payment history
- Export functionality

### Reports
- Patient Report
- Appointments Report
- Billing Report
- Revenue Report
- Services Report
- Daily Summary
- Custom date range selection
- Print and export options

### Audit Trail
- Complete activity logging
- Filter by action type (Login, Create, Update, Delete, Payment)
- Filter by user role
- Export to CSV, PDF, Excel

### Settings
- Clinic information management
- Business hours configuration
- Notification preferences
- Security settings (2FA, session timeout, password policy)
- Backup management

## Styling

Admin pages use a combination of:
- `dashboard.css` - Shared dashboard styles
- `admin.css` - Admin-specific styles for tables, forms, cards, etc.

## JavaScript Functionality

The `admin.js` file provides:
- Search functionality for tables
- Filter functionality
- Modal handling (User, Service)
- Form submissions
- Toast notifications
- Report generation and printing
- Export functionality

## Testing

### Login Testing
1. Login as `admin` / `admin123` → Should redirect to `admin_dashboard.php`
2. Login as `staff` / `staff123` → Should redirect to `staff-dashboard.php`
3. Login as `dentist` / `dentist123` → Should redirect to `dashboard.php`

### Navigation Testing
1. As admin, navigate through all menu items
2. Verify sidebar remains consistent on all pages
3. Verify active page is highlighted
4. Verify proper role protection on other dashboards

### Feature Testing
1. Test User Management CRUD operations
2. Test Billing status toggling
3. Test Report generation
4. Test Audit log filtering
5. Test Settings modifications

## Security Considerations

1. All admin pages verify admin role on every request
2. Session-based authentication
3. Proper input sanitization with `htmlspecialchars()`
4. CSRF protection recommended for forms
5. Database parameterization used in queries

## Future Enhancements

Potential improvements:
- CSRF token validation for forms
- Activity logging for admin actions
- File upload handling for patient records
- Email notification system
- SMS integration for appointments
- Multi-clinic support
