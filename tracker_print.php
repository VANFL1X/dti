<?php
require_once __DIR__ . '/includes/init.php';

$type = $_GET['type'] ?? 'supply';
$division = $_GET['division'] ?? [];
if (!is_array($division)) {
  $division = ($division === '' ? [] : [(string)$division]);
}
$division = array_values(array_unique(array_filter(array_map('trim', $division), static function ($v) {
  return $v !== '';
})));
$period = $_GET['period'] ?? 'month';
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$generatedAt = new DateTime('now', new DateTimeZone('Asia/Manila'));
$typeLabels = [
  'supply' => 'Supply Request',
  'vehicle' => 'Vehicle Request',
  'activity' => 'Event Report',
];
$typeLabel = $typeLabels[$type] ?? 'Records';

$printUser = $_SESSION['user'] ?? null;
$printedBy = 'Unknown User';
$printedDivision = 'Unknown Division';
if (!empty($printUser)) {
  $name = trim((string)($printUser['first_name'] ?? '') . ' ' . (string)($printUser['last_name'] ?? ''));
  $userDivision = trim((string)($printUser['division'] ?? ''));
  if ($name !== '') {
    $printedBy = $name;
  }
  if ($userDivision !== '') $printedDivision = $userDivision;
}

$results = [];

$divisionLabel = empty($division) ? 'All' : implode(', ', $division);

$addDivisionFilter = static function (&$where, &$params, &$types, $column, $selectedDivisions) {
  if (empty($selectedDivisions)) return;
  $parts = [];
  foreach ($selectedDivisions as $d) {
    $parts[] = "FIND_IN_SET(?, REPLACE($column, ', ', ',')) > 0";
    $params[] = $d;
    $types .= 's';
  }
  $where[] = '(' . implode(' OR ', $parts) . ')';
};

