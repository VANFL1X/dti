<?php
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

function records_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function records_display_datetime($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '---';
    }
    $ts = strtotime($value);
    return $ts ? date('M d, Y h:i A', $ts) : '---';
}

function records_display_date($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '---';
    }
    $ts = strtotime($value);
    return $ts ? date('M d, Y', $ts) : '---';
}

function records_avatar_url($avatar)
{
    $avatar = trim((string)$avatar);
    if ($avatar === '') return '';
    $uploadPath = __DIR__ . '/uploads/' . $avatar;
    $legacyPath = __DIR__ . '/data/avatars/' . $avatar;
    if (is_file($uploadPath)) return 'uploads/' . $avatar;
    if (is_file($legacyPath)) return 'data/avatars/' . $avatar;
    return '';
}

function records_bind_query(mysqli $mysqli, string $sql, string $types, array $params): array
{
    $rows = [];
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return $rows;
    }

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
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();

    return $rows;
}

function records_period_clause(string $field, string $period, string $date, string $week, string $month, array &$params, string &$types): string
{
    if ($period === 'day') {
        $params[] = $date;
        $types .= 's';
        return 'DATE(' . $field . ') = ?';
    }

    if ($period === 'week') {
        $weekYear = (int)date('o');
        $weekNo = (int)date('W');
        if (preg_match('/^(\d{4})-W(\d{2})$/', $week, $match)) {
            $weekYear = (int)$match[1];
            $weekNo = (int)$match[2];
        }
        $anchor = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $anchor->setISODate($weekYear, $weekNo);
        $params[] = $anchor->format('Y-m-d');
        $types .= 's';
        return 'YEARWEEK(' . $field . ', 1) = YEARWEEK(?, 1)';
    }

    $monthValue = date('Y-m');
    if (preg_match('/^\d{4}-\d{2}$/', $month)) {
        $monthValue = $month;
    }
    $params[] = $monthValue;
    $types .= 's';
    return "DATE_FORMAT(" . $field . ", '%Y-%m') = ?";
}

$type = strtolower(trim((string)($_GET['type'] ?? 'supply')));
$allowedTypes = ['supply', 'vehicle', 'claims', 'procurement', 'activity', 'inventory', 'ob_slip'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'supply';
}

$period = strtolower(trim((string)($_GET['period'] ?? 'month')));
if (!in_array($period, ['day', 'week', 'month'], true)) {
    $period = 'month';
}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
$week = trim((string)($_GET['week'] ?? date('o-\WW')));
$month = trim((string)($_GET['month'] ?? date('Y-m')));
$division = trim((string)($_GET['division'] ?? ''));
$employeeId = (int)($_GET['employee_id'] ?? 0);
$preparedBy = trim((string)($_GET['prepared_by'] ?? ''));
$notedBy = trim((string)($_GET['noted_by'] ?? ''));
$approvedBy = trim((string)($_GET['approved_by'] ?? ''));

$generatedAt = new DateTime('now', new DateTimeZone('Asia/Manila'));
$typeLabels = [
    'supply' => 'Supply Requests',
    'vehicle' => 'Vehicle Requests',
    'claims' => 'Claims',
    'procurement' => 'Procurement',
    'activity' => 'Activities',
  'ob_slip' => 'OB Slips',
    'inventory' => 'Inventory',
];
$typeLabel = $typeLabels[$type] ?? 'Records';

$employees = [];
$divisionOptions = [];
$employeeRes = $mysqli->query("SELECT id, first_name, last_name, division, avatar FROM users ORDER BY division ASC, last_name ASC, first_name ASC");
if ($employeeRes) {
    while ($row = $employeeRes->fetch_assoc()) {
        $employeeIdKey = (int)$row['id'];
        $employees[$employeeIdKey] = $row;
        $divisionValue = trim((string)($row['division'] ?? ''));
        if ($divisionValue !== '') {
            $divisionOptions[$divisionValue] = true;
        }
    }
}
$divisionOptions = array_keys($divisionOptions);
sort($divisionOptions, SORT_NATURAL | SORT_FLAG_CASE);

