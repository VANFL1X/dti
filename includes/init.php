<?php
// Start session and ensure DB/table exists and CSRF token
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/db.php';
$mysqli = getDB();

// Create users table if not exists (MySQL syntax)
$createSql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(191) NOT NULL,
    last_name VARCHAR(191) NOT NULL,
    middle_name VARCHAR(191) DEFAULT NULL,
    suffix VARCHAR(64) DEFAULT NULL,
    birthdate DATE NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    division VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    avatar VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createSql)) {
    // If table creation fails, throw an exception so the app fails fast
    throw new Exception('Failed to create users table: ' . $mysqli->error);
}

// Ensure avatar column exists (for backward compatibility with older SQLite install)
$res = $mysqli->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($res && $res->num_rows === 0) {
    $mysqli->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
}

// Create activities table for Indicative Calendar
$createActivities = "CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    is_global TINYINT(1) NOT NULL DEFAULT 0,
    division_scope VARCHAR(120) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (start_datetime),
    INDEX (end_datetime),
    INDEX (division_scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createActivities)) {
    throw new Exception('Failed to create activities table: ' . $mysqli->error);
}

// Ensure `is_global` column exists for activities to distinguish indicative/global events
$actColsRes = $mysqli->query("SHOW COLUMNS FROM activities LIKE 'is_global'");
if ($actColsRes && $actColsRes->num_rows === 0) {
    $mysqli->query("ALTER TABLE activities ADD COLUMN is_global TINYINT(1) NOT NULL DEFAULT 0");
}

// Ensure `division_scope` exists to explicitly scope division activities
$scopeColsRes = $mysqli->query("SHOW COLUMNS FROM activities LIKE 'division_scope'");
if ($scopeColsRes && $scopeColsRes->num_rows === 0) {
    $mysqli->query("ALTER TABLE activities ADD COLUMN division_scope VARCHAR(120) DEFAULT NULL");
    $mysqli->query("ALTER TABLE activities ADD INDEX (division_scope)");
}

// Create supply_requests table for supply request feature
$createSupply = "CREATE TABLE IF NOT EXISTS supply_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item VARCHAR(191) NOT NULL,
    variant VARCHAR(191) NOT NULL,
    quantity INT NOT NULL,
    unit VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createSupply)) {
    throw new Exception('Failed to create supply_requests table: ' . $mysqli->error);
}

// Create ob_slips table for OB Slip requests
$createObSlips = "CREATE TABLE IF NOT EXISTS ob_slips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ob_type VARCHAR(16) NOT NULL,
    slip_date DATE NOT NULL,
    employee_name VARCHAR(191) NOT NULL,
    section_name VARCHAR(191) NOT NULL,
    purpose TEXT NOT NULL,
    destination TEXT NOT NULL,
    departure_time TIME NOT NULL,
    return_time TIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (slip_date),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createObSlips)) {
    throw new Exception('Failed to create ob_slips table: ' . $mysqli->error);
}

// Create freedom_wall_posts table for calendar thoughts and messages
$createFreedomWallPosts = "CREATE TABLE IF NOT EXISTS freedom_wall_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    author_name VARCHAR(191) NOT NULL,
    division_name VARCHAR(191) DEFAULT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createFreedomWallPosts)) {
    throw new Exception('Failed to create freedom_wall_posts table: ' . $mysqli->error);
}

// Create user_supplies table for inventory tracking (per-user inventory)
$createUserSupplies = "CREATE TABLE IF NOT EXISTS user_supplies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item VARCHAR(191) NOT NULL,
    variant VARCHAR(191) DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(64) DEFAULT NULL,
    threshold INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (item)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createUserSupplies)) {
    throw new Exception('Failed to create user_supplies table: ' . $mysqli->error);
}

// Create vehicles table to store per-vehicle capacities
$createVehicles = "CREATE TABLE IF NOT EXISTS vehicles (
    plate_no VARCHAR(191) PRIMARY KEY,
    capacity INT NOT NULL DEFAULT 6,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createVehicles)) {
    throw new Exception('Failed to create vehicles table: ' . $mysqli->error);
}

// Backward compatibility for older user_supplies schema
$userSupplyColumns = [];
$userSupplyColsRes = $mysqli->query("SHOW COLUMNS FROM user_supplies");
if ($userSupplyColsRes) {
    while ($col = $userSupplyColsRes->fetch_assoc()) {
        $userSupplyColumns[] = $col['Field'];
    }
}
if (!in_array('variant', $userSupplyColumns, true)) {
    $mysqli->query("ALTER TABLE user_supplies ADD COLUMN variant VARCHAR(191) DEFAULT NULL AFTER item");
}

