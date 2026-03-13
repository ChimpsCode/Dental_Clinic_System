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
  'What should I expect during my first visit? ',
  'How often should I visit the dentist ? ',
  'Do you accept dental insurance? ',
  'What payment option do you offert? ',
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
  </head>
  <body>
    <div id="top" aria-hidden="true"></div>
      <header class="site-header">
        <div class="container header-inner">
        <a class="brand" href="#home" aria-label="RF Dental Clinic home">
          <img class="brand-mark" src="<?= htmlspecialchars($assets['logo']) ?>" alt="" />
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
                <label class="field">
                  <span class="sr-only">Date</span>
                  <input name="date" type="text" placeholder="Date" />
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
                  <p>Answer content can go here (replace with your real FAQ answers).</p>
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
                <img src="<?= htmlspecialchars($assets['logo']) ?>" alt="" />
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
            <img class="footer-logo" src="<?= htmlspecialchars($assets['logo']) ?>" alt="" />
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


  </body>
</html>