$divisionLabel = $division !== '' ? $division : 'All divisions';
$employeeLabel = 'All employees';
if ($employeeId > 0 && isset($employees[$employeeId])) {
    $employeeName = trim((string)($employees[$employeeId]['first_name'] ?? '') . ' ' . (string)($employees[$employeeId]['last_name'] ?? ''));
    if ($employeeName !== '') {
        $employeeLabel = $employeeName;
    }
}

$results = [];
$headers = [];
$periodFieldLabel = 'Updated';

$baseFilters = [];
$baseParams = [];
$baseTypes = '';
if ($division !== '') {
    $baseFilters[] = 'u.division = ?';
    $baseParams[] = $division;
    $baseTypes .= 's';
}
if ($employeeId > 0) {
    $baseFilters[] = 'u.id = ?';
    $baseParams[] = $employeeId;
    $baseTypes .= 'i';
}

if ($type === 'supply') {
    $periodParams = [];
    $periodTypes = '';
    $sql = "SELECT sr.id, sr.item, sr.variant, sr.quantity, sr.unit, sr.created_at, u.first_name, u.last_name, u.division FROM supply_requests sr INNER JOIN users u ON u.id = sr.user_id";
    $where = $baseFilters;
    $where[] = records_period_clause('sr.created_at', $period, $date, $week, $month, $periodParams, $periodTypes);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY sr.created_at DESC LIMIT 1000';
    $results = records_bind_query($mysqli, $sql, $baseTypes . $periodTypes, array_merge($baseParams, $periodParams));
    $headers = ['Requester', 'Item', 'Variant', 'Qty', 'Unit', 'Date', 'Division'];
    $periodFieldLabel = 'Created';
} elseif ($type === 'vehicle') {
    $periodParams = [];
    $periodTypes = '';
    $sql = "SELECT vr.id, vr.date_use, vr.departure_date, vr.departure_time, vr.expected_arrival_date, vr.expected_arrival_time, vr.vehicle_plate_no, vr.destination, vr.purpose, vr.status, vr.created_at, u.first_name, u.last_name, u.division FROM vehicle_requests vr INNER JOIN users u ON u.id = vr.user_id";
    $where = $baseFilters;
    $where[] = records_period_clause('vr.date_use', $period, $date, $week, $month, $periodParams, $periodTypes);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY vr.date_use DESC LIMIT 1000';
    $results = records_bind_query($mysqli, $sql, $baseTypes . $periodTypes, array_merge($baseParams, $periodParams));
    $headers = ['Requester', 'Vehicle', 'Use Date', 'Schedule', 'Destination', 'Purpose', 'Status', 'Division'];
    $periodFieldLabel = 'Use Date';
} elseif ($type === 'claims') {
    $periodParams = [];
    $periodTypes = '';
    $sql = "SELECT cm.id, cm.claim_ref, cm.received_eval_date, cm.pd_approval_date, cm.processing_date, cm.cheque_date, cm.remarks, cm.updated_at, u.first_name, u.last_name, u.division FROM claims_monitoring cm INNER JOIN users u ON u.id = cm.user_id";
    $where = $baseFilters;
    $periodField = "COALESCE(cm.cheque_date, cm.processing_date, cm.pd_approval_date, cm.received_eval_date, cm.updated_at)";
    $where[] = records_period_clause($periodField, $period, $date, $week, $month, $periodParams, $periodTypes);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY COALESCE(cm.cheque_date, cm.processing_date, cm.pd_approval_date, cm.received_eval_date, cm.updated_at) DESC, cm.id DESC LIMIT 1000';
    $results = records_bind_query($mysqli, $sql, $baseTypes . $periodTypes, array_merge($baseParams, $periodParams));
    $headers = ['Employee', 'Claim Ref', 'Received Eval', 'PD Approval', 'Processing', 'Cheque', 'Remarks', 'Division'];
    $periodFieldLabel = 'Latest Stage';
} elseif ($type === 'procurement') {
    $periodParams = [];
    $periodTypes = '';
    $sql = "SELECT pm.id, pm.tool_id, pm.approved_pr_date, pm.pd_approval_date, pm.retrieval_quotation_date, pm.abstract_canvas_date, pm.preparation_po_date, pm.issuance_po_date, pm.remarks, pm.updated_at, u.first_name, u.last_name, u.division FROM procurement_monitoring pm INNER JOIN users u ON u.id = pm.user_id";
    $where = $baseFilters;
    $periodField = "COALESCE(pm.issuance_po_date, pm.preparation_po_date, pm.abstract_canvas_date, pm.retrieval_quotation_date, pm.pd_approval_date, pm.approved_pr_date, pm.updated_at)";
    $where[] = records_period_clause($periodField, $period, $date, $week, $month, $periodParams, $periodTypes);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY COALESCE(pm.issuance_po_date, pm.preparation_po_date, pm.abstract_canvas_date, pm.retrieval_quotation_date, pm.pd_approval_date, pm.approved_pr_date, pm.updated_at) DESC, pm.id DESC LIMIT 1000';
    $results = records_bind_query($mysqli, $sql, $baseTypes . $periodTypes, array_merge($baseParams, $periodParams));
    $headers = ['Employee', 'Tool ID', 'Approved PR', 'PD Approval', 'Retrieval', 'Abstract', 'Prep PO', 'Issuance PO', 'Remarks', 'Division'];
    $periodFieldLabel = 'Latest Stage';
} elseif ($type === 'activity') {
    $periodParams = [];
    $periodTypes = '';
    $sql = "SELECT a.id, a.purpose, a.destination, a.start_datetime, a.end_datetime, a.created_at, u.first_name, u.last_name, u.division FROM activities a INNER JOIN users u ON u.id = a.user_id";
    $where = $baseFilters;
    $where[] = records_period_clause('a.start_datetime', $period, $date, $week, $month, $periodParams, $periodTypes);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY a.start_datetime DESC LIMIT 1000';
    $results = records_bind_query($mysqli, $sql, $baseTypes . $periodTypes, array_merge($baseParams, $periodParams));
    $headers = ['Employee', 'Purpose', 'Destination', 'Start', 'End', 'Division'];
    $periodFieldLabel = 'Start';
} else {
    $periodParams = [];
    $periodTypes = '';
    $sql = "SELECT us.id, us.item, us.variant, us.quantity, us.unit, us.threshold, us.updated_at, u.first_name, u.last_name, u.division FROM user_supplies us INNER JOIN users u ON u.id = us.user_id";
    $where = $baseFilters;
    $where[] = records_period_clause('us.updated_at', $period, $date, $week, $month, $periodParams, $periodTypes);
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY us.updated_at DESC LIMIT 1000';
    $results = records_bind_query($mysqli, $sql, $baseTypes . $periodTypes, array_merge($baseParams, $periodParams));
    $headers = ['Employee', 'Item', 'Variant', 'Qty', 'Unit', 'Threshold', 'Updated', 'Division'];
    $periodFieldLabel = 'Updated';
}

