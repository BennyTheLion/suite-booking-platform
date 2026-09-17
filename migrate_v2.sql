-- v2: login rate limiting
USE suite_booking_platform;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scope ENUM('admin','superadmin') NOT NULL,
  identifier VARCHAR(191) NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_scope_identifier_time (scope, identifier, attempted_at)
);
