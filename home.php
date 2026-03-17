<?php
$assets = [
  'logo' => 'https://www.figma.com/api/mcp/asset/0216bb5f-fd3d-487e-9d2c-5e8da91303ca',
  'hero' => './assets/images/Dentists.png',
  'about' => 'https://www.figma.com/api/mcp/asset/29ac1342-8a70-42fc-a97f-67f6e09243d6',
  'tooth' => 'https://www.figma.com/api/mcp/asset/e2ebbe80-7cbc-49ff-afcc-35ac70f56115',
  'icon_phone' => 'https://www.figma.com/api/mcp/asset/6b952526-b60f-4626-944d-502f23bcafeb',
  'icon_email' => 'https://www.figma.com/api/mcp/asset/edbadd5e-0598-4509-9014-7cc7a891e24e',
  'icon_time' => 'https://www.figma.com/api/mcp/asset/c6ae2ed7-ef74-4683-97bb-96d5151b6398',
];

$services = [
  [
    'title' => 'Consultation',
    'desc' => 'Professional oral health assessment and planning.',
    'price' => '₱500.00',
    'duration' => '30 min',
  ],
  [
    'title' => 'Crowns (Jacket)',
    'desc' => 'Protective cap to restore tooth shape and strength.',
    'price' => '₱2,000.00',
    'duration' => '90 min',
  ],
  [
    'title' => 'Denture Adjustment',
    'desc' => 'Reshaping of dentures to improve fit and comfort.',
    'price' => '₱500.00',
    'duration' => '30 min',
  ],
  [
    'title' => 'Fixed Bridge',
    'desc' => 'Permanent replacement for missing teeth anchored to adjacent teeth.',
    'price' => '₱5,000.00',
    'duration' => '90 min',
  ],
  [
    'title' => 'Orthodontic Appliance',
    'desc' => 'Devices like braces or retainers to align teeth.',
    'price' => '₱35,000.00',
    'duration' => '120 min',
  ],
  [
    'title' => 'Periapical Xray',
    'desc' => 'Single-tooth X-ray showing the root and surrounding bone.',
    'price' => '₱500.00',
    'duration' => '15 min',
  ],
  [
    'title' => 'Removable Dentures',
    'desc' => 'Custom-made removable replacement for missing teeth.',
    'price' => '₱5,000.00',
    'duration' => '30 min',
  ],
  [
    'title' => 'Root Canal Treatment',
    'desc' => 'Saves an infected tooth by removing the pulp.',
    'price' => '₱5,000.00',
    'duration' => '90 min',
  ],
  [
    'title' => 'Teeth Cleaning',
    'desc' => 'Case to case cleaning.',
    'price' => '₱800.00',
    'duration' => '60 min',
  ],
  [
    'title' => 'Teeth Whitening',
    'desc' => 'Cosmetic procedure to lighten teeth and remove stains.',
    'price' => '₱5,000.00',
    'duration' => '90 min',
  ],
  [
    'title' => 'Tooth Extraction (Ibot)',
    'desc' => 'Safe removal of a damaged or non-restorable tooth.',
    'price' => '₱800.00',
    'duration' => '45 min',
  ],
  [
    'title' => 'Tooth Restoration (Pasta)',
    'desc' => 'Restores decayed teeth using composite filling.',
    'price' => '₱800.00',
    'duration' => '45 min',
  ],
];