// OB Slip handling
if ($type === 'ob_slip') {
  $periodParams = [];
  $periodTypes = '';
  $sql = "SELECT ob.id, ob.ob_type, ob.slip_date, ob.employee_name, ob.section_name, ob.purpose, ob.destination, ob.departure_time, ob.return_time, ob.created_at, u.first_name, u.last_name, u.division FROM ob_slips ob INNER JOIN users u ON u.id = ob.user_id";
  $where = $baseFilters;
  $where[] = records_period_clause('ob.slip_date', $period, $date, $week, $month, $periodParams, $periodTypes);
  if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  $sql .= ' ORDER BY ob.slip_date DESC LIMIT 1000';
  $results = records_bind_query($mysqli, $sql, $baseTypes . $periodTypes, array_merge($baseParams, $periodParams));
  $headers = ['Employee', 'Type', 'Date', 'Departure', 'Return', 'Destination', 'Purpose', 'Division'];
  $periodFieldLabel = 'Slip Date';
}

$periodLabel = ucfirst($period);
$periodValueLabel = $period === 'day' ? $date : ($period === 'week' ? $week : $month);
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Records Print</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 18px; color: #111827; font-family: "Times New Roman", Times, serif; }
    .report-shell { max-width: 1280px; margin: 0 auto; }
    .report-header { border: 1px solid #111827; padding: 10px 12px 12px; margin-bottom: 10px; text-align: center; }
    .report-header-top { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; }
    .report-logo { width: 62px; height: 62px; object-fit: contain; }
    .report-org { margin: 0; font-size: 0.82rem; letter-spacing: 0.08em; text-transform: uppercase; text-align: center; color: #374151; }
    .report-office { margin: 2px 0 0; font-size: 1.02rem; font-weight: 700; text-align: center; letter-spacing: 0.02em; }
    .report-title { margin: 7px 0 0; font-size: 1.16rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; text-align: center; }
    .report-subtitle { margin: 3px 0 0; font-size: 0.84rem; text-align: center; color: #4b5563; }
    .report-divider { width: 180px; height: 1px; background: #9ca3af; margin: 8px auto 6px; }
    .report-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 6px; margin-bottom: 10px; }
    .meta-item { border: 1px solid #9ca3af; padding: 5px 7px; }
    .meta-label { display: block; font-size: 0.68rem; color: #374151; text-transform: uppercase; letter-spacing: 0.03em; }
    .meta-value { font-size: 0.82rem; font-weight: 600; color: #111827; }
    table { width: 100%; font-size: 11px; border-collapse: collapse; }
    th, td { padding: 0.34rem; vertical-align: top; border: 1px solid #9ca3af !important; }
    thead th { background: #e5e7eb !important; color: #111827; text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.02em; white-space: nowrap; }
    .report-table-wrap { border: 1px solid #111827; overflow: hidden; }
    .signatory-form {
      border: 1px solid #9ca3af;
      padding: 10px;
      margin-bottom: 10px;
      background: #fff;
    }
    .signatory-form .form-label {
      font-size: 0.78rem;
      margin-bottom: 0.2rem;
    }
    .signatory-form .form-control {
      font-size: 0.85rem;
    }
    .signature-block { margin-top: 20px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .sig-item { text-align: center; }
    .sig-line { margin: 28px 18px 4px; min-height: 24px; border-bottom: 1px solid #111827; display: flex; align-items: flex-end; justify-content: center; }
    .sig-name { min-height: 1.1rem; font-size: 0.86rem; font-weight: 700; }
    .sig-role { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
    .print-user-stamp { margin-top: 12px; text-align: right; font-size: 0.72rem; color: #374151; }
    @media print {
      .no-print, .no-print * { display: none !important; visibility: hidden !important; }
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
        <div class="text-muted">Adjust the filters below, then print or save as PDF.</div>
      </div>
      <div>
        <button class="btn btn-primary" onclick="window.print();">Print PDF</button>
        <a class="btn btn-secondary" href="dashboard.php">Close</a>
      </div>
    </div>

    <?php if ($type !== 'ob_slip'): ?>
    <form class="signatory-form no-print" method="get" action="records_print.php">
      <input type="hidden" name="type" value="<?php echo records_escape($type); ?>">
      <input type="hidden" name="period" value="<?php echo records_escape($period); ?>">
      <input type="hidden" name="date" value="<?php echo records_escape($date); ?>">
      <input type="hidden" name="week" value="<?php echo records_escape($week); ?>">
      <input type="hidden" name="month" value="<?php echo records_escape($month); ?>">
      <input type="hidden" name="division" value="<?php echo records_escape($division); ?>">
      <input type="hidden" name="employee_id" value="<?php echo (int)$employeeId; ?>">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label for="preparedByInput" class="form-label">Prepared by</label>
          <input id="preparedByInput" name="prepared_by" type="text" class="form-control" value="<?php echo records_escape($preparedBy); ?>" placeholder="Enter prepared by name">
        </div>
        <div class="col-md-4">
          <label for="notedByInput" class="form-label">Noted by</label>
          <input id="notedByInput" name="noted_by" type="text" class="form-control" value="<?php echo records_escape($notedBy); ?>" placeholder="Enter noted by name">
        </div>
        <div class="col-md-4">
          <label for="approvedByInput" class="form-label">Approved by</label>
          <input id="approvedByInput" name="approved_by" type="text" class="form-control" value="<?php echo records_escape($approvedBy); ?>" placeholder="Enter approved by name">
        </div>
        <div class="col-12 d-flex gap-2 mt-2">
          <button class="btn btn-primary" type="button" onclick="window.print();">Print PDF</button>
        </div>
      </div>
    </form>
    <?php endif; ?>

    <?php if ($type !== 'ob_slip'): ?>
    <div class="report-header">
      <div class="report-header-top">
        <img src="assets/logoDTI.png" alt="DTI Logo" class="report-logo">
        <p class="report-org">Republic of the Philippines</p>
        <p class="report-office">Department of Trade and Industry - Region 2</p>
      </div>
      <div class="report-divider"></div>
      <h1 class="report-title">Records Monitoring Report</h1>
      <p class="report-subtitle"><?php echo records_escape($typeLabel); ?> - Official Printout</p>
    </div>

    <div class="report-meta">
      <div class="meta-item"><span class="meta-label">Record Type</span><span class="meta-value"><?php echo records_escape($typeLabel); ?></span></div>
      <div class="meta-item"><span class="meta-label">Division</span><span class="meta-value"><?php echo records_escape($divisionLabel); ?></span></div>
      <div class="meta-item"><span class="meta-label">Employee</span><span class="meta-value"><?php echo records_escape($employeeLabel); ?></span></div>
      <div class="meta-item"><span class="meta-label">Period</span><span class="meta-value"><?php echo records_escape($periodLabel); ?></span></div>
      <div class="meta-item"><span class="meta-label">Period Value</span><span class="meta-value"><?php echo records_escape($periodValueLabel); ?></span></div>
      <div class="meta-item"><span class="meta-label">Generated</span><span class="meta-value"><?php echo records_escape($generatedAt->format('Y-m-d h:i A')); ?></span></div>
    </div>
    <?php endif; ?>

    <div class="report-table-wrap">
      <?php if (empty($results)): ?>
        <div class="p-3">No records found.</div>
      <?php else: ?>
        <?php if ($type === 'ob_slip'): ?>
          <style>
            .ob-page { page-break-after: always; margin-bottom: 0; }
            .ob-page:last-child { page-break-after: auto; }
            .ob-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
            .ob-card {
              border: 1px solid #111827;
              padding: 8px;
              height: 128mm;
              position: relative;
              box-sizing: border-box;
              overflow: hidden;
              font-size: 0.72rem;
            }
            .ob-head { text-align: center; line-height: 1.18; font-size: 0.78rem; }
            .ob-title { text-align: center; font-weight: 700; margin-top: 6px; letter-spacing: 0.03em; }
            .ob-checks { display: flex; justify-content: center; gap: 24px; margin-top: 8px; font-weight: 700; }
            .ob-row { display: grid; grid-template-columns: 30% 70%; align-items: end; margin-top: 3px; }
            .ob-row .k { font-weight: 700; letter-spacing: 0.02em; }
            .ob-line { border-bottom: 1px solid #111827; min-height: 15px; line-height: 1.1; overflow: hidden; }
            .ob-multiline { margin-top: 4px; }
            .ob-multiline .line { border-bottom: 1px solid #111827; min-height: 13px; line-height: 1.1; }
            .ob-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            .ob-table th, .ob-table td { border: 1px solid #111827; padding: 3px 5px; }
            .ob-table th { text-align: center; font-weight: 700; }
            .ob-sign-label { margin-top: 8px; font-weight: 700; }
            .ob-sign-line { border-bottom: 1px solid #111827; min-height: 16px; }
            .ob-sign-line.attested { margin-top: 14px; }
            .ob-sign-line.with-name {
              border-bottom: 0;
              display: flex;
              align-items: flex-end;
              gap: 0;
            }
            .ob-sign-line.with-name::before,
            .ob-sign-line.with-name::after {
              content: '';
              flex: 1 1 auto;
              border-bottom: 1px solid #111827;
            }
            .ob-sign-name {
              font-weight: 700;
              padding: 0 4px;
              border-bottom: 1px solid #111827;
              line-height: 1.1;
              white-space: nowrap;
            }
            .ob-sign-center { text-align: center; font-weight: 700; margin-top: 3px; line-height: 1.15; }
            .ob-rev {
              position: absolute;
              right: 8px;
              bottom: 6px;
              font-weight: 700;
              margin-top: 0;
            }

            @media print {
              @page {
                size: A4 portrait;
                margin: 8mm;
              }
              body { padding: 0; }
              .report-shell { max-width: none; }
              .ob-page { break-after: page; }
              .ob-page:last-child { break-after: auto; }
              .ob-grid { gap: 4mm; }
              .ob-card { break-inside: avoid; height: 128mm; }
            }
          </style>
          <?php
            // chunk results into pages of 4
            $chunks = array_chunk($results, 4);
            foreach ($chunks as $chunk):
          ?>
            <div class="ob-page">
              <div class="ob-grid">
                <?php foreach ($chunk as $row): ?>
                  <div class="ob-card">
                    <div class="ob-head">Republic of the Philippines<br>Department of Trade and Industry<br>Nueva Vizcaya Provincial Office</div>
                    <div class="ob-title">OB SLIP</div>
                    <div class="ob-checks">
                      <span>[ <?php echo (strcasecmp((string)($row['ob_type'] ?? ''), 'OFFICIAL') === 0 ? 'X' : ''); ?> ] OFFICIAL</span>
                      <span>[ <?php echo (strcasecmp((string)($row['ob_type'] ?? ''), 'PERSONAL') === 0 ? 'X' : ''); ?> ] PERSONAL</span>
                    </div>

                    <div class="ob-row"><div class="k">DATE:</div><div class="ob-line"><?php echo records_escape(records_display_date($row['slip_date'] ?? '')); ?></div></div>
                    <div class="ob-row"><div class="k">NAME:</div><div class="ob-line"><?php echo records_escape((string)($row['employee_name'] ?? trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')))); ?></div></div>
                    <div class="ob-row"><div class="k">SECTION:</div><div class="ob-line"><?php echo records_escape((string)($row['section_name'] ?? '')); ?></div></div>

                    <div class="ob-row"><div class="k">PURPOSE:</div><div class="ob-multiline"><div class="line"></div><div class="line"></div><div class="line"></div></div></div>
                    <div style="margin-left:30%; margin-top:-39px; padding-left:3px; line-height:1.2; min-height:39px;"><?php echo nl2br(records_escape((string)($row['purpose'] ?? ''))); ?></div>

                    <div class="ob-row"><div class="k">DESTINATION:</div><div class="ob-multiline"><div class="line"></div><div class="line"></div><div class="line"></div></div></div>
                    <div style="margin-left:30%; margin-top:-39px; padding-left:3px; line-height:1.2; min-height:39px;"><?php echo nl2br(records_escape((string)($row['destination'] ?? ''))); ?></div>

                    <table class="ob-table">
                      <tr><th colspan="2">TIME</th></tr>
                      <tr><td style="width:68%; font-weight:700;">DEPARTURE IN THE OFFICE</td><td><?php echo records_escape((string)($row['departure_time'] ?? '')); ?></td></tr>
                      <tr><td style="font-weight:700;">RETURN IN THE OFFICE</td><td><?php echo records_escape((string)($row['return_time'] ?? '')); ?></td></tr>
                    </table>

                    <div class="ob-sign-label">APPROVED BY:</div>
                    <div class="ob-sign-line with-name"><span class="ob-sign-name">LENORE LEE S. LOPEZ</span></div>
                    <div class="ob-sign-center">SIGNATURE OVER PRINTED NAME OF AUTHORIZED<br>SIGNATORY</div>
                    <div class="ob-sign-label">ATTESTED BY:</div>
                    <div class="ob-sign-line attested"></div>
                    <div class="ob-sign-center">GUARD ON DUTY</div>
                    <div class="ob-rev">Rev.11-28-18</div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
        <table class="table table-bordered mb-0">
          <thead>
            <tr>
              <?php foreach ($headers as $header): ?>
                <th><?php echo records_escape($header); ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($type === 'supply'): ?>
              <?php foreach ($results as $row): ?>
                <tr>
                  <td><?php echo records_escape(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''))); ?></td>
                  <td><?php echo records_escape((string)($row['item'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['variant'] ?? '')); ?></td>
                  <td><?php echo (int)($row['quantity'] ?? 0); ?></td>
                  <td><?php echo records_escape((string)($row['unit'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['created_at'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['division'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php elseif ($type === 'vehicle'): ?>
              <?php foreach ($results as $row): ?>
                <tr>
                  <td><?php echo records_escape(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''))); ?></td>
                  <td><?php echo records_escape((string)($row['vehicle_plate_no'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_date($row['date_use'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_date($row['departure_date'] ?? '') . ' ' . (string)($row['departure_time'] ?? '') . ' to ' . records_display_date($row['expected_arrival_date'] ?? '') . ' ' . (string)($row['expected_arrival_time'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['destination'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['purpose'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['status'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['division'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php elseif ($type === 'claims'): ?>
              <?php foreach ($results as $row): ?>
                <tr>
                  <td><?php echo records_escape(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''))); ?></td>
                  <td><?php echo records_escape((string)($row['claim_ref'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['received_eval_date'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['pd_approval_date'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['processing_date'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['cheque_date'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['remarks'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['division'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php elseif ($type === 'procurement'): ?>
              <?php foreach ($results as $row): ?>
                <tr>
                  <td><?php echo records_escape(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''))); ?></td>
                  <td><?php echo records_escape((string)($row['tool_id'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['approved_pr_date'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['pd_approval_date'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['retrieval_quotation_date'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['abstract_canvas_date'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['preparation_po_date'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['issuance_po_date'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['remarks'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['division'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php elseif ($type === 'activity'): ?>
              <?php foreach ($results as $row): ?>
                <tr>
                  <td><?php echo records_escape(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''))); ?></td>
                  <td><?php echo records_escape((string)($row['purpose'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['destination'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['start_datetime'] ?? '')); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['end_datetime'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['division'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <?php foreach ($results as $row): ?>
                <tr>
                  <td><?php echo records_escape(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''))); ?></td>
                  <td><?php echo records_escape((string)($row['item'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['variant'] ?? '')); ?></td>
                  <td><?php echo (int)($row['quantity'] ?? 0); ?></td>
                  <td><?php echo records_escape((string)($row['unit'] ?? '')); ?></td>
                  <td><?php echo (int)($row['threshold'] ?? 0); ?></td>
                  <td><?php echo records_escape(records_display_datetime($row['updated_at'] ?? '')); ?></td>
                  <td><?php echo records_escape((string)($row['division'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if ($type !== 'ob_slip'): ?>
    <div class="signature-block">
      <div class="sig-item">
        <div class="sig-line"><span class="sig-name" id="preparedByName"><?php echo records_escape($preparedBy !== '' ? $preparedBy : ''); ?></span></div>
        <div class="sig-role">Prepared by</div>
      </div>
      <div class="sig-item">
        <div class="sig-line"><span class="sig-name" id="notedByName"><?php echo records_escape($notedBy !== '' ? $notedBy : ''); ?></span></div>
        <div class="sig-role">Noted by</div>
      </div>
      <div class="sig-item">
        <div class="sig-line"><span class="sig-name" id="approvedByName"><?php echo records_escape($approvedBy !== '' ? $approvedBy : ''); ?></span></div>
        <div class="sig-role">Approved by</div>
      </div>
    </div>

    <div class="print-user-stamp">
      <?php echo records_escape($printedBy); ?> - <?php echo records_escape($printedDivision); ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($type !== 'ob_slip'): ?>
  <script>
    (function () {
      var preparedInput = document.getElementById('preparedByInput');
      var notedInput = document.getElementById('notedByInput');
      var approvedInput = document.getElementById('approvedByInput');
      var preparedOutput = document.getElementById('preparedByName');
      var notedOutput = document.getElementById('notedByName');
      var approvedOutput = document.getElementById('approvedByName');

      function setText(el, value) {
        if (!el) return;
        el.textContent = (value || '').trim();
      }

      function syncSignatories() {
        setText(preparedOutput, preparedInput ? preparedInput.value : '');
        setText(notedOutput, notedInput ? notedInput.value : '');
        setText(approvedOutput, approvedInput ? approvedInput.value : '');
      }

      if (preparedInput) preparedInput.addEventListener('input', syncSignatories);
      if (notedInput) notedInput.addEventListener('input', syncSignatories);
      if (approvedInput) approvedInput.addEventListener('input', syncSignatories);
      syncSignatories();
    })();
  </script>
  <?php endif; ?>

</body>
</html>
