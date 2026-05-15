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
$serviceImages = isset($settings['welcome_service_images']) ? json_decode($settings['welcome_service_images'], true) : [];
if (!$serviceImages) $serviceImages = [];

$heroTitleBlocks = isset($settings['welcome_hero_title_blocks']) ? json_decode($settings['welcome_hero_title_blocks'], true) : null;
if (!$heroTitleBlocks) {
    $heroTitleBlocks = [['text' => '', 'color' => '', 'fontSize' => 64, 'fontWeight' => 700, 'lineHeight' => 1.05, 'newLine' => true], ['text' => '', 'color' => '#005daa', 'fontSize' => 64, 'fontWeight' => 700, 'lineHeight' => 1.05, 'newLine' => true]];
}

$servicesList = $pdo->query("SELECT id, name, price, duration_minutes, description FROM services WHERE is_active = 1 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->query("SELECT id, file_path, section FROM content_images ORDER BY id DESC");
$uploadedImages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
<style>
.content-layout { width: 100%; }
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

.featured-img-slot { display: flex; align-items: center; gap: 16px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 12px; }
.featured-img-slot .featured-slot-header { min-width: 180px; }
.featured-img-slot .featured-slot-number { display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; background: #005daa; color: #fff; border-radius: 50%; font-size: 12px; font-weight: 700; margin-right: 8px; }
.featured-img-slot .featured-slot-name { font-weight: 600; font-size: 14px; color: #1a1a2e; }
.featured-img-slot .feat-remove-img { background: none; border: none; color: #ef4444; font-size: 18px; cursor: pointer; padding: 4px 8px; border-radius: 4px; }
.featured-img-slot .feat-remove-img:hover { background: #fee2e2; }

/* Service Images View Toggle */
.svc-view-toggle { display:inline-flex; gap:2px; background:#f3f4f6; border-radius:6px; padding:2px; }
.svc-view-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:28px; border:none; background:transparent; border-radius:4px; cursor:pointer; color:#6b7280; transition:all 0.15s; }
.svc-view-btn:hover { color:#374151; }
.svc-view-btn.active { background:#fff; color:#005daa; box-shadow:0 1px 3px rgba(0,0,0,0.1); }

/* List view (default) */
#allServiceImages.list-view .featured-img-slot { display:flex; flex-direction:row; align-items:center; gap:16px; padding:16px; background:#f9fafb; border-radius:8px; border:1px solid #e5e7eb; margin-bottom:12px; }
#allServiceImages.list-view .featured-img-slot .image-upload-area { margin-left:auto; flex-shrink:0; }

/* Grid view (2 columns) */
#allServiceImages.grid-view { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
#allServiceImages.grid-view .featured-img-slot { display:flex; flex-direction:column; align-items:center; gap:10px; padding:20px 16px; margin-bottom:0; background:#f9fafb; border-radius:8px; border:1px solid #e5e7eb; text-align:center; }
#allServiceImages.grid-view .featured-img-slot .featured-slot-header { min-width:0; width:100%; }
#allServiceImages.grid-view .featured-img-slot .featured-slot-name { display:block; }
#allServiceImages.grid-view .featured-img-slot .svc-meta { justify-content:center; }
#allServiceImages.grid-view .featured-img-slot .image-upload-area { flex-direction:column; align-items:center; gap:8px; }
#allServiceImages.grid-view .featured-img-slot .image-preview-sm { width:100px; height:80px; }

@media (max-width: 640px) {
    .form-row-split { grid-template-columns: 1fr; }
    .content-tabs { gap: 0; }
    .content-tab { padding: 10px 14px; font-size: 13px; }
    .service-checklist { grid-template-columns: 1fr; }
    #allServiceImages.grid-view { grid-template-columns:1fr; }
}

/* Hero Title Block Builder */
.hero-block-row { display:flex; gap:12px; align-items:flex-start; margin-bottom:12px; padding:16px; background:#f9fafb; border-radius:8px; border:1px solid #e5e7eb; flex-wrap:wrap; }
.hero-block-row .hb-fields { flex:1; display:flex; flex-direction:column; gap:8px; min-width:150px; }
.hero-block-row .hb-fields input[type="text"] { width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; box-sizing:border-box; }
.hero-block-row .hb-options { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.hero-block-row .hb-options label { font-size:11px; color:#6b7280; display:flex; flex-direction:column; gap:1px; align-items:center; }
.hero-block-row .hb-options input[type="number"],
.hero-block-row .hb-options input[type="color"],
.hero-block-row .hb-options select { padding:3px 6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px; }
.hero-block-row .hb-options input[type="number"] { width:52px; }
.hero-block-row .hb-options input[type="color"] { width:36px; height:28px; padding:1px; cursor:pointer; }
.hero-block-row .hb-options select { width:80px; }
</style>

<div class="content-layout">
    <div class="content-tabs" role="tablist">
        <button class="content-tab active" data-tab="home" role="tab" aria-selected="true">Home</button>
        <button class="content-tab" data-tab="about" role="tab" aria-selected="false">About Us</button>
        <button class="content-tab" data-tab="faq" role="tab" aria-selected="false">FAQ</button>
        <button class="content-tab" data-tab="services" role="tab" aria-selected="false">Services</button>
        <button class="content-tab" data-tab="logo" role="tab" aria-selected="false">Logo</button>
        <button class="content-tab" data-tab="footer" role="tab" aria-selected="false">Footer</button>
    </div>

    <div id="savedToast" class="saved-toast">Saved successfully!</div>

    <!-- ===== TAB: HOME ===== -->
    <div class="content-panel active" id="panel-home" role="tabpanel">
        <div class="form-section">
            <div class="form-section-title">Hero Section</div>
            <div class="form-row">
                <label>Hero Title</label>
                <p style="font-size:13px;color:#6b7280;margin:-8px 0 16px;">Add text blocks and style each one individually.</p>
                <div id="heroTitleBlocks"></div>
                <button class="add-btn" onclick="addHeroBlock()" type="button">+ Add Text Block</button>
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
            <p style="font-size:13px;color:#6b7280;margin:-8px 0 16px;">Select up to <strong>3 services</strong> to appear as featured cards on the welcome page.</p>
            <div style="font-size:13px;color:#374151;margin-bottom:10px;">Selected: <span id="featuredCount">0</span>/3</div>
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

        <div class="form-section">
            <div class="form-section-title" style="display:flex;align-items:center;justify-content:space-between;">
                <span>Service Images</span>
                <div class="svc-view-toggle">
                    <button class="svc-view-btn active" data-view="list" title="List view" type="button">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </button>
                    <button class="svc-view-btn" data-view="grid" title="Grid view" type="button">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    </button>
                </div>
            </div>
            <p style="font-size:13px;color:#6b7280;margin:-8px 0 16px;">Assign a custom image to each service. Used in both the featured cards and the "Explore All Services" section.</p>
            <div id="allServiceImages"></div>
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

    <!-- ===== TAB: FOOTER ===== -->
    <div class="content-panel" id="panel-footer" role="tabpanel">
        <div class="form-section">
            <div class="form-section-title">Footer Content</div>
            <div class="form-row">
                <label for="footerTagline">Footer Tagline</label>
                <textarea id="footerTagline" class="form-control" rows="3" placeholder="e.g. Providing exceptional dental care with a commitment to your comfort and health."><?php echo htmlspecialchars($settings['welcome_footer_tagline'] ?? ''); ?></textarea>
            </div>
            <div class="form-row">
                <label for="footerFacebook">Facebook URL</label>
                <input type="url" id="footerFacebook" class="form-control" value="<?php echo htmlspecialchars($settings['welcome_footer_facebook'] ?? 'https://www.facebook.com'); ?>" placeholder="https://www.facebook.com/...">
            </div>
            <div class="form-row">
                <label for="footerMessenger">Messenger URL</label>
                <input type="url" id="footerMessenger" class="form-control" value="<?php echo htmlspecialchars($settings['welcome_footer_messenger'] ?? 'https://m.me'); ?>" placeholder="https://m.me/...">
            </div>
            <div style="margin-top:16px;">
                <button class="save-btn" onclick="saveSection('footer')">Save Footer</button>
            </div>
        </div>
    </div>
</div>

<script>
const STATS_DATA = <?php echo json_encode($aboutStats); ?>;
const FAQS_DATA = <?php echo json_encode($faqs); ?>;
const SERVICE_IMAGES = <?php echo json_encode($serviceImages); ?>;
const SERVICES_DATA = <?php echo json_encode($servicesList); ?>;
const FEATURED_IDS = <?php echo json_encode($featuredIds); ?>;
let featuredOrder = FEATURED_IDS.slice();
let HERO_BLOCKS = <?php echo json_encode($heroTitleBlocks); ?>;

function renderHeroBlocks() {
    const container = document.getElementById('heroTitleBlocks');
    if (!container) return;
    container.innerHTML = '';
    HERO_BLOCKS.forEach((block, i) => {
        const div = document.createElement('div');
        div.className = 'hero-block-row';
        div.innerHTML = `
            <div class="hb-fields">
                <input type="text" class="hb-text" value="${escapeHtml(block.text)}" placeholder="Text content" data-index="${i}">
            </div>
            <div class="hb-options">
                <label>Color
                    <input type="color" class="hb-color" value="${block.color || '#000000'}" data-index="${i}">
                </label>
                <label>Size
                    <input type="number" class="hb-font-size" value="${block.fontSize || 64}" min="10" max="120" data-index="${i}">
                </label>
                <label>Weight
                    <select class="hb-font-weight" data-index="${i}">
                        <option value="300" ${(block.fontWeight == 300) ? 'selected' : ''}>Light</option>
                        <option value="400" ${(block.fontWeight == 400) ? 'selected' : ''}>Normal</option>
                        <option value="600" ${(block.fontWeight == 600) ? 'selected' : ''}>Semi Bold</option>
                        <option value="700" ${(block.fontWeight == 700) ? 'selected' : ''}>Bold</option>
                        <option value="900" ${(block.fontWeight == 900) ? 'selected' : ''}>Black</option>
                    </select>
                </label>
                <label>Line H
                    <input type="number" class="hb-line-height" value="${block.lineHeight || 1.05}" min="0.5" max="3" step="0.05" data-index="${i}">
                </label>
                <label style="cursor:pointer;flex-direction:row;gap:4px;font-size:11px;color:#374151;">
                    <input type="checkbox" class="hb-newline" data-index="${i}" ${block.newLine !== false ? 'checked' : ''}>
                    New Line
                </label>
            </div>
            <button class="remove-btn" onclick="removeHeroBlock(${i})" title="Remove" type="button">✕</button>
        `;
        container.appendChild(div);
    });
    // Attach live input events to sync HERO_BLOCKS
    container.querySelectorAll('.hb-text').forEach(inp => {
        inp.addEventListener('input', function() { HERO_BLOCKS[this.dataset.index].text = this.value; });
    });
    container.querySelectorAll('.hb-color').forEach(inp => {
        inp.addEventListener('input', function() { HERO_BLOCKS[this.dataset.index].color = this.value; });
    });
    container.querySelectorAll('.hb-font-size').forEach(inp => {
        inp.addEventListener('input', function() { HERO_BLOCKS[this.dataset.index].fontSize = parseFloat(this.value) || 64; });
    });
    container.querySelectorAll('.hb-font-weight').forEach(inp => {
        inp.addEventListener('change', function() { HERO_BLOCKS[this.dataset.index].fontWeight = parseInt(this.value) || 400; });
    });
    container.querySelectorAll('.hb-line-height').forEach(inp => {
        inp.addEventListener('input', function() { HERO_BLOCKS[this.dataset.index].lineHeight = parseFloat(this.value) || 1.05; });
    });
    container.querySelectorAll('.hb-newline').forEach(inp => {
        inp.addEventListener('change', function() { HERO_BLOCKS[this.dataset.index].newLine = this.checked; });
    });
}

function addHeroBlock() {
    HERO_BLOCKS.push({ text: '', color: '#000000', fontSize: 64, fontWeight: 700, lineHeight: 1.05, newLine: true });
    renderHeroBlocks();
}

function removeHeroBlock(index) {
    HERO_BLOCKS.splice(index, 1);
    renderHeroBlocks();
}

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

function renderAllServiceImages() {
    const container = document.getElementById('allServiceImages');
    if (!container) return;
    const savedView = localStorage.getItem('svcImageView') || 'list';
    container.className = savedView === 'grid' ? 'grid-view' : 'list-view';
    let html = '';
    SERVICES_DATA.forEach(svc => {
        const id = svc.id;
        const currentImg = SERVICE_IMAGES[id] || '';
        const previewSrc = currentImg ? '../../' + currentImg : '../../assets/images/Logo.png';
        html += '<div class="featured-img-slot">';
        html += '<div class="featured-slot-header"><span class="featured-slot-name">' + escapeHtml(svc.name) + '</span><div class="svc-meta" style="font-size:12px;color:#6b7280;margin-top:4px;">₱' + parseFloat(svc.price).toFixed(2) + ' &middot; ' + svc.duration_minutes + ' min</div></div>';
        html += '<div class="image-upload-area">';
        html += '<img id="svcPreview_' + id + '" class="image-preview image-preview-sm" src="' + previewSrc + '" alt="" onerror="this.src=\'../../assets/images/Logo.png\'">';
        html += '<label class="upload-btn"><span>Choose Image</span><input type="file" class="svc-img-file" accept="image/*" data-service-id="' + id + '"></label>';
        html += '<input type="hidden" id="svcPath_' + id +'" class="svc-img-path" value="' + escapeHtml(currentImg) + '">';
        html += '</div></div>';
    });
    container.innerHTML = html;
    document.querySelectorAll('.svc-img-file').forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            const serviceId = this.dataset.serviceId;
            const formData = new FormData();
            formData.append('action', 'upload_image');
            formData.append('image', file);
            formData.append('section', 'services');
            const btn = this.closest('.upload-btn');
            if (btn) btn.style.opacity = '0.6';
            fetch('welcome_content_actions.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (btn) btn.style.opacity = '1';
                    if (data.success) {
                        const path = data.file_path;
                        document.getElementById('svcPreview_' + serviceId).src = '../../' + path;
                        document.getElementById('svcPath_' + serviceId).value = path;
                        SERVICE_IMAGES[serviceId] = path;
                        fetch('welcome_content_actions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=save_service_image&service_id=' + encodeURIComponent(serviceId) + '&file_path=' + encodeURIComponent(path)
                        });
                        showToast('Image uploaded successfully');
                    } else {
                        alert('Upload failed: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(e => { if (btn) btn.style.opacity = '1'; alert('Upload error: ' + e.message); });
        });
    });
}

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
        formData.append('hero_title_blocks', JSON.stringify(HERO_BLOCKS));
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
        if (featuredOrder.length > 3) {
            btn.disabled = false;
            btn.textContent = 'Save Services Section';
            showToast('Maximum of 3 featured services allowed.');
            return;
        }
        formData.append('featured_services', JSON.stringify(featuredOrder));
        const images = {};
        document.querySelectorAll('.svc-img-path').forEach(hidden => {
            const id = hidden.id.replace('svcPath_', '');
            if (hidden.value) images[id] = hidden.value;
        });
        formData.append('service_images', JSON.stringify(images));
    } else if (section === 'logo') {
        formData.append('logo', document.getElementById('logoPath').value);
    } else if (section === 'footer') {
        formData.append('footer_tagline', document.getElementById('footerTagline').value);
        formData.append('footer_facebook', document.getElementById('footerFacebook').value);
        formData.append('footer_messenger', document.getElementById('footerMessenger').value);
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
renderHeroBlocks();
renderStats();
renderFaqs();

// Service checklist: max 3 selection + counter
function updateFeaturedCount() {
    const checked = document.querySelectorAll('#serviceChecklist input[type="checkbox"]:checked').length;
    document.getElementById('featuredCount').textContent = checked;
}
document.querySelectorAll('.service-check-item input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', function() {
        const checked = document.querySelectorAll('#serviceChecklist input[type="checkbox"]:checked');
        if (checked.length > 3) {
            this.checked = false;
            showToast('You can only select up to 3 featured services.');
            return;
        }
        this.closest('.service-check-item').classList.toggle('checked');
        const id = parseInt(this.value);
        if (this.checked) {
            if (featuredOrder.indexOf(id) === -1) featuredOrder.push(id);
        } else {
            const idx = featuredOrder.indexOf(id);
            if (idx !== -1) featuredOrder.splice(idx, 1);
        }
        updateFeaturedCount();
    });
});
updateFeaturedCount();
renderAllServiceImages();
// View toggle for service images
document.querySelectorAll('.svc-view-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const view = this.dataset.view;
        document.querySelectorAll('.svc-view-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        localStorage.setItem('svcImageView', view);
        const container = document.getElementById('allServiceImages');
        container.className = view === 'grid' ? 'grid-view' : 'list-view';
    });
});
// Sync toggle button state with saved preference
const savedView = localStorage.getItem('svcImageView') || 'list';
document.querySelectorAll('.svc-view-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.view === savedView);
});
</script>

<?php require_once __DIR__ . '/../../includes/admin_layout_end.php'; ?>
