-- Suite Booking Platform — multi-tenant schema
CREATE DATABASE IF NOT EXISTS suite_booking_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE suite_booking_platform;

CREATE TABLE super_admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL DEFAULT 'שם העסק',
  tagline VARCHAR(255) DEFAULT '',
  logo_path VARCHAR(255) DEFAULT NULL,
  hero_image VARCHAR(255) DEFAULT NULL,
  hero_position VARCHAR(10) NOT NULL DEFAULT 'center',
  phone VARCHAR(40) DEFAULT '',
  whatsapp VARCHAR(40) DEFAULT '',
  email VARCHAR(150) DEFAULT '',
  address VARCHAR(255) DEFAULT '',
  location_text VARCHAR(255) DEFAULT '',
  location_lat DECIMAL(10,7) DEFAULT NULL,
  location_lng DECIMAL(10,7) DEFAULT NULL,
  instagram_url VARCHAR(255) DEFAULT '',
  facebook_url VARCHAR(255) DEFAULT '',
  tiktok_url VARCHAR(255) DEFAULT '',
  trust1_title VARCHAR(120) DEFAULT '',
  trust1_text VARCHAR(255) DEFAULT '',
  trust2_title VARCHAR(120) DEFAULT '',
  trust2_text VARCHAR(255) DEFAULT '',
  trust3_title VARCHAR(120) DEFAULT '',
  trust3_text VARCHAR(255) DEFAULT '',
  cancellation_hours INT DEFAULT 3,
  palette_key VARCHAR(20) NOT NULL DEFAULT '',
  font_key VARCHAR(20) NOT NULL DEFAULT '',
  default_lang VARCHAR(5) NOT NULL DEFAULT 'he',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(150) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_site_username (site_id, username),
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
);

CREATE TABLE facilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fkey VARCHAR(40) NOT NULL UNIQUE,
  icon VARCHAR(40) DEFAULT '',
  label_he VARCHAR(80) NOT NULL,
  label_en VARCHAR(80) NOT NULL
);

INSERT INTO facilities (fkey, icon, label_he, label_en) VALUES
('bath','bath','אמבטיה','Bath'),
('jacuzzi','hot-tub','ג׳קוזי','Jacuzzi'),
('parking','car','חניה פרטית','Private Parking'),
('terrace','sun','מרפסת','Terrace'),
('wifi','wifi','אינטרנט אלחוטי','WiFi'),
('ac','wind','מזגן','Air Conditioning'),
('tv','tv','טלוויזיה','TV'),
('minibar','glass','מיני בר','Mini Bar'),
('sound','music','מערכת סאונד','Sound System'),
('breakfast','coffee','ארוחת בוקר','Breakfast');

CREATE TABLE rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  name VARCHAR(150) NOT NULL DEFAULT 'חדר',
  slug VARCHAR(150) NOT NULL,
  description TEXT,
  price_per_hour DECIMAL(10,2) DEFAULT 0,
  show_price_per_hour TINYINT(1) NOT NULL DEFAULT 1,
  price_per_day DECIMAL(10,2) DEFAULT 0,
  price_3h DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_extra_hour DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_hours INT DEFAULT 3,
  capacity INT DEFAULT 2,
  size_sqm INT DEFAULT NULL,
  bed_type VARCHAR(60) DEFAULT '',
  house_rules_text VARCHAR(255) DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_site_slug (site_id, slug),
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
);

CREATE TABLE room_facilities (
  room_id INT NOT NULL,
  facility_id INT NOT NULL,
  PRIMARY KEY (room_id, facility_id),
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE
);

CREATE TABLE room_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  type ENUM('image','video') NOT NULL DEFAULT 'image',
  path VARCHAR(255) NOT NULL,
  is_cover TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Weekly recurring open hours per room
CREATE TABLE availability_schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  weekday TINYINT NOT NULL, -- 0=Sunday .. 6=Saturday
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  open_time TIME DEFAULT '00:00:00',
  close_time TIME DEFAULT '23:59:00',
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Manual admin blocks (maintenance, holds) independent of bookings
CREATE TABLE availability_blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  block_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  reason VARCHAR(255) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  room_id INT NOT NULL,
  guest_name VARCHAR(120) NOT NULL,
  guest_phone VARCHAR(40) NOT NULL,
  booking_type ENUM('hourly','daily') NOT NULL DEFAULT 'hourly',
  date_start DATE NOT NULL,
  date_end DATE NOT NULL,
  time_start TIME DEFAULT NULL,
  time_end TIME DEFAULT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  notes VARCHAR(255) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

CREATE TABLE push_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  admin_id INT NOT NULL,
  endpoint VARCHAR(500) NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_endpoint (endpoint(255)),
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scope ENUM('admin','superadmin') NOT NULL,
  identifier VARCHAR(191) NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_scope_identifier_time (scope, identifier, attempted_at)
);

CREATE TABLE admin_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  booking_id INT DEFAULT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
);

-- Seed: one demo site with sample branding and rooms, ready to explore at /demo
-- (upload real photos from its admin panel to replace the placeholders)
INSERT INTO sites (slug, name, tagline, phone, whatsapp, email, address, location_text,
  trust1_title, trust1_text, trust2_title, trust2_text, trust3_title, trust3_text)
VALUES ('demo', 'סוויטת האורן', 'סוויטות עיצוב פרטיות במרכז הצפון, להשכרה לפי שעה או ללילה', '050-1234567', '972501234567', 'demo@suite-booking.example', 'רחוב האורן 12, בנימינה', 'כניסה עצמאית, חניה חופשית ברחוב',
  'הפרטים שלכם נשארים בינינו', 'שם וטלפון בלבד, בלי שיתוף עם צד שלישי.',
  'ביטול חינם עד 3 שעות לפני', 'שינוי או ביטול מתבצע בהודעה בוואטסאפ, בלי חיוב.',
  'כניסה עצמאית, בלי דלפק קבלה', 'קוד כניסה נשלח בוואטסאפ קצת לפני השעה שנקבעה.');

INSERT INTO rooms (site_id, name, slug, description, price_per_hour, price_per_day, price_3h, price_extra_hour, min_hours, capacity, size_sqm, bed_type, sort_order)
VALUES
(1, 'סוויטת ענבר', 'room-amber', 'סוויטה מרווחת עם ג׳קוזי זוגי, תאורה רכה ומרפסת פרטית פונה לגינה. מושלמת לערב רומנטי או ללינה שקטה.', 150, 600, 400, 100, 3, 2, 26, 'מיטה זוגית', 1),
(1, 'סוויטת קורל', 'room-coral', 'עיצוב תוסס עם קיר לבנים חשוף, מקלחת גשם ומיטה זוגית רחבה. כוללת מיני-בר ופינת ישיבה נעימה.', 180, 720, 440, 110, 3, 2, 28, 'מיטה זוגית רחבה', 2),
(1, 'סוויטת זית', 'room-olive', 'סוויטה שקטה בגווני ירוק וטבע, עם ג׳קוזי פינתי וחלונות גדולים המשקיפים לחצר.', 140, 560, 380, 90, 3, 2, 22, 'מיטה זוגית', 3);

INSERT INTO availability_schedule (room_id, weekday, is_closed, open_time, close_time)
SELECT r.id, w.n, 0, '00:00:00', '23:59:00'
FROM rooms r
JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) w(n)
WHERE r.site_id = 1;
