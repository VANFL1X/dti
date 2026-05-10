<?php
require_once __DIR__ . '/includes/init.php';
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$user = $_SESSION['user'];
$isAdmin = user_has_division($user, 'Admin Division');

$pendingVehicleRequests = [];
if ($isAdmin) {
  $pendingSql = "SELECT vr.id, vr.date_application, vr.date_use, vr.departure_date, vr.departure_time,
              vr.expected_arrival_date, vr.expected_arrival_time, vr.vehicle_plate_no,
              vr.destination, vr.purpose, vr.driver_name, vr.transportation_incharge,
              u.first_name, u.last_name,
              GROUP_CONCAT(p.passenger_name ORDER BY p.id SEPARATOR ', ') AS passengers
           FROM vehicle_requests vr
           LEFT JOIN users u ON u.id = vr.user_id
           LEFT JOIN passengers p ON p.request_id = vr.id
           WHERE vr.status = 'pending'
           GROUP BY vr.id
           ORDER BY vr.created_at DESC";
  $pendingRes = $mysqli->query($pendingSql);
  if ($pendingRes) {
    while ($row = $pendingRes->fetch_assoc()) {
      $pendingVehicleRequests[] = $row;
    }
  }
}
$pendingSupplyRequests = [];
if ($isAdmin) {
  $supplySql = "SELECT sr.id, sr.item, sr.variant, sr.quantity, sr.unit, sr.created_at, u.first_name, u.last_name, u.division FROM supply_requests sr JOIN users u ON u.id = sr.user_id ORDER BY sr.created_at DESC LIMIT 100";
  $supplyRes = $mysqli->query($supplySql);
  if ($supplyRes) {
    while ($r = $supplyRes->fetch_assoc()) {
      $pendingSupplyRequests[] = $r;
    }
  }
}

$pendingObSlips = [];
if ($isAdmin) {
  $obSql = "SELECT ob.id, ob.ob_type, ob.slip_date, ob.employee_name, ob.section_name, ob.purpose, ob.destination, ob.departure_time, ob.return_time, ob.created_at, u.first_name, u.last_name
            FROM ob_slips ob
            JOIN users u ON u.id = ob.user_id
            ORDER BY ob.created_at DESC
            LIMIT 100";
  $obRes = $mysqli->query($obSql);
  if ($obRes) {
    while ($r = $obRes->fetch_assoc()) {
      $pendingObSlips[] = $r;
    }
  }
}

