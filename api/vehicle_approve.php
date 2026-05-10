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

$checkStmt = $mysqli->prepare('SELECT vr.id, vr.user_id, vr.status, vr.date_use, vr.departure_date, vr.departure_time, vr.expected_arrival_date, vr.expected_arrival_time, vr.destination, vr.purpose, u.first_name, u.last_name, u.email FROM vehicle_requests vr LEFT JOIN users u ON u.id = vr.user_id WHERE vr.id = ? LIMIT 1');
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

$requesterEmail = (string)($row['email'] ?? '');
$requesterName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));

if (($row['status'] ?? '') === 'approved') {
    echo json_encode(['success' => true, 'message' => 'Vehicle request already approved']);
    exit;
}

$approveStmt = $mysqli->prepare("UPDATE vehicle_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
if (!$approveStmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}
$approveStmt->bind_param('ii', $user['id'], $requestId);
if (!$approveStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Approval failed: ' . $approveStmt->error]);
    exit;
}

// Create in-app notification for requester
try {
    $targetUserId = (int)($row['user_id'] ?? 0);
    if ($targetUserId > 0) {
        $notifStmt = $mysqli->prepare('INSERT INTO notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');
        if ($notifStmt) {
            $notifType = 'vehicle_approved';
            $notifTitle = 'Vehicle request approved';
            $notifBody = 'Your vehicle request to ' . (string)($row['destination'] ?? '') . ' on ' . (string)($row['date_use'] ?? '') . ' has been approved.';
            $notifStmt->bind_param('isiss', $targetUserId, $notifType, $requestId, $notifTitle, $notifBody);
            if (!$notifStmt->execute()) {
                error_log('Failed to insert vehicle approved notification: ' . $notifStmt->error);
            }
            $notifStmt->close();
        } else {
            error_log('Prepare notifications insert failed (vehicle approved): ' . $mysqli->error);
        }
    }
} catch (Throwable $e) {
    error_log('Failed to create vehicle approved in-app notification: ' . $e->getMessage());
}

echo json_encode(['success' => true, 'message' => 'Vehicle request approved']);

// Return response quickly, then send email notification to requester.
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

try {
    if ($requesterEmail !== '') {
        $subject = 'Vehicle Request Approved';
        $safeName = htmlspecialchars($requesterName !== '' ? $requesterName : $requesterEmail);
        $dateUse = htmlspecialchars((string)($row['date_use'] ?? ''));
        $departure = htmlspecialchars((string)($row['departure_date'] ?? '') . ' ' . (string)($row['departure_time'] ?? ''));
        $arrival = htmlspecialchars((string)($row['expected_arrival_date'] ?? '') . ' ' . (string)($row['expected_arrival_time'] ?? ''));
        $destination = htmlspecialchars((string)($row['destination'] ?? ''));
        $purpose = nl2br(htmlspecialchars((string)($row['purpose'] ?? '')));

        $body = '<!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width">'
            . '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:#f4f6f8;margin:0;padding:20px;}'
            . '.card{max-width:720px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(11,15,30,.08)}'
            . '.card-header{background:linear-gradient(90deg,#198754,#157347);padding:18px;color:#fff}'
            . '.card-body{padding:20px;color:#0b1220}'
            . '.list{background:#f8fafc;border-radius:8px;padding:12px;margin:12px 0}'
            . '.list-item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(11,15,30,.04)}'
            . '.list-item:last-child{border-bottom:0}'
            . '</style></head><body>'
            . '<div class="card">'
            . '<div class="card-header"><h2 style="margin:0;font-size:18px">DTI R2 Nueva Vizcaya</h2></div>'
            . '<div class="card-body">'
            . '<p style="margin-top:0">Hello ' . $safeName . ', your vehicle request has been <strong>approved</strong>.</p>'
            . '<div class="list">'
            . '<div class="list-item"><div style="font-weight:600">Date of use:</div><div>' . $dateUse . '</div></div>'
            . '<div class="list-item"><div style="font-weight:600">Departure:</div><div>' . $departure . '</div></div>'
            . '<div class="list-item"><div style="font-weight:600">Expected arrival:</div><div>' . $arrival . '</div></div>'
            . '<div class="list-item"><div style="font-weight:600">Destination:</div><div>' . $destination . '</div></div>'
            . '<div class="list-item"><div style="font-weight:600">Purpose:</div><div>' . $purpose . '</div></div>'
            . '</div>'
            . '</div></div></body></html>';

        $alt = "Vehicle Request Approved\n\nDate of use: " . (string)($row['date_use'] ?? '')
            . "\nDeparture: " . (string)($row['departure_date'] ?? '') . ' ' . (string)($row['departure_time'] ?? '')
            . "\nExpected arrival: " . (string)($row['expected_arrival_date'] ?? '') . ' ' . (string)($row['expected_arrival_time'] ?? '')
            . "\nDestination: " . (string)($row['destination'] ?? '')
            . "\nPurpose: " . (string)($row['purpose'] ?? '');

        $sent = send_email($requesterEmail, $subject, $body, $alt);
        if (!$sent) {
            error_log('Vehicle approval email send failed for request_id=' . $requestId . ', recipient=' . $requesterEmail);
        }
    } else {
        error_log('Vehicle approval email skipped: requester email is empty for request_id=' . $requestId);
    }
} catch (Throwable $e) {
    error_log('Failed to send vehicle approval email: ' . $e->getMessage());
}

