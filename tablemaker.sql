-- Run this SQL in your database
CREATE TABLE IF NOT EXISTS `otp_verification` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `otp_code` VARCHAR(6) NOT NULL,
  `expiry_time` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_expiry` (`expiry_time`)
);