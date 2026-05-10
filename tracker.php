<?php
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$statusFlash = $_SESSION['status_flash'] ?? null;
unset($_SESSION['status_flash']);

$now = new DateTime('now');
$nowSql = $now->format('Y-m-d H:i:s');

function resolve_avatar_url($avatar)
{
    $avatar = trim((string)$avatar);
    if ($avatar === '') return '';
    $uploadPath = __DIR__ . '/uploads/' . $avatar;
    $legacyPath = __DIR__ . '/data/avatars/' . $avatar;
    if (is_file($uploadPath)) return 'uploads/' . $avatar;
    if (is_file($legacyPath)) return 'data/avatars/' . $avatar;
    return '';
}

$employees = [];
$empRes = $mysqli->query("SELECT id, first_name, last_name, email, division, avatar FROM users ORDER BY last_name ASC, first_name ASC");
if ($empRes) {
    while ($row = $empRes->fetch_assoc()) {
        $employees[(int)$row['id']] = $row;
    }
}

$divisionOptions = [];
foreach ($employees as $empRow) {
  $divValue = trim((string)($empRow['division'] ?? ''));
  if ($divValue !== '') {
    $divisionOptions[$divValue] = true;
  }
}
$divisionOptions = array_keys($divisionOptions);
sort($divisionOptions, SORT_NATURAL | SORT_FLAG_CASE);

$activityMap = [];
$activityStmt = $mysqli->prepare("SELECT user_id, purpose, destination, start_datetime, end_datetime FROM activities WHERE start_datetime <= ? AND end_datetime >= ? ORDER BY start_datetime DESC");
if ($activityStmt) {
    $activityStmt->bind_param('ss', $nowSql, $nowSql);
    $activityStmt->execute();
    $activityRes = $activityStmt->get_result();
    while ($r = $activityRes->fetch_assoc()) {
        $uid = (int)$r['user_id'];
        if (!isset($activityMap[$uid])) {
            $activityMap[$uid] = $r;
        }
    }
    $activityStmt->close();
}

$vehicleMap = [];
$vehicleStmt = $mysqli->prepare("SELECT user_id, destination, departure_date, departure_time, expected_arrival_date, expected_arrival_time FROM vehicle_requests WHERE status = 'approved' AND CONCAT(departure_date, ' ', departure_time) <= ? AND CONCAT(expected_arrival_date, ' ', expected_arrival_time) >= ? ORDER BY departure_date DESC, departure_time DESC");
if ($vehicleStmt) {
    $vehicleStmt->bind_param('ss', $nowSql, $nowSql);
    $vehicleStmt->execute();
    $vehicleRes = $vehicleStmt->get_result();
    while ($r = $vehicleRes->fetch_assoc()) {
        $uid = (int)$r['user_id'];
        if (!isset($vehicleMap[$uid])) {
            $vehicleMap[$uid] = $r;
        }
    }
    $vehicleStmt->close();
}

  $manualStatusMap = [];
  // Fetch manual entries ordered newest-first so we can pick the latest per user
  $manualStmt = $mysqli->prepare("SELECT user_id, event_type, start_datetime, end_datetime, notes, updated_at FROM employee_events ORDER BY COALESCE(start_datetime, '0000-00-00 00:00:00') DESC, updated_at DESC");
  if ($manualStmt) {
    $manualStmt->execute();
    $manualRes = $manualStmt->get_result();
    while ($r = $manualRes->fetch_assoc()) {
      $uid = (int)$r['user_id'];
      if (!isset($manualStatusMap[$uid])) {
        $manualStatusMap[$uid] = $r;
      }
    }
    $manualStmt->close();
  }

