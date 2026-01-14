<?php
$pageTitle = 'Patient Records';
require_once 'includes/staff_layout_start.php';

try {
    require_once 'config/database.php';
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC LIMIT 50");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients = [];
}
?>

            <div class="search-toolbar" style="background: white; padding: 16px 24px; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--border-color);">
                <div class="search-input-container" style="position: relative; flex: 1;">
                    <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #6b7280;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="m19.6 21l-6.3-6.3q-.75.6-1.725.95T9.5 16q-2.725 0-4.612-1.888T3 9.5t1.888-4.612T9.5 3t4.613 1.888T16 9.5q0 1.1-.35 2.075T14.7 13.3l6.3 6.3zM9.5 14q1.875 0 3.188-1.312T14 9.5t-1.312-3.187T9.5 5T6.313 6.313T5 9.5t1.313 3.188T9.5 14"/></svg>
                    </span>
                    <input type="text" id="searchInput" placeholder="Search patient name..." class="search-input" style="width: 100%; padding: 12px 16px 12px 44px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem;">
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div class="total-patients-box" style="padding: 10px 16px; background: #f9fafb; border-radius: 8px; font-weight: 600; color: var(--text-main); border: 1px solid var(--border-color);">
                        Total: <?php echo count($patients); ?> Patients
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-title">
                    <span>Patient Records</span>
                </div>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Contact</th>
                                <th>Last Visit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patientsTableBody">
                            <?php if (empty($patients)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 60px; color: #6b7280;">
                                        <p style="font-size: 1.1rem; margin-bottom: 8px;">No patient records found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr class="patient-row" data-name="<?php echo strtolower(htmlspecialchars($patient['full_name'] ?? $patient['name'] ?? 'Unknown')); ?>">
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div class="patient-avatar" style="width: 40px; height: 40px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #6b7280; font-weight: 600;">
                                                    <?php echo strtoupper(substr($patient['full_name'] ?? $patient['name'] ?? 'U', 0, 1)); ?>
                                                </div>
                                                <span class="patient-name" style="font-weight: 500;"><?php echo htmlspecialchars($patient['full_name'] ?? $patient['name'] ?? 'Unknown'); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['phone'] ?? $patient['contact_info'] ?? 'N/A'); ?></td>
                                        <td><?php echo isset($patient['last_visit']) ? date('M d, Y', strtotime($patient['last_visit'])) : (isset($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A'); ?></td>
                                        <td><span class="status-badge" style="background: #dbeafe; color: #1e40af;">Active</span></td>
                                        <td>
                                            <div class="patient-actions">
                                                <a href="patient_details.php?id=<?php echo $patient['id']; ?>" class="action-btn icon" title="View" style="text-decoration: none;">👁️</a>
                                                <button class="action-btn icon" title="Edit">✏️</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

    <script>
        document.getElementById('searchInput').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('.patient-row').forEach(row => {
                const match = row.dataset.name.includes(search);
                row.style.display = match ? '' : 'none';
            });
        });
    </script>
<?php require_once 'includes/staff_layout_end.php'; ?>