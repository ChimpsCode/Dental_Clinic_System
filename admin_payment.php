<?php
/**
 * Payment - Admin page for viewing payments (Simple Paid/Unpaid status)
 */

$pageTitle = 'Payment';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Payment Overview</h2>
                    <button class="btn-primary" onclick="exportPayment()">📥 Export Report</button>
                </div>

                <!-- Stats Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon green">💰</div>
                        <div class="summary-info">
                            <h3>₱45,000</h3>
                            <p>Total Collected</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon yellow">⏳</div>
                        <div class="summary-info">
                            <h3>₱8,500</h3>
                            <p>Pending Payments</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon blue">📋</div>
                        <div class="summary-info">
                            <h3>25</h3>
                            <p>Total Transactions</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon red">❌</div>
                        <div class="summary-info">
                            <h3>2</h3>
                            <p>Overdue</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-filters">
                    <input type="text" class="search-input" placeholder="Search by patient name or invoice..." id="paymentSearch">
                    <select class="filter-select" id="paymentStatus">
                        <option value="">All Status</option>
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                    <select class="filter-select" id="dateRange">
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="all">All Time</option>
                    </select>
                </div>

                <!-- Payment Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient</th>
                                <th>Services</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="paymentTableBody">
                            <?php 
                            $sampleData = [
                                ['id' => 'INV-001', 'patient' => 'Maria Santos', 'services' => 'Root Canal, X-Ray', 'amount' => 5000, 'date' => '2024-01-10', 'dueDate' => '2024-01-17', 'status' => 'paid'],
                                ['id' => 'INV-002', 'patient' => 'Roberto Garcia', 'services' => 'Denture Adjustment', 'amount' => 1500, 'date' => '2024-01-11', 'dueDate' => '2024-01-18', 'status' => 'unpaid'],
                                ['id' => 'INV-003', 'patient' => 'Ana Reyes', 'services' => 'Oral Prophylaxis', 'amount' => 2000, 'date' => '2024-01-08', 'dueDate' => '2024-01-15', 'status' => 'unpaid'],
                                ['id' => 'INV-004', 'patient' => 'Juan Dela Cruz', 'services' => 'Tooth Extraction', 'amount' => 3000, 'date' => '2024-01-12', 'dueDate' => '2024-01-19', 'status' => 'paid'],
                            ];
                            foreach ($sampleData as $index => $row): 
                                $statusClass = $row['status'] === 'paid' ? 'paid' : 'unpaid';
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['patient']); ?></td>
                                <td><?php echo htmlspecialchars($row['services']); ?></td>
                                <td>₱<?php echo number_format($row['amount'], 0); ?></td>
                                <td><?php echo $row['date']; ?></td>
                                <td><?php echo $row['dueDate']; ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                <td>
                                    <button class="queue-kebab-btn" data-invoice-id="<?php echo $row['id']; ?>" data-status="<?php echo $row['status']; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <circle cx="12" cy="5" r="2"/>
                                            <circle cx="12" cy="12" r="2"/>
                                            <circle cx="12" cy="19" r="2"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <span class="pagination-info">Showing 1-4 of 25 transactions</span>
                    <div class="pagination-buttons">
                        <button class="pagination-btn" disabled>Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">7</button>
                        <button class="pagination-btn">Next</button>
                    </div>
                </div>
            </div>

            <!-- View Payment Modal -->
            <div id="viewPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 500px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">💰 Payment Details</h2>
                        <button onclick="closePaymentModal('viewPaymentModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
                    </div>
                    <div id="viewPaymentContent">
                        <!-- Content loaded dynamically -->
                    </div>
                </div>
            </div>

            <!-- Edit Payment Modal -->
            <div id="editPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 450px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">✏️ Edit Payment</h2>
                        <button onclick="closePaymentModal('editPaymentModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
                    </div>
                    <form id="editPaymentForm">
                        <input type="hidden" id="editPaymentId">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">Patient Name</label>
                            <input type="text" id="editPaymentPatient" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; background: #f3f4f6;" readonly>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">Services</label>
                            <input type="text" id="editPaymentServices" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; background: #f3f4f6;" readonly>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">New Amount (₱)</label>
                            <input type="number" id="editPaymentAmount" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;" min="0" step="0.01">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">Reason for Change</label>
                            <input type="text" id="editPaymentReason" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;" placeholder="e.g., Discount applied, Additional procedure">
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 12px;">
                            <button type="button" onclick="closePaymentModal('editPaymentModal')" style="padding: 10px 20px; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">Cancel</button>
                            <button type="button" onclick="savePaymentEdit()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer;">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Print Modal -->
            <div id="printPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 450px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">🖨️ Print Invoice</h2>
                        <button onclick="closePaymentModal('printPaymentModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
                    </div>
                    <div id="printPaymentContent">
                        <!-- Print preview loaded here -->
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                        <button onclick="closePaymentModal('printPaymentModal')" style="padding: 10px 20px; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">Close</button>
                        <button onclick="confirmPrint()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer;">🖨️ Print</button>
                    </div>
                </div>
            </div>

            <!-- Payment Kebab Dropdown - Portal Based (same as queue, patient-records) -->
            <div id="paymentKebabDropdownPortal" class="queue-kebab-dropdown-portal"></div>
            <div id="paymentKebabBackdrop" class="queue-kebab-backdrop"></div>

            <style>
                /* Queue Kebab Menu Styles - Portal Based (same as patient-records, appointments) */
                .queue-kebab-menu {
                    position: relative;
                    display: inline-block;
                }

                .queue-kebab-btn {
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

                .queue-kebab-btn:hover {
                    background-color: #f3f4f6;
                    color: #374151;
                }

                .queue-kebab-btn.active {
                    background-color: #e5e7eb;
                    color: #111827;
                }

                .queue-kebab-dropdown-portal {
                    display: none;
                    position: fixed;
                    background: white;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                    min-width: 160px;
                    max-width: 200px;
                    width: auto;
                    z-index: 99999;
                    overflow: hidden;
                }

                .queue-kebab-dropdown-portal.show {
                    display: block;
                    animation: queueKebabFadeIn 0.15s ease;
                }

                @keyframes queueKebabFadeIn {
                    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
                    to { opacity: 1; transform: scale(1) translateY(0); }
                }

                .queue-kebab-dropdown-portal a {
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

                .queue-kebab-dropdown-portal a:hover {
                    background-color: #f9fafb;
                    color: #111827;
                }

                .queue-kebab-dropdown-portal a svg {
                    flex-shrink: 0;
                }

                .queue-kebab-dropdown-portal a:first-child {
                    border-radius: 8px 8px 0 0;
                }

                .queue-kebab-dropdown-portal a:last-child {
                    border-radius: 0 0 8px 8px;
                }

                .queue-kebab-dropdown-portal a.danger {
                    color: #dc2626;
                }

                .queue-kebab-dropdown-portal a.danger:hover {
                    background-color: #fef2f2;
                }

                .queue-kebab-backdrop {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 99998;
                }

                .queue-kebab-backdrop.show {
                    display: block;
                }

                /* Modal overlay styles */
                .modal-overlay {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 100001;
                    align-items: center;
                    justify-content: center;
                }
                
                .modal-overlay.active {
                    display: flex;
                }
                
                .modal-backdrop {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                }
                
                .modal {
                    background: white;
                    border-radius: 16px;
                    padding: 24px;
                    max-width: 500px;
                    width: 90%;
                    max-height: 85vh;
                    overflow-y: auto;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
                    position: relative;
                    z-index: 100002;
                }

                /* Table styling */
                .table-container {
                    overflow-x: auto;
                }
                
                .data-table {
                    min-width: 900px;
                }

                .page-header {
                    margin-bottom: 0px;
                }
            </style>

            <script>
                // Payment data storage (in real app, this comes from database)
                var paymentData = {
                    'INV-001': { id: 'INV-001', patient: 'Maria Santos', services: 'Root Canal, X-Ray', amount: 5000, date: '2024-01-10', dueDate: '2024-01-17', status: 'paid', phone: '09123456789', address: '123 Main St, City' },
                    'INV-002': { id: 'INV-002', patient: 'Roberto Garcia', services: 'Denture Adjustment', amount: 1500, date: '2024-01-11', dueDate: '2024-01-18', status: 'unpaid', phone: '09123456790', address: '456 Oak Ave, Town' },
                    'INV-003': { id: 'INV-003', patient: 'Ana Reyes', services: 'Oral Prophylaxis', amount: 2000, date: '2024-01-08', dueDate: '2024-01-15', status: 'unpaid', phone: '09123456791', address: '789 Pine Rd, Village' },
                    'INV-004': { id: 'INV-004', patient: 'Juan Dela Cruz', services: 'Tooth Extraction', amount: 3000, date: '2024-01-12', dueDate: '2024-01-19', status: 'paid', phone: '09123456792', address: '321 Elm St, District' }
                };

                var currentPrintId = null;
                var currentInvoiceId = null;

                // Payment Kebab Menu - Portal Based (same as queue, patient-records)
                let paymentKebabDropdown = null;
                let paymentKebabBackdrop = null;
                let paymentActiveButton = null;

                function createPaymentKebabDropdown() {
                    paymentKebabDropdown = document.createElement('div');
                    paymentKebabDropdown.className = 'queue-kebab-dropdown-portal';
                    paymentKebabDropdown.id = 'paymentKebabDropdownPortal';
                    document.body.appendChild(paymentKebabDropdown);

                    paymentKebabBackdrop = document.createElement('div');
                    paymentKebabBackdrop.className = 'queue-kebab-backdrop';
                    paymentKebabBackdrop.id = 'paymentKebabBackdrop';
                    document.body.appendChild(paymentKebabBackdrop);

                    paymentKebabBackdrop.addEventListener('click', closePaymentKebabDropdown);
                }

                function getPaymentMenuItems(invoiceId, status) {
                    if (status === 'paid') {
                        return `
                            <a href="javascript:void(0)" data-action="view" data-invoice-id="${invoiceId}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                View Details
                            </a>
                            <a href="javascript:void(0)" data-action="print" data-invoice-id="${invoiceId}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 6 2 18 2 18 9"/>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                    <rect x="6" y="14" width="12" height="8"/>
                                </svg>
                                Print Invoice
                            </a>
                        `;
                    } else {
                        return `
                            <a href="javascript:void(0)" data-action="view" data-invoice-id="${invoiceId}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                View Details
                            </a>
                            <a href="javascript:void(0)" data-action="mark-paid" data-invoice-id="${invoiceId}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                Mark as Paid
                            </a>
                            <a href="javascript:void(0)" data-action="edit" data-invoice-id="${invoiceId}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Edit Amount
                            </a>
                            <a href="javascript:void(0)" data-action="print" data-invoice-id="${invoiceId}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 6 2 18 2 18 9"/>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                    <rect x="6" y="14" width="12" height="8"/>
                                </svg>
                                Print Invoice
                            </a>
                        `;
                    }
                }

                function positionPaymentKebabDropdown(button) {
                    if (!paymentKebabDropdown || !button) return;

                    const rect = button.getBoundingClientRect();
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    
                    const padding = 15;
                    const dropdownWidth = 170;
                    const dropdownHeight = status === 'paid' ? 100 : 160;
                    
                    let left = rect.right + 5;
                    let top = rect.top;

                    if (left + dropdownWidth > viewportWidth - padding) {
                        left = rect.left - dropdownWidth - 5;
                    }
                    
                    if (left < padding) {
                        left = padding;
                    }
                    
                    if (top + dropdownHeight > viewportHeight - padding) {
                        top = rect.bottom - dropdownHeight;
                    }
                    
                    if (top < padding) {
                        top = padding;
                    }

                    paymentKebabDropdown.style.left = left + 'px';
                    paymentKebabDropdown.style.top = top + 'px';
                }

                function openPaymentKebabDropdown(button) {
                    if (!paymentKebabDropdown) {
                        createPaymentKebabDropdown();
                    }

                    const invoiceId = button.dataset.invoiceId;
                    const status = button.dataset.status;

                    paymentKebabDropdown.innerHTML = getPaymentMenuItems(invoiceId, status);
                    positionPaymentKebabDropdown(button);

                    paymentKebabDropdown.classList.add('show');
                    paymentKebabBackdrop.classList.add('show');
                    paymentActiveButton = button;
                    button.classList.add('active');

                    paymentKebabDropdown.addEventListener('click', handlePaymentKebabClick);
                }

                function closePaymentKebabDropdown() {
                    if (paymentKebabDropdown) {
                        paymentKebabDropdown.classList.remove('show');
                        paymentKebabDropdown.innerHTML = '';
                    }
                    if (paymentKebabBackdrop) {
                        paymentKebabBackdrop.classList.remove('show');
                    }
                    if (paymentActiveButton) {
                        paymentActiveButton.classList.remove('active');
                        paymentActiveButton = null;
                    }
                }

                function handlePaymentKebabClick(e) {
                    const link = e.target.closest('a[data-action]');
                    if (!link) return;

                    e.preventDefault();
                    e.stopPropagation();

                    const action = link.dataset.action;
                    const invoiceId = link.dataset.invoiceId;

                    closePaymentKebabDropdown();

                    switch(action) {
                        case 'view':
                            viewPayment(invoiceId);
                            break;
                        case 'mark-paid':
                            markAsPaid(invoiceId);
                            break;
                        case 'edit':
                            editPayment(invoiceId);
                            break;
                        case 'print':
                            printPayment(invoiceId);
                            break;
                    }
                }

                // Click handler for kebab buttons
                document.addEventListener('click', function(e) {
                    const button = e.target.closest('.queue-kebab-btn');
                    if (button) {
                        e.preventDefault();
                        e.stopPropagation();

                        if (paymentActiveButton === button && paymentKebabDropdown && paymentKebabDropdown.classList.contains('show')) {
                            closePaymentKebabDropdown();
                        } else {
                            if (paymentActiveButton) {
                                paymentActiveButton.classList.remove('active');
                            }
                            openPaymentKebabDropdown(button);
                        }
                        return;
                    }
                });

                // View Payment Details
                function viewPayment(invoiceId) {
                    var payment = paymentData[invoiceId];
                    if (!payment) return;
                    
                    var statusBadge = payment.status === 'paid' 
                        ? '<span class="status-badge paid">Paid</span>'
                        : '<span class="status-badge unpaid">Unpaid</span>';
                    
                    document.getElementById('viewPaymentContent').innerHTML = 
                        '<div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 16px;">' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Invoice #</span>' +
                        '<span style="font-weight: 600;">' + payment.id + '</span>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Patient</span>' +
                        '<span style="font-weight: 600;">' + payment.patient + '</span>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Phone</span>' +
                        '<span>' + payment.phone + '</span>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Address</span>' +
                        '<span>' + payment.address + '</span>' +
                        '</div>' +
                        '</div>' +
                        '<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px;">' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Services</span>' +
                        '<span>' + payment.services + '</span>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Amount</span>' +
                        '<span style="font-weight: 700; font-size: 1.25rem; color: #059669;">₱' + payment.amount.toLocaleString() + '</span>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Date</span>' +
                        '<span>' + payment.date + '</span>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Due Date</span>' +
                        '<span>' + payment.dueDate + '</span>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Status</span>' +
                        statusBadge +
                        '</div>' +
                        '</div>';
                    
                    document.getElementById('viewPaymentModal').style.display = 'flex';
                }

                // Mark as Paid
                function markAsPaid(invoiceId) {
                    if (confirm('Mark invoice ' + invoiceId + ' as paid?')) {
                        if (paymentData[invoiceId]) {
                            paymentData[invoiceId].status = 'paid';
                        }
                        
                        var rows = document.querySelectorAll('#paymentTableBody tr');
                        rows.forEach(function(row) {
                            if (row.querySelector('td').textContent === invoiceId) {
                                var statusCell = row.querySelector('td:nth-child(7)');
                                statusCell.innerHTML = '<span class="status-badge paid">Paid</span>';
                                
                                var kebabBtn = row.querySelector('.queue-kebab-btn');
                                kebabBtn.setAttribute('data-status', 'paid');
                            }
                        });
                        
                        alert('Invoice marked as paid!');
                    }
                }

                // Edit Payment
                function editPayment(invoiceId) {
                    var payment = paymentData[invoiceId];
                    if (!payment) return;
                    
                    document.getElementById('editPaymentId').value = invoiceId;
                    document.getElementById('editPaymentPatient').value = payment.patient;
                    document.getElementById('editPaymentServices').value = payment.services;
                    document.getElementById('editPaymentAmount').value = payment.amount;
                    document.getElementById('editPaymentReason').value = '';
                    
                    document.getElementById('editPaymentModal').style.display = 'flex';
                }

                // Save Payment Edit
                function savePaymentEdit() {
                    var invoiceId = document.getElementById('editPaymentId').value;
                    var newAmount = parseFloat(document.getElementById('editPaymentAmount').value);
                    var reason = document.getElementById('editPaymentReason').value;
                    
                    if (isNaN(newAmount) || newAmount < 0) {
                        alert('Please enter a valid amount');
                        return;
                    }
                    
                    if (paymentData[invoiceId]) {
                        paymentData[invoiceId].amount = newAmount;
                    }
                    
                    var rows = document.querySelectorAll('#paymentTableBody tr');
                    rows.forEach(function(row) {
                        if (row.querySelector('td').textContent === invoiceId) {
                            row.querySelector('td:nth-child(4)').textContent = '₱' + newAmount.toLocaleString();
                        }
                    });
                    
                    alert('Payment amount updated successfully!');
                    closePaymentModal('editPaymentModal');
                }

                // Print Payment
                function printPayment(invoiceId) {
                    var payment = paymentData[invoiceId];
                    if (!payment) return;
                    
                    currentPrintId = invoiceId;
                    
                    var statusBadge = payment.status === 'paid' 
                        ? '<span style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">PAID</span>'
                        : '<span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">UNPAID</span>';
                    
                    document.getElementById('printPaymentContent').innerHTML = 
                        '<div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px;">' +
                        '<div style="text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #e5e7eb;">' +
                        '<h3 style="margin: 0 0 8px; font-size: 1.5rem;">Dental Clinic</h3>' +
                        '<p style="margin: 0; color: #6b7280; font-size: 0.875rem;"> smiles Dental Care Center</p>' +
                        '<p style="margin: 4px 0 0; color: #6b7280; font-size: 0.75rem;">123 Dental Street, City</p>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 16px;">' +
                        '<div><span style="color: #6b7280; font-size: 0.875rem;">Invoice #:</span> <strong>' + payment.id + '</strong></div>' +
                        '<div><span style="color: #6b7280; font-size: 0.875rem;">Date:</span> ' + payment.date + '</div>' +
                        '</div>' +
                        '<div style="margin-bottom: 16px;"><span style="color: #6b7280; font-size: 0.875rem;">Patient:</span> <strong>' + payment.patient + '</strong></div>' +
                        '<div style="margin-bottom: 16px;"><span style="color: #6b7280; font-size: 0.875rem;">Services:</span> ' + payment.services + '</div>' +
                        '<div style="margin-bottom: 16px;"><span style="color: #6b7280; font-size: 0.875rem;">Due Date:</span> ' + payment.dueDate + '</div>' +
                        '<div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin: 24px 0; text-align: center;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Total Amount</span><br>' +
                        '<span style="font-size: 2rem; font-weight: 700; color: #059669;">₱' + payment.amount.toLocaleString() + '</span>' +
                        '</div>' +
                        '<div style="text-align: center;">' + statusBadge + '</div>' +
                        '<div style="text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 0.75rem;">' +
                        '<p style="margin: 0;">Thank you for choosing our dental clinic!</p>' +
                        '<p style="margin: 4px 0 0;">Please keep this receipt for your records.</p>' +
                        '</div>' +
                        '</div>';
                    
                    document.getElementById('printPaymentModal').style.display = 'flex';
                }

                // Confirm Print
                function confirmPrint() {
                    window.print();
                }

                // Close modal
                function closePaymentModal(modalId) {
                    document.getElementById(modalId).style.display = 'none';
                }

                // Close modals on backdrop click
                document.querySelectorAll('.modal-overlay').forEach(function(modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            modal.style.display = 'none';
                        }
                    });
                });

                // Close menus on escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closePaymentKebabDropdown();
                        document.querySelectorAll('.modal-overlay').forEach(function(modal) {
                            modal.style.display = 'none';
                        });
                    }
                });
            </script>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
