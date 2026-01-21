<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin'])) {
    ob_end_clean();
    header('Location: ' . ($role === 'staff' ? 'staff-dashboard.php' : 'login.php'));
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'User';

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
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-answered { background: #dbeafe; color: #1e40af; }
        .status-booked { background: #d1fae5; color: #065f46; }
        .status-new-admission { background: #dcfce7; color: #166534; }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .dropdown { position: relative; display: inline-block; }
        
        .dropdown-btn {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
        }
        
        .dropdown-btn:hover { background: #f3f4f6; }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 6px;
            z-index: 100;
            overflow: hidden;
        }
        
        .dropdown-content.show { display: block; }
        
        .dropdown-content a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }
        
        .dropdown-content a:hover { background: #f3f4f6; }
        
        .dropdown-content a.danger { color: #dc2626; }
        
        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 4px 0;
        }
        
        .search-filters {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-input, .filter-select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-cancel {
            background: white;
            color: #6b7280;
            border: 1px solid #d1d5db;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
        }
        
        .btn-cancel:hover {
            background: #f9fafb;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background: white;
            border-radius: 8px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .space-y-3 > * + * {
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/images/Logo.png" alt="RF Logo">
            <span>RF Dental Clinic</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active"><span class="nav-item-icon">📊</span> Dashboard</a>
            <a href="inquiries.php" class="nav-item"><span class="nav-item-icon">💬</span> Inquiries</a>
            <a href="NewAdmission.php" class="nav-item"><span class="nav-item-icon">📝</span> New Admission</a>
            <a href="appointments.php" class="nav-item"><span class="nav-item-icon">📅</span> Appointments</a>
            <a href="patient-records.php" class="nav-item"><span class="nav-item-icon">👥</span> Patient Records</a>
            <a href="analytics.php" class="nav-item"><span class="nav-item-icon">📈</span> Analytics</a>
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
                    <h1>Inquiries Logbook</h1>
                    <p>View all patient inquiries and potential bookings</p>
                </div>
            </div>
            <div class="header-right">
                <div class="user-profile">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                    <span class="user-role"><?php echo ucfirst($role); ?></span>
                </div>
            </div>
        </header>

        <div class="content-area">
            <div class="content-main">
                <div class="section-card" style="margin-bottom: 30px;">
                    <div class="section-title">
                        <span>Inquiries Logbook</span>
                        <button onclick="openModal()" class="btn-primary">+ Add Inquiry</button>
                    </div>

                    <?php if (isset($message) && $message): ?>
                        <div style="margin-bottom: 20px; padding: 14px 18px; border-radius: 8px; <?php echo (isset($messageType) && $messageType === 'success') ? 'background: #d1fae5; color: #065f46;' : 'background: #fee2e2; color: #991b1b;'; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="search-filters" style="margin-bottom: 20px;">
                        <input type="text" id="searchInput" placeholder="Search by name..." class="search-input" style="flex: 1; min-width: 200px;">
                        <select id="filterSource" class="filter-select" style="min-width: 120px;">
                            <option value="">All Sources</option>
                            <option value="Fb messenger">Fb messenger</option>
                            <option value="Phone call">Phone call</option>
                            <option value="Walk-in">Walk-in</option>
                        </select>
                        <select id="filterStatus" class="filter-select" style="min-width: 120px;">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Answered">Answered</option>
                            <option value="Booked">Booked</option>
                            <option value="New Admission">New Admission</option>
                        </select>
                    </div>

                    <div style="overflow-x: auto; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <table class="data-table" style="width: 100%; border-collapse: collapse;">
                            <thead style="background: #f9fafb;">
                                <tr>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Name</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Source</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Topic</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Date</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Status</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Actions</th>
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
                                        <?php 
                                            $fullName = trim($inquiry['first_name'] . ' ' . $inquiry['middle_name'] . ' ' . $inquiry['last_name']);
                                        ?>
                                        <tr class="inquiry-row" data-name="<?php echo strtolower(htmlspecialchars($fullName)); ?>" data-source="<?php echo htmlspecialchars($inquiry['source']); ?>" data-status="<?php echo htmlspecialchars($inquiry['status']); ?>" style="border-bottom: 1px solid #f3f4f6;">
                                            <td style="padding: 12px 16px;">
                                                <div class="patient-name" style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($fullName); ?></div>
                                                <div style="font-size: 0.875rem; color: #6b7280; margin-top: 4px;"><?php echo htmlspecialchars($inquiry['contact_info'] ?? 'No contact'); ?></div>
                                            </td>
                                            <td style="padding: 12px 16px;">
                                                <?php $sourceIcons = ['Fb messenger' => '💬', 'Phone call' => '📞', 'Walk-in' => '🚶']; echo ($sourceIcons[$inquiry['source']] ?? '📝') . ' ' . htmlspecialchars($inquiry['source']); ?>
                                            </td>
                                            <td style="padding: 12px 16px;"><?php echo htmlspecialchars($inquiry['topic'] ?? 'General'); ?></td>
                                            <td style="padding: 12px 16px;"><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></td>
                                            <td style="padding: 12px 16px;"><span class="status-badge status-<?php echo strtolower($inquiry['status']); ?>"><?php echo htmlspecialchars($inquiry['status']); ?></span></td>
                                            <td style="padding: 12px 16px;">
                                                <div class="dropdown">
                                                    <button onclick="toggleDropdown(<?php echo $inquiry['id']; ?>)" class="dropdown-btn" title="Actions">⋯</button>
                                                    <div id="dropdown-<?php echo $inquiry['id']; ?>" class="dropdown-content">
                                                        <a href="javascript:void(0)" onclick="viewInquiry(<?php echo $inquiry['id']; ?>)">👁️ View Details</a>
                                                        <a href="NewAdmission.php?inquiry_id=<?php echo $inquiry['id']; ?>">📝 Forward to New Admission</a>
                                                        <a href="appointments.php?inquiry_id=<?php echo $inquiry['id']; ?>">📅 Forward to Appointment</a>
                                                        <a href="javascript:void(0)" onclick="addToQueue(<?php echo $inquiry['id']; ?>)">📋 Add to Queue</a>
                                                        <div class="dropdown-divider"></div>
                                                        <a href="javascript:void(0)" onclick="deleteInquiry(<?php echo $inquiry['id']; ?>)" class="danger">🗑️ Delete</a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="summary-card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon blue" style="font-size: 2rem;">📋</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo count($inquiries); ?></h3>
                            <p style="color: #6b7280; margin: 4px 0 0; font-size: 0.875rem;">Total Inquiries</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon yellow" style="font-size: 2rem;">⏳</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo count(array_filter($inquiries, fn($i) => $i['status'] === 'Pending')); ?></h3>
                            <p style="color: #6b7280; margin: 4px 0 0; font-size: 0.875rem;">Pending</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon green" style="font-size: 2rem;">✅</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo count(array_filter($inquiries, fn($i) => $i['status'] === 'Booked')); ?></h3>
                            <p style="color: #6b7280; margin: 4px 0 0; font-size: 0.875rem;">Booked</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon gray" style="font-size: 2rem;">📥</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo count(array_filter($inquiries, fn($i) => $i['status'] === 'New Admission')); ?></h3>
                            <p style="color: #6b7280; margin: 4px 0 0; font-size: 0.875rem;">New Admission</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="viewModal" class="modal-overlay">
        <div class="modal">
            <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Inquiry Details</h2>
            <div id="viewModalContent" class="space-y-3"></div>
        </div>
    </div>

    <div id="addModal" class="modal-overlay">
        <div class="modal">
            <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Add New Inquiry</h2>
            <form id="addInquiryForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 4px;">First Name *</label>
                        <input type="text" name="first_name" required style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 4px;">Middle Name</label>
                        <input type="text" name="middle_name" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 500; margin-bottom: 4px;">Last Name *</label>
                        <input type="text" name="last_name" required style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px;">
                    </div>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 4px;">Contact Number *</label>
                    <input type="text" name="contact_info" required style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 4px;">Source *</label>
                    <select name="source" required style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px;">
                        <option value="">Select Source</option>
                        <option value="Fb messenger">Fb messenger</option>
                        <option value="Phone call">Phone call</option>
                        <option value="Walk-in">Walk-in</option>
                    </select>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 4px;">Topic *</label>
                    <select name="topic" id="topicSelect" required style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px;" onchange="toggleOtherTopic()">
                        <option value="">Select Topic</option>
                        <!-- Services will be loaded here dynamically -->
                        <option value="Others">Others</option>
                    </select>
                    <input type="text" id="otherTopicInput" name="topic_other" placeholder="Please specify topic" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; margin-top: 8px; display: none;">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 4px;">Message</label>
                    <textarea name="inquiry_message" rows="4" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeAddModal()" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Add Inquiry</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
    <script>
        const inquiries = <?php echo json_encode($inquiries); ?>;
        
        function toggleDropdown(id) {
            const dropdown = document.getElementById('dropdown-' + id);
            const isShown = dropdown.classList.contains('show');
            
            document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
            
            if (!isShown) {
                dropdown.classList.add('show');
            }
        }
        
        function openModal() {
            document.getElementById('addModal').classList.add('active');
            loadServices();
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }

        function loadServices() {
            const topicSelect = document.getElementById('topicSelect');
            // Keep the "Select Topic" and "Others" options
            const selectOption = topicSelect.querySelector('option[value=""]');
            const othersOption = topicSelect.querySelector('option[value="Others"]');
            
            // Remove any previously loaded service options
            topicSelect.innerHTML = '';
            if (selectOption) topicSelect.appendChild(selectOption);
            
            fetch('api_public_services.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.services) {
                        data.services.forEach(service => {
                            const option = document.createElement('option');
                            option.value = service.name;
                            option.textContent = service.name;
                            topicSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading services:', error))
                .finally(() => {
                    if (othersOption) topicSelect.appendChild(othersOption);
                });
        }

        function toggleOtherTopic() {
            const topicSelect = document.getElementById('topicSelect');
            const otherTopicInput = document.getElementById('otherTopicInput');
            
            if (topicSelect.value === 'Others') {
                otherTopicInput.style.display = 'block';
            } else {
                otherTopicInput.style.display = 'none';
                otherTopicInput.value = '';
            }
        }

        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddModal();
        });

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) closeViewModal();
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
            }
        });

        document.getElementById('addInquiryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Debug: Log form data
            console.log('Form data being submitted:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }
            
            // Handle topic - use other topic if selected
            const topicSelect = document.getElementById('topicSelect');
            if (topicSelect.value === 'Others') {
                const otherTopic = document.getElementById('otherTopicInput').value;
                formData.set('topic', otherTopic);
            }
            
            formData.delete('topic_other');
            
            fetch('process_inquiry.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddModal();
                    location.reload();
                } else {
                    alert(data.message || 'Error adding inquiry');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding inquiry');
            });
        });

        function convertToAppointment(id) {
            if (confirm('Convert this inquiry to an appointment?')) {
                fetch('convert_inquiry.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Error converting inquiry');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error converting inquiry');
                });
            }
        }

        function deleteInquiry(id) {
            if (confirm('Are you sure you want to delete this inquiry?')) {
                fetch('delete_inquiry.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting inquiry');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting inquiry');
                });
            }
        }

        function addToQueue(id) {
            const inquiry = inquiries.find(i => i.id == id);
            if (!inquiry) return;
            
            document.getElementById('viewModalContent').innerHTML = `
                <div class="space-y-3">
                    <div><span style="color: #6b7280;">Name:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.name}</span></div>
                    <div><span style="color: #6b7280;">Contact:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.contact_info || 'N/A'}</span></div>
                    <div><span style="color: #6b7280;">Source:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.source}</span></div>
                    <div><span style="color: #6b7280;">Topic:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.topic}</span></div>
                    <div style="margin-top: 16px; padding: 16px; background: #fef3c7; border-radius: 8px;">
                        <p style="color: #92400e; font-weight: 500;">Added to Queue Successfully!</p>
                        <p style="color: #92400e; font-size: 0.875rem; margin-top: 4px;">This inquiry has been added to the queue for follow-up.</p>
                    </div>
                </div>
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
                    <button onclick="closeViewModal()" class="btn-cancel">Close</button>
                </div>
            `;
            
            document.getElementById('viewModal').classList.add('active');
            
            // Update inquiry status to "Pending" if not already
            fetch('update_inquiry_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id, status: 'Pending' })
            });
        }

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
            
            const fullName = `${inquiry.first_name || ''} ${inquiry.middle_name || ''} ${inquiry.last_name || ''}`.replace(/\s+/g, ' ').trim();
            
            document.getElementById('viewModalContent').innerHTML = `
                <div class="space-y-3">
                    <div><span style="color: #6b7280;">Name:</span> <span style="font-weight: 500; margin-left: 8px;">${fullName}</span></div>
                    <div><span style="color: #6b7280;">Contact:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.contact_info || 'N/A'}</span></div>
                    <div><span style="color: #6b7280;">Source:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.source}</span></div>
                    <div><span style="color: #6b7280;">Topic:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.topic || 'General'}</span></div>
                    <div><span style="color: #6b7280;">Status:</span> <span class="status-badge status-${inquiry.status.toLowerCase().replace(/\s+/g, '-')}" style="margin-left: 8px;">${inquiry.status}</span></div>
                    <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${new Date(inquiry.created_at).toLocaleDateString()}</span></div>
                    <div style="margin-top: 8px;"><span style="color: #6b7280;">Message:</span><p style="background: #f9fafb; padding: 14px; border-radius: 8px; margin: 8px 0 0; line-height: 1.5;">${inquiry.inquiry_message || 'No message'}</p></div>
                    ${inquiry.notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #fefce8; padding: 14px; border-radius: 8px; margin: 8px 0 0;">${inquiry.notes}</p></div>` : ''}
                </div>
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
                    <button onclick="closeViewModal()" class="btn-cancel">Close</button>
                </div>
            `;
            
            document.getElementById('viewModal').classList.add('active');
        }
    </script>
</body>
</html>
