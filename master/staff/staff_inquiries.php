<?php
$pageTitle = 'Inquiry / Consultation';

require_once '../../config/database.php';
require_once '../../includes/inquiry_module_helper.php';

ensureInquiryModuleSchema($pdo);

$query = "
    SELECT i.*,
           s.name AS service_name,
           ass.first_name AS assigned_first_name,
           ass.last_name AS assigned_last_name,
           ass.username AS assigned_username
    FROM inquiries i
    LEFT JOIN services s ON s.id = i.service_id
    LEFT JOIN users ass ON ass.id = i.assigned_staff_id
    WHERE COALESCE(i.is_archived, 0) = 0
    ORDER BY i.created_at DESC, i.id DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$services = fetchInquiryServices($pdo);
$staffMembers = fetchAssignableStaff($pdo);
$statuses = ['Pending', 'Contacted', 'Scheduled', 'Completed', 'Cancelled'];

require_once '../../includes/staff_layout_start.php';
?>
<style>
.page-shell {
    display: grid;
    gap: 22px;
}
.page-card,
.table-card,
.empty-state-card,
.modal-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.06);
}
.page-card {
    padding: 24px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18px;
}
.page-title-wrap h1 {
    margin: 0;
    font-size: 1.9rem;
    color: #0f172a;
    font-weight: 800;
}
.page-title-wrap p {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 1rem;
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    width: min(540px, 100%);
}
.search-wrap {
    position: relative;
    flex: 1;
}
.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
    z-index: 1;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.search-input,
