<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

// Returns HTML (and text) for a notification detail. Expects GET or POST 'id'.
try {
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid id']);
        exit;
    }

    $stmt = $mysqli->prepare('SELECT id, user_id, type, title, body, created_at FROM notifications WHERE id = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Not found']);
        exit;
    }

    $notifUserId = $row['user_id'] !== null ? (int)$row['user_id'] : null;
    $currentUserId = (int)$user['id'];
    if ($notifUserId !== null && $notifUserId !== $currentUserId) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    // For weekly_activities, regenerate the full HTML summary based on the notification title or created_at
    if (($row['type'] ?? '') === 'weekly_activities') {
        $tz = new DateTimeZone('Asia/Manila');

        // Try to parse the date range from the title (e.g. "April 13 - 19, 2026" or with prefixes)
        $weekStart = null; $weekEnd = null;
        $title = (string)$row['title'];
        if (preg_match('/([A-Za-z]+)\s+(\d{1,2})\s*-\s*(\d{1,2}),\s*(\d{4})$/', $title, $m)) {
            $month = $m[1]; $startDay = $m[2]; $endDay = $m[3]; $year = $m[4];
            try {
                $weekStart = new DateTime("{$month} {$startDay} {$year}", $tz);
                $weekStart->setTime(0,0,0);
                $weekEnd = new DateTime("{$month} {$endDay} {$year}", $tz);
                $weekEnd->setTime(23,59,59);
            } catch (Exception $e) {
                $weekStart = null; $weekEnd = null;
            }
        }

        // Fallback: compute the week (Monday-Sunday) that contains the notification created_at
        if ($weekStart === null || $weekEnd === null) {
            $created = new DateTime($row['created_at'], $tz);
            // determine ISO day of week (1=Mon ..7=Sun)
            $dow = (int)$created->format('N');
            $weekStart = (clone $created)->modify('-' . ($dow - 1) . ' days')->setTime(0,0,0);
            $weekEnd = (clone $weekStart)->modify('+6 days')->setTime(23,59,59);
        }

        // Build summary (similar to weekly script)
        $startStr = $weekStart->format('Y-m-d H:i:s');
        $endStr = $weekEnd->format('Y-m-d H:i:s');

        $activities = [];
        $ast = $mysqli->prepare("SELECT a.id, a.purpose, a.destination, a.start_datetime, a.end_datetime, a.division_scope, u.first_name, u.last_name, u.division as user_division
            FROM activities a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.start_datetime BETWEEN ? AND ?
            ORDER BY a.start_datetime ASC");
        if ($ast) {
            $s = $startStr; $e = $endStr; $ast->bind_param('ss', $s, $e);
            $ast->execute(); $ar = $ast->get_result(); while ($r = $ar->fetch_assoc()) $activities[] = $r; $ast->close();
        }

        $vehicles = [];
        $vst = $mysqli->prepare("SELECT vr.id, vr.purpose, vr.destination, vr.departure_date, vr.departure_time, vr.expected_arrival_date, vr.expected_arrival_time, u.first_name, u.last_name, u.division
            FROM vehicle_requests vr
            LEFT JOIN users u ON u.id = vr.user_id
            WHERE vr.status = 'approved'
            AND vr.departure_date BETWEEN ? AND ?
            ORDER BY vr.departure_date ASC, vr.departure_time ASC");
        if ($vst) {
            $vs = $weekStart->format('Y-m-d'); $ve = $weekEnd->format('Y-m-d'); $vst->bind_param('ss', $vs, $ve);
            $vst->execute(); $vr = $vst->get_result(); while ($r = $vr->fetch_assoc()) $vehicles[] = $r; $vst->close();
        }

        $periodLabel = $weekStart->format('F j') . ' - ' . $weekEnd->format('j, Y');
        $html = '<div><h4>Weekly activities summary — ' . htmlspecialchars($periodLabel) . '</h4>';
        if (empty($activities) && empty($vehicles)) {
            $html .= '<p>No activities for this period.</p>';
        } else {
            if (!empty($activities)) {
                $html .= '<h5>Activities</h5>';
                foreach ($activities as $a) {
                    $creator = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'Unknown';
                    $eventDivision = trim((string)($a['division_scope'] ?? '')) ?: trim((string)($a['user_division'] ?? ''));
                    $html .= '<div style="padding:8px;border-bottom:1px solid #eee"><strong>' . htmlspecialchars($a['purpose']) . '</strong><br>'
                        . htmlspecialchars($a['start_datetime']) . ' to ' . htmlspecialchars($a['end_datetime']) . '<br>'
                        . 'Division: ' . htmlspecialchars($eventDivision) . '<br>'
                        . 'Destination: ' . htmlspecialchars($a['destination'] ?? '') . '<br>'
                        . 'Posted by: ' . htmlspecialchars($creator) . '</div>';
                }
            }
            if (!empty($vehicles)) {
                $html .= '<h5 class="mt-3">Approved vehicle requests</h5>';
                foreach ($vehicles as $v) {
                    $creator = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '')) ?: 'Unknown';
                    $start = ($v['departure_date'] ?? '') . ' ' . ($v['departure_time'] ?? '');
                    $end = ($v['expected_arrival_date'] ?? '') . ' ' . ($v['expected_arrival_time'] ?? '');
                    $html .= '<div style="padding:8px;border-bottom:1px solid #eee"><strong>' . htmlspecialchars($v['purpose']) . '</strong><br>'
                        . htmlspecialchars($start) . ' to ' . htmlspecialchars($end) . '<br>'
                        . 'Division: ' . htmlspecialchars((string)($v['division'] ?? '')) . '<br>'
                        . 'Destination: ' . htmlspecialchars($v['destination'] ?? '') . '<br>'
                        . 'Requested by: ' . htmlspecialchars($creator) . '</div>';
                }
            }
        }
        $html .= '</div>';

        echo json_encode(['success' => true, 'html' => $html]);
        exit;
    }

    // Non-weekly types: return stored body as plain HTML-escaped
    $body = htmlspecialchars((string)$row['body']);
    echo json_encode(['success' => true, 'html' => '<div><h4>' . htmlspecialchars($row['title']) . '</h4><div>' . nl2br($body) . '</div></div>']);
    exit;

} catch (Throwable $e) {
    error_log('notification_detail error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit(1);
}
