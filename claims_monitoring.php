<?php
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!function_exists('claims_date_to_db')) {
    function claims_date_to_db($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt) {
            return null;
        }
        return $dt->format('Y-m-d') . ' 00:00:00';
    }
}

if (!function_exists('claims_date_display')) {
    function claims_date_display($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '---';
        }
        $ts = strtotime($value);
        return $ts ? date('M d, Y', $ts) : '---';
    }
}

if (!function_exists('claims_safe_text')) {
    function claims_safe_text($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('claims_avatar_url')) {
  function claims_avatar_url($avatar)
  {
    $avatar = trim((string)$avatar);
    if ($avatar === '') return '';
    $uploadPath = __DIR__ . '/uploads/' . $avatar;
    $legacyPath = __DIR__ . '/data/avatars/' . $avatar;
    if (is_file($uploadPath)) return 'uploads/' . $avatar;
    if (is_file($legacyPath)) return 'data/avatars/' . $avatar;
    return '';
  }
}

$statusFlash = $_SESSION['status_flash'] ?? null;
unset($_SESSION['status_flash']);

$user = $_SESSION['user'];
$now = new DateTime('now');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_claim'])) {
    $tokenOk = hash_equals($_SESSION['csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''));
    if (!$tokenOk) {
        $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        header('Location: claims_monitoring.php');
        exit;
    }

    $claimId = (int)($_POST['claim_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    $claimRef = trim((string)($_POST['claim_ref'] ?? ''));
    $receivedEvalDate = claims_date_to_db($_POST['received_eval_date'] ?? '');
    $pdApprovalDate = claims_date_to_db($_POST['pd_approval_date'] ?? '');
    $processingDate = claims_date_to_db($_POST['processing_date'] ?? '');
    $chequeDate = claims_date_to_db($_POST['cheque_date'] ?? '');
    $remarks = trim((string)($_POST['remarks'] ?? ''));

    if ($userId <= 0 || $claimRef === '') {
        $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Employee and claim reference are required.'];
        header('Location: claims_monitoring.php');
        exit;
    }

    $employeeStmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    if (!$employeeStmt) {
        $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Unable to validate employee.'];
        header('Location: claims_monitoring.php');
        exit;
    }
    $employeeStmt->bind_param('i', $userId);
    $employeeStmt->execute();
    $employeeRes = $employeeStmt->get_result();
    if ($employeeRes->num_rows === 0) {
        $employeeStmt->close();
        $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Selected employee was not found.'];
        header('Location: claims_monitoring.php');
        exit;
    }
    $employeeStmt->close();

    if ($claimId > 0) {
        $stmt = $mysqli->prepare("UPDATE claims_monitoring SET user_id = ?, claim_ref = ?, received_eval_date = ?, pd_approval_date = ?, processing_date = ?, cheque_date = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Failed to prepare update.'];
            header('Location: claims_monitoring.php');
            exit;
        }
        $stmt->bind_param('issssssi', $userId, $claimRef, $receivedEvalDate, $pdApprovalDate, $processingDate, $chequeDate, $remarks, $claimId);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION['status_flash'] = $ok
            ? ['type' => 'success', 'message' => 'Claim record updated.']
            : ['type' => 'danger', 'message' => 'Failed to update claim record.'];
    } else {
        $stmt = $mysqli->prepare("INSERT INTO claims_monitoring (user_id, claim_ref, received_eval_date, pd_approval_date, processing_date, cheque_date, remarks, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if (!$stmt) {
            $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Failed to prepare insert.'];
            header('Location: claims_monitoring.php');
            exit;
        }
        $stmt->bind_param('issssss', $userId, $claimRef, $receivedEvalDate, $pdApprovalDate, $processingDate, $chequeDate, $remarks);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION['status_flash'] = $ok
            ? ['type' => 'success', 'message' => 'Claim record added.']
            : ['type' => 'danger', 'message' => 'Failed to add claim record.'];
    }

    header('Location: claims_monitoring.php');
    exit;
}

$employees = [];
$empRes = $mysqli->query("SELECT id, first_name, last_name, division, avatar FROM users ORDER BY division ASC, last_name ASC, first_name ASC");
if ($empRes) {
    while ($row = $empRes->fetch_assoc()) {
        $employees[(int)$row['id']] = $row;
    }
}

$divisionOptions = [];
foreach ($employees as $empRow) {
    $divisionValue = trim((string)($empRow['division'] ?? ''));
    if ($divisionValue !== '') {
        $divisionOptions[$divisionValue] = true;
    }
}
$divisionOptions = array_keys($divisionOptions);
sort($divisionOptions, SORT_NATURAL | SORT_FLAG_CASE);

$claims = [];
$claimsSql = "SELECT cm.id, cm.user_id, cm.claim_ref, cm.received_eval_date, cm.pd_approval_date, cm.processing_date, cm.cheque_date, cm.remarks, cm.updated_at, u.first_name, u.last_name, u.division, u.avatar FROM claims_monitoring cm JOIN users u ON u.id = cm.user_id ORDER BY COALESCE(cm.cheque_date, cm.processing_date, cm.pd_approval_date, cm.received_eval_date, cm.updated_at) DESC, cm.id DESC";
$claimsRes = $mysqli->query($claimsSql);
if ($claimsRes) {
    while ($row = $claimsRes->fetch_assoc()) {
        $row['employee_name'] = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($row['employee_name'] === '') {
            $row['employee_name'] = 'Unknown Employee';
        }
        $row['employee_avatar'] = claims_avatar_url($row['avatar'] ?? '');
        $claims[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Claims Monitoring Tool</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .claims-shell {
      max-width: none;
      width: 100%;
      margin: 0;
      padding-left: 0.4rem;
      padding-right: 0.4rem;
    }
    .claims-shell .section-card {
      padding: 1rem;
    }
    .claims-shell .section-head {
      margin-bottom: 0.6rem;
    }
    .claims-table-wrap {
      border: 1px solid var(--surface-contrast);
      border-radius: 14px;
      overflow: hidden;
      background: var(--card-bg);
      backdrop-filter: blur(8px);
      box-shadow: var(--shadow-1);
    }
    .claims-table {
      margin-bottom: 0;
      min-width: 1180px;
    }
    .claims-table th,
    .claims-table td {
      padding: 0.55rem 0.45rem;
    }
    .claims-table thead th {
      background: rgba(148, 163, 184, 0.08);
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--text-dim);
      border-bottom: 1px solid var(--surface-contrast);
      white-space: nowrap;
      vertical-align: middle;
    }
    .claims-table tbody td {
      vertical-align: top;
      border-color: var(--surface-contrast);
      color: var(--text);
    }
    .claims-employee {
      min-width: 220px;
    }
    .claims-avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(100, 116, 139, 0.2);
      background: var(--surface);
    }
    .claims-fallback {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--primary);
      color: #fff;
      font-weight: 700;
      border: 2px solid rgba(255,255,255,0.05);
    }
    .claims-stage-cell {
      min-width: 170px;
    }
    .claims-stage-cell .stage-value {
      font-size: 1.02rem;
      font-weight: 700;
      letter-spacing: 0.01em;
    }
    .claims-table tbody tr.claim-stage-received > td {
      background-color: rgba(251, 191, 36, 0.12) !important;
    }
    .claims-table tbody tr.claim-stage-pd > td {
      background-color: rgba(59, 130, 246, 0.11) !important;
    }
    .claims-table tbody tr.claim-stage-processing > td {
      background-color: rgba(99, 102, 241, 0.12) !important;
    }
    .claims-table tbody tr.claim-stage-complete > td {
      background-color: rgba(16, 185, 129, 0.11) !important;
    }
    .claims-meta {
      white-space: nowrap;
      font-size: 0.9rem;
    }
    .claims-date {
      display: inline-flex;
      flex-direction: column;
      gap: 0.15rem;
    }
    .claims-date .stage-label {
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: var(--muted);
    }
    .claims-date .stage-value {
      font-weight: 600;
      color: var(--text);
    }
    .dark-mode .claims-table thead th {
      background: rgba(255,255,255,0.02);
      color: var(--text-dim);
    }
    .dark-mode .claims-table tbody td .text-muted,
    .dark-mode .claims-table tbody td small {
      color: var(--muted);
    }
    .dark-mode .claims-table tbody tr.claim-stage-received > td {
      background-color: rgba(251, 191, 36, 0.08) !important;
    }
    .dark-mode .claims-table tbody tr.claim-stage-pd > td {
      background-color: rgba(59, 130, 246, 0.08) !important;
    }
    .dark-mode .claims-table tbody tr.claim-stage-processing > td {
      background-color: rgba(99, 102, 241, 0.08) !important;
    }
    .dark-mode .claims-table tbody tr.claim-stage-complete > td {
      background-color: rgba(16, 185, 129, 0.08) !important;
    }
    .claims-stage-badge {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      white-space: nowrap;
    }
    .claims-modal-header {
      position: sticky;
      top: 0;
      z-index: 5;
      background: var(--surface);
      border-bottom: 1px solid var(--surface-contrast);
    }
    .claims-modal-content {
      border: 1px solid var(--surface-contrast);
      border-radius: 16px;
      overflow: hidden;
      background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,250,252,0.95));
      box-shadow: 0 20px 50px rgba(2, 6, 23, 0.24);
      min-height: 100vh;
    }
    .claims-modal-body {
      background: linear-gradient(180deg, rgba(248,250,252,0.6), rgba(241,245,249,0.45));
      padding: 1rem;
    }
    .claims-modal-footer {
      position: sticky;
      bottom: 0;
      z-index: 4;
      background: var(--surface);
      border-top: 1px solid var(--surface-contrast);
    }
    .claims-form-section {
      border: 1px solid var(--surface-contrast);
      border-radius: 12px;
      padding: 0.9rem;
      background: rgba(255, 255, 255, 0.65);
    }
    .claims-form-section-title {
      font-size: 0.83rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--text-dim);
      margin-bottom: 0.75rem;
    }
    .claims-modal-grid .form-label {
      font-weight: 600;
      margin-bottom: 0.35rem;
    }
    .dark-mode .claims-modal-content {
      background: linear-gradient(180deg, rgba(8,14,26,0.96), rgba(10,16,30,0.95));
      border-color: var(--surface-contrast);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.55);
    }
    .dark-mode .claims-modal-body {
      background: linear-gradient(180deg, rgba(10,16,30,0.58), rgba(7,12,24,0.48));
    }
    .dark-mode .claims-form-section {
      background: rgba(255,255,255,0.03);
      border-color: var(--surface-contrast);
    }
    .claims-modal-grid > [class*="col-"] {
      margin-bottom: 0;
    }
    .modal-fullscreen .claims-modal-content {
      border-radius: 0;
      border-left: 0;
      border-right: 0;
      box-shadow: none;
    }
    .modal-fullscreen .claims-modal-body {
      padding: 1.25rem;
    }
  </style>
  <style>
    /* Ensure footer stays at bottom on short pages */
    .dti-page-flex { display: flex; flex-direction: column; min-height: 100vh; }
    .dti-page-flex .claims-shell { flex: 1 0 auto; }
  </style>
