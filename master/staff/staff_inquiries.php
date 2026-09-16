<?php
$pageTitle = 'Inquiry / Consultation';

require_once '../../config/database.php';
require_once '../../includes/inquiry_module_helper.php';

ensureInquiryModuleSchema($pdo);
ensureInquiryReplySchema($pdo);

$avatarJoin = inquiryPatientAvatarJoinSql($pdo);
$query = "
    SELECT i.*,
           s.name AS service_name,
           ass.first_name AS assigned_first_name,
           ass.last_name AS assigned_last_name,
           ass.username AS assigned_username,
           up.profile_picture AS patient_profile_picture,
           lr.message AS latest_reply_message,
           lr.sender_type AS latest_reply_sender_type,
           lr.attachment AS latest_reply_attachment
    FROM inquiries i
    LEFT JOIN services s ON s.id = i.service_id
    LEFT JOIN users ass ON ass.id = i.assigned_staff_id
    {$avatarJoin}
    LEFT JOIN (
        SELECT r1.inquiry_id, r1.message, r1.sender_type, r1.attachment, r1.created_at
        FROM inquiry_replies r1
        INNER JOIN (
            SELECT inquiry_id, MAX(id) AS max_id
            FROM inquiry_replies
            GROUP BY inquiry_id
        ) r2 ON r1.id = r2.max_id
    ) lr ON lr.inquiry_id = i.id
    WHERE COALESCE(i.is_archived, 0) = 0
    ORDER BY COALESCE(lr.created_at, i.created_at) DESC, i.id DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($inquiries as &$inq) {
    if (!empty($inq['patient_profile_picture']) && !file_exists(dirname(__DIR__, 2) . '/' . $inq['patient_profile_picture'])) {
        $inq['patient_profile_picture'] = null;
    }
}
unset($inq);

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        if (!empty($row['patient_profile_picture']) && !file_exists(dirname(__DIR__, 2) . '/' . $row['patient_profile_picture'])) {
            $row['patient_profile_picture'] = null;
        }
    }
    echo json_encode($rows);
    exit;
}

