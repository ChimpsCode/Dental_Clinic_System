<?php
ob_start();
session_start();

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

$fullName = $_SESSION['full_name'] ?? 'Administrator';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        require_once 'config/database.php';
        
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO inquiries (name, contact_info, source, inquiry_message, topic, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['contact_info'], $_POST['source'], $_POST['inquiry_message'], $_POST['topic'] ?? 'General', $_POST['status'] ?? 'Pending', $_POST['notes'] ?? '']);
            $message = 'Inquiry added successfully!';
            $messageType = 'success';
        } elseif ($_POST['action'] === 'update_status') {
            $stmt = $pdo->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['id']]);
            $message = 'Status updated successfully!';
            $messageType = 'success';
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $message = 'Inquiry deleted successfully!';
            $messageType = 'success';
        } elseif ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare("UPDATE inquiries SET name = ?, contact_info = ?, source = ?, inquiry_message = ?, topic = ?, status = ?, notes = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['contact_info'], $_POST['source'], $_POST['inquiry_message'], $_POST['topic'], $_POST['status'], $_POST['notes'] ?? '', $_POST['id']]);
            $message = 'Inquiry updated successfully!';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

try {
    require_once 'config/database.php';
    $stmt = $pdo->query("SELECT * FROM inquiries ORDER BY created_at DESC");
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inquiries = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries - RF Dental Clinic Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-answered { background: #dbeafe; color: #1e40af; }
        .status-closed { background: #f3f4f6; color: #6b7280; }
        .status-booked { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-logo">
            <img src="assets/images/Logo.png" alt="RF Logo">
            <span>RF Dental Clinic</span>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item"><span class="nav-item-icon">📊</span> Dashboard</a>
            <a href="admin_users.php" class="nav-item"><span class="nav-item-icon">👤</span> User Management</a>
            <a href="admin_patients.php" class="nav-item"><span class="nav-item-icon">👥</span> Patient Records</a>
            <a href="admin_appointments.php" class="nav-item"><span class="nav-item-icon">📅</span> Appointments</a>
            <a href="admin_billing.php" class="nav-item"><span class="nav-item-icon">💰</span> Billing</a>
            <a href="admin_services.php" class="nav-item"><span class="nav-item-icon">🔧</span> Services List</a>
            <a href="admin_analytics.php" class="nav-item"><span class="nav-item-icon">📈</span> Analytics</a>
            <a href="admin_reports.php" class="nav-item"><span class="nav-item-icon">📊</span> Reports</a>
            <a href="admin_inquiries.php" class="nav-item active"><span class="nav-item-icon">💬</span> Inquiries</a>
            <a href="admin_audit_trail.php" class="nav-item"><span class="nav-item-icon">📋</span> Audit Trail</a>
            <a href="admin_settings.php" class="nav-item"><span class="nav-item-icon">⚙️</span> Settings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-item-icon">🚪</span> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <div class="header-title">
                    <h1>Inquiries Management</h1>
                    <p>Admin Panel - RF Dental Clinic</p>
                </div>
            </div>
            <div class="header-right">
                <div class="user-profile">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                    <span class="user-role">Admin</span>
                </div>
            </div>
        </header>

        <div class="content-area">
            <div class="content-main">
                <div class="two-column">
                    <div class="left-column" style="width: 100%; padding-left: 30px;">
                        <div class="section-card">
                            <div class="section-title">
                                <span>Inquiries Logbook</span>
                                <button onclick="openModal()" class="btn-primary">+ Add Inquiry</button>
                            </div>

                            <?php if ($message): ?>
                                <div style="margin-bottom: 20px; padding: 14px 18px; border-radius: 8px; <?php echo $messageType === 'success' ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #991b1b'; ?>">
                                    <?php echo htmlspecialchars($message); ?>
                                </div>
                            <?php endif; ?>

                            <div class="search-filters">
                                <input type="text" id="searchInput" placeholder="Search by name..." class="search-input">
                                <select id="filterSource" class="filter-select">
                                    <option value="">All Sources</option>
                                    <option value="Facebook">Facebook</option>
                                    <option value="Phone Call">Phone Call</option>
                                    <option value="Walk-in">Walk-in</option>
                                    <option value="Referral">Referral</option>
                                    <option value="Instagram">Instagram</option>
                                    <option value="Messenger">Messenger</option>
                                </select>
                                <select id="filterStatus" class="filter-select">
                                    <option value="">All Status</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Answered">Answered</option>
                                    <option value="Closed">Closed</option>
                                    <option value="Booked">Booked</option>
                                </select>
                            </div>

                            <div style="overflow-x: auto;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Source</th>
                                            <th>Topic</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="inquiriesTableBody">
                                        <?php if (empty($inquiries)): ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center; padding: 60px; color: #6b7280;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" style="margin: 0 auto 16px; display: block;">
                                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                                    </svg>
                                                    <p style="font-size: 1.1rem; margin-bottom: 8px;">No inquiries yet</p>
                                                    <p style="font-size: 0.875rem; color: #9ca3af;">Click "+ Add Inquiry" to log your first inquiry</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($inquiries as $inquiry): ?>
                                                <tr class="inquiry-row" data-name="<?php echo strtolower(htmlspecialchars($inquiry['name'])); ?>" data-source="<?php echo htmlspecialchars($inquiry['source']); ?>" data-status="<?php echo htmlspecialchars($inquiry['status']); ?>">
                                                    <td>
                                                        <div class="patient-name"><?php echo htmlspecialchars($inquiry['name']); ?></div>
                                                        <div style="font-size: 0.875rem; color: #6b7280; margin-top: 4px;"><?php echo htmlspecialchars($inquiry['contact_info'] ?? 'No contact'); ?></div>
                                                    </td>
                                                    <td>
                                                        <?php $sourceIcons = ['Facebook' => '📘', 'Phone Call' => '📞', 'Walk-in' => '🚶', 'Referral' => '👥', 'Instagram' => '📷', 'Messenger' => '💬']; echo ($sourceIcons[$inquiry['source']] ?? '📝') . ' ' . htmlspecialchars($inquiry['source']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($inquiry['topic'] ?? 'General'); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></td>
                                                    <td><span class="status-badge status-<?php echo strtolower($inquiry['status']); ?>"><?php echo htmlspecialchars($inquiry['status']); ?></span></td>
                                                    <td>
                                                        <div class="patient-actions">
                                                            <button onclick="viewInquiry(<?php echo $inquiry['id']; ?>)" class="action-btn icon" title="View">👁️</button>
                                                            <button onclick="editInquiry(<?php echo $inquiry['id']; ?>)" class="action-btn icon" title="Edit">✏️</button>
                                                            <?php if ($inquiry['status'] !== 'Booked'): ?>
                                                            <button onclick="convertToAppointment(<?php echo $inquiry['id']; ?>)" class="action-btn icon" title="Convert">📅</button>
                                                            <?php endif; ?>
                                                            <button onclick="deleteInquiry(<?php echo $inquiry['id']; ?>)" class="action-btn icon" title="Delete">🗑️</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="summary-cards" style="margin-top: 25px;">
                            <div class="summary-card">
                                <div class="summary-icon blue">📋</div>
                                <div class="summary-info">
                                    <h3><?php echo count($inquiries); ?></h3>
                                    <p>Total Inquiries</p>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon yellow">⏳</div>
                                <div class="summary-info">
                                    <h3><?php echo count(array_filter($inquiries, fn($i) => $i['status'] === 'Pending')); ?></h3>
                                    <p>Pending</p>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon green">✅</div>
                                <div class="summary-info">
                                    <h3><?php echo count(array_filter($inquiries, fn($i) => $i['status'] === 'Booked')); ?></h3>
                                    <p>Booked</p>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon gray">❌</div>
                                <div class="summary-info">
                                    <h3><?php echo count(array_filter($inquiries, fn($i) => $i['status'] === 'Closed')); ?></h3>
                                    <p>Closed</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="inquiryModal" class="modal-overlay">
        <div class="modal" style="max-width: 550px;">
            <h2 id="modalTitle" style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Add New Inquiry</h2>
            <form method="POST" id="inquiryForm">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="id" value="" id="inquiryId">
                
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="inquiryName" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Contact Info</label>
                    <input type="text" name="contact_info" id="inquiryContact" class="form-control">
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label>Source *</label>
                        <select name="source" id="inquirySource" required class="form-control">
                            <option value="Facebook">Facebook</option>
                            <option value="Phone Call">Phone Call</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Referral">Referral</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Messenger">Messenger</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Topic</label>
                        <select name="topic" id="inquiryTopic" class="form-control">
                            <option value="General">General</option>
                            <option value="Braces">Braces</option>
                            <option value="Extraction">Extraction</option>
                            <option value="Root Canal">Root Canal</option>
                            <option value="Cleaning">Cleaning</option>
                            <option value="Denture">Denture</option>
                            <option value="Price Inquiry">Price Inquiry</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Inquiry/Message *</label>
                    <textarea name="inquiry_message" id="inquiryMessage" required rows="3" class="form-control"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label>Status</label>
                        <select name="status" id="inquiryStatus" class="form-control">
                            <option value="Pending">Pending</option>
                            <option value="Answered">Answered</option>
                            <option value="Closed">Closed</option>
                            <option value="Booked">Booked</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Notes</label>
                        <input type="text" name="notes" id="inquiryNotes" class="form-control">
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeModal()" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-primary" id="submitBtn">Add Inquiry</button>
                </div>
            </form>
        </div>
    </div>

    <div id="viewModal" class="modal-overlay">
        <div class="modal">
            <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Inquiry Details</h2>
            <div id="viewModalContent" class="space-y-3"></div>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
    <script>
        const inquiries = <?php echo json_encode($inquiries); ?>;

        function openModal() {
            document.getElementById('modalTitle').textContent = 'Add New Inquiry';
            document.getElementById('formAction').value = 'add';
            document.getElementById('inquiryId').value = '';
            document.getElementById('submitBtn').textContent = 'Add Inquiry';
            document.getElementById('inquiryForm').reset();
            document.getElementById('inquiryModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('inquiryModal').classList.remove('active');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }

        document.getElementById('inquiryModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) closeViewModal();
        });

        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('filterSource').addEventListener('change', filterTable);
        document.getElementById('filterStatus').addEventListener('change', filterTable);

        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const source = document.getElementById('filterSource').value;
            const status = document.getElementById('filterStatus').value;
            
            document.querySelectorAll('.inquiry-row').forEach(row => {
                const matchSearch = !search || row.dataset.name.includes(search);
                const matchSource = !source || row.dataset.source === source;
                const matchStatus = !status || row.dataset.status === status;
                row.style.display = (matchSearch && matchSource && matchStatus) ? '' : 'none';
            });
        }

        function viewInquiry(id) {
            const inquiry = inquiries.find(i => i.id == id);
            if (!inquiry) return;
            
            document.getElementById('viewModalContent').innerHTML = `
                <div class="space-y-3">
                    <div><span style="color: #6b7280;">Name:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.name}</span></div>
                    <div><span style="color: #6b7280;">Contact:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.contact_info || 'N/A'}</span></div>
                    <div><span style="color: #6b7280;">Source:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.source}</span></div>
                    <div><span style="color: #6b7280;">Topic:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.topic}</span></div>
                    <div><span style="color: #6b7280;">Status:</span> <span class="status-badge status-${inquiry.status.toLowerCase()}" style="margin-left: 8px;">${inquiry.status}</span></div>
                    <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${new Date(inquiry.created_at).toLocaleDateString()}</span></div>
                    <div style="margin-top: 8px;"><span style="color: #6b7280;">Message:</span><p style="background: #f9fafb; padding: 14px; border-radius: 8px; margin: 8px 0 0; line-height: 1.5;">${inquiry.inquiry_message || 'No message'}</p></div>
                    ${inquiry.notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #fefce8; padding: 14px; border-radius: 8px; margin: 8px 0 0;">${inquiry.notes}</p></div>` : ''}
                </div>
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
                    <button onclick="closeViewModal()" class="btn-cancel">Close</button>
                    <button onclick="editInquiry(${inquiry.id}); closeViewModal();" class="btn-primary">Edit</button>
                    ${inquiry.status !== 'Booked' ? `<button onclick="convertToAppointment(${inquiry.id}); closeViewModal();" class="btn-primary">Convert</button>` : ''}
                </div>
            `;
            document.getElementById('viewModal').classList.add('active');
        }

        function editInquiry(id) {
            const inquiry = inquiries.find(i => i.id == id);
            if (!inquiry) return;
            
            document.getElementById('modalTitle').textContent = 'Edit Inquiry';
            document.getElementById('formAction').value = 'update';
            document.getElementById('inquiryId').value = inquiry.id;
            document.getElementById('submitBtn').textContent = 'Update Inquiry';
            
            document.getElementById('inquiryName').value = inquiry.name;
            document.getElementById('inquiryContact').value = inquiry.contact_info || '';
            document.getElementById('inquirySource').value = inquiry.source;
            document.getElementById('inquiryTopic').value = inquiry.topic || 'General';
            document.getElementById('inquiryMessage').value = inquiry.inquiry_message || '';
            document.getElementById('inquiryStatus').value = inquiry.status;
            document.getElementById('inquiryNotes').value = inquiry.notes || '';
            
            document.getElementById('inquiryModal').classList.add('active');
        }

        function convertToAppointment(id) {
            if (confirm('Update status to Booked?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="status" value="Booked">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteInquiry(id) {
            if (confirm('Delete this inquiry?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
