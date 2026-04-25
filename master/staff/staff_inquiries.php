<?php
$pageTitle = 'Inquiry / Consultation';

require_once '../../config/database.php';
require_once '../../includes/inquiry_module_helper.php';

ensureInquiryModuleSchema($pdo);

$statusFilter = trim($_GET['status'] ?? '');
$serviceFilter = (int)($_GET['service_id'] ?? 0);
$dateFilter = trim($_GET['date'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = ["COALESCE(i.is_archived, 0) = 0"];
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, getInquiryStatuses(), true)) {
    $where[] = "i.status = ?";
    $params[] = $statusFilter;
}

if ($serviceFilter > 0) {
    $where[] = "i.service_id = ?";
    $params[] = $serviceFilter;
}

if ($dateFilter !== '') {
    $where[] = "DATE(i.created_at) = ?";
    $params[] = $dateFilter;
}

if ($search !== '') {
    $where[] = "(COALESCE(i.full_name, '') LIKE ? OR COALESCE(i.phone, '') LIKE ? OR COALESCE(i.email, '') LIKE ? OR COALESCE(i.concern, i.inquiry_message, '') LIKE ? OR COALESCE(s.name, '') LIKE ?)";
    for ($i = 0; $i < 5; $i++) {
        $params[] = '%' . $search . '%';
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$query = "
    SELECT i.*,
           s.name AS service_name,
           ass.first_name AS assigned_first_name,
           ass.last_name AS assigned_last_name,
           ass.username AS assigned_username
    FROM inquiries i
    LEFT JOIN services s ON s.id = i.service_id
    LEFT JOIN users ass ON ass.id = i.assigned_staff_id
    $whereSql
    ORDER BY i.created_at DESC, i.id DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allStats = $pdo->query("
    SELECT status, created_at, preferred_date
    FROM inquiries
    WHERE COALESCE(is_archived, 0) = 0
")->fetchAll(PDO::FETCH_ASSOC);

$services = fetchInquiryServices($pdo);
$staffMembers = fetchAssignableStaff($pdo);

$summary = [
    'total' => count($allStats),
    'pending' => count(array_filter($allStats, fn($row) => ($row['status'] ?? '') === 'Pending')),
    'scheduled_today' => count(array_filter($allStats, function ($row) {
        $date = $row['preferred_date'] ?: substr((string)($row['created_at'] ?? ''), 0, 10);
        return ($row['status'] ?? '') === 'Scheduled' && $date === date('Y-m-d');
    })),
    'completed' => count(array_filter($allStats, fn($row) => ($row['status'] ?? '') === 'Completed')),
];

require_once '../../includes/staff_layout_start.php';
?>
<style>
.summary-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}
.summary-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 20px;
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.05);
    display: flex;
    align-items: center;
    gap: 16px;
}
.summary-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.summary-icon svg {
    width: 26px;
    height: 26px;
}
.summary-info h3 {
    font-size: 1.8rem;
    color: #0f172a;
    margin: 0;
}
.summary-info p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 0.95rem;
}
.module-shell {
    display: grid;
    gap: 24px;
}
.toolbar-card,
.table-card,
.empty-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.05);
}
.toolbar-card {
    padding: 22px;
}
.toolbar-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 18px;
}
.toolbar-title h2 {
    margin: 0;
    color: #0f172a;
    font-size: 1.55rem;
}
.toolbar-title p {
    margin: 6px 0 0;
    color: #64748b;
}
.toolbar-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}
.search-input {
    width: min(420px, 100%);
    border: 1px solid #d1d5db;
    border-radius: 14px;
    padding: 13px 16px;
    font: inherit;
}
.filter-row {
    display: grid;
    grid-template-columns: 1.25fr repeat(3, minmax(0, 180px));
    gap: 14px;
}
.filter-input,
.filter-select,
.filter-date {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 14px;
    padding: 13px 14px;
    font: inherit;
    background: #fff;
}
.btn-primary,
.btn-secondary,
.btn-danger {
    border: none;
    border-radius: 14px;
    padding: 12px 18px;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}
