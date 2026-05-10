<?php
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$period = strtolower(trim((string)($_GET['period'] ?? 'month')));
if (!in_array($period, ['day', 'week', 'month'], true)) {
    $period = 'month';
}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
$week = trim((string)($_GET['week'] ?? date('o-\\WW')));
$month = trim((string)($_GET['month'] ?? date('Y-m')));
$division = trim((string)($_GET['division'] ?? ''));
$employeeId = (int)($_GET['employee_id'] ?? 0);

$periodLabel = ucfirst($period);
$periodValueLabel = '';
$periodCondition = '';
$periodBindType = '';
$periodBindValue = null;

if ($period === 'day') {
    $periodValueLabel = $date;
    $periodCondition = 'DATE(event_start) = ?';
    $periodBindType = 's';
    $periodBindValue = $date;
} elseif ($period === 'week') {
    $weekYear = (int)date('o');
    $weekNo = (int)date('W');
    if (preg_match('/^(\d{4})-W(\d{2})$/', $week, $m)) {
        $weekYear = (int)$m[1];
        $weekNo = (int)$m[2];
    }
    $monday = new DateTime();
    $monday->setISODate($weekYear, $weekNo);
    $anchorDate = $monday->format('Y-m-d');
    $periodValueLabel = $weekYear . '-W' . str_pad((string)$weekNo, 2, '0', STR_PAD_LEFT);
    $periodCondition = 'YEARWEEK(event_start, 1) = YEARWEEK(?, 1)';
    $periodBindType = 's';
    $periodBindValue = $anchorDate;
} else {
    $monthValue = date('Y-m');
    if (preg_match('/^\d{4}-\d{2}$/', $month)) {
        $monthValue = $month;
    }
    $periodValueLabel = $monthValue;
    $periodCondition = "DATE_FORMAT(event_start, '%Y-%m') = ?";
    $periodBindType = 's';
    $periodBindValue = $monthValue;
}

$where = [];
$params = [];
$types = '';

if ($periodCondition !== '') {
    $where[] = $periodCondition;
    $types .= $periodBindType;
    $params[] = $periodBindValue;
}

if ($division !== '') {
    $where[] = 'division = ?';
    $types .= 's';
    $params[] = $division;
}

if ($employeeId > 0) {
    $where[] = 'user_id = ?';
    $types .= 'i';
    $params[] = $employeeId;
}

$historySql = "
    SELECT * FROM (
        SELECT
            a.user_id,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            COALESCE(u.division, '') AS division,
              CASE
                WHEN LOCATE('leave', LOWER(COALESCE(a.purpose, ''))) > 0 THEN 'On Leave'
                WHEN LOCATE('travel', LOWER(COALESCE(a.purpose, ''))) > 0 THEN 'On Travel'
                WHEN LOCATE('office', LOWER(COALESCE(a.purpose, ''))) > 0 THEN 'On Office'
                ELSE 'On Business'
              END AS status_label,
              a.start_datetime AS event_start,
              a.end_datetime AS event_end,
              'Activity' AS source_label,
              COALESCE(a.purpose, '') AS user_input
        FROM activities a
        INNER JOIN users u ON u.id = a.user_id

        UNION ALL

        SELECT
            v.user_id,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            COALESCE(u.division, '') AS division,
            'On Travel' AS status_label,
            CONCAT(v.departure_date, ' ', v.departure_time) AS event_start,
            CONCAT(v.expected_arrival_date, ' ', v.expected_arrival_time) AS event_end,
            'Vehicle Request' AS source_label,
            COALESCE(v.purpose, '') AS user_input
        FROM vehicle_requests v
        INNER JOIN users u ON u.id = v.user_id
        WHERE v.status = 'approved'

        UNION ALL

        SELECT
            e.user_id,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            COALESCE(u.division, '') AS division,
            CASE LOWER(COALESCE(e.event_type, ''))
              WHEN 'leave' THEN 'On Leave'
              WHEN 'travel' THEN 'On Travel'
              WHEN 'business' THEN 'On Business'
              ELSE 'On Office'
            END AS status_label,
            COALESCE(e.start_datetime, e.updated_at) AS event_start,
            e.end_datetime AS event_end,
            'Manual Status' AS source_label,
            CONCAT(COALESCE(e.event_type, ''), CASE WHEN COALESCE(e.notes, '') <> '' THEN CONCAT(' - ', e.notes) ELSE '' END) AS user_input
        FROM employee_events e
        INNER JOIN users u ON u.id = e.user_id
    ) history_union