$cards = [];
foreach ($employees as $id => $emp) {
    $fullName = trim((string)$emp['first_name'] . ' ' . (string)$emp['last_name']);
    if ($fullName === '') $fullName = 'Unknown Employee';

    $status = 'On Office';
    $badgeClass = 'success';
    $eventName = 'Office';
    $timeText = 'As of ' . $now->format('M d, Y h:i A');
    $detail = 'No active field event';

    if (isset($vehicleMap[$id])) {
        $v = $vehicleMap[$id];
        $status = 'On Travel';
        $badgeClass = 'info';
        $eventName = 'Travel';
        $timeText = date('M d, Y h:i A', strtotime((string)$v['departure_date'] . ' ' . (string)$v['departure_time']))
            . ' - ' . date('M d, Y h:i A', strtotime((string)$v['expected_arrival_date'] . ' ' . (string)$v['expected_arrival_time']));
        $detail = 'Destination: ' . (string)($v['destination'] ?? '');
    }

    if (isset($activityMap[$id])) {
        $a = $activityMap[$id];
        $purpose = strtolower((string)($a['purpose'] ?? ''));
        $eventName = (string)($a['purpose'] ?? 'Activity');
        $timeText = date('M d, Y h:i A', strtotime((string)$a['start_datetime']))
            . ' - ' . date('M d, Y h:i A', strtotime((string)$a['end_datetime']));
        $detail = 'Destination: ' . (string)($a['destination'] ?? '');

        if (strpos($purpose, 'leave') !== false) {
            $status = 'On Leave';
            $badgeClass = 'warning';
            $eventName = 'Leave';
        } elseif (strpos($purpose, 'travel') !== false) {
            $status = 'On Travel';
            $badgeClass = 'info';
            $eventName = 'Travel';
        } elseif (strpos($purpose, 'business') !== false) {
            $status = 'On Business';
            $badgeClass = 'primary';
            $eventName = 'Business';
        } elseif (strpos($purpose, 'office') !== false) {
            $status = 'On Office';
            $badgeClass = 'success';
            $eventName = 'Office';
        } else {
            $status = 'On Business';
            $badgeClass = 'primary';
        }
    }

      // Manual status set by account owner overrides inferred status.
      if (isset($manualStatusMap[$id])) {
        $m = $manualStatusMap[$id];
        $etype = strtolower((string)($m['event_type'] ?? ''));
        if ($etype === 'leave') {
          $status = 'On Leave';
          $badgeClass = 'warning';
          $eventName = 'Leave';
        } elseif ($etype === 'travel') {
          $status = 'On Travel';
          $badgeClass = 'info';
          $eventName = 'Travel';
        } elseif ($etype === 'business') {
          $status = 'On Business';
          $badgeClass = 'primary';
          $eventName = 'Business';
        } else {
          $status = 'On Office';
          $badgeClass = 'success';
          $eventName = 'Office';
        }

        $st = (string)($m['start_datetime'] ?? '');
        $et = (string)($m['end_datetime'] ?? '');
        if ($st !== '' && $et !== '') {
          $timeText = date('M d, Y h:i A', strtotime($st)) . ' - ' . date('M d, Y h:i A', strtotime($et));
        } elseif ($st !== '') {
          $timeText = 'Since ' . date('M d, Y h:i A', strtotime($st));
        } else {
          $timeText = 'Updated ' . date('M d, Y h:i A', strtotime((string)($m['updated_at'] ?? $nowSql)));
        }

        $note = trim((string)($m['notes'] ?? ''));
        if ($note !== '') {
          $detail = $note;
        }
      }

    $cards[] = [
      'user_id' => (int)$id,
        'name' => $fullName,
        'email' => (string)($emp['email'] ?? ''),
        'division' => (string)($emp['division'] ?? ''),
        'avatar' => resolve_avatar_url($emp['avatar'] ?? ''),
        'status' => $status,
        'badge' => $badgeClass,
        'event' => $eventName,
        'time' => $timeText,
        'detail' => $detail,
    ];
}

  $historyRows = [];
  $historySql = "
    SELECT
      TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
      COALESCE(u.division, '') AS division,
      CASE
        WHEN LOCATE('leave', LOWER(COALESCE(a.purpose, ''))) > 0 THEN 'On Leave'
        WHEN LOCATE('travel', LOWER(COALESCE(a.purpose, ''))) > 0 THEN 'On Travel'
        WHEN LOCATE('office', LOWER(COALESCE(a.purpose, ''))) > 0 THEN 'On Office'
        ELSE 'On Business'
      END AS status_label,
      a.start_datetime AS start_datetime,
      a.end_datetime AS end_datetime,
      a.start_datetime AS sort_datetime
    FROM activities a
    INNER JOIN users u ON u.id = a.user_id

    UNION ALL

    SELECT
      TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
      COALESCE(u.division, '') AS division,
      'On Travel' AS status_label,
      CONCAT(v.departure_date, ' ', v.departure_time) AS start_datetime,
      CONCAT(v.expected_arrival_date, ' ', v.expected_arrival_time) AS end_datetime,
      CONCAT(v.departure_date, ' ', v.departure_time) AS sort_datetime
    FROM vehicle_requests v
    INNER JOIN users u ON u.id = v.user_id
    WHERE v.status = 'approved'

    UNION ALL

    SELECT
      TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
      COALESCE(u.division, '') AS division,
      CASE LOWER(COALESCE(e.event_type, ''))
        WHEN 'leave' THEN 'On Leave'
        WHEN 'travel' THEN 'On Travel'
        WHEN 'business' THEN 'On Business'
        ELSE 'On Office'
      END AS status_label,
      e.start_datetime AS start_datetime,
      e.end_datetime AS end_datetime,
      COALESCE(e.updated_at, e.start_datetime, e.end_datetime) AS sort_datetime
    FROM employee_events e
    INNER JOIN users u ON u.id = e.user_id

    ORDER BY sort_datetime DESC
    LIMIT 500
  ";
  $historyRes = $mysqli->query($historySql);
  if ($historyRes) {
    while ($row = $historyRes->fetch_assoc()) {
      $name = trim((string)($row['employee_name'] ?? ''));
      if ($name === '') {
        $name = 'Unknown Employee';
      }

      $startRaw = trim((string)($row['start_datetime'] ?? ''));
      $endRaw = trim((string)($row['end_datetime'] ?? ''));
      $startTs = $startRaw !== '' ? strtotime($startRaw) : false;
      $endTs = $endRaw !== '' ? strtotime($endRaw) : false;

      if ($startTs && $endTs) {
        $timeText = date('M d, Y h:i A', $startTs) . ' - ' . date('M d, Y h:i A', $endTs);
      } elseif ($startTs) {
        $timeText = 'Since ' . date('M d, Y h:i A', $startTs);
      } elseif ($endTs) {
        $timeText = 'Until ' . date('M d, Y h:i A', $endTs);
      } else {
        $timeText = 'N/A';
      }

      $historyRows[] = [
        'employee_name' => $name,
        'division' => (string)($row['division'] ?? ''),
        'status_label' => (string)($row['status_label'] ?? 'On Office'),
        'time_text' => $timeText,
      ];
    }
  }

    if (isset($_GET['live']) && $_GET['live'] === '1') {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'success' => true,
        'updated_at' => $now->format('M d, Y h:i A'),
        'cards' => $cards,
      ]);
      exit;
    }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Employee Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    /* Theme-aware tracker styles */
    .tracker-table-wrap {
      border: 1px solid var(--surface-contrast);
      border-radius: 14px;
      overflow: hidden;
      background: var(--card-bg);
      backdrop-filter: blur(8px);
      box-shadow: var(--shadow-1);
    }
    .tracker-table {
      margin-bottom: 0;
      min-width: 860px;
    }
    .tracker-table thead th {
      background: rgba(148, 163, 184, 0.08);
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--text-dim);
      border-bottom: 1px solid var(--surface-contrast);
      white-space: nowrap;
    }
    .tracker-table tbody td {
      vertical-align: middle;
      border-color: var(--surface-contrast);
      color: var(--text);
    }
    .tracker-avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(100, 116, 139, 0.12);
      background: var(--surface);
    }
    .tracker-fallback {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--primary);
      color: #fff;
      font-weight: 700;
      border: 2px solid rgba(255,255,255,0.04);
    }
    .tracker-dot {
      width: 22px;
      text-align: center;
      font-size: 1.05rem;
    }
    .tracker-time {
      white-space: nowrap;
      font-size: 0.9rem;
      color: var(--text);
    }
    .tracker-table tbody tr.status-row-office > td {
      background-color: rgba(34, 197, 94, 0.09) !important;
    }
    .tracker-table tbody tr.status-row-business > td {
      background-color: rgba(59, 130, 246, 0.09) !important;
    }
    .tracker-table tbody tr.status-row-leave > td {
      background-color: rgba(250, 204, 21, 0.10) !important;
    }
    .tracker-table tbody tr.status-row-travel > td {
      background-color: rgba(6, 182, 212, 0.09) !important;
    }

    /* Dark-mode fine-tuning for tracker-specific elements */
    .dark-mode .tracker-table thead th {
      background: rgba(255,255,255,0.02);
      color: var(--text-dim);
      border-bottom: 1px solid var(--surface-contrast);
    }
    .dark-mode .tracker-fallback { background: var(--primary); }
    .dark-mode .tracker-avatar { border-color: rgba(255,255,255,0.06); }
    .dark-mode .tracker-table tbody tr.status-row-office > td { background-color: rgba(34,197,94,0.07) !important; }
    .dark-mode .tracker-table tbody tr.status-row-business > td { background-color: rgba(59,130,246,0.06) !important; }
    .dark-mode .tracker-table tbody tr.status-row-leave > td { background-color: rgba(250,204,21,0.08) !important; }
    .dark-mode .tracker-table tbody tr.status-row-travel > td { background-color: rgba(6,182,212,0.06) !important; }
    .dark-mode .tracker-table tbody td .text-muted, .dark-mode .tracker-table tbody td small { color: var(--muted); }
  </style>
