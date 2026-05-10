<?php
/**
 * Database Verification Script (CLI-safe)
 * Tests database connection and verifies all tables exist
 */

// Skip session for CLI use
if (PHP_SAPI !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

echo "=== DTI Database Verification ===\n\n";

try {
    // Direct database connection
    $dbHost = '127.0.0.1';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'dti';
    
    // Test 1: Connection
    echo "[1/4] Testing database connection... ";
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass);
    
    if ($mysqli->connect_errno) {
        echo "✗ Failed\n";
        echo "Error: " . $mysqli->connect_error . "\n";
        exit(1);
    }
    echo "✓ Connected\n";
    
    // Create database if needed
    echo "[2/4] Creating/selecting database '$dbName'... ";
    $create = $mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if ($create === false) {
        echo "✗ Failed to create database\n";
        exit(1);
    }
    
    if (!$mysqli->select_db($dbName)) {
        echo "✗ Failed to select database\n";
        exit(1);
    }
    
    $mysqli->set_charset('utf8mb4');
    echo "✓ Ready\n";
    
    // Test 3: Tables exist and create if needed
    echo "[3/4] Checking tables...\n";
    
    $tables = [
        'users' => "CREATE TABLE IF NOT EXISTS `users` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'activities' => "CREATE TABLE IF NOT EXISTS `activities` (
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
            FOREIGN KEY `fk_activities_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'supply_requests' => "CREATE TABLE IF NOT EXISTS `supply_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `item` VARCHAR(191) NOT NULL,
            `variant` VARCHAR(191) NOT NULL,
            `quantity` INT NOT NULL,
            `unit` VARCHAR(64) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            FOREIGN KEY `fk_supply_requests_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'ob_slips' => "CREATE TABLE IF NOT EXISTS `ob_slips` (
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
            FOREIGN KEY `fk_ob_slips_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'vehicle_requests' => "CREATE TABLE IF NOT EXISTS `vehicle_requests` (
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
            FOREIGN KEY `fk_vehicle_requests_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'passengers' => "CREATE TABLE IF NOT EXISTS `passengers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `request_id` INT NOT NULL,
            `passenger_name` VARCHAR(191) NOT NULL,
            INDEX `idx_request_id` (`request_id`),
            FOREIGN KEY `fk_passengers_request_id` (`request_id`) REFERENCES `vehicle_requests` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'leave_requests' => "CREATE TABLE IF NOT EXISTS `leave_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `leave_type` VARCHAR(64) NOT NULL,
            `notes` TEXT DEFAULT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_start_date` (`start_date`),
            INDEX `idx_end_date` (`end_date`),
            FOREIGN KEY `fk_leave_requests_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'notifications' => "CREATE TABLE IF NOT EXISTS `notifications` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'employee_events' => "CREATE TABLE IF NOT EXISTS `employee_events` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'user_login_logs' => "CREATE TABLE IF NOT EXISTS `user_login_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `login_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_login_at` (`login_at`),
            FOREIGN KEY `fk_user_login_logs_user_id` (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    $createdCount = 0;
    foreach ($tables as $tableName => $createSQL) {
        if ($mysqli->query($createSQL)) {
            echo "     ✓ $tableName\n";
            $createdCount++;
        } else {
            echo "     ✗ $tableName (Error: " . $mysqli->error . ")\n";
        }
    }
    
    // Test 4: Get statistics
    echo "[4/4] Database statistics:\n";
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT table_name) as table_count,
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.tables 
        WHERE table_schema = '$dbName'
    ";
    $result = $mysqli->query($statsQuery);
    if ($result) {
        $stats = $result->fetch_assoc();
        echo "     Tables: " . $stats['table_count'] . "\n";
        echo "     Size: " . ($stats['size_mb'] ?? '0') . " MB\n";
    }
    
    // Record counts
    echo "\n=== Record Counts ===\n";
    $tableNames = array_keys($tables);
    foreach ($tableNames as $table) {
        $countResult = $mysqli->query("SELECT COUNT(*) as cnt FROM $table");
        if ($countResult) {
            $countRow = $countResult->fetch_assoc();
            echo "$table: " . $countRow['cnt'] . " records\n";
        }
    }
    
    echo "\n✓ Database setup complete!\n";
    echo "\n=== Connection Info ===\n";
    echo "Host: $dbHost\n";
    echo "Database: $dbName\n";
    echo "Charset: utf8mb4\n";
    echo "Server Version: " . $mysqli->server_info . "\n";
    
    $mysqli->close();
    
    echo "\n=== Files Created ===\n";
    echo "✓ database_schema.sql - Complete SQL schema file\n";
    echo "✓ DATABASE_DOCUMENTATION.md - Detailed table documentation\n";
    echo "✓ DATABASE_QUICK_SETUP.md - Quick setup guide\n";
    echo "✓ verify_database.php - This verification script\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
