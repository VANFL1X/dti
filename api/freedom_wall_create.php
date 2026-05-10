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

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

if (mb_strlen($message) > 500) {
    echo json_encode(['success' => false, 'message' => 'Message must be 500 characters or less']);
    exit;
}

$authorName = 'Anonymous';
$divisionName = null;

$mysqli->query("DELETE FROM freedom_wall_posts WHERE created_at < (NOW() - INTERVAL 8 HOUR)");

$stmt = $mysqli->prepare("INSERT INTO freedom_wall_posts (user_id, author_name, division_name, message, created_at) VALUES (?, ?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}

$stmt->bind_param('isss', $user['id'], $authorName, $divisionName, $message);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Thought posted']);