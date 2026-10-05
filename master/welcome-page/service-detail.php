<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
require_once __DIR__ . '/../../includes/auth_redirect.php';
redirect_if_authenticated();
require_once '../../config/database.php';
require_once __DIR__ . '/../../includes/discount_helper.php';

$serviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($serviceId <= 0) {
    header('Location: home.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, mode, price, duration_minutes, description FROM services WHERE id = ? AND is_active = 1 AND (is_archived = 0 OR is_archived IS NULL)");
$stmt->execute([$serviceId]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: home.php');
    exit;
}

$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$clinicSettings = [];
while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $clinicSettings[$row['setting_key']] = $row['setting_value'];
}
$clinicName = $clinicSettings['clinic_name'] ?? 'RF Dental Clinic';
$clinicAddress = $clinicSettings['clinic_address'] ?? '';
$clinicPhone = $clinicSettings['clinic_phone'] ?? '';
$clinicEmail = $clinicSettings['clinic_email'] ?? '';
$welcomeLogo = $clinicSettings['welcome_logo'] ?? '';
$footerTagline = $clinicSettings['welcome_footer_tagline'] ?? 'Providing exceptional dental care with a commitment to your comfort and health.';
$footerFacebook = $clinicSettings['welcome_footer_facebook'] ?? 'https://www.facebook.com';
$footerMessenger = $clinicSettings['welcome_footer_messenger'] ?? 'https://m.me';
$serviceImages = json_decode($clinicSettings['welcome_service_images'] ?? '', true) ?? [];

$logoFull = $welcomeLogo ? '../../' . $welcomeLogo : '../../assets/images/Logo.png';

$defaultHours = [
    'monday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'tuesday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'wednesday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'thursday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'friday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'saturday' => ['open' => '09:00', 'close' => '13:00', 'is_open' => true],
    'sunday' => ['open' => '', 'close' => '', 'is_open' => false]
];
$businessHours = isset($clinicSettings['business_hours']) ? json_decode($clinicSettings['business_hours'], true) : $defaultHours;
if (!$businessHours) $businessHours = $defaultHours;

function serviceDetailFormatHours($hours) {
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $shortDays = ['monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun'];
    
    $groups = [];
    $currentGroup = null;

    foreach ($days as $day) {
        if (isset($hours[$day]) && $hours[$day]['is_open']) {
            $timeRange = date('g:i A', strtotime($hours[$day]['open'])) . ' - ' . date('g:i A', strtotime($hours[$day]['close']));
            
            if ($currentGroup && $currentGroup['time'] === $timeRange) {
                $currentGroup['end'] = $day;
            } else {
                if ($currentGroup) $groups[] = $currentGroup;
                $currentGroup = ['start' => $day, 'end' => $day, 'time' => $timeRange];
            }
        } else {
            if ($currentGroup) {
                $groups[] = $currentGroup;
                $currentGroup = null;
            }
        }
    }
    if ($currentGroup) $groups[] = $currentGroup;

    if (empty($groups)) return 'Closed';

    $displayParts = [];
    foreach ($groups as $group) {
        if ($group['start'] === $group['end']) {
            $displayParts[] = $shortDays[$group['start']] . ' ' . $group['time'];
        } else {
            $displayParts[] = $shortDays[$group['start']] . ' - ' . $shortDays[$group['end']] . ' ' . $group['time'];
        }
    }

    return implode(' | ', $displayParts);
}
$displayHours = serviceDetailFormatHours($businessHours);

$customImg = $serviceImages[$service['id']] ?? '';
$serviceImg = ($customImg && file_exists(__DIR__ . '/../../' . $customImg)) ? '../../' . $customImg : $logoFull;

$modeLabel = $service['mode'] === 'NONE' ? 'Not applicable' : ($service['mode'] === 'BULK' ? 'Bulk package' : 'Per session');

$assets = [
    'icon_phone' => '../../assets/images/icon-phone.svg',
    'icon_email' => '../../assets/images/icon-email.svg',
    'icon_time' => '../../assets/images/icon-time.svg',
];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($service['name']) ?> - <?= htmlspecialchars($clinicName) ?></title>
    <link rel="icon" type="image/png" href="../../assets/images/Logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,700;1,400&family=Inria+Sans:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/css/home-styles.css" />
    <script src="../../assets/js/form-sanitize.js?v=2"></script>
    <style>
      body { opacity: 0; animation: homeFadeIn 0.45s ease-out forwards; }
      @keyframes homeFadeIn { from { opacity: 0; } to { opacity: 1; } }

      .sd-back { padding: 16px 0 0; }
      .sd-back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 15px; font-weight: 600; color: var(--primary);
        text-decoration: none; padding: 8px 0;
      }
      .sd-back-btn:hover { color: var(--accent); }
      @media (max-width: 768px) {
        .site-header { display: none; }
        body { padding-top: 0; }
      }

      .sd-hero { padding: 24px 0 32px; }
      .sd-hero-inner {
        display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center;
      }
      .sd-hero-img {
        border-radius: 12px; width: 100%; height: 340px; object-fit: cover;
        box-shadow: 0 8px 24px rgba(0,0,0,0.10);
      }
      .sd-hero-title {
        font-size: 36px; font-weight: 700; margin: 0 0 12px; line-height: 1.15;
      }
      .sd-hero-desc {
        font-size: 18px; color: rgba(0,0,0,0.7); margin: 0; line-height: 1.6;
      }
      .sd-hero-meta { margin-top: 20px; display: flex; gap: 24px; }
      .sd-hero-meta-item { font-size: 14px; color: rgba(0,0,0,0.6); }
      .sd-hero-meta-item strong { color: var(--text); font-weight: 600; }

      .sd-cards { padding: 0 0 40px; }
      .sd-cards-inner {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
      }
      .sd-card {
        background: #fff; border: 1px solid rgba(0,0,0,0.12);
        border-radius: 10px; padding: 28px 24px; text-align: center;
        box-shadow: 0 4px 14px rgba(0,0,0,0.06);
      }
      .sd-card-icon { font-size: 32px; margin-bottom: 10px; line-height: 1; }
      .sd-card-label {
        font-size: 14px; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.5px; color: rgba(0,0,0,0.5); margin-bottom: 6px;
      }
      .sd-card-value { font-size: 22px; font-weight: 700; color: var(--accent); }

      .sd-info { padding: 0 0 40px; }
      .sd-info-inner {
        display: grid; grid-template-columns: 1fr 1fr; gap: 24px;
      }
      .sd-info-box {
        background: #fff; border: 1px solid rgba(0,0,0,0.12);
        border-radius: 10px; padding: 28px 24px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.06);
      }
      .sd-info-box h3 {
        font-size: 20px; font-weight: 700; margin: 0 0 16px;
        color: var(--primary);
      }
      .sd-info-box ul, .sd-info-box ol {
        margin: 0; padding-left: 20px;
        font-size: 15px; color: rgba(0,0,0,0.75); line-height: 1.8;
      }

      .sd-cta { padding: 0 0 64px; text-align: center; }
      .sd-cta .btn {
        padding: 16px 40px; font-size: 18px; border-radius: 8px;
      }

      @media (max-width: 980px) {
        .sd-hero-inner { grid-template-columns: 1fr; gap: 24px; }
        .sd-hero-img { height: 260px; }
        .sd-hero-title { font-size: 28px; }
        .sd-cards-inner { grid-template-columns: repeat(2, 1fr); }
        .sd-info-inner { grid-template-columns: 1fr; }
      }
      @media (max-width: 560px) {
        .sd-hero { padding: 16px 0 24px; }
        .sd-hero-img { height: 200px; }
        .sd-hero-title { font-size: 24px; }
        .sd-hero-desc { font-size: 16px; }
        .sd-cards-inner { grid-template-columns: 1fr; }
        .sd-card { padding: 20px 16px; }
        .sd-card-value { font-size: 20px; }
        .sd-info-box { padding: 20px 16px; }
        .sd-cta .btn { width: 100%; }
      }

      .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7); display: flex; justify-content: center;
        align-items: center; z-index: 9999;
      }
      .modal-content {
        background: white; border-radius: 12px; width: 90%; max-width: 520px;
        max-height: 90vh; padding: 0; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalSlideIn 0.3s ease; overflow: hidden;
        display: flex; flex-direction: column;
      }
      @keyframes modalSlideIn {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }
      .modal-header {
        padding: 20px 24px; border-bottom: 1px solid #eee;
        display: flex; justify-content: space-between; align-items: center;
        flex-shrink: 0;
      }
      .modal-header h3 { margin: 0; font-size: 18px; font-weight: 600; }
      .modal-close {
        background: none; border: none; font-size: 24px; cursor: pointer;
        color: #666; padding: 0; line-height: 1;
      }
      .modal-close:hover { color: #333; }
      .modal-body { padding: 24px; max-height: calc(90vh - 80px); overflow-y: auto; flex: 1; }
      .modal-intro {
        color: #666; margin-bottom: 20px; font-size: 14px;
      }

      .choice-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
      }
      .choice-card {
        border: 2px solid #e5e7eb; border-radius: 12px; padding: 32px 20px;
        text-align: center; cursor: pointer; transition: all 0.25s ease;
        background: #fff;
      }
      .choice-card:hover {
        border-color: var(--primary); box-shadow: 0 8px 24px rgba(0,93,170,0.12);
        transform: translateY(-2px);
      }
      .choice-card-icon { font-size: 40px; margin-bottom: 12px; line-height: 1; }
      .choice-card-title {
        font-size: 18px; font-weight: 700; color: var(--text); margin-bottom: 6px;
      }
      .choice-card-desc {
        font-size: 13px; color: rgba(0,0,0,0.6); margin: 0; line-height: 1.4;
      }

      .success-modal { text-align: center; padding: 40px 30px; }
      .success-icon { color: #28a745; margin-bottom: 16px; }
      .success-icon svg { display: block; margin: 0 auto; }

      .modal-form .form-group { margin-bottom: 16px; }
      .modal-form label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
      .modal-form input, .modal-form select {
        width: 100%; padding: 12px; border: 1px solid #ddd;
        border-radius: 6px; font-size: 14px; box-sizing: border-box;
      }
      .modal-form input:focus, .modal-form select:focus {
        outline: none; border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
      }
      .btn-block { width: 100%; margin-top: 8px; }
      .modal-footer-text {
        margin-top: 16px; text-align: center; font-size: 12px; color: #666;
      }
      .modal-footer-text a { color: #007bff; text-decoration: none; }
      .modal-footer-text a:hover { text-decoration: underline; }

      .registration-modal { max-width: 520px; }
      .form-section { margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
      .form-section:last-of-type { border-bottom: none; margin-bottom: 16px; }
      .form-section-title {
        font-size: 15px; font-weight: 600; color: #0059a3;
        margin: 0 0 16px 0; padding-bottom: 8px;
        border-bottom: 2px solid #0059a3; display: inline-block;
      }
      .modal-form .form-row { display: flex; gap: 12px; }
      .modal-form .form-row .form-group { flex: 1; }
      .modal-form select { background: white; cursor: pointer; }
      .modal-form .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }

      #reg-password[data-strength="weak"] { border-color: #ef4444 !important; }
      #reg-password[data-strength="medium"] { border-color: #f59e0b !important; }
      #reg-password[data-strength="strong"] { border-color: #16a34a !important; }
      #reg-confirm-password[data-match="match"] { border-color: #16a34a !important; }
      #reg-confirm-password[data-match="mismatch"] { border-color: #ef4444 !important; }

      .registration-password-box {
        margin: 10px 0 14px;
        background: rgba(15, 23, 42, 0.04);
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 14px;
        color: #0f172a;
        font-size: 13px;
        line-height: 1.55;
      }
      .registration-password-title { font-weight: 700; margin-bottom: 8px; }
      .pwd-req-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 7px; }
      .pwd-req, .pwd-match-hint {
        display: flex; align-items: center; gap: 8px; color: #64748b; font-size: 13px;
        transition: color 0.2s ease;
      }
      .pwd-check { width: 20px; height: 20px; flex-shrink: 0; display: inline-flex; }
      .pwd-check svg { width: 20px; height: 20px; overflow: visible; }
      .pwd-check-bg { fill: #fff; stroke: #cbd5e1; stroke-width: 1.75; transition: fill 0.22s ease, stroke 0.22s ease; }
      .pwd-check-mark {
        fill: none; stroke: #fff; stroke-width: 2.3; stroke-linecap: round; stroke-linejoin: round;
        stroke-dasharray: 20; stroke-dashoffset: 20; transition: stroke-dashoffset 0.32s ease 0.08s;
      }
      .pwd-check-x {
        fill: none; stroke: #ef4444; stroke-width: 2.1; stroke-linecap: round; opacity: 0;
        transform-origin: 12px 12px; transform: scale(0.6);
        transition: opacity 0.18s ease, transform 0.18s ease;
      }
      .pwd-req.is-met, .pwd-match-hint.is-met { color: #15803d; font-weight: 600; }
      .pwd-req.is-met .pwd-check-bg, .pwd-match-hint.is-met .pwd-check-bg { fill: #22c55e; stroke: #16a34a; }
      .pwd-req.is-met .pwd-check-mark, .pwd-match-hint.is-met .pwd-check-mark { stroke-dashoffset: 0; }
      .pwd-req.is-met .pwd-check, .pwd-match-hint.is-met .pwd-check {
        animation: pwdCheckPop 0.38s cubic-bezier(0.34, 1.56, 0.64, 1);
      }
      .pwd-match-hint { margin: 8px 0 0; min-height: 22px; }
      .pwd-match-hint.is-idle { color: #94a3b8; }
      .pwd-match-hint.is-mismatch { color: #dc2626; font-weight: 600; }
      .pwd-match-hint.is-mismatch .pwd-check-bg { fill: #fef2f2; stroke: #ef4444; }
      .pwd-match-hint.is-mismatch .pwd-check-x { opacity: 1; transform: scale(1); }
      .password-input-group {
        position: relative;
        margin-bottom: 0;
      }
      .password-input-group input {
        padding-right: 42px;
      }
      .modal-form .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        color: #999;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2;
      }
      .modal-form .toggle-password:hover { color: #0059a3; }
      .modal-form .eye-icon { width: 20px; height: 20px; }

      @media (max-width: 560px) {
        .modal-content { width: 95%; max-width: 95%; max-height: 85vh; }
        .modal-body { padding: 16px; }
        .modal-form .form-row { flex-direction: column; gap: 0; }
        .modal-header { padding: 16px; }
        .modal-header h3 { font-size: 16px; }
        .choice-grid { grid-template-columns: 1fr; }
        .choice-card { padding: 24px 16px; }
        .success-modal { padding: 30px 20px; }
      }
    </style>
    <?php require __DIR__ . '/../../includes/app_notice_modal_script.php'; ?>
    <?php app_guest_bfcache_reload_script(); ?>
  </head>
  <body>
    <div class="nav-overlay"></div>
    <header class="site-header">
      <div class="container header-inner">
        <a class="brand" href="home.php" aria-label="<?= htmlspecialchars($clinicName); ?> home">
          <img class="brand-mark" src="<?= htmlspecialchars($logoFull); ?>" alt="" />
          <span class="brand-text"><?= htmlspecialchars($clinicName); ?></span>
        </a>
        <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
          <span class="nav-toggle-bar"></span>
          <span class="nav-toggle-bar"></span>
          <span class="nav-toggle-bar"></span>
        </button>
        <nav class="nav" aria-label="Primary">
          <a href="home.php" class="nav-link">Home</a>
          <a href="home.php#about" class="nav-link">About Us</a>
          <a href="home.php#services" class="nav-link">Services</a>
          <a href="home.php#faq" class="nav-link">FAQ</a>
          <a href="home.php#contact" class="nav-link">Contact Us</a>
          <div class="nav-actions">
            <a class="btn btn-outline" href="login.php?form=1#register">Sign Up</a>
            <a class="btn btn-primary" href="login.php?form=1">Login</a>
          </div>
        </nav>
        <div class="header-actions">
          <a class="btn btn-outline" href="login.php?form=1#register">Sign Up</a>
          <a class="btn btn-primary" href="login.php?form=1">Login</a>
        </div>
      </div>
    </header>

    <main>
      <div class="container sd-back">
        <a class="sd-back-btn" href="home.php#services">&larr; Back</a>
      </div>

      <section class="sd-hero">
        <div class="container sd-hero-inner">
          <div>
            <img class="sd-hero-img" src="<?= htmlspecialchars($serviceImg) ?>" alt="<?= htmlspecialchars($service['name']) ?>" />
          </div>
          <div>
            <h1 class="sd-hero-title"><?= htmlspecialchars($service['name']) ?></h1>
            <p class="sd-hero-desc"><?= htmlspecialchars($service['description'] ?? '') ?></p>
            <div class="sd-hero-meta">
              <span class="sd-hero-meta-item"><strong>Mode:</strong> <?= htmlspecialchars($modeLabel) ?></span>
            </div>
          </div>
        </div>
      </section>

      <section class="sd-cards">
        <div class="container sd-cards-inner">
          <div class="sd-card">
            <div class="sd-card-icon">&#9201;</div>
            <div class="sd-card-label">Duration</div>
            <div class="sd-card-value"><?= (int)$service['duration_minutes'] ?> min</div>
          </div>
          <div class="sd-card">
            <div class="sd-card-icon">&#128176;</div>
            <div class="sd-card-label">Price</div>
            <div class="sd-card-value">&#8369;<?= number_format((float)$service['price'], 2) ?></div>
          </div>
          <div class="sd-card">
            <div class="sd-card-icon">&#128295;</div>
            <div class="sd-card-label">Maintenance</div>
            <div class="sd-card-value"><?= $service['mode'] === 'NONE' ? 'One-time' : 'As needed' ?></div>
          </div>
        </div>
      </section>

      <section class="sd-info">
        <div class="container sd-info-inner">
          <div class="sd-info-box">
            <h3>Key Benefits</h3>
            <ul>
              <li>Professional care from experienced dental specialists</li>
              <li>Modern equipment and advanced treatment techniques</li>
              <li>Comfortable and welcoming clinic environment</li>
              <li>Personalized treatment plan tailored to your needs</li>
              <li>Affordable rates with flexible payment options</li>
            </ul>
          </div>
          <div class="sd-info-box">
            <h3>Treatment Process</h3>
            <ol>
              <li>Schedule your appointment online or via phone</li>
              <li>Visit our clinic for an initial consultation and assessment</li>
              <li>Receive your personalized treatment plan</li>
              <li>Complete the treatment with our expert team</li>
              <li>Follow up with post-treatment care and checkups</li>
            </ol>
          </div>
        </div>
      </section>

      <section class="sd-cta">
        <div class="container">
          <button type="button" class="btn btn-primary btn-lg" id="scheduleBtn">Schedule <?= htmlspecialchars($service['name']) ?></button>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="container footer-inner">
        <div class="footer-brand">
          <div class="footer-brand-top">
            <img class="footer-logo" src="<?= htmlspecialchars($logoFull); ?>" alt="" />
            <div class="footer-brand-title"><?= htmlspecialchars($clinicName); ?></div>
          </div>
          <p class="footer-text"><?= htmlspecialchars($footerTagline); ?></p>
        </div>
        <div class="footer-col">
          <div class="footer-heading">Quick Links</div>
          <a class="footer-link" href="home.php">Home</a>
          <a class="footer-link" href="home.php#about">About Us</a>
          <a class="footer-link" href="home.php#services">Services</a>
          <a class="footer-link" href="home.php#contact">Contact</a>
        </div>
        <div class="footer-col">
          <div class="footer-heading">Services</div>
          <?php
          $footerServices = $pdo->query("SELECT id, name FROM services WHERE is_active = 1 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY name LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
          ?>
          <?php foreach ($footerServices as $fs): ?>
            <a class="footer-link" href="service-detail.php?id=<?= (int)$fs['id'] ?>"><?= htmlspecialchars($fs['name']); ?></a>
          <?php endforeach; ?>
        </div>
        <div class="footer-col footer-connect">
          <div class="footer-heading">Connect With Us</div>
          <div class="footer-icons" aria-label="Social and contact links">
            <a href="<?= htmlspecialchars($footerFacebook); ?>" target="_blank" rel="noreferrer" class="footer-icon-link" aria-label="Facebook">
              <span class="footer-icon footer-icon-fb" aria-hidden="true">f</span>
            </a>
            <a href="mailto:<?= htmlspecialchars($clinicEmail ?: 'rfdc2023@gmail.com'); ?>" class="footer-icon-link" aria-label="Email">
              <span class="footer-icon footer-icon-mail" aria-hidden="true">✉</span>
            </a>
            <a href="<?= htmlspecialchars($footerMessenger); ?>" target="_blank" rel="noreferrer" class="footer-icon-link" aria-label="Facebook Messenger">
              <img src="../../assets/images/icon-messenger.svg" alt="" class="footer-icon-img" aria-hidden="true" />
            </a>
            <a href="tel:<?= htmlspecialchars(str_replace([' ', '-', '(', ')'], '', $clinicPhone ?: '09671878603')); ?>" class="footer-icon-link" aria-label="Call">
              <span class="footer-icon footer-icon-phone" aria-hidden="true">☎</span>
            </a>
          </div>
          <div class="footer-location">
            <span class="footer-location-icon" aria-hidden="true">&#128205;</span>
            <span class="footer-text small">
              <?= htmlspecialchars($clinicAddress) ?: 'Celeste Bldg. MaxSuniel St. Brgy. Carmen, Cagayan de Oro, Philippines, 9000'; ?>
            </span>
          </div>
        </div>
      </div>
    </footer>

    <div id="choiceModal" class="modal-overlay" style="display: none;">
      <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
          <h3>Schedule <?= htmlspecialchars($service['name']) ?></h3>
          <button type="button" class="modal-close" id="choiceModalClose">&times;</button>
        </div>
        <div class="modal-body">
          <p class="modal-intro">Are you a new patient or an existing patient?</p>
          <div class="choice-grid">
            <div class="choice-card" id="newPatientChoice">
              <div class="choice-card-icon">&#128100;</div>
              <div class="choice-card-title">New Patient</div>
              <p class="choice-card-desc">First time visiting our clinic. Create an account to secure your appointment.</p>
            </div>
            <div class="choice-card" id="existingPatientChoice">
              <div class="choice-card-icon">&#128104;</div>
              <div class="choice-card-title">Existing Patient</div>
              <p class="choice-card-desc">Already have an account? Log in to book your appointment.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="registrationModal" class="modal-overlay" style="display: none;">
      <div class="modal-content registration-modal">
        <div class="modal-header">
          <h3>Secure Your Appointment</h3>
          <button type="button" class="modal-close" id="regModalClose">&times;</button>
        </div>
        <div class="modal-body">
          <p class="modal-intro">Complete your patient details, then choose how to register.</p>
          <form id="registrationForm" class="modal-form" autocomplete="off" novalidate>
            <input type="text" tabindex="-1" aria-hidden="true" autocomplete="username" style="position:absolute;left:-9999px;height:0;width:0;opacity:0;" value="">
            <input type="password" tabindex="-1" aria-hidden="true" autocomplete="current-password" style="position:absolute;left:-9999px;height:0;width:0;opacity:0;" value="">
            <input type="hidden" id="reg-first-name" name="first_name" />
            <input type="hidden" id="reg-middle-name" name="middle_name" />
            <input type="hidden" id="reg-last-name" name="last_name" />
            <input type="hidden" id="reg-email-hidden" name="email_hidden" />

            <div data-registration-stage="details">
            <div class="form-section">
              <h4 class="form-section-title">Patient Information</h4>
              <div class="form-row">
                <div class="form-group">
                  <label for="reg-first-name-display">First Name</label>
                  <input type="text" id="reg-first-name-display" placeholder="First Name" required maxlength="80" pattern="[A-Za-zÑñ\s\-]{1,80}" title="Letters, spaces, Ñ, and hyphens only" autocomplete="given-name" />
                </div>
                <div class="form-group">
                  <label for="reg-middle-name-display">Middle Name</label>
                  <input type="text" id="reg-middle-name-display" placeholder="Middle Name" maxlength="80" pattern="[A-Za-zÑñ\s\-]{1,80}" title="Letters, spaces, Ñ, and hyphens only" autocomplete="additional-name" />
                </div>
                <div class="form-group">
                  <label for="reg-last-name-display">Last Name</label>
                  <input type="text" id="reg-last-name-display" placeholder="Last Name" required maxlength="80" pattern="[A-Za-zÑñ\s\-]{1,80}" title="Letters, spaces, Ñ, and hyphens only" autocomplete="family-name" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="reg-phone">Phone Number <span style="color:#dc2626">*</span></label>
                  <input type="tel" id="reg-phone" name="phone" required placeholder="09123456789" maxlength="11" inputmode="numeric" pattern="09[0-9]{9}" title="11-digit mobile number starting with 09" autocomplete="tel" />
                </div>
                <div class="form-group">
                  <label for="reg-gender">Gender <span style="color:#dc2626">*</span></label>
                  <select id="reg-gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                  </select>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="reg-dob">Date of Birth</label>
                  <input type="date" id="reg-dob" name="date_of_birth" data-discount-dob="1" onchange="var d=new Date(this.value);var a=new Date().getFullYear()-d.getFullYear();if(new Date().getMonth()<d.getMonth()||(new Date().getMonth()==d.getMonth()&&new Date().getDate()<d.getDate()))a--;document.getElementById('reg-age').value=a>0&&a<120?a:'';if(window.DiscountIdFields)window.DiscountIdFields.sync(document)" />
                </div>
                <div class="form-group">
                  <label for="reg-age">Age</label>
                  <input type="number" id="reg-age" name="age" min="1" max="120" placeholder="Auto-calculated" readonly style="background-color: #f3f4f6;" />
                </div>
              </div>
              <div class="form-group">
                <label for="reg-address">Address (Optional)</label>
                <input type="text" id="reg-address" name="address" placeholder="Your complete address" maxlength="255" />
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="reg-emergency-name">Emergency Contact Name <span style="color:#dc2626">*</span></label>
                  <input type="text" id="reg-emergency-name" name="emergency_contact_name" placeholder="Full name" required maxlength="80" pattern="[A-Za-zÑñ\s\-]{1,80}" title="Letters, spaces, Ñ, and hyphens only" />
                </div>
                <div class="form-group">
                  <label for="reg-emergency-phone">Emergency Contact Number <span style="color:#dc2626">*</span></label>
                  <input type="tel" id="reg-emergency-phone" name="emergency_contact_phone" placeholder="09123456789" maxlength="11" required inputmode="numeric" pattern="09[0-9]{9}" title="11-digit mobile number starting with 09" autocomplete="tel" />
                </div>
              </div>
              <div class="form-group">
                <label for="reg-emergency-relationship">Relationship <span style="color:#dc2626">*</span></label>
                <select id="reg-emergency-relationship" name="emergency_contact_relationship" required>
                  <option value="" disabled selected>Select relationship</option>
                  <option value="Parent">Parent</option>
                  <option value="Spouse">Spouse</option>
                  <option value="Child">Child</option>
                  <option value="Sibling">Sibling</option>
                  <option value="Grandparent">Grandparent</option>
                  <option value="Relative">Relative</option>
                  <option value="Guardian">Guardian</option>
                  <option value="Friend">Friend</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="form-group" data-senior-id-fields style="display:none;">
                <label for="reg-senior-id-type">Senior government ID <span style="color:#dc2626">*</span></label>
                <select id="reg-senior-id-type" name="senior_id_type">
                  <?php echo seniorIdTypeOptionsHtml(); ?>
                </select>
                <label for="reg-senior-id" style="margin-top:8px;display:block;">ID number <span style="color:#dc2626">*</span></label>
                <input type="text" id="reg-senior-id" name="senior_citizen_id" placeholder="Enter ID number" maxlength="30" />
                <div style="font-size:12px;color:#6b7280;margin-top:4px;">Age 60+ still needs a government ID number for the official 20% senior discount (RA 9994).</div>
              </div>
              <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                  <input type="checkbox" name="is_pwd" value="1" data-pwd-toggle="1" style="width:10px" />
                  Person with Disability (PWD)
                </label>
              </div>
              <div class="form-group" data-pwd-id-fields style="display:none;">
                <label for="reg-pwd-id">PWD ID No. <span style="color:#dc2626">*</span></label>
                <input type="text" id="reg-pwd-id" name="pwd_id" placeholder="e.g., PWD-CDO-2024-00123" maxlength="30" />
                <div style="font-size:12px;color:#6b7280;margin-top:4px;">The official PWD ID number is required for the 20% PWD discount (RA 10754).</div>
              </div>
            </div>

              <button type="button" class="btn btn-primary btn-block" data-registration-details-next>Next</button>
            </div>
            <div data-registration-stage="contact" style="display:none;">
            <div class="form-section">
              <h4 class="form-section-title">Account contact</h4>
              <div class="form-group" style="margin-bottom:12px;">
                <label for="reg-contact-method">Register using</label>
                <select id="reg-contact-method" name="contact_method" class="styled-select" style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:8px;">
                  <option value="email">Email address</option>
                  <option value="phone">Mobile number</option>
                </select>
                <p id="reg-contact-hint" data-contact-hint style="font-size:13px;color:#64748b;margin:8px 0;"></p>
              </div>
              <div class="form-group">
                <label for="reg-email">Email (Username)</label>
                <input type="email" id="reg-email" name="email" required placeholder="your@email.com" autocomplete="off" maxlength="254" />
                <div id="reg-email-status" style="display:none; margin-top:6px; font-size:13px; color:#dc3545;"></div>
              </div>
<div class="form-group" data-contact-mobile-group style="display:none;">
                <label for="reg-contact-mobile">Mobile number</label>
                <input type="tel" id="reg-contact-mobile" placeholder="09123456789" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" autocomplete="tel" aria-describedby="reg-contact-hint" />
              </div>
<p data-contact-error role="alert" style="display:none;color:#dc2626;font-size:13px;"></p>
<div style="display:flex;gap:10px;width:100%;margin-top:16px;">
                <button type="button" class="btn btn-outline login-btn" data-registration-back="details" style="flex:1;">Back</button>
                <button type="button" class="btn btn-primary login-btn" data-registration-contact-next style="flex:2;">Next</button>
              </div>
            </div>
            </div>
            <div data-registration-stage="password" style="display:none;">
            <div class="form-section">
              <h4 class="form-section-title">Create your password</h4>
<div class="form-group" style="margin-bottom:16px;">
                <label for="reg-contact-summary">Registering with <span data-contact-summary-label>email address</span></label>
                <input type="text" id="reg-contact-summary" readonly aria-label="Selected registration contact" style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;box-sizing:border-box;" />
              </div>
              <div class="form-group">
                <label for="reg-password">Create Password</label>
                <div class="password-input-group">
                  <input type="password" id="reg-password" name="password" required minlength="8" placeholder="Password" autocomplete="new-password" readonly />
                  <button type="button" class="toggle-password" id="regTogglePassword" aria-label="Toggle password visibility">
                    <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                      <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                  </button>
                </div>
              </div>
              <div id="passwordRequirementsBox" class="registration-password-box">
                <div class="registration-password-title">Password Requirements:</div>
                <ul class="pwd-req-list">
                  <li class="pwd-req" data-rule="length">
                    <span class="pwd-check" aria-hidden="true"><svg viewBox="0 0 24 24"><circle class="pwd-check-bg" cx="12" cy="12" r="10"></circle><path class="pwd-check-mark" d="M7.5 12.4l3.1 3.1 5.9-6.2"></path></svg></span>
                    <span>At least 8 characters</span>
                  </li>
                  <li class="pwd-req" data-rule="upper">
                    <span class="pwd-check" aria-hidden="true"><svg viewBox="0 0 24 24"><circle class="pwd-check-bg" cx="12" cy="12" r="10"></circle><path class="pwd-check-mark" d="M7.5 12.4l3.1 3.1 5.9-6.2"></path></svg></span>
                    <span>At least one uppercase letter</span>
                  </li>
                  <li class="pwd-req" data-rule="lower">
                    <span class="pwd-check" aria-hidden="true"><svg viewBox="0 0 24 24"><circle class="pwd-check-bg" cx="12" cy="12" r="10"></circle><path class="pwd-check-mark" d="M7.5 12.4l3.1 3.1 5.9-6.2"></path></svg></span>
                    <span>At least one lowercase letter</span>
                  </li>
                  <li class="pwd-req" data-rule="number">
                    <span class="pwd-check" aria-hidden="true"><svg viewBox="0 0 24 24"><circle class="pwd-check-bg" cx="12" cy="12" r="10"></circle><path class="pwd-check-mark" d="M7.5 12.4l3.1 3.1 5.9-6.2"></path></svg></span>
                    <span>At least one number</span>
                  </li>
                  <li class="pwd-req" data-rule="symbol">
                    <span class="pwd-check" aria-hidden="true"><svg viewBox="0 0 24 24"><circle class="pwd-check-bg" cx="12" cy="12" r="10"></circle><path class="pwd-check-mark" d="M7.5 12.4l3.1 3.1 5.9-6.2"></path></svg></span>
                    <span>At least one symbol</span>
                  </li>
                </ul>
              </div>
              <div class="form-group">
                <label for="reg-confirm-password">Confirm Password</label>
                <div class="password-input-group">
                  <input type="password" id="reg-confirm-password" name="confirm_password" required placeholder="Re-enter password" autocomplete="new-password" readonly />
                  <button type="button" class="toggle-password" id="regToggleConfirmPassword" aria-label="Toggle confirm password visibility">
                    <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                      <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                  </button>
                </div>
                <div class="pwd-match-hint is-idle" id="regPasswordMatchHint">
                  <span class="pwd-check" aria-hidden="true"><svg viewBox="0 0 24 24"><circle class="pwd-check-bg" cx="12" cy="12" r="10"></circle><path class="pwd-check-mark" d="M7.5 12.4l3.1 3.1 5.9-6.2"></path><path class="pwd-check-x" d="M9 9l6 6M15 9l-6 6"></path></svg></span>
                  <span class="pwd-match-text">Re-enter password to confirm</span>
                </div>
              </div>
            </div>

            <button type="button" class="btn btn-outline btn-block" data-registration-back="contact" style="margin-bottom:10px;">Back</button>
            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </div>
          </form>

          <div id="regOtpSection" style="display: none;">
            <div style="text-align:center; padding: 20px 0;">
              <p style="margin-bottom: 16px; color: #555;">
                Enter the 6-digit verification code sent to <strong id="regOtpEmailDisplay"></strong>
              </p>
              <div class="alert alert-error" id="regOtpError" style="display: none; color: #dc3545; font-size: 14px; margin-bottom: 12px; text-align: center;"></div>
              <div style="margin-bottom: 16px;">
                <input type="text" id="regOtpCode" placeholder="Enter verification code" maxlength="6" inputmode="numeric" style="width:200px; text-align:center; letter-spacing:6px; font-size:20px; padding:10px; border:2px solid #2563eb; border-radius:8px; outline:none;">
              </div>
              <button type="button" class="btn btn-primary" id="regOtpVerifyBtn" style="width:100%;">
                <span id="regOtpVerifyBtnText">Verify & Create Account</span>
              </button>
              <div style="margin-top: 12px;">
                <span style="font-size:13px; color:#666;">Didn't receive the code? </span>
                <button type="button" id="regOtpResendBtn" style="background:none; border:none; color:#2563eb; cursor:pointer; font-size:13px; text-decoration:underline;">Resend</button>
                <span id="regOtpTimer" style="font-size:13px; color:#999; margin-left:4px;"></span>
              </div>
              <div style="margin-top: 12px;">
                <a href="#" id="backToRegFormLink" style="font-size:13px; color:#666;">Back to registration form</a>
              </div>
            </div>
          </div>

          <div class="modal-footer-text">
            <p>By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</p>
          </div>
        </div>
      </div>
    </div>

    <div id="successModal" class="modal-overlay" style="display: none;">
      <div class="modal-content success-modal">
        <div class="success-icon">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
        </div>
        <h3>Registration Complete!</h3>
        <p>Your account has been created successfully.</p>
        <p>Redirecting to your dashboard...</p>
      </div>
    </div>

    <script>
      (() => {
        const navToggle = document.querySelector('.nav-toggle');
        const nav = document.querySelector('.nav');
        const overlay = document.querySelector('.nav-overlay');
        if (navToggle && nav) {
          const toggleNav = (shouldOpen) => {
            const isOpen = shouldOpen ?? !nav.classList.contains('open');
            nav.classList.toggle('open', isOpen);
            navToggle.classList.toggle('open', isOpen);
            overlay?.classList.toggle('open', isOpen);
            navToggle.setAttribute('aria-expanded', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
          };
          navToggle.addEventListener('click', () => toggleNav());
          overlay?.addEventListener('click', () => toggleNav(false));
          nav.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => toggleNav(false));
          });
        }
      })();

      document.addEventListener('DOMContentLoaded', function() {
        const choiceModal = document.getElementById('choiceModal');
        const registrationModal = document.getElementById('registrationModal');
        const successModal = document.getElementById('successModal');
        const scheduleBtn = document.getElementById('scheduleBtn');
        const choiceModalClose = document.getElementById('choiceModalClose');
        const regModalClose = document.getElementById('regModalClose');
        const body = document.body;

        function openModal(modal) {
          modal.style.display = 'flex';
          body.style.overflow = 'hidden';
        }

        function closeModal(modal) {
          modal.style.display = 'none';
          body.style.overflow = '';
        }

        scheduleBtn.addEventListener('click', function() {
          openModal(choiceModal);
        });

        choiceModalClose.addEventListener('click', function() {
          closeModal(choiceModal);
        });

        choiceModal.addEventListener('click', function(e) {
          if (e.target === choiceModal) closeModal(choiceModal);
        });

        document.getElementById('newPatientChoice').addEventListener('click', function() {
          closeModal(choiceModal);
          openModal(registrationModal);
          clearRegistrationAutofill();
          const firstNameField = document.getElementById('reg-first-name-display');
          if (firstNameField) firstNameField.focus();
        });

        document.getElementById('existingPatientChoice').addEventListener('click', function() {
          window.location.href = 'login.php?form=1';
        });

        regModalClose.addEventListener('click', function() {
          closeModal(registrationModal);
        });

        registrationModal.addEventListener('click', function(e) {
          if (e.target === registrationModal) closeModal(registrationModal);
        });

        // OTP step elements
        const regOtpSection = document.getElementById('regOtpSection');
        const regOtpEmailDisplay = document.getElementById('regOtpEmailDisplay');
        const regOtpCode = document.getElementById('regOtpCode');
        const regOtpVerifyBtn = document.getElementById('regOtpVerifyBtn');
        const regOtpVerifyBtnText = document.getElementById('regOtpVerifyBtnText');
        const regOtpError = document.getElementById('regOtpError');
        const regOtpResendBtn = document.getElementById('regOtpResendBtn');
        const regOtpTimer = document.getElementById('regOtpTimer');
        const backToRegFormLink = document.getElementById('backToRegFormLink');
        let regOtpCountdown = null;

        function showRegOtpStep(email) {
          regForm.style.display = 'none';
          regOtpSection.style.display = 'block';
          regOtpEmailDisplay.textContent = email;
          regOtpCode.value = '';
          regOtpCode.focus();
          regOtpError.style.display = 'none';
          startRegOtpTimer();
        }

        function hideRegOtpStep() {
          regOtpSection.style.display = 'none';
          regForm.style.display = 'block';
          stopRegOtpTimer();
        }

        function startRegOtpTimer() {
          stopRegOtpTimer();
          let timeLeft = 180;
          regOtpTimer.textContent = '(03:00)';
          regOtpResendBtn.disabled = true;
          regOtpCountdown = setInterval(function() {
            timeLeft--;
            if (timeLeft <= 0) {
              stopRegOtpTimer();
              regOtpTimer.textContent = '(expired)';
              regOtpResendBtn.disabled = false;
              return;
            }
            var m = String(Math.floor(timeLeft / 60)).padStart(2, '0');
            var s = String(timeLeft % 60).padStart(2, '0');
            regOtpTimer.textContent = '(' + m + ':' + s + ')';
          }, 1000);
        }

        function stopRegOtpTimer() {
          if (regOtpCountdown) {
            clearInterval(regOtpCountdown);
            regOtpCountdown = null;
          }
        }

        async function sendRegisterRequest(formData) {
          formData.append('action', 'register');
          const response = await fetch('../user-patient/process_registration.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
          });
          return await response.json();
        }

        const regForm = document.getElementById('registrationForm');
        regForm.addEventListener('submit', async function(e) {
          e.preventDefault();

          const firstDisplay = document.getElementById('reg-first-name-display');
          const middleDisplay = document.getElementById('reg-middle-name-display');
          const lastDisplay = document.getElementById('reg-last-name-display');
          if (firstDisplay && document.getElementById('reg-first-name')) {
            document.getElementById('reg-first-name').value = firstDisplay.value.trim();
          }
          if (middleDisplay && document.getElementById('reg-middle-name')) {
            document.getElementById('reg-middle-name').value = middleDisplay.value.trim();
          }
          if (lastDisplay && document.getElementById('reg-last-name')) {
            document.getElementById('reg-last-name').value = lastDisplay.value.trim();
          }

          const password = document.getElementById('reg-password').value;
          const confirmPassword = document.getElementById('reg-confirm-password').value;
          const phone = document.getElementById('reg-phone').value;
          const gender = document.getElementById('reg-gender').value;
          const email = document.getElementById('reg-email').value;
          const emergencyName = (document.getElementById('reg-emergency-name')?.value || '').trim();
          const emergencyPhone = (document.getElementById('reg-emergency-phone')?.value || '').trim();
          const emergencyRelationship = document.getElementById('reg-emergency-relationship')?.value || '';
          const addressVal = document.getElementById('reg-address') ? document.getElementById('reg-address').value : '';

          if (window.FormSanitize && !window.FormSanitize.alertIfInvalid([
            window.FormSanitize.name(firstDisplay ? firstDisplay.value : '', true, 'First name'),
            window.FormSanitize.name(middleDisplay ? middleDisplay.value : '', false, 'Middle name'),
            window.FormSanitize.name(lastDisplay ? lastDisplay.value : '', true, 'Last name'),
            window.FormSanitize.phone(phone, true),
            window.FormSanitize.email(email, !window.PatientRegistration?.isMobile()),
            window.FormSanitize.address(addressVal, false),
            window.FormSanitize.name(emergencyName, true, 'Emergency contact name'),
            window.FormSanitize.phone(emergencyPhone, true)
          ])) {
            return;
          }

          if (!phone) { alert('Please enter your phone number'); return; }
          if (!gender) { alert('Please select your gender'); return; }
          if (!email && !window.PatientRegistration?.isMobile()) { alert('Please enter your email address'); return; }
          if (!emergencyName) { alert('Please enter the emergency contact name'); return; }
          if (!emergencyPhone) { alert('Please enter the emergency contact number'); return; }
          if (!emergencyRelationship) { alert('Please select the emergency contact relationship'); return; }
          if (password !== confirmPassword) { alert('Passwords do not match'); return; }
          if (!(password.length >= 8 && /[A-Z]/.test(password) && /[a-z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password))) {
            alert('Password must be at least 8 characters and include uppercase, lowercase, number, and symbol');
            return;
          }

          const submitBtn = regForm.querySelector('button[type="submit"]');
          const originalText = submitBtn.textContent;
          submitBtn.disabled = true;
          submitBtn.textContent = 'Sending code...';

          const formData = new FormData(regForm);

          try {
            const result = await sendRegisterRequest(formData);

            if (result.success) {
              if (result.step === 'otp') {
                showRegOtpStep(result.destination || window.PatientRegistration.identifier());
              } else {
                closeModal(registrationModal);
                openModal(successModal);
                setTimeout(function() {
                  window.location.href = '../user-patient/patient-dashboard.php';
                }, 2000);
              }
            } else {
              alert(result.message || 'Registration failed');
              submitBtn.disabled = false;
              submitBtn.textContent = originalText;
            }
          } catch (error) {
            console.error('Registration error:', error);
            alert('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
          }
        });

        // OTP verify handler
        if (regOtpVerifyBtn) {
          regOtpVerifyBtn.addEventListener('click', async function() {
            const code = regOtpCode.value.trim();
            if (!code || code.length !== 6) {
              alert('Please enter the 6-digit verification code.');
              return;
            }

            regOtpVerifyBtn.disabled = true;
            regOtpVerifyBtnText.textContent = 'Verifying...';
            regOtpError.style.display = 'none';

            try {
              const formData = new FormData();
              formData.append('action', 'verify_otp');
              formData.append('otp', code);

              const response = await fetch('../user-patient/process_registration.php', {
                method: 'POST',
                credentials: 'include',
                body: formData
              });

              const result = await response.json();

              if (result.success) {
                stopRegOtpTimer();
                closeModal(registrationModal);
                openModal(successModal);
                setTimeout(function() {
                  window.location.href = result.redirect || '../user-patient/patient-dashboard.php';
                }, 2000);
              } else {
                regOtpError.textContent = result.message || 'Verification failed.';
                regOtpError.style.display = 'block';
                regOtpVerifyBtn.disabled = false;
                regOtpVerifyBtnText.textContent = 'Verify & Create Account';
              }
            } catch (error) {
              console.error('OTP verification error:', error);
              regOtpError.textContent = 'An error occurred. Please try again.';
              regOtpError.style.display = 'block';
              regOtpVerifyBtn.disabled = false;
              regOtpVerifyBtnText.textContent = 'Verify & Create Account';
            }
          });
        }

        if (regOtpCode && regOtpVerifyBtn) {
          regOtpCode.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            if (!regOtpVerifyBtn.disabled) {
              regOtpVerifyBtn.click();
            }
          });
        }

        // Resend handler
        if (regOtpResendBtn) {
          regOtpResendBtn.addEventListener('click', async function() {
            regOtpResendBtn.disabled = true;
            regOtpResendBtn.textContent = 'Sending...';

            const formData = new FormData(regForm);

            try {
              const result = await sendRegisterRequest(formData);
              if (result.success && result.step === 'otp') {
                startRegOtpTimer();
              } else {
                alert(result.message || 'Failed to resend code.');
                regOtpResendBtn.disabled = false;
                regOtpResendBtn.textContent = 'Resend';
              }
            } catch (error) {
              console.error('Resend error:', error);
              alert('Failed to resend code.');
              regOtpResendBtn.disabled = false;
              regOtpResendBtn.textContent = 'Resend';
            }
          });
        }

        // Back to registration form
        if (backToRegFormLink) {
          backToRegFormLink.addEventListener('click', function(e) {
            e.preventDefault();
            hideRegOtpStep();
          });
        }

        const firstNameDisplay = document.getElementById('reg-first-name-display');
        const middleNameDisplay = document.getElementById('reg-middle-name-display');
        const lastNameDisplay = document.getElementById('reg-last-name-display');
        const firstNameHidden = document.getElementById('reg-first-name');
        const middleNameHidden = document.getElementById('reg-middle-name');
        const lastNameHidden = document.getElementById('reg-last-name');

        const capitalizeName = function(name) {
          return name.replace(/\b\w/g, function(l) { return l.toUpperCase(); });
        };

        if (firstNameDisplay && firstNameHidden) {
          firstNameDisplay.addEventListener('input', function() {
            var val = capitalizeName(this.value);
            this.value = val;
            firstNameHidden.value = val;
          });
        }
        if (middleNameDisplay && middleNameHidden) {
          middleNameDisplay.addEventListener('input', function() {
            var val = capitalizeName(this.value);
            this.value = val;
            middleNameHidden.value = val;
          });
        }
        if (lastNameDisplay && lastNameHidden) {
          lastNameDisplay.addEventListener('input', function() {
            var val = capitalizeName(this.value);
            this.value = val;
            lastNameHidden.value = val;
          });
        }

        function bindDigitsOnlyField(field) {
          if (!field) return;
          const sanitize = function() {
            const digitsOnly = field.value.replace(/\D+/g, '');
            if (field.value !== digitsOnly) {
              field.value = digitsOnly;
            }
          };
          field.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            const allowedKeys = [
              'Backspace', 'Delete', 'Tab', 'Escape', 'Enter',
              'ArrowLeft', 'ArrowRight', 'Home', 'End'
            ];
            if (allowedKeys.includes(e.key)) return;
            if (/^[0-9]$/.test(e.key)) return;
            e.preventDefault();
          });
          field.addEventListener('input', sanitize);
          field.addEventListener('paste', function() {
            setTimeout(sanitize, 0);
          });
          field.addEventListener('blur', sanitize);
          sanitize();
        }
        bindDigitsOnlyField(document.getElementById('reg-phone'));
        bindDigitsOnlyField(document.getElementById('reg-emergency-phone'));

        function clearRegistrationAutofill() {
          const form = document.getElementById('registrationForm');
          if (form) form.style.display = 'block';
          const otp = document.getElementById('regOtpSection');
          if (otp) otp.style.display = 'none';
          window.PatientRegistration.showStage('details', false);
          ['reg-email', 'reg-password', 'reg-confirm-password'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.value = '';
          });
          const pwd = document.getElementById('reg-password');
          if (pwd) pwd.dispatchEvent(new Event('input'));
          [50, 300, 800].forEach(function(delay) {
            setTimeout(function() {
              ['reg-email', 'reg-password', 'reg-confirm-password'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.value = '';
              });
              if (pwd) pwd.dispatchEvent(new Event('input'));
            }, delay);
          });
        }

        ['reg-password', 'reg-confirm-password'].forEach(function(id) {
          const el = document.getElementById(id);
          if (!el) return;
          el.addEventListener('focus', function() {
            el.removeAttribute('readonly');
          });
        });

        const regPasswordInput = document.getElementById('reg-password');
        const regConfirmPasswordInput = document.getElementById('reg-confirm-password');

        function getPasswordRuleFlags(password) {
          return {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            symbol: /[^A-Za-z0-9]/.test(password)
          };
        }

        function updatePasswordRequirementChecks(password) {
          const flags = getPasswordRuleFlags(password || '');
          document.querySelectorAll('#passwordRequirementsBox [data-rule]').forEach(function(item) {
            const met = !!flags[item.getAttribute('data-rule')];
            if (item.classList.contains('is-met') === met) return;
            item.classList.toggle('is-met', met);
          });
        }

        function updateConfirmPasswordMatch() {
          const hint = document.getElementById('regPasswordMatchHint');
          const text = hint ? hint.querySelector('.pwd-match-text') : null;
          const password = regPasswordInput ? regPasswordInput.value : '';
          const confirm = regConfirmPasswordInput ? regConfirmPasswordInput.value : '';
          if (!hint || !text || !regConfirmPasswordInput) return;

          hint.classList.remove('is-met', 'is-mismatch', 'is-idle');
          if (!confirm) {
            hint.classList.add('is-idle');
            text.textContent = 'Re-enter password to confirm';
            regConfirmPasswordInput.dataset.match = 'empty';
            return;
          }
          if (password && password === confirm) {
            hint.classList.add('is-met');
            text.textContent = 'Passwords match';
            regConfirmPasswordInput.dataset.match = 'match';
            return;
          }
          hint.classList.add('is-mismatch');
          text.textContent = 'Passwords do not match';
          regConfirmPasswordInput.dataset.match = 'mismatch';
        }

        function getPasswordStrengthState(password) {
          const flags = getPasswordRuleFlags(password);
          const matchedRules = [flags.length, flags.upper, flags.lower, flags.number, flags.symbol].filter(Boolean).length;
          if (!password) return 'empty';
          if (!flags.length || matchedRules <= 2) return 'weak';
          if (matchedRules === 3) return 'medium';
          return 'strong';
        }

        if (regPasswordInput) {
          const updatePasswordUi = function() {
            const value = regPasswordInput.value || '';
            regPasswordInput.dataset.strength = getPasswordStrengthState(value);
            updatePasswordRequirementChecks(value);
            updateConfirmPasswordMatch();
          };
          regPasswordInput.addEventListener('input', updatePasswordUi);
          regPasswordInput.addEventListener('blur', updatePasswordUi);
          updatePasswordUi();
        }
        if (regConfirmPasswordInput) {
          regConfirmPasswordInput.addEventListener('input', updateConfirmPasswordMatch);
          regConfirmPasswordInput.addEventListener('blur', updateConfirmPasswordMatch);
          updateConfirmPasswordMatch();
        }

        function setupPasswordToggle(input, toggle) {
          if (!input || !toggle) return;
          function updateToggleVisibility() {
            toggle.style.display = input.value.length > 0 ? 'flex' : 'none';
          }
          input.addEventListener('input', updateToggleVisibility);
          input.addEventListener('paste', updateToggleVisibility);
          input.addEventListener('keyup', updateToggleVisibility);
          updateToggleVisibility();
          toggle.addEventListener('click', function() {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            const eyeOpen = toggle.querySelector('.eye-open');
            const eyeClosed = toggle.querySelector('.eye-closed');
            if (type === 'password') {
              if (eyeOpen) eyeOpen.style.display = 'block';
              if (eyeClosed) eyeClosed.style.display = 'none';
            } else {
              if (eyeOpen) eyeOpen.style.display = 'none';
              if (eyeClosed) eyeClosed.style.display = 'block';
            }
          });
        }
        setupPasswordToggle(regPasswordInput, document.getElementById('regTogglePassword'));
        setupPasswordToggle(regConfirmPasswordInput, document.getElementById('regToggleConfirmPassword'));

        var emailInput = document.getElementById('reg-email');
        var emailHidden = document.getElementById('reg-email-hidden');
        if (emailInput && emailHidden) {
          emailInput.addEventListener('input', function() {
            emailHidden.value = this.value;
          });
        }
      });
    </script>
    <script src="../../assets/js/discount_id_fields.js"></script>
    <script src="../../assets/js/patient_registration_contact.js"></script>
  </body>
</html>
