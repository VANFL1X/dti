<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['user'] ?? null;
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$purpose = trim($_POST['purpose'] ?? '');
$destination = trim($_POST['destination'] ?? '');
$start = trim($_POST['start_datetime'] ?? '');
$end = trim($_POST['end_datetime'] ?? '');
$requested_division = trim($_POST['division'] ?? '');

if ($purpose === '' || $destination === '' || $start === '' || $end === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate datetimes
$startTs = strtotime($start);
$endTs = strtotime($end);
if ($startTs === false || $endTs === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid date/time format']);
    exit;
}
if ($endTs < $startTs) {
    echo json_encode(['success' => false, 'message' => 'End date/time cannot be earlier than start date/time']);
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO activities (user_id, purpose, destination, start_datetime, end_datetime, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}
$sStart = date('Y-m-d H:i:s', $startTs);
$sEnd = date('Y-m-d H:i:s', $endTs);
// Determine is_global: if no division requested -> global (indicative).
$is_global = 0;
$division_scope = null;
if ($requested_division === '') {
    $is_global = 1;
} else {
    // Enforce that creator belongs to the requested division
    if (!user_has_division($user, $requested_division)) {
        echo json_encode(['success' => false, 'message' => 'You are not allowed to create activities for this division']);
        exit;
    }
    $division_scope = $requested_division;
}

// Insert including is_global flag
$stmt->close();
$stmt = $mysqli->prepare("INSERT INTO activities (user_id, purpose, destination, start_datetime, end_datetime, is_global, division_scope, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}
$stmt->bind_param('issssis', $user['id'], $purpose, $destination, $sStart, $sEnd, $is_global, $division_scope);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Activity created']);

