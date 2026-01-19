<?php
$pageTitle = 'Services Management';
require_once 'includes/admin_layout_start.php';

require_once 'config/database.php';

$message = '';
$messageType = '';

// Pagination settings
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 5;
$offset = ($currentPage - 1) * $itemsPerPage;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'add' || $action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $mode = $_POST['mode'] ?? 'SINGLE';
                $price = (float)($_POST['price'] ?? 0);
                $duration = (int)($_POST['duration_minutes'] ?? 30);
                $description = trim($_POST['description'] ?? '');
                $is_active = (int)($_POST['is_active'] ?? 1);
                
                if (empty($name)) {
                    throw new Exception('Service name is required');
                }
                
                if (!in_array($mode, ['BULK', 'SINGLE', 'NONE'])) {
                    throw new Exception('Invalid mode');
                }
                
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO services (name, mode, price, duration_minutes, description, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $mode, $price, $duration, $description, $is_active]);
                    $toastMessage = 'Service added successfully!';
                    $toastType = 'success';
                } else {
                    if ($id <= 0) {
                        throw new Exception('Invalid service ID');
                    }
                    $stmt = $pdo->prepare("UPDATE services SET name = ?, mode = ?, price = ?, duration_minutes = ?, description = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $mode, $price, $duration, $description, $is_active, $id]);
                    $toastMessage = 'Service updated successfully!';
                    $toastType = 'success';
                }
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Invalid service ID');
                }
                $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
                $stmt->execute([$id]);
                $toastMessage = 'Service deleted successfully!';
                $toastType = 'success';
            }
        }
    } catch (Exception $e) {
        $toastMessage = 'Error: ' . $e->getMessage();
        $toastType = 'error';
    }
}

// Get total services and pagination data
$stmtCount = $pdo->query("SELECT COUNT(*) FROM services");
$totalServices = $stmtCount->fetchColumn();
$totalPages = ceil($totalServices / $itemsPerPage);

// Get search/filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$modeFilter = isset($_GET['mode']) ? trim($_GET['mode']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build WHERE clause for filtering
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($modeFilter)) {
    $whereClauses[] = "mode = ?";
    $params[] = $modeFilter;
}

