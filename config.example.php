<?php
// Copy this file to config.php and fill in real values. config.php is gitignored.

define('DB_HOST', 'localhost');
define('DB_NAME', 'suite_booking_platform');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL of the app, no trailing slash, e.g. http://localhost/suite-booking-platform
define('APP_BASE_URL', 'http://localhost/suite-booking-platform');

// Used as the "from" address for outgoing admin/guest emails.
define('MAIL_FROM', 'no-reply@example.com');
define('MAIL_FROM_NAME', 'Suite Booking');

// Web push (browser notifications for admins). Generate your own pair — never reuse the sample
// below in production — with: vendor/bin/php -r "require 'vendor/autoload.php'; print_r(\Minishlink\WebPush\VAPID::createVapidKeys());"
define('VAPID_PUBLIC_KEY', '');
define('VAPID_PRIVATE_KEY', '');
define('VAPID_SUBJECT', 'mailto:' . MAIL_FROM);