.btn-primary { background: #2563eb; color: #fff; }
.btn-secondary { background: #eef2ff; color: #1d4ed8; }
.btn-danger { background: #fee2e2; color: #b91c1c; }
.table-card { padding: 8px 0 0; overflow: hidden; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; min-width: 1280px; }
.data-table th {
    text-align: left;
    padding: 18px 24px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    border-bottom: 1px solid #e5e7eb;
}
.data-table td {
    padding: 18px 24px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: top;
    color: #0f172a;
    font-size: 14px;
}
.patient-name {
    font-weight: 700;
    color: #0f172a;
}
.muted {
    color: #64748b;
    line-height: 1.55;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}
.status-Pending { background: #fef3c7; color: #92400e; }
.status-Contacted { background: #dbeafe; color: #1d4ed8; }
.status-Scheduled { background: #dcfce7; color: #166534; }
.status-Completed { background: #e0f2fe; color: #0369a1; }
.status-Cancelled { background: #fee2e2; color: #b91c1c; }
.urgency-pill {
    display: inline-flex;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}
.urgency-High { background: #fee2e2; color: #b91c1c; }
.urgency-Medium { background: #fef3c7; color: #92400e; }
.urgency-Low { background: #dcfce7; color: #166534; }
.actions-cell {
    width: 72px;
    text-align: right;
}
.meatball-btn {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #f3f4f6;
    cursor: pointer;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}
.meatball-btn:hover,
.meatball-btn[aria-expanded="true"] {
    background: #e9eef5;
    border-color: #dbe3ef;
    color: #0f172a;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
}
.meatball-btn i {
    font-size: 14px;
    line-height: 1;
}
.meatball-icon {
    width: 14px;
    height: 14px;
    display: block;
}
.meatball-icon circle {
    fill: currentColor;
}
.meatball-menu {
    position: fixed;
    min-width: 200px;
    max-width: 220px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    padding: 6px;
    z-index: 200000;
    display: none;
    overflow: hidden;
}
.meatball-menu.show {
    display: block;
    animation: meatballFadeIn 0.15s ease;
}
@keyframes meatballFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.meatball-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 199999;
}
.meatball-backdrop.show { display: block; }
.menu-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 6px 4px;
}
.menu-action {
    width: 100%;
    border: none;
    background: none;
    text-align: left;
    padding: 10px 12px;
    border-radius: 10px;
    cursor: pointer;
    font: inherit;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 500;
}
.menu-action i {
    width: 16px;
    text-align: center;
    color: #6b7280;
}
.menu-action:hover { background: #f8fafc; }
.menu-action:focus-visible,
.meatball-btn:focus-visible,
.modal-close:focus-visible,
.btn-primary:focus-visible,
.btn-secondary:focus-visible,
.btn-danger:focus-visible,
.form-input:focus-visible,
.form-select:focus-visible,
.form-textarea:focus-visible,
.search-input:focus-visible,
.filter-select:focus-visible,
.filter-date:focus-visible {
    outline: 3px solid rgba(37, 99, 235, 0.18);
    outline-offset: 2px;
}
.menu-action.danger { color: #dc2626; }
.menu-action.danger i { color: #dc2626; }
.menu-action.warn { color: #b45309; }
.menu-action.warn i { color: #b45309; }
.empty-card {
    padding: 54px 28px;
    text-align: center;
}
.empty-card h3 { font-size: 1.5rem; color: #0f172a; margin-bottom: 10px; }
.empty-card p { color: #64748b; margin-bottom: 20px; }
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1600;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal-card {
    width: min(880px, 100%);
    max-height: 92vh;
    overflow: auto;
    background: #fff;
    border-radius: 22px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 30px 60px rgba(15, 23, 42, 0.2);
}
.modal-header {
    padding: 22px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 { margin: 0; font-size: 1.25rem; color: #0f172a; }
.modal-close {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    cursor: pointer;
}
.modal-body {
    padding: 24px;
}
.detail-grid,
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 20px;
}
.detail-block.full,
.form-group.full {
    grid-column: 1 / -1;
}
.detail-label,
.form-label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    font-weight: 700;
}
.detail-value {
    color: #0f172a;
    line-height: 1.6;
}
.detail-panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    margin-bottom: 18px;
    border-radius: 18px;
    background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
    border: 1px solid #dbeafe;
}
.detail-panel-header h4 {
    margin: 0 0 6px;
    color: #0f172a;
    font-size: 1.1rem;
}
.detail-panel-header p {
    margin: 0;
    color: #64748b;
}
.detail-stack {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.detail-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid #dbeafe;
    color: #1e3a8a;
    font-size: 12px;
    font-weight: 700;
}
.form-input,
.form-select,
.form-textarea {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 14px;
    padding: 13px 14px;
    font: inherit;
    background: #fff;
}
.form-textarea { min-height: 120px; resize: vertical; }
.modal-footer {
    padding: 0 24px 24px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}
.toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    min-width: 280px;
    background: #0f172a;
    color: #fff;
    padding: 14px 16px;
    border-radius: 14px;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.18);
    z-index: 1700;
    display: none;
}
.toast.show { display: block; }
@media (max-width: 1200px) {
    .summary-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .filter-row { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
    .summary-cards,
    .filter-row,
    .detail-grid,
    .form-grid { grid-template-columns: 1fr; }
    .toolbar-top { flex-direction: column; align-items: stretch; }
    .toolbar-actions { justify-content: stretch; }
    .search-input { width: 100%; }
}
</style>

<div class="module-shell">
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon" style="background:#dbeafe;color:#1d4ed8;">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 4h12a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3H9l-4 4v-4H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Z"/></svg>
            </div>
            <div class="summary-info"><h3><?php echo $summary['total']; ?></h3><p>Total Inquiries</p></div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background:#fef3c7;color:#92400e;">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 2h10v2a5 5 0 0 1-2.2 4.15L13.4 9 14.8 9.85A5 5 0 0 1 17 14v8H7v-8a5 5 0 0 1 2.2-4.15L10.6 9 9.2 8.15A5 5 0 0 1 7 4V2Zm2 12v6h6v-6a3 3 0 0 0-1.32-2.49L12 10.5l-1.68 1.01A3 3 0 0 0 9 14Zm1.5-10a3 3 0 0 0 1.32 2.49L12 7.5l.18-.01A3 3 0 0 0 13.5 4h-3Z"/></svg>
            </div>
            <div class="summary-info"><h3><?php echo $summary['pending']; ?></h3><p>Pending</p></div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background:#dcfce7;color:#166534;">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 2h2v2h6V2h2v2h1a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h1V2Zm12 8H5v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8Zm-4.7 2.3 1.4 1.4-4.5 4.5-2.5-2.5 1.4-1.4 1.1 1.1 3.1-3.1Z"/></svg>
            </div>
            <div class="summary-info"><h3><?php echo $summary['scheduled_today']; ?></h3><p>Scheduled Today</p></div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background:#e0f2fe;color:#0369a1;">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm4.3 6.3-5.6 5.6-2-2-1.4 1.4 3.4 3.4 7-7-1.4-1.4Z"/></svg>
            </div>
            <div class="summary-info"><h3><?php echo $summary['completed']; ?></h3><p>Completed</p></div>
        </div>
    </div>

    <div class="toolbar-card">
        <div class="toolbar-top">
            <div class="toolbar-title">
                <h2>Inquiry / Consultation</h2>
                <p>Review patient concerns, update follow-up progress, and convert approved requests into appointments.</p>
            </div>
            <div class="toolbar-actions">
                <button type="button" class="btn-primary" onclick="openCreateModal()">+ New Inquiry</button>
            </div>
        </div>
        <form method="GET" class="filter-row">
            <input type="text" name="search" class="search-input" placeholder="Search patient, contact, service, or concern..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <?php foreach (getInquiryStatuses() as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" class="filter-date" value="<?php echo htmlspecialchars($dateFilter); ?>">
            <select name="service_id" class="filter-select">
                <option value="0">All Services</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?php echo (int)$service['id']; ?>" <?php echo $serviceFilter === (int)$service['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($service['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($inquiries)): ?>
        <div class="empty-card">
            <h3>No inquiries yet</h3>
            <p>No inquiry / consultation requests match the current filters.</p>
            <button type="button" class="btn-primary" onclick="openCreateModal()">Create New Inquiry</button>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Contact Info</th>
                            <th>Service / Inquiry</th>
                            <th>Preferred Schedule</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Assigned Staff</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inquiries as $inquiry): ?>
                            <?php
                            $fullName = trim(($inquiry['full_name'] ?: trim(($inquiry['first_name'] ?? '') . ' ' . ($inquiry['middle_name'] ?? '') . ' ' . ($inquiry['last_name'] ?? ''))));
                            $schedule = [];
                            if (!empty($inquiry['preferred_date'])) {
                                $schedule[] = date('M d, Y', strtotime($inquiry['preferred_date']));
                            }
                            if (!empty($inquiry['preferred_time'])) {
                                $schedule[] = date('g:i A', strtotime($inquiry['preferred_time']));
                            }
                            $assigned = trim(($inquiry['assigned_first_name'] ?? '') . ' ' . ($inquiry['assigned_last_name'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <div class="patient-name"><?php echo htmlspecialchars($fullName ?: 'Unknown Patient'); ?></div>
                                    <div class="muted"><?php echo htmlspecialchars($inquiry['email'] ?: 'No email'); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($inquiry['phone'] ?: ($inquiry['contact_info'] ?: 'No contact')); ?></div>
                                </td>
                                <td>
                                    <div class="patient-name"><?php echo htmlspecialchars($inquiry['service_name'] ?: 'General Consultation'); ?></div>
                                    <div class="muted"><?php echo htmlspecialchars(mb_strlen($inquiry['concern'] ?: ($inquiry['inquiry_message'] ?? '')) > 80 ? mb_substr($inquiry['concern'] ?: ($inquiry['inquiry_message'] ?? ''), 0, 80) . '...' : ($inquiry['concern'] ?: ($inquiry['inquiry_message'] ?? 'No concern provided'))); ?></div>
                                    <?php if (!empty($inquiry['urgency'])): ?>
                                        <div style="margin-top:8px;"><span class="urgency-pill urgency-<?php echo htmlspecialchars($inquiry['urgency']); ?>"><?php echo htmlspecialchars($inquiry['urgency']); ?> Urgency</span></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($schedule ? implode(' at ', $schedule) : 'No preference'); ?></td>
                                <td><?php echo htmlspecialchars($inquiry['source'] ?: 'Patient Portal'); ?></td>
                                <td><span class="status-badge status-<?php echo htmlspecialchars($inquiry['status']); ?>"><?php echo htmlspecialchars($inquiry['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($assigned ?: ($inquiry['assigned_username'] ?: 'Unassigned')); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($inquiry['created_at'])); ?></td>
                                <td class="actions-cell">
                                    <button type="button" class="meatball-btn" aria-label="Open actions" aria-haspopup="menu" aria-expanded="false" data-inquiry-id="<?php echo (int)$inquiry['id']; ?>" onclick="openActionMenu(event)">
                                        <svg class="meatball-icon" viewBox="0 0 16 16" aria-hidden="true">
                                            <circle cx="8" cy="3" r="1.5"></circle>
                                            <circle cx="8" cy="8" r="1.5"></circle>
                                            <circle cx="8" cy="13" r="1.5"></circle>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="meatball-menu" id="actionMenu"></div>
<div class="meatball-backdrop" id="actionMenuBackdrop"></div>

<div class="modal-overlay" id="viewModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Inquiry Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="detail-grid" id="viewDetails"></div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="formModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="formModalTitle">New Inquiry</h3>
            <button type="button" class="modal-close" onclick="closeModal('formModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="inquiryFormModal">
            <div class="modal-body">
                <input type="hidden" name="id" id="form_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="form_full_name">Patient Name</label>
                        <input class="form-input" id="form_full_name" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="form_phone">Phone</label>
                        <input class="form-input" id="form_phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="form_email">Email</label>
                        <input class="form-input" id="form_email" name="email" type="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="form_service_id">Service</label>
                        <select class="form-select" id="form_service_id" name="service_id">
                            <option value="">Select service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo (int)$service['id']; ?>"><?php echo htmlspecialchars($service['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="form_preferred_date">Preferred Date</label>
                        <input class="form-input" id="form_preferred_date" name="preferred_date" type="date">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="form_preferred_time">Preferred Time</label>
                        <input class="form-input" id="form_preferred_time" name="preferred_time" type="time">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="form_pain_level">Pain Level</label>
                        <input class="form-input" id="form_pain_level" name="pain_level" placeholder="Example: 3 - Moderate">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="form_urgency">Urgency</label>
                        <select class="form-select" id="form_urgency" name="urgency">
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="form_source">Source</label>
                        <input class="form-input" id="form_source" name="source" value="Staff Dashboard">
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="form_concern">Concern / Message</label>
                        <textarea class="form-textarea" id="form_concern" name="concern" required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('formModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Inquiry</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="statusModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Change Status</h3>
            <button type="button" class="modal-close" onclick="closeModal('statusModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="statusForm">
            <div class="modal-body">
                <input type="hidden" name="id" id="status_id">
                <div class="form-group full">
                    <label class="form-label" for="status_value">Status</label>
                    <select class="form-select" id="status_value" name="status">
                        <?php foreach (getInquiryStatuses() as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="assignModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Assign Staff</h3>
            <button type="button" class="modal-close" onclick="closeModal('assignModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="assignForm">
            <div class="modal-body">
                <input type="hidden" name="id" id="assign_id">
                <div class="form-group full">
                    <label class="form-label" for="assigned_staff_id">Assigned Staff</label>
                    <select class="form-select" id="assigned_staff_id" name="assigned_staff_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($staffMembers as $member): ?>
                            <?php $memberName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')); ?>
                            <option value="<?php echo (int)$member['id']; ?>"><?php echo htmlspecialchars($memberName ?: $member['username']); ?> (<?php echo htmlspecialchars(ucfirst($member['role'])); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Assignment</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="notesModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Add Internal Notes</h3>
            <button type="button" class="modal-close" onclick="closeModal('notesModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="notesForm">
            <div class="modal-body">
                <input type="hidden" name="id" id="notes_id">
                <div class="form-group full">
                    <label class="form-label" for="notes_value">Internal Notes</label>
                    <textarea class="form-textarea" id="notes_value" name="notes"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('notesModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Notes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="convertModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Convert to Appointment</h3>
            <button type="button" class="modal-close" onclick="closeModal('convertModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="convertForm">
            <div class="modal-body">
                <input type="hidden" name="id" id="convert_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="appointment_date">Appointment Date</label>
                        <input class="form-input" id="appointment_date" name="appointment_date" type="date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="appointment_time">Appointment Time</label>
                        <input class="form-input" id="appointment_time" name="appointment_time" type="time" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('convertModal')">Cancel</button>
                <button type="submit" class="btn-primary">Create Appointment</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="confirmModal">
    <div class="modal-card" style="width:min(460px,100%);">
        <div class="modal-header">
            <h3 id="confirmTitle">Confirm Action</h3>
            <button type="button" class="modal-close" onclick="closeModal('confirmModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="detail-value" id="confirmMessage">Are you sure?</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('confirmModal')">Cancel</button>
            <button type="button" class="btn-danger" id="confirmActionBtn">Confirm</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const inquiries = <?php echo json_encode($inquiries); ?>;
const inquiryMap = Object.fromEntries(inquiries.map((item) => [String(item.id), item]));
const actionMenu = document.getElementById('actionMenu');
const actionMenuBackdrop = document.getElementById('actionMenuBackdrop');
let activeInquiry = null;
let activeMenuButton = null;
let pendingConfirmAction = null;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[character]));
}

function formatMultilineText(value, fallback) {
    const safeValue = escapeHtml(value || fallback || '');
    return safeValue.replace(/\n/g, '<br>');
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.background = isError ? '#b91c1c' : '#0f172a';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2600);
}

function closeActionMenu() {
    actionMenu.classList.remove('show');
    actionMenu.innerHTML = '';
    actionMenu.style.left = '';
    actionMenu.style.right = '';
    actionMenu.style.top = '';
    actionMenu.style.visibility = '';
    actionMenuBackdrop.classList.remove('show');
    if (activeMenuButton) {
        activeMenuButton.setAttribute('aria-expanded', 'false');
    }
    activeMenuButton = null;
}

actionMenuBackdrop.addEventListener('click', closeActionMenu);

function openActionMenu(event) {
    event.stopPropagation();
    const triggerButton = event.currentTarget;
    const inquiryId = triggerButton.dataset.inquiryId;
    const inquiry = inquiryMap[inquiryId];

    if (!inquiry) {
        showToast('Unable to load inquiry actions.', true);
        return;
    }

    if (activeMenuButton === triggerButton && actionMenu.classList.contains('show')) {
        closeActionMenu();
        return;
    }

    activeInquiry = inquiry;
    activeMenuButton = triggerButton;
    const rect = triggerButton.getBoundingClientRect();
    actionMenu.innerHTML = `
        <button class="menu-action" type="button" onclick="openViewModal()"><i class="fas fa-eye"></i> View Details</button>
        <button class="menu-action" type="button" onclick="openEditModal()"><i class="fas fa-pen"></i> Edit Inquiry</button>
        <button class="menu-action" type="button" onclick="openStatusModal()"><i class="fas fa-flag"></i> Change Status</button>
        <button class="menu-action" type="button" onclick="openAssignModal()"><i class="fas fa-user-check"></i> Assign Staff</button>
        <button class="menu-action" type="button" onclick="openNotesModal()"><i class="fas fa-note-sticky"></i> Add Internal Notes</button>
        <div class="menu-divider"></div>
        <button class="menu-action" type="button" onclick="openConvertModal()"><i class="fas fa-calendar-plus"></i> Convert to Appointment</button>
        <button class="menu-action warn" type="button" onclick="confirmArchiveInquiry()"><i class="fas fa-box-archive"></i> Archive Inquiry</button>
        <button class="menu-action danger" type="button" onclick="confirmDeleteInquiry()"><i class="fas fa-trash-can"></i> Delete Inquiry</button>
    `;
    actionMenu.classList.add('show');
    actionMenuBackdrop.classList.add('show');
    actionMenu.style.display = 'block';
    actionMenu.style.visibility = 'hidden';
    const menuRect = actionMenu.getBoundingClientRect();
    const viewportPadding = 12;
    const menuWidth = menuRect.width || 200;
    const menuHeight = menuRect.height || 260;

    let left = rect.left - menuWidth - 8;
    let top = rect.top + (rect.height / 2) - (menuHeight / 2);

    if (left < viewportPadding) {
        left = rect.right + 8;
    }
    if (left + menuWidth > window.innerWidth - viewportPadding) {
        left = window.innerWidth - menuWidth - viewportPadding;
    }
    if (top + menuHeight > window.innerHeight - viewportPadding) {
        top = window.innerHeight - viewportPadding - menuHeight;
    }
    if (top < viewportPadding) {
        top = viewportPadding;
    }

    actionMenu.style.top = `${top}px`;
    actionMenu.style.left = `${left}px`;
    actionMenu.style.visibility = 'visible';
    triggerButton.setAttribute('aria-expanded', 'true');
}

window.addEventListener('resize', closeActionMenu);
window.addEventListener('scroll', closeActionMenu, true);
document.addEventListener('click', (event) => {
    if (!event.target.closest('.meatball-btn') && !event.target.closest('.meatball-menu')) {
        closeActionMenu();
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeActionMenu();
    }
});

function openModal(id) {
    document.getElementById(id).classList.add('active');
    closeActionMenu();
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    if (id === 'confirmModal') {
        pendingConfirmAction = null;
    }
}

document.querySelectorAll('.modal-overlay').forEach((overlay) => {
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});

function openViewModal() {
    const schedule = [
        activeInquiry.preferred_date ? new Date(activeInquiry.preferred_date).toLocaleDateString() : '',
        activeInquiry.preferred_time ? new Date(`1970-01-01T${activeInquiry.preferred_time}`).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}) : ''
    ].filter(Boolean).join(' at ') || 'No preference';

    const assigned = [activeInquiry.assigned_first_name, activeInquiry.assigned_last_name].filter(Boolean).join(' ') || activeInquiry.assigned_username || 'Unassigned';
    document.getElementById('viewDetails').innerHTML = `
        <div class="detail-block full">
            <div class="detail-panel-header">
                <div>
                    <h4>${escapeHtml(activeInquiry.full_name || 'Unknown Patient')}</h4>
                    <p>${escapeHtml(activeInquiry.service_name || 'General Consultation')}</p>
                </div>
                <div class="detail-stack">
                    <span class="detail-chip">Pain: ${escapeHtml(activeInquiry.pain_level || 'N/A')}</span>
                    <span class="status-badge status-${escapeHtml(activeInquiry.status || 'Pending')}">${escapeHtml(activeInquiry.status || 'Pending')}</span>
                </div>
            </div>
        </div>
        <div class="detail-block">
            <div class="detail-label">Patient Name</div>
            <div class="detail-value">${escapeHtml(activeInquiry.full_name || 'Unknown Patient')}</div>
        </div>
        <div class="detail-block">
            <div class="detail-label">Contact Info</div>
            <div class="detail-value">${escapeHtml(activeInquiry.phone || activeInquiry.contact_info || 'No phone')}<br>${escapeHtml(activeInquiry.email || 'No email')}</div>
        </div>
        <div class="detail-block">
            <div class="detail-label">Service</div>
            <div class="detail-value">${escapeHtml(activeInquiry.service_name || 'General Consultation')}</div>
        </div>
        <div class="detail-block">
            <div class="detail-label">Preferred Date / Time</div>
            <div class="detail-value">${escapeHtml(schedule)}</div>
        </div>
        <div class="detail-block">
            <div class="detail-label">Pain Level</div>
            <div class="detail-value">${escapeHtml(activeInquiry.pain_level || 'Not specified')}</div>
        </div>
        <div class="detail-block">
            <div class="detail-label">Status</div>
            <div class="detail-value"><span class="status-badge status-${escapeHtml(activeInquiry.status || 'Pending')}">${escapeHtml(activeInquiry.status || 'Pending')}</span></div>
        </div>
        <div class="detail-block">
            <div class="detail-label">Assigned Staff</div>
            <div class="detail-value">${escapeHtml(assigned)}</div>
        </div>
        <div class="detail-block">
            <div class="detail-label">Source</div>
            <div class="detail-value">${escapeHtml(activeInquiry.source || 'Patient Portal')}</div>
        </div>
        <div class="detail-block full">
            <div class="detail-label">Concern</div>
            <div class="detail-value">${formatMultilineText(activeInquiry.concern || activeInquiry.inquiry_message, 'No concern provided')}</div>
        </div>
        <div class="detail-block full">
            <div class="detail-label">Internal Notes</div>
            <div class="detail-value">${formatMultilineText(activeInquiry.notes, 'No internal notes yet.')}</div>
        </div>
    `;
    openModal('viewModal');
}

function openCreateModal() {
    activeInquiry = null;
    document.getElementById('formModalTitle').textContent = 'New Inquiry';
    document.getElementById('inquiryFormModal').reset();
    document.getElementById('form_id').value = '';
    document.getElementById('form_source').value = 'Staff Dashboard';
    openModal('formModal');
}

function openEditModal() {
    document.getElementById('formModalTitle').textContent = 'Edit Inquiry';
    document.getElementById('form_id').value = activeInquiry.id;
    document.getElementById('form_full_name').value = activeInquiry.full_name || '';
    document.getElementById('form_phone').value = activeInquiry.phone || activeInquiry.contact_info || '';
    document.getElementById('form_email').value = activeInquiry.email || '';
    document.getElementById('form_service_id').value = activeInquiry.service_id || '';
    document.getElementById('form_preferred_date').value = activeInquiry.preferred_date || '';
    document.getElementById('form_preferred_time').value = activeInquiry.preferred_time || '';
    document.getElementById('form_pain_level').value = activeInquiry.pain_level || '';
    document.getElementById('form_urgency').value = activeInquiry.urgency || 'Low';
    document.getElementById('form_source').value = activeInquiry.source || 'Patient Portal';
    document.getElementById('form_concern').value = activeInquiry.concern || activeInquiry.inquiry_message || '';
    openModal('formModal');
}

function openStatusModal() {
    document.getElementById('status_id').value = activeInquiry.id;
    document.getElementById('status_value').value = activeInquiry.status;
    openModal('statusModal');
}

function openAssignModal() {
    document.getElementById('assign_id').value = activeInquiry.id;
    document.getElementById('assigned_staff_id').value = activeInquiry.assigned_staff_id || '';
    openModal('assignModal');
}

function openNotesModal() {
    document.getElementById('notes_id').value = activeInquiry.id;
    document.getElementById('notes_value').value = activeInquiry.notes || '';
    openModal('notesModal');
}

function openConvertModal() {
    document.getElementById('convert_id').value = activeInquiry.id;
    document.getElementById('appointment_date').value = activeInquiry.preferred_date || '';
    document.getElementById('appointment_time').value = activeInquiry.preferred_time || '';
    openModal('convertModal');
}

function openConfirmModal(title, message, action, buttonLabel = 'Confirm') {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmActionBtn').textContent = buttonLabel;
    pendingConfirmAction = action;
    openModal('confirmModal');
}

document.getElementById('confirmActionBtn').addEventListener('click', async () => {
    if (!pendingConfirmAction) return;
    await pendingConfirmAction();
});

async function postAction(payload) {
    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => formData.append(key, value ?? ''));
    const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
    return response.json();
}

document.getElementById('inquiryFormModal').addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    formData.append('action', document.getElementById('form_id').value ? 'update' : 'create');
    const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
    const data = await response.json();
    if (!data.success) {
        showToast(data.message || 'Unable to save inquiry.', true);
        return;
    }
    showToast(data.message || 'Inquiry saved successfully.');
    window.location.reload();
});

document.getElementById('statusForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = await postAction({
        action: 'status',
        id: document.getElementById('status_id').value,
        status: document.getElementById('status_value').value
    });
    if (!data.success) return showToast(data.message || 'Unable to update status.', true);
    showToast(data.message || 'Status updated.');
    window.location.reload();
});

document.getElementById('assignForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = await postAction({
        action: 'assign',
        id: document.getElementById('assign_id').value,
        assigned_staff_id: document.getElementById('assigned_staff_id').value
    });
    if (!data.success) return showToast(data.message || 'Unable to save assignment.', true);
    showToast(data.message || 'Assignment updated.');
    window.location.reload();
});

document.getElementById('notesForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = await postAction({
        action: 'notes',
        id: document.getElementById('notes_id').value,
        notes: document.getElementById('notes_value').value
    });
    if (!data.success) return showToast(data.message || 'Unable to save notes.', true);
    showToast(data.message || 'Notes saved.');
    window.location.reload();
});

document.getElementById('convertForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = await postAction({
        action: 'convert_to_appointment',
        id: document.getElementById('convert_id').value,
        appointment_date: document.getElementById('appointment_date').value,
        appointment_time: document.getElementById('appointment_time').value
    });
    if (!data.success) return showToast(data.message || 'Unable to convert inquiry.', true);
    showToast(data.message || 'Inquiry converted to appointment.');
    window.location.reload();
});

function confirmArchiveInquiry() {
    if (!activeInquiry) return;
    openConfirmModal(
        'Archive Inquiry',
        `Archive inquiry for ${activeInquiry.full_name || 'this patient'}? This will hide it from the active table but keep it in the database.`,
        archiveInquiry,
        'Archive'
    );
}

async function archiveInquiry() {
    if (!activeInquiry) return;
    const data = await postAction({ action: 'archive', id: activeInquiry.id });
    if (!data.success) return showToast(data.message || 'Unable to archive inquiry.', true);
    closeModal('confirmModal');
    showToast(data.message || 'Inquiry archived.');
    window.location.reload();
}

function confirmDeleteInquiry() {
    if (!activeInquiry) return;
    openConfirmModal(
        'Delete Inquiry',
        `Permanently delete inquiry for ${activeInquiry.full_name || 'this patient'}? This action cannot be undone.`,
        deleteInquiry,
        'Delete'
    );
}

async function deleteInquiry() {
    if (!activeInquiry) return;
    const data = await postAction({ action: 'delete', id: activeInquiry.id });
    if (!data.success) return showToast(data.message || 'Unable to delete inquiry.', true);
    closeModal('confirmModal');
    showToast(data.message || 'Inquiry deleted permanently.');
    window.location.reload();
}
</script>

<?php require_once '../../includes/staff_layout_end.php'; ?>
