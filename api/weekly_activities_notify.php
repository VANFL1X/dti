<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

// Run this script from CLI or via scheduler every Monday 07:00 Asia/Manila (PHT)
try {
    $tz = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $tz);

    // For a Monday run we want the previous week: Monday - Sunday
    $weekStart = new DateTime('last monday', $tz);
    $weekStart->setTime(0, 0, 0);
    $weekEnd = new DateTime('last sunday', $tz);
    $weekEnd->setTime(23, 59, 59);

    $startStr = $weekStart->format('Y-m-d H:i:s');
    $endStr = $weekEnd->format('Y-m-d H:i:s');

    // Fetch activities (global and division-scoped) for the week
    $activities = [];
    $stmt = $mysqli->prepare("SELECT a.id, a.purpose, a.destination, a.start_datetime, a.end_datetime, a.division_scope, u.first_name, u.last_name, u.division as user_division
        FROM activities a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE a.start_datetime BETWEEN ? AND ?
        ORDER BY a.start_datetime ASC");
    if ($stmt) {
        $stmt->bind_param('ss', $startStr, $endStr);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $activities[] = $r;
        }
        $stmt->close();
    }

    // Fetch approved vehicle requests falling in the week by departure_date
    $vehicles = [];
    $vstmt = $mysqli->prepare("SELECT vr.id, vr.purpose, vr.destination, vr.departure_date, vr.departure_time, vr.expected_arrival_date, vr.expected_arrival_time, u.first_name, u.last_name
        FROM vehicle_requests vr
        LEFT JOIN users u ON u.id = vr.user_id
        WHERE vr.status = 'approved'
        AND vr.departure_date BETWEEN ? AND ?
        ORDER BY vr.departure_date ASC, vr.departure_time ASC");
    if ($vstmt) {
        $vstart = $weekStart->format('Y-m-d');
        $vend = $weekEnd->format('Y-m-d');
        $vstmt->bind_param('ss', $vstart, $vend);
        $vstmt->execute();
        $vres = $vstmt->get_result();
        while ($r = $vres->fetch_assoc()) {
            $vehicles[] = $r;
        }
        $vstmt->close();
    }

    // Build summary HTML
    $periodLabel = $weekStart->format('F j') . ' - ' . $weekEnd->format('j, Y');
    $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
        . '<style>body{font-family:Arial,Helvetica,sans-serif;color:#0b1220;padding:18px}h2{margin-top:0} .item{margin:10px 0;padding:10px;border:1px solid #eee;border-radius:6px}</style></head><body>';
    $html .= '<h2>Weekly activities summary — ' . htmlspecialchars($periodLabel) . '</h2>';

    if (empty($activities) && empty($vehicles)) {
        $html .= '<p>No indicative activities or approved vehicle requests for this period.</p>';
    } else {
        if (!empty($activities)) {
            $html .= '<h3>Indicative activities</h3>';
            foreach ($activities as $a) {
                $creator = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'Unknown';
                // Determine event division: prefer explicit division_scope, fallback to user's division (first CSV value)
                $eventDivision = trim((string)($a['division_scope'] ?? ''));
                if ($eventDivision === '') {
                    $eventDivision = trim((string)($a['user_division'] ?? ''));
                    if (strpos($eventDivision, ',') !== false) {
                        $parts = array_map('trim', explode(',', $eventDivision));
                        $eventDivision = (string)($parts[0] ?? '');
                    }
                }
                $html .= '<div class="item"><div><strong>' . htmlspecialchars($a['purpose']) . '</strong></div>'
                    . '<div>' . htmlspecialchars($a['start_datetime']) . ' to ' . htmlspecialchars($a['end_datetime']) . '</div>'
                    . '<div>Division: ' . htmlspecialchars($eventDivision) . '</div>'
                    . '<div>Destination: ' . htmlspecialchars($a['destination'] ?? '') . '</div>'
                    . '<div>Posted by: ' . htmlspecialchars($creator) . '</div></div>';
            }
        }

        if (!empty($vehicles)) {
            $html .= '<h3>Approved vehicle requests</h3>';
            foreach ($vehicles as $v) {
                $creator = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '')) ?: 'Unknown';
                $start = ($v['departure_date'] ?? '') . ' ' . ($v['departure_time'] ?? '');
                $end = ($v['expected_arrival_date'] ?? '') . ' ' . ($v['expected_arrival_time'] ?? '');
                $html .= '<div class="item"><div><strong>' . htmlspecialchars($v['purpose']) . '</strong></div>'
                    . '<div>' . htmlspecialchars($start) . ' to ' . htmlspecialchars($end) . '</div>'
                    . '<div>Division: ' . htmlspecialchars((string)($v['division'] ?? '')) . '</div>'
                    . '<div>Destination: ' . htmlspecialchars($v['destination'] ?? '') . '</div>'
                    . '<div>Requested by: ' . htmlspecialchars($creator) . '</div></div>';
            }
        }
    }

    $html .= '</body></html>';
    $alt = "Weekly activities summary: " . $periodLabel;

    // Build plaintext summary (used for in-app notification body and email alt)
    $textBody = 'Weekly activities summary — ' . $periodLabel . "\n\n";
    if (empty($activities) && empty($vehicles)) {
        $textBody .= "No indicative activities or approved vehicle requests for this period.\n";
    } else {
        if (!empty($activities)) {
            $textBody .= "Indicative activities:\n";
            foreach ($activities as $a) {
                $creator = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'Unknown';
                $textBody .= '- ' . ($a['purpose'] ?? '') . ' — ' . ($a['start_datetime'] ?? '') . ' to ' . ($a['end_datetime'] ?? '') . '; Destination: ' . ($a['destination'] ?? '') . '; Posted by: ' . $creator . "\n";
            }
            $textBody .= "\n";
        }

        if (!empty($vehicles)) {
            $textBody .= "Approved vehicle requests:\n";
            foreach ($vehicles as $v) {
                $creator = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '')) ?: 'Unknown';
                $start = ($v['departure_date'] ?? '') . ' ' . ($v['departure_time'] ?? '');
                $end = ($v['expected_arrival_date'] ?? '') . ' ' . ($v['expected_arrival_time'] ?? '');
                $textBody .= '- ' . ($v['purpose'] ?? '') . ' — ' . $start . ' to ' . $end . '; Destination: ' . ($v['destination'] ?? '') . '; Requested by: ' . $creator . "\n";
            }
            $textBody .= "\n";
        }
    }

    // Fetch recipients: all users with email
    $users = [];
    $ures = $mysqli->query("SELECT id, first_name, last_name, email FROM users WHERE email IS NOT NULL AND email <> ''");
    if ($ures) {
        while ($u = $ures->fetch_assoc()) {
            $users[] = $u;
        }
    }

    $emailsSent = 0;
    $notificationsInserted = 0;

    // Prepare statements: insert notification and check existing weekly notification
    $notifStmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?, 'weekly_activities', ?, ?, ?, 0, NOW())");
    $existsStmt = $mysqli->prepare("SELECT id FROM notifications WHERE user_id = ? AND type = 'weekly_activities' AND title = ? LIMIT 1");

    foreach ($users as $user) {
        $uid = (int)$user['id'];
        $toEmail = (string)$user['email'];
        $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $toEmail;

        // Skip if this user already received this period's weekly notification
        $title = 'Weekly activities: ' . $periodLabel;
        $skip = false;
        if ($existsStmt) {
            $existsStmt->bind_param('is', $uid, $title);
            $existsStmt->execute();
            $er = $existsStmt->get_result();
            if ($er && $er->num_rows > 0) {
                $skip = true;
            }
            if ($er) $er->free();
        }

        if (!$skip && $notifStmt) {
            $notifBody = mb_substr(trim(preg_replace('/\s+/', ' ', $textBody)), 0, 800);
            $zero = 0;
            $notifStmt->bind_param('iiss', $uid, $zero, $title, $notifBody);
            if ($notifStmt->execute()) {
                $notificationsInserted++;
            }
        }

        // Send email only if not skipped (avoid duplicate sends)
        $subject = 'Weekly activities — ' . $periodLabel;
        if (!$skip) {
            $sent = send_email($toEmail, $subject, $html, $textBody);
            if ($sent) $emailsSent++;
        }
    }

    if ($notifStmt) $notifStmt->close();

    echo json_encode(['success' => true, 'week_start' => $startStr, 'week_end' => $endStr, 'emails_sent' => $emailsSent, 'notifications_inserted' => $notificationsInserted], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('Weekly notify failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit(1);
}
