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

$item = trim($_POST['item'] ?? '');
$variant = trim($_POST['variant'] ?? '');
$quantity = $_POST['quantity'] ?? '';
$unit = trim($_POST['unit'] ?? '');

$variantItems = [
    'Bond Paper','Photo Paper','Ink','Folder','Envelope','Sticker Paper',
    'Tape','Tissue','Toner Cart','Toner Cartridge','Wrapping Paper'
];
$variantRequired = in_array($item, $variantItems, true);

if ($item === '' || $unit === '' || $quantity === '' || ($variantRequired && $variant === '')) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!is_numeric($quantity) || (int)$quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantity must be a positive number']);
    exit;
}

$qty = (int)$quantity;

$stmt = $mysqli->prepare("INSERT INTO supply_requests (user_id, item, variant, quantity, unit, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}
$stmt->bind_param('issis', $user['id'], $item, $variant, $qty, $unit);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    exit;
}

// respond quickly to client before performing slower tasks (like sending email)
$response = ['success' => true, 'message' => 'Supply request submitted'];
echo json_encode($response);

// attempt to flush response and close connection so user sees quick submit
ignore_user_abort(true);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_end_flush();
    @ob_flush();
    flush();
}

// notify requester by email with styled HTML (runs after response is sent)
try {
    $recipient = $user['email'] ?? null;
    if ($recipient) {
        $subject = 'Supply Request Submitted';

        $displayName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        $division = htmlspecialchars((string)($user['division'] ?? ''));
        $itemDisp = htmlspecialchars($item ?: '');
        $variantDisp = htmlspecialchars($variant ?: '');
        $quantityDisp = htmlspecialchars((string)$qty . ' ' . $unit);

        $body = '<!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width">'
            . '<style>'
            . 'body{font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background:#f4f6f8; margin:0; padding:20px;}'
            . '.card{max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(11,15,30,0.08)}'
            . '.card-header{background:linear-gradient(90deg,#2563EB,#6F42C1);padding:18px;color:#fff}'
            . '.card-body{padding:20px;color:#0b1220}'
            . '.meta{color:#6b7280;font-size:13px;margin-bottom:12px}'
            . '.list{background:#f8fafc;border-radius:8px;padding:12px;margin:12px 0}'
            . '.list-item{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(11,15,30,0.04)}'
            . '.list-item:last-child{border-bottom:0}'
            . '</style></head><body>'
            . '<div class="card">'
            . '<div class="card-header"><h2 style="margin:0;font-size:18px">DTI R2 Nueva Vizcaya</h2></div>'
            . '<div class="card-body">'
            . '<div class="list">'
            . '<div class="list-item"><div style="font-weight:600">Division:</div><div> ' . ($division !== '' ? $division : '—') . '</div></div>'
            . '<div class="list-item"><div style="font-weight:600">Requested by:</div><div> ' . htmlspecialchars($recipient) . '</div></div>'
            . '<div class="list-item"><div style="font-weight:600">Item:</div><div> ' . $itemDisp . '</div></div>'
            . ($variantDisp !== '' ? '<div class="list-item"><div style="font-weight:600">Variant:</div><div> ' . $variantDisp . '</div></div>' : '')
            . '<div class="list-item"><div style="font-weight:600">Quantity:</div><div> ' . $quantityDisp . '</div></div>'
            . '</div>'
            . '</div></div></body></html>';

        $alt = "Supply Request Submitted\n\nDivision: " . strip_tags($division) . "\nRequested by: " . $recipient . "\nItem: " . strip_tags($itemDisp);
        if ($variantDisp !== '') {
            $alt .= "\nVariant: " . strip_tags($variantDisp);
        }
        $alt .= "\nQuantity: " . strip_tags($quantityDisp);

        send_email($recipient, $subject, $body, $alt);

        // notify Admin Division users
        try {
            $adminDivision = 'Admin Division';
            $admStmt = $mysqli->prepare("SELECT email FROM users WHERE email <> '' AND FIND_IN_SET(?, REPLACE(division, ', ', ',')) > 0");
            if ($admStmt) {
                $admStmt->bind_param('s', $adminDivision);
                $admStmt->execute();
                $admRes = $admStmt->get_result();
                while ($admRow = $admRes->fetch_assoc()) {
                    $adminEmail = $admRow['email'] ?? null;
                    if ($adminEmail) {
                        $adminSubject = 'New supply request submitted';
                        $adminItemList = '';
                        $adminItemList .= '<div class="list-item"><div style="font-weight:600">Division:</div><div>' . ($division !== '' ? $division : '—') . '</div></div>';
                        $adminItemList .= '<div class="list-item"><div style="font-weight:600">Requested by:</div><div>' . htmlspecialchars($recipient) . '</div></div>';
                        $adminItemList .= '<div class="list-item"><div style="font-weight:600">Item:</div><div>' . $itemDisp . '</div></div>';
                        $adminItemList .= ($variantDisp !== '' ? '<div class="list-item"><div style="font-weight:600">Variant:</div><div>' . $variantDisp . '</div></div>' : '');
                        $adminItemList .= '<div class="list-item"><div style="font-weight:600">Quantity:</div><div>' . $quantityDisp . '</div></div>';

                        $adminBody = '<!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width">'
                            . '<style>'
                            . 'body{font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background:#f4f6f8; margin:0; padding:20px;}'
                            . '.card{max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(11,15,30,0.08)}'
                            . '.card-header{background:linear-gradient(90deg,#2563EB,#6F42C1);padding:18px;color:#fff}'
                            . '.card-body{padding:20px;color:#0b1220}'
                            . '.meta{color:#6b7280;font-size:13px;margin-bottom:12px}'
                            . '.list{background:#f8fafc;border-radius:8px;padding:12px;margin:12px 0}'
                            . '.list-item{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(11,15,30,0.04)}'
                            . '.list-item:last-child{border-bottom:0}'
                            . '</style></head><body>'
                            . '<div class="card">'
                            . '<div class="card-header"><h2 style="margin:0;font-size:18px">DTI R2 Nueva Vizcaya</h2></div>'
                            . '<div class="card-body">'
                            . '<p class="meta">A new supply request was submitted by ' . $displayName . ' (' . htmlspecialchars($recipient) . ').</p>'
                            . '<div class="list">'
                            . $adminItemList
                            . '</div>'
                            . '</div></div></body></html>';
                        send_email($adminEmail, $adminSubject, $adminBody);
                    }
                }
                $admStmt->close();
            }
        } catch (Throwable $e) {
            error_log('Failed to notify admin about supply request: ' . $e->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('Failed to send supply notification: ' . $e->getMessage());
}

