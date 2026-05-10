<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

// Run daily (CLI or scheduler) to send birthday greetings.
try {
    $tz = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $tz);

    $month = (int)$now->format('n');
    $day = (int)$now->format('j');
    $dateLabel = $now->format('F j, Y');

    $users = [];
    $stmt = $mysqli->prepare("SELECT id, first_name, last_name, email, division, birthdate FROM users WHERE email IS NOT NULL AND email <> '' AND MONTH(birthdate) = ? AND DAY(birthdate) = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare birthday query: ' . $mysqli->error);
    }

    $stmt->bind_param('ii', $month, $day);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();

    $existsStmt = $mysqli->prepare("SELECT id FROM notifications WHERE user_id = ? AND type = 'birthday_greeting' AND DATE(created_at) = CURDATE() LIMIT 1");
    $notifStmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?, 'birthday_greeting', ?, ?, ?, 0, NOW())");

    if (!$existsStmt || !$notifStmt) {
        throw new Exception('Failed to prepare notification statements: ' . $mysqli->error);
    }

    $emailsSent = 0;
    $notificationsInserted = 0;
    $skipped = 0;

    foreach ($users as $user) {
        $uid = (int)$user['id'];
        $firstName = trim((string)($user['first_name'] ?? ''));
        $lastName = trim((string)($user['last_name'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName === '') {
            $fullName = 'Employee';
        }

        $recipientEmail = trim((string)($user['email'] ?? ''));
        if ($recipientEmail === '') {
            $skipped++;
            continue;
        }

        $existsStmt->bind_param('i', $uid);
        $existsStmt->execute();
        $existingRes = $existsStmt->get_result();
        $alreadySentToday = $existingRes && $existingRes->num_rows > 0;
        if ($existingRes) {
            $existingRes->free();
        }

        if ($alreadySentToday) {
            $skipped++;
            continue;
        }

        $subject = 'Happy Birthday, ' . ($firstName !== '' ? $firstName : $fullName) . '!';
        $title = 'Happy Birthday!';
        $bodyText = "Warmest birthday greetings from your DTI Family!\n\n"
            . "On your special day, we celebrate not only the passing of another year but also the dedication, passion, and positivity you bring to our organization. Your hard work and commitment continue to inspire those around you.\n\n"
            . "May this year bless you with good health, happiness, and continued success in all that you do. Enjoy your day-you truly deserve it!\n\n"
            . "Happy Birthday!";

        $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
            . '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f8fafc;padding:24px;color:#111827} .card{max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:24px} h2{margin:0 0 12px;color:#b45309} p{line-height:1.6;margin:0 0 14px} .muted{color:#6b7280;font-size:13px}</style>'
            . '</head><body><div class="card">'
            . '<h2>Happy Birthday, ' . htmlspecialchars($firstName !== '' ? $firstName : $fullName, ENT_QUOTES, 'UTF-8') . '!</h2>'
            . '<p>Warmest birthday greetings from your DTI Family!</p>'
            . '<p>On your special day, we celebrate not only the passing of another year but also the dedication, passion, and positivity you bring to our organization. Your hard work and commitment continue to inspire those around you.</p>'
            . '<p>May this year bless you with good health, happiness, and continued success in all that you do. Enjoy your day-you truly deserve it!</p>'
            . '<p><strong>Happy Birthday!</strong></p>'
            . '<p><strong>Date:</strong> ' . htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p class="muted">This is an automated birthday greeting.</p>'
            . '</div></body></html>';

        $sent = send_email($recipientEmail, $subject, $html, $bodyText);
        if (!$sent) {
            continue;
        }

        $zero = 0;
        $notifStmt->bind_param('iiss', $uid, $zero, $title, $bodyText);
        if ($notifStmt->execute()) {
            $notificationsInserted++;
        }

        $emailsSent++;
    }

    $existsStmt->close();
    $notifStmt->close();

    echo json_encode([
        'success' => true,
        'date' => $now->format('Y-m-d'),
        'birthday_users' => count($users),
        'emails_sent' => $emailsSent,
        'notifications_inserted' => $notificationsInserted,
        'skipped' => $skipped
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('Birthday greeting notify failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit(1);
}
