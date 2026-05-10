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

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$markAll = isset($_POST['all']) && $_POST['all'] === '1';
$currentUserId = (int)($user['id'] ?? 0);

if ($currentUserId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

if (!$markAll && $id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

try {
    if ($markAll) {
        $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        if (!$stmt) throw new Exception($mysqli->error);
        $stmt->bind_param('i', $currentUserId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        if (!$stmt) throw new Exception($mysqli->error);
        $stmt->bind_param('ii', $id, $currentUserId);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