// Create vehicle_requests table for vehicle request feature
$createVehicleRequests = "CREATE TABLE IF NOT EXISTS vehicle_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date_application DATE NOT NULL,
    date_use DATE NOT NULL,
    departure_date DATE NOT NULL,
    departure_time TIME NOT NULL,
    expected_arrival_date DATE NOT NULL,
    expected_arrival_time TIME NOT NULL,
    vehicle_plate_no VARCHAR(191) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    purpose TEXT NOT NULL,
    driver_name VARCHAR(191) NOT NULL,
    transportation_incharge VARCHAR(191) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    approved_by INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (date_use),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createVehicleRequests)) {
    throw new Exception('Failed to create vehicle_requests table: ' . $mysqli->error);
}

// Backward compatibility for older vehicle_requests schema
$vehicleColumns = [];
$vehicleColsRes = $mysqli->query("SHOW COLUMNS FROM vehicle_requests");
if ($vehicleColsRes) {
    while ($col = $vehicleColsRes->fetch_assoc()) {
        $vehicleColumns[] = $col['Field'];
    }
}
if (!in_array('status', $vehicleColumns, true)) {
    $mysqli->query("ALTER TABLE vehicle_requests ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending'");
}
if (!in_array('approved_by', $vehicleColumns, true)) {
    $mysqli->query("ALTER TABLE vehicle_requests ADD COLUMN approved_by INT DEFAULT NULL");
}
if (!in_array('approved_at', $vehicleColumns, true)) {
    $mysqli->query("ALTER TABLE vehicle_requests ADD COLUMN approved_at DATETIME DEFAULT NULL");
}

// Create passengers table for multiple passengers per vehicle request
$createPassengers = "CREATE TABLE IF NOT EXISTS passengers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    passenger_name VARCHAR(191) NOT NULL,
    INDEX (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createPassengers)) {
    throw new Exception('Failed to create passengers table: ' . $mysqli->error);
}

// Create notifications table for in-app notifications
$createNotifications = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    type VARCHAR(64) NOT NULL,
    ref_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (type),
    INDEX (is_read),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createNotifications)) {
    throw new Exception('Failed to create notifications table: ' . $mysqli->error);
}



// Backward compatibility: ensure notifications.user_id exists in older schemas
$notifCols = [];
$notifColsRes = $mysqli->query("SHOW COLUMNS FROM notifications");
if ($notifColsRes) {
    while ($col = $notifColsRes->fetch_assoc()) {
        $notifCols[] = $col['Field'];
    }
}
if (!in_array('user_id', $notifCols, true)) {
    $mysqli->query("ALTER TABLE notifications ADD COLUMN user_id INT DEFAULT NULL AFTER id");
    $mysqli->query("ALTER TABLE notifications ADD INDEX (user_id)");
}

// Create employee_events table for manually selected employee status (office/travel/business/leave)
$createEmployeeEvents = "CREATE TABLE IF NOT EXISTS employee_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    start_datetime DATETIME DEFAULT NULL,
    end_datetime DATETIME DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_status (user_id),
    INDEX (event_type),
    INDEX (start_datetime),
    INDEX (end_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createEmployeeEvents)) {
    throw new Exception('Failed to create employee_events table: ' . $mysqli->error);
}

// If a unique index on user_id exists from older installs, remove it so multiple
// manual status entries per user are preserved (we want history, not a single row).
$idxRes = $mysqli->query("SHOW INDEX FROM employee_events WHERE Key_name = 'uniq_user_status'");
if ($idxRes && $idxRes->num_rows > 0) {
    $mysqli->query("ALTER TABLE employee_events DROP INDEX uniq_user_status");
}

// Create claims_monitoring table for claim progress tracking
$createClaimsMonitoring = "CREATE TABLE IF NOT EXISTS claims_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    claim_ref VARCHAR(191) NOT NULL,
    received_eval_date DATETIME DEFAULT NULL,
    pd_approval_date DATETIME DEFAULT NULL,
    processing_date DATETIME DEFAULT NULL,
    cheque_date DATETIME DEFAULT NULL,
    remarks VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (claim_ref),
    INDEX (received_eval_date),
    INDEX (pd_approval_date),
    INDEX (processing_date),
    INDEX (cheque_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createClaimsMonitoring)) {
    throw new Exception('Failed to create claims_monitoring table: ' . $mysqli->error);
}