.filter-select,
.filter-input,
.form-input,
.form-select,
.form-textarea {
    width: 100%;
    border: 1px solid #dbe2ea;
    border-radius: 16px;
    background: #fff;
    padding: 13px 16px;
    font: inherit;
    color: #0f172a;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}
.search-input {
    padding-left: 44px;
}
.search-input:focus,
.filter-select:focus,
.filter-input:focus,
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
}
.btn-primary,
.btn-secondary,
.btn-ghost,
.btn-danger {
    border: none;
    border-radius: 16px;
    padding: 12px 18px;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.16s ease, box-shadow 0.16s ease, opacity 0.16s ease;
}
.btn-primary:hover,
.btn-secondary:hover,
.btn-ghost:hover,
.btn-danger:hover {
    transform: translateY(-1px);
}
.btn-primary {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #fff;
    box-shadow: 0 16px 28px rgba(37, 99, 235, 0.22);
}
.btn-secondary {
    background: #eef4ff;
    color: #1d4ed8;
}
.btn-ghost {
    background: #f8fafc;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.btn-danger {
    background: #fee2e2;
    color: #b91c1c;
}
.filters-card {
    display: grid;
    gap: 14px;
}
.filter-row {
    display: grid;
    grid-template-columns: 1fr 220px 220px 220px;
    gap: 14px;
    align-items: end;
}
.custom-range-row {
    display: none;
    grid-template-columns: repeat(2, minmax(0, 220px));
    gap: 14px;
}
.custom-range-row.active {
    display: grid;
}
.filter-group label,
.form-group label,
.detail-field label {
    display: block;
    margin-bottom: 8px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.table-card {
    overflow: hidden;
}
.table-head {
    padding: 22px 24px 0;
}
.table-head h2 {
    margin: 0;
    font-size: 1.2rem;
    color: #0f172a;
}
.table-head p {
    margin: 6px 0 0;
    color: #64748b;
}
.table-wrap {
    overflow: auto;
    padding: 16px 0 4px;
}
.data-table {
    width: 100%;
    min-width: 1280px;
    border-collapse: collapse;
}
.data-table th {
    padding: 16px 24px;
    text-align: left;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    border-bottom: 1px solid #e5e7eb;
}
.data-table td {
    padding: 20px 24px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: top;
    color: #0f172a;
    font-size: 14px;
}
.patient-name {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
}
.muted-text {
    color: #64748b;
    line-height: 1.55;
}
.service-title {
    font-weight: 700;
}
.source-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 12px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}
.status-Pending { background: #fef3c7; color: #92400e; }
.status-Contacted { background: #dbeafe; color: #1d4ed8; }
.status-Scheduled { background: #dcfce7; color: #166534; }
.status-Completed { background: #e5e7eb; color: #475569; }
.status-Cancelled { background: #fee2e2; color: #b91c1c; }
.actions-cell {
    width: 84px;
    text-align: right;
}
.patient-kebab-btn {
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
.patient-kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}
.patient-kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}
.patient-kebab-dropdown-portal {
    position: fixed;
    min-width: 200px;
    max-width: 220px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    z-index: 99999;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateX(8px) scale(0.96);
    transform-origin: top right;
    transition: opacity 0.16s ease, transform 0.16s ease, visibility 0.16s ease;
}
.patient-kebab-dropdown-portal.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateX(0) scale(1);
}
.patient-kebab-dropdown-portal a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 16px;
    color: #374151;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.15s ease;
    cursor: pointer;
    white-space: nowrap;
}
.patient-kebab-dropdown-portal a:hover {
    background-color: #f9fafb;
    color: #111827;
}
.patient-kebab-dropdown-portal a svg {
    flex-shrink: 0;
}
.patient-kebab-dropdown-portal a.is-danger {
    color: #b91c1c;
}
.patient-kebab-dropdown-portal a:first-child {
    border-radius: 14px 14px 0 0;
}
.patient-kebab-dropdown-portal a:last-child {
    border-radius: 0 0 14px 14px;
}
.patient-kebab-backdrop {
    position: fixed;
    inset: 0;
    z-index: 99998;
    display: none;
}
.patient-kebab-backdrop.show {
    display: block;
}
.empty-state-card {
    padding: 56px 28px;
    text-align: center;
}
.empty-state-card h3 {
    margin: 0 0 10px;
    font-size: 1.45rem;
    color: #0f172a;
}
.empty-state-card p {
    margin: 0 0 22px;
    color: #64748b;
}
.results-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 0 24px 18px;
    color: #64748b;
    font-size: 14px;
}
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.58);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
}
.modal-overlay.active {
    display: flex;
}
.modal-card {
    width: min(1040px, 100%);
    max-height: 92vh;
    overflow: auto;
}
.modal-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 22px 24px;
    border-bottom: 1px solid #e5e7eb;
}
.modal-head h3 {
    margin: 0;
    font-size: 1.25rem;
    color: #0f172a;
}
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
.detail-layout {
    display: grid;
    grid-template-columns: 1.2fr 0.9fr;
    gap: 22px;
}
.detail-panel,
.control-panel {
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 20px;
    background: #fcfdff;
}
.detail-grid,
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}
.full-span {
    grid-column: 1 / -1;
}
.detail-field .value {
    color: #0f172a;
    line-height: 1.6;
    background: #fff;
    border: 1px solid #edf2f7;
    border-radius: 14px;
    padding: 13px 14px;
    min-height: 52px;
}
.form-textarea {
    min-height: 120px;
    resize: vertical;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 0 24px 24px;
}
.toast {
    position: fixed;
    right: 22px;
    bottom: 22px;
    min-width: 280px;
    max-width: 420px;
    padding: 14px 16px;
    border-radius: 16px;
    color: #fff;
    background: #0f172a;
    box-shadow: 0 24px 42px rgba(15, 23, 42, 0.18);
    z-index: 2100;
    display: none;
}
.toast.show {
    display: block;
}
.table-empty {
    text-align: center;
    padding: 54px 24px;
    color: #64748b;
}
@media (max-width: 1180px) {
    .filter-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .detail-layout {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 860px) {
    .page-header {
        flex-direction: column;
    }
    .header-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
    .filter-row,
    .custom-range-row,
    .form-grid,
    .detail-grid {
        grid-template-columns: 1fr;
    }
    .results-bar {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="page-shell">
    <section class="page-card">
        <div class="page-header">
            <div class="page-title-wrap">
                <h1>Inquiry / Consultation</h1>
                <p>Manage patient inquiries and requests</p>
            </div>
            <div class="header-actions">
                <div class="search-wrap">
                    <span class="search-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                    </span>
                    <input id="searchInput" type="text" class="search-input" placeholder="Search patient">
                </div>
            </div>
        </div>
    </section>

    <section class="page-card filters-card">
        <div class="filter-row">
            <div class="filter-group">
                <label for="statusFilter">Status Filter</label>
                <select id="statusFilter" class="filter-select">
                    <option value="All">All</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="dateFilter">Date Filter</label>
                <select id="dateFilter" class="filter-select">
                    <option value="All">All Dates</option>
                    <option value="Today">Today</option>
                    <option value="This Week">This Week</option>
                    <option value="Custom">Custom Range</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="serviceFilter">Service Filter</label>
                <select id="serviceFilter" class="filter-select">
                    <option value="All">All Services</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo htmlspecialchars($service['name']); ?>"><?php echo htmlspecialchars($service['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="sourceFilter">Source Filter</label>
                <select id="sourceFilter" class="filter-select">
                    <option value="All">All Sources</option>
                    <option value="Facebook">Facebook</option>
                    <option value="Walk-in">Walk-in</option>
                    <option value="Referral">Referral</option>
                    <option value="Website">Website</option>
                    <option value="Patient Portal">Patient Portal</option>
                </select>
            </div>
        </div>
        <div id="customRangeRow" class="custom-range-row">
            <div class="filter-group">
                <label for="dateFrom">Date From</label>
                <input id="dateFrom" type="date" class="filter-input">
            </div>
            <div class="filter-group">
                <label for="dateTo">Date To</label>
                <input id="dateTo" type="date" class="filter-input">
            </div>
        </div>
    </section>

    <section class="table-card">
        <div class="table-head">
            <h2>Patient Inquiry Records</h2>
            <p>Review requests, update statuses, assign staff, and respond quickly to consultation needs.</p>
        </div>
        <div class="results-bar">
            <div id="resultsSummary">Showing 0 inquiries</div>
            <button type="button" class="btn-ghost" onclick="resetFilters()">Reset Filters</button>
        </div>
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
                <tbody id="inquiryTableBody"></tbody>
            </table>
        </div>
        <div id="emptyTableState" class="table-empty" style="display:none;">
            <h3 style="margin:0 0 8px;color:#0f172a;">No inquiries yet</h3>
            <p style="margin:0 0 18px;">No inquiries matched your current search or filter settings.</p>
        </div>
    </section>
</div>

<div class="modal-overlay" id="formModal">
    <div class="modal-card">
        <div class="modal-head">
            <h3 id="formModalTitle">+ New Inquiry</h3>
            <button type="button" class="modal-close" onclick="closeModal('formModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="inquiryForm">
            <div class="modal-body">
                <input type="hidden" id="form_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="form_full_name">Full Name</label>
                        <input id="form_full_name" name="full_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="form_phone">Phone Number</label>
                        <input id="form_phone" name="phone" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="form_email">Email</label>
                        <input id="form_email" name="email" type="email" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="form_service_id">Service Needed</label>
                        <select id="form_service_id" name="service_id" class="form-select">
                            <option value="">Select service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo (int)$service['id']; ?>"><?php echo htmlspecialchars($service['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="form_preferred_date">Preferred Date</label>
                        <input id="form_preferred_date" name="preferred_date" type="date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="form_preferred_time">Preferred Time</label>
                        <input id="form_preferred_time" name="preferred_time" type="time" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="form_source">Source</label>
                        <select id="form_source" name="source" class="form-select">
                            <option value="Website">Website</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Referral">Referral</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="form_urgency">Urgency Level</label>
                        <select id="form_urgency" name="urgency" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="form_pain_level">Pain Level</label>
                        <select id="form_pain_level" name="pain_level" class="form-select">
                            <option value="">Select pain level</option>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="form_status">Status</label>
                        <select id="form_status" name="status" class="form-select">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $status === 'Pending' ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-span">
                        <label for="form_concern">Full Concern Description</label>
                        <textarea id="form_concern" name="concern" class="form-textarea" required></textarea>
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

<div class="modal-overlay" id="detailModal">
    <div class="modal-card">
        <div class="modal-head">
            <h3>Inquiry Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('detailModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="detail-layout">
                <div class="detail-panel">
                    <div class="detail-grid" id="detailFields"></div>
                </div>
                <div class="control-panel">
                    <form id="detailControlForm">
                        <input type="hidden" id="detail_id">
                        <div class="form-group">
                            <label for="detail_status">Change Status</label>
                            <select id="detail_status" class="form-select">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="detail_assigned_staff_id">Assign Staff</label>
                            <select id="detail_assigned_staff_id" class="form-select">
                                <option value="">Unassigned</option>
                                <?php foreach ($staffMembers as $member): ?>
                                    <option value="<?php echo (int)$member['id']; ?>">
                                        <?php echo htmlspecialchars(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?: ($member['username'] ?? 'Staff')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="detail_notes">Add Internal Notes</label>
                            <textarea id="detail_notes" class="form-textarea" placeholder="Add internal notes for follow-up, coordination, or scheduling."></textarea>
                        </div>
                        <div class="modal-footer" style="padding:0;margin-top:8px;">
                            <button type="submit" class="btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const inquiries = <?php echo json_encode($inquiries); ?>;
const services = <?php echo json_encode($services); ?>;
let inquiryMap = Object.fromEntries(inquiries.map((item) => [String(item.id), item]));
let filteredInquiries = [...inquiries];
let activeInquiryId = null;

const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const dateFilter = document.getElementById('dateFilter');
const serviceFilter = document.getElementById('serviceFilter');
const sourceFilter = document.getElementById('sourceFilter');
const dateFrom = document.getElementById('dateFrom');
const dateTo = document.getElementById('dateTo');
const customRangeRow = document.getElementById('customRangeRow');
const tableBody = document.getElementById('inquiryTableBody');
const emptyTableState = document.getElementById('emptyTableState');
const resultsSummary = document.getElementById('resultsSummary');

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[character]));
}

function nl2brSafe(value, fallback = 'N/A') {
    const text = value && String(value).trim() !== '' ? value : fallback;
    return escapeHtml(text).replace(/\n/g, '<br>');
}

function formatDateTime(dateValue, timeValue) {
    const parts = [];
    if (dateValue) {
        parts.push(new Date(`${dateValue}T00:00:00`).toLocaleDateString());
    }
    if (timeValue) {
        parts.push(new Date(`1970-01-01T${timeValue}`).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
    }
    return parts.length ? parts.join(' at ') : 'No preference';
}

function formatCreatedAt(value) {
    if (!value) return 'N/A';
    const date = new Date(value.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function getAssignedName(inquiry) {
    const full = [inquiry.assigned_first_name, inquiry.assigned_last_name].filter(Boolean).join(' ').trim();
    return full || inquiry.assigned_username || 'Unassigned';
}

function isWithinDateFilter(inquiry) {
    if (dateFilter.value === 'All') return true;
    const createdAt = new Date(String(inquiry.created_at).replace(' ', 'T'));
    if (Number.isNaN(createdAt.getTime())) return false;

    const now = new Date();
    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const todayEnd = new Date(todayStart);
    todayEnd.setDate(todayEnd.getDate() + 1);

    if (dateFilter.value === 'Today') {
        return createdAt >= todayStart && createdAt < todayEnd;
    }

    if (dateFilter.value === 'This Week') {
        const start = new Date(todayStart);
        const day = start.getDay();
        const diff = day === 0 ? -6 : 1 - day;
        start.setDate(start.getDate() + diff);
        const end = new Date(start);
        end.setDate(end.getDate() + 7);
        return createdAt >= start && createdAt < end;
    }

    if (dateFilter.value === 'Custom') {
        if (!dateFrom.value && !dateTo.value) return true;
        const recordDate = createdAt.toISOString().slice(0, 10);
        if (dateFrom.value && recordDate < dateFrom.value) return false;
        if (dateTo.value && recordDate > dateTo.value) return false;
        return true;
    }

    return true;
}

function applyFilters() {
    const query = searchInput.value.trim().toLowerCase();
    filteredInquiries = inquiries.filter((inquiry) => {
        const haystack = [
            inquiry.full_name,
            inquiry.first_name,
            inquiry.last_name,
            inquiry.phone,
            inquiry.contact_info,
            inquiry.email,
            inquiry.concern,
            inquiry.inquiry_message,
            inquiry.service_name,
            inquiry.source,
            inquiry.status,
            getAssignedName(inquiry)
        ].join(' ').toLowerCase();

        const matchesSearch = query === '' || haystack.includes(query);
        const matchesStatus = statusFilter.value === 'All' || (inquiry.status || 'Pending') === statusFilter.value;
        const matchesService = serviceFilter.value === 'All' || (inquiry.service_name || 'General Consultation') === serviceFilter.value;
        const matchesSource = sourceFilter.value === 'All' || (inquiry.source || 'Website') === sourceFilter.value;

        return matchesSearch && matchesStatus && matchesService && matchesSource && isWithinDateFilter(inquiry);
    });

    renderTable();
}

function renderTable() {
    if (!filteredInquiries.length) {
        tableBody.innerHTML = '';
        emptyTableState.style.display = 'block';
        resultsSummary.textContent = 'Showing 0 inquiries';
        return;
    }

    emptyTableState.style.display = 'none';
    resultsSummary.textContent = `Showing ${filteredInquiries.length} ${filteredInquiries.length === 1 ? 'inquiry' : 'inquiries'}`;

    tableBody.innerHTML = filteredInquiries.map((inquiry) => {
        const concern = inquiry.concern || inquiry.inquiry_message || 'No concern provided';
        const preview = concern.length > 92 ? `${concern.slice(0, 92)}...` : concern;
        const fullName = inquiry.full_name || [inquiry.first_name, inquiry.middle_name, inquiry.last_name].filter(Boolean).join(' ') || 'Unknown Patient';
        const source = inquiry.source || 'Website';
        const status = inquiry.status || 'Pending';
        return `
            <tr>
                <td>
                    <div class="patient-name">${escapeHtml(fullName)}</div>
                </td>
                <td>
                    <div>${escapeHtml(inquiry.phone || inquiry.contact_info || 'No phone')}</div>
                    <div class="muted-text">${escapeHtml(inquiry.email || 'No email')}</div>
                </td>
                <td>
                    <div class="service-title">${escapeHtml(inquiry.service_name || 'General Consultation')}</div>
                    <div class="muted-text">${escapeHtml(preview)}</div>
                </td>
                <td>${escapeHtml(formatDateTime(inquiry.preferred_date, inquiry.preferred_time))}</td>
                <td><span class="source-chip">${escapeHtml(source)}</span></td>
                <td><span class="status-badge status-${escapeHtml(status)}">${escapeHtml(status)}</span></td>
                <td>${escapeHtml(getAssignedName(inquiry))}</td>
                <td>${escapeHtml(formatCreatedAt(inquiry.created_at))}</td>
                <td class="actions-cell">
                    <button
                        type="button"
                        class="patient-kebab-btn"
                        aria-label="Open actions"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        data-inquiry-id="${Number(inquiry.id)}"
                        onclick="openActionMenu(event)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="6" r="2"/>
                            <circle cx="12" cy="12" r="2"/>
                            <circle cx="12" cy="18" r="2"/>
                        </svg>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function resetFilters() {
    searchInput.value = '';
    statusFilter.value = 'All';
    dateFilter.value = 'All';
    serviceFilter.value = 'All';
    sourceFilter.value = 'All';
    dateFrom.value = '';
    dateTo.value = '';
    customRangeRow.classList.remove('active');
    applyFilters();
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.background = isError ? '#b91c1c' : '#0f172a';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2800);
}

let inquiryKebabDropdown = null;
let inquiryKebabBackdrop = null;
let inquiryActiveButton = null;

function openModal(id) {
    document.getElementById(id).classList.add('active');
    closeActionMenu();
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function closeActionMenu() {
    if (inquiryKebabDropdown) {
        inquiryKebabDropdown.classList.remove('show');
        inquiryKebabDropdown.innerHTML = '';
    }
    if (inquiryKebabBackdrop) {
        inquiryKebabBackdrop.classList.remove('show');
    }
    if (inquiryActiveButton) {
        inquiryActiveButton.setAttribute('aria-expanded', 'false');
        inquiryActiveButton.classList.remove('active');
        inquiryActiveButton = null;
    }
}

function createInquiryKebabDropdown() {
    inquiryKebabDropdown = document.createElement('div');
    inquiryKebabDropdown.className = 'patient-kebab-dropdown-portal';
    inquiryKebabDropdown.id = 'inquiryKebabDropdownPortal';
    document.body.appendChild(inquiryKebabDropdown);

    inquiryKebabBackdrop = document.createElement('div');
    inquiryKebabBackdrop.className = 'patient-kebab-backdrop';
    inquiryKebabBackdrop.id = 'inquiryKebabBackdrop';
    document.body.appendChild(inquiryKebabBackdrop);

    inquiryKebabBackdrop.addEventListener('click', closeActionMenu);
}

function getInquiryMenuItems(inquiryId) {
    return `
        <a href="javascript:void(0)" data-action="view" data-id="${inquiryId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        <a href="javascript:void(0)" data-action="edit" data-id="${inquiryId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 20h9"/>
                <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z"/>
            </svg>
            Edit
        </a>
        <a href="javascript:void(0)" data-action="schedule" data-id="${inquiryId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M8 2v4"/>
                <path d="M16 2v4"/>
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <path d="M3 10h18"/>
                <path d="m9 16 2 2 4-4"/>
            </svg>
            Mark as Scheduled
        </a>
        <a href="javascript:void(0)" class="is-danger" data-action="cancel" data-id="${inquiryId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="m4.9 4.9 14.2 14.2"/>
            </svg>
            Cancel
        </a>
    `;
}

function positionInquiryKebabDropdown(button) {
    if (!inquiryKebabDropdown || !button) return;

    const rect = button.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const padding = 12;

    inquiryKebabDropdown.style.display = 'block';
    inquiryKebabDropdown.style.visibility = 'hidden';
    const dropdownRect = inquiryKebabDropdown.getBoundingClientRect();
    const dropdownWidth = dropdownRect.width || 200;
    const dropdownHeight = dropdownRect.height || 120;

    let left = rect.left - dropdownWidth - 8;
    let top = rect.top + (rect.height / 2) - (dropdownHeight / 2);

    if (left < padding) {
        left = rect.right + 8;
    }
    if (top + dropdownHeight > viewportHeight - padding) {
        top = viewportHeight - padding - dropdownHeight;
    }
    if (top < padding) {
        top = padding;
    }

    inquiryKebabDropdown.style.left = `${left}px`;
    inquiryKebabDropdown.style.top = `${top}px`;
    inquiryKebabDropdown.style.visibility = 'visible';
}

function openActionMenu(event) {
    event.preventDefault();
    event.stopPropagation();

    const button = event.currentTarget;
    const inquiryId = button.dataset.inquiryId;
    const inquiry = inquiryMap[String(inquiryId)];

    if (!inquiry) {
        showToast('Unable to load actions.', true);
        return;
    }

    if (!inquiryKebabDropdown) {
        createInquiryKebabDropdown();
    }

    if (inquiryActiveButton === button && inquiryKebabDropdown.classList.contains('show')) {
        closeActionMenu();
        return;
    }

    if (inquiryActiveButton) {
        inquiryActiveButton.classList.remove('active');
        inquiryActiveButton.setAttribute('aria-expanded', 'false');
    }

    inquiryKebabDropdown.innerHTML = getInquiryMenuItems(inquiryId);
    positionInquiryKebabDropdown(button);
    inquiryKebabDropdown.classList.add('show');
    inquiryKebabBackdrop.classList.add('show');
    inquiryActiveButton = button;
    button.classList.add('active');
    button.setAttribute('aria-expanded', 'true');
    inquiryKebabDropdown.onclick = handleInquiryKebabClick;
}

function handleInquiryKebabClick(event) {
    const link = event.target.closest('a[data-action]');
    if (!link) return;

    event.preventDefault();
    event.stopPropagation();

    const action = link.dataset.action;
    const id = parseInt(link.dataset.id, 10);

    closeActionMenu();

    switch (action) {
        case 'view':
            openDetailModal(id);
            break;
        case 'edit':
            openEditModal(id);
            break;
        case 'schedule':
            quickSchedule(id);
            break;
        case 'cancel':
            quickCancel(id);
            break;
    }
}

document.querySelectorAll('.modal-overlay').forEach((overlay) => {
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});

window.addEventListener('resize', function() {
    if (inquiryKebabDropdown && inquiryKebabDropdown.classList.contains('show') && inquiryActiveButton) {
        positionInquiryKebabDropdown(inquiryActiveButton);
    }
});
window.addEventListener('scroll', closeActionMenu, true);
document.addEventListener('click', function(event) {
    if (!event.target.closest('.patient-kebab-btn') && !event.target.closest('.patient-kebab-dropdown-portal')) {
        closeActionMenu();
    }
});
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeActionMenu();
    }
});

function openEditModal(id) {
    const inquiry = inquiryMap[String(id)];
    if (!inquiry) {
        showToast('Unable to load inquiry record.', true);
        return;
    }

    activeInquiryId = id;
    document.getElementById('formModalTitle').textContent = 'Edit Inquiry';
    document.getElementById('form_id').value = inquiry.id;
    document.getElementById('form_full_name').value = inquiry.full_name || '';
    document.getElementById('form_phone').value = inquiry.phone || inquiry.contact_info || '';
    document.getElementById('form_email').value = inquiry.email || '';
    document.getElementById('form_service_id').value = inquiry.service_id || '';
    document.getElementById('form_preferred_date').value = inquiry.preferred_date || '';
    document.getElementById('form_preferred_time').value = inquiry.preferred_time || '';
    document.getElementById('form_source').value = inquiry.source || 'Website';
    document.getElementById('form_urgency').value = inquiry.urgency || 'Medium';
    document.getElementById('form_pain_level').value = inquiry.pain_level || '';
    document.getElementById('form_status').value = inquiry.status || 'Pending';
    document.getElementById('form_concern').value = inquiry.concern || inquiry.inquiry_message || '';
    openModal('formModal');
}

function openDetailModal(id) {
    const inquiry = inquiryMap[String(id)];
    if (!inquiry) {
        showToast('Unable to load inquiry details.', true);
        return;
    }

    activeInquiryId = id;
    document.getElementById('detail_id').value = inquiry.id;
    document.getElementById('detail_status').value = inquiry.status || 'Pending';
    document.getElementById('detail_assigned_staff_id').value = inquiry.assigned_staff_id || '';
    document.getElementById('detail_notes').value = inquiry.notes || '';

    const fullName = inquiry.full_name || [inquiry.first_name, inquiry.middle_name, inquiry.last_name].filter(Boolean).join(' ') || 'Unknown Patient';
    document.getElementById('detailFields').innerHTML = `
        <div class="detail-field">
            <label>Patient Name</label>
            <div class="value">${escapeHtml(fullName)}</div>
        </div>
        <div class="detail-field">
            <label>Contact Info</label>
            <div class="value">${escapeHtml(inquiry.phone || inquiry.contact_info || 'No phone')}<br>${escapeHtml(inquiry.email || 'No email')}</div>
        </div>
        <div class="detail-field full-span">
            <label>Full Concern Description</label>
            <div class="value">${nl2brSafe(inquiry.concern || inquiry.inquiry_message, 'No concern provided')}</div>
        </div>
        <div class="detail-field">
            <label>Service Needed</label>
            <div class="value">${escapeHtml(inquiry.service_name || 'General Consultation')}</div>
        </div>
        <div class="detail-field">
            <label>Pain Level</label>
            <div class="value">${escapeHtml(inquiry.pain_level || 'Not specified')}</div>
        </div>
        <div class="detail-field">
            <label>Preferred Date & Time</label>
            <div class="value">${escapeHtml(formatDateTime(inquiry.preferred_date, inquiry.preferred_time))}</div>
        </div>
        <div class="detail-field">
            <label>Source</label>
            <div class="value">${escapeHtml(inquiry.source || 'Website')}</div>
        </div>
        <div class="detail-field full-span">
            <label>Notes</label>
            <div class="value">${nl2brSafe(inquiry.notes, 'No internal notes yet.')}</div>
        </div>
    `;

    openModal('detailModal');
}

async function postAction(payload) {
    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => formData.append(key, value ?? ''));
    const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
    return response.json();
}

async function refreshData() {
    window.location.reload();
}

async function quickSchedule(id) {
    const data = await postAction({ action: 'status', id, status: 'Scheduled' });
    if (!data.success) {
        showToast(data.message || 'Unable to mark inquiry as scheduled.', true);
        return;
    }
    showToast(data.message || 'Inquiry marked as scheduled.');
    refreshData();
}

async function quickCancel(id) {
    const data = await postAction({ action: 'status', id, status: 'Cancelled' });
    if (!data.success) {
        showToast(data.message || 'Unable to cancel inquiry.', true);
        return;
    }
    showToast(data.message || 'Inquiry cancelled.');
    refreshData();
}

document.getElementById('inquiryForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const formData = new FormData(form);
    formData.append('action', document.getElementById('form_id').value ? 'update' : 'create');

    try {
        const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!data.success) {
            showToast(data.message || 'Unable to save inquiry.', true);
            return;
        }
        showToast(data.message || 'Inquiry saved successfully.');
        closeModal('formModal');
        refreshData();
    } catch (error) {
        showToast('Something went wrong while saving the inquiry.', true);
    }
});

document.getElementById('detailControlForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const id = document.getElementById('detail_id').value;
    if (!id) {
        showToast('Invalid inquiry selected.', true);
        return;
    }

    const responses = await Promise.all([
        postAction({ action: 'status', id, status: document.getElementById('detail_status').value }),
        postAction({ action: 'assign', id, assigned_staff_id: document.getElementById('detail_assigned_staff_id').value }),
        postAction({ action: 'notes', id, notes: document.getElementById('detail_notes').value })
    ]);

    const failed = responses.find((item) => !item.success);
    if (failed) {
        showToast(failed.message || 'Unable to save inquiry details.', true);
        return;
    }

    showToast('Inquiry details updated successfully.');
    closeModal('detailModal');
    refreshData();
});

[searchInput, statusFilter, serviceFilter, sourceFilter, dateFrom, dateTo].forEach((element) => {
    element.addEventListener('input', applyFilters);
    element.addEventListener('change', applyFilters);
});

dateFilter.addEventListener('change', () => {
    customRangeRow.classList.toggle('active', dateFilter.value === 'Custom');
    if (dateFilter.value !== 'Custom') {
        dateFrom.value = '';
        dateTo.value = '';
    }
    applyFilters();
});

renderTable();
</script>

<?php require_once '../../includes/staff_layout_end.php'; ?>
