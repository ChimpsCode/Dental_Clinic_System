<?php
$pageTitle = 'Services Management';
require_once 'includes/admin_layout_start.php';

require_once 'config/database.php';

// Check and migrate services table schema if needed
// This ensures your local database matches the expected schema
try {
    // Check if table exists first
    $tableExists = $pdo->query("SHOW TABLES LIKE 'services'")->rowCount() > 0;
    
    if ($tableExists) {
        $columns = $pdo->query("SHOW COLUMNS FROM services")->fetchAll(PDO::FETCH_COLUMN);
        $columnMap = array_flip($columns);
        
        // Migrate from old schema to new schema if needed
        if (isset($columnMap['service_name']) && !isset($columnMap['name'])) {
            // Rename service_name to name
            $pdo->exec("ALTER TABLE services CHANGE COLUMN service_name name VARCHAR(200) NOT NULL");
            $columns = $pdo->query("SHOW COLUMNS FROM services")->fetchAll(PDO::FETCH_COLUMN);
            $columnMap = array_flip($columns);
        }
        
        if (isset($columnMap['default_cost']) && !isset($columnMap['price'])) {
            // Rename default_cost to price
            $pdo->exec("ALTER TABLE services CHANGE COLUMN default_cost price DECIMAL(10,2) DEFAULT 0.00");
            $columns = $pdo->query("SHOW COLUMNS FROM services")->fetchAll(PDO::FETCH_COLUMN);
            $columnMap = array_flip($columns);
        }
        
        // Add missing columns (check if name exists first to determine position)
        if (!isset($columnMap['mode'])) {
            $afterColumn = isset($columnMap['name']) ? 'name' : 'id';
            $pdo->exec("ALTER TABLE services ADD COLUMN mode ENUM('BULK', 'SINGLE', 'NONE') DEFAULT 'SINGLE' AFTER $afterColumn");
            $columns = $pdo->query("SHOW COLUMNS FROM services")->fetchAll(PDO::FETCH_COLUMN);
            $columnMap = array_flip($columns);
        }
        if (!isset($columnMap['price'])) {
            $afterColumn = isset($columnMap['mode']) ? 'mode' : (isset($columnMap['name']) ? 'name' : 'id');
            $pdo->exec("ALTER TABLE services ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00 AFTER $afterColumn");
            $columns = $pdo->query("SHOW COLUMNS FROM services")->fetchAll(PDO::FETCH_COLUMN);
            $columnMap = array_flip($columns);
        }
        if (!isset($columnMap['duration_minutes'])) {
            $afterColumn = isset($columnMap['price']) ? 'price' : (isset($columnMap['mode']) ? 'mode' : 'id');
            $pdo->exec("ALTER TABLE services ADD COLUMN duration_minutes INT DEFAULT 30 AFTER $afterColumn");
        }
    }
} catch (PDOException $e) {
    // Table might not exist yet, database.php will create it with correct schema
    // Or there might be a permission issue - log it but don't break the page
    error_log("Services table migration check failed: " . $e->getMessage());
}

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

// Determine which column name to use (after migration should be 'name')
$nameColumnCheck = false;
try {
    $nameColumnCheck = $pdo->query("SHOW COLUMNS FROM services LIKE 'name'")->fetch();
    $nameColumnForQuery = $nameColumnCheck ? 'name' : 'service_name';
} catch (PDOException $e) {
    $nameColumnForQuery = 'service_name';
    $nameColumnCheck = false;
}

// Build WHERE clause for filtering
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "($nameColumnForQuery LIKE ? OR description LIKE ?)";
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

// Get services for current page (use the column name we determined earlier)
$sql = "SELECT * FROM services $whereSQL ORDER BY $nameColumnForQuery ASC LIMIT $itemsPerPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize column names - if old schema (service_name), map to 'name' for compatibility
// This ensures the rest of the code works regardless of which column name exists
// Only normalize if we're using the old column name
if (!$nameColumnCheck && !empty($services)) {
    foreach ($services as &$service) {
        if (isset($service['service_name']) && !isset($service['name'])) {
            $service['name'] = $service['service_name'];
        }
        if (isset($service['default_cost']) && !isset($service['price'])) {
            $service['price'] = $service['default_cost'];
        }
        // Set defaults for missing columns
        if (!isset($service['mode'])) {
            $service['mode'] = 'SINGLE';
        }
        if (!isset($service['duration_minutes'])) {
            $service['duration_minutes'] = 30;
        }
    }
    unset($service);
}

