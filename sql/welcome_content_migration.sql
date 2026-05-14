-- Welcome Content Management System
-- Migration: Create content_images table + seed default settings

CREATE TABLE IF NOT EXISTS content_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    section VARCHAR(50),
    uploaded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default welcome content settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('welcome_logo', ''),
('welcome_hero_title', 'Your Best Dental Care In Town Awaits'),
('welcome_hero_subtitle', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas quam libero, malesuada sed lorem in, sodales dapibus ligula.'),
('welcome_hero_image', ''),
('welcome_hero_badge_big', '7+'),
('welcome_hero_badge_small', 'Years Experience'),
('welcome_hero_note1', 'Hi I''m Dr. Rex'),
('welcome_hero_note2', 'and I''m Dr. Floralyn'),
('welcome_about_heading', 'About RF Dental'),
('welcome_about_paragraph1', 'At RF Dental Clinic, we''re committed to providing exceptional dental care in a comfortable and welcoming environment. Our experienced team uses the latest technology to ensure you receive the best treatment possible.'),
('welcome_about_paragraph2', 'We believe everyone deserves a healthy, beautiful smile. That''s why we offer comprehensive services ranging from routine checkups to advanced cosmetic procedures, all delivered with personalized care and attention.'),
('welcome_about_image', ''),
('welcome_about_stats', '[{"value":"5,000+","label":"Happy Patients"},{"value":"98%","label":"Satisfaction Rate"}]'),
('welcome_faqs', '[{"q":"What should I expect during my first visit?","a":"During your first visit, you''ll meet our friendly team, discuss your dental history, and receive a thorough examination. We recommend bringing any previous dental records and a list of medications you''re currently taking."},{"q":"How often should I visit the dentist?","a":"We recommend visiting the dentist every 6 months for regular check-ups and cleanings. However, if you have specific dental concerns or conditions, we may suggest more frequent visits to ensure optimal oral health."},{"q":"Do you accept dental insurance?","a":"Unfortunately, we do not accept dental insurance at this time. However, we offer competitive pricing and flexible payment options to make our services accessible to all patients."},{"q":"What payment options do you offer?","a":"We offer flexible payment options including cash, gcash e-wallet and bank transfers."}]'),
('welcome_featured_services', '[9,11,12]');
