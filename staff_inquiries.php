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

<style>
.kebab-menu {
    position: relative;
    display: inline-block;
}

.kebab-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}

.kebab-dropdown-portal {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    min-width: 220px;
    max-width: 280px;
    width: auto;
    z-index: 9999;
    overflow: hidden;
}

.kebab-dropdown-portal.show {
    display: block;
    animation: kebabFadeIn 0.15s ease;
}

@keyframes kebabFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.kebab-dropdown-portal a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: #374151;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.15s ease;
    cursor: pointer;
    white-space: nowrap;
}

.kebab-dropdown-portal a:hover {
    background-color: #f9fafb;
    color: #111827;
}

.kebab-dropdown-portal a.danger {
    color: #dc2626;
}

.kebab-dropdown-portal a.danger:hover {
    background-color: #fef2f2;
}

.kebab-dropdown-portal a svg {
    flex-shrink: 0;
}

.kebab-dropdown-portal a:first-child {
    border-radius: 8px 8px 0 0;
}

.kebab-dropdown-portal a:last-child {
    border-radius: 0 0 8px 8px;
}

.kebab-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9998;
}

.kebab-backdrop.show {
    display: block;
}
</style>

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
                    <div class="summary-icon gray">📥</div>
                    <div class="summary-info">
                        <h3><?php echo count(array_filter($inquiries, fn($i) => $i['status'] === 'New Admission')); ?></h3>
                        <p>New Admission</p>
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
                        <option value="Fb messenger">Fb messenger</option>
                        <option value="Phone call">Phone call</option>
                        <option value="Walk-in">Walk-in</option>
                    </select>
                    <select id="filterStatus" class="filter-select">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Answered">Answered</option>
                        <option value="Booked">Booked</option>
                        <option value="New Admission">New Admission</option>
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
                                    <?php 
                                        $fullName = trim($inquiry['first_name'] . ' ' . $inquiry['middle_name'] . ' ' . $inquiry['last_name']);
                                    ?>
                                    <tr class="inquiry-row" data-name="<?php echo strtolower(htmlspecialchars($fullName)); ?>" data-source="<?php echo htmlspecialchars($inquiry['source']); ?>" data-status="<?php echo htmlspecialchars($inquiry['status']); ?>">
                                        <td>
                                            <div class="patient-name" style="font-weight: 500;"><?php echo htmlspecialchars($fullName); ?></div>
                                            <div style="font-size: 0.875rem; color: #6b7280; margin-top: 4px;"><?php echo htmlspecialchars($inquiry['contact_info'] ?? 'No contact'); ?></div>
                                        </td>
                                        <td>
                                            <?php $sourceIcons = ['Fb messenger' => '💬', 'Phone call' => '📞', 'Walk-in' => '🚶']; echo ($sourceIcons[$inquiry['source']] ?? '📝') . ' ' . htmlspecialchars($inquiry['source']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($inquiry['topic'] ?? 'General'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($inquiry['status']); ?>"><?php echo htmlspecialchars($inquiry['status']); ?></span></td>
                                        <td>
                                            <div class="kebab-menu">
                                                <button class="kebab-btn" data-inquiry-id="<?php echo $inquiry['id']; ?>" data-status="<?php echo htmlspecialchars($inquiry['status']); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                        <circle cx="12" cy="6" r="2"/>
                                                        <circle cx="12" cy="12" r="2"/>
                                                        <circle cx="12" cy="18" r="2"/>
                                                    </svg>
                                                </button>
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
                        <label>First Name *</label>
                        <input type="text" name="first_name" required class="form-control">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" class="form-control">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Contact Number *</label>
                    <input type="text" name="contact_info" required class="form-control" placeholder="Enter contact number">
                </div>
                <div class="form-group">
                    <label>Source *</label>
                    <select name="source" id="sourceSelect" required class="form-control" onchange="console.log('Source selected:', this.value)">
                        <option value="">Select Source</option>
                        <option value="Fb messenger">Fb messenger</option>
                        <option value="Phone call">Phone call</option>
                        <option value="Walk-in">Walk-in</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Topic *</label>
                    <select name="topic" id="topicSelect" required class="form-control" onchange="toggleOtherTopic()">
                        <option value="">Select Topic</option>
                        <!-- Services will be loaded here dynamically -->
                        <option value="Others">Others</option>
                    </select>
                    <input type="text" id="otherTopicInput" name="topic_other" class="form-control" placeholder="Please specify topic" style="display: none; margin-top: 8px;">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="inquiry_message" rows="3" class="form-control"></textarea>
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
            loadServices();
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
                otherTopicInput.required = true;
            } else {
                otherTopicInput.style.display = 'none';
                otherTopicInput.required = false;
                otherTopicInput.value = '';
            }
        }

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

        function forwardToAdmission(id) {
            if (confirm('Forward this inquiry to New Admission form? The form will open with inquiry data pre-filled.')) {
                window.location.href = 'staff_new_admission.php?inquiry_id=' + id;
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

        let kebabDropdown = null;
        let kebabBackdrop = null;
        let activeButton = null;

        function createKebabDropdown() {
            kebabDropdown = document.createElement('div');
            kebabDropdown.className = 'kebab-dropdown-portal';
            kebabDropdown.id = 'kebabDropdownPortal';
            document.body.appendChild(kebabDropdown);

            kebabBackdrop = document.createElement('div');
            kebabBackdrop.className = 'kebab-backdrop';
            kebabBackdrop.id = 'kebabBackdrop';
            document.body.appendChild(kebabBackdrop);

            kebabBackdrop.addEventListener('click', closeKebabDropdown);
        }

        function getKebabMenuItems(inquiryId, status) {
            const isBooked = status === 'Booked';
            const isNewAdmission = status === 'New Admission';
            const canForward = !isBooked && !isNewAdmission;

            let itemsHtml = `
                <a href="javascript:void(0)" data-action="view" data-id="${inquiryId}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    View
                </a>
            `;

            if (canForward) {
                itemsHtml += `
                    <a href="javascript:void(0)" data-action="appointment" data-id="${inquiryId}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Forward to Appointment
                    </a>
                    <a href="javascript:void(0)" data-action="admission" data-id="${inquiryId}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 2L11 13"/>
                            <path d="M22 2l-7 20-4-9-9-4 20-7z"/>
                        </svg>
                        Forward to Admission
                    </a>
                `;
            }

            itemsHtml += `
                <a href="javascript:void(0)" data-action="delete" data-id="${inquiryId}" class="danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    Delete
                </a>
            `;

            return itemsHtml;
        }

        function positionKebabDropdown(button) {
            if (!kebabDropdown || !button) return;

            const rect = button.getBoundingClientRect();
            const dropdownRect = kebabDropdown.getBoundingClientRect();
            
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            
            const padding = 15;
            const dropdownWidth = 220;
            
            let left = rect.right + 5;
            let top = rect.bottom + 8;

            if (left + dropdownWidth > viewportWidth - padding) {
                left = rect.left - dropdownWidth - 5;
            }
            
            if (left < padding) {
                left = padding;
            }
            
            if (top + 200 > viewportHeight - padding) {
                top = rect.top - 200 - 8;
            }
            
            if (top < padding) {
                top = padding;
            }

            kebabDropdown.style.left = left + 'px';
            kebabDropdown.style.top = top + 'px';
        }

        function openKebabDropdown(button) {
            if (!kebabDropdown) {
                createKebabDropdown();
            }

            const inquiryId = button.dataset.inquiryId;
            const status = button.dataset.status;

            kebabDropdown.innerHTML = getKebabMenuItems(inquiryId, status);
            positionKebabDropdown(button);

            kebabDropdown.classList.add('show');
            kebabBackdrop.classList.add('show');
            activeButton = button;
            button.classList.add('active');

            kebabDropdown.addEventListener('click', handleKebabClick);
        }

        function closeKebabDropdown() {
            if (kebabDropdown) {
                kebabDropdown.classList.remove('show');
                kebabDropdown.innerHTML = '';
            }
            if (kebabBackdrop) {
                kebabBackdrop.classList.remove('show');
            }
            if (activeButton) {
                activeButton.classList.remove('active');
                activeButton = null;
            }
        }

        function handleKebabClick(e) {
            const link = e.target.closest('a[data-action]');
            if (!link) return;

            e.preventDefault();
            e.stopPropagation();

            const action = link.dataset.action;
            const id = parseInt(link.dataset.id);

            closeKebabDropdown();

            switch(action) {
                case 'view':
                    viewInquiry(id);
                    break;
                case 'appointment':
                    convertToAppointment(id);
                    break;
                case 'admission':
                    forwardToAdmission(id);
                    break;
                case 'delete':
                    deleteInquiry(id);
                    break;
            }
        }

        document.addEventListener('click', function(e) {
            const button = e.target.closest('.kebab-btn');
            if (button) {
                e.preventDefault();
                e.stopPropagation();

                if (activeButton === button && kebabDropdown && kebabDropdown.classList.contains('show')) {
                    closeKebabDropdown();
                } else {
                    if (activeButton) {
                        activeButton.classList.remove('active');
                    }
                    openKebabDropdown(button);
                }
                return;
            }

            if (!e.target.closest('.kebab-dropdown-portal')) {
                closeKebabDropdown();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && kebabDropdown && kebabDropdown.classList.contains('show')) {
                closeKebabDropdown();
            }
        });

        window.addEventListener('resize', function() {
            if (kebabDropdown && kebabDropdown.classList.contains('show') && activeButton) {
                positionKebabDropdown(activeButton);
            }
        });

        function viewInquiry(id) {
            const inquiry = inquiries.find(i => i.id == id);
            if (!inquiry) return;
            
            const fullName = `${inquiry.first_name || ''} ${inquiry.middle_name || ''} ${inquiry.last_name || ''}`.replace(/\s+/g, ' ').trim();
            const isBooked = inquiry.status === 'Booked';
            const isNewAdmission = inquiry.status === 'New Admission';
            const canForward = !isBooked && !isNewAdmission;
            
            let actionsHtml = '';
            if (canForward) {
                actionsHtml = `
                    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                        <button onclick="convertToAppointment(${inquiry.id}); closeViewModal();" class="btn-primary">Forward to Appointment</button>
                        <button onclick="forwardToAdmission(${inquiry.id}); closeViewModal();" class="btn-primary" style="background: #059669;">Forward to Admission</button>
                    </div>
                `;
            } else {
                actionsHtml = `
                    <div style="display: flex; justify-content: flex-end; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                        <button onclick="closeViewModal()" class="btn-cancel">Close</button>
                    </div>
                `;
            }
            
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
                ${actionsHtml}
            `;
            
            document.getElementById('viewModal').classList.add('active');
        }
    </script>
<?php require_once 'includes/staff_layout_end.php'; ?>