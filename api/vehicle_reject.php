<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['user'] ?? null;
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (!user_has_division($user, 'Admin Division')) {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$requestId = (int)($_POST['request_id'] ?? 0);
if ($requestId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request id']);
    exit;
}

$checkStmt = $mysqli->prepare('SELECT id, status FROM vehicle_requests WHERE id = ? LIMIT 1');
if (!$checkStmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}
$checkStmt->bind_param('i', $requestId);
$checkStmt->execute();
$res = $checkStmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Vehicle request not found']);
    exit;
}

if (($row['status'] ?? '') === 'rejected') {
    echo json_encode(['success' => true, 'message' => 'Vehicle request already rejected']);
    exit;
}

$rejectStmt = $mysqli->prepare("UPDATE vehicle_requests SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
if (!$rejectStmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}
$rejectStmt->bind_param('ii', $user['id'], $requestId);
if (!$rejectStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Rejection failed: ' . $rejectStmt->error]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Vehicle request rejected']);

