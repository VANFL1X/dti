<?php
require_once __DIR__ . '/../includes/init.php';

$accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
$requestedWith = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
$wantsJson = (stripos($accept, 'application/json') !== false)
    || (stripos($contentType, 'application/json') !== false)
    || (strcasecmp($requestedWith, 'XMLHttpRequest') === 0);

if (!function_exists('status_response')) {
    function status_response($success, $message, $wantsJson, $redirectPath)
    {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => (bool)$success,
                'message' => (string)$message,
            ]);
            exit;
        }

        $_SESSION['status_flash'] = [
            'type' => $success ? 'success' : 'danger',
            'message' => (string)$message,
        ];
        header('Location: ' . $redirectPath);
        exit;
    }
}

$user = $_SESSION['user'] ?? null;
if (!$user) {
    status_response(false, 'Authentication required', $wantsJson, '../index.php');
}

$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    status_response(false, 'Invalid CSRF token', $wantsJson, '../tracker.php');
}

$eventType = strtolower(trim((string)($_POST['event_type'] ?? '')));
$start = trim((string)($_POST['start_datetime'] ?? ''));
$end = trim((string)($_POST['end_datetime'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));

$allowed = ['office', 'travel', 'business', 'leave'];
if (!in_array($eventType, $allowed, true)) {
    status_response(false, 'Invalid status', $wantsJson, '../tracker.php');
}

$startSql = null;
$endSql = null;

if ($start !== '') {
    $ts = strtotime($start);
    if ($ts === false) {
        status_response(false, 'Invalid start date/time', $wantsJson, '../tracker.php');
    }
    $startSql = date('Y-m-d H:i:s', $ts);
} else {
    $startSql = date('Y-m-d H:i:s');
}

if ($end !== '') {
    $te = strtotime($end);
    if ($te === false) {
        status_response(false, 'Invalid end date/time', $wantsJson, '../tracker.php');
    }
    $endSql = date('Y-m-d H:i:s', $te);
    if ($endSql < $startSql) {
        status_response(false, 'End date/time must be after start', $wantsJson, '../tracker.php');
    }
}

try {
    $uid = (int)$user['id'];
    // Insert a new manual status row to preserve history (do not overwrite previous entries)
    $stmt = $mysqli->prepare("INSERT INTO employee_events (user_id, event_type, start_datetime, end_datetime, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }
    $stmt->bind_param('issss', $uid, $eventType, $startSql, $endSql, $notes);
    if (!$stmt->execute()) {
        throw new Exception('Save failed: ' . $stmt->error);
    }
    $stmt->close();

    status_response(true, 'Status updated successfully.', $wantsJson, '../tracker.php');
} catch (Throwable $e) {
    status_response(false, $e->getMessage(), $wantsJson, '../tracker.php');
}
