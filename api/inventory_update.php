<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['user'] ?? null;
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Only admins can update inventory
if (!user_has_division($user, 'Admin Division')) {
    echo json_encode(['success' => false, 'message' => 'Admin privileges required']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$targetUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$item = trim($_POST['item'] ?? '');
$variant = trim($_POST['variant'] ?? '');
$unit = trim($_POST['unit'] ?? '');
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : null;

if ($targetUserId <= 0 || $item === '' || $quantity === null || $quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
    exit;
}

// Upsert user_supplies
$stmt = $mysqli->prepare("SELECT id, quantity FROM user_supplies WHERE user_id = ? AND item = ? AND IFNULL(variant, '') = ? LIMIT 1");
$stmt->bind_param('iss', $targetUserId, $item, $variant);
$stmt->execute();
$res = $stmt->get_result();
$nowId = null;
$isUpdate = false;
if ($row = $res->fetch_assoc()) {
    $nowId = (int)$row['id'];
    $isUpdate = true;
    $upd = $mysqli->prepare("UPDATE user_supplies SET quantity = ?, unit = ?, updated_at = NOW() WHERE id = ?");
    $upd->bind_param('isi', $quantity, $unit, $nowId);
    $ok = $upd->execute();
    $upd->close();
} else {
    $ins = $mysqli->prepare("INSERT INTO user_supplies (user_id, item, variant, quantity, unit, threshold, updated_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
    $ins->bind_param('issis', $targetUserId, $item, $variant, $quantity, $unit);
    $ok = $ins->execute();
    $nowId = $mysqli->insert_id;
    $ins->close();
}
$stmt->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to save inventory']);
    exit;
}

// Always notify user after inventory submission (email + in-app)
try {
    // Fetch target user email
    $uStmt = $mysqli->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param('i', $targetUserId);
    $uStmt->execute();
    $uRes = $uStmt->get_result();
    $targetUser = $uRes->fetch_assoc();
    $uStmt->close();

    $variantLabel = $variant !== '' ? $variant : 'N/A';
    $unitLabel = $unit !== '' ? $unit : 'N/A';
    $actionLabel = $isUpdate ? 'Updated' : 'Added';
    $title = 'Inventory ' . $actionLabel . ': ' . $item;
    $body = 'Inventory entry ' . strtolower($actionLabel) . ' for ' . $item . ' (Variant: ' . $variantLabel . ') with quantity ' . (int)$quantity . ' ' . $unitLabel . '.';

    // Insert in-app notification
    $nStmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?, 'inventory', ?, ?, ?, 0, NOW())");
    $refId = $nowId ?? null;
    $nStmt->bind_param('iiss', $targetUserId, $refId, $title, $body);
    $nStmt->execute();
    $nStmt->close();

    // Send email if user has email
    if (!empty($targetUser['email'])) {
        $recipient = $targetUser['email'];
        $subject = $title;
        $displayName = htmlspecialchars(trim(($targetUser['first_name'] ?? '') . ' ' . ($targetUser['last_name'] ?? '')));
        $html = '<!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width">'
            . '<style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;color:#111} .card{max-width:600px;margin:0 auto;background:#fff;padding:18px;border-radius:10px;}</style>'
            . '</head><body><div class="card"><h3>' . htmlspecialchars($title) . '</h3>'
            . '<p>Hi ' . ($displayName !== '' ? $displayName : 'User') . ',</p>'
            . '<p>Your inventory was ' . strtolower($actionLabel) . ' by admin:</p>'
            . '<ul>'
            . '<li><strong>Item:</strong> ' . htmlspecialchars($item) . '</li>'
            . '<li><strong>Variant:</strong> ' . htmlspecialchars($variantLabel) . '</li>'
            . '<li><strong>Quantity:</strong> ' . (int)$quantity . ' ' . htmlspecialchars($unitLabel) . '</li>'
            . '</ul>'
            . '<p>— DTI</p></div></body></html>';
        $alt = 'Inventory ' . strtolower($actionLabel) . ': Item ' . $item . ', Variant ' . $variantLabel . ', Quantity ' . (int)$quantity . ' ' . $unitLabel;
        send_email($recipient, $subject, $html, $alt);
    }
} catch (Throwable $e) {
    error_log('Inventory notification failed: ' . $e->getMessage());
}

echo json_encode(['success' => true, 'message' => $isUpdate ? 'Inventory updated' : 'Inventory added']);
