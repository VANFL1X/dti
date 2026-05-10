<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$tz = new DateTimeZone('Asia/Manila');

function build_week_summary_for_range($mysqli, DateTime $weekStart, DateTime $weekEnd)
{
    $startStr = $weekStart->format('Y-m-d H:i:s');
    $endStr = $weekEnd->format('Y-m-d H:i:s');

    // Activities
    $activities = [];
    $stmt = $mysqli->prepare("SELECT a.id, a.purpose, a.destination, a.start_datetime, a.end_datetime, a.division_scope, u.first_name, u.last_name, u.division as user_division
        FROM activities a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE a.start_datetime BETWEEN ? AND ?
        ORDER BY a.start_datetime ASC");
    if ($stmt) {
        $s = $startStr; $e = $endStr;
        $stmt->bind_param('ss', $s, $e);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $activities[] = $r;
        $stmt->close();
    }

    // Vehicles
    $vehicles = [];
    $vstmt = $mysqli->prepare("SELECT vr.id, vr.purpose, vr.destination, vr.departure_date, vr.departure_time, vr.expected_arrival_date, vr.expected_arrival_time, u.first_name, u.last_name, u.division
        FROM vehicle_requests vr
        LEFT JOIN users u ON u.id = vr.user_id
        WHERE vr.status = 'approved'
        AND vr.departure_date BETWEEN ? AND ?
        ORDER BY vr.departure_date ASC, vr.departure_time ASC");
    if ($vstmt) {
        $vs = $weekStart->format('Y-m-d'); $ve = $weekEnd->format('Y-m-d');
        $vstmt->bind_param('ss', $vs, $ve);
        $vstmt->execute();
        $vres = $vstmt->get_result();
        while ($r = $vres->fetch_assoc()) $vehicles[] = $r;
        $vstmt->close();
    }

    $periodLabel = $weekStart->format('F j') . ' - ' . $weekEnd->format('j, Y');

    // HTML summary (small, re-using existing style)
    $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
        . '<style>body{font-family:Arial,Helvetica,sans-serif;color:#0b1220;padding:18px}h2{margin-top:0} .item{margin:10px 0;padding:10px;border:1px solid #eee;border-radius:6px}</style></head><body>';
    $html .= '<h2>Weekly activities summary — ' . htmlspecialchars($periodLabel) . '</h2>';

    if (empty($activities) && empty($vehicles)) {
        $html .= '<p>No indicative activities or approved vehicle requests for this period.</p>';
    } else {
        if (!empty($activities)) {
            $html .= '<h3>Activities</h3>';
            foreach ($activities as $a) {
                $creator = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'Unknown';
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

    // Build plaintext
    $text = 'Weekly activities summary — ' . $periodLabel . "\n\n";
    if (empty($activities) && empty($vehicles)) {
        $text .= "No indicative activities or approved vehicle requests for this period.\n";
    } else {
        if (!empty($activities)) {
            $text .= "Activities:\n";
            foreach ($activities as $a) {
                $creator = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'Unknown';
                $eventDivision = trim((string)($a['division_scope'] ?? '')) ?: trim((string)($a['user_division'] ?? ''));
                $text .= '- ' . ($a['purpose'] ?? '') . ' — ' . ($a['start_datetime'] ?? '') . ' to ' . ($a['end_datetime'] ?? '') . '; Division: ' . $eventDivision . '; Destination: ' . ($a['destination'] ?? '') . '; Posted by: ' . $creator . "\n";
            }
            $text .= "\n";
        }
        if (!empty($vehicles)) {
            $text .= "Approved vehicle requests:\n";
            foreach ($vehicles as $v) {
                $creator = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '')) ?: 'Unknown';
                $start = ($v['departure_date'] ?? '') . ' ' . ($v['departure_time'] ?? '');
                $end = ($v['expected_arrival_date'] ?? '') . ' ' . ($v['expected_arrival_time'] ?? '');
                $text .= '- ' . ($v['purpose'] ?? '') . ' — ' . $start . ' to ' . $end . '; Division: ' . ($v['division'] ?? '') . '; Destination: ' . ($v['destination'] ?? '') . '; Requested by: ' . $creator . "\n";
            }
            $text .= "\n";
        }
    }

    return ['html' => $html, 'text' => $text, 'periodLabel' => $periodLabel];
}

// 1) Update existing weekly_activities notifications: regenerate plaintext body based on notification.created_at
$updated = 0;
$sel = $mysqli->query("SELECT id, user_id, title, created_at FROM notifications WHERE type = 'weekly_activities'");
if ($sel) {
    $upStmt = $mysqli->prepare("UPDATE notifications SET body = ? WHERE id = ?");
    while ($row = $sel->fetch_assoc()) {
        $nid = (int)$row['id'];
        $created = $row['created_at'];
        $dt = new DateTime($created, $tz);
        $ws = clone $dt; $ws->modify('last monday')->setTime(0,0,0);
        $we = clone $dt; $we->modify('last sunday')->setTime(23,59,59);
        $summary = build_week_summary_for_range($mysqli, $ws, $we);
        $clean = mb_substr(trim(preg_replace('/\s+/', ' ', $summary['text'])), 0, 2000);
        if ($upStmt) {
            $upStmt->bind_param('si', $clean, $nid);
            if ($upStmt->execute()) $updated++;
        }
    }
    if ($upStmt) $upStmt->close();
}

// 2) Resend a fresh notification for the previous week to everyone (title includes '(resend)')
$now = new DateTime('now', $tz);
$rs_ws = new DateTime('last monday', $tz); $rs_ws->setTime(0,0,0);
$rs_we = new DateTime('last sunday', $tz); $rs_we->setTime(23,59,59);
$summaryNow = build_week_summary_for_range($mysqli, $rs_ws, $rs_we);
$resendTitle = 'Weekly activities (resend): ' . $summaryNow['periodLabel'];

$users = $mysqli->query("SELECT id, first_name, last_name, email FROM users WHERE email IS NOT NULL AND email <> ''");
$emailsSent = 0; $notifsInserted = 0;
$insertStmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?, 'weekly_activities', 0, ?, ?, 0, NOW())");
$checkStmt = $mysqli->prepare("SELECT id FROM notifications WHERE user_id = ? AND title = ? LIMIT 1");

if ($users) {
    while ($u = $users->fetch_assoc()) {
        $uid = (int)$u['id'];
        $email = (string)$u['email'];

        $already = false;
        if ($checkStmt) {
            $checkStmt->bind_param('is', $uid, $resendTitle);
            $checkStmt->execute();
            $cr = $checkStmt->get_result();
            if ($cr && $cr->num_rows > 0) $already = true;
            if ($cr) $cr->free();
        }

        if (!$already) {
            $plain = mb_substr(trim(preg_replace('/\s+/', ' ', $summaryNow['text'])), 0, 2000);
            if ($insertStmt) {
                $insertStmt->bind_param('iss', $uid, $resendTitle, $plain);
                if ($insertStmt->execute()) $notifsInserted++;
            }
            // send email
            $sent = send_email($email, $resendTitle, $summaryNow['html'], $summaryNow['text']);
            if ($sent) $emailsSent++;
        }
    }
}

if ($insertStmt) $insertStmt->close();
if ($checkStmt) $checkStmt->close();

echo json_encode(['success'=>true, 'updated_notifications'=>$updated, 'resend_week'=> $summaryNow['periodLabel'], 'emails_sent'=>$emailsSent, 'new_notifications'=>$notifsInserted], JSON_UNESCAPED_UNICODE);
