<?php
$pageTitle = 'Welcome Page Content';
require_once __DIR__ . '/../../config/database.php';

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'welcome_%'");
$rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($rows as $key => $value) {
    $settings[$key] = $value;
}

$heroImage = $settings['welcome_hero_image'] ?? '';
$aboutImage = $settings['welcome_about_image'] ?? '';
$logoPath = $settings['welcome_logo'] ?? '';

$aboutStats = isset($settings['welcome_about_stats']) ? json_decode($settings['welcome_about_stats'], true) : [];
if (!$aboutStats) $aboutStats = [['value' => '5,000+', 'label' => 'Happy Patients'], ['value' => '98%', 'label' => 'Satisfaction Rate']];

$faqs = isset($settings['welcome_faqs']) ? json_decode($settings['welcome_faqs'], true) : [];
if (!$faqs) $faqs = [];

$featuredIds = isset($settings['welcome_featured_services']) ? json_decode($settings['welcome_featured_services'], true) : [];
if (!$featuredIds) $featuredIds = [];

$servicesList = $pdo->query("SELECT id, name, price, duration_minutes, description FROM services WHERE is_active = 1 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->query("SELECT id, file_path, section FROM content_images ORDER BY id DESC");
$uploadedImages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
<style>
.content-layout { max-width: 960px; }
.content-tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 2px solid #e5e7eb; padding-bottom: 0; overflow-x: auto; }
.content-tab { padding: 12px 22px; cursor: pointer; border: none; background: transparent; font-size: 14px; font-weight: 500; color: #6b7280; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
.content-tab:hover { color: #005daa; }
.content-tab.active { color: #005daa; border-bottom-color: #005daa; }
.content-panel { display: none; }
.content-panel.active { display: block; }
.form-section { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.form-section-title { font-size: 16px; font-weight: 700; color: #1a1a2e; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
.form-row { margin-bottom: 16px; }
.form-row label { display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px; }
.form-row .form-control { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; transition: border-color 0.2s; box-sizing: border-box; }
.form-row .form-control:focus { outline: none; border-color: #005daa; box-shadow: 0 0 0 3px rgba(0,93,170,0.1); }
.form-row textarea.form-control { resize: vertical; min-height: 80px; }
.form-row-split { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.image-upload-area { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
.image-preview { width: 180px; height: 120px; border-radius: 8px; object-fit: cover; border: 1px solid #e5e7eb; background: #f9fafb; }
.image-preview-sm { width: 120px; height: 80px; }
.image-preview-circle { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 1px solid #e5e7eb; background: #f9fafb; }
.upload-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #f3f4f6; border: 1px dashed #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px; color: #374151; transition: all 0.2s; }
.upload-btn:hover { background: #e5e7eb; border-color: #9ca3af; }
.upload-btn input[type="file"] { display: none; }
.save-btn { padding: 10px 28px; background: #005daa; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.save-btn:hover { background: #004b8a; }
.save-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.saved-toast { display: none; padding: 10px 18px; background: #059669; color: #fff; border-radius: 8px; font-size: 14px; font-weight: 500; margin-top: 12px; animation: fadeInOut 2s ease; }
@keyframes fadeInOut { 0% { opacity: 0; transform: translateY(-8px); } 15% { opacity: 1; transform: translateY(0); } 85% { opacity: 1; transform: translateY(0); } 100% { opacity: 0; } }

.faq-item-row, .stat-item-row { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 12px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; }
.faq-item-row .faq-fields, .stat-item-row .stat-fields { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.faq-item-row .faq-fields input,
.faq-item-row .faq-fields textarea,
.stat-item-row .stat-fields input { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
.remove-btn { background: none; border: none; color: #ef4444; font-size: 20px; cursor: pointer; padding: 6px; line-height: 1; margin-top: 4px; }
.remove-btn:hover { background: #fee2e2; border-radius: 6px; }
.add-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #f3f4f6; border: 1px dashed #d1d5db; border-radius: 8px; cursor: pointer; font-size: 13px; color: #374151; }
.add-btn:hover { background: #e5e7eb; }

.service-checklist { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
.service-check-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
.service-check-item:hover { border-color: #005daa; background: #f0f7ff; }
.service-check-item.checked { border-color: #005daa; background: #dcf0ff; }
.service-check-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: #005daa; }
.service-check-item .service-info { flex: 1; }
.service-check-item .service-info .svc-name { font-weight: 600; font-size: 14px; color: #1a1a2e; }
.service-check-item .service-info .svc-meta { font-size: 12px; color: #6b7280; }

.logo-upload-area { text-align: center; padding: 40px 20px; }
.logo-preview { max-width: 200px; max-height: 200px; margin: 0 auto 16px; display: block; border-radius: 12px; border: 2px solid #e5e7eb; padding: 8px; }
.logo-placeholder { width: 200px; height: 200px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; border-radius: 12px; border: 2px dashed #d1d5db; color: #9ca3af; font-size: 14px; background: #f9fafb; }

@media (max-width: 640px) {
    .form-row-split { grid-template-columns: 1fr; }
    .content-tabs { gap: 0; }
    .content-tab { padding: 10px 14px; font-size: 13px; }
    .service-checklist { grid-template-columns: 1fr; }
}
</style>

<div class="content-layout">
    <div class="content-tabs" role="tablist">
        <button class="content-tab active" data-tab="home" role="tab" aria-selected="true">Home</button>
        <button class="content-tab" data-tab="about" role="tab" aria-selected="false">About Us</button>
        <button class="content-tab" data-tab="faq" role="tab" aria-selected="false">FAQ</button>
        <button class="content-tab" data-tab="services" role="tab" aria-selected="false">Services</button>
        <button class="content-tab" data-tab="logo" role="tab" aria-selected="false">Logo</button>
    </div>

    <div id="savedToast" class="saved-toast">Saved successfully!</div>

    <!-- ===== TAB: HOME ===== -->
    <div class="content-panel active" id="panel-home" role="tabpanel">
        <div class="form-section">
            <div class="form-section-title">Hero Section</div>
            <div class="form-row">
                <label for="heroTitle">Hero Title (HTML allowed)</label>
                <input type="text" id="heroTitle" class="form-control" value="<?php echo htmlspecialchars($settings['welcome_hero_title'] ?? 'Your Best Dental Care In Town Awaits'); ?>">
            </div>
            <div class="form-row">
                <label for="heroSubtitle">Hero Subtitle</label>
                <textarea id="heroSubtitle" class="form-control" rows="3"><?php echo htmlspecialchars($settings['welcome_hero_subtitle'] ?? ''); ?></textarea>
            </div>
            <div class="form-row">
                <label>Hero Image</label>
                <div class="image-upload-area">
                    <img id="heroImagePreview" class="image-preview" src="<?php echo $heroImage ? '../../' . $heroImage : '../../assets/images/Dentists.png'; ?>" alt="" onerror="this.src='../../assets/images/Dentists.png'">
                    <label class="upload-btn">
                        <span>Choose Image</span>
                        <input type="file" id="heroImageInput" accept="image/*" data-section="hero">
                    </label>
                    <input type="hidden" id="heroImage" value="<?php echo htmlspecialchars($heroImage); ?>">
                </div>
            </div>
            <div class="form-row-split">
                <div class="form-row">
                    <label for="heroBadgeBig">Badge Number (e.g. "7+")</label>
                    <input type="text" id="heroBadgeBig" class="form-control" value="<?php echo htmlspecialchars($settings['welcome_hero_badge_big'] ?? '7+'); ?>">
                </div>
                <div class="form-row">
                    <label for="heroBadgeSmall">Badge Label (e.g. "Years Experience")</label>
                    <input type="text" id="heroBadgeSmall" class="form-control" value="<?php echo htmlspecialchars($settings['welcome_hero_badge_small'] ?? 'Years Experience'); ?>">
                </div>
            </div>
            <div class="form-row-split">
                <div class="form-row">
                    <label for="heroNote1">Doctor Note 1 (e.g. "Hi I'm Dr. Rex")</label>
                    <input type="text" id="heroNote1" class="form-control" value="<?php echo htmlspecialchars($settings['welcome_hero_note1'] ?? "Hi I'm Dr. Rex"); ?>">
                </div>
                <div class="form-row">
                    <label for="heroNote2">Doctor Note 2 (e.g. "and I'm Dr. Floralyn")</label>
                    <input type="text" id="heroNote2" class="form-control" value="<?php echo htmlspecialchars($settings['welcome_hero_note2'] ?? "and I'm Dr. Floralyn"); ?>">
                </div>
            </div>
            <button class="save-btn" onclick="saveSection('hero')">Save Home Section</button>
        </div>
    </div>

    <!-- ===== TAB: ABOUT US ===== -->
    <div class="content-panel" id="panel-about" role="tabpanel">
        <div class="form-section">
            <div class="form-section-title">About Us Content</div>
            <div class="form-row">
                <label for="aboutHeading">Heading (HTML allowed)</label>
                <input type="text" id="aboutHeading" class="form-control" value="<?php echo htmlspecialchars($settings['welcome_about_heading'] ?? 'About RF Dental'); ?>">
            </div>
            <div class="form-row">
                <label for="aboutParagraph1">Paragraph 1</label>
                <textarea id="aboutParagraph1" class="form-control" rows="4"><?php echo htmlspecialchars($settings['welcome_about_paragraph1'] ?? ''); ?></textarea>
            </div>
            <div class="form-row">
                <label for="aboutParagraph2">Paragraph 2</label>
                <textarea id="aboutParagraph2" class="form-control" rows="4"><?php echo htmlspecialchars($settings['welcome_about_paragraph2'] ?? ''); ?></textarea>
            </div>
            <div class="form-row">
                <label>About Image</label>
                <div class="image-upload-area">
                    <img id="aboutImagePreview" class="image-preview" src="<?php echo $aboutImage ? '../../' . $aboutImage : '../../assets/images/2nd_Anniv.jpg'; ?>" alt="" onerror="this.src='../../assets/images/2nd_Anniv.jpg'">
                    <label class="upload-btn">
                        <span>Choose Image</span>
                        <input type="file" id="aboutImageInput" accept="image/*" data-section="about">
                    </label>
                    <input type="hidden" id="aboutImage" value="<?php echo htmlspecialchars($aboutImage); ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">Statistics</div>
            <p style="font-size:13px;color:#6b7280;margin:-8px 0 16px;">These appear below the About Us text (e.g. "5,000+ Happy Patients").</p>
            <div id="statsList"></div>
            <button class="add-btn" onclick="addStatItem()">+ Add Stat</button>
            <div style="margin-top:16px;">
                <button class="save-btn" onclick="saveSection('about')">Save About Us Section</button>
            </div>
        </div>
    </div>

    <!-- ===== TAB: FAQ ===== -->
    <div class="content-panel" id="panel-faq" role="tabpanel">
        <div class="form-section">
            <div class="form-section-title">Frequently Asked Questions</div>
            <p style="font-size:13px;color:#6b7280;margin:-8px 0 16px;">Manage the Q&A pairs displayed on the welcome page.</p>
            <div id="faqList"></div>
            <button class="add-btn" onclick="addFaqItem()">+ Add FAQ</button>
            <div style="margin-top:16px;">
                <button class="save-btn" onclick="saveSection('faq')">Save FAQ Section</button>
            </div>
        </div>
    </div>

    <!-- ===== TAB: SERVICES ===== -->
    <div class="content-panel" id="panel-services" role="tabpanel">
        <div class="form-section">
            <div class="form-section-title">Featured Services</div>
            <p style="font-size:13px;color:#6b7280;margin:-8px 0 16px;">Select which services appear in the featured cards on the welcome page.</p>
            <div class="service-checklist" id="serviceChecklist">
                <?php foreach ($servicesList as $svc): ?>
                <label class="service-check-item <?php echo in_array($svc['id'], $featuredIds) ? 'checked' : ''; ?>">
                    <input type="checkbox" value="<?php echo $svc['id']; ?>" <?php echo in_array($svc['id'], $featuredIds) ? 'checked' : ''; ?>>
                    <div class="service-info">
                        <div class="svc-name"><?php echo htmlspecialchars($svc['name']); ?></div>
                        <div class="svc-meta">₱<?php echo number_format((float)$svc['price'], 2); ?> · <?php echo (int)$svc['duration_minutes']; ?> min</div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:16px;">
                <button class="save-btn" onclick="saveSection('services')">Save Services Selection</button>
            </div>
        </div>
    </div>

    <!-- ===== TAB: LOGO ===== -->
    <div class="content-panel" id="panel-logo" role="tabpanel">
        <div class="form-section">
            <div class="form-section-title">Clinic Logo</div>
            <p style="font-size:13px;color:#6b7280;margin:-8px 0 16px;">This logo is shared across the entire welcome page (header, footer, contact form).</p>
            <div class="logo-upload-area">
                <div>
                    <?php if ($logoPath): ?>
                        <img id="logoPreview" class="logo-preview" src="../../<?php echo htmlspecialchars($logoPath); ?>" alt="Logo">
                    <?php else: ?>
                        <img id="logoPreview" class="logo-preview" src="../../assets/images/Logo.png" alt="Logo">
                    <?php endif; ?>
                </div>
                <label class="upload-btn" style="display:inline-flex;">
                    <span>Choose Logo Image</span>
                    <input type="file" id="logoInput" accept="image/*" data-section="logo">
                </label>
                <input type="hidden" id="logoPath" value="<?php echo htmlspecialchars($logoPath); ?>">
                <div style="margin-top:20px;">
                    <button class="save-btn" onclick="saveSection('logo')">Save Logo</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const STATS_DATA = <?php echo json_encode($aboutStats); ?>;
const FAQS_DATA = <?php echo json_encode($faqs); ?>;

function renderStats() {
    const container = document.getElementById('statsList');
    container.innerHTML = '';
    STATS_DATA.forEach((item, i) => {
        const div = document.createElement('div');
        div.className = 'stat-item-row';
        div.innerHTML = `
            <div class="stat-fields">
                <input type="text" class="stat-value" value="${escapeHtml(item.value)}" placeholder="Value (e.g. 5,000+)" style="font-weight:600;">
                <input type="text" class="stat-label" value="${escapeHtml(item.label)}" placeholder="Label (e.g. Happy Patients)">
            </div>
            <button class="remove-btn" onclick="removeStatItem(${i})" title="Remove">✕</button>
        `;
        container.appendChild(div);
    });
}

function addStatItem() {
    STATS_DATA.push({ value: '', label: '' });
    renderStats();
}

function removeStatItem(index) {
    STATS_DATA.splice(index, 1);
    renderStats();
}

function renderFaqs() {
    const container = document.getElementById('faqList');
    container.innerHTML = '';
    FAQS_DATA.forEach((item, i) => {
        const div = document.createElement('div');
        div.className = 'faq-item-row';
        div.innerHTML = `
            <div class="faq-fields">
                <input type="text" class="faq-q" value="${escapeHtml(item.q)}" placeholder="Question">
                <textarea class="faq-a" rows="3" placeholder="Answer">${escapeHtml(item.a)}</textarea>
            </div>
            <button class="remove-btn" onclick="removeFaqItem(${i})" title="Remove">✕</button>
        `;
        container.appendChild(div);
    });
}

function addFaqItem() {
    FAQS_DATA.push({ q: '', a: '' });
    renderFaqs();
}

function removeFaqItem(index) {
    FAQS_DATA.splice(index, 1);
    renderFaqs();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// Tab switching
document.querySelectorAll('.content-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.content-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
        document.querySelectorAll('.content-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        this.setAttribute('aria-selected', 'true');
        document.getElementById('panel-' + this.dataset.tab).classList.add('active');
    });
});

// Image upload handler
function setupImageUpload(inputId, previewId, hiddenId) {
    document.getElementById(inputId).addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('action', 'upload_image');
        formData.append('image', file);
        formData.append('section', this.dataset.section);

        fetch('welcome_content_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById(previewId).src = '../../' + data.file_path;
                document.getElementById(hiddenId).value = data.file_path;
                showToast('Image uploaded successfully');
            } else {
                alert('Upload failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(e => alert('Upload error: ' + e.message));
    });
}

setupImageUpload('heroImageInput', 'heroImagePreview', 'heroImage');
setupImageUpload('aboutImageInput', 'aboutImagePreview', 'aboutImage');
setupImageUpload('logoInput', 'logoPreview', 'logoPath');

function showToast(msg) {
    const toast = document.getElementById('savedToast');
    toast.textContent = msg;
    toast.style.display = 'block';
    toast.style.animation = 'none';
    setTimeout(() => { toast.style.animation = ''; }, 10);
    setTimeout(() => { toast.style.display = 'none'; }, 2500);
}

function saveSection(section) {
    const btn = document.querySelector(`.save-btn[onclick*="${section}"]`);
    btn.disabled = true;
    btn.textContent = 'Saving...';

    let formData = new FormData();
    formData.append('action', 'save_' + section);

    if (section === 'hero') {
        formData.append('hero_title', document.getElementById('heroTitle').value);
        formData.append('hero_subtitle', document.getElementById('heroSubtitle').value);
        formData.append('hero_image', document.getElementById('heroImage').value);
        formData.append('hero_badge_big', document.getElementById('heroBadgeBig').value);
        formData.append('hero_badge_small', document.getElementById('heroBadgeSmall').value);
        formData.append('hero_note1', document.getElementById('heroNote1').value);
        formData.append('hero_note2', document.getElementById('heroNote2').value);
    } else if (section === 'about') {
        // Collect stats from rendered inputs
        const statItems = document.querySelectorAll('#statsList .stat-item-row');
        const stats = [];
        statItems.forEach(row => {
            const value = row.querySelector('.stat-value').value.trim();
            const label = row.querySelector('.stat-label').value.trim();
            if (value || label) stats.push({ value, label });
        });
        formData.append('about_heading', document.getElementById('aboutHeading').value);
        formData.append('about_paragraph1', document.getElementById('aboutParagraph1').value);
        formData.append('about_paragraph2', document.getElementById('aboutParagraph2').value);
        formData.append('about_image', document.getElementById('aboutImage').value);
        formData.append('about_stats', JSON.stringify(stats));
    } else if (section === 'faq') {
        const faqItems = document.querySelectorAll('#faqList .faq-item-row');
        const faqs = [];
        faqItems.forEach(row => {
            const q = row.querySelector('.faq-q').value.trim();
            const a = row.querySelector('.faq-a').value.trim();
            if (q || a) faqs.push({ q, a });
        });
        formData.append('faqs', JSON.stringify(faqs));
    } else if (section === 'services') {
        const checked = [];
        document.querySelectorAll('#serviceChecklist input[type="checkbox"]:checked').forEach(cb => {
            checked.push(parseInt(cb.value));
        });
        formData.append('featured_services', JSON.stringify(checked));
    } else if (section === 'logo') {
        formData.append('logo', document.getElementById('logoPath').value);
    }

    fetch('welcome_content_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Save ' + (section.charAt(0).toUpperCase() + section.slice(1)) + ' Section';
        if (data.success) {
            showToast(data.message || 'Saved successfully!');
        } else {
            alert('Save failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.textContent = 'Save ' + (section.charAt(0).toUpperCase() + section.slice(1)) + ' Section';
        alert('Error: ' + e.message);
    });
}

// Initial render
renderStats();
renderFaqs();

// Service checklist toggle class
document.querySelectorAll('.service-check-item input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', function() {
        this.closest('.service-check-item').classList.toggle('checked');
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin_layout_end.php'; ?>
