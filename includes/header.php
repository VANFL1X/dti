<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$user = $_SESSION['user'] ?? null;
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$headerNotifications = [];
$headerUnreadCount = 0;
$headerClaimsRecords = [];
$headerProcurementRecords = [];
$headerIsAdmin = $user ? user_has_division($user, 'Admin Division') : false;

if (!function_exists('header_claim_avatar_url')) {
  function header_claim_avatar_url($avatar)
  {
    $avatar = trim((string)$avatar);
    if ($avatar === '') return '';
    $uploadPath = dirname(__DIR__) . '/uploads/' . $avatar;
    $legacyPath = dirname(__DIR__) . '/data/avatars/' . $avatar;
    if (is_file($uploadPath)) return 'uploads/' . $avatar;
    if (is_file($legacyPath)) return 'data/avatars/' . $avatar;
    return '';
  }
}

if ($user && isset($mysqli) && $mysqli instanceof mysqli) {
  $uid = (int)($user['id'] ?? 0);
  if ($uid > 0) {
    $headerNotifStmt = $mysqli->prepare("SELECT id, type, title, body, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
    if ($headerNotifStmt) {
      $headerNotifStmt->bind_param('i', $uid);
      $headerNotifStmt->execute();
      $headerNotifRes = $headerNotifStmt->get_result();
      if ($headerNotifRes) {
        while ($n = $headerNotifRes->fetch_assoc()) {
          $headerNotifications[] = $n;
          if ((int)($n['is_read'] ?? 0) === 0) {
            $headerUnreadCount++;
          }
        }
      }
      $headerNotifStmt->close();
    }

    $claimsSql = "SELECT cm.id, cm.user_id, cm.claim_ref, cm.received_eval_date, cm.pd_approval_date, cm.processing_date, cm.cheque_date, cm.remarks, cm.updated_at, u.first_name, u.last_name, u.division, u.avatar FROM claims_monitoring cm JOIN users u ON u.id = cm.user_id";
    $claimsParams = [];
    $claimsTypes = '';
    if (!$headerIsAdmin) {
      $userDivision = trim((string)($user['division'] ?? ''));
      if ($userDivision !== '') {
        $claimsSql .= ' WHERE u.division = ?';
        $claimsParams[] = $userDivision;
        $claimsTypes .= 's';
      }
    }
    $claimsSql .= ' ORDER BY COALESCE(cm.cheque_date, cm.processing_date, cm.pd_approval_date, cm.received_eval_date, cm.updated_at) DESC, cm.id DESC LIMIT 25';
    $claimsStmt = $mysqli->prepare($claimsSql);
    if ($claimsStmt) {
      if ($claimsParams) {
        $bind = array_merge([$claimsTypes], $claimsParams);
        $tmp = [];
        foreach ($bind as $k => $v) $tmp[$k] = &$bind[$k];
        call_user_func_array([$claimsStmt, 'bind_param'], $tmp);
      }
      $claimsStmt->execute();
      $claimsRes = $claimsStmt->get_result();
      if ($claimsRes) {
        while ($row = $claimsRes->fetch_assoc()) {
          $row['avatar_url'] = header_claim_avatar_url($row['avatar'] ?? '');
          $headerClaimsRecords[] = $row;
        }
      }
      $claimsStmt->close();
    }

    $procSql = "SELECT pm.id, pm.user_id, pm.tool_id, pm.approved_pr_date, pm.pd_approval_date, pm.retrieval_quotation_date, pm.abstract_canvas_date, pm.preparation_po_date, pm.issuance_po_date, pm.remarks, pm.updated_at, u.first_name, u.last_name, u.division, u.avatar FROM procurement_monitoring pm JOIN users u ON u.id = pm.user_id";
    $procParams = [];
    $procTypes = '';
    if (!$headerIsAdmin) {
      $userDivision = trim((string)($user['division'] ?? ''));
      if ($userDivision !== '') {
        $procSql .= ' WHERE u.division = ?';
        $procParams[] = $userDivision;
        $procTypes .= 's';
      }
    }
    $procSql .= ' ORDER BY COALESCE(pm.issuance_po_date, pm.preparation_po_date, pm.abstract_canvas_date, pm.retrieval_quotation_date, pm.pd_approval_date, pm.approved_pr_date, pm.updated_at) DESC, pm.id DESC LIMIT 25';
    $procStmt = $mysqli->prepare($procSql);
    if ($procStmt) {
      if ($procParams) {
        $bind = array_merge([$procTypes], $procParams);
        $tmp = [];
        foreach ($bind as $k => $v) $tmp[$k] = &$bind[$k];
        call_user_func_array([$procStmt, 'bind_param'], $tmp);
      }
      $procStmt->execute();
      $procRes = $procStmt->get_result();
      if ($procRes) {
        while ($row = $procRes->fetch_assoc()) {
          $row['avatar_url'] = header_claim_avatar_url($row['avatar'] ?? '');
          $headerProcurementRecords[] = $row;
        }
      }
      $procStmt->close();
    }
  }
}
?>
<script>
  (function () {
    try {
      var saved = localStorage.getItem('dti_theme');
      var theme = saved || ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
      document.documentElement.classList.toggle('dark-mode', theme === 'dark');
    } catch (e) {
      // Ignore storage/access errors and keep default theme.
    }
  })();
</script>
<header class="dti-header border-bottom">
  <div class="container d-flex align-items-center justify-content-between py-2">
    <a class="d-flex align-items-center text-decoration-none" href="index.php">
      <img src="assets/logoDTI.png" alt="DTI" class="dti-logo" width="48" height="48">
      <div class="ms-2">
        <div class="text-muted small">R2 Nueva Vizcaya</div>
      </div>
    </a>
    <div class="d-flex align-items-center">
      <button id="themeToggle" class="btn btn-outline-secondary btn-sm me-2" aria-label="Toggle theme">🌙</button>
      <?php if ($user): ?>
        <button id="headerClaimsBtn" type="button" class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#globalClaimsModal" aria-label="Claims Monitoring Tool">Claims</button>
        <button id="headerProcBtn" type="button" class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#globalProcurementModal" aria-label="Procurement Monitoring Tool">Procurement</button>
        <button id="headerNotificationBtn" type="button" class="btn btn-outline-secondary btn-sm me-2 position-relative" data-bs-toggle="modal" data-bs-target="#globalNotificationModal" aria-label="Notifications">
          <i class="bi bi-bell"></i>
          <?php if ($headerUnreadCount > 0): ?>
            <span id="headerNotifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo (int)$headerUnreadCount; ?></span>
          <?php else: ?>
            <span id="headerNotifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
          <?php endif; ?>
        </button>
        <?php if ($currentPage === 'index.php'): ?>
          <button class="btn btn-outline-secondary btn-sm me-2 activity-glass-btn" data-bs-toggle="modal" data-bs-target="#loginActivityModal">Activity</button>
        <?php endif; ?>
        <span class="me-3 text-muted">Hello, <?php echo htmlspecialchars($user['first_name']); ?></span>
        <?php if ($currentPage !== 'dashboard.php'): ?>
          <a class="btn btn-outline-secondary btn-sm me-2" href="dashboard.php">Dashboard</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
      <?php else: ?>
        <?php if ($currentPage === 'index.php'): ?>
          <button class="btn btn-outline-secondary btn-sm me-2 activity-glass-btn" data-bs-toggle="modal" data-bs-target="#loginActivityModal">Activity</button>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="modal fade" id="loginActivityModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable activity-modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h4 class="modal-title mb-0">Division Login Activity</h4>
          <div class="text-muted" id="activitySubtitle">Track login patterns across divisions</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3" style="max-width:220px;">
          <label for="activityPeriodSelect" class="form-label fw-semibold">Time Period:</label>
          <select id="activityPeriodSelect" class="form-select">
            <option value="7d" selected>Last 7 days</option>
            <option value="30d">Last 30 days</option>
            <option value="90d">Last 90 days</option>
          </select>
        </div>

        <div id="activityNoData" class="alert alert-secondary d-none">No login activity data found.</div>

        <div id="divisionChartSection" class="border rounded p-2 bg-light mb-3">
          <div class="activity-chart-wrap">
            <canvas id="divisionActivityChart"></canvas>
          </div>
        </div>

        <div id="accountDrilldownSection" class="d-none">
          <h6 id="accountDrilldownTitle" class="mb-2">User Login Counts</h6>
          <div class="border rounded p-2 bg-light">
            <div class="activity-chart-wrap">
              <canvas id="accountActivityChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($user): ?>
<div class="modal fade" id="globalProcurementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable global-procurement-modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Procurement Monitoring Tool</h5>
          <div class="text-muted">View procurement status and details entered by the admin.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($headerProcurementRecords)): ?>
          <div class="alert alert-secondary mb-0">No procurement records found.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Tool ID</th>
                  <th>Status</th>
                  <th>Approved PR Date</th>
                  <th>Approval by the PD Date</th>
                  <th>Retrieval of Quotation Date</th>
                  <th>Abstract of Canvas Date</th>
                  <th>Preparation of PO Date</th>
                  <th>Issuance of PO Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($headerProcurementRecords as $proc): ?>
                  <?php
                    $employeeName = trim((string)($proc['first_name'] ?? '') . ' ' . (string)($proc['last_name'] ?? ''));
                    if ($employeeName === '') $employeeName = 'Unknown Employee';
                    $approvedPr = trim((string)($proc['approved_pr_date'] ?? ''));
                    $pdApproval = trim((string)($proc['pd_approval_date'] ?? ''));
                    $retrieval = trim((string)($proc['retrieval_quotation_date'] ?? ''));
                    $abstractCanvas = trim((string)($proc['abstract_canvas_date'] ?? ''));
                    $preparationPo = trim((string)($proc['preparation_po_date'] ?? ''));
                    $issuancePo = trim((string)($proc['issuance_po_date'] ?? ''));
                    $statusLabel = 'Pending';
                    if ($issuancePo !== '') $statusLabel = 'Issuance of PO to Winning Bidder';
                    elseif ($preparationPo !== '') $statusLabel = 'Preparation of Purchase Order';
                    elseif ($abstractCanvas !== '') $statusLabel = 'Abstract of Canvas';
                    elseif ($retrieval !== '') $statusLabel = 'Retrieval of Quotation';
                    elseif ($pdApproval !== '') $statusLabel = "Approval by the PD";
                    elseif ($approvedPr !== '') $statusLabel = 'Approved PR';
                    $avatarUrl = (string)($proc['avatar_url'] ?? '');
                    $rowClass = '';
                    if ($issuancePo !== '') $rowClass = 'proc-modal-stage-complete';
                    elseif ($retrieval !== '' || $abstractCanvas !== '' || $preparationPo !== '') $rowClass = 'proc-modal-stage-canvas';
                    elseif ($pdApproval !== '') $rowClass = 'proc-modal-stage-pd';
                    elseif ($approvedPr !== '') $rowClass = 'proc-modal-stage-pr';
                  ?>
                  <tr class="<?php echo $rowClass; ?>">
                    <td>
                      <div class="proc-modal-employee d-flex align-items-center gap-2">
                        <?php if ($avatarUrl !== ''): ?>
                          <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Profile" class="proc-modal-avatar">
                        <?php else: ?>
                          <span class="proc-modal-fallback"><?php echo htmlspecialchars(strtoupper(substr($employeeName, 0, 1))); ?></span>
                        <?php endif; ?>
                        <div>
                          <div class="fw-semibold"><?php echo htmlspecialchars($employeeName); ?></div>
                          <div class="small text-muted"><?php echo htmlspecialchars((string)($proc['division'] ?? '')); ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?php echo htmlspecialchars((string)($proc['tool_id'] ?? '')); ?></td>
                    <td><span class="badge text-bg-primary claims-stage-badge"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                    <td><div class="proc-modal-stage"><span class="proc-modal-stage-label">Approved PR</span><span class="proc-modal-stage-value"><?php echo htmlspecialchars($approvedPr !== '' ? date('M d, Y', strtotime($approvedPr)) : '---'); ?></span></div></td>
                    <td><div class="proc-modal-stage"><span class="proc-modal-stage-label">PD Approval</span><span class="proc-modal-stage-value"><?php echo htmlspecialchars($pdApproval !== '' ? date('M d, Y', strtotime($pdApproval)) : '---'); ?></span></div></td>
                    <td><div class="proc-modal-stage"><span class="proc-modal-stage-label">Retrieval</span><span class="proc-modal-stage-value"><?php echo htmlspecialchars($retrieval !== '' ? date('M d, Y', strtotime($retrieval)) : '---'); ?></span></div></td>
                    <td><div class="proc-modal-stage"><span class="proc-modal-stage-label">Abstract</span><span class="proc-modal-stage-value"><?php echo htmlspecialchars($abstractCanvas !== '' ? date('M d, Y', strtotime($abstractCanvas)) : '---'); ?></span></div></td>
                    <td><div class="proc-modal-stage"><span class="proc-modal-stage-label">Preparation PO</span><span class="proc-modal-stage-value"><?php echo htmlspecialchars($preparationPo !== '' ? date('M d, Y', strtotime($preparationPo)) : '---'); ?></span></div></td>
                    <td><div class="proc-modal-stage"><span class="proc-modal-stage-label">Issuance PO</span><span class="proc-modal-stage-value"><?php echo htmlspecialchars($issuancePo !== '' ? date('M d, Y', strtotime($issuancePo)) : '---'); ?></span></div></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <?php if ($headerIsAdmin): ?>
          <a class="btn btn-primary" href="procurement_monitoring.php">Open Procurement Tool</a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="globalClaimsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable global-claims-modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Claims Monitoring Tool</h5>
          <div class="text-muted">View claim status and details entered by the admin.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($headerClaimsRecords)): ?>
          <div class="alert alert-secondary mb-0">No claims found.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Voucher</th>
                  <th>Status</th>
                  <th>Receive for Evaluation/Checking</th>
                  <th>For PD's Approval</th>
                  <th>Approved and for Processing</th>
                  <th>Deposited/Credited/Issued Cheque</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($headerClaimsRecords as $claim): ?>
                  <?php
                    $employeeName = trim((string)($claim['first_name'] ?? '') . ' ' . (string)($claim['last_name'] ?? ''));
                    if ($employeeName === '') $employeeName = 'Unknown Employee';
                    $received = trim((string)($claim['received_eval_date'] ?? ''));
                    $pdApproval = trim((string)($claim['pd_approval_date'] ?? ''));
                    $processing = trim((string)($claim['processing_date'] ?? ''));
                    $cheque = trim((string)($claim['cheque_date'] ?? ''));
                    $statusLabel = 'Pending';
                    if ($cheque !== '') $statusLabel = 'Deposited/Credited/Issued Cheque';
                    elseif ($processing !== '') $statusLabel = 'Approved and for Processing';
                    elseif ($pdApproval !== '') $statusLabel = "For PD's Approval";
                    elseif ($received !== '') $statusLabel = 'Receive for Evaluation/Checking';
                    $avatarUrl = (string)($claim['avatar_url'] ?? '');
                    $rowClass = '';
                    if ($cheque !== '') $rowClass = 'claims-modal-stage-complete';
                    elseif ($processing !== '') $rowClass = 'claims-modal-stage-processing';
                    elseif ($pdApproval !== '') $rowClass = 'claims-modal-stage-pd';
                    elseif ($received !== '') $rowClass = 'claims-modal-stage-received';
                  ?>
                  <tr class="<?php echo $rowClass; ?>">
                    <td>
                      <div class="claims-modal-employee d-flex align-items-center gap-2">
                        <?php if ($avatarUrl !== ''): ?>
                          <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Profile" class="claims-modal-avatar">
                        <?php else: ?>
                          <span class="claims-modal-fallback"><?php echo htmlspecialchars(strtoupper(substr($employeeName, 0, 1))); ?></span>
                        <?php endif; ?>
                        <div>
                          <div class="fw-semibold"><?php echo htmlspecialchars($employeeName); ?></div>
                          <div class="small text-muted"><?php echo htmlspecialchars((string)($claim['division'] ?? '')); ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?php echo htmlspecialchars((string)($claim['claim_ref'] ?? '')); ?></td>
                    <td><span class="badge text-bg-primary claims-stage-badge"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                    <td><div class="claims-modal-stage"><span class="claims-modal-stage-label">Receive</span><span class="claims-modal-stage-value"><?php echo htmlspecialchars($received !== '' ? date('M d, Y', strtotime($received)) : '---'); ?></span></div></td>
                    <td><div class="claims-modal-stage"><span class="claims-modal-stage-label">PD Approval</span><span class="claims-modal-stage-value"><?php echo htmlspecialchars($pdApproval !== '' ? date('M d, Y', strtotime($pdApproval)) : '---'); ?></span></div></td>
                    <td><div class="claims-modal-stage"><span class="claims-modal-stage-label">Processing</span><span class="claims-modal-stage-value"><?php echo htmlspecialchars($processing !== '' ? date('M d, Y', strtotime($processing)) : '---'); ?></span></div></td>
                    <td><div class="claims-modal-stage"><span class="claims-modal-stage-label">Cheque</span><span class="claims-modal-stage-value"><?php echo htmlspecialchars($cheque !== '' ? date('M d, Y', strtotime($cheque)) : '---'); ?></span></div></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <?php if ($headerIsAdmin): ?>
          <a class="btn btn-primary" href="claims_monitoring.php">Open Claims Tool</a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="globalNotificationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Notifications</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="globalNotifCsrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
        <?php if (empty($headerNotifications)): ?>
          <div class="text-muted">No notifications.</div>
        <?php else: ?>
          <div class="list-group" id="globalNotifList">
            <?php foreach ($headerNotifications as $n): ?>
              <?php $isRead = ((int)($n['is_read'] ?? 0) === 1); ?>
              <?php $notifBodyRaw = (string)($n['body'] ?? ''); ?>
              <?php $notifBodyPlain = strip_tags(html_entity_decode($notifBodyRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>
              <div class="list-group-item" data-notif-id="<?php echo (int)$n['id']; ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <div class="fw-semibold <?php echo $isRead ? 'text-muted' : ''; ?>"><?php echo htmlspecialchars((string)($n['title'] ?? 'Notification')); ?></div>
                    <div class="small <?php echo $isRead ? 'text-muted' : ''; ?>"><?php echo nl2br(htmlspecialchars($notifBodyPlain)); ?></div>
                    <div class="small text-muted mt-1"><?php echo htmlspecialchars((string)($n['created_at'] ?? '')); ?></div>
                  </div>
                  <?php if (!$isRead): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary global-mark-read" data-id="<?php echo (int)$n['id']; ?>">Mark read</button>
                  <?php else: ?>
                    <span class="badge text-bg-light">Read</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="globalMarkAllRead">Mark all as read</button>
      </div>
    </div>
  </div>
</div>

<!-- Notification Details Modal -->
<div class="modal fade" id="notificationDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notificationDetailTitle">Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="notificationDetailBody">
        <div class="text-muted">Loading…</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function() {
    var list = document.getElementById('globalNotifList');
    var markAllBtn = document.getElementById('globalMarkAllRead');
    var badge = document.getElementById('headerNotifBadge');
    var csrfInput = document.getElementById('globalNotifCsrf');
    if (!list || !markAllBtn || !badge || !csrfInput) return;

    function updateBadge(count) {
      var safe = Math.max(0, count);
      badge.textContent = String(safe);
      if (safe > 0) badge.classList.remove('d-none');
      else badge.classList.add('d-none');
    }

    function currentUnreadCount() {
      return list.querySelectorAll('.global-mark-read').length;
    }

    list.addEventListener('click', function(e) {
      // If mark-read button clicked
      var markBtn = e.target.closest('.global-mark-read');
      if (markBtn) {
        var id = markBtn.getAttribute('data-id');
        if (!id) return;
        var fd = new FormData();
        fd.append('id', id);
        fd.append('csrf_token', csrfInput.value || '');
        fetch('api/notifications_mark_read.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); }).then(function(data){
          if (!data || !data.success) return;
          var item = markBtn.closest('.list-group-item');
          if (item) {
            var title = item.querySelector('.fw-semibold');
            var body = item.querySelector('.small');
            if (title) title.classList.add('text-muted');
            if (body) body.classList.add('text-muted');
            markBtn.replaceWith((function(){ var s = document.createElement('span'); s.className = 'badge text-bg-light'; s.textContent = 'Read'; return s; })());
          }
          updateBadge(currentUnreadCount());
        });
        return;
      }

      // Otherwise, open detail modal when clicking a notification item
      var item = e.target.closest('.list-group-item');
      if (!item) return;
      var notifId = item.getAttribute('data-notif-id');
      if (!notifId) return;

      var detailTitleEl = document.getElementById('notificationDetailTitle');
      var detailBodyEl = document.getElementById('notificationDetailBody');
      if (!detailTitleEl || !detailBodyEl) return;
      detailTitleEl.textContent = 'Loading...';
      detailBodyEl.innerHTML = '<div class="text-muted">Loading…</div>';

      fetch('api/notification_detail.php?id=' + encodeURIComponent(notifId)).then(function(r){ return r.json(); }).then(function(data){
        if (!data || !data.success) {
          detailTitleEl.textContent = 'Error';
          detailBodyEl.innerHTML = '<div class="alert alert-danger">Failed to load details.</div>';
          return;
        }
        detailTitleEl.textContent = item.querySelector('.fw-semibold') ? item.querySelector('.fw-semibold').textContent : 'Details';
        detailBodyEl.innerHTML = data.html || '<div class="text-muted">No details available.</div>';
        var modal = new bootstrap.Modal(document.getElementById('notificationDetailModal'));
        modal.show();
        // Mark this notification as read (if unread) when opening details
        var notifIdInt = parseInt(notifId, 10);
        if (!isNaN(notifIdInt)) {
          var fd2 = new FormData();
          fd2.append('id', notifIdInt);
          fd2.append('csrf_token', csrfInput.value || '');
          fetch('api/notifications_mark_read.php', { method: 'POST', body: fd2 }).then(function(r){ return r.json(); }).then(function(mdata){
            if (!mdata || !mdata.success) return;
            // update list item UI: replace mark button with Read badge and dim text
            var markBtn = item.querySelector('.global-mark-read');
            if (markBtn) {
              var titleEl = item.querySelector('.fw-semibold');
              var bodyEl = item.querySelector('.small');
              if (titleEl) titleEl.classList.add('text-muted');
              if (bodyEl) bodyEl.classList.add('text-muted');
              markBtn.replaceWith((function(){ var s = document.createElement('span'); s.className = 'badge text-bg-light'; s.textContent = 'Read'; return s; })());
            }
            // update badge count
            updateBadge(currentUnreadCount());
          }).catch(function(){ /* ignore mark-read failure */ });
        }
      }).catch(function(){
        detailTitleEl.textContent = 'Error';
        detailBodyEl.innerHTML = '<div class="alert alert-danger">Failed to load details.</div>';
      });
    });

    markAllBtn.addEventListener('click', function() {
      var fd = new FormData();
      fd.append('all', '1');
      fd.append('csrf_token', csrfInput.value || '');

      fetch('api/notifications_mark_read.php', {
        method: 'POST',
        body: fd
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data || !data.success) return;
        list.querySelectorAll('.global-mark-read').forEach(function(btn) {
          var item = btn.closest('.list-group-item');
          if (item) {
            var title = item.querySelector('.fw-semibold');
            var body = item.querySelector('.small');
            if (title) title.classList.add('text-muted');
            if (body) body.classList.add('text-muted');
          }
          btn.replaceWith((function(){ var s = document.createElement('span'); s.className = 'badge text-bg-light'; s.textContent = 'Read'; return s; })());
        });
        updateBadge(0);
      });
    });
  })();