</head>
<body class="p-4">
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container mt-4 section-shell">
  <div class="section-card section-card--tracker">
    <div class="section-head d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <span class="section-icon"><i class="bi bi-people"></i></span>
        <div>
          <h1 class="h3 mb-1">Employee Monitoring Tracker</h1>
        </div>
      </div>
    </div>

    <div class="alert alert-light border mt-3 mb-3 d-flex justify-content-between align-items-center">
      <div>
        <div><strong>Updated:</strong> <span id="trackerUpdatedAt"><?php echo htmlspecialchars($now->format('M d, Y h:i A')); ?></span></div>
        <div class="small text-muted"><strong>Now:</strong> <span id="trackerNow"></span></div>
      </div>
      <div class="small text-muted"><i class="bi bi-broadcast-pin"></i> <span id="liveStatus">Live: syncing...</span></div>
    </div>

    <div class="mb-3 d-flex gap-2 align-items-center flex-wrap">
      <div class="input-group" style="max-width: 460px;">
        <input id="trackerSearchInput" type="search" class="form-control" placeholder="Search employee, division, event..." aria-label="Search tracker">
        <button id="trackerSearchBtn" class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i> Search</button>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#myStatusModal"><i class="bi bi-person-check"></i> My Status</button>
      <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#trackerHistoryModal"><i class="bi bi-clock-history"></i> History</button>
      
    </div>

    <?php if (is_array($statusFlash) && !empty($statusFlash['message'])): ?>
      <div class="alert alert-<?php echo (($statusFlash['type'] ?? '') === 'danger') ? 'danger' : 'success'; ?> mb-3">
        <?php echo htmlspecialchars((string)$statusFlash['message']); ?>
      </div>
    <?php endif; ?>

    <div id="trackerEmpty" class="alert alert-secondary<?php echo empty($cards) ? '' : ' d-none'; ?>">No employee records found.</div>
    <div id="trackerTableWrap" class="tracker-table-wrap table-responsive<?php echo empty($cards) ? ' d-none' : ''; ?>">
      <table class="table tracker-table align-middle">
        <thead>
          <tr>
            <th>Employee</th>
            <th class="text-center">On Office</th>
            <th class="text-center">On Business</th>
            <th class="text-center">On Leave</th>
            <th class="text-center">On Travel</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody id="trackerTableBody">
          <?php foreach ($cards as $c): ?>
            <?php
              $isOffice = ($c['status'] === 'On Office');
              $isBusiness = ($c['status'] === 'On Business');
              $isLeave = ($c['status'] === 'On Leave');
              $isTravel = ($c['status'] === 'On Travel');
              $rowClass = $isOffice ? 'status-row-office' : ($isBusiness ? 'status-row-business' : ($isLeave ? 'status-row-leave' : ($isTravel ? 'status-row-travel' : '')));
            ?>
            <tr class="<?php echo $rowClass; ?>">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if ($c['avatar'] !== ''): ?>
                    <img src="<?php echo htmlspecialchars($c['avatar']); ?>" alt="Profile" class="tracker-avatar">
                  <?php else: ?>
                    <span class="tracker-fallback"><?php echo htmlspecialchars(strtoupper(substr($c['name'], 0, 1))); ?></span>
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($c['name']); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($c['division']); ?> | <?php echo htmlspecialchars($c['event']); ?></div>
                  </div>
                </div>
              </td>
              <td class="text-center tracker-dot"><?php echo $isOffice ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">-</span>'; ?></td>
              <td class="text-center tracker-dot"><?php echo $isBusiness ? '<i class="bi bi-check-circle-fill text-primary"></i>' : '<span class="text-muted">-</span>'; ?></td>
              <td class="text-center tracker-dot"><?php echo $isLeave ? '<i class="bi bi-check-circle-fill text-warning"></i>' : '<span class="text-muted">-</span>'; ?></td>
              <td class="text-center tracker-dot"><?php echo $isTravel ? '<i class="bi bi-check-circle-fill text-info"></i>' : '<span class="text-muted">-</span>'; ?></td>
              <td class="tracker-time">
                <div><?php echo htmlspecialchars($c['time']); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($c['detail']); ?></div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Status History Modal -->