";

if (!empty($where)) {
    $historySql .= ' WHERE ' . implode(' AND ', $where);
}
$historySql .= ' ORDER BY event_start DESC LIMIT 2000';

$historyRows = [];
$stmt = $mysqli->prepare($historySql);
if ($stmt) {
    if (!empty($params)) {
        $bind = array_merge([$types], $params);
        $tmp = [];
        foreach ($bind as $k => $v) {
            $tmp[$k] = &$bind[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $tmp);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $historyRows[] = $row;
    }
    $stmt->close();
}

$employeeLabel = 'All employees';
if ($employeeId > 0) {
    $empStmt = $mysqli->prepare("SELECT TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) AS full_name FROM users WHERE id = ? LIMIT 1");
    if ($empStmt) {
        $empStmt->bind_param('i', $employeeId);
        $empStmt->execute();
        $empRes = $empStmt->get_result();
        if ($empRow = $empRes->fetch_assoc()) {
            $tmpName = trim((string)($empRow['full_name'] ?? ''));
            if ($tmpName !== '') {
                $employeeLabel = $tmpName;
            }
        }
        $empStmt->close();
    }
}

$printUser = $_SESSION['user'] ?? null;
$printedBy = 'Unknown User';
$printedDivision = 'Unknown Division';
if (!empty($printUser)) {
    $name = trim((string)($printUser['first_name'] ?? '') . ' ' . (string)($printUser['last_name'] ?? ''));
    $userDivision = trim((string)($printUser['division'] ?? ''));
    if ($name !== '') {
        $printedBy = $name;
    }
    if ($userDivision !== '') {
        $printedDivision = $userDivision;
    }
}

$generatedAt = new DateTime('now', new DateTimeZone('Asia/Manila'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Employee Tracker History Print</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 18px; color: #111827; font-family: "Times New Roman", Times, serif; }
    .report-shell { max-width: 1200px; margin: 0 auto; }
    .report-header { border: 1px solid #111827; padding: 10px 12px 12px; margin-bottom: 10px; text-align: center; }
    .report-logo { width: 62px; height: 62px; object-fit: contain; }
    .report-org { margin: 0; font-size: 0.82rem; letter-spacing: 0.08em; text-transform: uppercase; color: #374151; }
    .report-office { margin: 2px 0 0; font-size: 1.02rem; font-weight: 700; letter-spacing: 0.02em; }
    .report-title { margin: 7px 0 0; font-size: 1.16rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
    .report-subtitle { margin: 3px 0 0; font-size: 0.84rem; color: #4b5563; }
    .report-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 6px; margin-bottom: 10px; }
    .meta-item { border: 1px solid #9ca3af; padding: 5px 7px; }
    .meta-label { display: block; font-size: 0.68rem; color: #374151; text-transform: uppercase; letter-spacing: 0.03em; }
    .meta-value { font-size: 0.82rem; font-weight: 600; color: #111827; }
    .report-table-wrap { border: 1px solid #111827; overflow: hidden; }
    table { width: 100%; font-size: 11px; border-collapse: collapse; }
    th, td { padding: 0.34rem; vertical-align: top; border: 1px solid #9ca3af !important; }
    thead th { background: #e5e7eb !important; color: #111827; text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.02em; }
    .print-user-stamp { margin-top: 12px; text-align: right; font-size: 0.72rem; color: #374151; }
    @media print {
      .no-print, .no-print * { display: none !important; visibility: hidden !important; }
      body { padding: 0; }
      .report-shell { max-width: none; }
      table, tr, td, th { break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="report-shell">
    <div class="d-flex justify-content-between mb-3 no-print">
      <div>
        <h4 class="mb-1">Employee Tracker History Print Preview</h4>
        <div class="text-muted">Use the print button to generate PDF output.</div>
      </div>
      <div>
        <button class="btn btn-primary" onclick="window.print();">Print</button>
        <a class="btn btn-secondary" href="tracker.php">Close</a>
      </div>
    </div>

    <div class="report-header">
      <img src="assets/logoDTI.png" alt="DTI Logo" class="report-logo">
      <p class="report-org">Republic of the Philippines</p>
      <p class="report-office">Department of Trade and Industry - Region 2</p>
      <h1 class="report-title">Employee Tracker History Report</h1>
      <p class="report-subtitle">Official Printout</p>
    </div>

    <div class="report-meta">
      <div class="meta-item">
        <span class="meta-label">Period</span>
        <span class="meta-value"><?php echo htmlspecialchars($periodLabel); ?></span>
      </div>
      <div class="meta-item">
        <span class="meta-label">Period Value</span>
        <span class="meta-value"><?php echo htmlspecialchars($periodValueLabel); ?></span>
      </div>
      <div class="meta-item">
        <span class="meta-label">Division</span>
        <span class="meta-value"><?php echo htmlspecialchars($division !== '' ? $division : 'All divisions'); ?></span>
      </div>
      <div class="meta-item">
        <span class="meta-label">Employee</span>
        <span class="meta-value"><?php echo htmlspecialchars($employeeLabel); ?></span>
      </div>
      <div class="meta-item">
        <span class="meta-label">Generated</span>
        <span class="meta-value"><?php echo htmlspecialchars($generatedAt->format('Y-m-d h:i A')); ?></span>
      </div>
    </div>

    <div class="report-table-wrap">
      <?php if (empty($historyRows)): ?>
        <div class="p-3">No records found.</div>
      <?php else:
        // Group rows by user_id
        $byUser = [];
        foreach ($historyRows as $r) {
          $uid = (int)($r['user_id'] ?? 0);
          if (!isset($byUser[$uid])) $byUser[$uid] = ['name' => (string)($r['employee_name'] ?? 'Unknown Employee'), 'division' => (string)($r['division'] ?? ''), 'rows' => []];
          $byUser[$uid]['rows'][] = $r;
        }

        // Sort each user's rows chronologically (ascending)
        foreach ($byUser as $uid => $block) {
          usort($byUser[$uid]['rows'], function($a, $b) {
            $ta = strtotime((string)($a['event_start'] ?? '')) ?: 0;
            $tb = strtotime((string)($b['event_start'] ?? '')) ?: 0;
            return $ta <=> $tb;
          });
        }

        // Render per-employee timelines
        foreach ($byUser as $uid => $block): ?>
          <div class="p-3 border-bottom">
            <h5 class="mb-2"><?php echo htmlspecialchars($block['name']); ?> <small class="text-muted"><?php echo htmlspecialchars($block['division']); ?></small></h5>
            <table class="table table-sm table-bordered mb-0">
              <thead>
                <tr>
                  <th style="width:160px">Time</th>
                  <th style="width:140px">Status</th>
                  <th>Source</th>
                  <th>User Input</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($block['rows'] as $r):
                  $startRaw = trim((string)($r['event_start'] ?? ''));
                  $endRaw = trim((string)($r['event_end'] ?? ''));
                  $startTs = $startRaw !== '' ? strtotime($startRaw) : false;
                  $endTs = $endRaw !== '' ? strtotime($endRaw) : false;
                  if ($startTs && $endTs) {
                    $timeText = date('M d, Y h:i A', $startTs) . ' - ' . date('M d, Y h:i A', $endTs);
                  } elseif ($startTs) {
                    $timeText = 'Since ' . date('M d, Y h:i A', $startTs);
                  } elseif ($endTs) {
                    $timeText = 'Until ' . date('M d, Y h:i A', $endTs);
                  } else {
                    $timeText = ($startRaw !== '' ? $startRaw : '') . ($endRaw !== '' ? ' - ' . $endRaw : '');
                  }
                ?>
                  <tr>
                    <td><?php echo htmlspecialchars($timeText); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['status_label'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['source_label'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['user_input'] ?? '')); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach;
      endif; ?>
    </div>

    <div class="print-user-stamp">
      <?php echo htmlspecialchars($printedBy); ?> - <?php echo htmlspecialchars($printedDivision); ?>
    </div>
  </div>
</body>
</html>