// Migration: ensure claims_monitoring columns exist for older installations
$claimsColsRes = $mysqli->query("SHOW COLUMNS FROM claims_monitoring");
$claimsCols = [];
if ($claimsColsRes) {
    while ($col = $claimsColsRes->fetch_assoc()) {
        $claimsCols[] = $col['Field'];
    }
}
if (!in_array('claim_ref', $claimsCols, true)) {
    $mysqli->query("ALTER TABLE claims_monitoring ADD COLUMN claim_ref VARCHAR(191) NOT NULL AFTER user_id");
    $mysqli->query("ALTER TABLE claims_monitoring ADD INDEX (claim_ref)");
}
if (!in_array('received_eval_date', $claimsCols, true)) {
    $mysqli->query("ALTER TABLE claims_monitoring ADD COLUMN received_eval_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE claims_monitoring ADD INDEX (received_eval_date)");
}
if (!in_array('pd_approval_date', $claimsCols, true)) {
    $mysqli->query("ALTER TABLE claims_monitoring ADD COLUMN pd_approval_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE claims_monitoring ADD INDEX (pd_approval_date)");
}
if (!in_array('processing_date', $claimsCols, true)) {
    $mysqli->query("ALTER TABLE claims_monitoring ADD COLUMN processing_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE claims_monitoring ADD INDEX (processing_date)");
}
if (!in_array('cheque_date', $claimsCols, true)) {
    $mysqli->query("ALTER TABLE claims_monitoring ADD COLUMN cheque_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE claims_monitoring ADD INDEX (cheque_date)");
}
if (!in_array('remarks', $claimsCols, true)) {
    $mysqli->query("ALTER TABLE claims_monitoring ADD COLUMN remarks VARCHAR(255) DEFAULT NULL");
}
if (!in_array('created_at', $claimsCols, true)) {
    $mysqli->query("ALTER TABLE claims_monitoring ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
}
if (!in_array('updated_at', $claimsCols, true)) {
    $mysqli->query("ALTER TABLE claims_monitoring ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

// Create procurement_monitoring table for PR and RFQ/Canvas workflow tracking
$createProcurementMonitoring = "CREATE TABLE IF NOT EXISTS procurement_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tool_id VARCHAR(191) NOT NULL,
    approved_pr_date DATETIME DEFAULT NULL,
    pd_approval_date DATETIME DEFAULT NULL,
    retrieval_quotation_date DATETIME DEFAULT NULL,
    abstract_canvas_date DATETIME DEFAULT NULL,
    preparation_po_date DATETIME DEFAULT NULL,
    issuance_po_date DATETIME DEFAULT NULL,
    remarks VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (tool_id),
    INDEX (approved_pr_date),
    INDEX (pd_approval_date),
    INDEX (retrieval_quotation_date),
    INDEX (abstract_canvas_date),
    INDEX (preparation_po_date),
    INDEX (issuance_po_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createProcurementMonitoring)) {
    throw new Exception('Failed to create procurement_monitoring table: ' . $mysqli->error);
}

// Migration: ensure procurement_monitoring columns exist for older installations
$procColsRes = $mysqli->query("SHOW COLUMNS FROM procurement_monitoring");
$procCols = [];
if ($procColsRes) {
    while ($col = $procColsRes->fetch_assoc()) {
        $procCols[] = $col['Field'];
    }
}
if (!in_array('tool_id', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN tool_id VARCHAR(191) NOT NULL AFTER user_id");
    $mysqli->query("ALTER TABLE procurement_monitoring ADD INDEX (tool_id)");
}
if (!in_array('approved_pr_date', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN approved_pr_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE procurement_monitoring ADD INDEX (approved_pr_date)");
}
if (!in_array('pd_approval_date', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN pd_approval_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE procurement_monitoring ADD INDEX (pd_approval_date)");
}
if (!in_array('retrieval_quotation_date', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN retrieval_quotation_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE procurement_monitoring ADD INDEX (retrieval_quotation_date)");
}
if (!in_array('abstract_canvas_date', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN abstract_canvas_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE procurement_monitoring ADD INDEX (abstract_canvas_date)");
}
if (!in_array('preparation_po_date', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN preparation_po_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE procurement_monitoring ADD INDEX (preparation_po_date)");
}
if (!in_array('issuance_po_date', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN issuance_po_date DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE procurement_monitoring ADD INDEX (issuance_po_date)");
}
if (!in_array('remarks', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN remarks VARCHAR(255) DEFAULT NULL");
}
if (!in_array('created_at', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
}
if (!in_array('updated_at', $procCols, true)) {
    $mysqli->query("ALTER TABLE procurement_monitoring ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

// Migration: Add start_datetime, end_datetime, notes columns if they don't exist (backward compatibility)
$eeColsRes = $mysqli->query("SHOW COLUMNS FROM employee_events");
$eeCols = [];
if ($eeColsRes) {
    while ($col = $eeColsRes->fetch_assoc()) {
        $eeCols[] = $col['Field'];
    }
}
if (!in_array('start_datetime', $eeCols, true)) {
    $mysqli->query("ALTER TABLE employee_events ADD COLUMN start_datetime DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE employee_events ADD INDEX (start_datetime)");
}
if (!in_array('end_datetime', $eeCols, true)) {
    $mysqli->query("ALTER TABLE employee_events ADD COLUMN end_datetime DATETIME DEFAULT NULL");
    $mysqli->query("ALTER TABLE employee_events ADD INDEX (end_datetime)");
}
if (!in_array('notes', $eeCols, true)) {
    $mysqli->query("ALTER TABLE employee_events ADD COLUMN notes VARCHAR(255) DEFAULT NULL");
}
if (!in_array('created_at', $eeCols, true)) {
    $mysqli->query("ALTER TABLE employee_events ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
}
if (!in_array('updated_at', $eeCols, true)) {
    $mysqli->query("ALTER TABLE employee_events ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

// Create user login logs table for activity monitoring graphs
$createLoginLogs = "CREATE TABLE IF NOT EXISTS user_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createLoginLogs)) {
    throw new Exception('Failed to create user_login_logs table: ' . $mysqli->error);
}

// Create report_deadlines table for managing report submission deadlines per division/user
$createReportDeadlines = "CREATE TABLE IF NOT EXISTS report_deadlines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    division VARCHAR(191) NOT NULL,
    user_id INT DEFAULT NULL,
    report_type VARCHAR(64) NOT NULL,
    deadline_date DATE NOT NULL,
    deadline_time TIME DEFAULT '17:00:00',
    notify_before_days INT NOT NULL DEFAULT 3,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    remarks TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (division),
    INDEX (user_id),
    INDEX (report_type),
    INDEX (deadline_date),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createReportDeadlines)) {
    throw new Exception('Failed to create report_deadlines table: ' . $mysqli->error);
}

// Create report_deadline_notifications table to track which notifications have been sent
$createReportDeadlineNotifications = "CREATE TABLE IF NOT EXISTS report_deadline_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deadline_id INT NOT NULL,
    user_id INT NOT NULL,
    notification_type VARCHAR(32) NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (deadline_id),
    INDEX (user_id),
    INDEX (notification_type),
    INDEX (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$mysqli->query($createReportDeadlineNotifications)) {
    throw new Exception('Failed to create report_deadline_notifications table: ' . $mysqli->error);
}

/**
 * Parse division field into a normalized array.
 * Supports legacy single division and comma-separated multi-division values.
 */
if (!function_exists('parse_user_divisions')) {
    function parse_user_divisions($divisionValue)
    {
        $raw = is_string($divisionValue) ? $divisionValue : '';
        if ($raw === '') return [];
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static function ($v) {
            return $v !== '';
        });
        return array_values(array_unique($parts));
    }
}

if (!function_exists('user_has_division')) {
    function user_has_division($user, $divisionName)
    {
        if (!is_array($user) || !isset($user['division'])) return false;
        return in_array((string)$divisionName, parse_user_divisions($user['division']), true);
    }
}

    // PHPMailer helper — use environment variables in production
    $mailConfig = [
        'smtp_host' => getenv('DTI_SMTP_HOST') ?: 'smtp.gmail.com',
        'smtp_port' => getenv('DTI_SMTP_PORT') ?: 587,
        'smtp_secure' => getenv('DTI_SMTP_SECURE') ?: 'tls',
        'smtp_username' => getenv('DTI_SMTP_USER') ?: 'nanteskenshin@gmail.com',
        'smtp_password' => getenv('DTI_SMTP_PASS') ?: 'dfxn vlaz rixy jsoc',
        'from_email' => getenv('DTI_FROM_EMAIL') ?: 'nanteskenshin@gmail.com',
        'from_name' => getenv('DTI_FROM_NAME') ?: 'DTI'
    ];

    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    } else {
        require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
        require_once __DIR__ . '/../PHPMailer/src/Exception.php';
    }

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    if (!function_exists('send_email')) {
        function send_email($to, $subject, $body, $altBody = '')
        {
            global $mailConfig;
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $mailConfig['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $mailConfig['smtp_username'];
                $mail->Password = $mailConfig['smtp_password'];
                $mail->SMTPSecure = $mailConfig['smtp_secure'];
                $mail->Port = $mailConfig['smtp_port'];

                $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);

                if (is_array($to)) {
                    foreach ($to as $addr => $name) {
                        if (is_int($addr)) {
                            $mail->addAddress($name);
                        } else {
                            $mail->addAddress($addr, $name);
                        }
                    }
                } else {
                    $mail->addAddress($to);
                }

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($body);

                $mail->send();
                return true;
            } catch (Exception $e) {
                error_log('Mail error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
                return false;
            }
        }
    }

