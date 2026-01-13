<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        ob_end_clean();
        header('Location: admin_dashboard.php');
        exit();
    }
    ob_end_clean();
    header('Location: dashboard.php');
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Staff Member';
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        require_once 'config/database.php';
        
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO inquiries (name, contact_info, source, inquiry_message, topic, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['contact_info'], $_POST['source'], $_POST['inquiry_message'], $_POST['topic'] ?? 'General', $_POST['status'] ?? 'Pending']);
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
    <title>Inquiries - RF Dental Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/staff_dashboard.css">
    <style>
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-answered { background: #dbeafe; color: #1e40af; }
        .status-closed { background: #f3f4f6; color: #6b7280; }
        .status-booked { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/images/Logo.png" alt="RF Logo">
            <span>RF Dental Clinic</span>
        </div>
        <nav class="sidebar-nav">
            <a href="staff-dashboard.php" class="nav-item"><span class="nav-item-icon">📊</span> Dashboard</a>
            <a href="staff_inquiries.php" class="nav-item active"><span class="nav-item-icon">💬</span> Inquiries</a>
            <a href="staff_new_admission.php" class="nav-item"><span class="nav-item-icon">📝</span> New Admission</a>
            <a class="nav-item"><span class="nav-item-icon">👥</span> Patient Records</a>
            <a class="nav-item"><span class="nav-item-icon">📅</span> Appointments</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-item-icon">🚪</span> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <div class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="header-title">
                    <h1>Inquiries Management</h1>
                    <p>Digital logbook for tracking potential patients</p>
                </div>
            </div>
            <div class="header-right">
                <div class="user-profile">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                    <span class="user-role">Staff</span>
                </div>
            </div>
        </header>


        

        <div class="content-area" style="padding: 30px; overflow-y: auto; flex: 1;">
            <div class="content-main">
                <div class="two-column">
                    <div class="left-column">
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
        <div class="modal">
            <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Add New Inquiry</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Contact Info</label>
                    <input type="text" name="contact_info" class="form-control">
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label>Source *</label>
                        <select name="source" required class="form-control">
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
                        <select name="topic" class="form-control">
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
                    <textarea name="inquiry_message" required rows="3" class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="Pending">Pending - Needs follow-up</option>
                        <option value="Answered">Answered - Information provided</option>
                        <option value="Closed">Closed - No response</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeModal()" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Add Inquiry</button>
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
                </div>
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
                    <button onclick="closeViewModal()" class="btn-cancel">Close</button>
                    ${inquiry.status !== 'Booked' ? `<button onclick="convertToAppointment(${inquiry.id}); closeViewModal();" class="btn-primary">Convert to Appointment</button>` : ''}
                </div>
            `;
            document.getElementById('viewModal').classList.add('active');
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