<div class="modal fade" id="trackerHistoryModal" tabindex="-1" aria-labelledby="trackerHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="trackerHistoryModalLabel">User Status History</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($historyRows)): ?>
          <div class="alert alert-secondary mb-0">No status history records found.</div>
        <?php else: ?>
          <form class="row g-2 align-items-end mb-3" method="get" action="tracker_history_print.php" target="_blank">
            <div class="col-md-2">
              <label class="form-label small mb-1" for="historyPrintPeriod">Period</label>
              <select class="form-select form-select-sm" name="period" id="historyPrintPeriod">
                <option value="day">Day</option>
                <option value="week">Week</option>
                <option value="month" selected>Month</option>
              </select>
            </div>
            <div class="col-md-2" id="historyPrintDayWrap" style="display:none;">
              <label class="form-label small mb-1" for="historyPrintDate">Date</label>
              <input class="form-control form-control-sm" type="date" id="historyPrintDate" name="date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>
            <div class="col-md-2" id="historyPrintWeekWrap" style="display:none;">
              <label class="form-label small mb-1" for="historyPrintWeek">Week</label>
              <input class="form-control form-control-sm" type="week" id="historyPrintWeek" name="week" value="<?php echo htmlspecialchars(date('o-\\WW')); ?>">
            </div>
            <div class="col-md-2" id="historyPrintMonthWrap">
              <label class="form-label small mb-1" for="historyPrintMonth">Month</label>
              <input class="form-control form-control-sm" type="month" id="historyPrintMonth" name="month" value="<?php echo htmlspecialchars(date('Y-m')); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1" for="historyPrintDivision">Division</label>
              <select class="form-select form-select-sm" id="historyPrintDivision" name="division">
                <option value="">All divisions</option>
                <?php foreach ($divisionOptions as $div): ?>
                  <option value="<?php echo htmlspecialchars($div); ?>"><?php echo htmlspecialchars($div); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1" for="historyPrintEmployee">Employee</label>
              <select class="form-select form-select-sm" id="historyPrintEmployee" name="employee_id">
                <option value="">All employees</option>
                <?php foreach ($employees as $empId => $empData): ?>
                  <?php $empName = trim((string)($empData['first_name'] ?? '') . ' ' . (string)($empData['last_name'] ?? '')); ?>
                  <option value="<?php echo (int)$empId; ?>"><?php echo htmlspecialchars($empName !== '' ? $empName : 'Unknown Employee'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-12">
              <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-printer"></i> Print History PDF
              </button>
            </div>
          </form>

          <div class="mb-3">
            <div class="input-group">
              <input id="historySearchInput" type="search" class="form-control" placeholder="Search employee, division, status, or time..." aria-label="Search status history">
              <button id="historySearchBtn" class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i> Search</button>
            </div>
          </div>
          <div id="historyEmpty" class="alert alert-secondary d-none mb-3">No matching history records.</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Division</th>
                  <th>Status</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody id="trackerHistoryBody">
                <?php foreach ($historyRows as $h): ?>
                  <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars((string)$h['employee_name']); ?></td>
                    <td><?php echo htmlspecialchars((string)$h['division']); ?></td>
                    <td><?php echo htmlspecialchars((string)$h['status_label']); ?></td>
                    <td><?php echo htmlspecialchars((string)$h['time_text']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- My Status Modal -->
<div class="modal fade" id="myStatusModal" tabindex="-1" aria-labelledby="myStatusModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="myStatusModalLabel">Set Your Status</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="myStatusForm" action="api/employee_status_set.php" method="post">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="mb-3">
            <label for="eventType" class="form-label">Current Status</label>
            <select class="form-select" id="eventType" name="event_type" required>
              <option value="office">On Office</option>
              <option value="travel">On Travel</option>
              <option value="business">On Business</option>
              <option value="leave">On Leave</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="startDateTime" class="form-label">Start Date & Time</label>
            <input type="datetime-local" class="form-control" id="startDateTime" name="start_datetime">
          </div>
          <div class="mb-3">
            <label for="endDateTime" class="form-label">End Date & Time</label>
            <input type="datetime-local" class="form-control" id="endDateTime" name="end_datetime">
          </div>
          <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Add any additional details..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Status</button>
        </div>
      </form>
    </div>
  </div>
</div>

 

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/script.js?v=20260407k2"></script>
<script>
  function formatLocalDisplay(dateObj) {
    try {
      return dateObj.toLocaleString(undefined, {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    } catch (e) {
      return dateObj.toString();
    }
  }

  function toDateTimeLocalValue(dateObj) {
    const pad = function(n) { return String(n).padStart(2, '0'); };
    return dateObj.getFullYear()
      + '-' + pad(dateObj.getMonth() + 1)
      + '-' + pad(dateObj.getDate())
      + 'T' + pad(dateObj.getHours())
      + ':' + pad(dateObj.getMinutes());
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>'"]/g, function(ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[ch];
    });
  }

  function renderTrackerRows(cards) {
    const wrap = document.getElementById('trackerTableWrap');
    const body = document.getElementById('trackerTableBody');
    const empty = document.getElementById('trackerEmpty');
    if (!wrap || !body || !empty) return;

    if (!Array.isArray(cards) || cards.length === 0) {
      wrap.classList.add('d-none');
      empty.classList.remove('d-none');
      body.innerHTML = '';
      return;
    }

    empty.classList.add('d-none');
    wrap.classList.remove('d-none');

    // Apply current filter (if any)
    const filter = (window.currentTrackerFilter || '').trim().toLowerCase();
    let filtered = cards;
    if (filter) {
      filtered = (cards || []).filter(function(c) {
        const name = String(c.name || '').toLowerCase();
        const division = String(c.division || '').toLowerCase();
        const eventName = String(c.event || '').toLowerCase();
        const timeText = String(c.time || '').toLowerCase();
        const detail = String(c.detail || '').toLowerCase();
        return name.indexOf(filter) !== -1 || division.indexOf(filter) !== -1 || eventName.indexOf(filter) !== -1 || timeText.indexOf(filter) !== -1 || detail.indexOf(filter) !== -1;
      });
    }

    body.innerHTML = (filtered || []).map(function(c) {
      const name = escapeHtml(c.name);
      const division = escapeHtml(c.division);
      const eventName = escapeHtml(c.event);
      const timeText = escapeHtml(c.time);
      const detail = escapeHtml(c.detail);
      const status = String(c.status || '');
      const avatar = String(c.avatar || '');
      const initial = name ? name.charAt(0).toUpperCase() : '?';

      const avatarHtml = avatar !== ''
        ? '<img src="' + escapeHtml(avatar) + '" alt="Profile" class="tracker-avatar">'
        : '<span class="tracker-fallback">' + initial + '</span>';

      const office = status === 'On Office' ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">-</span>';
      const business = status === 'On Business' ? '<i class="bi bi-check-circle-fill text-primary"></i>' : '<span class="text-muted">-</span>';
      const leave = status === 'On Leave' ? '<i class="bi bi-check-circle-fill text-warning"></i>' : '<span class="text-muted">-</span>';
      const travel = status === 'On Travel' ? '<i class="bi bi-check-circle-fill text-info"></i>' : '<span class="text-muted">-</span>';
      let rowClass = '';
      if (status === 'On Office') rowClass = 'status-row-office';
      else if (status === 'On Business') rowClass = 'status-row-business';
      else if (status === 'On Leave') rowClass = 'status-row-leave';
      else if (status === 'On Travel') rowClass = 'status-row-travel';

      return '<tr class="' + rowClass + '">' 
        + '<td>'
        + '<div class="d-flex align-items-center gap-2">'
        + '<div>' + avatarHtml + '</div>'
        + '<div>'
        + '<div class="fw-semibold">' + name + '</div>'
        + '<div class="small text-muted">' + division + ' | ' + eventName + '</div>'
        + '</div>'
        + '</div>'
        + '</td>'
        + '<td class="text-center tracker-dot">' + office + '</td>'
        + '<td class="text-center tracker-dot">' + business + '</td>'
        + '<td class="text-center tracker-dot">' + leave + '</td>'
        + '<td class="text-center tracker-dot">' + travel + '</td>'
        + '<td class="tracker-time"><div>' + timeText + '</div><div class="small text-muted">' + detail + '</div></td>'
        + '</tr>';
    }).join('');
  }

  async function pollLiveTracker() {
    const status = document.getElementById('liveStatus');
    const updatedAt = document.getElementById('trackerUpdatedAt');
    try {
      const response = await fetch('tracker.php?live=1', {
        method: 'GET',
        cache: 'no-store'
      });
      const data = await response.json();
      if (data && data.success) {
        renderTrackerRows(data.cards || []);
        if (updatedAt && data.updated_at) updatedAt.textContent = data.updated_at;
        if (status) status.textContent = 'Live: online';
      } else if (status) {
        status.textContent = 'Live: retrying...';
      }
    } catch (err) {
      if (status) status.textContent = 'Live: reconnecting...';
    }
  }

  pollLiveTracker();
  setInterval(pollLiveTracker, 10000);

  // Search/filter handling
  window.currentTrackerFilter = '';
  const searchInput = document.getElementById('trackerSearchInput');
  const searchBtn = document.getElementById('trackerSearchBtn');
  function applyTrackerSearch() {
    if (!searchInput) return;
    window.currentTrackerFilter = (searchInput.value || '').trim();
    // trigger an immediate live fetch so results reflect server state, but render will filter
    pollLiveTracker();
  }
  if (searchBtn) searchBtn.addEventListener('click', applyTrackerSearch);
  if (searchInput) searchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); applyTrackerSearch(); } });

  // History modal search/filter handling
  const historySearchInput = document.getElementById('historySearchInput');
  const historySearchBtn = document.getElementById('historySearchBtn');
  const historyTableBody = document.getElementById('trackerHistoryBody');
  const historyEmpty = document.getElementById('historyEmpty');

  function applyHistorySearch() {
    if (!historyTableBody || !historySearchInput) return;
    const query = (historySearchInput.value || '').trim().toLowerCase();
    const rows = historyTableBody.querySelectorAll('tr');
    let visibleCount = 0;

    rows.forEach(function(row) {
      const text = (row.textContent || '').toLowerCase();
      const isVisible = query === '' || text.indexOf(query) !== -1;
      row.classList.toggle('d-none', !isVisible);
      if (isVisible) visibleCount += 1;
    });

    if (historyEmpty) {
      historyEmpty.classList.toggle('d-none', visibleCount > 0);
    }
  }

  if (historySearchBtn) historySearchBtn.addEventListener('click', applyHistorySearch);
  if (historySearchInput) {
    historySearchInput.addEventListener('input', applyHistorySearch);
    historySearchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        applyHistorySearch();
      }
    });
  }

  // Print form period controls in history modal
  const historyPrintPeriod = document.getElementById('historyPrintPeriod');
  const historyPrintDayWrap = document.getElementById('historyPrintDayWrap');
  const historyPrintWeekWrap = document.getElementById('historyPrintWeekWrap');
  const historyPrintMonthWrap = document.getElementById('historyPrintMonthWrap');
  function updateHistoryPrintPeriodFields() {
    if (!historyPrintPeriod) return;
    const value = historyPrintPeriod.value;
    if (historyPrintDayWrap) historyPrintDayWrap.style.display = value === 'day' ? '' : 'none';
    if (historyPrintWeekWrap) historyPrintWeekWrap.style.display = value === 'week' ? '' : 'none';
    if (historyPrintMonthWrap) historyPrintMonthWrap.style.display = value === 'month' ? '' : 'none';
  }
  if (historyPrintPeriod) {
    historyPrintPeriod.addEventListener('change', updateHistoryPrintPeriodFields);
    updateHistoryPrintPeriodFields();
  }

  // Realtime calendar/time display and default datetime values in My Status form
  const trackerNow = document.getElementById('trackerNow');
  const startDateTime = document.getElementById('startDateTime');
  const endDateTime = document.getElementById('endDateTime');
  const myStatusModalEl = document.getElementById('myStatusModal');

  function updateRealtimeClock() {
    const now = new Date();
    if (trackerNow) trackerNow.textContent = formatLocalDisplay(now);
  }

  function primeMyStatusDateTimes() {
    const now = new Date();
    if (startDateTime && !startDateTime.value) {
      startDateTime.value = toDateTimeLocalValue(now);
    }
    if (endDateTime && !endDateTime.value) {
      const plusOneHour = new Date(now.getTime() + 60 * 60 * 1000);
      endDateTime.value = toDateTimeLocalValue(plusOneHour);
    }
  }

  updateRealtimeClock();
  setInterval(updateRealtimeClock, 1000);
  primeMyStatusDateTimes();
  if (myStatusModalEl) {
    myStatusModalEl.addEventListener('show.bs.modal', primeMyStatusDateTimes);
  }

  
</script>
</body>
</html>