// reuse simplified querying logic from tracker.php
if ($type === 'supply') {
  $sql = "SELECT sr.id, sr.item, sr.variant, sr.quantity, sr.unit, sr.created_at, u.first_name, u.last_name, u.division FROM supply_requests sr JOIN users u ON u.id = sr.user_id";
  $where = [];
  $params = [];
  $types = '';
  $addDivisionFilter($where, $params, $types, 'u.division', $division);
  if ($period === 'day') { $where[] = 'DATE(sr.created_at) = ?'; $params[] = $date; $types .= 's'; }
  elseif ($period === 'week') { $where[] = 'YEARWEEK(sr.created_at, 1) = YEARWEEK(?, 1)'; $params[] = $date; $types .= 's'; }
  elseif ($period === 'month') { $where[] = 'MONTH(sr.created_at) = ? AND YEAR(sr.created_at) = ?'; $params[] = (int)$month; $params[] = (int)$year; $types .= 'ii'; }
  elseif ($period === 'year') { $where[] = 'YEAR(sr.created_at) = ?'; $params[] = (int)$year; $types .= 'i'; }
  if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= ' ORDER BY sr.created_at DESC LIMIT 1000';
  $stmt = $mysqli->prepare($sql);
  if ($stmt) {
    if ($params) {
      $bind = array_merge([$types], $params);
      $tmp = [];
      foreach ($bind as $k => $v) $tmp[$k] = &$bind[$k];
      call_user_func_array([$stmt, 'bind_param'], $tmp);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $results[] = $r;
    $stmt->close();
  }
} elseif ($type === 'vehicle') {
  $sql = "SELECT vr.id, vr.date_use, vr.departure_date, vr.departure_time, vr.expected_arrival_date, vr.expected_arrival_time, vr.vehicle_plate_no, vr.destination, vr.purpose, vr.status, vr.created_at, u.first_name, u.last_name, u.division FROM vehicle_requests vr JOIN users u ON u.id = vr.user_id";
  $where = [];
  $params = [];
  $types = '';
  $addDivisionFilter($where, $params, $types, 'u.division', $division);
  if ($period === 'day') { $where[] = 'vr.date_use = ?'; $params[] = $date; $types .= 's'; }
  elseif ($period === 'week') { $where[] = 'YEARWEEK(vr.date_use, 1) = YEARWEEK(?, 1)'; $params[] = $date; $types .= 's'; }
  elseif ($period === 'month') { $where[] = 'MONTH(vr.date_use) = ? AND YEAR(vr.date_use) = ?'; $params[] = (int)$month; $params[] = (int)$year; $types .= 'ii'; }
  elseif ($period === 'year') { $where[] = 'YEAR(vr.date_use) = ?'; $params[] = (int)$year; $types .= 'i'; }
  if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= ' ORDER BY vr.date_use DESC LIMIT 1000';
  $stmt = $mysqli->prepare($sql);
  if ($stmt) {
    if ($params) {
      $bind = array_merge([$types], $params);
      $tmp = [];
      foreach ($bind as $k => $v) $tmp[$k] = &$bind[$k];
      call_user_func_array([$stmt, 'bind_param'], $tmp);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $results[] = $r;
    $stmt->close();
  }
} else {
  $sql = "SELECT a.id, a.purpose, a.destination, a.start_datetime, a.end_datetime, a.created_at, u.first_name, u.last_name, u.division FROM activities a JOIN users u ON u.id = a.user_id";
  $where = [];
  $params = [];
  $types = '';
  $addDivisionFilter($where, $params, $types, 'u.division', $division);
  if ($period === 'day') { $where[] = 'DATE(a.start_datetime) = ?'; $params[] = $date; $types .= 's'; }
  elseif ($period === 'week') { $where[] = 'YEARWEEK(a.start_datetime, 1) = YEARWEEK(?, 1)'; $params[] = $date; $types .= 's'; }
  elseif ($period === 'month') { $where[] = 'MONTH(a.start_datetime) = ? AND YEAR(a.start_datetime) = ?'; $params[] = (int)$month; $params[] = (int)$year; $types .= 'ii'; }
  elseif ($period === 'year') { $where[] = 'YEAR(a.start_datetime) = ?'; $params[] = (int)$year; $types .= 'i'; }
  if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= ' ORDER BY a.start_datetime DESC LIMIT 1000';
  $stmt = $mysqli->prepare($sql);
  if ($stmt) {
    if ($params) {
      $bind = array_merge([$types], $params);
      $tmp = [];
      foreach ($bind as $k => $v) $tmp[$k] = &$bind[$k];
      call_user_func_array([$stmt, 'bind_param'], $tmp);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $results[] = $r;
    $stmt->close();
  }
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Records Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body {
        padding: 18px;
        color: #111827;
        font-family: "Times New Roman", Times, serif;
      }
      .report-shell {
        max-width: 1100px;
        margin: 0 auto;
      }
      .report-header {
        border: 1px solid #111827;
        padding: 10px 12px 12px;
        margin-bottom: 10px;
        text-align: center;
      }
      .report-header-top {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
      }
      .report-logo {
        width: 62px;
        height: 62px;
        object-fit: contain;
      }
      .report-org {
        margin: 0;
        font-size: 0.82rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        text-align: center;
        color: #374151;
      }
      .report-office {
        margin: 2px 0 0;
        font-size: 1.02rem;
        font-weight: 700;
        text-align: center;
        letter-spacing: 0.02em;
      }
      .report-title {
        margin: 7px 0 0;
        font-size: 1.16rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        text-align: center;
      }
      .report-subtitle {
        margin: 3px 0 0;
        font-size: 0.84rem;
        text-align: center;
        color: #4b5563;
      }
      .report-divider {
        width: 180px;
        height: 1px;
        background: #9ca3af;
        margin: 8px auto 6px;
      }
      .report-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 6px;
        margin-bottom: 10px;
      }
      .meta-item {
        border: 1px solid #9ca3af;
        padding: 5px 7px;
      }
      .meta-label {
        display: block;
        font-size: 0.68rem;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.03em;
      }
      .meta-value {
        font-size: 0.82rem;
        font-weight: 600;
        color: #111827;
      }
      table {
        width: 100%;
        font-size: 11px;
        border-collapse: collapse;
      }
      th, td {
        padding: 0.34rem;
        vertical-align: top;
        border: 1px solid #9ca3af !important;
      }
      thead th {
        background: #e5e7eb !important;
        color: #111827;
        text-transform: uppercase;
        font-size: 10.5px;
        letter-spacing: 0.02em;
      }
      .report-table-wrap {
        border: 1px solid #111827;
        overflow: hidden;
      }
      .signatory-form {
        border: 1px solid #9ca3af;
        padding: 8px;
        margin-bottom: 10px;
      }
      .signatory-form .form-label {
        font-size: 0.78rem;
        margin-bottom: 0.2rem;
      }
      .signatory-form .form-control {
        font-size: 0.82rem;
      }
      .signature-block {
        margin-top: 20px;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
      }
      .sig-item {
        text-align: center;
      }
      .sig-line {
        margin: 28px 18px 4px;
        min-height: 24px;
        border-bottom: 1px solid #111827;
        display: flex;
        align-items: flex-end;
        justify-content: center;
      }
      .sig-name {
        min-height: 1.1rem;
        font-size: 0.86rem;
        font-weight: 700;
      }
      .sig-role {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
      }
      .print-user-stamp {
        margin-top: 12px;
        text-align: right;
        font-size: 0.72rem;
        color: #374151;
      }
      @media print {
        .no-print,
        .no-print * {
          display: none !important;
          visibility: hidden !important;
        }
        body { padding: 0; }
        .report-shell { max-width: none; }
        .report-header { margin-bottom: 8px; }
        .meta-item { break-inside: avoid; }
        .signature-block, .sig-item { break-inside: avoid; }
        table, tr, td, th { break-inside: avoid; }
      }
      @media (max-width: 768px) {
        .report-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .signature-block { grid-template-columns: 1fr; gap: 10px; }
      }
    </style>
  </head>
  <body>
    <div class="report-shell">
      <div class="d-flex justify-content-between mb-3 no-print">
        <div>
          <h4 class="mb-1">Records Print Preview</h4>
          <div class="text-muted">Use the print button to generate PDF output.</div>
        </div>
        <div>
          <button class="btn btn-primary" onclick="window.print();">Print</button>
          <a class="btn btn-secondary" href="tracker.php">Close</a>
        </div>
      </div>

      <div class="signatory-form no-print">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label for="preparedByInput" class="form-label">Prepared by</label>
            <input id="preparedByInput" type="text" class="form-control" placeholder="Enter name">
          </div>
          <div class="col-md-4">
            <label for="notedByInput" class="form-label">Noted by</label>
            <input id="notedByInput" type="text" class="form-control" placeholder="Enter name">
          </div>
          <div class="col-md-4">
            <label for="approvedByInput" class="form-label">Approved by</label>
            <input id="approvedByInput" type="text" class="form-control" placeholder="Enter name">
          </div>
          <div class="col-12">
            <button class="btn btn-outline-primary btn-sm" type="button" id="applySignatoriesBtn">Apply Names</button>
          </div>
        </div>
      </div>

      <div class="report-header">
        <div class="report-header-top">
          <img src="assets/logoDTI.png" alt="DTI Logo" class="report-logo">
          <p class="report-org">Republic of the Philippines</p>
          <p class="report-office">Department of Trade and Industry - Region 2</p>
        </div>
        <div class="report-divider"></div>
        <h1 class="report-title">Records Monitoring Report</h1>
        <p class="report-subtitle"><?php echo htmlspecialchars($typeLabel); ?> - Official Printout</p>
      </div>

      <div class="report-meta">
        <div class="meta-item">
          <span class="meta-label">Record Type</span>
          <span class="meta-value"><?php echo htmlspecialchars($typeLabel); ?></span>
        </div>
        <div class="meta-item">
          <span class="meta-label">Division</span>
          <span class="meta-value"><?php echo htmlspecialchars($divisionLabel); ?></span>
        </div>
        <div class="meta-item">
          <span class="meta-label">Period</span>
          <span class="meta-value"><?php echo htmlspecialchars(ucfirst($period)); ?></span>
        </div>
        <div class="meta-item">
          <span class="meta-label">Generated</span>
          <span class="meta-value"><?php echo htmlspecialchars($generatedAt->format('Y-m-d h:i A')); ?></span>
        </div>
      </div>

      <div class="report-table-wrap">
      <?php if (empty($results)): ?>
        <div class="p-3">No records found.</div>
      <?php else: ?>
        <?php if ($type === 'supply'): ?>
          <table class="table table-bordered">
            <thead>
              <tr><th>Requester</th><th>Item</th><th>Variant</th><th>Qty</th><th>Unit</th><th>Date</th><th>Division</th></tr>
            </thead>
            <tbody>
              <?php foreach ($results as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['item']); ?></td>
                  <td><?php echo htmlspecialchars($r['variant']); ?></td>
                  <td><?php echo (int)$r['quantity']; ?></td>
                  <td><?php echo htmlspecialchars($r['unit']); ?></td>
                  <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                  <td><?php echo htmlspecialchars($r['division']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php elseif ($type === 'vehicle'): ?>
          <table class="table table-bordered">
            <thead>
              <tr><th>Requester</th><th>Vehicle</th><th>Use Date</th><th>Schedule</th><th>Destination</th><th>Purpose</th><th>Status</th><th>Division</th></tr>
            </thead>
            <tbody>
              <?php foreach ($results as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['vehicle_plate_no']); ?></td>
                  <td><?php echo htmlspecialchars($r['date_use']); ?></td>
                  <td><?php echo htmlspecialchars($r['departure_date'].' '.$r['departure_time']).' → '.htmlspecialchars($r['expected_arrival_date'].' '.$r['expected_arrival_time']); ?></td>
                  <td><?php echo htmlspecialchars($r['destination']); ?></td>
                  <td><?php echo htmlspecialchars($r['purpose']); ?></td>
                  <td><?php echo htmlspecialchars($r['status']); ?></td>
                  <td><?php echo htmlspecialchars($r['division']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <table class="table table-bordered">
            <thead>
              <tr><th>Requester</th><th>Purpose</th><th>Destination</th><th>Start</th><th>End</th><th>Division</th></tr>
            </thead>
            <tbody>
              <?php foreach ($results as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['purpose']); ?></td>
                  <td><?php echo htmlspecialchars($r['destination']); ?></td>
                  <td><?php echo htmlspecialchars($r['start_datetime']); ?></td>
                  <td><?php echo htmlspecialchars($r['end_datetime']); ?></td>
                  <td><?php echo htmlspecialchars($r['division']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php endif; ?>
      </div>

      <div class="signature-block">
        <div class="sig-item">
          <div class="sig-line"><span class="sig-name" id="preparedByName">&nbsp;</span></div>
          <div class="sig-role">Prepared by</div>
        </div>
        <div class="sig-item">
          <div class="sig-line"><span class="sig-name" id="notedByName">&nbsp;</span></div>
          <div class="sig-role">Noted by</div>
        </div>
        <div class="sig-item">
          <div class="sig-line"><span class="sig-name" id="approvedByName">&nbsp;</span></div>
          <div class="sig-role">Approved by</div>
        </div>
      </div>

      <div class="print-user-stamp">
        <?php echo htmlspecialchars($printedBy); ?> - <?php echo htmlspecialchars($printedDivision); ?>
      </div>
    </div>

    <script>
      (function () {
        var applyBtn = document.getElementById('applySignatoriesBtn');
        if (!applyBtn) return;

        var preparedEl = document.getElementById('preparedByName');
        var notedEl = document.getElementById('notedByName');
        var approvedEl = document.getElementById('approvedByName');
        var preparedInput = document.getElementById('preparedByInput');
        var notedInput = document.getElementById('notedByInput');
        var approvedInput = document.getElementById('approvedByInput');

        function setName(el, value) {
          if (!el) return;
          var trimmed = (value || '').trim();
          el.textContent = trimmed || '\u00A0';
        }

        function applyNames() {
          setName(preparedEl, preparedInput ? preparedInput.value : '');
          setName(notedEl, notedInput ? notedInput.value : '');
          setName(approvedEl, approvedInput ? approvedInput.value : '');
        }

        applyBtn.addEventListener('click', function () {
          applyNames();
        });

        if (preparedInput) preparedInput.addEventListener('input', applyNames);
        if (notedInput) notedInput.addEventListener('input', applyNames);
        if (approvedInput) approvedInput.addEventListener('input', applyNames);
      })();
    </script>

  </body>
</html>