$services = fetchInquiryServices($pdo);
$statuses = ['Unread', 'Read', 'Replied'];

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
.form-group label {
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
    padding: 16px 16px 4px;
}
.data-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}
.data-table th {
    padding: 14px 14px;
    text-align: left;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    border-bottom: 1px solid #e5e7eb;
}
.data-table td {
    padding: 16px 14px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: top;
    color: #0f172a;
    font-size: 14px;
}
.patient-name {
    font-size: 14px;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.msg-cell {
    max-width: 220px;
    word-break: break-word;
}
.muted-text {
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
    font-weight: 800;
    white-space: nowrap;
}
.status-Unread { background: #fef3c7; color: #92400e; }
.status-Read { background: #f0fdf4; color: #16a34a; }
.status-Replied { background: #dbeafe; color: #1d4ed8; }
.actions-cell {
    width: 1px;
    white-space: nowrap;
    text-align: center;
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
    border-radius: 14px;
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
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.58);
    align-items: center;
    justify-content: center;
    z-index: 99999;
    padding: 20px;
}
.modal-overlay.active {
    display: flex;
}
.chat-modal {
    width: min(500px, 100%);
    height: min(680px, 92vh);
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 30px 60px rgba(15, 23, 42, 0.22);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.chat-modal-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #fafbff;
}
.chat-modal-back {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    background: #eef2ff;
    color: #1d4ed8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.15s;
}
.chat-modal-back:hover { background: #dbeafe; }
.chat-modal-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
}
.chat-modal-user-info {
    flex: 1;
    min-width: 0;
}
.chat-modal-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 15px;
}
.chat-modal-email {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}
.chat-modal-source {
    font-size: 11px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    flex-shrink: 0;
}
.chat-modal-messages {
    flex: 1;
    overflow-y: auto;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #fcfdff;
}
.chat-bubble {
    max-width: 88%;
    padding: 12px 16px;
    border-radius: 18px;
    line-height: 1.55;
    font-size: 14px;
    word-break: break-word;
}
.chat-bubble.received {
    align-self: flex-start;
    background: #f0f2f5;
    color: #111827;
    border-bottom-left-radius: 6px;
}
.chat-bubble.sent {
    align-self: flex-end;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    border-bottom-right-radius: 6px;
}
.chat-bubble .chat-meta {
    font-size: 11px;
    opacity: 0.65;
    margin-top: 6px;
}
.chat-bubble.sent .chat-meta {
    color: rgba(255,255,255,0.8);
}
.chat-bubble.system {
    align-self: center;
    background: transparent;
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 12px;
    max-width: 100%;
}
.chat-channel-info {
    padding: 10px 20px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    border-top: 1px solid #e5e7eb;
    background: #fafbff;
    color: #475569;
}
.chat-compose {
    padding: 14px 20px 18px;
    border-top: 1px solid #e5e7eb;
    background: #fff;
}
.reply-quote-bar {
    display: none;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 10px;
    padding: 10px 12px;
    background: #f3f4f6;
    border-radius: 10px;
    border-left: 4px solid #2563eb;
}
.reply-quote-bar.visible {
    display: flex;
}
.reply-quote-bar-body {
    flex: 1;
    min-width: 0;
}
.reply-quote-bar-label {
    font-size: 11px;
    font-weight: 700;
    color: #2563eb;
    margin-bottom: 4px;
}
.reply-quote-bar-text {
    font-size: 13px;
    color: #374151;
    line-height: 1.4;
    white-space: pre-wrap;
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.reply-quote-bar-close {
    border: none;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 0 2px;
}
.inq-quote {
    font-size: 12px;
    line-height: 1.4;
    padding: 8px 10px;
    margin-bottom: 8px;
    border-radius: 8px;
    border-left: 3px solid rgba(255, 255, 255, 0.75);
    background: rgba(255, 255, 255, 0.16);
    color: rgba(255, 255, 255, 0.95);
}
.chat-bubble.received .inq-quote {
    background: #e5e7eb;
    border-left-color: #2563eb;
    color: #374151;
}
.chat-send-row {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.chat-send-row textarea {
    flex: 1;
    border: 1px solid #d1d5db;
    border-radius: 20px;
    padding: 12px 16px;
    font: inherit;
    font-size: 14px;
    resize: none;
    min-height: 48px;
    max-height: 120px;
    outline: none;
    transition: border-color 0.2s;
    background: #f9fafb;
}
.chat-send-row textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    background: #fff;
}
.chat-send-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.15s, box-shadow 0.15s;
    box-shadow: 0 4px 12px rgba(37,99,235,0.25);
}
.chat-send-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(37,99,235,0.35);
}
.chat-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}
.chat-attach-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.2s, color 0.2s;
}
.chat-attach-btn:hover {
    background: #e2e8f0;
    color: #2563eb;
}
.chat-attachment-preview {
    padding: 8px 0 4px;
}
.chat-attachment-preview-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: #f0f4ff;
    border: 1px solid #c7d2fe;
    border-radius: 8px;
    font-size: 13px;
    color: #3730a3;
    max-width: 250px;
}
.chat-attachment-preview-item img {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 6px;
}
.chat-attachment-preview-item .att-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}
.chat-attachment-preview-item .att-remove {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 16px;
    padding: 0 2px;
    line-height: 1;
}
.chat-bubble .chat-att-img {
    max-width: 260px;
    max-height: 260px;
    border-radius: 8px;
    margin-top: 6px;
    cursor: pointer;
    display: block;
}
.chat-bubble .chat-att-file {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 8px;
    margin-top: 6px;
    color: inherit;
    text-decoration: none;
    font-size: 13px;
}
.chat-bubble.received .chat-att-file {
    background: rgba(0,0,0,0.05);
    border-color: rgba(0,0,0,0.1);
    color: #3730a3;
}
.form-textarea {
    min-height: 120px;
    resize: vertical;
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
/* Pagination */
.pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.pagination-info {
    color: #6b7280;
    font-size: 0.875rem;
}
.pagination-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    text-decoration: none;
    color: #4a5568;
    font-size: 14px;
    transition: all 0.2s ease;
    min-width: 32px;
    cursor: pointer;
}
.pagination-btn:hover:not(.active):not(:disabled) {
    background-color: #f7fafc;
    border-color: #cbd5e0;
}
.pagination-btn.active {
    background-color: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}
.pagination-btn:disabled {
    color: #a0aec0;
    background-color: #fff;
    cursor: not-allowed;
    border-color: #edf2f7;
}
.pagination-ellipsis {
    color: #a0aec0;
    padding: 0 4px;
}

