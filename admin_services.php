<?php
/**
 * Services List - Admin page for managing dental services
 */

$pageTitle = 'Services List';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Services List</h2>
                    <button class="btn-primary" onclick="openServiceModal()">+ Add New Service</button>
                </div>

                <!-- Services Grid -->
                <div class="services-grid">
                    <!-- Service Card 1 -->
                    <div class="service-card">
                        <div class="service-header">
                            <span class="service-icon">🦷</span>
                            <h3>Tooth Extraction</h3>
                        </div>
                        <div class="service-body">
                            <p class="service-description">Simple tooth extraction procedure</p>
                            <div class="service-price">₱1,500</div>
                            <div class="service-meta">
                                <span class="service-duration">⏱️ 30 mins</span>
                                <span class="service-status active">Active</span>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button class="action-btn icon" title="Edit">✏️</button>
                            <button class="action-btn icon" title="Deactivate">❌</button>
                        </div>
                    </div>

                    <!-- Service Card 2 -->
                    <div class="service-card">
                        <div class="service-header">
                            <span class="service-icon">🔬</span>
                            <h3>Root Canal Treatment</h3>
                        </div>
                        <div class="service-body">
                            <p class="service-description">Endodontic treatment for infected teeth</p>
                            <div class="service-price">₱5,000</div>
                            <div class="service-meta">
                                <span class="service-duration">⏱️ 90 mins</span>
                                <span class="service-status active">Active</span>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button class="action-btn icon" title="Edit">✏️</button>
                            <button class="action-btn icon" title="Deactivate">❌</button>
                        </div>
                    </div>

                    <!-- Service Card 3 -->
                    <div class="service-card">
                        <div class="service-header">
                            <span class="service-icon">✨</span>
                            <h3>Oral Prophylaxis</h3>
                        </div>
                        <div class="service-body">
                            <p class="service-description">Professional teeth cleaning</p>
                            <div class="service-price">₱2,000</div>
                            <div class="service-meta">
                                <span class="service-duration">⏱️ 45 mins</span>
                                <span class="service-status active">Active</span>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button class="action-btn icon" title="Edit">✏️</button>
                            <button class="action-btn icon" title="Deactivate">❌</button>
                        </div>
                    </div>

                    <!-- Service Card 4 -->
                    <div class="service-card">
                        <div class="service-header">
                            <span class="service-icon">🦷</span>
                            <h3>Denture Adjustment</h3>
                        </div>
                        <div class="service-body">
                            <p class="service-description">Fit and adjustment of dentures</p>
                            <div class="service-price">₱1,500</div>
                            <div class="service-meta">
                                <span class="service-duration">⏱️ 30 mins</span>
                                <span class="service-status active">Active</span>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button class="action-btn icon" title="Edit">✏️</button>
                            <button class="action-btn icon" title="Deactivate">❌</button>
                        </div>
                    </div>

                    <!-- Service Card 5 -->
                    <div class="service-card">
                        <div class="service-header">
                            <span class="service-icon">📷</span>
                            <h3> dental X-Ray</h3>
                        </div>
                        <div class="service-body">
                            <p class="service-description">Panoramic and periapical X-rays</p>
                            <div class="service-price">₱800</div>
                            <div class="service-meta">
                                <span class="service-duration">⏱️ 15 mins</span>
                                <span class="service-status active">Active</span>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button class="action-btn icon" title="Edit">✏️</button>
                            <button class="action-btn icon" title="Deactivate">❌</button>
                        </div>
                    </div>

                    <!-- Service Card 6 -->
                    <div class="service-card">
                        <div class="service-header">
                            <span class="service-icon">💎</span>
                            <h3>Dental Braces Consultation</h3>
                        </div>
                        <div class="service-body">
                            <p class="service-description">Orthodontic assessment and consultation</p>
                            <div class="service-price">₱1,000</div>
                            <div class="service-meta">
                                <span class="service-duration">⏱️ 60 mins</span>
                                <span class="service-status active">Active</span>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button class="action-btn icon" title="Edit">✏️</button>
                            <button class="action-btn icon" title="Deactivate">❌</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Service Modal -->
            <div id="serviceModal" class="modal-overlay">
                <div class="modal">
                    <h2 id="serviceModalTitle">Add New Service</h2>
                    
                    <form id="serviceForm">
                        <div class="form-group">
                            <label>Service Name</label>
                            <input type="text" id="serviceName" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea id="serviceDescription" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label>Price (₱)</label>
                                <input type="number" id="servicePrice" class="form-control" required>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Duration (minutes)</label>
                                <input type="number" id="serviceDuration" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select id="serviceStatus" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="btn-cancel" onclick="closeServiceModal()">Cancel</button>
                            <button type="submit" class="btn-primary">Save Service</button>
                        </div>
                    </form>
                </div>
            </div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
