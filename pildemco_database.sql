-- ═══════════════════════════════════════════════════════════════════════
--  PILDEMCO DATABASE SCHEMA
--  Database: Pildemco_Database
--  Import this file first, then run reset_passwords.php ONCE (then delete it)
-- ═══════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `Pildemco_Database`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `Pildemco_Database`;

-- ── USER ACCOUNTS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_info` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `username`        VARCHAR(100) NOT NULL UNIQUE,
    `email`           VARCHAR(150) NOT NULL UNIQUE,
    `password`        VARCHAR(255) NOT NULL,
    `address`         VARCHAR(255) DEFAULT NULL,
    `cellphone`       VARCHAR(20)  DEFAULT NULL,
    `user_type`       ENUM('admin','user') NOT NULL DEFAULT 'user',
    `terms_agreed_at` DATETIME     DEFAULT NULL,
    `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── EQUIPMENT ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `equipment` (
    `equipment_id`  INT AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(150) NOT NULL,
    `category`      VARCHAR(100) NOT NULL DEFAULT 'Other',
    `description`   TEXT         DEFAULT NULL,
    `rate_cash`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `rate_palay`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `rate_unit`     VARCHAR(50)  NOT NULL DEFAULT 'per day',
    `status`        ENUM('available','maintenance','unavailable') NOT NULL DEFAULT 'available',
    `image_path`    VARCHAR(255) DEFAULT NULL,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── BOOKINGS ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bookings` (
    `booking_id`     INT AUTO_INCREMENT PRIMARY KEY,
    `farmer_id`      INT NOT NULL,
    `equipment_id`   INT NOT NULL,
    `start_date`     DATE         NOT NULL,
    `end_date`       DATE         NOT NULL,
    `use_date`       DATE         DEFAULT NULL,
    `use_time_start` TIME         DEFAULT NULL,
    `use_time_end`   TIME         DEFAULT NULL,
    `land_barangay`  VARCHAR(200) DEFAULT NULL,
    `land_purok`     VARCHAR(200) DEFAULT NULL,
    `land_lat`       DECIMAL(10,7) DEFAULT NULL,
    `land_lng`       DECIMAL(10,7) DEFAULT NULL,
    `total_hours`    DECIMAL(6,2) DEFAULT 1.00,
    `total_amount`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(20)  NOT NULL DEFAULT 'cash',
    `status`         ENUM('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
    `remarks`        TEXT         DEFAULT NULL,
    `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`farmer_id`)    REFERENCES `user_info`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`equipment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PAYMENTS ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payments` (
    `payment_id`     INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id`     INT NOT NULL,
    `farmer_id`      INT NOT NULL,
    `amount_cash`    DECIMAL(10,2) DEFAULT 0.00,
    `kg_palay`       DECIMAL(10,2) DEFAULT 0.00,
    `payment_method` VARCHAR(20)  DEFAULT 'cash',
    `status`         VARCHAR(20)  DEFAULT 'verified',
    `paid_at`        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE,
    FOREIGN KEY (`farmer_id`)  REFERENCES `user_info`(`id`)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── MESSAGES ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `messages` (
    `message_id`  INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id`   INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `sender_type` VARCHAR(10) DEFAULT 'user',
    `message`     TEXT NOT NULL,
    `is_read`     TINYINT(1) DEFAULT 0,
    `sent_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── NOTIFICATIONS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT NOT NULL,
    `title`      VARCHAR(150) NOT NULL,
    `message`    TEXT NOT NULL,
    `type`       ENUM('info','success','warning','emergency') DEFAULT 'info',
    `is_read`    TINYINT(1) DEFAULT 0,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `user_info`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════════════════════════════════
--  SAMPLE DATA
-- ═══════════════════════════════════════════════════════════════════════

-- Admin account (password set by reset_passwords.php → Admin@1234)
INSERT INTO `user_info` (`username`, `email`, `password`, `address`, `cellphone`, `user_type`)
VALUES ('admin', 'admin@pildemco.coop', 'CHANGE_VIA_reset_passwords.php', 'San Agustin, San Jose', '09171234567', 'admin');

-- Sample farmers (password set by reset_passwords.php → Farmer@123)
INSERT INTO `user_info` (`username`, `email`, `password`, `address`, `cellphone`, `user_type`) VALUES
('juan_delacruz',  'juan@example.com',    'CHANGE_VIA_reset_passwords.php', 'Brgy. Magsaysay',  '09181111111', 'user'),
('maria_santos',   'maria@example.com',   'CHANGE_VIA_reset_passwords.php', 'Brgy. Lumangbayan','09182222222', 'user'),
('pedro_reyes',    'pedro@example.com',   'CHANGE_VIA_reset_passwords.php', 'Brgy. San Agustin','09183333333', 'user'),
('ana_garcia',     'ana@example.com',     'CHANGE_VIA_reset_passwords.php', 'Brgy. Tigbao',     '09184444444', 'user'),
('roberto_torres', 'roberto@example.com', 'CHANGE_VIA_reset_passwords.php', 'Brgy. Batangas II','09185555555', 'user');

-- Sample equipment
INSERT INTO `equipment` (`name`, `category`, `description`, `rate_cash`, `rate_palay`, `rate_unit`, `status`) VALUES
('Four-Wheel Drive Tractor',  'Land Preparation', 'Heavy-duty 4WD tractor with plow and harrow attachments for land preparation.', 1500.00, 8.00, 'per day',     'available'),
('Hand Tractor / Kuliglig',   'Land Preparation', 'Compact hand tractor suitable for small to medium rice paddies.',              500.00,  3.00, 'per day',     'available'),
('Rice Transplanter',         'Harvesting',       'Automated rice transplanter for uniform seedling spacing.',                   1200.00, 6.00, 'per day',     'available'),
('Combine Harvester',         'Harvesting',       'Full-sized combine harvester for efficient rice harvesting.',                 2500.00, 12.00,'per day',     'available'),
('Rice Thresher',             'Post Harvest',     'Mechanical thresher for separating rice grains from stalks.',                 800.00,  4.00, 'per day',     'available'),
('Rotary Tiller',             'Soil Preparation', 'Rotary tiller for soil breaking and seedbed preparation.',                    700.00,  3.50, 'per day',     'available');

-- ═══════════════════════════════════════════════════════════════════════
--  AFTER IMPORTING:
--  1. Run: http://yoursite/reset_passwords.php
--  2. DELETE reset_passwords.php from server immediately after
--  3. Login as: admin@pildemco.coop / Admin@1234
-- ═══════════════════════════════════════════════════════════════════════