</script>

<?php endif; ?>
<?php if ($user): ?>

<?php endif; ?>


<style>
  #globalProcurementModal .proc-modal-employee {
    min-width: 220px;
  }

  #globalProcurementModal .proc-modal-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(100, 116, 139, 0.22);
    background: var(--surface);
  }

  #globalProcurementModal .proc-modal-fallback {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--primary, #2563eb);
    color: #fff;
    font-weight: 700;
  }

  #globalProcurementModal .proc-modal-stage {
    display: inline-flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 135px;
  }

  #globalProcurementModal .proc-modal-stage-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--muted);
  }

  #globalProcurementModal .proc-modal-stage-value {
    font-weight: 700;
    color: var(--text);
  }

  #globalProcurementModal tbody tr.proc-modal-stage-pr > td { background-color: rgba(251,191,36,0.10) !important; }
  #globalProcurementModal tbody tr.proc-modal-stage-pd > td { background-color: rgba(59,130,246,0.10) !important; }
  #globalProcurementModal tbody tr.proc-modal-stage-canvas > td { background-color: rgba(99,102,241,0.10) !important; }
  #globalProcurementModal tbody tr.proc-modal-stage-complete > td { background-color: rgba(16,185,129,0.10) !important; }

  .dark-mode #globalProcurementModal tbody tr.proc-modal-stage-pr > td { background-color: rgba(251,191,36,0.08) !important; }
  .dark-mode #globalProcurementModal tbody tr.proc-modal-stage-pd > td { background-color: rgba(59,130,246,0.08) !important; }
  .dark-mode #globalProcurementModal tbody tr.proc-modal-stage-canvas > td { background-color: rgba(99,102,241,0.08) !important; }
  .dark-mode #globalProcurementModal tbody tr.proc-modal-stage-complete > td { background-color: rgba(16,185,129,0.08) !important; }

  #globalProcurementModal .global-procurement-modal-dialog {
    width: 100vw;
    max-width: 100vw;
    margin: 0;
    padding: 0.45rem;
  }

  #globalProcurementModal .modal-content {
    min-height: calc(100vh - 0.9rem);
    border-radius: 12px;
  }

  #globalClaimsModal .claims-modal-employee {
    min-width: 220px;
  }

  #globalClaimsModal .claims-modal-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(100, 116, 139, 0.22);
    background: var(--surface);
  }

  #globalClaimsModal .claims-modal-fallback {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--primary, #2563eb);
    color: #fff;
    font-weight: 700;
  }

  #globalClaimsModal .claims-modal-stage {
    display: inline-flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 145px;
  }

  #globalClaimsModal .claims-modal-stage-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--muted);
  }

  #globalClaimsModal .claims-modal-stage-value {
    font-weight: 700;
    color: var(--text);
  }

  #globalClaimsModal tbody tr.claims-modal-stage-received > td {
    background-color: rgba(251, 191, 36, 0.10) !important;
  }

  #globalClaimsModal tbody tr.claims-modal-stage-pd > td {
    background-color: rgba(59, 130, 246, 0.10) !important;
  }

  #globalClaimsModal tbody tr.claims-modal-stage-processing > td {
    background-color: rgba(99, 102, 241, 0.10) !important;
  }

  #globalClaimsModal tbody tr.claims-modal-stage-complete > td {
    background-color: rgba(16, 185, 129, 0.10) !important;
  }

  .dark-mode #globalClaimsModal tbody tr.claims-modal-stage-received > td { background-color: rgba(251,191,36,0.08) !important; }
  .dark-mode #globalClaimsModal tbody tr.claims-modal-stage-pd > td { background-color: rgba(59,130,246,0.08) !important; }
  .dark-mode #globalClaimsModal tbody tr.claims-modal-stage-processing > td { background-color: rgba(99,102,241,0.08) !important; }
  .dark-mode #globalClaimsModal tbody tr.claims-modal-stage-complete > td { background-color: rgba(16,185,129,0.08) !important; }

  #globalClaimsModal .global-claims-modal-dialog {
    width: 100vw;
    max-width: 100vw;
    margin: 0;
    padding: 0.45rem;
  }

  #globalClaimsModal .modal-content {
    min-height: calc(100vh - 0.9rem);
    border-radius: 12px;
  }

  #loginActivityModal .modal-content {
    border-radius: 18px;
    overflow: hidden;
  }

  /* Modal entrance animation */
  #loginActivityModal .modal-dialog {
    transform: translateY(-10px);
    transition: transform 360ms cubic-bezier(.2,.8,.2,1), opacity 360ms ease;
    opacity: 0;
  }
  #loginActivityModal.show .modal-dialog {
    transform: translateY(0);
    opacity: 1;
  }

  #loginActivityModal .modal-content {
    background:
      radial-gradient(circle at top right, rgba(37, 99, 235, 0.14), transparent 45%),
      radial-gradient(circle at bottom left, rgba(14, 165, 233, 0.12), transparent 42%),
      rgba(255, 255, 255, 0.78);
    border: 1px solid rgba(255, 255, 255, 0.65);
    box-shadow: 0 24px 56px rgba(15, 23, 42, 0.24);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }

  #loginActivityModal .modal-header {
    border-bottom: 1px solid rgba(148, 163, 184, 0.25);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.45), rgba(255, 255, 255, 0.1));
    padding: 18px 20px;
  }

  #loginActivityModal .modal-body {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.6), rgba(241, 245, 249, 0.38));
  }

  #loginActivityModal .modal-footer {
    border-top: 1px solid rgba(148, 163, 184, 0.22);
    background: rgba(248, 250, 252, 0.48);
  }

  .activity-glass-btn {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(148, 163, 184, 0.7);
    color: #0f172a;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
  }

  .activity-glass-btn:hover,
  .activity-glass-btn:focus {
    background: rgba(255, 255, 255, 0.38);
    border-color: rgba(100, 116, 139, 0.85);
    color: #0b1220;
  }

  .dark-mode .activity-glass-btn {
    background: rgba(30, 41, 59, 0.72);
    border-color: rgba(148, 163, 184, 0.45);
    color: #e6eef6;
    box-shadow: 0 8px 20px rgba(2, 6, 23, 0.45);
  }

  .dark-mode .activity-glass-btn:hover,
  .dark-mode .activity-glass-btn:focus {
    background: rgba(51, 65, 85, 0.86);
    border-color: rgba(148, 163, 184, 0.65);
    color: #ffffff;
  }

  .dark-mode .dti-header .btn-outline-secondary,
  .dark-mode .dti-header a.btn-outline-secondary {
    background: rgba(15, 23, 42, 0.56) !important;
    border-color: rgba(148, 163, 184, 0.45) !important;
    color: #e6eef6 !important;
  }

  .dark-mode .dti-header .btn-outline-secondary:hover,
  .dark-mode .dti-header .btn-outline-secondary:focus,
  .dark-mode .dti-header a.btn-outline-secondary:hover,
  .dark-mode .dti-header a.btn-outline-secondary:focus {
    background: rgba(30, 41, 59, 0.8) !important;
    color: #ffffff !important;
  }

  .dark-mode .dti-header .btn.btn-primary,
  .dark-mode .dti-header a.btn.btn-primary {
    background-color: #3b82f6 !important;
    border-color: #60a5fa !important;
    color: #ffffff !important;
  }

  #loginActivityModal #activityNoData {
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: rgba(241, 245, 249, 0.78);
    color: #334155;
  }

  #loginActivityModal #divisionChartSection,
  #loginActivityModal #accountDrilldownSection .border {
    border: 1px solid rgba(148, 163, 184, 0.25) !important;
    border-radius: 14px !important;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.82), rgba(248, 250, 252, 0.74)) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72), 0 10px 24px rgba(15, 23, 42, 0.08);
  }

  #loginActivityModal #activityPeriodSelect {
    border: 1px solid rgba(148, 163, 184, 0.42);
    background-color: rgba(255, 255, 255, 0.85);
    border-radius: 10px;
  }

  #loginActivityModal .modal-footer .btn {
    border-radius: 999px;
    padding-left: 14px;
    padding-right: 14px;
  }

  #loginActivityModal .activity-modal-dialog {
    width: min(92vw, 1280px);
    max-width: min(92vw, 1280px);
    margin: 1.75rem auto;
  }

  #loginActivityModal .modal-body,
  #loginActivityModal .modal-content {
    overflow-x: hidden;
  }

  #loginActivityModal .modal-title {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: -0.01em;
    margin-bottom: 4px;
    color: #0f172a;
  }

  #loginActivityModal #activitySubtitle {
    font-size: 13px;
    color: #475569;
  }

  #loginActivityModal .form-label,
  #loginActivityModal .form-select,
  #loginActivityModal #accountDrilldownTitle,
  #loginActivityModal .btn {
    font-size: 14px;
  }

  #loginActivityModal .activity-chart-wrap {
    height: 430px;
  }

  @media (max-width: 768px) {
    #loginActivityModal .activity-modal-dialog {
      width: 96vw;
      max-width: 96vw;
      margin: 0.75rem auto;
    }
  }
</style>

<!-- Login Modal (in header so available on all pages) -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
        
      <div class="modal-header">
        <h5 class="modal-title">Login</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="headerLoginForm" class="needs-validation" novalidate method="post" action="login.php">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="mb-3">
            <label for="loginEmailHeader" class="form-label">Email</label>
            <input name="email" type="email" class="form-control" id="loginEmailHeader" required>
            <div class="invalid-feedback">Please enter a valid email.</div>
          </div>
          <div class="mb-3">
            <label for="loginPasswordHeader" class="form-label">Password</label>
            <input name="password" type="password" class="form-control" id="loginPasswordHeader" required>
            <div class="invalid-feedback">Please enter your password.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link me-auto" id="showCreate">Create account</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Login</button>
        </div>
      </form>
    </div>
  </div>
</div>

