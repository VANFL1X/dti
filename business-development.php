<?php
require_once __DIR__ . '/includes/init.php';
$user = $_SESSION['user'] ?? null;
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Business Development Division</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
  </head>
  <body class="p-4">
    <?php include __DIR__ . '/includes/header.php'; ?>
    <div class="container mt-4 section-shell">
      <div class="section-card section-card--business">
        <div class="section-head">
          <span class="section-icon"><i class="bi bi-briefcase"></i></span>
          <div>
            <h1 class="h3 mb-1">Business Development Division</h1>
            <p class="section-subtitle text-muted">Programs, opportunities, and support for MSMEs.</p>
          </div>
        </div>
        <div class="section-actions justify-content-center">
          <a class="btn btn-outline-secondary section-action-btn" href="profile.php"><i class="bi bi-person-circle"></i><span>View Profile</span></a>
          <?php if ($user && user_has_division($user, 'Business Development Division')): ?>
            <button id="setActivityBtn" class="btn btn-primary section-action-btn ms-2" data-division="Business Development Division"><i class="bi bi-calendar-plus"></i><span>Set Activity</span></button>
          <?php elseif (!$user): ?>
            <button class="btn btn-primary section-action-btn ms-2" data-bs-toggle="modal" data-bs-target="#loginModal"><i class="bi bi-calendar-plus"></i><span>Set Activity</span></button>
          <?php endif; ?>
          <button id="showDivisionUsersBtn" class="btn btn-outline-secondary section-action-btn ms-2" data-division="Business Development Division"><i class="bi bi-people"></i><span>Users</span></button>
        </div>
        <div class="mt-3 p-3 rounded" style="background: var(--card-bg); border: 1px solid var(--surface-contrast);">
          <div id="divisionCalendar" data-division="Business Development Division"></div>
        </div>
      </div>
    </div>
    <?php include __DIR__ . '/includes/event_details_modal.php'; ?>
    <?php include __DIR__ . '/includes/activity_modal.php'; ?>
    <?php include __DIR__ . '/includes/division_users_modal.php'; ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="assets/calendar-embed.js"></script>
    <script src="assets/script.js?v=20260407k2"></script>
  </body>
</html>