$faqs = [
  'What should I expect during my first visit?',
  'How often should I visit the dentist?',
  'Do you accept dental insurance?',
  'What payment options do you offer?',
];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>RF Dental Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,700;1,400&family=Inria+Sans:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/home-styles.css" />
    <link rel="stylesheet" href="assets/css/home-integration.css" />
    <script src="assets/js/home-integration.js" defer></script>
  </head>
  <body>
    <div id="top" aria-hidden="true"></div>
      <header class="site-header">
        <div class="container header-inner">
        <a class="brand" href="#home" aria-label="RF Dental Clinic home">
          <img class="brand-mark" src="assets/images/Logo.png" alt="" />
          <span class="brand-text">RF Dental Clinic</span>
        </a>

        <nav class="nav" aria-label="Primary">
          <a href="#top" class="nav-link">Home</a>
          <a href="#about" class="nav-link">About Us</a>
          <a href="#services" class="nav-link">Services</a>
          <a href="#faq" class="nav-link">FAQ</a>
          <a href="#contact" class="nav-link">Contact Us</a>
        </nav>

        <div class="header-actions">
          <a class="btn btn-outline" href="patient_register.php">Sign Up</a>
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
              Your <span class="accent">Best Dental</span> Care
              <br />
              In Town Awaits
            </h1>
            <p class="hero-subtitle">
              Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas quam libero, malesuada sed lorem in, sodales dapibus ligula.
            </p>

            <div class="hero-ctas">
              <a class="btn btn-primary btn-lg" href="#services">Explore Services</a>
              <a class="btn btn-outline btn-lg" href="patient_register.php">Sign Up</a>
            </div>

            <div class="appointment-card" aria-label="Book an appointment">
              <div class="appointment-title">Book an Appointment Today</div>
              <form class="appointment-form" action="#" method="post">
                <label class="field">
                  <span class="sr-only">Name</span>
                  <input name="name" type="text" placeholder="Name" />
                </label>
                <label class="field">
                  <span class="sr-only">Email</span>
                  <input name="email" type="email" placeholder="Email" />
                </label>
                <label class="field">
                  <span class="sr-only">Phone</span>
                  <input name="phone" type="tel" placeholder="Phone" />
                </label>

                <button class="btn btn-primary" type="submit">Book Now</button>
              </form>
            </div>
          </div>

          <div class="hero-visual">
            <div class="hero-photo-wrap">
              <img class="hero-photo" src="<?= htmlspecialchars($assets['hero']) ?>" alt="Dentists from RF Dental Clinic" />

              <div class="hero-note hero-note-1">
                <div class="hero-note-text">Hi I’m Dr. Rex</div>
              </div>
              <div class="hero-note hero-note-2">
                <div class="hero-note-text">and I’m Dr. Floralyn</div>
              </div>
              <div class="hero-badge">
                <div class="hero-badge-big">7+</div>
                <div class="hero-badge-small">Years Experience</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Inline login panel inside hero -->
      <div class="inline-login-panel" id="inlineLoginPanel" role="dialog" aria-modal="true" aria-labelledby="inlineLoginTitle">
        <div class="login-form-container inline login-card">
          <button type="button" class="login-close-btn" id="inlineLoginClose" aria-label="Close login form">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>

          <div class="logo-container">
            <div class="logo"><img src="assets/images/Logo.png" alt="RF Dental Clinic logo" /></div>
          </div>
          <div class="clinic-name" id="inlineLoginTitle">RF Dental Clinic</div>

          <form id="integratedLoginFormInline" novalidate>
            <div class="alert alert-error" id="loginErrorMessageInline" style="display:none"></div>

            <div class="input-group">
              <input id="integrated-username-inline" name="username" type="text" placeholder="Username" autocomplete="username" required />
            </div>

            <div class="input-group">
              <input id="integrated-password-inline" name="password" type="password" placeholder="Password" autocomplete="current-password" required />
              <button type="button" class="toggle-password" id="integratedTogglePasswordInline" aria-label="Toggle password visibility" style="display:none">
                <span class="eye-open">
                  <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </span>
                <span class="eye-closed" style="display:none">
                  <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 3 21 21"></path><path d="M10.58 10.58A3 3 0 0 0 13.4 13.4"></path><path d="M9.88 4.24A10.64 10.64 0 0 1 21 12a10.7 10.7 0 0 1-1.1 2.94"></path><path d="M6.61 6.61A10.7 10.7 0 0 0 3 12a10.64 10.64 0 0 0 7 7"></path></svg>
                </span>
              </button>
            </div>

            <div class="form-links">
              <a class="link" href="forgot-password.php">Forgot password?</a>
              <span class="separator">|</span>
              <a class="link" href="#" id="switchToSignup">Create account</a>
            </div>

            <button class="login-btn" type="button" id="integratedLoginBtnInline">Login</button>
          </form>
        </div>

        <div class="login-form-container inline signup-card" id="inlineSignupCard" aria-hidden="true">
          <button type="button" class="login-close-btn" id="inlineSignupClose" aria-label="Close sign up form">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>

          <div class="logo-container">
            <div class="logo"><img src="assets/images/Logo.png" alt="RF Dental Clinic logo" /></div>
          </div>
          <div class="clinic-name" id="inlineSignupTitle">Create Account</div>

          <form id="integratedSignupFormInline" novalidate>
            <div class="alert alert-error" id="signupErrorMessageInline" style="display:none"></div>

            <div class="input-group two-col">
              <input id="signup-firstname" name="first_name" type="text" placeholder="First name" autocomplete="given-name" required />
              <input id="signup-lastname" name="last_name" type="text" placeholder="Last name" autocomplete="family-name" required />
            </div>

            <div class="input-group">
              <input id="signup-email" name="email" type="email" placeholder="Email" autocomplete="email" required />
            </div>

            <div class="input-group">
              <input id="signup-phone" name="phone" type="tel" placeholder="Phone" autocomplete="tel" required />
            </div>

            <div class="input-group">
              <input id="signup-password" name="password" type="password" placeholder="Password (min 6 chars)" autocomplete="new-password" required />
            </div>

            <div class="input-group">
              <input id="signup-confirm" name="confirm_password" type="password" placeholder="Confirm password" autocomplete="new-password" required />
            </div>

            <div class="form-links">
              <a class="link" href="#" id="switchToLogin">Already have an account?</a>
            </div>

            <button class="login-btn" type="button" id="integratedSignupBtnInline">Sign Up</button>
          </form>
        </div>
      </div>

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
              About <span class="accent">RF Dental</span>
            </h2>

            <p class="about-text">
              At RF Dental Clinic, we're committed to providing exceptional dental care in a comfortable and welcoming environment. Our experienced team uses the latest technology to ensure you receive the best treatment possible.
            </p>
            <p class="about-text">
              We believe everyone deserves a healthy, beautiful smile. That's why we offer comprehensive services ranging from routine checkups to advanced cosmetic procedures, all delivered with personalized care and attention.
            </p>

            <div class="about-stats">
              <div class="stat">
                <div class="stat-value accent">5,000+</div>
                <div class="stat-label">Happy Patients</div>
              </div>
              <div class="stat">
                <div class="stat-value accent">98%</div>
                <div class="stat-label">Satisfaction Rate</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="services" class="section services" style="padding-top:30px">
        <div class="container">
          <h2 class="section-title center">
            Our <span class="accent">Services</span>
          </h2>
          <p class="section-subtitle center">
            Comprehensive dental care tailored to your needs, delivered with expertise and compassion.
          </p>

          <div class="services-grid">
            <?php foreach ($services as $svc): ?>
              <article class="service-card">
                <h3 class="service-title"><?= htmlspecialchars($svc['title']) ?></h3>
                <p class="service-desc"><?= htmlspecialchars($svc['desc']) ?></p>
                <div class="service-meta">
                  <span class="service-price"><?= htmlspecialchars($svc['price']) ?></span>
                  <span class="service-dot" aria-hidden="true">•</span>
                  <span class="service-duration"><?= htmlspecialchars($svc['duration']) ?></span>
                </div>
              </article>
            <?php endforeach; ?>
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
            <?php foreach ($faqs as $idx => $q): ?>
              <details class="faq-item">
                <summary class="faq-summary">
                  <span class="faq-q"><?= htmlspecialchars($q) ?></span>
                  <span class="faq-chevron" aria-hidden="true"></span>
                </summary>
                    <div class="faq-a">
                      <?php if ($idx == 0): ?>
                        <p>During your first visit, you'll meet our friendly team, discuss your dental history, and receive a thorough examination. We recommend bringing any previous dental records and a list of medications you're currently taking.</p>
                      <?php elseif ($idx == 1): ?>
                        <p>We recommend visiting the dentist every 6 months for regular check-ups and cleanings. However, if you have specific dental concerns or conditions, we may suggest more frequent visits to ensure optimal oral health.</p>
                      <?php elseif ($idx == 2): ?>
                        <p>Unfortunately, we do not accept dental insurance at this time. However, we offer competitive pricing and flexible payment options to make our services accessible to all patients.</p>
                      <?php elseif ($idx == 3): ?>
                        <p>We offer flexible payment options including cash, gcash e-wallet and bank transfers.</p>
                      
                      <?php endif; ?>
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

      <section id="contact" class="section contact" style="padding-top:30px">
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
                  <span>0967 187 8603</span>
                </div>
                <div class="meta-row">
                  <img src="<?= htmlspecialchars($assets['icon_email']) ?>" alt="" class="meta-icon" />
                  <a href="mailto:rfdc2023@gmail.com">rfdc2023@gmail.com</a>
                </div>
                <div class="meta-row">
                  <img src="<?= htmlspecialchars($assets['icon_time']) ?>" alt="" class="meta-icon" />
                  <span>Mon - Sat 9:00 AM - 5:00 PM</span>
                </div>
              </div>
            </div>
            <div class="contact-form-card">
              <div class="form-logo" aria-hidden="true">
                <img src="assets/images/Logo.png" alt="" />
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
            <img class="footer-logo" src="assets/images/Logo.png" alt="" />
            <div class="footer-brand-title">Dental Clinic</div>
          </div>
          <p class="footer-text">Providing exceptional dental care with a commitment to your comfort and health.</p>
        </div>

        <div class="footer-col">
          <div class="footer-heading">Quick Links</div>
          <a class="footer-link" href="#home">Home</a>
          <a class="footer-link" href="#about">About Us</a>
          <a class="footer-link" href="#services">Services</a>
          <a class="footer-link" href="#contact">Contact</a>
        </div>

        <div class="footer-col">
          <div class="footer-heading">Services</div>
          <a class="footer-link" href="#services">General Dentistry</a>
          <a class="footer-link" href="#services">General Dentistry</a>
          <a class="footer-link" href="#services">General Dentistry</a>
          <a class="footer-link" href="#services">General Dentistry</a>
          <a class="footer-link" href="#services">General Dentistry</a>
        </div>

        <div class="footer-col footer-connect">
          <div class="footer-heading">Connect With Us</div>
          <div class="footer-icons" aria-label="Social and contact links">
            <a href="https://www.facebook.com" target="_blank" rel="noreferrer" class="footer-icon-link" aria-label="Facebook">
              <span class="footer-icon footer-icon-fb" aria-hidden="true">f</span>
            </a>
            <a href="mailto:rfdc2023@gmail.com" class="footer-icon-link" aria-label="Email">
              <span class="footer-icon footer-icon-mail" aria-hidden="true">✉</span>
            </a>
            <a href="https://m.me" target="_blank" rel="noreferrer" class="footer-icon-link" aria-label="Facebook Messenger">
              <img src="./assets/images/messenger.png" alt="" class="footer-icon-img" aria-hidden="true" />
            </a>
            <a href="tel:09671878603" class="footer-icon-link" aria-label="Call">
              <span class="footer-icon footer-icon-phone" aria-hidden="true">☎</span>
            </a>
          </div>
          <div class="footer-location">
            <span class="footer-location-icon" aria-hidden="true">📍</span>
            <span class="footer-text small">
              Celeste Bldg. MaxSuniel St. Brgy. Carmen, Cagayan de Oro, Philippines, 9000
            </span>
          </div>
        </div>
      </div>
    </footer>

    <button
      type="button"
      class="chat-fab"
      id="chatFab"
      aria-label="Open AI chat"
      aria-haspopup="dialog"
      aria-controls="chatWidget"
      aria-expanded="false"
    >
      <span class="chat-fab-icon" aria-hidden="true">💬</span>
    </button>
    
    <!-- Chatbot greeting balloon -->
    <div class="chat-balloon" id="chatBalloon">
      <div class="chat-balloon-content">
        <span class="chat-balloon-icon">🤖</span>
        <span class="chat-balloon-text">Hi! I'm your RF Dental Clinic chatbot assistant.</span>
      </div>
      <div class="chat-balloon-arrow"></div>
    </div>

    <section class="chat-widget" id="chatWidget" role="dialog" aria-modal="false" aria-label="AI automated chat" hidden>
      <header class="chat-header">
        <div class="chat-title">
          <div class="chat-title-main">RF Dental AI</div>
          <div class="chat-title-sub">Automated inquiry assistant</div>
        </div>
        <button type="button" class="chat-close" id="chatClose" aria-label="Close chat">✕</button>
      </header>

      <div class="chat-body" id="chatBody" aria-live="polite" aria-relevant="additions"></div>

      <form class="chat-input" id="chatForm" autocomplete="off">
        <input
          type="text"
          id="chatText"
          name="message"
          placeholder="Type your question… (e.g., price for braces, clinic hours)"
          aria-label="Chat message"
          required
        />
        <button class="btn btn-primary" type="submit">Send</button>
      </form>
    </section>

    <script>
      (() => {
        const fab = document.getElementById('chatFab');
        const widget = document.getElementById('chatWidget');
        const closeBtn = document.getElementById('chatClose');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('chatText');
        const body = document.getElementById('chatBody');
        const balloon = document.getElementById('chatBalloon');

        const SERVICES = [
          { name: 'Consultation', price: '₱500.00', duration: '30 min' },
          { name: 'Crowns (Jacket)', price: '₱2,000.00', duration: '90 min' },
          { name: 'Denture Adjustment', price: '₱500.00', duration: '30 min' },
          { name: 'Fixed Bridge', price: '₱5,000.00', duration: '90 min' },
          { name: 'Orthodontic Appliance', price: '₱35,000.00', duration: '120 min' },
          { name: 'Periapical Xray', price: '₱500.00', duration: '15 min' },
          { name: 'Removable Dentures', price: '₱5,000.00', duration: '30 min' },
          { name: 'Root Canal Treatment', price: '₱5,000.00', duration: '90 min' },
          { name: 'Teeth Cleaning', price: '₱800.00', duration: '60 min' },
          { name: 'Teeth Whitening', price: '₱5,000.00', duration: '90 min' },
          { name: 'Tooth Extraction (Ibot)', price: '₱800.00', duration: '45 min' },
          { name: 'Tooth Restoration (Pasta)', price: '₱800.00', duration: '45 min' },
        ];

        const CLINIC = {
          phone: '0967 187 8603',
          email: 'rfdc2023@gmail.com',
          hours: 'Mon - Sat 9:00 AM - 5:00 PM',
        };

        function addMsg(role, text) {
          const row = document.createElement('div');
          row.className = `chat-msg chat-msg-${role}`;
          const bubble = document.createElement('div');
          bubble.className = 'chat-bubble';
          bubble.textContent = text;
          row.appendChild(bubble);
          body.appendChild(row);
          body.scrollTop = body.scrollHeight;
        }

        function normalize(s) {
          return (s || '').toLowerCase().replace(/\s+/g, ' ').trim();
        }

        function findService(q) {
          const nq = normalize(q);
          return SERVICES.find(s => normalize(s.name).includes(nq) || nq.includes(normalize(s.name)));
        }

        function replyTo(q) {
          const nq = normalize(q);

          if (!nq) return "How can I help you today?";

          if (/(hi|hello|hey)\b/.test(nq)) {
            return "Hi! You can ask about our services, prices, durations, clinic hours, or how to book an appointment.";
          }

          if (/(hours|open|schedule|time)\b/.test(nq)) {
            return `Our clinic hours are ${CLINIC.hours}.`;
          }

          if (/(phone|contact|call|number)\b/.test(nq)) {
            return `You can call us at ${CLINIC.phone}.`;
          }

          if (/(email|gmail)\b/.test(nq)) {
            return `You can email us at ${CLINIC.email}.`;
          }

          if (/(price|cost|how much|fee|₱|php)\b/.test(nq)) {
            const svc = findService(q);
            if (svc) return `${svc.name}: ${svc.price} • ${svc.duration}. Want to book an appointment?`;
            return "Which service are you asking about? (e.g., Teeth Whitening, Orthodontic Appliance, Root Canal Treatment)";
          }

          if (/(services|offer|available)\b/.test(nq)) {
            return "We offer Consultation, Crowns (Jacket), Denture Adjustment, Fixed Bridge, Orthodontic Appliance, Xray, Dentures, Root Canal, Cleaning, Whitening, Extraction, and Restoration. Ask me any one service for price and duration.";
          }

          if (/(book|appointment|schedule)\b/.test(nq)) {
            return "To book, you can use the appointment form on this page or tell me your preferred date/time and service, then we’ll confirm via phone or email.";
          }

          const svc = findService(q);
          if (svc) return `${svc.name}: ${svc.price} • ${svc.duration}.`;

          return "I can help with services, prices, durations, clinic hours, and booking. What would you like to know?";
        }

        function openChat() {
          widget.hidden = false;
          fab.setAttribute('aria-expanded', 'true');
          setTimeout(() => input.focus(), 0);
          if (!body.dataset.greeted) {
            addMsg('bot', "Hi! I’m RF Dental AI. Ask me about services, prices, durations, clinic hours, or booking.");
            body.dataset.greeted = '1';
          }
        }

        function closeChat() {
          widget.hidden = true;
          fab.setAttribute('aria-expanded', 'false');
          fab.focus();
        }

        fab.addEventListener('click', () => (widget.hidden ? openChat() : closeChat()));
        closeBtn.addEventListener('click', closeChat);
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && !widget.hidden) closeChat();
        });

        form.addEventListener('submit', (e) => {
          e.preventDefault();
          const text = input.value.trim();
          if (!text) return;
          addMsg('user', text);
          input.value = '';
          const response = replyTo(text);
          window.setTimeout(() => addMsg('bot', response), 250);
        });

        // Show chat balloon greeting
        if (balloon) {
          setTimeout(() => {
            balloon.classList.add('show');
            // Hide balloon after 2 seconds
            setTimeout(() => {
              balloon.classList.remove('show');
            }, 4000);
          }, 1000); // Wait 1 second after page load
        }
      })();
    </script>

    <!-- Registration Modal for Lead Capture Flow -->
    <div id="registrationModal" class="modal-overlay" style="display: none;">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Secure Your Appointment</h3>
          <button type="button" class="modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
          <p class="modal-intro">You're almost done! Create a password to secure your account and complete your booking.</p>
          <form id="registrationForm" class="modal-form">
            <div class="form-group">
              <label for="reg-password">Create Password</label>
              <input type="password" id="reg-password" name="password" required minlength="8" placeholder="At least 8 characters" />
            </div>
            <div class="form-group">
              <label for="reg-confirm-password">Confirm Password</label>
              <input type="password" id="reg-confirm-password" name="confirm_password" required placeholder="Re-enter password" />
            </div>
            <input type="hidden" id="reg-name" name="name" />
            <input type="hidden" id="reg-email" name="email" />
            <input type="hidden" id="reg-phone" name="phone" />
            <button type="submit" class="btn btn-primary btn-block">Finalize Registration</button>
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
          appointmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Collect form data
            const formData = new FormData(appointmentForm);
            const name = formData.get('name');
            const email = formData.get('email');
            const phone = formData.get('phone');

            // Validate required fields
            if (!name || !email || !phone) {
              alert('Please fill in all fields');
              return;
            }

            // Store data in hidden fields for the registration form
            document.getElementById('reg-name').value = name;
            document.getElementById('reg-email').value = email;
            document.getElementById('reg-phone').value = phone;

            // Show the registration modal
            registrationModal.style.display = 'flex';
            body.style.overflow = 'hidden';
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
              const response = await fetch('process_registration.php', {
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
                  window.location.href = 'patient/patient-dashboard.php';
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
        
        // Set active state on page load and hash change
        setActiveLink();
        window.addEventListener('hashchange', setActiveLink);
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
        max-width: 420px;
        padding: 0;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalSlideIn 0.3s ease;
      }
      
      .success-modal {
        text-align: center;
        padding: 40px 30px;
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
      
      .success-icon {
        color: #28a745;
        margin-bottom: 16px;
      }
      
      .success-icon svg {
        display: block;
        margin: 0 auto;
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

  </body>
</html>
