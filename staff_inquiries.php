<?php
$pageTitle = 'Inquiries';

try {
    require_once 'config/database.php';
    $stmt = $pdo->query("SELECT * FROM inquiries ORDER BY created_at DESC");
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $inquiries = [];
}

require_once 'includes/staff_layout_start.php';
?>

            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">📋</div>
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

            <div class="section-card" style="margin-top: 30px;">
                <div class="section-title">
                    <span>Inquiries Logbook</span>
                    <button onclick="openModal()" class="btn-primary">+ Add Inquiry</button>
                </div>

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
                                            <div class="patient-name" style="font-weight: 500;"><?php echo htmlspecialchars($inquiry['name']); ?></div>
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

    <div id="viewModal" class="modal-overlay">
        <div class="modal">
            <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Inquiry Details</h2>
            <div id="viewModalContent"></div>
        </div>
    </div>

    <div id="addModal" class="modal-overlay">
        <div class="modal">
            <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Add New Inquiry</h2>
            <form id="addInquiryForm">
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>Name *</label>
                        <input type="text" name="name" required class="form-control">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Contact Info</label>
                        <input type="text" name="contact_info" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Source *</label>
                    <select name="source" required class="form-control">
                        <option value="">Select Source</option>
                        <option value="Facebook">Facebook</option>
                        <option value="Phone Call">Phone Call</option>
                        <option value="Walk-in">Walk-in</option>
                        <option value="Referral">Referral</option>
                        <option value="Instagram">Instagram</option>
                        <option value="Messenger">Messenger</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Topic</label>
                    <input type="text" name="topic" class="form-control">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="inquiry_message" rows="3" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="Pending">Pending</option>
                        <option value="Answered">Answered</option>
                        <option value="Closed">Closed</option>
                        <option value="Booked">Booked</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeAddModal()" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Add Inquiry</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const inquiries = <?php echo json_encode($inquiries); ?>;

        function openModal() {
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddModal();
        });

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) closeViewModal();
        });

        document.getElementById('addInquiryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
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
            if (confirm('Convert this inquiry to an appointment? This will mark it as Booked.')) {
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
                    <div><span style="color: #6b7280;">Topic:</span> <span style="font-weight: 500; margin-left: 8px;">${inquiry.topic || 'General'}</span></div>
                    <div><span style="color: #6b7280;">Status:</span> <span class="status-badge status-${inquiry.status.toLowerCase()}" style="margin-left: 8px;">${inquiry.status}</span></div>
                    <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${new Date(inquiry.created_at).toLocaleDateString()}</span></div>
                    <div style="margin-top: 8px;"><span style="color: #6b7280;">Message:</span><p style="background: #f9fafb; padding: 14px; border-radius: 8px; margin: 8px 0 0; line-height: 1.5;">${inquiry.inquiry_message || 'No message'}</p></div>
                    ${inquiry.notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #fefce8; padding: 14px; border-radius: 8px; margin: 8px 0 0;">${inquiry.notes}</p></div>` : ''}
                </div>
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
                    <button onclick="closeViewModal()" class="btn-cancel">Close</button>
                    ${inquiry.status !== 'Booked' ? `<button onclick="convertToAppointment(${inquiry.id}); closeViewModal();" class="btn-primary">Convert to Appointment</button>` : ''}
                </div>
            `;
            
            document.getElementById('viewModal').style.display = 'flex';
        }
    </script>
<?php require_once 'includes/staff_layout_end.php'; ?>