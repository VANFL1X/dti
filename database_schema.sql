-- DTI Website Database Schema
-- This schema creates all necessary tables for the DTI management system
-- Database: dti
-- Charset: utf8mb4
-- Created: 2026-03-31

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `dti` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dti`;

-- ============================================================================
-- Users Table - Stores employee information
-- ============================================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(191) NOT NULL,
    `last_name` VARCHAR(191) NOT NULL,
    `middle_name` VARCHAR(191) DEFAULT NULL,
    `suffix` VARCHAR(64) DEFAULT NULL,
    `birthdate` DATE NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `division` VARCHAR(191) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `avatar` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_email` (`email`),
    INDEX `idx_division` (`division`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Activities Table - Stores calendar activities/indicative events
-- ============================================================================
CREATE TABLE IF NOT EXISTS `activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `purpose` VARCHAR(255) NOT NULL,
    `destination` VARCHAR(255) NOT NULL,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME NOT NULL,
    `is_global` TINYINT(1) NOT NULL DEFAULT 0,
    `division_scope` VARCHAR(120) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_start_datetime` (`start_datetime`),
    INDEX `idx_end_datetime` (`end_datetime`),
    INDEX `idx_division_scope` (`division_scope`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY `fk_activities_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Supply Requests Table - Stores office supply requests
-- ============================================================================
CREATE TABLE IF NOT EXISTS `supply_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `item` VARCHAR(191) NOT NULL,
    `variant` VARCHAR(191) NOT NULL,
    `quantity` INT NOT NULL,
    `unit` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY `fk_supply_requests_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- OB Slips Table - Stores official business slip submissions
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ob_slips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `ob_type` VARCHAR(16) NOT NULL,
    `slip_date` DATE NOT NULL,
    `employee_name` VARCHAR(191) NOT NULL,
    `section_name` VARCHAR(191) NOT NULL,
    `purpose` TEXT NOT NULL,
    `destination` TEXT NOT NULL,
    `departure_time` TIME NOT NULL,
    `return_time` TIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_slip_date` (`slip_date`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY `fk_ob_slips_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Vehicle Requests Table - Stores vehicle/transportation requests
-- ============================================================================
CREATE TABLE IF NOT EXISTS `vehicle_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `date_application` DATE NOT NULL,
    `date_use` DATE NOT NULL,
    `departure_date` DATE NOT NULL,
    `departure_time` TIME NOT NULL,
    `expected_arrival_date` DATE NOT NULL,
    `expected_arrival_time` TIME NOT NULL,
    `vehicle_plate_no` VARCHAR(191) NOT NULL,
    `destination` VARCHAR(255) NOT NULL,
    `purpose` TEXT NOT NULL,
    `driver_name` VARCHAR(191) NOT NULL,
    `transportation_incharge` VARCHAR(191) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `approved_by` INT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_date_use` (`date_use`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY `fk_vehicle_requests_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY `fk_vehicle_requests_approved_by` (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Passengers Table - Stores passengers for vehicle requests
-- ============================================================================
CREATE TABLE IF NOT EXISTS `passengers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `passenger_name` VARCHAR(191) NOT NULL,
    INDEX `idx_request_id` (`request_id`),
    FOREIGN KEY `fk_passengers_request_id` (`request_id`) REFERENCES `vehicle_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Leave Requests Table - Stores employee leave/time-off requests
-- ============================================================================
-- Leave requests feature removed: table intentionally excluded from schema

-- ============================================================================
-- Notifications Table - Stores in-app notifications
-- ============================================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `type` VARCHAR(64) NOT NULL,
    `ref_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `body` TEXT DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY `fk_notifications_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Employee Events Table - Stores employee status (office/travel/business/leave)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `employee_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `event_type` VARCHAR(32) NOT NULL,
    `start_datetime` DATETIME DEFAULT NULL,
    `end_datetime` DATETIME DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_user_status` (`user_id`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_start_datetime` (`start_datetime`),
    INDEX `idx_end_datetime` (`end_datetime`),
    FOREIGN KEY `fk_employee_events_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- User Login Logs Table - Stores login activity for analytics
-- ============================================================================
CREATE TABLE IF NOT EXISTS `user_login_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `login_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_login_at` (`login_at`),
    FOREIGN KEY `fk_user_login_logs_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Sample Data (Optional) - Uncomment to add test data
-- ============================================================================

-- INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `division`, `birthdate`) 
-- VALUES ('John', 'Doe', 'john@example.com', 'hashed_password_here', 'Admin Division', '1990-01-15');