@media (max-width: 1180px) {
    .filter-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
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
    .custom-range-row {
        grid-template-columns: 1fr;
    }
    .results-bar {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="page-shell">
    

    <section class="page-card filters-card">
        <div class="filter-row">
            <div class="filter-group">
                <label for="statusFilter">Status Filter</label>
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
                <label for="sourceFilter">Source Filter</label>
                <select id="sourceFilter" class="filter-select">
                    <option value="All">All Sources</option>
                    <!-- <option value="Fb messenger">FB Messenger</option> -->
                    <option value="Website">Clinic Website</option>
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
            <p>Review requests and respond to consultation needs.</p>
        </div>
        <div class="results-bar">
            <div id="resultsSummary">Showing 0 inquiries</div>
            <button type="button" class="btn-ghost" onclick="resetFilters()">Reset Filters</button>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="padding-left: 40px;">First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Message</th>
                        <th>Status</th>
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
    <div class="pagination" id="pagination"></div>
</div>

<div class="modal-overlay" id="replyModal">
    <div class="chat-modal">
        <div class="chat-modal-header">
            <button type="button" class="chat-modal-back" onclick="closeModal('replyModal')" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>
                </svg>
            </button>
            <div class="chat-modal-avatar" id="replyAvatar" style="overflow:hidden;">JD</div>
            <div class="chat-modal-user-info">
                <div class="chat-modal-name" id="replyFromName"></div>
                <div class="chat-modal-email" id="replyEmail"></div>
                <div class="chat-modal-email" id="replyPhone" style="color:#64748b;font-size:11px;margin-top:1px;"></div>
            </div>
            <div class="chat-modal-source" id="replySource"></div>
        </div>
        <div class="chat-modal-messages" id="chatMessages">
            <div class="chat-bubble received" id="originalBubble">
                <div id="replyConcern"></div>
                <div class="chat-meta">Original concern</div>
            </div>
        </div>
        <div class="chat-channel-info" id="replyChannelInfo"></div>
        <form id="replyForm" class="chat-compose">
            <input type="hidden" id="reply_inquiry_id">
            <div id="replyQuoteBar" class="reply-quote-bar" hidden>
                <div class="reply-quote-bar-body">
                    <div class="reply-quote-bar-label">Replying to appointment notes</div>
                    <div class="reply-quote-bar-text" id="replyQuoteText"></div>
                </div>
                <button type="button" class="reply-quote-bar-close" id="replyQuoteClose" aria-label="Dismiss quote">&times;</button>
            </div>
            <div id="replyAttachmentPreview" class="chat-attachment-preview" style="display:none;"></div>
            <div class="chat-send-row">
                <button type="button" class="chat-attach-btn" id="replyAttachFile" title="Attach file"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg></button>
                <button type="button" class="chat-attach-btn" id="replyAttachImage" title="Attach image"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></button>
                <input type="file" id="replyFileInput" style="display:none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,image/*">
                <textarea id="reply_message" name="message" placeholder="Type your reply..." rows="1"></textarea>
                <button type="submit" class="chat-send-btn" aria-label="Send">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toast"></div>

<script src="../../assets/js/inquiry_quote.js"></script>
<script>
let inquiries = <?php echo json_encode($inquiries); ?>;
let inquiryMap = Object.fromEntries(inquiries.map((item) => [String(item.id), item]));
let filteredInquiries = [...inquiries];
let activeInquiryId = null;
let currentPage = 1;
const itemsPerPage = 8;

const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const dateFilter = document.getElementById('dateFilter');
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

function openLightbox(src) {
    let overlay = document.getElementById('imgLightbox');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'imgLightbox';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:999999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
        overlay.innerHTML = '<img id="lightboxImg" style="max-width:90vw;max-height:90vh;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.5);">';
        overlay.addEventListener('click', () => overlay.style.display = 'none');
        document.body.appendChild(overlay);
    }
    document.getElementById('lightboxImg').src = src;
    overlay.style.display = 'flex';
}

function formatCreatedAt(value) {
    if (!value) return 'N/A';
    const date = new Date(value.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function getStatusClass(status) {
    return `status-${String(status || 'Unread').replace(/\s+/g, '-')}`;
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
    currentPage = 1;
    const query = searchInput.value.trim().toLowerCase();
    filteredInquiries = inquiries.filter((inquiry) => {
        const haystack = [
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
        const matchesStatus = statusFilter.value === 'All' || (inquiry.status || 'Unread') === statusFilter.value;
        const matchesSource = sourceFilter.value === 'All' || (inquiry.source || 'Website') === sourceFilter.value;

        return matchesSearch && matchesStatus && matchesSource && isWithinDateFilter(inquiry);
    });

    renderTable();
}

function renderTable() {
    const total = filteredInquiries.length;
    const totalPages = Math.max(1, Math.ceil(total / itemsPerPage));
    if (currentPage > totalPages) currentPage = totalPages;
    const start = (currentPage - 1) * itemsPerPage;
    const end = Math.min(start + itemsPerPage, total);
    const pageItems = filteredInquiries.slice(start, end);

    if (!total) {
        tableBody.innerHTML = '';
        emptyTableState.style.display = 'block';
        resultsSummary.textContent = 'Showing 0 inquiries';
        document.getElementById('pagination').innerHTML = '';
        return;
    }

    emptyTableState.style.display = 'none';
    resultsSummary.textContent = `${total} ${total === 1 ? 'inquiry' : 'inquiries'} found`;

    tableBody.innerHTML = pageItems.map((inquiry) => {
        const lastReply = inquiry.latest_reply_message || '';
        const lastAttachment = inquiry.latest_reply_attachment || '';
        const lastSender = inquiry.latest_reply_sender_type || '';
        let previewSource = lastReply ? (lastSender === 'staff' ? 'you: ' + lastReply : lastReply) : (inquiry.concern || inquiry.inquiry_message || 'No concern provided');
        if (lastAttachment) {
            const hasImage = /\.(jpe?g|png|gif|webp)$/i.test(lastAttachment);
            const suffix = hasImage ? '[Image]' : '[Attachment]';
            if (!lastReply) {
                previewSource = lastSender === 'staff' ? 'you: ' + suffix : suffix;
            } else {
                previewSource += ' ' + suffix;
            }
        } else if (!lastReply && !inquiry.concern && !inquiry.inquiry_message) {
            previewSource = 'No concern provided';
        }
        const preview = previewSource.length > 92 ? `${previewSource.slice(0, 92)}...` : previewSource;
        const displayFirstName = escapeHtml(inquiry.first_name || '');
        const displayMiddleName = inquiry.middle_name ? escapeHtml(inquiry.middle_name) : null;
        const displayLastName = escapeHtml(inquiry.last_name || '');
        const hasName = displayFirstName || displayMiddleName || displayLastName;
        const status = inquiry.status || 'Unread';
        return `
            <tr>
                <td>
                    <div style="display:flex;
                    align-items:flex-start;
                    gap:12px">
                        ${inquiry.patient_profile_picture ? `
                        <img src="../../${escapeHtml(inquiry.patient_profile_picture)}" alt=""
                            style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                        ` : `
                        <div 
                            style="width:40px;
                            height:40px;
                            border-radius:50%;
                            background:linear-gradient(135deg,#2563eb,#0d92f7);
                            color:#fff;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            font-weight:700;
                            font-size:14px;
                            flex-shrink:0">${escapeHtml(getInitials((inquiry.first_name||'')+' '+(inquiry.last_name||'')))}</div>
                        `}
                        <div class="patient-name">${displayFirstName || '-'}${inquiry.fb_sender_id ? ' <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;background:#e0f2fe;color:#0369a1;padding:2px 7px;border-radius:999px;vertical-align:middle;" title="Facebook Messenger"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.04 2 11c0 2.83 1.39 5.35 3.56 7.02V22l3.33-1.83c.89.25 1.84.38 2.83.38h.28c5.52 0 10-4.04 10-9S17.52 2 12 2zm1.07 12.14l-2.54-2.72L6.5 14.14l5.08-5.4 2.59 2.72 4.97-2.72-5.07 5.4z"/></svg>FB</span>' : ''}</div>
                    </div>
                </td>
                <td><div class="patient-name">${displayMiddleName || '-'}</div></td>
                <td><div class="patient-name">${displayLastName || '-'}</div></td>
                <td class="msg-cell">
                    <div class="muted-text">${escapeHtml(preview)}</div>
                </td>
                <td><span class="status-badge ${escapeHtml(getStatusClass(status))}">${escapeHtml(status)}</span></td>
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
    renderPagination();
}

function renderPagination() {
    const total = filteredInquiries.length;
    const totalPages = Math.max(1, Math.ceil(total / itemsPerPage));
    const container = document.getElementById('pagination');

    if (total <= itemsPerPage) {
        container.innerHTML = '';
        return;
    }

    const start = (currentPage - 1) * itemsPerPage;
    const end = Math.min(start + itemsPerPage, total);

    const pages = [];
    const range = 2;

    const addPage = (p) => {
        if (p >= 1 && p <= totalPages) {
            const active = p === currentPage ? ' active' : '';
            pages.push(`<button type="button" class="pagination-btn${active}" onclick="goToPage(${p})">${p}</button>`);
        }
    };

    const addEllipsis = () => {
        if (pages[pages.length - 1] !== 'ellipsis') pages.push('ellipsis');
    };

    addPage(1);

    if (currentPage - range > 2) addEllipsis();

    for (let i = Math.max(2, currentPage - range); i <= Math.min(totalPages - 1, currentPage + range); i++) {
        addPage(i);
    }

    if (currentPage + range < totalPages - 1) addEllipsis();

    if (totalPages > 1) addPage(totalPages);

    const prevDisabled = currentPage <= 1 ? ' disabled' : '';
    const nextDisabled = currentPage >= totalPages ? ' disabled' : '';

    container.innerHTML =
        `<span class="pagination-info">Showing ${start + 1}–${end} of ${total} inquiries</span>` +
        `<div class="pagination-buttons">` +
            `<button type="button" class="pagination-btn" onclick="goToPage(currentPage - 1)"${prevDisabled}>Previous</button>` +
            pages.map(p => p === 'ellipsis' ? '<span class="pagination-ellipsis">...</span>' : p).join('') +
            `<button type="button" class="pagination-btn" onclick="goToPage(currentPage + 1)"${nextDisabled}>Next</button>` +
        `</div>`;
}

function goToPage(page) {
    const total = filteredInquiries.length;
    const totalPages = Math.max(1, Math.ceil(total / itemsPerPage));
    if (page < 1 || page > totalPages || page === currentPage) return;
    currentPage = page;
    renderTable();
}

function resetFilters() {
    searchInput.value = '';
    statusFilter.value = 'All';
    dateFilter.value = 'All';
    sourceFilter.value = 'All';
    dateFrom.value = '';
    dateTo.value = '';
    customRangeRow.classList.remove('active');
    currentPage = 1;
    applyFilters();
}

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.background = isError ? '#b91c1c' : '#0f172a';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2800);
}

function showReplyQuote(text) {
    const bar = document.getElementById('replyQuoteBar');
    const quote = document.getElementById('replyQuoteText');
    if (!bar || !quote) return;
    quote.textContent = text;
    bar.hidden = false;
    bar.classList.add('visible');
}

function hideReplyQuote() {
    const bar = document.getElementById('replyQuoteBar');
    const quote = document.getElementById('replyQuoteText');
    if (quote) quote.textContent = '';
    if (bar) {
        bar.hidden = true;
        bar.classList.remove('visible');
    }
}

document.getElementById('replyQuoteClose')?.addEventListener('click', hideReplyQuote);

let inquiryKebabDropdown = null;
let inquiryKebabBackdrop = null;
let inquiryActiveButton = null;

function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        document.body.appendChild(el);
        el.classList.add('active');
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
    if (id === 'replyModal') hideReplyQuote();
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
        <a href="javascript:void(0)" onclick="handleInquiryMenuClick(event, 'reply', ${inquiryId})">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            Reply
        </a>
        <a href="javascript:void(0)" onclick="handleInquiryMenuClick(event, 'archive', ${inquiryId})">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 4H3l2 3h14l2-3z"/>
                <path d="M5 7v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7"/>
                <path d="M10 10h4"/>
            </svg>
            Delete
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
}

function handleInquiryMenuClick(event, action, id) {
    event.preventDefault();
    event.stopPropagation();
    closeActionMenu();
    switch (action) {
        case 'reply':
            openReplyModal(id);
            break;
        case 'archive':
            archiveInquiry(id);
            break;
    }
}

async function archiveInquiry(id) {
    if (!confirm('Move this inquiry to archive?')) return;
    const data = await postAction({ action: 'archive', id });
    if (!data.success) {
        showToast(data.message || 'Unable to archive inquiry.', true);
        return;
    }
    showToast('Inquiry moved to archive.');
    refreshData();
}

document.addEventListener('click', (event) => {
    const modal = document.getElementById('replyModal');
    if (modal && modal.classList.contains('active') && event.target === modal) {
        closeModal('replyModal');
    }
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
        const modal = document.getElementById('replyModal');
        if (modal && modal.classList.contains('active')) {
            closeModal('replyModal');
        }
    }
});

async function postAction(payload) {
    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => formData.append(key, value ?? ''));
    const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
    return response.json();
}

async function refreshData() {
    window.location.reload();
}

function getInitials(name) {
    const parts = (name || 'P').trim().split(/\s+/);
    const first = parts[0] || '';
    const last = parts.length > 1 ? parts[parts.length - 1] : '';
    return ((first[0] || '') + (last[0] || '')).toUpperCase() || 'P';
}

function addChatBubble(text, type, timestamp, label, attachment) {
    const container = document.getElementById('chatMessages');
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble ' + type;
    const labelHtml = label ? '<div style="font-size:11px;font-weight:600;color:' + (type === 'received' ? '#64748b' : 'rgba(255,255,255,0.7)') + ';margin-bottom:3px;">' + escapeHtml(label) + '</div>' : '';
    let contentHtml = '';
    if (text) {
        contentHtml += (typeof inquiryQuotedMessageHtml === 'function')
            ? inquiryQuotedMessageHtml(text)
            : escapeHtml(text).replace(/\n/g, '<br>');
    }
        if (attachment) {
        const isImg = /\.(jpe?g|png|gif|webp)$/i.test(attachment);
        if (isImg) {
            contentHtml += '<img src="../' + escapeHtml(attachment) + '" class="chat-att-img" alt="Attachment" onclick="openLightbox(this.src)" style="cursor:pointer;">';
        } else {
            contentHtml += '<a href="../' + escapeHtml(attachment) + '" target="_blank" class="chat-att-file"><i class="fas fa-paperclip"></i> ' + escapeHtml(attachment.split('/').pop()) + '</a>';
        }
    }
    bubble.innerHTML = labelHtml + '<div>' + contentHtml + '</div><div class="chat-meta">' + escapeHtml(timestamp || 'just now') + '</div>';
    container.appendChild(bubble);
    container.scrollTop = container.scrollHeight;
}

function parseStaffReplies(notes) {
    if (!notes) return [];
    const replies = [];
    const lines = notes.split('\n');
    let current = null;
    for (const line of lines) {
        const staffMatch = line.match(/^\[Staff replied on (.+?)\]/);
        if (staffMatch) {
            if (current) replies.push(current);
            current = { timestamp: staffMatch[1], message: '', type: 'sent' };
        } else if (current) {
            current.message += (current.message ? '\n' : '') + line;
        }
    }
    if (current) replies.push(current);
    return replies;
}

async function openReplyModal(id) {
    const inquiry = inquiryMap[String(id)];
    if (!inquiry) {
        showToast('Unable to load inquiry record.', true);
        return;
    }

    activeInquiryId = id;
    document.getElementById('reply_inquiry_id').value = inquiry.id;
    const msgInput = document.getElementById('reply_message');
    msgInput.value = '';
    msgInput.style.height = 'auto';
    hideReplyQuote();

    const fullName = [inquiry.first_name, inquiry.middle_name, inquiry.last_name].filter(Boolean).join(' ') || 'Unknown Patient';
    document.getElementById('replyFromName').textContent = fullName;
    document.getElementById('replySource').textContent = inquiry.source || 'Website';
    document.getElementById('replyEmail').textContent = inquiry.email || 'No email';
    document.getElementById('replyPhone').textContent = inquiry.phone || inquiry.contact_info || 'No phone';
    const avatarEl = document.getElementById('replyAvatar');
    if (inquiry.patient_profile_picture) {
        avatarEl.innerHTML = '<img src="../../' + escapeHtml(inquiry.patient_profile_picture) + '" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">';
    } else {
        avatarEl.textContent = getInitials(fullName);
        avatarEl.style.background = 'linear-gradient(135deg,#2563eb,#1d4ed8)';
    }

    const concernText = inquiry.concern || inquiry.inquiry_message || 'No concern provided.';

    const messageContainer = document.getElementById('chatMessages');
    messageContainer.innerHTML = '';
    const originalBubble = document.createElement('div');
    originalBubble.className = 'chat-bubble received';
    originalBubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">' + escapeHtml(fullName) + '</div><div>' + escapeHtml(concernText).replace(/\n/g, '<br>') + '</div><div class="chat-meta">' + escapeHtml(formatCreatedAt(inquiry.created_at)) + '</div>';
    messageContainer.appendChild(originalBubble);

    try {
        const data = await postAction({ action: 'get_conversation', id });
        if (data.success && data.replies) {
            data.replies.forEach((reply) => {
                const bubble = document.createElement('div');
                if (reply.sender_type !== 'staff') {
                    bubble.className = 'chat-bubble received';
                } else {
                    bubble.className = 'chat-bubble sent';
                }
                const label = reply.sender_type !== 'staff' ? (fullName) : 'Staff';
                const msgText = reply.message
                    ? ((typeof inquiryQuotedMessageHtml === 'function') ? inquiryQuotedMessageHtml(reply.message) : escapeHtml(reply.message).replace(/\n/g, '<br>'))
                    : '';
                let attHtml = '';
                if (reply.attachment) {
                    const isImg = /\.(jpe?g|png|gif|webp)$/i.test(reply.attachment);
                    if (isImg) {
                        attHtml = '<img src="../' + escapeHtml(reply.attachment) + '" class="chat-att-img" alt="Attachment" onclick="openLightbox(this.src)" style="cursor:pointer;">';
                    } else {
                        attHtml = '<a href="../' + escapeHtml(reply.attachment) + '" target="_blank" class="chat-att-file"><i class="fas fa-paperclip"></i> ' + escapeHtml(reply.attachment.split('/').pop()) + '</a>';
                    }
                }
                bubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:' + (reply.sender_type !== 'staff' ? '#64748b' : 'rgba(255,255,255,0.7)') + ';margin-bottom:3px;">' + escapeHtml(label) + '</div><div>' + msgText + attHtml + '</div><div class="chat-meta">' + escapeHtml(formatCreatedAt(reply.created_at)) + '</div>';
                messageContainer.appendChild(bubble);
            });
        }
    } catch (e) {
        const replies = parseStaffReplies(inquiry.notes);
        replies.forEach((reply) => {
            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble ' + reply.type;
            const displayText = reply.message || '(no message)';
            bubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:rgba(255,255,255,0.7);margin-bottom:3px;">Staff</div><div>' + escapeHtml(displayText).replace(/\n/g, '<br>') + '</div><div class="chat-meta">' + escapeHtml(reply.timestamp) + '</div>';
            messageContainer.appendChild(bubble);
        });
    }

    messageContainer.scrollTop = messageContainer.scrollHeight;

    const channelInfo = document.getElementById('replyChannelInfo');
    const isPortal = (inquiry.source || '') === 'Patient Portal' && inquiry.patient_id;
    const isFBMessenger = (inquiry.source || '') === 'Fb messenger' && inquiry.fb_sender_id;
    if (isFBMessenger) {
        channelInfo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2"><path d="M12 2C6.48 2 2 6.04 2 11c0 2.83 1.39 5.35 3.56 7.02V22l3.33-1.83c.89.25 1.84.38 2.83.38h.28c5.52 0 10-4.04 10-9S17.52 2 12 2z"/></svg> Reply will be sent via Facebook Messenger';
    } else if (isPortal) {
        channelInfo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Reply will appear in patient\'s account messages';
    } else {
        channelInfo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M22 12v1a5 5 0 0 1-5 5h-1"/></svg> Reply will be sent via email with a conversation link to ' + escapeHtml(inquiry.email || 'the sender');
    }

    openModal('replyModal');

    if ((inquiry.status || 'Unread') === 'Unread') {
        inquiry.status = 'Read';
        postAction({ action: 'mark_read', id });
        applyFilters();
    }

    setTimeout(() => msgInput.focus(), 200);
}

document.getElementById('replyForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const id = document.getElementById('reply_inquiry_id').value;
    const typedMessage = document.getElementById('reply_message').value.trim();
    const quoteBar = document.getElementById('replyQuoteBar');
    const quoteText = document.getElementById('replyQuoteText');
    const quote = (quoteBar && quoteBar.classList.contains('visible') && quoteText)
        ? quoteText.textContent.trim()
        : '';
    const message = (typeof wrapInquiryQuotedMessage === 'function' && quote)
        ? wrapInquiryQuotedMessage(quote, typedMessage)
        : typedMessage;
    const replyFileInput = document.getElementById('replyFileInput');
    const selectedFile = replyFileInput.files[0] || null;
    if (!id || (!typedMessage && !selectedFile)) {
        showToast('Reply message or attachment is required.', true);
        return;
    }

    const btn = event.currentTarget.querySelector('button[type="submit"]');
    const textarea = document.getElementById('reply_message');
    btn.disabled = true;

    try {
        const inquiry = inquiryMap[String(id)];
        const isFBMessenger = inquiry && (inquiry.source || '') === 'Fb messenger' && inquiry.fb_sender_id;
        let data;
        if (isFBMessenger) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('message', message || '[Attachment]');
            const resp = await fetch('../api/fb_send_reply.php', { method: 'POST', body: formData });
            data = await resp.json();
        } else {
            const formData = new FormData();
            formData.append('action', 'staff_reply');
            formData.append('id', id);
            formData.append('message', message || '');
            if (selectedFile) {
                formData.append('attachment', selectedFile);
            }
            const resp = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
            data = await resp.json();
        }
        if (!data.success) {
            showToast(data.message || 'Unable to send reply.', true);
            btn.disabled = false;
            return;
        }
        const now = new Date();
        const ts = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0') + ' ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0');
        const attPath = data.attachment || null;
        addChatBubble(message, 'sent', ts, 'Staff', attPath);
        const entry = inquiryMap[String(id)];
        if (entry) {
            entry.notes = (entry.notes || '') + '\n[Staff replied on ' + ts + ']\n' + message;
        }
        textarea.value = '';
        textarea.style.height = 'auto';
        hideReplyQuote();
        replyFileInput.value = '';
        clearReplyAttachment();
        btn.disabled = false;
        textarea.focus();
        showToast('Reply sent.');
    } catch (error) {
        console.error('Reply error:', error);
        showToast('Something went wrong while sending the reply. ' + (error.message || ''), true);
        btn.disabled = false;
    }
});

const replyTextarea = document.getElementById('reply_message');
replyTextarea.addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
replyTextarea.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' || event.shiftKey) return;
    event.preventDefault();
    const form = document.getElementById('replyForm');
    if (form) form.requestSubmit();
});

document.getElementById('replyAttachFile').addEventListener('click', () => {
    const inp = document.getElementById('replyFileInput');
    inp.accept = '.pdf,.doc,.docx,.xls,.xlsx,.txt,image/*';
    inp.click();
});
document.getElementById('replyAttachImage').addEventListener('click', () => {
    const inp = document.getElementById('replyFileInput');
    inp.accept = 'image/*';
    inp.click();
});
document.getElementById('replyFileInput').addEventListener('change', function() { showReplyAttachmentPreview(this.files[0]); });

function showReplyAttachmentPreview(file) {
    if (!file) return;
    const preview = document.getElementById('replyAttachmentPreview');
    const isImage = file.type.startsWith('image/');
    preview.style.display = 'block';
    preview.innerHTML = '<div class="chat-attachment-preview-item">'
        + (isImage ? '<img src="' + URL.createObjectURL(file) + '" alt="Preview">' : '<i class="fas fa-file" style="font-size:20px;"></i>')
        + '<span class="att-name">' + escapeHtml(file.name) + '</span>'
        + '<button type="button" class="att-remove" onclick="clearReplyAttachment()">&times;</button>'
        + '</div>';
}

function clearReplyAttachment() {
    document.getElementById('replyFileInput').value = '';
    const preview = document.getElementById('replyAttachmentPreview');
    preview.style.display = 'none';
    preview.innerHTML = '';
}

[searchInput, statusFilter, sourceFilter, dateFrom, dateTo].forEach((element) => {
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

(function openInquiryFromAppointment() {
    const params = new URLSearchParams(window.location.search);
    const openId = params.get('open');
    if (!openId) return;
    const draft = sessionStorage.getItem('inquiryDraft') || '';
    try { sessionStorage.removeItem('inquiryDraft'); } catch (e) {}
    openReplyModal(openId).then(function() {
        if (draft) showReplyQuote(draft);
        const ta = document.getElementById('reply_message');
        if (ta) {
            ta.value = '';
            ta.focus();
        }
    }).catch(function() {
        showToast('Unable to open that conversation.', true);
    });
})();

async function pollInquiries() {
    try {
        const r = await fetch(window.location.pathname + '?ajax=1');
        const data = await r.json();
        if (!Array.isArray(data) || !data.length) return;
        const oldMap = inquiryMap;
        inquiries = data;
        inquiryMap = Object.fromEntries(inquiries.map((item) => [String(item.id), item]));
        let changed = false;
        for (const item of data) {
            const old = oldMap[String(item.id)];
            if (!old || old.status !== item.status || old.latest_reply_created_at !== item.latest_reply_created_at) {
                changed = true;
                break;
            }
        }
        if (changed) {
            applyFilters();
        }
    } catch (_e) {}
}
setInterval(pollInquiries, 8000);
</script>

<?php require_once '../../includes/staff_layout_end.php'; ?>
