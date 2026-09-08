<?php
require_once '../../config/database.php';

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
          <p class="modal-intro">Almost done! Complete your information and create a password to secure your account.</p>
          <form id="registrationForm" class="modal-form">
            <input type="hidden" id="reg-first-name" name="first_name" />
            <input type="hidden" id="reg-middle-name" name="middle_name" />
            <input type="hidden" id="reg-last-name" name="last_name" />
            <input type="hidden" id="reg-email-hidden" name="email_hidden" />

            <div class="form-section">
              <h4 class="form-section-title">Patient Information</h4>
              <div class="form-row">
                <div class="form-group">
                  <label for="reg-first-name-display">First Name</label>
                  <input type="text" id="reg-first-name-display" placeholder="First Name" required />
                </div>
                <div class="form-group">
                  <label for="reg-middle-name-display">Middle Name</label>
                  <input type="text" id="reg-middle-name-display" placeholder="Middle Name" />
                </div>
                <div class="form-group">
                  <label for="reg-last-name-display">Last Name</label>
                  <input type="text" id="reg-last-name-display" placeholder="Last Name" required />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="reg-phone">Phone Number</label>
                  <input type="tel" id="reg-phone" name="phone" required placeholder="09123456789" maxlength="11" inputmode="numeric" pattern="[0-9]*" autocomplete="tel" />
                </div>
                <div class="form-group">
                  <label for="reg-gender">Gender</label>
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
                  <input type="date" id="reg-dob" name="date_of_birth" onchange="var d=new Date(this.value);var a=new Date().getFullYear()-d.getFullYear();if(new Date().getMonth()<d.getMonth()||(new Date().getMonth()==d.getMonth()&&new Date().getDate()<d.getDate()))a--;document.getElementById('reg-age').value=a>0&&a<120?a:''" />
                </div>
                <div class="form-group">
                  <label for="reg-age">Age</label>
                  <input type="number" id="reg-age" name="age" min="1" max="120" placeholder="Auto-calculated" readonly style="background-color: #f3f4f6;" />
                </div>
              </div>
              <div class="form-group">
                <label for="reg-address">Address (Optional)</label>
                <input type="text" id="reg-address" name="address" placeholder="Your complete address" />
              </div>
            </div>

            <div class="form-section">
              <h4 class="form-section-title">Account Information</h4>
              <div class="form-group">
                <label for="reg-email">Email (Username)</label>
                <input type="email" id="reg-email" name="email" required placeholder="your@email.com" />
                <div id="reg-email-status" style="display:none; margin-top:6px; font-size:13px; color:#dc3545;"></div>
              </div>
              <div class="form-group">
                <label for="reg-password">Create Password</label>
                <input type="password" id="reg-password" name="password" required minlength="8" placeholder="Password" />
              </div>
              <div class="form-group">
                <label for="reg-confirm-password">Confirm Password</label>
                <input type="password" id="reg-confirm-password" name="confirm_password" required placeholder="Re-enter password" />
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Create Account &amp; Book Appointment</button>
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
          document.getElementById('reg-email').focus();
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

          const password = document.getElementById('reg-password').value;
          const confirmPassword = document.getElementById('reg-confirm-password').value;
          const phone = document.getElementById('reg-phone').value;
          const gender = document.getElementById('reg-gender').value;
          const email = document.getElementById('reg-email').value;

          if (!phone) { alert('Please enter your phone number'); return; }
          if (!gender) { alert('Please select your gender'); return; }
          if (!email) { alert('Please enter your email address'); return; }
          if (password !== confirmPassword) { alert('Passwords do not match'); return; }
          if (password.length < 8) { alert('Password must be at least 8 characters'); return; }

          const submitBtn = regForm.querySelector('button[type="submit"]');
          const originalText = submitBtn.textContent;
          submitBtn.disabled = true;
          submitBtn.textContent = 'Sending code...';

          const formData = new FormData(regForm);

          try {
            const result = await sendRegisterRequest(formData);

            if (result.success) {
              if (result.step === 'otp') {
                showRegOtpStep(email);
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

        var emailInput = document.getElementById('reg-email');
        var emailHidden = document.getElementById('reg-email-hidden');
        if (emailInput && emailHidden) {
          emailInput.addEventListener('input', function() {
            emailHidden.value = this.value;
          });
        }
      });
    </script>
  </body>
</html>
