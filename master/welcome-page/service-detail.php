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
    $weekdayHours = [];
    $weekendHours = [];
    foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
        if ($hours[$day]['is_open']) $weekdayHours[] = $hours[$day]['open'] . '-' . $hours[$day]['close'];
    }
    foreach (['saturday', 'sunday'] as $day) {
        if ($hours[$day]['is_open']) $weekendHours[] = $hours[$day]['open'] . '-' . $hours[$day]['close'];
    }
    if ($weekdayHours && $weekendHours) {
        return 'Mon - Fri ' . date('g:i A', strtotime($hours['monday']['open'])) . ' - ' . date('g:i A', strtotime($hours['monday']['close'])) . ' | Sat ' . date('g:i A', strtotime($hours['saturday']['open'])) . ' - ' . date('g:i A', strtotime($hours['saturday']['close']));
    } elseif ($weekdayHours) {
        return 'Mon - Fri ' . date('g:i A', strtotime($hours['monday']['open'])) . ' - ' . date('g:i A', strtotime($hours['monday']['close']));
    }
    return 'Mon - Sat 9:00 AM - 5:00 PM';
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
            <a class="btn btn-outline" href="login.php#register">Sign Up</a>
            <a class="btn btn-primary" href="login.php">Login</a>
          </div>
        </nav>
        <div class="header-actions">
          <a class="btn btn-outline" href="login.php#register">Sign Up</a>
          <a class="btn btn-primary" href="login.php">Login</a>
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
          <a class="btn btn-primary btn-lg" href="login.php">Schedule <?= htmlspecialchars($service['name']) ?></a>
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
    </script>
  </body>
</html>
