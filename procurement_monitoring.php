<?php
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!function_exists('proc_date_to_db')) {
    function proc_date_to_db($value)
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt) return null;
        return $dt->format('Y-m-d') . ' 00:00:00';
    }
}

if (!function_exists('proc_date_display')) {
    function proc_date_display($value)
    {
        $value = trim((string)$value);
        if ($value === '') return '---';
        $ts = strtotime($value);
        return $ts ? date('M d, Y', $ts) : '---';
    }
}

if (!function_exists('proc_safe_text')) {
    function proc_safe_text($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('proc_avatar_url')) {
    function proc_avatar_url($avatar)
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

$user = $_SESSION['user'];
$isAdmin = user_has_division($user, 'Admin Division');
$now = new DateTime('now');
$statusFlash = $_SESSION['status_flash'] ?? null;
unset($_SESSION['status_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = hash_equals($_SESSION['csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''));
    if (!$tokenOk) {
        $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        header('Location: procurement_monitoring.php');
        exit;
    }

    if (!$isAdmin) {
        $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Only admin can modify procurement records.'];
        header('Location: procurement_monitoring.php');
        exit;
    }

    if (isset($_POST['delete_proc']) && (int)($_POST['delete_id'] ?? 0) > 0) {
        $deleteId = (int)$_POST['delete_id'];
        $deleteStmt = $mysqli->prepare('DELETE FROM procurement_monitoring WHERE id = ?');
        if ($deleteStmt) {
            $deleteStmt->bind_param('i', $deleteId);
            $ok = $deleteStmt->execute();
            $deleteStmt->close();
            $_SESSION['status_flash'] = $ok
                ? ['type' => 'success', 'message' => 'Procurement record deleted.']
                : ['type' => 'danger', 'message' => 'Failed to delete procurement record.'];
        }
        header('Location: procurement_monitoring.php');
        exit;
    }

    if (isset($_POST['save_proc'])) {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $toolId = trim((string)($_POST['tool_id'] ?? ''));

        $approvedPrDate = proc_date_to_db($_POST['approved_pr_date'] ?? '');
        $pdApprovalDate = proc_date_to_db($_POST['pd_approval_date'] ?? '');
        $retrievalQuotationDate = proc_date_to_db($_POST['retrieval_quotation_date'] ?? '');
        $abstractCanvasDate = proc_date_to_db($_POST['abstract_canvas_date'] ?? '');
        $preparationPoDate = proc_date_to_db($_POST['preparation_po_date'] ?? '');
        $issuancePoDate = proc_date_to_db($_POST['issuance_po_date'] ?? '');
        $remarks = trim((string)($_POST['remarks'] ?? ''));

        if ($userId <= 0 || $toolId === '') {
            $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Employee and Tool ID are required.'];
            header('Location: procurement_monitoring.php');
            exit;
        }

        $employeeStmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        if ($employeeStmt) {
            $employeeStmt->bind_param('i', $userId);
            $employeeStmt->execute();
            $employeeRes = $employeeStmt->get_result();
            $exists = $employeeRes && $employeeRes->num_rows > 0;
            $employeeStmt->close();
            if (!$exists) {
                $_SESSION['status_flash'] = ['type' => 'danger', 'message' => 'Selected employee was not found.'];
                header('Location: procurement_monitoring.php');
                exit;
            }
        }

        if ($recordId > 0) {
            $stmt = $mysqli->prepare("UPDATE procurement_monitoring SET user_id = ?, tool_id = ?, approved_pr_date = ?, pd_approval_date = ?, retrieval_quotation_date = ?, abstract_canvas_date = ?, preparation_po_date = ?, issuance_po_date = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('issssssssi', $userId, $toolId, $approvedPrDate, $pdApprovalDate, $retrievalQuotationDate, $abstractCanvasDate, $preparationPoDate, $issuancePoDate, $remarks, $recordId);
                $ok = $stmt->execute();
                $stmt->close();
                $_SESSION['status_flash'] = $ok
                    ? ['type' => 'success', 'message' => 'Procurement record updated.']
                    : ['type' => 'danger', 'message' => 'Failed to update procurement record.'];
            }
        } else {
            $stmt = $mysqli->prepare("INSERT INTO procurement_monitoring (user_id, tool_id, approved_pr_date, pd_approval_date, retrieval_quotation_date, abstract_canvas_date, preparation_po_date, issuance_po_date, remarks, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if ($stmt) {
                $stmt->bind_param('issssssss', $userId, $toolId, $approvedPrDate, $pdApprovalDate, $retrievalQuotationDate, $abstractCanvasDate, $preparationPoDate, $issuancePoDate, $remarks);
                $ok = $stmt->execute();
                $stmt->close();
                $_SESSION['status_flash'] = $ok
                    ? ['type' => 'success', 'message' => 'Procurement record added.']
                    : ['type' => 'danger', 'message' => 'Failed to add procurement record.'];
            }
        }

        header('Location: procurement_monitoring.php');
        exit;
    }
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
    if ($divisionValue !== '') $divisionOptions[$divisionValue] = true;
}
$divisionOptions = array_keys($divisionOptions);
sort($divisionOptions, SORT_NATURAL | SORT_FLAG_CASE);

$records = [];
$recordsSql = "SELECT pm.id, pm.user_id, pm.tool_id, pm.approved_pr_date, pm.pd_approval_date, pm.retrieval_quotation_date, pm.abstract_canvas_date, pm.preparation_po_date, pm.issuance_po_date, pm.remarks, pm.updated_at, u.first_name, u.last_name, u.division, u.avatar FROM procurement_monitoring pm JOIN users u ON u.id = pm.user_id";
$recordParams = [];
$recordTypes = '';
if (!$isAdmin) {
    $userDivision = trim((string)($user['division'] ?? ''));
    if ($userDivision !== '') {
        $recordsSql .= ' WHERE u.division = ?';
        $recordParams[] = $userDivision;
        $recordTypes .= 's';
    }
}
$recordsSql .= ' ORDER BY COALESCE(pm.issuance_po_date, pm.preparation_po_date, pm.abstract_canvas_date, pm.retrieval_quotation_date, pm.pd_approval_date, pm.approved_pr_date, pm.updated_at) DESC, pm.id DESC';
$recordsStmt = $mysqli->prepare($recordsSql);
if ($recordsStmt) {
    if ($recordParams) {
        $bind = array_merge([$recordTypes], $recordParams);
        $tmp = [];
        foreach ($bind as $k => $v) $tmp[$k] = &$bind[$k];
        call_user_func_array([$recordsStmt, 'bind_param'], $tmp);
    }
    $recordsStmt->execute();
    $recordsRes = $recordsStmt->get_result();
    if ($recordsRes) {
        while ($row = $recordsRes->fetch_assoc()) {
            $row['employee_name'] = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            if ($row['employee_name'] === '') $row['employee_name'] = 'Unknown Employee';
            $row['employee_avatar'] = proc_avatar_url($row['avatar'] ?? '');
            $records[] = $row;
        }
    }
    $recordsStmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Procurement Monitoring Tool</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .proc-shell { max-width: none; width: 100%; margin: 0; padding: 0 0.4rem; }
    .proc-shell .section-card { padding: 1rem; }
    .proc-shell .section-head { margin-bottom: 0.6rem; }
    .proc-table-wrap {
      border: 1px solid var(--surface-contrast);
      border-radius: 14px;
      overflow: hidden;
      background: var(--card-bg);
      backdrop-filter: blur(8px);
      box-shadow: var(--shadow-1);
    }
    .proc-table { margin-bottom: 0; min-width: 1280px; }
    .proc-table th, .proc-table td { padding: 0.55rem 0.45rem; }
    .proc-table thead th {
      background: rgba(148, 163, 184, 0.08);
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--text-dim);
      border-bottom: 1px solid var(--surface-contrast);
      white-space: nowrap;
      vertical-align: middle;
    }
    .proc-table tbody td { vertical-align: top; border-color: var(--surface-contrast); color: var(--text); }
    .proc-employee { min-width: 220px; }
    .proc-avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(100, 116, 139, 0.2);
      background: var(--surface);
    }
    .proc-fallback {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--primary);
      color: #fff;
      font-weight: 700;
    }
    .proc-stage-cell { min-width: 165px; }
    .proc-stage {
      display: inline-flex;
      flex-direction: column;
      gap: 0.12rem;
    }
    .proc-stage-label {
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: var(--muted);
    }
    .proc-stage-value {
      font-weight: 700;
      color: var(--text);
      font-size: 1.02rem;
    }
    .proc-table tbody tr.proc-stage-pr > td { background-color: rgba(251, 191, 36, 0.10) !important; }
    .proc-table tbody tr.proc-stage-pd > td { background-color: rgba(59, 130, 246, 0.10) !important; }
    .proc-table tbody tr.proc-stage-canvas > td { background-color: rgba(99, 102, 241, 0.10) !important; }
    .proc-table tbody tr.proc-stage-complete > td { background-color: rgba(16, 185, 129, 0.10) !important; }
    .dark-mode .proc-table thead th { background: rgba(255,255,255,0.02); color: var(--text-dim); }
    .dark-mode .proc-table tbody tr.proc-stage-pr > td { background-color: rgba(251,191,36,0.08) !important; }
    .dark-mode .proc-table tbody tr.proc-stage-pd > td { background-color: rgba(59,130,246,0.08) !important; }
    .dark-mode .proc-table tbody tr.proc-stage-canvas > td { background-color: rgba(99,102,241,0.08) !important; }
    .dark-mode .proc-table tbody tr.proc-stage-complete > td { background-color: rgba(16,185,129,0.08) !important; }
  </style>
  <style>
    /* Ensure footer stays at bottom on short pages */
    .dti-page-flex { display: flex; flex-direction: column; min-height: 100vh; }
    .dti-page-flex .proc-shell { flex: 1 0 auto; }
  </style>