if (!empty($statusFilter) && $statusFilter !== '') {
    $whereClauses[] = "is_active = ?";
    $params[] = (int)$statusFilter;
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get services for current page
$sql = "SELECT * FROM services $whereSQL ORDER BY name ASC LIMIT $itemsPerPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all services for stats
$stmtAll = $pdo->query("SELECT * FROM services");
$allServices = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$totalBulk = count(array_filter($allServices, function($s) { return $s['mode'] === 'BULK'; }));
$totalSingle = count(array_filter($allServices, function($s) { return $s['mode'] === 'SINGLE'; }));
$totalInactive = count(array_filter($allServices, function($s) { return $s['is_active'] == 0; }));
?>

<style>
/* Full width for admin services page */
.content-sidebar {
    max-width: none !important;
    margin: 0 !important;
    width: 100% !important;
}

.content-main {
    max-width: none !important;
}

.content-area {
    padding-top: 10px;
}

.services-page {
    padding-left: 25px;
    padding-right: 25px;
    margin: 0px; 
    width: 100%;
    box-sizing: border-box;
    
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    width: 100%;
}

.page-header h2 { 
    font-size: 1.5rem; 
    font-weight: 600; 
    color: #111827; 
    margin: 0; 
}

.add-btn { 
    background: #2563eb; 
    color: white; 
    border: none; 
    padding: 10px 20px; 
    border-radius: 8px; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
}

.add-btn:hover { background: #1d4ed8; }

.services-table-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    width: 100%;
}

.services-table { 
    width: 100%; 
    border-collapse: collapse; 
}

.services-table th { 
    background: #f9fafb; 
    padding: 14px 16px; 
    text-align: left; 
    font-size: 0.75rem; 
    font-weight: 600; 
    color: #6b7280; 
    text-transform: uppercase; 
    border-bottom: 1px solid #e5e7eb; 
}

.services-table td { 
    padding: 16px; 
    border-bottom: 1px solid #f3f4f6; 
    font-size: 0.9rem; 
    color: #374151; 
}

.services-table tr:hover { background: #f9fafb; }

.service-name { 
    font-weight: 600; 
    color: #111827; 
}

.mode-badge { 
    padding: 4px 12px; 
    border-radius: 9999px; 
    font-size: 0.75rem; 
    font-weight: 600; 
    text-transform: uppercase; 
}

.mode-bulk { 
    background: #dbeafe; 
    color: #1d4ed8; 
}

.mode-single { 
    background: #d1fae5; 
    color: #065f46; 
}

.mode-none { 
    background: #f3f4f6; 
    color: #6b7280; 
}

.status-badge { 
    padding: 4px 12px; 
    border-radius: 9999px; 
    font-size: 0.75rem; 
    font-weight: 600; 
}

.status-active { 
    background: #d1fae5; 
    color: #065f46; 
}

.status-inactive { 
    background: #fee2e2; 
    color: #dc2626; 
}

.price { 
    font-weight: 600; 
    color: #059669; 
}

.actions { 
    display: flex; 
    gap: 8px; 
}

.action-btn { 
    padding: 6px 12px; 
    border: 1px solid #d1d5db; 
    border-radius: 6px; 
    background: white; 
    cursor: pointer; 
    font-size: 0.85rem; 
}

.action-btn:hover { 
    background: #f3f4f6; 
}

.action-btn.edit:hover { 
    background: #dbeafe; 
    border-color: #93c5fd; 
}

.action-btn.delete:hover { 
    background: #fee2e2; 
    border-color: #fca5a5; 
}

/* Toast notification */
.toast { 
    position: fixed; 
    bottom: 24px; 
    right: 24px; 
    background: #111827; 
    color: white; 
    padding: 14px 20px; 
    border-radius: 8px; 
    font-size: 0.9rem; 
    z-index: 999999; 
    opacity: 0; 
    transform: translateY(100px); 
    transition: all 0.3s ease; 
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.toast.show { 
    opacity: 1; 
    transform: translateY(0); 
}

.toast.success { 
    background: #059669; 
}

.toast.error { 
    background: #dc2626; 
}

/* Pagination */
.pagination {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 16px;
    margin-top: 24px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.pagination-info {
    font-size: 0.9rem;
    color: #6b7280;
    margin-bottom: 16px;
}

.pagination-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
}

.pagination a {
    display: inline-block;
    padding: 10px 16px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    text-decoration: none;
    color: #374151;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 40px;
    text-align: center;
}

.pagination a:hover:not(.disabled) {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.pagination a.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.pagination a.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination a.disabled:hover {
    background: white;
    border-color: #d1d5db;
}
</style>

<div class="services-page">
    <div class="page-header">
        <div>
            <h2>Services Management</h2>
            <p style="font-size: 0.875rem; color: #6b7280; margin: 4px 0 0 0;">
                Active services will appear in staff admission for patient selection
            </p>
        </div>
        <button class="add-btn" onclick="document.getElementById('addModal').style.display='flex'">
            <span>+</span> Add New Service
        </button>
    </div>

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 15px;">
        <div style="background: white; padding: 10px; border-radius: 12px; border: 1px solid #e5e7eb; text-align: center;">
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; margin-bottom: 8px;">Total Services</div>
            <div style="font-size: 2rem; font-weight: 700; color: #111827;"><?php echo $totalServices; ?></div>
        </div>
        <div style="background: white; padding: 10px; border-radius: 12px; border: 1px solid #e5e7eb; text-align: center;">
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; margin-bottom: 8px;">Total Bulk</div>
            <div style="font-size: 2rem; font-weight: 700; color: #059669;"><?php echo $totalBulk; ?></div>
        </div>
        <div style="background: white; padding: 10px; border-radius: 12px; border: 1px solid #e5e7eb; text-align: center;">
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; margin-bottom: 8px;">Total Single</div>
            <div style="font-size: 2rem; font-weight: 700; color: #2563eb;"><?php echo $totalSingle; ?></div>
        </div>
        <div style="background: white; padding: 10px; border-radius: 12px; border: 1px solid #e5e7eb; text-align: center;">
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; margin-bottom: 8px;">Total Inactive</div>
            <div style="font-size: 2rem; font-weight: 700; color: #dc2626;"><?php echo $totalInactive; ?></div>
        </div>
    </div>

    <form method="GET" style="margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search services by name, mode, or description..."
               style="flex: 1; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; outline: none; min-width: 200px;">
        <select name="mode" onchange="this.form.submit()" style="padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; outline: none;">
            <option value="">All Modes</option>
            <option value="BULK" <?php echo $modeFilter === 'BULK' ? 'selected' : ''; ?>>BULK</option>
            <option value="SINGLE" <?php echo $modeFilter === 'SINGLE' ? 'selected' : ''; ?>>SINGLE</option>
            <option value="NONE" <?php echo $modeFilter === 'NONE' ? 'selected' : ''; ?>>NONE</option>
        </select>
        <select name="status" onchange="this.form.submit()" style="padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; outline: none;">
            <option value="">All Status</option>
            <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Active</option>
            <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Inactive</option>
        </select>
    </form>

    <div class="services-table-container">
        <table class="services-table">
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Mode</th>
                    <th>Price</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($services)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
                            No services found. Add your first service!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                                <?php if ($service['description']): ?>
                                    <div style="font-size: 0.8rem; color: #6b7280; margin-top: 4px;">
                                        <?php echo htmlspecialchars($service['description']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="mode-badge mode-<?php echo strtolower($service['mode']); ?>">
                                    <?php echo htmlspecialchars($service['mode']); ?>
                                </span>
                            </td>
                            <td class="price">₱<?php echo number_format($service['price'], 2); ?></td>
                            <td><?php echo $service['duration_minutes'] ? $service['duration_minutes'] . ' min' : '-'; ?></td>
                            <td>
                                <span class="status-badge <?php echo $service['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <button type="button" class="action-btn edit" onclick="editService(<?php echo (int)$service['id']; ?>)">✏️ Edit</button>
                                    <button type="button" class="action-btn delete" onclick="deleteService(<?php echo (int)$service['id']; ?>)">🗑️ Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>


</div>

<!-- Add Modal -->
<div id="addModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 16px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #e5e7eb;">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #111827;">Add New Service</h3>
            <button onclick="document.getElementById('addModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
        </div>
        <form method="POST" action="" style="padding: 24px;">
            <input type="hidden" name="action" value="add">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Service Name *</label>
                <input type="text" name="name" id="addName" required placeholder="e.g., Tooth Extraction" style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Mode *</label>
                <select name="mode" id="addMode" required style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
                    <option value="BULK">BULK - Select entire arches</option>
                    <option value="SINGLE">SINGLE - Individual tooth selection</option>
                    <option value="NONE">NONE - No tooth selection needed</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Price (₱) *</label>
                <input type="number" name="price" id="addPrice" required min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Duration (minutes)</label>
                <input type="number" name="duration_minutes" id="addDuration" min="1" placeholder="30" style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Description</label>
                <textarea name="description" id="addDescription" placeholder="Brief description of service..." style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; min-height: 80px; resize: vertical;"></textarea>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Status</label>
                <select name="is_active" id="addIsActive" style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="background: #f3f4f6; border: none; color: #374151; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer;">Cancel</button>
                <button type="submit" style="background: #2563eb; border: none; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer;">Add Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 16px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #e5e7eb;">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #111827;">Edit Service</h3>
            <button onclick="document.getElementById('editModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
        </div>
        <form method="POST" action="" style="padding: 24px;">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Service Name *</label>
                <input type="text" name="name" id="editName" required placeholder="e.g., Tooth Extraction" style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Mode *</label>
                <select name="mode" id="editMode" required style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
                    <option value="BULK">BULK - Select entire arches</option>
                    <option value="SINGLE">SINGLE - Individual tooth selection</option>
                    <option value="NONE">NONE - No tooth selection needed</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Price (₱) *</label>
                <input type="number" name="price" id="editPrice" required min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Duration (minutes)</label>
                <input type="number" name="duration_minutes" id="editDuration" min="1" placeholder="30" style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Description</label>
                <textarea name="description" id="editDescription" placeholder="Brief description of service..." style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; min-height: 80px; resize: vertical;"></textarea>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Status</label>
                <select name="is_active" id="editIsActive" style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="background: #f3f4f6; border: none; color: #374151; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer;">Cancel</button>
                <button type="submit" style="background: #2563eb; border: none; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer;">Update Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- Toast -->
<div id="toast" class="toast"></div>

<script>
<?php foreach ($services as $service): ?>
window.serviceData = window.serviceData || {};
window.serviceData[<?php echo $service['id']; ?>] = {
    id: <?php echo $service['id']; ?>,
    name: "<?php echo addslashes($service['name']); ?>",
    mode: "<?php echo $service['mode']; ?>",
    price: <?php echo $service['price']; ?>,
    duration_minutes: <?php echo (int)$service['duration_minutes']; ?>,
    description: "<?php echo addslashes((string)($service['description'] ?? '')); ?>",
    is_active: <?php echo (int)$service['is_active']; ?>
};
<?php endforeach; ?>

<?php if (isset($toastMessage)): ?>
showToast('<?php echo addslashes($toastMessage); ?>', '<?php echo $toastType; ?>');
<?php endif; ?>

function editService(id) {
    const service = window.serviceData[id];
    if (!service) return;
    
    document.getElementById('editId').value = service.id;
    document.getElementById('editName').value = service.name;
    document.getElementById('editMode').value = service.mode;
    document.getElementById('editPrice').value = service.price;
    document.getElementById('editDuration').value = service.duration_minutes || '';
    document.getElementById('editDescription').value = service.description || '';
    document.getElementById('editIsActive').value = service.is_active;
    document.getElementById('editModal').style.display = 'flex';
}

function deleteService(id) {
    if (confirm('Are you sure you want to delete this service?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 1500);
}
</script>

<?php require_once 'includes/admin_layout_end.php'; ?>