</head>
<body class="p-4 dti-page-flex">
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container-fluid mt-4 claims-shell">
  <div class="section-card section-card--claims">
    <div class="section-head d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="d-flex align-items-center">
        <span class="section-icon"><i class="bi bi-clipboard-data"></i></span>
        <div>
          <h1 class="h3 mb-1">Claims Monitoring Tool</h1>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#claimModal" onclick="openClaimModal()"><i class="bi bi-plus-circle"></i> Add Claim</button>
        <button type="button" class="btn btn-outline-primary" id="editSelectedClaimBtn" disabled><i class="bi bi-pencil-square"></i> Edit Selected</button>
      </div>
    </div>

    <div class="alert alert-light border mt-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div><strong>Updated:</strong> <span id="claimsUpdatedAt"><?php echo claims_safe_text($now->format('M d, Y h:i A')); ?></span></div>
      <div class="small text-muted"><i class="bi bi-people"></i> <?php echo count($claims); ?> claim record(s)</div>
    </div>

    <div class="mb-3 d-flex gap-2 align-items-center flex-wrap">
      <div class="input-group" style="max-width: 460px;">
        <input id="claimsSearchInput" type="search" class="form-control" placeholder="Search employee, division, voucher..." aria-label="Search claims">
        <button id="claimsSearchBtn" class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i> Search</button>
      </div>
      <div style="min-width: 220px; max-width: 280px;">
        <select id="claimsDivisionFilter" class="form-select">
          <option value="">All divisions</option>
          <?php foreach ($divisionOptions as $divisionName): ?>
            <option value="<?php echo claims_safe_text(strtolower($divisionName)); ?>"><?php echo claims_safe_text($divisionName); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if (is_array($statusFlash) && !empty($statusFlash['message'])): ?>
      <div class="alert alert-<?php echo (($statusFlash['type'] ?? '') === 'danger') ? 'danger' : 'success'; ?> mb-3">
        <?php echo claims_safe_text((string)$statusFlash['message']); ?>
      </div>
    <?php endif; ?>

    <div id="claimsEmpty" class="alert alert-secondary<?php echo empty($claims) ? '' : ' d-none'; ?>">No claim records found.</div>
    <div id="claimsTableWrap" class="claims-table-wrap table-responsive<?php echo empty($claims) ? ' d-none' : ''; ?>">
      <table class="table claims-table align-middle">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Voucher</th>
            <th>Receive for Evaluation / Checking</th>
            <th>For PD's Approval</th>
            <th>Approved / For Processing</th>
            <th>Deposited / Credited / Issued Cheque</th>
            <th>Remarks</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="claimsTableBody">
          <?php foreach ($claims as $claim): ?>
            <?php
              $received = claims_date_display($claim['received_eval_date'] ?? '');
              $pdApproval = claims_date_display($claim['pd_approval_date'] ?? '');
              $processing = claims_date_display($claim['processing_date'] ?? '');
              $cheque = claims_date_display($claim['cheque_date'] ?? '');
              $remarks = trim((string)($claim['remarks'] ?? ''));
              $avatarUrl = (string)($claim['employee_avatar'] ?? '');
              $rowClass = '';
              if (trim((string)($claim['cheque_date'] ?? '')) !== '') {
                $rowClass = 'claim-stage-complete';
              } elseif (trim((string)($claim['processing_date'] ?? '')) !== '') {
                $rowClass = 'claim-stage-processing';
              } elseif (trim((string)($claim['pd_approval_date'] ?? '')) !== '') {
                $rowClass = 'claim-stage-pd';
              } elseif (trim((string)($claim['received_eval_date'] ?? '')) !== '') {
                $rowClass = 'claim-stage-received';
              }
            ?>
            <tr class="claim-row <?php echo $rowClass; ?>" role="button" tabindex="0" data-division="<?php echo claims_safe_text(strtolower((string)($claim['division'] ?? ''))); ?>" data-claim-search="<?php echo claims_safe_text(strtolower(trim(($claim['employee_name'] ?? '') . ' ' . ($claim['division'] ?? '') . ' ' . ($claim['claim_ref'] ?? '') . ' ' . $received . ' ' . $pdApproval . ' ' . $processing . ' ' . $cheque . ' ' . $remarks))); ?>" data-claim-json='<?php echo json_encode([
              "id" => (int)$claim['id'],
              "user_id" => (int)$claim['user_id'],
              "division" => (string)($claim['division'] ?? ''),
              "claim_ref" => (string)$claim['claim_ref'],
              "received_eval_date" => trim((string)($claim['received_eval_date'] ?? '')) !== '' ? date("Y-m-d", strtotime((string)$claim['received_eval_date'])) : '',
              "pd_approval_date" => trim((string)($claim['pd_approval_date'] ?? '')) !== '' ? date("Y-m-d", strtotime((string)$claim['pd_approval_date'])) : '',
              "processing_date" => trim((string)($claim['processing_date'] ?? '')) !== '' ? date("Y-m-d", strtotime((string)$claim['processing_date'])) : '',
              "cheque_date" => trim((string)$claim['cheque_date'] ?? '') !== '' ? date("Y-m-d", strtotime((string)$claim['cheque_date'])) : '',
              "remarks" => (string)($claim['remarks'] ?? ''),
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
              <td class="claims-employee">
                <div class="d-flex align-items-center gap-2">
                  <?php if ($avatarUrl !== ''): ?>
                    <img src="<?php echo claims_safe_text($avatarUrl); ?>" alt="Profile" class="claims-avatar">
                  <?php else: ?>
                    <span class="claims-fallback"><?php echo claims_safe_text(strtoupper(substr((string)$claim['employee_name'], 0, 1))); ?></span>
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?php echo claims_safe_text($claim['employee_name']); ?></div>
                    <div class="small text-muted"><?php echo claims_safe_text((string)($claim['division'] ?? '')); ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="fw-semibold"><?php echo claims_safe_text((string)($claim['claim_ref'] ?? '')); ?></div>
              </td>
              <td class="claims-stage-cell">
                <div class="claims-date">
                  <span class="stage-label">Receive for Evaluation/Checking</span>
                  <span class="stage-value"><?php echo claims_safe_text($received); ?></span>
                </div>
              </td>
              <td class="claims-stage-cell">
                <div class="claims-date">
                  <span class="stage-label">For PD's Approval</span>
                  <span class="stage-value"><?php echo claims_safe_text($pdApproval); ?></span>
                </div>
              </td>
              <td class="claims-stage-cell">
                <div class="claims-date">
                  <span class="stage-label">Approved and for Processing</span>
                  <span class="stage-value"><?php echo claims_safe_text($processing); ?></span>
                </div>
              </td>
              <td class="claims-stage-cell">
                <div class="claims-date">
                  <span class="stage-label">Deposited/Credited/Issued Cheque</span>
                  <span class="stage-value"><?php echo claims_safe_text($cheque); ?></span>
                </div>
              </td>
              <td><?php echo claims_safe_text($remarks !== '' ? $remarks : '---'); ?></td>
              <td>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary"
                  data-bs-toggle="modal"
                  data-bs-target="#claimModal"
                  onclick='openClaimModal(<?php echo json_encode([
                      "id" => (int)$claim['id'],
                      "user_id" => (int)$claim['user_id'],
                      "claim_ref" => (string)$claim['claim_ref'],
                      "received_eval_date" => trim((string)($claim['received_eval_date'] ?? '')) !== '' ? date("Y-m-d", strtotime((string)$claim['received_eval_date'])) : '',
                      "pd_approval_date" => trim((string)($claim['pd_approval_date'] ?? '')) !== '' ? date("Y-m-d", strtotime((string)$claim['pd_approval_date'])) : '',
                      "processing_date" => trim((string)($claim['processing_date'] ?? '')) !== '' ? date("Y-m-d", strtotime((string)$claim['processing_date'])) : '',
                      "cheque_date" => trim((string)$claim['cheque_date'] ?? '') !== '' ? date("Y-m-d", strtotime((string)$claim['cheque_date'])) : '',
                      "remarks" => (string)($claim['remarks'] ?? ''),
                  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                  <i class="bi bi-pencil"></i> Edit
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="claimModal" tabindex="-1" aria-labelledby="claimModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
    <div class="modal-content claims-modal-content">
      <div class="modal-header claims-modal-header">
        <h1 class="modal-title fs-5" id="claimModalLabel">Add Claim Record</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="claims_monitoring.php" id="claimForm">
        <div class="modal-body claims-modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo claims_safe_text($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="claim_id" id="claimId" value="0">
          <div class="claims-form-section mb-3">
            <div class="claims-form-section-title">Claim Details</div>
            <div class="row g-3 claims-modal-grid">
              <div class="col-md-6">
                <label for="claimDivision" class="form-label">Division</label>
                <select class="form-select" id="claimDivision" name="division">
                  <option value="">All divisions</option>
                  <?php foreach ($divisionOptions as $divisionName): ?>
                    <option value="<?php echo claims_safe_text($divisionName); ?>"><?php echo claims_safe_text($divisionName); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="claimUserId" class="form-label">Employee</label>
                <select class="form-select" id="claimUserId" name="user_id" required>
                  <option value="">Select employee</option>
                  <?php foreach ($employees as $empId => $empData): ?>
                    <?php $empName = trim((string)($empData['first_name'] ?? '') . ' ' . (string)($empData['last_name'] ?? '')); ?>
                    <option value="<?php echo (int)$empId; ?>" data-division="<?php echo claims_safe_text((string)($empData['division'] ?? '')); ?>"><?php echo claims_safe_text($empName !== '' ? $empName : 'Unknown Employee'); ?><?php echo trim((string)($empData['division'] ?? '')) !== '' ? ' - ' . claims_safe_text((string)$empData['division']) : ''; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="claimRef" class="form-label">Voucher</label>
                <input type="text" class="form-control" id="claimRef" name="claim_ref" required placeholder="Enter voucher">
              </div>
              <div class="col-md-6">
                <label for="claimRemarks" class="form-label">Remarks</label>
                <input type="text" class="form-control" id="claimRemarks" name="remarks" placeholder="Optional remarks">
              </div>
            </div>
          </div>

          <div class="claims-form-section">
            <div class="claims-form-section-title">Status Timeline Dates</div>
            <div class="row g-3 claims-modal-grid">
              <div class="col-12">
                <label for="receivedEvalDate" class="form-label">Receive for Evaluation/Checking</label>
                <input type="date" class="form-control" id="receivedEvalDate" name="received_eval_date">
              </div>
              <div class="col-md-6">
                <label for="pdApprovalDate" class="form-label">For PD's Approval</label>
                <input type="date" class="form-control" id="pdApprovalDate" name="pd_approval_date">
              </div>
              <div class="col-md-6">
                <label for="processingDate" class="form-label">Approved and for Processing</label>
                <input type="date" class="form-control" id="processingDate" name="processing_date">
              </div>
              <div class="col-12">
                <label for="chequeDate" class="form-label">Deposited/Credited/Issued Cheque</label>
                <input type="date" class="form-control" id="chequeDate" name="cheque_date">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer claims-modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="save_claim" value="1" class="btn btn-primary">Save Claim</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/script.js?v=20260407k2"></script>
