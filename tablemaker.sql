-- Modified OTP table for phone verification
CREATE TABLE IF NOT EXISTS `otp_verification` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `phone_number` VARCHAR(20) NOT NULL,  -- Changed from email to phone
  `otp_code` VARCHAR(6) NOT NULL,
  `expiry_time` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_verified` BOOLEAN DEFAULT FALSE,
  INDEX `idx_phone` (`phone_number`),
  INDEX `idx_expiry` (`expiry_time`)
);