$userNotifications = [];
$userNotificationCount = 0;
if (!$isAdmin) {
  $notifStmt = $mysqli->prepare("SELECT id, type, title, body, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
  if ($notifStmt) {
    $uid = (int)$user['id'];
    $notifStmt->bind_param('i', $uid);
    $notifStmt->execute();
    $notifRes = $notifStmt->get_result();
    if ($notifRes) {
      while ($n = $notifRes->fetch_assoc()) {
        $userNotifications[] = $n;
        if ((int)($n['is_read'] ?? 0) === 0) {
          $userNotificationCount++;
        }
      }
    }
    $notifStmt->close();
  }
}

// Get upcoming report deadlines for the user
$upcomingDeadlines = [];
$userDivisions = parse_user_divisions($user['division'] ?? '');
if (!empty($userDivisions)) {
  // Get deadlines for this user's divisions (both user-specific and division-wide)
  $placeholders = implode(',', array_fill(0, count($userDivisions), '?'));
  $uid = (int)$user['id'];
  $deadlineQuery = "
    SELECT rd.id, rd.division, rd.report_type, rd.deadline_date, rd.deadline_time, rd.remarks, rd.user_id, rd.notify_before_days
    FROM report_deadlines rd
    WHERE rd.status = 'active'
    AND rd.deadline_date >= DATE(NOW())
    AND (
      (rd.user_id = ? AND FIND_IN_SET(rd.division, ?)) OR
      (rd.user_id IS NULL AND FIND_IN_SET(rd.division, ?))
    )
    ORDER BY rd.deadline_date ASC, rd.deadline_time ASC
    LIMIT 10
  ";
  $divisionStr = implode(',', $userDivisions);
  $deadlineStmt = $mysqli->prepare($deadlineQuery);
  if ($deadlineStmt) {
    $deadlineStmt->bind_param('iss', $uid, $divisionStr, $divisionStr);
    $deadlineStmt->execute();
    $deadlineRes = $deadlineStmt->get_result();
    while ($d = $deadlineRes->fetch_assoc()) {
      $upcomingDeadlines[] = $d;
    }
    $deadlineStmt->close();
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTI Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
      /* Make page a column flex layout so footer can sit at bottom */
      html, body { height: 100%; }
      body { display: flex; flex-direction: column; min-height: 100vh; }
      .section-shell { flex: 1 1 auto; }
      .admin-tool-btn {
        /* Match landing page button dimensions */
        min-height: 106px;
        padding: 1.15rem 1.65rem;
        font-weight: 700;
        font-size: 1.14rem;
        display: flex;
        align-items: center;
        width: 100%;
        justify-content: flex-start;
        flex: 1 1 auto;
        color: var(--text) !important;
        text-align: left;
        gap: 0.6rem;
      }
      .admin-tools-row > .col {
        display: flex;
        align-items: stretch;
      }
      .admin-tool-stack {
        display: flex;
        flex-direction: column;
        gap: .75rem;
        width: 100%;
      }
      .admin-tool-stack .admin-tool-btn:first-child {
        flex: 1 1 auto;
      }
      .admin-tool-stack .admin-tool-btn:last-child {
        flex: 0 0 auto;
      }
      .admin-tool-main { flex: 1 1 auto; }
      .admin-tool-action { flex: 0 0 auto; }
      .admin-tool-btn .bi,
      .dashboard-nav-btn .bi {
        width: 1.5rem;
        min-width: 1.5rem;
        text-align: center;
        font-size: 1.35rem;
        color: inherit;
        margin-right: 0 !important;
      }
      .dashboard-nav-btn {
        min-height: 110px;
        padding: 1.15rem 1.2rem;
        font-size: 1.12rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        text-align: left;
        gap: 0.6rem;
      }
      .dashboard-nav-btn .bi { font-size: 1.4rem; }
      .admin-tool-btn,
      .dashboard-nav-btn {
        background: rgba(148, 163, 184, 0.18) !important;
        border: 1px solid rgba(100, 116, 139, 0.35) !important;
        backdrop-filter: blur(14px) saturate(1.1);
        -webkit-backdrop-filter: blur(14px) saturate(1.1);
        box-shadow: 0 12px 28px rgba(51, 65, 85, 0.18);
        transition: transform .2s ease, box-shadow .2s ease, background-color .2s ease;
      }
      .admin-tool-btn:hover,
      .dashboard-nav-btn:hover {
        background: rgba(148, 163, 184, 0.28) !important;
        box-shadow: 0 16px 34px rgba(51, 65, 85, 0.24);
        transform: translateY(-2px);
      }
      .dark-mode .admin-tool-btn,
      .dark-mode .dashboard-nav-btn {
        background: rgba(51, 65, 85, 0.48) !important;
        border-color: rgba(148, 163, 184, 0.28) !important;
        color: #ffffff !important;
      }
      .dark-mode .admin-tool-btn:hover,
      .dark-mode .dashboard-nav-btn:hover {
        background: rgba(71, 85, 105, 0.58) !important;
        color: #ffffff !important;
      }
      .dark-mode .admin-tool-btn .bi,
      .dark-mode .dashboard-nav-btn .bi {
        color: #ffffff !important;
      }
      /* Wider pending modal (use large horizontal space without forcing fullscreen height) */
      .vehicle-modal-wide {
        max-width: calc(100vw - 48px) !important;
        width: calc(100vw - 48px) !important;
        margin: 0 24px !important;
      }
      .vehicle-modal-wide .modal-content { overflow-x: hidden; }
      /* Center admin dashboard card and constrain max width for readability */
      .section-card--admin {
        max-width: 1100px;
        margin: 0 auto;
        width: 100%;
        background: rgba(148, 163, 184, 0.2);
        border: 1px solid rgba(100, 116, 139, 0.38);
        backdrop-filter: blur(18px) saturate(1.05);
        -webkit-backdrop-filter: blur(18px) saturate(1.05);
        box-shadow: 0 18px 36px rgba(51, 65, 85, 0.22);
      }
      .section-card--admin::before { display: none !important; }
      .dark-mode .section-card--admin {
        background: rgba(51, 65, 85, 0.55);
        border-color: rgba(148, 163, 184, 0.25);
      }
      .records-modal-dialog {
        max-width: 980px !important;
        width: calc(100vw - 32px) !important;
        margin: 16px auto !important;
      }
      .records-modal-content {
        overflow: hidden;
      }
      .records-modal-body {
        padding: 1rem;
      }
      .records-print-box {
        border: 1px solid rgba(100, 116, 139, 0.28);
        border-radius: 12px;
        padding: 0.9rem;
        background: rgba(148, 163, 184, 0.08);
      }
      .records-modal-form .form-label {
        font-size: 0.78rem;
        margin-bottom: 0.25rem;
      }
      /* Deadline card styles */
      .deadline-reminder-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
      }
      .deadline-reminder-card.urgent {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        box-shadow: 0 8px 24px rgba(245, 87, 108, 0.3);
      }
      .deadline-reminder-card,
      .deadline-reminder-card * {
        color: #fff;
      }
      .deadline-reminder-info h6 {
        margin-bottom: 0.5rem;
        font-weight: 600;
      }
      .deadline-reminder-info .deadline-type {
        font-size: 0.95rem;
        opacity: 0.95;
        margin-bottom: 0.25rem;
      }
      .deadline-reminder-info .deadline-date {
        font-size: 0.9rem;
        opacity: 0.85;
      }
      .deadline-reminder-icon {
        font-size: 2.5rem;
        opacity: 0.3;
      }
      .deadline-upcoming-list {
        max-height: 400px;
        overflow-y: auto;
      }
      .deadline-upcoming-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        background: var(--card-bg);
        border-left: 4px solid #667eea;
        margin-bottom: 0.5rem;
        border-radius: 4px;
        color: var(--text);
      }
      .deadline-upcoming-item.urgent {
        border-left-color: #f5576c;
      }
      .deadline-upcoming-item .deadline-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-right: 0.5rem;
      }
      .deadline-upcoming-item .deadline-badge.days-1 {
        background: #ffe7e7;
        color: #f5576c;
      }
      .deadline-upcoming-item .deadline-badge.days-3 {
        background: #fff4e7;
        color: #f59e0b;
      }
      .deadline-upcoming-item .deadline-badge.days-7 {
        background: #e7f0ff;
        color: #667eea;
      }
      .dark-mode .deadline-reminder-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      }
      .dark-mode .deadline-upcoming-item {
        background: rgba(15, 23, 42, 0.72);
        border-left-color: #93c5fd;
        border: 1px solid rgba(148, 163, 184, 0.22);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
      }
      .dark-mode .deadline-upcoming-item.urgent {
        border-left-color: #f87171;
      }
      .dark-mode .deadline-upcoming-item .deadline-badge.days-1 {
        background: rgba(239, 68, 68, 0.16);
        color: #fca5a5;
      }
      .dark-mode .deadline-upcoming-item .deadline-badge.days-3 {
        background: rgba(245, 158, 11, 0.18);
        color: #fbbf24;
      }
      .dark-mode .deadline-upcoming-item .deadline-badge.days-7 {
        background: rgba(59, 130, 246, 0.16);
        color: #93c5fd;
      }
    </style>
  </head>
  <body class="p-4">
    <?php include __DIR__ . '/includes/header.php'; ?>
    <div class="container mt-4 section-shell">
      <?php
        // Select dashboard title/subtitle based on user division (checks all divisions)
        $dashboardTitle = 'Dashboard';
        $dashboardSubtitle = 'Quick access to tools.';

        $divisionMap = [
          'Admin Division' => ['title' => 'Admin Dashboard', 'subtitle' => 'Quick access to admin tools.'],
          'Office of the Provincial Director' => ['title' => 'Office of the Provincial Director', 'subtitle' => 'Quick access to OPD tools.'],
          'Consumer Protection Division' => ['title' => 'Consumer Protection Dashboard', 'subtitle' => 'Quick access to consumer protection tools.'],
          'Business Development Division' => ['title' => 'Business Development Dashboard', 'subtitle' => 'Quick access to business development tools.'],
          'Planning Unit' => ['title' => 'Planning Unit Dashboard', 'subtitle' => 'Quick access to planning tools.'],
        ];

        foreach ($divisionMap as $divName => $info) {
          if (user_has_division($user, $divName)) {
            $dashboardTitle = $info['title'];
            $dashboardSubtitle = $info['subtitle'];
            break;
          }
        }
      ?>
      <div class="section-head">
          <span class="section-icon"><i class="bi bi-grid-3x3-gap"></i></span>
          <div>
            <h2 class="h5 mb-1"><?php echo htmlspecialchars($dashboardTitle); ?></h2>
            <p class="section-subtitle text-muted mb-0"><?php echo htmlspecialchars($dashboardSubtitle); ?></p>
          </div>
        </div>
      <div class="section-card section-card--admin mt-4">
        <div class="row row-cols-1 row-cols-md-4 g-3">
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start dashboard-nav-btn" href="admin-division.php"><i class="bi bi-building me-2"></i>Admin Division</a>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start dashboard-nav-btn" href="opd.php"><i class="bi bi-person-badge me-2"></i>Office of Provincial Director</a>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start dashboard-nav-btn" href="business-development.php"><i class="bi bi-briefcase me-2"></i>Business Development</a>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start dashboard-nav-btn" href="consumer-protection.php"><i class="bi bi-shield-lock me-2"></i>Consumer Protection</a>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start dashboard-nav-btn" href="planning-unit.php"><i class="bi bi-diagram-3 me-2"></i>Planning Unit</a>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start dashboard-nav-btn" href="tracker.php"><i class="bi bi-search me-2"></i>Tracker</a>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start dashboard-nav-btn" href="calendar.php"><i class="bi bi-calendar-event me-2"></i>Indicative Calendar</a>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start dashboard-nav-btn" href="profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a>
          </div>
        </div>
      </div>

      <!-- Report Deadlines Section -->
      <?php if (!empty($upcomingDeadlines)): ?>
      <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0"><i class="bi bi-clock-history"></i> Your Upcoming Report Deadlines</h5>
        </div>
        <div class="deadline-upcoming-list">
          <?php foreach ($upcomingDeadlines as $deadline): ?>
            <?php 
              $deadlineDateTime = new DateTime($deadline['deadline_date'] . ' ' . $deadline['deadline_time']);
              $now = new DateTime();
              $daysUntil = $deadlineDateTime->diff($now)->days;
              if ($deadlineDateTime < $now) {
                $daysUntil = -$daysUntil; // negative if in past
              }
              $isUrgent = $daysUntil <= 1;
              $badgeClass = $daysUntil <= 1 ? 'days-1' : ($daysUntil <= 3 ? 'days-3' : 'days-7');
            ?>
            <div class="deadline-upcoming-item <?php echo $isUrgent ? 'urgent' : ''; ?>">
              <span class="deadline-badge <?php echo $badgeClass; ?>">
                <?php echo $daysUntil . ' day' . ($daysUntil !== 1 ? 's' : ''); ?>
              </span>
              <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 0.95rem;">
                  <?php echo ucfirst($deadline['report_type']); ?> Report
                </div>
                <div style="font-size: 0.85rem; opacity: 0.8;">
                  <?php echo htmlspecialchars($deadline['division']); ?> â€¢ Due <?php echo date('M d, Y \a\t g:i A', strtotime($deadline['deadline_date'] . ' ' . $deadline['deadline_time'])); ?>
                </div>
                <?php if ($deadline['remarks']): ?>
                  <div style="font-size: 0.8rem; opacity: 0.7; font-style: italic;">
                    ðŸ“ <?php echo htmlspecialchars($deadline['remarks']); ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($isAdmin): ?>
      <div class="section-card section-card--admin mt-4">
        <div class="row row-cols-1 row-cols-md-3 g-2 admin-tools-row">
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start admin-tool-btn" href="claims_monitoring.php"><i class="bi bi-clipboard-data me-2"></i>Claims Monitoring Tool</a>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start admin-tool-btn" href="procurement_monitoring.php"><i class="bi bi-box-seam me-2"></i>Procurement Monitoring Tool</a>
          </div>
          <div class="col">
            <div class="admin-tool-stack">
              <button type="button" class="btn btn-outline-secondary text-start admin-tool-btn" data-bs-toggle="modal" data-bs-target="#vehicleRequestModal"><i class="bi bi-truck me-2"></i>Pending</button>
            </div>
          </div>
          <div class="col">
            <button type="button" class="btn btn-outline-secondary w-100 text-start admin-tool-btn" data-bs-toggle="modal" data-bs-target="#recordsPreviewModal"><i class="bi bi-folder2-open me-2"></i>Records</button>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start admin-tool-btn" href="report-deadlines.php"><i class="bi bi-alarm me-2"></i>Report Deadline</a>
          </div>
          <div class="col">
            <a class="btn btn-outline-secondary w-100 text-start admin-tool-btn" href="inventory.php"><i class="bi bi-boxes me-2"></i>Inventory</a>
          </div>

        </div>
      </div>

      <div class="modal fade" id="vehicleRequestModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable vehicle-modal-wide">
              <div class="modal-content" style="overflow-x:hidden;">
              <div class="modal-header">
              <h5 class="modal-title">Pending Vehicle Requests</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p class="text-muted mb-3">Approve requests to automatically post them in the calendar.</p>
              <div id="vehicleApproveAlert"></div>
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Requester</th>
                      <th>Vehicle</th>
                      <th>Schedule</th>
                      <th>Destination</th>
                      <th>Passengers</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody id="pendingVehicleBody">
                    <?php if (empty($pendingVehicleRequests)): ?>
                      <tr id="no-pending-vehicle-row">
                        <td colspan="6" class="text-muted">No pending vehicle requests.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($pendingVehicleRequests as $req): ?>
                        <tr id="vehicle-request-row-<?php echo (int)$req['id']; ?>">
                          <td><?php echo htmlspecialchars(trim(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? ''))); ?></td>
                          <td>
                            <div><strong><?php echo htmlspecialchars($req['vehicle_plate_no']); ?></strong></div>
                            <small class="text-muted">Driver: <?php echo htmlspecialchars($req['driver_name']); ?></small>
                          </td>
                          <td>
                            <div><?php echo htmlspecialchars($req['departure_date'] . ' ' . $req['departure_time']); ?></div>
                            <small class="text-muted">to <?php echo htmlspecialchars($req['expected_arrival_date'] . ' ' . ($req['expected_arrival_time'] ?? '')); ?></small>
                          </td>
                          <td>
                            <div><?php echo htmlspecialchars($req['destination']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($req['purpose']); ?></small>
                          </td>
                          <td><small><?php echo htmlspecialchars((string)($req['passengers'] ?? '')); ?></small></td>
                          <td>
                            <div class="d-flex gap-2">
                              <button class="btn btn-sm btn-primary approve-vehicle-btn" data-request-id="<?php echo (int)$req['id']; ?>">Approve</button>
                              <button class="btn btn-sm btn-danger reject-vehicle-btn" data-request-id="<?php echo (int)$req['id']; ?>">Reject</button>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Notifications</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <?php if (empty($pendingVehicleRequests) && empty($pendingSupplyRequests) && empty($pendingObSlips)): ?>
                <p class="text-muted mb-0">No new notifications.</p>
              <?php else: ?>
                <?php if (!empty($pendingVehicleRequests)): ?>
                  <h6>Pending Vehicle Requests</h6>
                  <ul class="list-group mb-3">
                    <?php foreach ($pendingVehicleRequests as $v): ?>
                      <li class="list-group-item list-group-item-light d-flex justify-content-between align-items-start">
                        <div>
                          <div class="fw-bold"><?php echo htmlspecialchars(trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? ''))); ?></div>
                          <div class="text-muted small"><?php echo htmlspecialchars($v['departure_date'] . ' ' . $v['departure_time']); ?> &mdash; <?php echo htmlspecialchars($v['destination']); ?></div>
                        </div>
                        <div class="text-end">
                          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#vehicleRequestModal">View</button>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

                <?php if (!empty($pendingSupplyRequests)): ?>
                  <h6>Supply Requests</h6>
                  <ul class="list-group">
                    <?php foreach ($pendingSupplyRequests as $s): ?>
                      <li class="list-group-item list-group-item-light d-flex justify-content-between align-items-start">
                        <div>
                          <div class="fw-bold"><?php echo htmlspecialchars(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''))); ?></div>
                          <div class="text-muted small"><?php echo htmlspecialchars($s['item']); ?><?php echo ($s['variant'] !== '' ? ' &mdash; ' . htmlspecialchars($s['variant']) : ''); ?> Â· <?php echo (int)$s['quantity']; ?> <?php echo htmlspecialchars($s['unit']); ?></div>
                        </div>
                        <div class="text-end">
                          <a class="btn btn-sm btn-outline-secondary" href="tracker.php">Open</a>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

                <?php if (!empty($pendingObSlips)): ?>
                  <h6 class="mt-3">OB Slips</h6>
                  <ul class="list-group">
                    <?php foreach ($pendingObSlips as $o): ?>
                      <li class="list-group-item list-group-item-light d-flex justify-content-between align-items-start">
                        <div>
                          <div class="fw-bold"><?php echo htmlspecialchars(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''))); ?></div>
                          <div class="text-muted small">
                            <?php echo htmlspecialchars($o['ob_type']); ?> · <?php echo htmlspecialchars($o['slip_date']); ?> · <?php echo htmlspecialchars($o['departure_time']); ?>-<?php echo htmlspecialchars($o['return_time']); ?>
                          </div>
                          <div class="text-muted small"><?php echo htmlspecialchars($o['destination']); ?></div>
                        </div>
                        <div class="text-end">
                          <span class="badge text-bg-secondary"><?php echo htmlspecialchars($o['section_name']); ?></span>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="modal fade" id="recordsPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable records-modal-dialog">
          <div class="modal-content records-modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title mb-0">Records</h5>
                <div class="text-muted">Choose the record type and filters before opening the report.</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body records-modal-body">
              <form class="row g-2 records-modal-form" method="get" action="records_print.php" target="_blank">
                <div class="col-12">
                  <label class="form-label" for="recordsModalType">Record Type</label>
                  <select class="form-select" id="recordsModalType" name="type">
                    <option value="supply">Supply Request</option>
                    <option value="vehicle">Vehicle Request</option>
                    <option value="claims">Claims</option>
                    <option value="ob_slip">OB Slip</option>
                    <option value="procurement">Procurement</option>
                    <option value="activity">Activities</option>
                    <option value="inventory">Inventory</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="recordsModalPeriod">Period</label>
                  <select class="form-select" id="recordsModalPeriod" name="period">
                    <option value="day">Daily</option>
                    <option value="week">Weekly</option>
                    <option value="month" selected>Monthly</option>
                  </select>
                </div>
                <div class="col-md-6" id="recordsModalDayWrap" style="display:none;">
                  <label class="form-label" for="recordsModalDate">Date</label>
                  <input type="date" class="form-control" id="recordsModalDate" name="date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                </div>
                <div class="col-md-6" id="recordsModalWeekWrap" style="display:none;">
                  <label class="form-label" for="recordsModalWeek">Week</label>
                  <input type="week" class="form-control" id="recordsModalWeek" name="week" value="<?php echo htmlspecialchars(date('o-\WW')); ?>">
                </div>
                <div class="col-md-6" id="recordsModalMonthWrap">
                  <label class="form-label" for="recordsModalMonth">Month</label>
                  <input type="month" class="form-control" id="recordsModalMonth" name="month" value="<?php echo htmlspecialchars(date('Y-m')); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="recordsModalDivision">Division</label>
                  <select class="form-select" id="recordsModalDivision" name="division">
                    <option value="">All divisions</option>
                    <?php
                      $dashboardDivisions = [];
                      $dashboardUsersRes = $mysqli->query("SELECT DISTINCT division FROM users WHERE division IS NOT NULL AND division <> '' ORDER BY division ASC");
                      if ($dashboardUsersRes) {
                        while ($divRow = $dashboardUsersRes->fetch_assoc()) {
                          $dashboardDivisions[] = $divRow['division'];
                        }
                      }
                      foreach ($dashboardDivisions as $divName):
                    ?>
                      <option value="<?php echo htmlspecialchars($divName); ?>"><?php echo htmlspecialchars($divName); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="recordsModalEmployee">Employee</label>
                  <select class="form-select" id="recordsModalEmployee" name="employee_id">
                    <option value="0">All employees</option>
                    <?php
                      $dashboardEmployeesRes = $mysqli->query("SELECT id, first_name, last_name FROM users ORDER BY last_name ASC, first_name ASC");
                      if ($dashboardEmployeesRes) {
                        while ($empRow = $dashboardEmployeesRes->fetch_assoc()) {
                          $empName = trim((string)($empRow['first_name'] ?? '') . ' ' . (string)($empRow['last_name'] ?? ''));
                    ?>
                      <option value="<?php echo (int)$empRow['id']; ?>"><?php echo htmlspecialchars($empName !== '' ? $empName : 'Unknown Employee'); ?></option>
                    <?php
                        }
                      }
                    ?>
                  </select>
                </div>
                <div class="col-12 d-flex gap-2 mt-2">
                  <button type="submit" class="btn btn-primary"><i class="bi bi-printer me-1"></i> Open Report</button>
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <?php if (!$isAdmin): ?>
      <div class="modal fade" id="userNotificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">My Notifications</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <?php if (empty($userNotifications)): ?>
                <p class="text-muted mb-0">No notifications.</p>
              <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="small text-muted">Last <?php echo count($userNotifications); ?> notifications</div>
                  <button id="userMarkAllReadBtn" class="btn btn-sm btn-outline-secondary">Mark all read</button>
                </div>
                <ul class="list-group">
                  <?php foreach ($userNotifications as $n): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start <?php echo ((int)($n['is_read'] ?? 0) === 0) ? 'list-group-item-info' : ''; ?>" data-notification-id="<?php echo (int)$n['id']; ?>">
                      <div>
                        <div class="fw-bold"><?php echo htmlspecialchars((string)$n['title']); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars((string)$n['body']); ?></div>
                        <div class="text-muted xsmall mt-1"><small><?php echo htmlspecialchars((string)$n['created_at']); ?></small></div>
                      </div>
                      <div class="text-end">
                        <?php if ((int)($n['is_read'] ?? 0) === 0): ?>
                          <button class="btn btn-sm btn-primary user-mark-read-btn" data-id="<?php echo (int)$n['id']; ?>">Mark read</button>
                        <?php else: ?>
                          <span class="text-secondary small">Read</span>
                        <?php endif; ?>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/script.js?v=20260407k2"></script>
    <script>
      (function () {
        var period = document.getElementById('recordsModalPeriod');
        var dayWrap = document.getElementById('recordsModalDayWrap');
        var weekWrap = document.getElementById('recordsModalWeekWrap');
        var monthWrap = document.getElementById('recordsModalMonthWrap');

        function updateRecordsModalPeriodFields() {
          if (!period) return;
          var value = period.value;
          if (dayWrap) dayWrap.style.display = value === 'day' ? '' : 'none';
          if (weekWrap) weekWrap.style.display = value === 'week' ? '' : 'none';
          if (monthWrap) monthWrap.style.display = value === 'month' ? '' : 'none';
        }

        if (period) {
          period.addEventListener('change', updateRecordsModalPeriodFields);
          updateRecordsModalPeriodFields();
        }
      })();
    </script>
    <?php if ($isAdmin): ?>
    <script>
      (function() {
        var csrf = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        var buttons = document.querySelectorAll('.approve-vehicle-btn');
        var alertEl = document.getElementById('vehicleApproveAlert');
        var pendingBody = document.getElementById('pendingVehicleBody');

        buttons.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var requestId = btn.getAttribute('data-request-id');
            if (!requestId) return;

            // Optimistic UI: remove the row immediately and show success
            var row = document.getElementById('vehicle-request-row-' + requestId);
            var rowHTML = row ? row.outerHTML : null;
            if (row) row.remove();
            if (pendingBody && pendingBody.querySelectorAll('tr[id^="vehicle-request-row-"]').length === 0) {
              pendingBody.innerHTML = '<tr id="no-pending-vehicle-row"><td colspan="6" class="text-muted">No pending vehicle requests.</td></tr>';
            }
            if (alertEl) {
              alertEl.innerHTML = '<div class="alert alert-success">Vehicle request approved (pending server confirmation).</div>';
            }

            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('request_id', requestId);

            fetch('api/vehicle_approve.php', { method: 'POST', body: fd })
              .then(function(res) { return res.json(); })
              .then(function(data) {
                if (data.success) {
                  // success already shown optimistically; optionally refresh calendar highlights here
                } else {
                  // restore row on failure
                  if (rowHTML && pendingBody) {
                    // remove the 'no pending' row if present
                    var noPending = document.getElementById('no-pending-vehicle-row');
                    if (noPending) noPending.remove();
                    pendingBody.insertAdjacentHTML('afterbegin', rowHTML);
                  }
                  if (alertEl) alertEl.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Approval failed') + '</div>';
                }
              })
              .catch(function() {
                // restore row on network error
                if (rowHTML && pendingBody) {
                  var noPending = document.getElementById('no-pending-vehicle-row');
                  if (noPending) noPending.remove();
                  pendingBody.insertAdjacentHTML('afterbegin', rowHTML);
                }
                if (alertEl) alertEl.innerHTML = '<div class="alert alert-danger">Network error while approving request.</div>';
              });
          });
        });
        // Reject handlers
        var rejectButtons = document.querySelectorAll('.reject-vehicle-btn');
        rejectButtons.forEach(function(rbtn) {
          rbtn.addEventListener('click', function() {
            var requestId = rbtn.getAttribute('data-request-id');
            if (!requestId) return;
            if (!confirm('Are you sure you want to reject this vehicle request?')) return;

            // Optimistic UI: remove row immediately
            var row = document.getElementById('vehicle-request-row-' + requestId);
            var rowHTML = row ? row.outerHTML : null;
            if (row) row.remove();
            if (pendingBody && pendingBody.querySelectorAll('tr[id^="vehicle-request-row-"]').length === 0) {
              pendingBody.innerHTML = '<tr id="no-pending-vehicle-row"><td colspan="6" class="text-muted">No pending vehicle requests.</td></tr>';
            }
            if (alertEl) alertEl.innerHTML = '<div class="alert alert-warning">Vehicle request rejected (pending server confirmation).</div>';

            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('request_id', requestId);
            fetch('api/vehicle_reject.php', { method: 'POST', body: fd })
              .then(function(res) { return res.json(); })
              .then(function(data) {
                if (data.success) {
                  // nothing to do; already removed
                } else {
                  // restore row on failure
                  if (rowHTML && pendingBody) {
                    var noPending = document.getElementById('no-pending-vehicle-row');
                    if (noPending) noPending.remove();
                    pendingBody.insertAdjacentHTML('afterbegin', rowHTML);
                  }
                  if (alertEl) alertEl.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Rejection failed') + '</div>';
                }
              })
              .catch(function() {
                if (rowHTML && pendingBody) {
                  var noPending = document.getElementById('no-pending-vehicle-row');
                  if (noPending) noPending.remove();
                  pendingBody.insertAdjacentHTML('afterbegin', rowHTML);
                }
                if (alertEl) alertEl.innerHTML = '<div class="alert alert-danger">Network error while rejecting request.</div>';
              });
          });
        });
      })();
    </script>
    <?php endif; ?>

    <?php if (!$isAdmin): ?>
    <script>
      (function() {
        var csrf = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;

        function updateUserBadge(delta) {
          var btn = document.getElementById('userNotificationBtn');
          if (!btn) return;
          var badge = btn.querySelector('.badge');
          if (!badge) return;
          var n = parseInt((badge.textContent || '0').trim(), 10) || 0;
          n = Math.max(0, n + delta);
          if (n === 0) {
            badge.remove();
          } else {
            badge.textContent = String(n);
          }
        }

        document.addEventListener('click', function(evt) {
          var markBtn = evt.target.closest('.user-mark-read-btn');
          if (markBtn) {
            evt.preventDefault();
            var id = markBtn.getAttribute('data-id');
            if (!id) return;
            markBtn.disabled = true;
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('id', id);
            fetch('api/notifications_mark_read.php', { method: 'POST', body: fd })
              .then(function(res) { return res.json(); })
              .then(function(data) {
                if (!data.success) {
                  markBtn.disabled = false;
                  alert(data.message || 'Failed to mark as read');
                  return;
                }
                var li = markBtn.closest('li[data-notification-id]');
                if (li) li.classList.remove('list-group-item-info');
                var readLabel = document.createElement('span');
                readLabel.className = 'text-secondary small';
                readLabel.textContent = 'Read';
                markBtn.parentNode.replaceChild(readLabel, markBtn);
                updateUserBadge(-1);
              })
              .catch(function() {
                markBtn.disabled = false;
                alert('Network error');
              });
            return;
          }

          var allBtn = evt.target.closest('#userMarkAllReadBtn');
          if (allBtn) {
            evt.preventDefault();
            allBtn.disabled = true;
            var fdAll = new FormData();
            fdAll.append('csrf_token', csrf);
            fdAll.append('all', '1');
            fetch('api/notifications_mark_read.php', { method: 'POST', body: fdAll })
              .then(function(res) { return res.json(); })
              .then(function(data) {
                if (!data.success) {
                  allBtn.disabled = false;
                  alert(data.message || 'Failed to mark all as read');
                  return;
                }
                document.querySelectorAll('#userNotificationModal li.list-group-item-info').forEach(function(li) {
                  li.classList.remove('list-group-item-info');
                });
                document.querySelectorAll('#userNotificationModal .user-mark-read-btn').forEach(function(btn) {
                  var readLabel = document.createElement('span');
                  readLabel.className = 'text-secondary small';
                  readLabel.textContent = 'Read';
                  btn.parentNode.replaceChild(readLabel, btn);
                });
                var btn = document.getElementById('userNotificationBtn');
                if (btn) {
                  var badge = btn.querySelector('.badge');
                  if (badge) badge.remove();
                }
              })
              .catch(function() {
                allBtn.disabled = false;
                alert('Network error');
              });
          }
        });
      })();
    </script>
    <?php endif; ?>
  </body>
</html>












