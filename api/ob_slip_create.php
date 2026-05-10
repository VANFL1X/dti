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

$obType = strtoupper(trim((string)($_POST['ob_type'] ?? '')));
$date = trim((string)($_POST['date'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$section = trim((string)($_POST['section'] ?? ''));
$purpose = trim((string)($_POST['purpose'] ?? ''));
$destination = trim((string)($_POST['destination'] ?? ''));
$departureTime = trim((string)($_POST['departure_time'] ?? ''));
$returnTime = trim((string)($_POST['return_time'] ?? ''));

$allowedTypes = ['OFFICIAL', 'PERSONAL'];
if (!in_array($obType, $allowedTypes, true)) {
    echo json_encode(['success' => false, 'message' => 'Please select Official or Personal']);
    exit;
}

if ($date === '' || $name === '' || $section === '' || $purpose === '' || $destination === '' || $departureTime === '' || $returnTime === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $departureTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $returnTime)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format']);
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO ob_slips (user_id, ob_type, slip_date, employee_name, section_name, purpose, destination, departure_time, return_time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}

$uid = (int)$user['id'];
$stmt->bind_param('issssssss', $uid, $obType, $date, $name, $section, $purpose, $destination, $departureTime, $returnTime);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'OB Slip submitted']);