<script>
  function escapeHtml(value) {
    return String(value || '').replace(/[&<>'"]/g, function(ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[ch];
    });
  }

  function openClaimModal(claim) {
    const modalTitle = document.getElementById('claimModalLabel');
    const form = document.getElementById('claimForm');
    const claimId = document.getElementById('claimId');
    const claimDivision = document.getElementById('claimDivision');
    const claimUserId = document.getElementById('claimUserId');
    const claimRef = document.getElementById('claimRef');
    const claimRemarks = document.getElementById('claimRemarks');
    const receivedEvalDate = document.getElementById('receivedEvalDate');
    const pdApprovalDate = document.getElementById('pdApprovalDate');
    const processingDate = document.getElementById('processingDate');
    const chequeDate = document.getElementById('chequeDate');

    const data = claim || {};
    if (modalTitle) modalTitle.textContent = data.id ? 'Edit Claim Record' : 'Add Claim Record';
    if (claimId) claimId.value = data.id || 0;
    if (claimDivision) claimDivision.value = data.division || '';
    filterClaimEmployees();
    if (claimUserId) claimUserId.value = data.user_id || '';
    if (claimRef) claimRef.value = data.claim_ref || '';
    if (claimRemarks) claimRemarks.value = data.remarks || '';
    if (receivedEvalDate) receivedEvalDate.value = data.received_eval_date || '';
    if (pdApprovalDate) pdApprovalDate.value = data.pd_approval_date || '';
    if (processingDate) processingDate.value = data.processing_date || '';
    if (chequeDate) chequeDate.value = data.cheque_date || '';
    setSelectedClaim(data);
  }

  let selectedClaimData = null;
  let selectedClaimRow = null;

  function setSelectedClaim(data, rowEl) {
    selectedClaimData = data || null;
    selectedClaimRow = rowEl || selectedClaimRow;
    const editBtn = document.getElementById('editSelectedClaimBtn');
    if (editBtn) {
      editBtn.disabled = !selectedClaimData;
    }
  }

  function selectClaimRow(rowEl) {
    const json = rowEl ? rowEl.getAttribute('data-claim-json') : '';
    if (!json) return;
    try {
      const data = JSON.parse(json);
      const body = document.getElementById('claimsTableBody');
      if (body) {
        body.querySelectorAll('.claim-row').forEach(function(row) {
          row.classList.remove('table-active');
        });
      }
      rowEl.classList.add('table-active');
      setSelectedClaim(data, rowEl);
    } catch (e) {
      // ignore parse errors
    }
  }

  function openSelectedClaim() {
    if (!selectedClaimData) return;
    openClaimModal(selectedClaimData);
    const modalEl = document.getElementById('claimModal');
    if (modalEl) {
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    }
  }

  function filterClaims() {
    const input = document.getElementById('claimsSearchInput');
    const body = document.getElementById('claimsTableBody');
    const empty = document.getElementById('claimsEmpty');
    const wrap = document.getElementById('claimsTableWrap');
    const divisionFilter = document.getElementById('claimsDivisionFilter');
    if (!input || !body || !wrap || !empty || !divisionFilter) return;

    const query = (input.value || '').trim().toLowerCase();
    const divisionQuery = (divisionFilter.value || '').trim().toLowerCase();
    const rows = body.querySelectorAll('tr');
    let visible = 0;
    rows.forEach(function(row) {
      const text = (row.getAttribute('data-claim-search') || row.textContent || '').toLowerCase();
      const rowDivision = (row.getAttribute('data-division') || '').toLowerCase();
      const matchesSearch = query === '' || text.indexOf(query) !== -1;
      const matchesDivision = divisionQuery === '' || rowDivision === divisionQuery;
      const show = matchesSearch && matchesDivision;
      row.classList.toggle('d-none', !show);
      if (show) visible += 1;
    });
    empty.classList.toggle('d-none', visible > 0);
    wrap.classList.toggle('d-none', visible === 0 && rows.length > 0);
  }

  function filterClaimEmployees() {
    const divisionSelect = document.getElementById('claimDivision');
    const employeeSelect = document.getElementById('claimUserId');
    if (!divisionSelect || !employeeSelect) return;

    const selectedDivision = (divisionSelect.value || '').trim().toLowerCase();
    const options = employeeSelect.querySelectorAll('option[data-division]');
    let selectedIsVisible = false;

    options.forEach(function(option) {
      const optionDivision = (option.getAttribute('data-division') || '').trim().toLowerCase();
      const isVisible = selectedDivision === '' || optionDivision === selectedDivision;
      option.hidden = !isVisible;
      option.disabled = !isVisible;
      if (employeeSelect.value === option.value && isVisible) {
        selectedIsVisible = true;
      }
    });

    if (!selectedIsVisible) {
      employeeSelect.value = '';
    }
  }

  const searchInput = document.getElementById('claimsSearchInput');
  const searchBtn = document.getElementById('claimsSearchBtn');
  const claimsDivisionFilter = document.getElementById('claimsDivisionFilter');
  const divisionSelect = document.getElementById('claimDivision');
  const employeeSelect = document.getElementById('claimUserId');
  if (searchInput) {
    searchInput.addEventListener('input', filterClaims);
    searchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        filterClaims();
      }
    });
  }
  if (searchBtn) searchBtn.addEventListener('click', filterClaims);
  if (claimsDivisionFilter) claimsDivisionFilter.addEventListener('change', filterClaims);

  const editSelectedClaimBtn = document.getElementById('editSelectedClaimBtn');
  if (editSelectedClaimBtn) {
    editSelectedClaimBtn.addEventListener('click', openSelectedClaim);
  }

  if (divisionSelect) {
    divisionSelect.addEventListener('change', function() {
      filterClaimEmployees();
    });
  }
  if (employeeSelect) {
    employeeSelect.addEventListener('change', function() {
      const currentOption = employeeSelect.selectedOptions[0];
      const detectedDivision = currentOption ? (currentOption.getAttribute('data-division') || '') : '';
      if (divisionSelect && detectedDivision && !divisionSelect.value) {
        divisionSelect.value = detectedDivision;
      }
    });
  }

  const claimRows = document.querySelectorAll('.claim-row');
  claimRows.forEach(function(row) {
    row.addEventListener('click', function(event) {
      if (event.target.closest('button')) return;
      selectClaimRow(row);
    });
    row.addEventListener('keydown', function(event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        selectClaimRow(row);
      }
    });
  });

  document.getElementById('claimModal').addEventListener('hidden.bs.modal', function() {
    openClaimModal();
  });
</script>
</body>
</html>