</head>
<body class="p-4 dti-page-flex">
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container-fluid mt-4 proc-shell">
  <div class="section-card section-card--claims">
    <div class="section-head d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="d-flex align-items-center">
        <span class="section-icon"><i class="bi bi-box-seam"></i></span>
        <div>
          <h1 class="h3 mb-1">Procurement Monitoring Tool</h1>
        </div>
      </div>
      <?php if ($isAdmin): ?>
      <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#procModal" onclick="openProcModal()"><i class="bi bi-plus-circle"></i> Add Procurement</button>
        <button type="button" class="btn btn-outline-primary" id="editSelectedProcBtn" disabled><i class="bi bi-pencil-square"></i> Edit Selected</button>
        <button type="button" class="btn btn-outline-danger" id="deleteSelectedProcBtn" disabled><i class="bi bi-trash"></i> Delete Selected</button>
      </div>
      <?php endif; ?>
    </div>

    <div class="alert alert-light border mt-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div><strong>Updated:</strong> <span><?php echo proc_safe_text($now->format('M d, Y h:i A')); ?></span></div>
      <div class="small text-muted"><i class="bi bi-collection"></i> <?php echo count($records); ?> procurement record(s)</div>
    </div>

    <div class="mb-3 d-flex gap-2 align-items-center flex-wrap">
      <div class="input-group" style="max-width: 460px;">
        <input id="procSearchInput" type="search" class="form-control" placeholder="Search employee, division, tool ID..." aria-label="Search procurement">
        <button id="procSearchBtn" class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i> Search</button>
      </div>
      <div style="min-width: 220px; max-width: 280px;">
        <select id="procDivisionFilter" class="form-select">
          <option value="">All divisions</option>
          <?php foreach ($divisionOptions as $divisionName): ?>
            <option value="<?php echo proc_safe_text(strtolower($divisionName)); ?>"><?php echo proc_safe_text($divisionName); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if (is_array($statusFlash) && !empty($statusFlash['message'])): ?>
      <div class="alert alert-<?php echo (($statusFlash['type'] ?? '') === 'danger') ? 'danger' : 'success'; ?> mb-3">
        <?php echo proc_safe_text((string)$statusFlash['message']); ?>
      </div>
    <?php endif; ?>

    <div id="procEmpty" class="alert alert-secondary<?php echo empty($records) ? '' : ' d-none'; ?>">No procurement records found.</div>
    <div id="procTableWrap" class="proc-table-wrap table-responsive<?php echo empty($records) ? ' d-none' : ''; ?>">
      <table class="table proc-table align-middle">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Tool ID</th>
            <th>Approved PR Date</th>
            <th>PD Approval Date</th>
            <th>Retrieval of Quotation Date</th>
            <th>Abstract of Canvas Date</th>
            <th>Preparation of PO Date</th>
            <th>Issuance of PO to Winning Bidder Date</th>
            <th>Remarks</th>
            <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody id="procTableBody">
          <?php foreach ($records as $record): ?>
            <?php
              $approvedPr = proc_date_display($record['approved_pr_date'] ?? '');
              $pdApproval = proc_date_display($record['pd_approval_date'] ?? '');
              $retrieval = proc_date_display($record['retrieval_quotation_date'] ?? '');
              $abstractCanvas = proc_date_display($record['abstract_canvas_date'] ?? '');
              $preparationPo = proc_date_display($record['preparation_po_date'] ?? '');
              $issuancePo = proc_date_display($record['issuance_po_date'] ?? '');
              $remarks = trim((string)($record['remarks'] ?? ''));
              $rowClass = '';
              if (trim((string)($record['issuance_po_date'] ?? '')) !== '') $rowClass = 'proc-stage-complete';
              elseif (trim((string)($record['retrieval_quotation_date'] ?? '')) !== '' || trim((string)($record['abstract_canvas_date'] ?? '')) !== '' || trim((string)($record['preparation_po_date'] ?? '')) !== '') $rowClass = 'proc-stage-canvas';
              elseif (trim((string)($record['pd_approval_date'] ?? '')) !== '') $rowClass = 'proc-stage-pd';
              elseif (trim((string)($record['approved_pr_date'] ?? '')) !== '') $rowClass = 'proc-stage-pr';
            ?>
            <tr class="proc-row <?php echo $rowClass; ?>" role="button" tabindex="0"
                data-division="<?php echo proc_safe_text(strtolower((string)($record['division'] ?? ''))); ?>"
                data-proc-search="<?php echo proc_safe_text(strtolower(trim(($record['employee_name'] ?? '') . ' ' . ($record['division'] ?? '') . ' ' . ($record['tool_id'] ?? '') . ' ' . $approvedPr . ' ' . $pdApproval . ' ' . $retrieval . ' ' . $abstractCanvas . ' ' . $preparationPo . ' ' . $issuancePo . ' ' . $remarks))); ?>"
                data-proc-json='<?php echo json_encode([
                    "id" => (int)$record['id'],
                    "user_id" => (int)$record['user_id'],
                    "division" => (string)($record['division'] ?? ''),
                    "tool_id" => (string)($record['tool_id'] ?? ''),
                    "approved_pr_date" => trim((string)($record['approved_pr_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['approved_pr_date'])) : '',
                    "pd_approval_date" => trim((string)($record['pd_approval_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['pd_approval_date'])) : '',
                    "retrieval_quotation_date" => trim((string)($record['retrieval_quotation_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['retrieval_quotation_date'])) : '',
                    "abstract_canvas_date" => trim((string)($record['abstract_canvas_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['abstract_canvas_date'])) : '',
                    "preparation_po_date" => trim((string)($record['preparation_po_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['preparation_po_date'])) : '',
                    "issuance_po_date" => trim((string)($record['issuance_po_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['issuance_po_date'])) : '',
                    "remarks" => (string)($record['remarks'] ?? ''),
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
              <td class="proc-employee">
                <div class="d-flex align-items-center gap-2">
                  <?php if ((string)($record['employee_avatar'] ?? '') !== ''): ?>
                    <img src="<?php echo proc_safe_text((string)$record['employee_avatar']); ?>" alt="Profile" class="proc-avatar">
                  <?php else: ?>
                    <span class="proc-fallback"><?php echo proc_safe_text(strtoupper(substr((string)$record['employee_name'], 0, 1))); ?></span>
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?php echo proc_safe_text((string)$record['employee_name']); ?></div>
                    <div class="small text-muted"><?php echo proc_safe_text((string)($record['division'] ?? '')); ?></div>
                  </div>
                </div>
              </td>
              <td><div class="fw-semibold"><?php echo proc_safe_text((string)($record['tool_id'] ?? '')); ?></div></td>
              <td class="proc-stage-cell"><div class="proc-stage"><span class="proc-stage-label">Approved PR</span><span class="proc-stage-value"><?php echo proc_safe_text($approvedPr); ?></span></div></td>
              <td class="proc-stage-cell"><div class="proc-stage"><span class="proc-stage-label">PD Approval</span><span class="proc-stage-value"><?php echo proc_safe_text($pdApproval); ?></span></div></td>
              <td class="proc-stage-cell"><div class="proc-stage"><span class="proc-stage-label">Retrieval</span><span class="proc-stage-value"><?php echo proc_safe_text($retrieval); ?></span></div></td>
              <td class="proc-stage-cell"><div class="proc-stage"><span class="proc-stage-label">Abstract</span><span class="proc-stage-value"><?php echo proc_safe_text($abstractCanvas); ?></span></div></td>
              <td class="proc-stage-cell"><div class="proc-stage"><span class="proc-stage-label">Preparation PO</span><span class="proc-stage-value"><?php echo proc_safe_text($preparationPo); ?></span></div></td>
              <td class="proc-stage-cell"><div class="proc-stage"><span class="proc-stage-label">Issuance PO</span><span class="proc-stage-value"><?php echo proc_safe_text($issuancePo); ?></span></div></td>
              <td><?php echo proc_safe_text($remarks !== '' ? $remarks : '---'); ?></td>
              <?php if ($isAdmin): ?>
              <td>
                <div class="d-flex gap-1">
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#procModal"
                    onclick='openProcModal(<?php echo json_encode([
                        "id" => (int)$record['id'],
                        "user_id" => (int)$record['user_id'],
                        "division" => (string)($record['division'] ?? ''),
                        "tool_id" => (string)($record['tool_id'] ?? ''),
                        "approved_pr_date" => trim((string)($record['approved_pr_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['approved_pr_date'])) : '',
                        "pd_approval_date" => trim((string)($record['pd_approval_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['pd_approval_date'])) : '',
                        "retrieval_quotation_date" => trim((string)($record['retrieval_quotation_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['retrieval_quotation_date'])) : '',
                        "abstract_canvas_date" => trim((string)($record['abstract_canvas_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['abstract_canvas_date'])) : '',
                        "preparation_po_date" => trim((string)($record['preparation_po_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['preparation_po_date'])) : '',
                        "issuance_po_date" => trim((string)($record['issuance_po_date'] ?? '')) !== '' ? date('Y-m-d', strtotime((string)$record['issuance_po_date'])) : '',
                        "remarks" => (string)($record['remarks'] ?? ''),
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="post" action="procurement_monitoring.php" onsubmit="return confirm('Delete this procurement record?');" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo proc_safe_text($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="delete_id" value="<?php echo (int)$record['id']; ?>">
                    <button type="submit" name="delete_proc" value="1" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
<div class="modal fade" id="procModal" tabindex="-1" aria-labelledby="procModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="procModalLabel">Add Procurement Record</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="procurement_monitoring.php" id="procForm">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo proc_safe_text($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="record_id" id="procRecordId" value="0">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="procDivision" class="form-label">Division</label>
              <select class="form-select" id="procDivision" name="division">
                <option value="">All divisions</option>
                <?php foreach ($divisionOptions as $divisionName): ?>
                  <option value="<?php echo proc_safe_text($divisionName); ?>"><?php echo proc_safe_text($divisionName); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="procUserId" class="form-label">Employee</label>
              <select class="form-select" id="procUserId" name="user_id" required>
                <option value="">Select employee</option>
                <?php foreach ($employees as $empId => $empData): ?>
                  <?php $empName = trim((string)($empData['first_name'] ?? '') . ' ' . (string)($empData['last_name'] ?? '')); ?>
                  <option value="<?php echo (int)$empId; ?>" data-division="<?php echo proc_safe_text((string)($empData['division'] ?? '')); ?>"><?php echo proc_safe_text($empName !== '' ? $empName : 'Unknown Employee'); ?><?php echo trim((string)($empData['division'] ?? '')) !== '' ? ' - ' . proc_safe_text((string)$empData['division']) : ''; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="procToolId" class="form-label">Tool ID</label>
              <input type="text" class="form-control" id="procToolId" name="tool_id" required placeholder="Enter tool ID">
            </div>
            <div class="col-md-6">
              <label for="procRemarks" class="form-label">Remarks</label>
              <input type="text" class="form-control" id="procRemarks" name="remarks" placeholder="Optional remarks">
            </div>
            <div class="col-md-6">
              <label for="approvedPrDate" class="form-label">Approved PR Date</label>
              <input type="date" class="form-control" id="approvedPrDate" name="approved_pr_date">
            </div>
            <div class="col-md-6">
              <label for="pdApprovalDate" class="form-label">Approval by the PD Date</label>
              <input type="date" class="form-control" id="pdApprovalDate" name="pd_approval_date">
            </div>
            <div class="col-md-6">
              <label for="retrievalQuotationDate" class="form-label">Retrieval of Quotation Date</label>
              <input type="date" class="form-control" id="retrievalQuotationDate" name="retrieval_quotation_date">
            </div>
            <div class="col-md-6">
              <label for="abstractCanvasDate" class="form-label">Abstract of Canvas Date</label>
              <input type="date" class="form-control" id="abstractCanvasDate" name="abstract_canvas_date">
            </div>
            <div class="col-md-6">
              <label for="preparationPoDate" class="form-label">Preparation of Purchase Order Date</label>
              <input type="date" class="form-control" id="preparationPoDate" name="preparation_po_date">
            </div>
            <div class="col-md-6">
              <label for="issuancePoDate" class="form-label">Issuance of Purchase Order to Winning Bidder Date</label>
              <input type="date" class="form-control" id="issuancePoDate" name="issuance_po_date">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="save_proc" value="1" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<form id="deleteSelectedProcForm" method="post" action="procurement_monitoring.php" class="d-none">
  <input type="hidden" name="csrf_token" value="<?php echo proc_safe_text($_SESSION['csrf_token'] ?? ''); ?>">
  <input type="hidden" name="delete_id" id="deleteSelectedProcId" value="0">
  <input type="hidden" name="delete_proc" value="1">
</form>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/script.js?v=20260407k2"></script>
<script>
  function openProcModal(record) {
    const data = record || {};
    const title = document.getElementById('procModalLabel');
    const recordId = document.getElementById('procRecordId');
    const division = document.getElementById('procDivision');
    const userId = document.getElementById('procUserId');
    const toolId = document.getElementById('procToolId');
    const remarks = document.getElementById('procRemarks');
    const approvedPrDate = document.getElementById('approvedPrDate');
    const pdApprovalDate = document.getElementById('pdApprovalDate');
    const retrievalQuotationDate = document.getElementById('retrievalQuotationDate');
    const abstractCanvasDate = document.getElementById('abstractCanvasDate');
    const preparationPoDate = document.getElementById('preparationPoDate');
    const issuancePoDate = document.getElementById('issuancePoDate');

    if (title) title.textContent = data.id ? 'Edit Procurement Record' : 'Add Procurement Record';
    if (recordId) recordId.value = data.id || 0;
    if (division) division.value = data.division || '';
    filterProcEmployees();
    if (userId) userId.value = data.user_id || '';
    if (toolId) toolId.value = data.tool_id || '';
    if (remarks) remarks.value = data.remarks || '';
    if (approvedPrDate) approvedPrDate.value = data.approved_pr_date || '';
    if (pdApprovalDate) pdApprovalDate.value = data.pd_approval_date || '';
    if (retrievalQuotationDate) retrievalQuotationDate.value = data.retrieval_quotation_date || '';
    if (abstractCanvasDate) abstractCanvasDate.value = data.abstract_canvas_date || '';
    if (preparationPoDate) preparationPoDate.value = data.preparation_po_date || '';
    if (issuancePoDate) issuancePoDate.value = data.issuance_po_date || '';
    setSelectedProc(data);
  }

  let selectedProcData = null;

  function setSelectedProc(data) {
    selectedProcData = data || null;
    const editBtn = document.getElementById('editSelectedProcBtn');
    const deleteBtn = document.getElementById('deleteSelectedProcBtn');
    if (editBtn) editBtn.disabled = !selectedProcData;
    if (deleteBtn) deleteBtn.disabled = !selectedProcData;
  }

  function selectProcRow(rowEl) {
    const json = rowEl ? rowEl.getAttribute('data-proc-json') : '';
    if (!json) return;
    try {
      const data = JSON.parse(json);
      const body = document.getElementById('procTableBody');
      if (body) {
        body.querySelectorAll('.proc-row').forEach(function(row) {
          row.classList.remove('table-active');
        });
      }
      rowEl.classList.add('table-active');
      setSelectedProc(data);
    } catch (e) {
      // ignore
    }
  }

  function filterProcRecords() {
    const input = document.getElementById('procSearchInput');
    const divisionFilter = document.getElementById('procDivisionFilter');
    const body = document.getElementById('procTableBody');
    const empty = document.getElementById('procEmpty');
    const wrap = document.getElementById('procTableWrap');
    if (!input || !divisionFilter || !body || !empty || !wrap) return;

    const query = (input.value || '').trim().toLowerCase();
    const division = (divisionFilter.value || '').trim().toLowerCase();
    const rows = body.querySelectorAll('tr');
    let visible = 0;

    rows.forEach(function(row) {
      const text = (row.getAttribute('data-proc-search') || row.textContent || '').toLowerCase();
      const rowDivision = (row.getAttribute('data-division') || '').toLowerCase();
      const matchesSearch = query === '' || text.indexOf(query) !== -1;
      const matchesDivision = division === '' || rowDivision === division;
      const show = matchesSearch && matchesDivision;
      row.classList.toggle('d-none', !show);
      if (show) visible += 1;
    });

    empty.classList.toggle('d-none', visible > 0);
    wrap.classList.toggle('d-none', visible === 0 && rows.length > 0);
  }

  function filterProcEmployees() {
    const divisionSelect = document.getElementById('procDivision');
    const employeeSelect = document.getElementById('procUserId');
    if (!divisionSelect || !employeeSelect) return;

    const selectedDivision = (divisionSelect.value || '').trim().toLowerCase();
    const options = employeeSelect.querySelectorAll('option[data-division]');
    let selectedVisible = false;

    options.forEach(function(option) {
      const optionDivision = (option.getAttribute('data-division') || '').trim().toLowerCase();
      const visible = selectedDivision === '' || optionDivision === selectedDivision;
      option.hidden = !visible;
      option.disabled = !visible;
      if (employeeSelect.value === option.value && visible) selectedVisible = true;
    });

    if (!selectedVisible) employeeSelect.value = '';
  }

  const searchInput = document.getElementById('procSearchInput');
  const searchBtn = document.getElementById('procSearchBtn');
  const divisionFilter = document.getElementById('procDivisionFilter');
  if (searchInput) {
    searchInput.addEventListener('input', filterProcRecords);
    searchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        filterProcRecords();
      }
    });
  }
  if (searchBtn) searchBtn.addEventListener('click', filterProcRecords);
  if (divisionFilter) divisionFilter.addEventListener('change', filterProcRecords);

  const rowEls = document.querySelectorAll('.proc-row');
  rowEls.forEach(function(row) {
    row.addEventListener('click', function(e) {
      if (e.target.closest('button') || e.target.closest('form')) return;
      selectProcRow(row);
    });
    row.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        selectProcRow(row);
      }
    });
  });

  const editSelectedBtn = document.getElementById('editSelectedProcBtn');
  if (editSelectedBtn) {
    editSelectedBtn.addEventListener('click', function() {
      if (!selectedProcData) return;
      openProcModal(selectedProcData);
      const modalEl = document.getElementById('procModal');
      if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      }
    });
  }

  const deleteSelectedBtn = document.getElementById('deleteSelectedProcBtn');
  if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener('click', function() {
      if (!selectedProcData || !selectedProcData.id) return;
      if (!confirm('Delete selected procurement record?')) return;
      const form = document.getElementById('deleteSelectedProcForm');
      const deleteId = document.getElementById('deleteSelectedProcId');
      if (form && deleteId) {
        deleteId.value = selectedProcData.id;
        form.submit();
      }
    });
  }

  const modalDivision = document.getElementById('procDivision');
  const modalUser = document.getElementById('procUserId');
  if (modalDivision) {
    modalDivision.addEventListener('change', filterProcEmployees);
  }
  if (modalUser) {
    modalUser.addEventListener('change', function() {
      const opt = modalUser.selectedOptions[0];
      const detected = opt ? (opt.getAttribute('data-division') || '') : '';
      if (modalDivision && detected && !modalDivision.value) {
        modalDivision.value = detected;
      }
    });
  }

  const procModal = document.getElementById('procModal');
  if (procModal) {
    procModal.addEventListener('hidden.bs.modal', function() {
      openProcModal();
    });
  }
</script>
</body>
</html>











