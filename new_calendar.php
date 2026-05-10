<?php
require_once __DIR__ . '/includes/init.php';
$user = $_SESSION['user'] ?? null;
$csrf = $_SESSION['csrf_token'] ?? '';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>New Indicative Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
  </head>
  <body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <div class="container py-4">
      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap calendar-page-toolbar mb-3">
        <h3 class="mb-0">New Indicative Calendar</h3>
        <div>
          <?php if ($user): ?>
            <button id="setActivityBtn" class="btn btn-primary">Set Activity</button>
          <?php else: ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Set Activity</button>
          <?php endif; ?>
        </div>
      </div>

      <div class="mb-3 p-3 rounded" style="background: var(--card-bg); border: 1px solid var(--surface-contrast);">
        <div class="calendar-layout">
          <aside class="calendar-legend-panel">
            <strong>Division Legend</strong>
            <div class="calendar-legend-list">
              <div class="legend-item"><div class="legend-color" style="background-color: #2563EB;"></div> Admin Division</div>
              <div class="legend-item"><div class="legend-color" style="background-color: #DC3545;"></div> Office of the Provincial Director</div>
              <div class="legend-item"><div class="legend-color" style="background-color: #198754;"></div> Consumer Protection Division</div>
              <div class="legend-item"><div class="legend-color" style="background-color: #FF6B35;"></div> Business Development Division</div>
              <div class="legend-item"><div class="legend-color" style="background-color: #6F42C1;"></div> Planning Unit</div>
            </div>
          </aside>
            <div id="divisionCalendar"></div>
        </div>
      </div>
    </div>

    <?php include __DIR__ . '/includes/event_details_modal.php'; ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="assets/calendar-embed.js"></script>
    <script src="assets/script.js?v=20260407k2"></script>
  </body>
</html>