// Get all services for stats
$stmtAll = $pdo->query("SELECT * FROM services");
$allServices = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$totalBulk = count(array_filter($allServices, function($s) { return $s['mode'] === 'BULK'; }));
$totalSingle = count(array_filter($allServices, function($s) { return $s['mode'] === 'SINGLE'; }));
$totalInactive = count(array_filter($allServices, function($s) { return $s['is_active'] == 0; }));
?>

<style>
/* Services page specific styles */
.services-page {
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

/* Service kebab menu */
.service-kebab-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    color: #6b7280;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.service-kebab-btn:hover,
.service-kebab-btn.active {
    background-color: #f3f4f6;
    color: #111827;
}

.service-kebab-dropdown {
    position: fixed;
    display: none;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.2);
    min-width: 170px;
    z-index: 10000;
    overflow: hidden;
}

.service-kebab-dropdown.show {
    display: block;
    animation: fadeInKebab 0.16s ease;
}

.service-kebab-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    color: #374151;
    text-decoration: none;
    font-size: 0.95rem;
    transition: background 0.15s ease, color 0.15s ease;
}

.service-kebab-dropdown a:hover {
    background: #f3f4f6;
    color: #111827;
}

.service-kebab-dropdown .danger {
    color: #b91c1c;
}

.service-kebab-dropdown .danger:hover {
    background: #fef2f2;
    color: #991b1b;
}

.service-kebab-dropdown svg {
    width: 18px;
    height: 18px;
    color: currentColor;
}

.service-kebab-backdrop {
    position: fixed;
    inset: 0;
    display: none;
    z-index: 9999;
}

.service-kebab-backdrop.show {
    display: block;
}

