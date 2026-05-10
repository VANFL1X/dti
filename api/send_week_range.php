<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $tz = new DateTimeZone('Asia/Manila');

    $start = trim((string)($_GET['start'] ?? $_POST['start'] ?? ''));
    $end = trim((string)($_GET['end'] ?? $_POST['end'] ?? ''));
    $force = isset($_GET['force']) || isset($_POST['force']);

    // Support CLI: parse argv like start=YYYY-MM-DD end=YYYY-MM-DD force=1
    if (PHP_SAPI === 'cli') {
        global $argv;
        foreach (array_slice($argv, 1) as $a) {
            if (strpos($a, '=') !== false) {
                list($k, $v) = explode('=', $a, 2);
                $k = trim($k); $v = trim($v);
                if ($k === 'start') $start = $v;
                if ($k === 'end') $end = $v;
                if ($k === 'force') $force = true;
            }
        }
    }

    if ($start === '' || $end === '') {
        echo json_encode(['success' => false, 'message' => 'start and end required (YYYY-MM-DD)']);
        exit(1);
    }

    $ws = DateTime::createFromFormat('Y-m-d', $start, $tz);
    $we = DateTime::createFromFormat('Y-m-d', $end, $tz);
    if (!$ws || !$we) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit(1);
    }
    $ws->setTime(0,0,0);
    $we->setTime(23,59,59);

    // Build summary
    $periodLabel = $ws->format('F j') . ' - ' . $we->format('j, Y');

    // Reuse logic from weekly builder
    $activities = [];
    $stmt = $mysqli->prepare("SELECT a.id, a.purpose, a.destination, a.start_datetime, a.end_datetime, a.division_scope, u.first_name, u.last_name, u.division as user_division FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.start_datetime BETWEEN ? AND ? ORDER BY a.start_datetime ASC");
    if ($stmt) {
        $s = $ws->format('Y-m-d H:i:s'); $e = $we->format('Y-m-d H:i:s');
        $stmt->bind_param('ss', $s, $e);
        $stmt->execute(); $res = $stmt->get_result(); while ($r = $res->fetch_assoc()) $activities[] = $r; $stmt->close();
    }

    $vehicles = [];
    $vstmt = $mysqli->prepare("SELECT vr.id, vr.purpose, vr.destination, vr.departure_date, vr.departure_time, vr.expected_arrival_date, vr.expected_arrival_time, u.first_name, u.last_name, u.division FROM vehicle_requests vr LEFT JOIN users u ON u.id = vr.user_id WHERE vr.status = 'approved' AND vr.departure_date BETWEEN ? AND ? ORDER BY vr.departure_date ASC, vr.departure_time ASC");
    if ($vstmt) {
        $vs = $ws->format('Y-m-d'); $ve = $we->format('Y-m-d');
        $vstmt->bind_param('ss', $vs, $ve);
        $vstmt->execute(); $vres = $vstmt->get_result(); while ($r = $vres->fetch_assoc()) $vehicles[] = $r; $vstmt->close();
    }

    // Compose HTML and plaintext
    $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><style>body{font-family:Arial,Helvetica,sans-serif;color:#0b1220;padding:18px}h2{margin-top:0}.item{margin:10px 0;padding:10px;border:1px solid #eee;border-radius:6px}</style></head><body>';
    $html .= '<h2>Weekly activities summary — ' . htmlspecialchars($periodLabel) . '</h2>';
    if (empty($activities) && empty($vehicles)) {
        $html .= '<p>No activities or vehicle requests for this period.</p>';
    } else {
        if (!empty($activities)) { $html .= '<h3>Activities</h3>'; foreach ($activities as $a) { $creator = trim(($a['first_name'] ?? '').' '.($a['last_name'] ?? '')) ?: 'Unknown'; $eventDivision = trim((string)($a['division_scope'] ?? '')) ?: trim((string)($a['user_division'] ?? '')); $html .= '<div class="item"><div><strong>' . htmlspecialchars($a['purpose']) . '</strong></div><div>' . htmlspecialchars($a['start_datetime']) . ' to ' . htmlspecialchars($a['end_datetime']) . '</div><div>Division: ' . htmlspecialchars($eventDivision) . '</div><div>Destination: ' . htmlspecialchars($a['destination'] ?? '') . '</div><div>Posted by: ' . htmlspecialchars($creator) . '</div></div>'; } }
    if (!empty($vehicles)) { $html .= '<h3>Approved vehicle requests</h3>'; foreach ($vehicles as $v) { $creator = trim(($v['first_name'] ?? '').' '.($v['last_name'] ?? '')) ?: 'Unknown'; $startt = ($v['departure_date'] ?? '') . ' ' . ($v['departure_time'] ?? ''); $endd = ($v['expected_arrival_date'] ?? '') . ' ' . ($v['expected_arrival_time'] ?? ''); $html .= '<div class="item"><div><strong>' . htmlspecialchars($v['purpose']) . '</strong></div><div>' . htmlspecialchars($startt) . ' to ' . htmlspecialchars($endd) . '</div><div>Division: ' . htmlspecialchars((string)($v['division'] ?? '')) . '</div><div>Destination: ' . htmlspecialchars($v['destination'] ?? '') . '</div><div>Requested by: ' . htmlspecialchars($creator) . '</div></div>'; } }
    }
    $html .= '</body></html>';

    $text = 'Weekly activities summary — ' . $periodLabel . "\n\n";
    if (empty($activities) && empty($vehicles)) { $text .= "No activities for this period.\n"; } else {
        if (!empty($activities)) { $text .= "Activities:\n"; foreach ($activities as $a) { $creator = trim(($a['first_name'] ?? '').' '.($a['last_name'] ?? '')) ?: 'Unknown'; $eventDivision = trim((string)($a['division_scope'] ?? '')) ?: trim((string)($a['user_division'] ?? '')); $text .= '- ' . ($a['purpose'] ?? '') . ' — ' . ($a['start_datetime'] ?? '') . ' to ' . ($a['end_datetime'] ?? '') . '; Division: ' . $eventDivision . '; Destination: ' . ($a['destination'] ?? '') . '; Posted by: ' . $creator . "\n"; } $text .= "\n"; }
        if (!empty($vehicles)) { $text .= "Approved vehicle requests:\n"; foreach ($vehicles as $v) { $creator = trim(($v['first_name'] ?? '').' '.($v['last_name'] ?? '')) ?: 'Unknown'; $startt = ($v['departure_date'] ?? '') . ' ' . ($v['departure_time'] ?? ''); $endd = ($v['expected_arrival_date'] ?? '') . ' ' . ($v['expected_arrival_time'] ?? ''); $text .= '- ' . ($v['purpose'] ?? '') . ' — ' . $startt . ' to ' . $endd . '; Division: ' . ($v['division'] ?? '') . '; Destination: ' . ($v['destination'] ?? '') . '; Requested by: ' . $creator . "\n"; } $text .= "\n"; }
    }

    // Send to users
    $title = 'Weekly activities: ' . $periodLabel;
    $emailsSent = 0; $notifs = 0;
    $users = $mysqli->query("SELECT id, email FROM users WHERE email IS NOT NULL AND email <> ''");
    $insert = $mysqli->prepare("INSERT INTO notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?, 'weekly_activities', 0, ?, ?, 0, NOW())");
    $check = $mysqli->prepare("SELECT id FROM notifications WHERE user_id = ? AND title = ? LIMIT 1");
    while ($u = $users->fetch_assoc()) {
        $uid = (int)$u['id']; $email = (string)$u['email'];
        $already = false;
        if (!$force && $check) { $check->bind_param('is', $uid, $title); $check->execute(); $cr = $check->get_result(); if ($cr && $cr->num_rows > 0) $already = true; if ($cr) $cr->free(); }
        if (!$already && $insert) { $plain = mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 2000); $insert->bind_param('iss', $uid, $title, $plain); if ($insert->execute()) $notifs++; }
        if (!$already) { $sent = send_email($email, $title, $html, $text); if ($sent) $emailsSent++; }
    }
    if ($insert) $insert->close(); if ($check) $check->close();

    echo json_encode(['success' => true, 'period' => $periodLabel, 'emails_sent' => $emailsSent, 'notifications_inserted' => $notifs], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('send_week_range error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit(1);
}
