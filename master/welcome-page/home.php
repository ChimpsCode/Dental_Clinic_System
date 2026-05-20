<?php
require_once '../../config/database.php';

$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$clinicSettings = [];
while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $clinicSettings[$row['setting_key']] = $row['setting_value'];
}
$clinicName = $clinicSettings['clinic_name'] ?? 'RF Dental Clinic';
$clinicAddress = $clinicSettings['clinic_address'] ?? '';
$clinicPhone = $clinicSettings['clinic_phone'] ?? '';
$clinicEmail = $clinicSettings['clinic_email'] ?? '';

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

function formatHoursForDisplay($hours) {
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $shortDays = ['monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun'];
    
    $groups = [];
    $currentGroup = null;

    foreach ($days as $day) {
        if ($hours[$day]['is_open']) {
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
$displayHours = formatHoursForDisplay($businessHours);

// Load welcome content from settings
$welcomeLogo    = $clinicSettings['welcome_logo'] ?? '';
$heroTitleBlocks = isset($clinicSettings['welcome_hero_title_blocks']) ? json_decode($clinicSettings['welcome_hero_title_blocks'], true) : null;
if (!$heroTitleBlocks) {
    $heroTitleBlocks = [['text' => '', 'color' => '', 'fontSize' => 64, 'fontWeight' => 700, 'lineHeight' => 1.05, 'newLine' => true], ['text' => '', 'color' => '#005daa', 'fontSize' => 64, 'fontWeight' => 700, 'lineHeight' => 1.05, 'newLine' => true]];
}
$heroSubtitle   = $clinicSettings['welcome_hero_subtitle'] ?? 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas quam libero, malesuada sed lorem in, sodales dapibus ligula.';
$heroImage      = $clinicSettings['welcome_hero_image'] ?? '';
$heroBadgeBig   = $clinicSettings['welcome_hero_badge_big'] ?? '7+';
$heroBadgeSmall = $clinicSettings['welcome_hero_badge_small'] ?? 'Years Experience';
$heroNote1      = $clinicSettings['welcome_hero_note1'] ?? "Hi I'm Dr. Rex";
$heroNote2      = $clinicSettings['welcome_hero_note2'] ?? "and I'm Dr. Floralyn";
$aboutHeading   = $clinicSettings['welcome_about_heading'] ?? 'About <span class="accent">RF Dental</span>';
$aboutParagraph1 = $clinicSettings['welcome_about_paragraph1'] ?? 'At RF Dental Clinic, we\'re committed to providing exceptional dental care in a comfortable and welcoming environment. Our experienced team uses the latest technology to ensure you receive the best treatment possible.';
$aboutParagraph2 = $clinicSettings['welcome_about_paragraph2'] ?? 'We believe everyone deserves a healthy, beautiful smile. That\'s why we offer comprehensive services ranging from routine checkups to advanced cosmetic procedures, all delivered with personalized care and attention.';
$aboutImage     = $clinicSettings['welcome_about_image'] ?? '';
$aboutStats     = json_decode($clinicSettings['welcome_about_stats'] ?? '', true) ?: [['value' => '5,000+', 'label' => 'Happy Patients'], ['value' => '98%', 'label' => 'Satisfaction Rate']];
$faqs           = json_decode($clinicSettings['welcome_faqs'] ?? '', true) ?: [];
$featuredIds   = json_decode($clinicSettings['welcome_featured_services'] ?? '', true) ?? [];
$serviceImages = json_decode($clinicSettings['welcome_service_images'] ?? '', true) ?? [];
$footerTagline = $clinicSettings['welcome_footer_tagline'] ?? 'Providing exceptional dental care with a commitment to your comfort and health.';
$footerFacebook = $clinicSettings['welcome_footer_facebook'] ?? 'https://www.facebook.com';
$footerMessenger = $clinicSettings['welcome_footer_messenger'] ?? 'https://m.me';

$logoFull = $welcomeLogo ? '../../' . $welcomeLogo : '../../assets/images/Logo.png';
$heroFull = $heroImage ? '../../' . $heroImage : '../../assets/images/Dentists.png';
$aboutFull = $aboutImage ? '../../' . $aboutImage : '../../assets/images/2nd_Anniv.jpg';

// Fetch all active services from DB (excluding featured ones, since they already appear above)
$allServices = [];
if (!empty($featuredIds)) {
    $placeholders = implode(',', array_fill(0, count($featuredIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, duration_minutes, description FROM services WHERE is_active = 1 AND (is_archived = 0 OR is_archived IS NULL) AND id NOT IN ($placeholders) ORDER BY name");
    $stmt->execute($featuredIds);
    $allServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $allServices = $pdo->query("SELECT id, name, price, duration_minutes, description FROM services WHERE is_active = 1 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch featured services (preserving selection order)
$featuredServices = [];
if (!empty($featuredIds)) {
    $placeholders = implode(',', array_fill(0, count($featuredIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, duration_minutes, description FROM services WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($featuredIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $indexed = [];
    foreach ($rows as $r) $indexed[$r['id']] = $r;
    foreach ($featuredIds as $id) {
        if (isset($indexed[$id])) $featuredServices[] = $indexed[$id];
    }
}

$imgAssetsDir = __DIR__ . '/../../assets/images/';
$assets = [
  'logo' => $logoFull,
  'hero' => $heroFull,
  'about' => $aboutFull,
  'tooth' => 'https://www.figma.com/api/mcp/asset/e2ebbe80-7cbc-49ff-afcc-35ac70f56115',
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
    <title><?php echo htmlspecialchars($clinicName); ?></title>
    <link rel="icon" type="image/png" href="../../assets/images/Logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,700;1,400&family=Inria+Sans:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/css/home-styles.css" />
    <link rel="stylesheet" href="../../assets/css/home-integration.css" />
    <script src="../../assets/js/home-integration.js" defer></script>
    <style>
      @keyframes homeFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }

      body {
        opacity: 0;
        animation: homeFadeIn 0.45s ease-out forwards;
      }

      .reveal-item {
        opacity: 0;
        transform: translateY(18px);
        transition: opacity 0.55s ease, transform 0.55s ease;
      }

      .reveal-item.reveal-visible {
        opacity: 1;
        transform: translateY(0);
      }

      .btn,
      .service-card,
      .faq-item,
      .contact-form-card,
      .contact-info-box {
        transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.25s ease, color 0.25s ease;
      }

      /* Featured services grid (three cards) */
      .featured-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 18px;
        align-items: stretch;
      }

      .services-header {
        display: flex;
        gap: 20px;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 25px;
      }

      .services-title .section-title,
      .services-title .section-subtitle {
        text-align: left;
      }

      .services-actions .explore-all-btn {
        white-space: nowrap;
      }

      /* Full services list (hidden by default) */
      .all-services-list {
        margin-top: 24px;
        display: none;
      }

      .all-services-list[aria-hidden="false"] {
        display: block;
      }

      /* Reuse existing services-grid style if present; otherwise define basic layout */
      .services-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-top: 12px;
      }

      .service-card {
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 6px 18px rgba(22,31,45,0.06);
        display: flex;
        flex-direction: column;
        margin: 0; /* ensure no extra outer gap */
        padding: 0; /* remove inherited padding from external stylesheet */
      }

      .service-card img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        display: block;
        margin: 0;
        padding: 0;
        border: 0;
        /* ensure image fills the rounded top corners */
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
      }

      .service-card .service-body {
        padding: 14px 16px;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }

      .service-card .learn-more {
        margin-top: 12px;
        color: #0b5fff;
        font-weight: 600;
        text-decoration: none;
      }

      @media (max-width: 880px) {
        .featured-grid,
        .services-grid {
          grid-template-columns: 1fr;
        }

        .services-header {
          flex-direction: column;
          align-items: stretch;
        }

        .services-actions {
          display: flex;
          justify-content: flex-end;
        }
      }

      .service-feature-card {
        background: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(22, 31, 45, 0.06);
        display: flex;
        flex-direction: column;
      }

      .service-feature-card img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        display: block;
      }

      .service-feature-body {
        padding: 18px;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }

      .service-feature-body .learn-more {
        color: #0b5fff;
        font-weight: 600;
        text-decoration: none;
        margin-top: 12px;
      }
    </style>
  </head>
  <body>
    <div id="top" aria-hidden="true"></div>
      <div class="nav-overlay"></div>
    <header class="site-header">
        <div class="container header-inner">
        <a class="brand" href="#top" aria-label="<?php echo htmlspecialchars($clinicName); ?> home">
          <img class="brand-mark" src="<?php echo htmlspecialchars($logoFull); ?>" alt="" />
          <span class="brand-text"><?php echo htmlspecialchars($clinicName); ?></span>
        </a>

        <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
          <span class="nav-toggle-bar"></span>
          <span class="nav-toggle-bar"></span>
          <span class="nav-toggle-bar"></span>
        </button>

        <nav class="nav" aria-label="Primary">
          <a href="#top" class="nav-link">Home</a>
          <a href="#about" class="nav-link">About Us</a>
          <a href="#services" class="nav-link">Services</a>
          <a href="#faq" class="nav-link">FAQ</a>
          <a href="#contact" class="nav-link">Contact Us</a>
          <div class="nav-actions">
            <a class="btn btn-outline" href="login.php#register">Sign Up</a>
            <a class="btn btn-primary" id="headerLoginBtn" href="login.php">Login</a>
          </div>
        </nav>

        <div class="header-actions">
          <a class="btn btn-outline" href="login.php#register">Sign Up</a>
          <a class="btn btn-primary" id="headerLoginBtn" href="login.php">Login</a>
        </div>
      </div>
    </header>

    <main id="home">
      <section class="hero">
        <div class="hero-bg"></div>
        <div class="hero-gradient"></div>
        <div class="container hero-inner">
          <div class="hero-copy">
            <h1 class="hero-title">
              <?php foreach ($heroTitleBlocks as $i => $block): ?>
                <?php $isNewLine = isset($block['newLine']) ? $block['newLine'] : true; ?>
                <?php if ($i > 0 && $isNewLine): ?><br><?php endif; ?>
                <span style="color:<?php echo htmlspecialchars($block['color'] ?: 'inherit'); ?>;font-size:<?php echo (float)($block['fontSize'] ?? 64); ?>px;font-weight:<?php echo (int)($block['fontWeight'] ?? 700); ?>;line-height:<?php echo (float)($block['lineHeight'] ?? 1.05); ?>"><?php echo htmlspecialchars($block['text'] ?? ''); ?></span>
              <?php endforeach; ?>
            </h1>
            <p class="hero-subtitle">
              <?php echo htmlspecialchars($heroSubtitle); ?>
            </p>

            <div class="hero-ctas">
              <a class="btn btn-primary btn-lg" href="#services">Explore Services</a>
              <a class="btn btn-outline btn-lg" href="login.php#register">Sign Up</a>
            </div>

            <div class="appointment-card" aria-label="Book an appointment">
              <div class="appointment-title">Book an Appointment Today</div>
              <form class="appointment-form" action="#" method="post">
                <label class="field">
                  <span class="sr-only">First Name</span>
                  <input name="first_name" type="text" placeholder="First Name" required />
                </label>
                <label class="field">
                  <span class="sr-only">Middle Name</span>
                  <input name="middle_name" type="text" placeholder="Middle Name" />
                </label>
                <label class="field">
                  <span class="sr-only">Last Name</span>
                  <input name="last_name" type="text" placeholder="Last Name" required />
                </label>
                <label class="field">
                  <span class="sr-only">Email Address</span>
                  <input name="email" type="email" placeholder="Email Address" required />
                </label>
                <button class="btn btn-primary" type="submit">Book Now</button>
              </form>
            </div>
          </div>

          <div class="hero-visual">
            <div class="hero-photo-wrap">
              <img class="hero-photo" src="<?= htmlspecialchars($assets['hero']) ?>" alt="Dentists from RF Dental Clinic" />

              <div class="hero-note hero-note-1">
                <div class="hero-note-text"><?php echo htmlspecialchars($heroNote1); ?></div>
              </div>
              <div class="hero-note hero-note-2">
                <div class="hero-note-text"><?php echo htmlspecialchars($heroNote2); ?></div>
              </div>
              <div class="hero-badge">
                <div class="hero-badge-big"><?php echo htmlspecialchars($heroBadgeBig); ?></div>
                <div class="hero-badge-small"><?php echo htmlspecialchars($heroBadgeSmall); ?></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="about" class="section about" style="padding-bottom:50px">
        <div class="container about-inner">
          <div class="about-photo">
            <img src="<?= htmlspecialchars($assets['about']) ?>" alt="RF Dental Clinic team in consultation" />
            <div class="about-badge">
              <div class="about-badge-top">2+</div>
              <div class="about-badge-bottom">YEARS<br />AND<br />COUNTING</div>
            </div>
          </div>

          <div class="about-copy">
            <h2 class="section-title">
              <?php echo $aboutHeading; ?>
            </h2>

            <p class="about-text">
              <?php echo htmlspecialchars($aboutParagraph1); ?>
            </p>
            <p class="about-text">
              <?php echo htmlspecialchars($aboutParagraph2); ?>
            </p>

            <div class="about-stats">
              <?php foreach ($aboutStats as $stat): ?>
              <div class="stat">
                <div class="stat-value accent"><?php echo htmlspecialchars($stat['value'] ?? ''); ?></div>
                <div class="stat-label"><?php echo htmlspecialchars($stat['label'] ?? ''); ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      <section id="services" class="section services" style="padding-top:30px">
        <div class="container">
          <div class="services-header">
            <div class="services-title">
              <h2 class="section-title">
                Our <span class="accent">Services</span>
              </h2>
              <p class="section-subtitle">
                Comprehensive dental care tailored to your needs, delivered with expertise and compassion.
              </p>
            </div>

            <div class="services-actions">
              <a id="exploreAllBtn" class="btn btn-primary explore-all-btn" href="#allServices">Explore All Services</a>
            </div>
          </div>

          <div class="featured-grid" aria-label="Featured services">
            <?php foreach ($featuredServices as $f): ?>
              <?php
                $customImg = $serviceImages[$f['id']] ?? '';
                $fimg = ($customImg && file_exists(__DIR__ . '/../../' . $customImg)) ? '../../' . $customImg : $logoFull;
              ?>
              <article class="service-feature-card">
                <img src="<?= htmlspecialchars($fimg) ?>" alt="<?= htmlspecialchars($f['name']) ?>" />
                <div class="service-feature-body">
                  <div>
                    <h3 class="service-title"><?= htmlspecialchars($f['name']) ?></h3>
                    <p class="service-desc"><?= htmlspecialchars($f['description'] ?? '') ?></p>
                  </div>
                  <div>
                    <div class="service-meta">
                      <span class="service-price">₱<?= number_format((float)$f['price'], 2) ?></span>
                      <span class="service-dot" aria-hidden="true">•</span>
                      <span class="service-duration"><?= (int)$f['duration_minutes'] ?> min</span>
                    </div>
                    <a class="learn-more" href="service-detail.php?id=<?= $f['id'] ?>">Learn more →</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <div id="allServices" class="all-services-list" aria-hidden="true">
            <div class="services-grid">
              <?php foreach ($allServices as $svc):
                  $customImg = $serviceImages[$svc['id']] ?? '';
                  if ($customImg && file_exists(__DIR__ . '/../../' . $customImg)) {
                    $simg = '../../' . $customImg;
                  } else {
                    $simg = $logoFull;
                  }
              ?>
                <article class="service-card">
                  <img src="<?= htmlspecialchars($simg) ?>" alt="<?= htmlspecialchars($svc['name']) ?>" />
                  <div class="service-body">
                    <div>
                      <h3 class="service-title"><?= htmlspecialchars($svc['name']) ?></h3>
                      <p class="service-desc"><?= htmlspecialchars($svc['description'] ?? '') ?></p>
                    </div>
                    <div>
                      <div class="service-meta">
                        <span class="service-price">₱<?= number_format((float)$svc['price'], 2) ?></span>
                        <span class="service-dot" aria-hidden="true">•</span>
                        <span class="service-duration"><?= (int)$svc['duration_minutes'] ?> min</span>
                      </div>
                      <a class="learn-more" href="service-detail.php?id=<?= $svc['id'] ?>">Learn more →</a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      <section id="faq" class="section faq" style="padding-bottom: 88px;">
        <div class="container">
          <h2 class="section-title center">
            Frequently Asked <span class="accent">Questions</span>
          </h2>
          <p class="section-subtitle center">
            Find answers to common questions about our dental services and appointments.
          </p>

          <div class="faq-list" role="list">
            <?php foreach ($faqs as $faq): ?>
              <details class="faq-item">
                <summary class="faq-summary">
                  <span class="faq-q"><?= htmlspecialchars($faq['q'] ?? '') ?></span>
                  <span class="faq-chevron" aria-hidden="true"></span>
                </summary>
                    <div class="faq-a">
                      <p><?= nl2br(htmlspecialchars($faq['a'] ?? '')) ?></p>
                    </div>
              </details>
            <?php endforeach; ?>
          </div>

          <div class="faq-cta">
            <div class="faq-cta-text">Still have questions?</div>
            <a class="btn btn-primary btn-lg" href="#contact">Inquire now</a>
          </div>
        </div>
      </section>

      <section id="contact" class="section contact" style="padding-top:10px">
        <div class="container">
          <h2 class="section-title center">
            <span class="accent">Contact</span> Us
          </h2>
          <p class="contact-intro center">
            Ready to take the first step toward a healthier smile? Schedule your appointment today and experience the RF Dental difference.
          </p>

          <div class="contact-wrapper">
            <div class="contact-info-box">
              <h3 class="contact-info-title">Contact Info</h3>
              <div class="contact-meta">
                <div class="meta-row">
                  <img src="<?= htmlspecialchars($assets['icon_phone']) ?>" alt="" class="meta-icon" />
                  <span><?php echo htmlspecialchars($clinicPhone ?: '0967 187 8603'); ?></span>
                </div>
                <div class="meta-row">
                  <img src="<?= htmlspecialchars($assets['icon_email']) ?>" alt="" class="meta-icon" />
                  <a href="mailto:<?php echo htmlspecialchars($clinicEmail ?: 'rfdc2023@gmail.com'); ?>"><?php echo htmlspecialchars($clinicEmail ?: 'rfdc2023@gmail.com'); ?></a>
                </div>
                <div class="meta-row">
                  <img src="<?= htmlspecialchars($assets['icon_time']) ?>" alt="" class="meta-icon" />
                  <span><?php echo htmlspecialchars($displayHours); ?></span>
                </div>
              </div>

              <div class="contact-map" id="clinicMap">
                <div class="contact-map-preview">
                  <iframe
                    src="https://www.google.com/maps?q=<?= urlencode($clinicAddress ?: 'Celeste Bldg. MaxSuniel St. Brgy. Carmen, Cagayan de Oro, Philippines, 9000') ?>&output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Clinic location map">
                  </iframe>
                  <div class="contact-map-click-area" tabindex="0" role="button" aria-label="Open map in larger view">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="11" cy="11" r="8"></circle>
                      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                      <line x1="11" y1="8" x2="11" y2="14"></line>
                      <line x1="8" y1="11" x2="14" y2="11"></line>
                    </svg>
                    <span>Click to enlarge</span>
                  </div>
                </div>
              </div>

            </div>
            <div class="contact-form-card">
              <div class="form-logo" aria-hidden="true">
                <img src="<?php echo htmlspecialchars($logoFull); ?>" alt="" />
              </div>
              <form class="contact-form" action="#" method="post">
                <div class="form-row">
                  <label class="form-field">
                    <span class="form-label">First Name</span>
                    <input type="text" name="first_name" placeholder="Juan" />
                  </label>
                </div>
                <div class="form-row">
                  <label class="form-field">
                    <span class="form-label">Middle Name</span>
                    <input type="text" name="middle_name" placeholder="Realina" />
                  </label>
                </div>
                <div class="form-row">
                  <label class="form-field">
                    <span class="form-label">Last Name</span>
                    <input type="text" name="last_name" placeholder="Dela Cruz" />
                  </label>
                </div>
                <div class="form-row form-row-split">
                  <label class="form-field">
                    <span class="form-label">Email</span>
                    <input type="email" name="email" placeholder="example@gmail.com" />
                  </label>
                  <label class="form-field">
                    <span class="form-label">Phone Number</span>
                    <input type="tel" name="phone" placeholder="09123456789" />
                  </label>
                </div>
                <div class="form-row">
                  <label class="form-field">
                    <span class="form-label">Message</span>
                    <textarea name="message" rows="4" placeholder="Your message..."></textarea>
                  </label>
                </div>
                <div class="form-actions">
                  <button class="btn btn-primary" type="submit">Submit</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="container footer-inner">
        <div class="footer-brand">
          <div class="footer-brand-top">
            <img class="footer-logo" src="<?php echo htmlspecialchars($logoFull); ?>" alt="" />
            <div class="footer-brand-title"><?php echo htmlspecialchars($clinicName); ?></div>
          </div>
          <p class="footer-text"><?php echo htmlspecialchars($footerTagline); ?></p>
        </div>

        <div class="footer-col">
          <div class="footer-heading">Quick Links</div>
          <a class="footer-link" href="#top">Home</a>
          <a class="footer-link" href="#about">About Us</a>
          <a class="footer-link" href="#services">Services</a>
          <a class="footer-link" href="#contact">Contact</a>
        </div>

        <div class="footer-col">
          <div class="footer-heading">Services</div>
          <?php
          $footerServices = $pdo->query("SELECT id, name FROM services WHERE is_active = 1 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY name LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
          ?>
          <?php foreach ($footerServices as $fs): ?>
            <a class="footer-link" href="service-detail.php?id=<?= (int)$fs['id'] ?>"><?php echo htmlspecialchars($fs['name']); ?></a>
          <?php endforeach; ?>
        </div>

        <div class="footer-col footer-connect">
          <div class="footer-heading">Connect With Us</div>
          <div class="footer-icons" aria-label="Social and contact links">
            <a href="<?php echo htmlspecialchars($footerFacebook); ?>" target="_blank" rel="noreferrer" class="footer-icon-link" aria-label="Facebook">
              <span class="footer-icon footer-icon-fb" aria-hidden="true">f</span>
            </a>
            <a href="mailto:<?php echo htmlspecialchars($clinicEmail ?: 'rfdc2023@gmail.com'); ?>" class="footer-icon-link" aria-label="Email">
              <span class="footer-icon footer-icon-mail" aria-hidden="true">✉</span>
            </a>
            <a href="<?php echo htmlspecialchars($footerMessenger); ?>" target="_blank" rel="noreferrer" class="footer-icon-link" aria-label="Facebook Messenger">
              <img src="../../assets/images/icon-messenger.svg" alt="" class="footer-icon-img" aria-hidden="true" />
            </a>
            <a href="tel:<?php echo htmlspecialchars(str_replace([' ', '-', '(', ')'], '', $clinicPhone ?: '09671878603')); ?>" class="footer-icon-link" aria-label="Call">
              <span class="footer-icon footer-icon-phone" aria-hidden="true">☎</span>
            </a>
          </div>
          <div class="footer-location">
            <span class="footer-location-icon" aria-hidden="true">📍</span>
            <span class="footer-text small">
              <?php echo htmlspecialchars($clinicAddress) ?: 'Celeste Bldg. MaxSuniel St. Brgy. Carmen, Cagayan de Oro, Philippines, 9000'; ?>
            </span>
          </div>
        </div>
      </div>
    </footer>

    <?php include '../../includes/chatbot_widget.php'; ?>

    <script>
      // Toggle full services list when Explore All Services is clicked
      (function() {
        const btn = document.getElementById('exploreAllBtn');
        const all = document.getElementById('allServices');
        if (!btn || !all) return;

        btn.addEventListener('click', function(e) {
          e.preventDefault();
          const isOpen = all.getAttribute('aria-hidden') === 'false';
          if (isOpen) {
            all.setAttribute('aria-hidden', 'true');
            btn.textContent = 'Explore All Services';
          } else {
            all.setAttribute('aria-hidden', 'false');
            btn.textContent = 'Hide Services';
            // scroll into view
            setTimeout(() => all.scrollIntoView({ behavior: 'smooth', block: 'start' }), 120);
          }
        });
      })();
    </script>

    <!-- Registration Modal for Lead Capture Flow -->
    <div id="registrationModal" class="modal-overlay" style="display: none;">
      <div class="modal-content registration-modal">
        <div class="modal-header">
          <h3>Secure Your Appointment</h3>
          <button type="button" class="modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
          <p class="modal-intro">Almost done! Complete your information and create a password to secure your account.</p>
          <form id="registrationForm" class="modal-form">
            <!-- Hidden fields from initial form -->
            <input type="hidden" id="reg-first-name" name="first_name" />
            <input type="hidden" id="reg-middle-name" name="middle_name" />
            <input type="hidden" id="reg-last-name" name="last_name" />
            <input type="hidden" id="reg-email-hidden" name="email_hidden" />
            
            <!-- Patient Information Section -->
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
                  <input type="tel" id="reg-phone" name="phone" required placeholder="09123456789" maxlength="11" />
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
            
            <!-- Account Information Section -->
            <div class="form-section">
              <h4 class="form-section-title">Account Information</h4>
              <div class="form-group">
                <label for="reg-email">Email (Username)</label>
                <input type="email" id="reg-email" name="email" required placeholder="your@email.com" />
              </div>
              <div class="form-group">
                <label for="reg-password">Create Password</label>
                <input type="password" id="reg-password" name="password" required minlength="8" placeholder="At least 8 characters" />
              </div>
              <div class="form-group">
                <label for="reg-confirm-password">Confirm Password</label>
                <input type="password" id="reg-confirm-password" name="confirm_password" required placeholder="Re-enter password" />
              </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Create Account & Book Appointment</button>
          </form>
          <div class="modal-footer-text">
            <p>By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Registration Success Modal -->
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

    <!-- Welcome Back Modal -->
    <div id="welcomeBackModal" class="modal-overlay" style="display: none;">
      <div class="modal-content welcome-back-modal">
        <div class="welcome-back-logo">
          <img src="<?php echo htmlspecialchars($logoFull); ?>" alt="Dental Clinic Logo" />
        </div>
        <h2 class="welcome-back-title">Welcome Back, Patient!</h2>
        <p class="welcome-back-message" id="welcomeBackMessage">
          An account for <strong id="welcomeBackUserEmail">patient@email.com</strong> was found. Please enter your password to continue your booking.
        </p>
        <form id="welcomeBackForm" class="welcome-back-form">
          <div class="password-input-group">
            <input 
              type="password" 
              id="welcome-back-password" 
              name="password" 
              required 
              minlength="8" 
              placeholder="Type your Password..." 
              class="password-input"
            />
            <button type="button" class="toggle-password" id="welcomeBackTogglePassword" aria-label="Toggle password visibility">
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
          <div class="captcha-container">
            <div class="captcha-wrapper">
              <div id="captchaDisplay" class="captcha-display"></div>
              <button type="button" class="captcha-refresh" id="refreshCaptchaBtn" aria-label="Refresh CAPTCHA">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                </svg>
              </button>
            </div>
            <input type="text" id="captcha-code" name="captcha_code" required placeholder="Enter CAPTCHA code" class="captcha-input" autocomplete="off" />
          </div>
          <div class="captcha-error" id="captchaError" style="display: none; color: #dc3545; font-size: 13px; margin-bottom: 10px; text-align: center;"></div>
          <input type="hidden" id="welcome-back-email" name="email" />
          <input type="hidden" id="welcome-back-name" name="name" />
          <input type="hidden" id="welcome-back-phone" name="phone" />
          <input type="hidden" id="welcome-back-appointment-date" name="appointment_date" />
          <button type="submit" class="btn btn-primary welcome-back-btn">Verify & Book</button>
        </form>
        <a href="./reset_password.php" class="forgot-password-link">Forgot Password?</a>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const appointmentForm = document.querySelector('.appointment-form');
        const registrationModal = document.getElementById('registrationModal');
        const successModal = document.getElementById('successModal');
        const registrationForm = document.getElementById('registrationForm');
        const modalClose = document.querySelector('.modal-close');
        const body = document.body;

        // Step 1: Handle initial appointment form submission
        if (appointmentForm) {
          appointmentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Collect form data
            const formData = new FormData(appointmentForm);
            const firstName = formData.get('first_name');
            const middleName = formData.get('middle_name');
            const lastName = formData.get('last_name');
            const email = formData.get('email');

            // Validate required fields
            if (!firstName || !lastName || !email) {
              alert('Please fill in all required fields (First Name, Last Name, Email)');
              return;
            }

            // Check if user already has an account
            try {
              const checkData = { exists: false };
              
              try {
                const checkResponse = await fetch('../user-patient/check_user_exists.php', {
                  method: 'POST',
                  headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                  },
                  body: formData
                });
                const checkResult = await checkResponse.json();
                if (checkResult && typeof checkResult === 'object' && 'exists' in checkResult) {
                  checkData.exists = checkResult.exists;
                }
              } catch (checkErr) {
                console.log('Check user endpoint not available, proceeding with registration');
              }
              
              if (checkData.exists) {
                // User exists - show welcome back modal
                const fullName = middleName ? firstName + ' ' + middleName + ' ' + lastName : firstName + ' ' + lastName;
                document.getElementById('welcome-back-email').value = email;
                document.getElementById('welcome-back-name').value = fullName;
                document.getElementById('welcomeBackUserEmail').textContent = email;
                
                document.getElementById('welcomeBackModal').style.display = 'flex';
                body.style.overflow = 'hidden';
                
                // Clear and regenerate CAPTCHA
                document.getElementById('captcha-code').value = '';
                document.getElementById('captchaError').style.display = 'none';
                generateCaptcha();
              } else {
                // User doesn't exist - show registration modal
                // Pre-fill the modal fields (capitalize names)
                const capitalizeName = (name) => {
                  return name.replace(/\b\w/g, l => l.toUpperCase());
                };
                
                document.getElementById('reg-first-name').value = capitalizeName(firstName);
                document.getElementById('reg-middle-name').value = middleName ? capitalizeName(middleName) : '';
                document.getElementById('reg-last-name').value = capitalizeName(lastName);
                document.getElementById('reg-email').value = email;
                document.getElementById('reg-email-hidden').value = email;
                
                // Also fill display fields
                document.getElementById('reg-first-name-display').value = capitalizeName(firstName);
                document.getElementById('reg-middle-name-display').value = middleName ? capitalizeName(middleName) : '';
                document.getElementById('reg-last-name-display').value = capitalizeName(lastName);
                
                registrationModal.style.display = 'flex';
                body.style.overflow = 'hidden';
              }
            } catch (error) {
              console.error('Error:', error);
              // Fallback to registration modal
              const capitalizeName = (name) => {
                return name.replace(/\b\w/g, l => l.toUpperCase());
              };
              
              document.getElementById('reg-first-name').value = capitalizeName(firstName);
              document.getElementById('reg-middle-name').value = middleName ? capitalizeName(middleName) : '';
              document.getElementById('reg-last-name').value = capitalizeName(lastName);
              document.getElementById('reg-email').value = email;
              document.getElementById('reg-email-hidden').value = email;
              
              // Also fill display fields
              document.getElementById('reg-first-name-display').value = capitalizeName(firstName);
              document.getElementById('reg-middle-name-display').value = middleName ? capitalizeName(middleName) : '';
              document.getElementById('reg-last-name-display').value = capitalizeName(lastName);
              
              registrationModal.style.display = 'flex';
              body.style.overflow = 'hidden';
            }
          });
        }

        // Close modal handlers
        if (modalClose) {
          modalClose.addEventListener('click', function() {
            registrationModal.style.display = 'none';
            body.style.overflow = '';
          });
        }

        registrationModal.addEventListener('click', function(e) {
          if (e.target === registrationModal) {
            registrationModal.style.display = 'none';
            body.style.overflow = '';
          }
        });

        // Step 2: Handle registration form submission
        if (registrationForm) {
          registrationForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const password = document.getElementById('reg-password').value;
            const confirmPassword = document.getElementById('reg-confirm-password').value;
            const phone = document.getElementById('reg-phone').value;
            const gender = document.getElementById('reg-gender').value;
            const email = document.getElementById('reg-email').value;

            // Validate required fields
            if (!phone) {
              alert('Please enter your phone number');
              return;
            }

            if (!gender) {
              alert('Please select your gender');
              return;
            }

            if (!email) {
              alert('Please enter your email address');
              return;
            }

            if (password !== confirmPassword) {
              alert('Passwords do not match');
              return;
            }

            if (password.length < 8) {
              alert('Password must be at least 8 characters');
              return;
            }

            const formData = new FormData(registrationForm);

            try {
              const response = await fetch('../user-patient/process_registration.php', {
                method: 'POST',
                body: formData
              });

              const result = await response.json();

              if (result.success) {
                // Hide registration modal and show success
                registrationModal.style.display = 'none';
                successModal.style.display = 'flex';

                // Step 3: Redirect to dashboard after delay
                setTimeout(() => {
                  window.location.href = '../user-patient/patient-dashboard.php';
                }, 2000);
              } else {
                alert(result.message || 'Registration failed');
              }
            } catch (error) {
              console.error('Registration error:', error);
              alert('An error occurred. Please try again.');
            }
          });
        }

        // Welcome Back Modal handlers
        const welcomeBackModal = document.getElementById('welcomeBackModal');
        const welcomeBackForm = document.getElementById('welcomeBackForm');
        const welcomeBackCloseBtn = document.querySelector('#welcomeBackModal .modal-close');
        
        if (welcomeBackCloseBtn) {
          welcomeBackCloseBtn.addEventListener('click', function() {
            welcomeBackModal.style.display = 'none';
            body.style.overflow = '';
          });
        }
        
        if (welcomeBackModal) {
          welcomeBackModal.addEventListener('click', function(e) {
            if (e.target === welcomeBackModal) {
              welcomeBackModal.style.display = 'none';
              body.style.overflow = '';
            }
          });
        }
        
        if (welcomeBackForm) {
          welcomeBackForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('welcome-back-password').value;
            const email = document.getElementById('welcome-back-email').value;
            
            if (!password || password.length < 8) {
              alert('Password must be at least 8 characters');
              return;
            }
            
            // Disable button during submission
            const submitBtn = welcomeBackForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Verifying...';
            
            // Try to login (skip captcha since patient identity already verified via appointment details)
            const loginFormData = new FormData();
            loginFormData.append('username', email);
            loginFormData.append('password', password);
            loginFormData.append('skip_captcha', '1');
            
            try {
              const response = await fetch('./login.php', {
                method: 'POST',
                headers: {
                  'X-Requested-With': 'XMLHttpRequest'
                },
                body: loginFormData
              });

              const result = await response.json();

              if (result.success) {
                // Redirect based on role
                if (result.role === 'admin') {
                  window.location.href = '../admin/admin_dashboard.php';
                } else if (result.role === 'staff') {
                  window.location.href = '../staff/staff-dashboard.php';
                } else if (result.role === 'dentist') {
                  window.location.href = '../dentist/dentist_dashboard.php';
                } else if (result.role === 'user') {
                  window.location.href = '../user-patient/patient-dashboard.php';
                } else {
                  window.location.href = '../dashboard.php';
                }
              } else {
                // Login failed - show error and re-enable button
                alert(result.message || 'Login failed. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
              }
            } catch (error) {
              console.error('Login error:', error);
              alert('An error occurred. Please try again.');
              submitBtn.disabled = false;
              submitBtn.textContent = originalText;
            }
          });
        }

        const captchaDisplay = document.getElementById('captchaDisplay');
        const captchaInput = document.getElementById('captcha-code');
        const captchaError = document.getElementById('captchaError');
        const refreshCaptchaBtn = document.getElementById('refreshCaptchaBtn');
        const captchaCanvas = document.getElementById('captchaCanvas'); // Ensure this is selected if it exists
        
        let currentCaptchaCode = '';
        
        function generateCaptcha() {
          const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
          let code = '';
          for (let i = 0; i < 6; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
          }
          currentCaptchaCode = code;
          if (captchaDisplay) {
            captchaDisplay.textContent = code;
          }
          if (captchaInput) {
            captchaInput.value = '';
            captchaInput.focus();
          }
        }
        
        function refreshCaptcha() {
          generateCaptcha();
        }
        
        if (captchaDisplay) {
          generateCaptcha();
        }
        
        if (refreshCaptchaBtn) {
          refreshCaptchaBtn.addEventListener('click', function() {
            refreshCaptcha();
          });
        }
        
        if (captchaDisplay) {
          captchaDisplay.addEventListener('click', function() {
            refreshCaptcha();
          });
        }
        
        if (captchaCanvas) {
          captchaCanvas.addEventListener('click', function() {
            refreshCaptcha();
          });
        }

        // Navigation active state handling
        const navLinks = document.querySelectorAll('.nav-link');
        
        // Set initial active state based on hash
        function setActiveLink() {
          const hash = window.location.hash || '#top';
          navLinks.forEach(link => {
            if (link.getAttribute('href') === hash) {
              link.classList.add('active');
            } else {
              link.classList.remove('active');
            }
          });
        }
        
        // Add click event to nav links
        navLinks.forEach(link => {
          link.addEventListener('click', function(e) {
            navLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
          });
});
        
        // Global function to calculate age
        window.handleBirthdateChange = function() {
            const birthdateInput = document.getElementById('reg-dob');
            const ageInput = document.getElementById('reg-age');
            
            if (birthdateInput && ageInput && birthdateInput.value) {
                const birthDate = new Date(birthdateInput.value);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                if (age >= 0 && age < 120) {
                    ageInput.value = age;
                }
            }
        };

        // Set active state on page load and hash change
        setActiveLink();
        window.addEventListener('hashchange', setActiveLink);

        // Sync display fields with hidden fields (capitalize on input)
        const capitalizeName = (name) => {
          return name.replace(/\b\w/g, l => l.toUpperCase());
        };

        const firstNameDisplay = document.getElementById('reg-first-name-display');
        const middleNameDisplay = document.getElementById('reg-middle-name-display');
        const lastNameDisplay = document.getElementById('reg-last-name-display');
        const firstNameHidden = document.getElementById('reg-first-name');
        const middleNameHidden = document.getElementById('reg-middle-name');
        const lastNameHidden = document.getElementById('reg-last-name');

        if (firstNameDisplay && firstNameHidden) {
          firstNameDisplay.addEventListener('input', function() {
            const capitalized = capitalizeName(this.value);
            this.value = capitalized;
            firstNameHidden.value = capitalized;
          });
        }
        if (middleNameDisplay && middleNameHidden) {
          middleNameDisplay.addEventListener('input', function() {
            const capitalized = capitalizeName(this.value);
            this.value = capitalized;
            middleNameHidden.value = capitalized;
          });
        }
        if (lastNameDisplay && lastNameHidden) {
          lastNameDisplay.addEventListener('input', function() {
            const capitalized = capitalizeName(this.value);
            this.value = capitalized;
            lastNameHidden.value = capitalized;
          });
        }
      });
    </script>

    <style>
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
      }
      
      .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 520px;
        max-height: 90vh;
        padding: 0;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalSlideIn 0.3s ease;
        overflow: hidden;
        display: flex;
        flex-direction: column;
      }
      
      .success-modal {
        text-align: center;
        padding: 40px 30px;
        overflow-y: auto;
        max-height: 90vh;
      }
      
      @keyframes modalSlideIn {
        from {
          transform: translateY(-20px);
          opacity: 0;
        }
        to {
          transform: translateY(0);
          opacity: 1;
        }
      }
      
      .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
      }
      
      .modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
      }
      
      .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
        padding: 0;
        line-height: 1;
      }
      
      .modal-close:hover {
        color: #333;
      }
      
      .modal-body {
        padding: 24px;
        max-height: calc(90vh - 80px);
        overflow-y: auto;
        overflow-x: hidden;
        flex: 1;
      }
      
      .modal-intro {
        color: #666;
        margin-bottom: 20px;
        font-size: 14px;
      }
      
      .modal-form .form-group {
        margin-bottom: 16px;
      }
      
      .modal-form label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        font-size: 14px;
      }
      
      .modal-form input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
      }
      
      .modal-form input:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
      }
      
      .btn-block {
        width: 100%;
        margin-top: 8px;
      }
      
      .modal-footer-text {
        margin-top: 16px;
        text-align: center;
        font-size: 12px;
        color: #666;
      }
      
      .modal-footer-text a {
        color: #007bff;
        text-decoration: none;
      }
      
      .modal-footer-text a:hover {
        text-decoration: underline;
      }
      
      /* Registration Modal Extended Styles */
      .registration-modal {
        max-width: 520px;
      }
      
      .form-section {
        margin-bottom: 24px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
      }
      
      .form-section:last-of-type {
        border-bottom: none;
        margin-bottom: 16px;
      }
      
      .form-section-title {
        font-size: 15px;
        font-weight: 600;
        color: #0059a3;
        margin: 0 0 16px 0;
        padding-bottom: 8px;
        border-bottom: 2px solid #0059a3;
        display: inline-block;
      }
      
      .modal-form .form-row {
        display: flex;
        gap: 12px;
      }
      
      .modal-form .form-row .form-group {
        flex: 1;
      }
      
      .modal-form select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        cursor: pointer;
        box-sizing: border-box;
      }
      
      .modal-form select:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
      }
      
      .success-icon {
        color: #28a745;
        margin-bottom: 16px;
      }
      
      .success-icon svg {
        display: block;
        margin: 0 auto;
      }
      
      /* Welcome Back Modal Styles */
      .welcome-back-modal {
        padding: 40px;
        max-width: 420px;
        width: 100%;
        text-align: center;
        border-radius: 12px;
      }
      
      .welcome-back-logo {
        display: flex;
        justify-content: center;
        margin-bottom: 24px;
      }
      
      .welcome-back-logo img {
        width: 60px;
        height: auto;
      }
      
      .welcome-back-title {
        font-size: 24px;
        font-weight: 600;
        color: #0059a3;
        margin: 0 0 16px;
      }
      
      .welcome-back-message {
        font-size: 14px;
        color: #555;
        line-height: 1.5;
        margin: 0 0 24px;
      }
      
      .welcome-back-message strong {
        color: #333;
        font-weight: 600;
      }
      
      .welcome-back-form {
        margin-bottom: 20px;
      }
      
      .password-input-group {
        position: relative;
        margin-bottom: 20px;
      }
      
      .password-input {
        width: 100%;
        border: none;
        border-bottom: 1px solid #d1d5db;
        padding: 12px 40px 12px 0;
        font-size: 14px;
        background: transparent;
        transition: border-color 0.2s;
      }
      
      .password-input::placeholder {
        color: #999;
      }
      
      .password-input:focus {
        outline: none;
        border-bottom-color: #0059a3;
      }
      
      .toggle-password {
        position: absolute;
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        color: #999;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      
      .toggle-password:hover {
        color: #0059a3;
      }
      
      .eye-icon {
        width: 20px;
        height: 20px;
      }
      
      .welcome-back-btn {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 6px;
      }
      
      .forgot-password-link {
        display: block;
        color: #999;
        text-decoration: none;
        font-size: 13px;
        transition: color 0.2s;
      }
      
      .forgot-password-link:hover {
        color: #0059a3;
        text-decoration: underline;
      }

      .captcha-container {
        margin-bottom: 20px;
      }

      .captcha-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
      }

      .captcha-display {
        background: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 8px 15px;
        font-family: 'Courier New', monospace;
        font-size: 22px;
        font-weight: bold;
        letter-spacing: 6px;
        color: #0059a3;
        user-select: none;
        cursor: pointer;
        min-width: 120px;
        text-align: center;
        background-image: repeating-linear-gradient(
          0deg,
          transparent,
          transparent 2px,
          rgba(0,0,0,0.03) 2px,
          rgba(0,0,0,0.03) 4px
        );
      }

      .captcha-refresh {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        color: #666;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s;
      }

      .captcha-refresh:hover {
        color: #0059a3;
      }

      .captcha-input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
        text-transform: uppercase;
        letter-spacing: 2px;
      }

      .captcha-input:focus {
        outline: none;
        border-color: #0059a3;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
      }

      @media (max-height: 700px) {
        .modal-body {
          max-height: calc(90vh - 60px);
        }
      }

      @media (max-width: 560px) {
        .modal-content {
          width: 95%;
          max-width: 95%;
          max-height: 85vh;
        }
        .modal-body {
          max-height: calc(85vh - 60px);
          padding: 16px;
        }
        .modal-form .form-row {
          flex-direction: column;
          gap: 0;
        }
        .modal-header {
          padding: 16px;
        }
        .modal-header h3 {
          font-size: 16px;
        }
      }
      
      @media (max-width: 480px) {
        .modal-content {
          width: 95%;
          max-height: 85vh;
          border-radius: 8px;
        }
        .modal-header {
          padding: 14px;
        }
        .modal-header h3 {
          font-size: 18px;
        }
        .modal-body {
          padding: 16px;
        }
        .modal-intro {
          font-size: 14px;
        }
        .form-section {
          margin-bottom: 16px;
          padding-bottom: 16px;
        }
        .form-section-title {
          font-size: 14px;
          margin-bottom: 12px;
        }
        .form-row {
          flex-direction: column;
          gap: 12px;
        }
        .form-group {
          width: 100%;
        }
        .form-group label {
          font-size: 13px;
        }
        .form-group input,
        .form-group select {
          padding: 10px;
          font-size: 14px;
        }
        .btn-block {
          padding: 14px;
          font-size: 15px;
        }
        .modal-footer-text {
          font-size: 11px;
        }
        .welcome-back-modal {
          padding: 20px;
        }
        .welcome-back-logo img {
          width: 60px;
        }
        .welcome-back-title {
          font-size: 20px;
        }
        .welcome-back-message {
          font-size: 14px;
        }
        .captcha-container {
          flex-direction: column;
          gap: 10px;
        }
        .captcha-wrapper {
          justify-content: center;
        }
        .captcha-display {
          font-size: 20px;
          padding: 8px 12px;
          min-width: 100px;
        }
        .captcha-input {
          padding: 10px;
          font-size: 14px;
        }
        .success-modal {
          padding: 30px 20px;
        }
        .success-icon svg {
          width: 48px;
          height: 48px;
        }
        .success-modal h3 {
          font-size: 20px;
        }
        .success-modal p {
          font-size: 14px;
        }
      }
    </style>

    <!-- Toast Notification -->
    <div id="integrationToast" class="toast">
      <div class="toast-wrapper">
        <div class="toast-left-border"></div>
        <div class="toast-icon-wrapper">
          <span class="toast-icon">✓</span>
        </div>
        <div class="toast-content">
          <div class="toast-title"></div>
          <div class="toast-message"></div>
        </div>
        <button class="toast-close" onclick="closeIntegrationToast()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
      </div>
      <div class="toast-loading-line"></div>
    </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const faqItems = document.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
          item.addEventListener('toggle', function() {
            if (this.open) {
              faqItems.forEach(other => {
                if (other !== this && other.open) {
                  other.open = false;
                }
              });
            }
          });
        });
      });
    </script>

    <div class="map-modal" id="mapModal" role="dialog" aria-modal="true" aria-label="Clinic location map">
      <div class="map-modal-backdrop" id="mapModalBackdrop"></div>
      <div class="map-modal-content">
        <button class="map-modal-close" id="mapModalClose" aria-label="Close map">&times;</button>
        <div class="map-modal-iframe-wrap">
          <iframe
            src="https://www.google.com/maps?q=<?= urlencode($clinicAddress ?: 'Celeste Bldg. MaxSuniel St. Brgy. Carmen, Cagayan de Oro, Philippines, 9000') ?>&output=embed"
            allowfullscreen
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Clinic location map (large view)">
          </iframe>
        </div>
      </div>
    </div>

  </body>
</html>