@keyframes fadeInKebab {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
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
/* Container to hold the buttons in a row */
.pagination-buttons {
    display: flex;
    gap: 8px; /* Space between the buttons */
    align-items: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

/* Default Button Style (White bg, gray border) */
.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px; /* Adjust height/width here */
    background-color: #ffffff;
    border: 1px solid #e2e8f0; /* Light gray border */
    border-radius: 6px; /* Rounded corners */
    text-decoration: none;
    color: #4a5568; /* Dark gray text */
    font-size: 14px;
    transition: all 0.2s ease;
    min-width: 32px; /* Ensures square-ish shape for numbers */
}

/* Hover Effect for normal buttons */
.pagination-btn:hover:not(.active):not(.disabled) {
    background-color: #f7fafc;
    border-color: #cbd5e0;
}

/* Active State (The Blue Button) */
.pagination-btn.active {
    background-color: #2563eb; /* Bright Blue */
    color: #ffffff;
    border-color: #2563eb;
}

/* Disabled State (The faded Previous button) */
.pagination-btn.disabled {
    color: #a0aec0; /* Light gray text */
    background-color: #fff;
    cursor: not-allowed;
    border-color: #edf2f7; /* Very light border */
}
</style>

<div class="content-main">
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
                                <button
                                    type="button"
                                    class="service-kebab-btn"
                                    data-service-id="<?php echo (int)$service['id']; ?>"
                                    aria-label="Service actions"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="12" cy="5" r="2"></circle>
                                        <circle cx="12" cy="12" r="2"></circle>
                                        <circle cx="12" cy="19" r="2"></circle>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination">
        <span class="pagination-info">
            Showing <?php echo $totalServices > 0 ? ($offset + 1) : 0; ?>-<?php echo min($offset + $itemsPerPage, $totalServices); ?> of <?php echo $totalServices; ?> services
        </span>
        <div class="pagination-buttons">
            <?php
            // Build query string for pagination links
            $queryParams = [];
            if (!empty($search)) $queryParams['search'] = $search;
            if (!empty($modeFilter)) $queryParams['mode'] = $modeFilter;
            if (!empty($statusFilter)) $queryParams['status'] = $statusFilter;
            $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
            ?>
            
            <!-- Previous Button -->
            <a href="?page=<?php echo max(1, $currentPage - 1) . $queryString; ?>" 
               class="pagination-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>"
               <?php echo $currentPage <= 1 ? 'style="opacity: 0.5; cursor: not-allowed;" onclick="return false;"' : ''; ?>>
                Previous
            </a>
            
            <!-- Page Numbers -->
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i . $queryString; ?>" 
                   class="pagination-btn <?php echo $i === $currentPage ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <!-- Next Button -->
            <a href="?page=<?php echo min($totalPages, $currentPage + 1) . $queryString; ?>" 
               class="pagination-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>"
               <?php echo $currentPage >= $totalPages ? 'style="opacity: 0.5; cursor: not-allowed;" onclick="return false;"' : ''; ?>>
                Next
            </a>
        </div>
    </div>
</div>

<!-- Service kebab portal -->
<div id="serviceKebabDropdown" class="service-kebab-dropdown"></div>
<div id="serviceKebabBackdrop" class="service-kebab-backdrop"></div>

<!-- Add Modal -->
<div id="addModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 16px; width: 90%; max-width: 500px; max-height: 90vh;">
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
    <div class="modal" style="background: white; border-radius: 16px; width: 90%; max-width: 500px; max-height: 90vh;">
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

/**
 * Service kebab (meatball) menu
 */
let serviceKebabDropdown = null;
let serviceKebabBackdrop = null;
let activeServiceKebabButton = null;

function initServiceKebabMenu() {
    serviceKebabDropdown = document.getElementById('serviceKebabDropdown');
    serviceKebabBackdrop = document.getElementById('serviceKebabBackdrop');

    if (!serviceKebabDropdown || !serviceKebabBackdrop) return;

    document.addEventListener('click', handleServiceKebabToggle);
    serviceKebabDropdown.addEventListener('click', handleServiceKebabAction);
    serviceKebabBackdrop.addEventListener('click', closeServiceKebabDropdown);
    window.addEventListener('resize', closeServiceKebabDropdown);
    window.addEventListener('scroll', closeServiceKebabDropdown, true);
}

function handleServiceKebabToggle(e) {
    const button = e.target.closest('.service-kebab-btn');
    const isKebabButton = Boolean(button);

    if (!isKebabButton) {
        if (!e.target.closest('.service-kebab-dropdown')) {
            closeServiceKebabDropdown();
        }
        return;
    }

    e.preventDefault();
    e.stopPropagation();

    if (activeServiceKebabButton === button) {
        closeServiceKebabDropdown();
        return;
    }

    openServiceKebabDropdown(button);
}

function openServiceKebabDropdown(button) {
    if (!serviceKebabDropdown || !serviceKebabBackdrop) return;

    const id = button.dataset.serviceId;

    serviceKebabDropdown.innerHTML = getServiceKebabMarkup(id);
    positionServiceKebabDropdown(button);

    serviceKebabDropdown.classList.add('show');
    serviceKebabBackdrop.classList.add('show');
    button.classList.add('active');
    activeServiceKebabButton = button;
}

function closeServiceKebabDropdown() {
    if (serviceKebabDropdown) {
        serviceKebabDropdown.classList.remove('show');
        serviceKebabDropdown.innerHTML = '';
    }
    if (serviceKebabBackdrop) {
        serviceKebabBackdrop.classList.remove('show');
    }
    if (activeServiceKebabButton) {
        activeServiceKebabButton.classList.remove('active');
        activeServiceKebabButton = null;
    }
}

function positionServiceKebabDropdown(button) {
    const rect = button.getBoundingClientRect();
    const dropdownWidth = 180;
    const dropdownHeight = 120;
    const padding = 12;

    let left = rect.right + 6;
    let top = rect.top;

    if (left + dropdownWidth > window.innerWidth - padding) {
        left = rect.left - dropdownWidth - 6;
    }
    if (left < padding) left = padding;

    if (top + dropdownHeight > window.innerHeight - padding) {
        top = rect.bottom - dropdownHeight;
    }
    if (top < padding) top = padding;

    serviceKebabDropdown.style.left = `${left}px`;
    serviceKebabDropdown.style.top = `${top}px`;
}

function getServiceKebabMarkup(id) {
    return `
        <a href="#" data-action="edit" data-id="${id}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
            Edit
        </a>
        <a href="#" class="danger" data-action="delete" data-id="${id}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Delete
        </a>
    `;
}

function handleServiceKebabAction(e) {
    const link = e.target.closest('a[data-action]');
    if (!link) return;

    e.preventDefault();
    const action = link.dataset.action;
    const id = parseInt(link.dataset.id, 10);

    closeServiceKebabDropdown();

    if (!id) return;

    if (action === 'edit') {
        editService(id);
    } else if (action === 'delete') {
        deleteService(id);
    }
}

// Kick off kebab menu bindings
initServiceKebabMenu();
</script>

<?php require_once 'includes/admin_layout_end.php'; ?>
