<?php
require_once __DIR__ . '/includes/init.php';
$user = $_SESSION['user'] ?? null;
$csrf = $_SESSION['csrf_token'] ?? '';

$freedomWallPosts = [];
try {
  $mysqli->query("DELETE FROM freedom_wall_posts WHERE created_at < (NOW() - INTERVAL 8 HOUR)");
  $wallResult = $mysqli->query("SELECT message, created_at FROM freedom_wall_posts WHERE DATE(created_at) = CURRENT_DATE() ORDER BY created_at DESC, id DESC");
  if ($wallResult) {
    while ($wallRow = $wallResult->fetch_assoc()) {
      $freedomWallPosts[] = $wallRow;
    }
  }
} catch (Throwable $e) {
  $freedomWallPosts = [];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Indicative Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
      #calendar { width: 100%; margin: 0; }
      .fc-event { cursor: pointer; }
      .fc-daygrid-event { border-radius: 6px; }
      .fc-daygrid-event .fc-event-main { color: white !important; }
      .fc-daygrid-event .fc-event-title { color: white !important; font-weight: 500; }
      .fc-daygrid-event .fc-event-title small { color: rgba(255,255,255,0.9) !important; }
      .calendar-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 280px;
        gap: 16px;
        align-items: start;
      }
      .calendar-side-column {
        display: flex;
        flex-direction: column;
        gap: 12px;
      }
      .calendar-legend-panel {
        border: 1px solid rgba(15,23,42,0.12);
        border-radius: 10px;
        background: var(--surface);
        padding: 12px;
      }
      .freedom-wall-panel {
        border: 1px solid var(--surface-contrast);
        border-radius: 10px;
        background: var(--card-bg);
        padding: 12px;
        color: var(--text);
      }
      .freedom-wall-header {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        align-items: flex-start;
      }
      .freedom-wall-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 8px;
        max-height: 430px;
        overflow-y: auto;
        padding-right: 4px;
      }
      .freedom-wall-item {
        border: 1px solid var(--surface-contrast);
        border-radius: 10px;
        padding: 10px 12px;
        background: var(--surface);
      }
      .freedom-wall-item .freedom-wall-meta {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: 0.78rem;
        color: var(--muted);
        margin-bottom: 4px;
      }
      .freedom-wall-item .freedom-wall-message {
        font-size: 0.92rem;
        line-height: 1.35;
      }
      .freedom-wall-empty {
        color: var(--muted);
        font-size: 0.92rem;
      }
      .calendar-legend-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 8px;
      }
      .calendar-legend-list .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
      }
      @media (max-width: 991.98px) {
        .calendar-layout {
          grid-template-columns: 1fr;
        }
      }
      .legend-item { display: inline-flex; align-items: center; gap: 8px; margin-right: 20px; margin-bottom: 10px; }
      .legend-color { width: 20px; height: 20px; border-radius: 4px; }
      .creator-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #dee2e6;
        background: var(--surface);
      }
      .fc-event-time::before { display: none; }
      .fc-event-time { display: none; }
      .passenger-row { display: flex; gap: 8px; margin-bottom: 8px; }
      .passenger-row .form-control { flex: 1; }
      #passengersWrap {
        max-height: 220px;
        overflow-y: auto;
        padding-right: 4px;
      }
      .vehicle-date-input.date-target-active {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.2);
      }
      .vehicle-calendar-wrap {
        border: 1px solid rgba(15,23,42,0.12);
        border-radius: 10px;
        padding: 10px;
        background: var(--surface);
      }
      /* (inventory page handles variant listbox styling) */
      #vehicleModal .modal-dialog {
        max-width: 100vw;
        width: 100vw;
        height: 100vh;
        height: 100dvh;
        margin: 0;
      }
      #vehicleModal .modal-content {
        height: 100vh;
        height: 100dvh;
        display: flex;
        flex-direction: column;
      }
      #vehicleModal #vehicleForm {
        height: 100%;
        display: flex;
        flex-direction: column;
        min-height: 0;
      }
      #vehicleModal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
      }
      #vehicleModal .vehicle-form-horizontal .row.g-3 > [class*="col-"] {
        display: grid;
        grid-template-columns: 170px 1fr;
        align-items: start;
        gap: 10px;
      }
      #vehicleModal .vehicle-form-horizontal .form-label {
        margin-bottom: 0;
        text-align: left;
        font-weight: 600;
      }
      #vehicleModal .vehicle-form-horizontal .form-control,
      #vehicleModal .vehicle-form-horizontal .form-select,
      #vehicleModal .vehicle-form-horizontal textarea,
      #vehicleModal .vehicle-form-horizontal .form-text,
      #vehicleModal .vehicle-form-horizontal .invalid-feedback {
        text-align: left;
      }
      #vehicleModal .vehicle-form-horizontal .invalid-feedback,
      #vehicleModal .vehicle-form-horizontal .form-text,
      #vehicleModal .vehicle-form-horizontal datalist {
        grid-column: 2;
      }
      #vehicleModal .vehicle-modal-layout .vehicle-calendar-panel {
        position: sticky;
        top: 0;
      }
      @media (max-width: 991.98px) {
        #vehicleModal .modal-dialog { max-width: 100%; }
        #vehicleModal .vehicle-form-horizontal .row.g-3 > [class*="col-"] {
          display: block;
        }
        #vehicleModal .vehicle-form-horizontal .form-label {
          text-align: left;
          margin-bottom: 0.35rem;
        }
        #vehicleModal .vehicle-modal-layout .vehicle-calendar-panel {
          position: static;
        }
      }
      #vehicleMiniCalendar .fc-toolbar-title { font-size: 1rem; }
      #vehicleMiniCalendar .fc-button { padding: 0.2rem 0.45rem; font-size: 0.82rem; }
      .vehicle-date-targets { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
      .vehicle-date-target-btn.active {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
      }
      .ob-slip-sheet {
        border: 1px solid var(--surface-contrast);
        border-radius: 8px;
        padding: 14px;
        background: var(--surface);
        color: var(--text);
      }
      .ob-slip-sheet .col-md-3.fw-semibold { color: var(--muted); }
      .ob-slip-head {
        text-align: center;
        margin-bottom: 10px;
        line-height: 1.25;
      }
      .ob-slip-title {
        font-weight: 700;
        letter-spacing: 0.04em;
      }
      .ob-slip-line {
        border-bottom: 1px solid var(--surface-contrast);
        min-height: 30px;
        display: flex;
        align-items: end;
        padding: 0 4px 2px;
      }
      .ob-slip-time-table {
        width: 100%;
        border: 1px solid #adb5bd;
        border-collapse: collapse;
      }
      .ob-slip-time-table th,
      .ob-slip-time-table td {
        border: 1px solid #adb5bd;
        padding: 6px 8px;
        vertical-align: middle;
      }
      .ob-slip-sign-label {
        margin-top: 14px;
        font-weight: 700;
      }
      .ob-slip-sign-line {
        border-bottom: 1px solid var(--surface-contrast);
        min-height: 34px;
        position: relative;
      }
      .ob-slip-sign-line.with-name {
        border-bottom: 0;
        display: flex;
        align-items: flex-end;
        gap: 0;
      }
      .ob-slip-sign-line.with-name::before,
      .ob-slip-sign-line.with-name::after {
        content: '';
        flex: 1 1 auto;
        border-bottom: 1px solid var(--surface-contrast);
        margin-bottom: 0;
      }
      .ob-slip-sign-caption {
        text-align: center;
        font-weight: 700;
        margin-top: 4px;
        line-height: 1.2;
      }
      .ob-slip-sign-name {
        position: static;
        font-weight: 700;
        letter-spacing: 0.08em;
        background: transparent;
        padding: 0 4px;
        border-bottom: 1px solid var(--surface-contrast);
        line-height: 1.1;
        white-space: nowrap;
        margin-bottom: 0;
      }
      .ob-slip-rev {
        text-align: right;
        font-weight: 700;
        margin-top: 8px;
      }
      #obSlipModal .modal-dialog {
        max-width: 860px;
        height: calc(100vh - 1rem);
        margin: 0.5rem auto;
      }
      #obSlipModal .modal-content {
        height: 100%;
        display: flex;
        flex-direction: column;
      }
      #obSlipModal #obSlipForm {
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
      }
      #obSlipModal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
      }
      @media (max-width: 575.98px) {
        #obSlipModal .modal-dialog {
          height: calc(100vh - 0.5rem);
          margin: 0.25rem auto;
        }
      }
      /* Ensure OB Slip button remains visible in dark mode */
      #obSlipBtn.btn-outline-dark {
        color: var(--text) !important;
        border-color: var(--btn-border) !important;
        background: transparent !important;
      }
      .dark-mode #obSlipBtn.btn-outline-dark {
        color: var(--text) !important;
        border-color: var(--btn-border) !important;
      }
      .dark-mode #obSlipBtn.btn-outline-dark:hover {
        background: rgba(255,255,255,0.03) !important;
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <div class="container py-4">
      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap calendar-page-toolbar mb-3">
        <h3 class="mb-0">Indicative Calendar</h3>
        <div>
          <?php if ($user): ?>
            <button id="supplyBtn" class="btn btn-supply me-2">Supply</button>
            <button id="obSlipBtn" class="btn btn-outline-dark me-2">OB Slip</button>
            <button id="vehicleBtn" class="btn btn-outline-primary me-2">Vehicle Request</button>
            <button id="setActivityBtn" class="btn btn-primary">Set Activity</button>
          <?php else: ?>
            <button class="btn btn-supply me-2" data-bs-toggle="modal" data-bs-target="#loginModal">Supply</button>
            <button class="btn btn-outline-dark me-2" data-bs-toggle="modal" data-bs-target="#loginModal">OB Slip</button>
            <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#loginModal">Vehicle Request</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Set Activity</button>
          <?php endif; ?>
        </div>
      </div>

      <div class="mb-3 p-3 rounded" style="background: var(--card-bg); border: 1px solid var(--surface-contrast);">
        <div class="calendar-layout">
          <div>
            <div id="calendar"></div>
          </div>
          <div class="calendar-side-column">
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
            <aside class="freedom-wall-panel">
              <div class="freedom-wall-header">
                <strong>Freedom Wall</strong>
                <?php if ($user): ?>
                  <button type="button" id="freedomWallBtn" class="btn btn-sm btn-outline-primary">Write Thought</button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Write Thought</button>
                <?php endif; ?>
              </div>
              <div class="freedom-wall-list">
                <?php if (!empty($freedomWallPosts)): ?>
                  <?php foreach ($freedomWallPosts as $post): ?>
                    <div class="freedom-wall-item">
                      <div class="freedom-wall-meta">
                        <span>Anonymous</span>
                        <span><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($post['created_at'] ?? 'now'))); ?></span>
                      </div>
                      <div class="freedom-wall-message"><?php echo nl2br(htmlspecialchars($post['message'] ?? '')); ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="freedom-wall-empty">Write the first thought for the calendar side.</div>
                <?php endif; ?>
              </div>
            </aside>
          </div>
        </div>
      </div>
    </div>

    <!-- Vehicle Request Modal -->
    <div class="modal fade" id="vehicleModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
          <form id="vehicleForm" class="needs-validation vehicle-form-horizontal" novalidate>
            <div class="modal-header">
              <h5 class="modal-title">Vehicle Request</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
              <div id="vehicleAlert"></div>

              <div class="row g-4 vehicle-modal-layout">
                <div class="col-lg-7">

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Date of Application</label>
                  <input id="dateApplication" name="date_application" type="date" class="form-control" required readonly>
                  <div class="invalid-feedback">Date of application is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Date of Use</label>
                  <input id="vehicleDateUse" name="date_use" type="text" class="form-control vehicle-date-input" placeholder="YYYY-MM-DD" autocomplete="off" readonly required>
                  <div class="invalid-feedback">Date of use is required.</div>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-12">
                  <label class="form-label">Vehicle Plate No.</label>
                  <input name="vehicle_plate_no" type="text" class="form-control" list="vehiclePlateList" placeholder="e.g. ABC-1234" required>
                  <datalist id="vehiclePlateList">
                    <option value="ABC-1234"></option>
                    <option value="XYZ-5678"></option>
                    <option value="DEF-9012"></option>
                  </datalist>
                  <div class="invalid-feedback">Vehicle plate no. is required.</div>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-6">
                  <label class="form-label">Departure Date</label>
                  <input id="vehicleDepartureDate" name="departure_date" type="text" class="form-control vehicle-date-input" placeholder="YYYY-MM-DD" autocomplete="off" readonly required>
                  <div class="invalid-feedback">Departure date is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Departure Time</label>
                  <input id="vehicleDepartureTime" name="departure_time" type="time" class="form-control" required>
                  <div class="invalid-feedback">Departure time is required.</div>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-6">
                  <label class="form-label">Expected Arrival Date</label>
                  <input id="vehicleExpectedArrivalDate" name="expected_arrival_date" type="text" class="form-control vehicle-date-input" placeholder="YYYY-MM-DD" autocomplete="off" readonly required>
                  <div class="invalid-feedback">Expected arrival date is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Expected Arrival Time</label>
                  <input id="vehicleExpectedArrivalTime" name="expected_arrival_time" type="time" class="form-control" required>
                  <div class="invalid-feedback">Expected arrival time is required.</div>
                </div>
                
              </div>

              <div class="mt-3">
                <label class="form-label">Authorized Passengers</label>
                <div id="passengersWrap"></div>
                <button type="button" id="addPassengerBtn" class="btn btn-outline-secondary btn-sm mt-2">Add Passenger</button>
                <div class="form-text">At least one passenger is required.</div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-6">
                  <label class="form-label">Purpose</label>
                  <input name="purpose" type="text" class="form-control" required>
                  <div class="invalid-feedback">Purpose is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Destination</label>
                  <input name="destination" type="text" class="form-control" required>
                  <div class="invalid-feedback">Destination is required.</div>
                </div>
              </div>

              <div class="row g-3 mt-1">
                 <div class="col-md-6">
                  <label class="form-label">Driver Name</label>
                  <input name="driver_name" type="text" class="form-control" required>
                  <div class="invalid-feedback">Driver name is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Transportation Incharge</label>
                  <input name="transportation_incharge" type="text" class="form-control" required>
                  <div class="invalid-feedback">Transportation incharge is required.</div>
                </div>

              </div>

              <div class="row g-3 mt-3">
                <div class="col-12 d-flex gap-2 justify-content-end">
                  <button type="button" id="vehicleCancelBtn" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
              </div>

                </div>

                <div class="col-lg-5">
                  <div class="vehicle-calendar-panel">
                    <label class="form-label">Calendar Picker</label>
                    <div class="vehicle-date-targets">
                      <button type="button" class="btn btn-outline-primary btn-sm vehicle-date-target-btn" data-target-date="vehicleDateUse">Select Date of Use</button>
                      <button type="button" class="btn btn-outline-success btn-sm vehicle-date-target-btn" data-target-date="vehicleDepartureDate" data-target-time="vehicleDepartureTime">Select Departure Date &amp; Time</button>
                      <button type="button" class="btn btn-outline-danger btn-sm vehicle-date-target-btn" data-target-date="vehicleExpectedArrivalDate" data-target-time="vehicleExpectedArrivalTime">Select Arrival Date &amp; Time</button>
                    </div>
                    <div class="vehicle-calendar-wrap">
                      <div id="vehicleMiniCalendar"></div>
                    </div>
                    <div class="form-text">Click a selection button, then choose a date in the calendar. Departure and arrival buttons will open their time input next.</div>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Supply Modal -->
    <div class="modal fade" id="supplyModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="supplyForm" class="needs-validation" novalidate>
            <div class="modal-header">
              <h5 class="modal-title">Supply Request</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
              <div id="supplyAlert"></div>
              <div class="mb-3">
                <label class="form-label">Item</label>
                <select name="item" id="supplyItem" class="form-select" required>
                  <option value="">Choose...</option>
                  <option>Bond Paper</option>
                  <option>Photo Paper</option>
                  <option>Folder</option>
                  <option>Envelope</option>
                  <option>Stapler</option>
                  <option>Staples</option>
                  <option>Ballpen</option>
                  <option>Gel Pen</option>
                  <option>Sticker Paper</option>
                  <option>Stamp Pad</option>
                  <option>Correction Tape</option>
                  <option>Binder Clip</option>
                  <option>Fastener</option>
                  <option>Tissue</option>
                  <option>Acetate</option>
                  <option>Air Freshener</option>
                  <option>Alcohol</option>
                  <option>Battery</option>
                  <option>Binding and Punching Machine</option>
                  <option>Binding Ring/Com</option>
                  <option>Broom</option>
                  <option>Calculator</option>
                  <option>Carbon Film</option>
                  <option>Cartolina</option>
                  <option>Chalk</option>
                  <option>Cleaner</option>
                  <option>Cleanser</option>
                  <option>Clearbook</option>
                  <option>Clip</option>
                  <option>Computer Continuous Form</option>
                  <option>Computer Mouse</option>
                  <option>Cutter/Utility Knife</option>
                  <option>Data File Box</option>
                  <option>Data Folder</option>
                  <option>Dater Stamp</option>
                  <option>Detergent Powder</option>
                  <option>Digital Voice Recorder</option>
                  <option>Disinfectant Spray</option>
                  <option>Drum Cart</option>
                  <option>Dustpan</option>
                  <option>Index Tab</option>
                  <option>Ink Cartridge</option>
                  <option>Eraser</option>
                  <option>External Hard Drive</option>
                  <option>File Organizer</option>
                  <option>File Tab Divider</option>
                  <option>Furniture Cleaner</option>
                  <option>Glue</option>
                  <option>Hand Sanitizer</option>
                  <option>Hand Soap</option>
                  <option>Notepad</option>
                  <option>Pad Paper</option>
                  <option>Paper Clip</option>
                  <option>Paper Shedder</option>
                  <option>Paper Timmer/Cutting Machine</option>
                  <option>Paper</option>
                  <option>Marker</option>
                  <option>Pencil Sharpener</option>
                  <option>Record Book</option>
                  <option>Ribbon Cart</option>
                  <option>Ribbon Cartridge</option>
                  <option>Rubber Band</option>
                  <option>Ruler</option>
                  <option>Scissors</option>
                  <option>Scouring Pad</option>
                  <option>Sign Pen</option>
                  <option>Staple Remover</option>
                  <option>Staple Wire</option>
                  <option>Steno Notebook</option>
                  <option>Stapler</option>
                  <option>Tape</option>
                  <option>Toner Cart</option>
                  <option>Toner Cartridge</option>
                  <option>Trashbag</option>
                  <option>Twine</option>
                  <option>Wrapping Paper</option>
                  <option>Others</option>
                </select>
                <div class="invalid-feedback">Please select an item.</div>
              </div>
              <div class="mb-3 d-none" id="otherItemWrap">
                <label class="form-label">Other Item</label>
                <input id="otherItemInput" type="text" class="form-control" placeholder="Enter other item">
                <div class="invalid-feedback">Please enter the item name.</div>
              </div>
              <div class="mb-3" id="supplyVariantWrap">
                <label class="form-label">Variant / Type</label>
                <select name="variant" id="supplyVariant" class="form-select" required>
                  <option value="">Choose...</option>
                  <option>Short</option>
                  <option>Long</option>
                  <option>A4</option>
                  <option>Expanding</option>
                </select>
                <div class="invalid-feedback">Variant/type is required.</div>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Quantity</label>
                  <input name="quantity" type="number" min="1" class="form-control" required>
                  <div class="invalid-feedback">Quantity must be a positive number.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Unit</label>
                  <select name="unit" class="form-select" required>
                    <option value="">Choose...</option>
                    <option>piece</option>
                    <option>pieces</option>
                    <option>box</option>
                    <option>boxes</option>
                    <option>pack</option>
                    <option>packs</option>
                    <option>bottle</option>
                    <option>bottles</option>
                    <option>ream</option>
                    <option>reams</option>
                    <option>roll</option>
                    <option>can</option>
                    <option>gallon</option>
                    <option>bundle</option>
                    <option>tube</option>
                    <option>pouch</option>
                    <option>cart</option>
                    <option>set</option>
                    <option>jar</option>
                    <option>pad</option>
                    <option>book</option>
                  </select>
                  <div class="invalid-feedback">Unit is required.</div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Request Supply</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Activity Modal -->
    <div class="modal fade" id="obSlipModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <form id="obSlipForm" class="needs-validation" novalidate>
            <div class="modal-header">
              <h5 class="modal-title">OB Slip</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
              <div id="obSlipAlert"></div>

              <div class="ob-slip-sheet">
                <div class="ob-slip-head small">
                  <div>Republic of the Philippines</div>
                  <div>Department of Trade and Industry</div>
                  <div>Nueva Vizcaya Provincial Office</div>
                  <div class="ob-slip-title mt-2">OB SLIP</div>
                </div>

                <div class="d-flex justify-content-center gap-4 mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="ob_type" id="obTypeOfficial" value="OFFICIAL" required>
                    <label class="form-check-label fw-semibold" for="obTypeOfficial">Official</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="ob_type" id="obTypePersonal" value="PERSONAL" required>
                    <label class="form-check-label fw-semibold" for="obTypePersonal">Personal</label>
                  </div>
                </div>
                <div class="invalid-feedback d-block mb-2" id="obTypeError" style="display:none !important;">Please select Official or Personal.</div>

                <div class="row g-2 align-items-end mb-2">
                  <div class="col-md-3 fw-semibold">DATE:</div>
                  <div class="col-md-9"><input type="date" name="date" class="form-control" required></div>
                </div>
                <div class="row g-2 align-items-end mb-2">
                  <div class="col-md-3 fw-semibold">NAME:</div>
                  <div class="col-md-9"><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?>" required></div>
                </div>
                <div class="row g-2 align-items-end mb-2">
                  <div class="col-md-3 fw-semibold">SECTION:</div>
                  <div class="col-md-9"><input type="text" name="section" class="form-control" value="<?php echo htmlspecialchars((string)($user['division'] ?? '')); ?>" required></div>
                </div>

                <div class="row g-2 mb-2">
                  <div class="col-md-3 fw-semibold pt-2">PURPOSE:</div>
                  <div class="col-md-9"><textarea name="purpose" class="form-control" rows="3" required></textarea></div>
                </div>

                <div class="row g-2 mb-3">
                  <div class="col-md-3 fw-semibold pt-2">DESTINATION:</div>
                  <div class="col-md-9"><textarea name="destination" class="form-control" rows="3" required></textarea></div>
                </div>

                <table class="ob-slip-time-table mb-3">
                  <thead>
                    <tr>
                      <th style="width:55%;">TIME</th>
                      <th style="width:45%;">&nbsp;</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="fw-semibold">DEPARTURE IN THE OFFICE</td>
                      <td><input type="time" name="departure_time" class="form-control form-control-sm" required></td>
                    </tr>
                    <tr>
                      <td class="fw-semibold">RETURN IN THE OFFICE</td>
                      <td><input type="time" name="return_time" class="form-control form-control-sm" required></td>
                    </tr>
                  </tbody>
                </table>

                <div class="ob-slip-sign-label">APPROVED BY:</div>
                <div class="ob-slip-sign-line with-name">
                  <span class="ob-slip-sign-name">LENORE LEE S. LOPEZ</span>
                </div>
                <div class="ob-slip-sign-caption">SIGNATURE OVER PRINTED NAME OF AUTHORIZED<br>SIGNATORY</div>

                <div class="ob-slip-sign-label">ATTESTED BY:</div>
                <div class="ob-slip-sign-line"></div>
                <div class="ob-slip-sign-caption">GUARD ON DUTY</div>
                <div class="ob-slip-rev">Rev.11-28-18</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Save OB Slip</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Activity Modal -->
    <div class="modal fade" id="activityModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="activityForm" class="needs-validation" novalidate>
            <div class="modal-header">
              <h5 class="modal-title">Set Activity</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
              <div id="activityAlert"></div>
              <div class="mb-3">
                <label class="form-label">Purpose</label>
                <input name="purpose" type="text" class="form-control" required>
                <div class="invalid-feedback">Purpose is required.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Destination</label>
                <input name="destination" type="text" class="form-control" required>
                <div class="invalid-feedback">Destination is required.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Start Date and Time</label>
                <input name="start_datetime" type="datetime-local" class="form-control" required>
                <div class="invalid-feedback">Start date/time is required.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">End Date and Time</label>
                <input name="end_datetime" type="datetime-local" class="form-control" required>
                <div class="invalid-feedback">End date/time is required.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Save Activity</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="eventTitle">Activity Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex align-items-center gap-3 mb-3 p-2 border rounded">
              <img id="evCreatorAvatar" class="creator-avatar" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="Creator Avatar">
              <div>
                <div class="fw-semibold" id="evCreatorName">Unknown User</div>
                <div class="text-muted small" id="evCreatorDivision">No division</div>
              </div>
            </div>
            <p><strong>Purpose:</strong> <span id="evPurpose"></span></p>
            <p><strong>Destination:</strong> <span id="evDestination"></span></p>
            <p><strong>Start:</strong> <span id="evStart"></span></p>
            <p><strong>End:</strong> <span id="evEnd"></span></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Freedom Wall Modal -->
    <div class="modal fade" id="freedomWallModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="freedomWallForm" class="needs-validation" novalidate>
            <div class="modal-header">
              <h5 class="modal-title">Write a Thought</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
              <div id="freedomWallAlert"></div>
              <div class="mb-3">
                <label for="freedomWallMessage" class="form-label">Message</label>
                <textarea id="freedomWallMessage" name="message" class="form-control" rows="5" maxlength="500" placeholder="Share a quick reminder, thought, or update" required></textarea>
                <div class="form-text">Keep it short and useful. Maximum 500 characters.</div>
                <div class="invalid-feedback">Please enter a message.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Post Thought</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
          var calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'dayGridMonth',
          eventDisplay: 'block',
          headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
          // Load events but exclude vehicle events from the main indicative calendar
          events: function(fetchInfo, successCallback, failureCallback) {
            fetch('api/events.php').then(function(res){ return res.json(); }).then(function(data){
              if (!Array.isArray(data)) return successCallback([]);
              var filtered = data.filter(function(evt){
                try {
                  var props = evt.extendedProps || {};
                  if (props.event_type === 'vehicle' || evt.groupId === 'vehicle') return false;
                } catch (e) {
                  // on error, keep the event
                }
                return true;
              });
              successCallback(filtered);
            }).catch(function(err){ failureCallback(err); });
          },
          eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
          eventDidMount: function(info) {
            // Ensure month-view tiles use full division color, not only dot indicators.
            if (info.event.backgroundColor) {
              info.el.style.backgroundColor = info.event.backgroundColor;
              info.el.style.borderColor = info.event.backgroundColor;
            }
            var props = info.event.extendedProps || {};
            var avatarUrl = props.creator_avatar || 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
            var titleEl = info.el.querySelector('.fc-event-title');
            if (titleEl) {
              var avatarImg = '<img src="' + avatarUrl + '" style="display: inline-block; width: 24px; height: 24px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.7); flex-shrink: 0;">';
              var startStr = info.event.start ? info.event.start.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false }) : '';
              var endStr = info.event.end ? info.event.end.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false }) : '';
              var timeStr = startStr;
              if (startStr && endStr) {
                timeStr = startStr + ' - ' + endStr;
              }
              titleEl.innerHTML = '<div style="display: flex; align-items: center; gap: 6px;">' + avatarImg + '<div style="text-align: center; flex: 1;">' + info.event.title + '<br><small style="opacity: 0.85;">' + timeStr + '</small></div></div>';
              titleEl.style.padding = '4px 2px';
            }
          },
          eventClick: function(info) {
            var ev = info.event;
            var props = ev.extendedProps || {};
            var defaultAvatar = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

            document.getElementById('eventTitle').textContent = ev.title || 'Activity Details';
            document.getElementById('evPurpose').textContent = ev.title || '';
            document.getElementById('evDestination').textContent = props.destination || '';
            document.getElementById('evStart').textContent = ev.start ? ev.start.toLocaleString() : '';
            document.getElementById('evEnd').textContent = ev.end ? ev.end.toLocaleString() : '';
            document.getElementById('evCreatorName').textContent = props.creator_name || 'Unknown User';
            document.getElementById('evCreatorDivision').textContent = props.creator_division || 'No division';
            document.getElementById('evCreatorAvatar').src = props.creator_avatar || defaultAvatar;

            var modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
            modal.show();
          }
        });
        calendar.render();

        // Listen for vehicle approval events from other tabs and refetch
        window.addEventListener('storage', function(e){
          if (!e) return;
          try {
            if (e.key === 'vehicle_approved') {
              // another tab approved a vehicle request â€” refresh main calendar
              calendar.refetchEvents();
              // also refresh the vehicle mini-calendar inside the modal if available
              if (typeof loadApprovedVehiclesIntoMiniCalendar === 'function') {
                loadApprovedVehiclesIntoMiniCalendar();
              }
            }
          } catch (err) {
            // ignore
          }
        });

        // Open Set Activity modal
        var setBtn = document.getElementById('setActivityBtn');
        if (setBtn) setBtn.addEventListener('click', function(){
          var modal = new bootstrap.Modal(document.getElementById('activityModal'));
          modal.show();
        });

        // Open Vehicle Request modal
        var vehicleBtn = document.getElementById('vehicleBtn');
        if (vehicleBtn) vehicleBtn.addEventListener('click', function(){
          var modal = new bootstrap.Modal(document.getElementById('vehicleModal'));
          modal.show();
        });

        var freedomWallBtn = document.getElementById('freedomWallBtn');
        if (freedomWallBtn) freedomWallBtn.addEventListener('click', function(){
          var modal = new bootstrap.Modal(document.getElementById('freedomWallModal'));
          modal.show();
        });

        // Handle form submit via fetch
        var form = document.getElementById('activityForm');
        form.addEventListener('submit', function(e){
          e.preventDefault();
          if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
          var fd = new FormData(form);
          fetch('api/activity_create.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
              var alertEl = document.getElementById('activityAlert');
              alertEl.innerHTML = '';
              if (data.success) {
                var modalEl = document.getElementById('activityModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();
                calendar.refetchEvents();
              } else {
                alertEl.innerHTML = '<div class="alert alert-danger">'+(data.message||'Error')+'</div>';
              }
            }).catch(err => {
              document.getElementById('activityAlert').innerHTML = '<div class="alert alert-danger">Network error</div>';
            });
        });

            // Supply modal handling
            var supplyBtn = document.getElementById('supplyBtn');
            if (supplyBtn) supplyBtn.addEventListener('click', function(){
              var modal = new bootstrap.Modal(document.getElementById('supplyModal'));
              modal.show();
            });

            var supplyItem = document.getElementById('supplyItem');
            var otherItemWrap = document.getElementById('otherItemWrap');
            var otherItemInput = document.getElementById('otherItemInput');
            var supplyVariantWrap = document.getElementById('supplyVariantWrap');
            var supplyVariant = document.getElementById('supplyVariant');
              function toggleOtherItem() {
              var val = supplyItem ? supplyItem.value : '';
              var isOthers = val === 'Others';

              // Items that require a variant/type and their options
              var variantMap = {
                'Bond Paper': ['A4','Short','Long'],
                'Photo Paper': ['4x6','5x7','A4'],
                'Folder': ['Expanding','Letter','Legal','With Tab A4','With Tab Legal','Fancy A4','L-type A4','L-type Legal','Morocco with Slide Legal','Pressboard'],
                'Envelope': ['Business','A4','CD','Documentary A4','Documentary Legal','Expanding Kraft','Expanding Plastic','Mailing','Mailing with window','Mailing White'],
                'Sticker Paper': ['Matte','Glossy','A4'],
                'Alcohol': ['Ethyl'],
                'Battery': ['Dry Cell'],
                'Binding and Punching Machine': ['50mm binding capacity'],
                'Binding Ring/Com': ['Plastic, 32mm'],
                'Broom': ['Walis Tingting','Walis Tambo'],
                'Calculator': ['Compact'],
                'Carbon Film': ['Legal size'],
                'Cartolina': ['Assorted colors'],
                'Chalk': ['White Enamel'],
                'Cleaner': ['Toilet and Urinal'],
                'Cleanser': ['Scouring Powder'],
                'Clearbook': ['Legal size','A4'],
                'Clip': ['Backfold 19mm','Backfold 25mm','Backfold 32mm','Backfold 50mm'],
                'Computer Continuous Form': ['280mm x 241mm','280mm x 378mm'],
                'Computer Mouse': ['Wireless'],
                'Detergent Powder': ['All-purpose'],
                'Drum Cart': ['Brother DR-3455 Black'],
                'Index Tab': [],
                'Ink Cartridge': [
                  'Canon CL-811 Colored',
                  'Canon PG-810 Black',
                  'EPSON C13T664100 (T6641) BLACK',
                  'EPSON C13T664200 (T6642) CYAN',
                  'EPSON C13T664300 (T6643) MAGENTA',
                  'EPSON C13T664400 (T6644) YELLOW',
                  'HP C2P04AA (HP62) BLACK',
                  'HP C2P06AA (HP62) TRI-COLOR',
                  'HP CC640WA (HP60) BLACK',
                  'HP CC643WA (HP60) TRI-COLOR',
                  'HP CD888AA (HP703) TRI-COLOR',
                  'HP CH561WA (HP61) BLACK',
                  'HP CH562WA (HP61) TRI-COLOR',
                  'HP CN045AA (HP950XL) BLACK',
                  'HP CN046AA (HP951XL) CYAN',
                  'HP CN047AA (HP951XL) MAGENTA',
                  'HP CN048AA (HP951XL) YELLOW',
                  'HP CN692AA (HP704) BLACK',
                  'HP CN693AA (HP704) TRI-COLOR',
                  'HP CZ107AA (HP678) BLACK',
                  'HP CZ108AA (HP678) TRI-COLOR',
                  'HP F6V26AA (HP680) TRI-COLOR',
                  'HP F6V27AA (HP680) BLACK',
                  'HP L0S51AA (HP955) CYAN ORIGINAL',
                  'HP L0S54AA (HP955) MAGENTA ORIGINAL',
                  'HP L0S57AA (HP955) YELLOW ORIGINAL',
                  'HP L0S60AA (HP955) BLACK ORIGINAL',
                  'HP L0S63AA (HP955XL) CYAN ORIGINAL',
                  'HP L0S66AA (HP955XL) MAGENTA',
                  'HP L0S69AA (HP955XL) YELLOW',
                  'HP L0S72AA (HP955XL) BLACK ORIGINAL',
                  'HP T6L89AA (HP905) CYAN ORIGINAL',
                  'HP T6L93AA (HP905) MAGENTA ORIGINAL',
                  'HP T6L97AA (HP905) YELLOW ORIGINAL',
                  'HP T6M01AA (HP905) BLACK ORIGINAL'
                ],
                'Eraser': ['Felt Blackboard/Whiteboard','Plastic/Rubber'],
                'Marker': ['Fluorescent','Permanent Black','Permanent Blue','Permanent Red','Whiteboard Black','Whiteboard Blue','Whiteboard Red'],
                'Staple Remover': ['Plier type'],
                'Staple Wire': ['Heavy Duty (Binder) 23/13','Standard'],
                'Steno Notebook': [],
                'Stapler': ['Heavy Duty (Binder)','Standard Type'],
                'External Hard Drive': [],
                'Fastener': ['Metal'],
                'File Organizer': [],
                'File Tab Divider': ['A4','Legal'],
                'Furniture Cleaner': [],
                'Glue': ['All purpose'],
                'Hand Sanitizer': ['500ml'],
                'Hand Soap': ['Liquid 500ml'],
                'Notepad': ['Stick-on 76mm x 100','Stick-on 50mm x 76mm','76mm x 76mm'],
                'Pad Paper': ['Ruled'],
                'Paper Clip': ['Vinyl/Plastic Coated 33mm','Vinyl/Plastic Coated Jumbo 50mm'],
                'Paper Shedder': [],
                'Paper Timmer/Cutting Machine': [],
                'Paper': ['Multi-purpose 70gsm (min.) Legal','Multi-Purpose A4','MULTICOPY A4','MULTICOPY Legal','Parchment'],
                'Pencil Sharpener': [],
                'Record Book': ['300 pages','500 pages'],
                'Ribbon Cart': ['EPSON C13S015516 (#8750) Black','EPSON C13S015632 Black'],
                'Ribbon Cartridge': ['EPSON C13S015531 (S015086)'],
                'Rubber Band': [],
                'Ruler': ['Plastic 450mm'],
                'Scissors': ['Symmetrical','Asymmetrical'],
                'Scouring Pad': [],
                'Sign Pen': ['Extra Fine Tip Black','Extra Fine Tip Blue','Extra Fine Tip Red','Fine Tip Black','Fine Tip Blue','Fine Tip Red','Medium Tip Black','Medium Tip Blue','Medium Tip Red'],
                'Stamp Pad': ['Felt','Ink']
                ,
                'Tape': ['electrical','masking 24mm','masking 48mm','packaging 48mm','transparent 24mm','transparent 48mm'],
                'Tissue': ['interfolded paper towel','toilet tissue paper 2ply'],
                'Toner Cart': [
                  'BROTHER TN-2130 Black',
                  'BROTHER TN-3320 Black',
                  'BROTHER TN-3350 Black',
                  'BROTHER TN-3478 Black',
                  'HP CE400A Black',
                  'HP CE401A Cyan',
                  'HP CE402A Yellow',
                  'HP CE403A Magenta',
                  'HP Q7553A Black',
                  'SAMSUNG ML-D2850B Black',
                  'SAMSUNG MLT-D104S Black',
                  'SAMSUNG MLT-D108S Black',
                  'SAMSUNG SCX-D6555A Black'
                ],
                'Toner Cartridge': [
                  'Brother TN-456 Black High Yield',
                  'Brother TN-456 Cyan High Yield',
                  'Brother TN-456 Magenta High',
                  'Brother TN-456 Yellow High Yield',
                  'Canon CRG-324 II',
                  'HP CB435A Black',
                  'HP CE255A Black',
                  'HP CE278A Black',
                  'HP CE285A (HP85A) Black',
                  'HP CE310A Black',
                  'HP CE311A Cyan',
                  'HP CE312A Yellow',
                  'HP CE313A Magenta',
                  'HP CE505A Black',
                  'HP CF217A (HP17A) Black',
                  'HP CF226A (HP26A) Black',
                  'HP CF281A (HP81A) Black',
                  'HP CF283A (HP83A) LaserJet',
                  'HP CF283XC (HP83X) Blk Contract L',
                  'HP CF287A (HP87) Black',
                  'HP CF325XC (HP25X) Black LaserJet',
                  'HP CF350A Black LJ',
                  'HP CF351A Cyan LJ',
                  'HP CF352A Yellow LJ',
                  'HP CF353A Magenta LJ',
                  'HP CF360A (HP508A) Black',
                  'HP CF361A (HP508A) Cyan',
                  'HP CF362A (HP508A) Yellow',
                  'HP CF363A (HP508A) Magenta',
                  'HP CF400A (HP201A) Black',
                  'HP CF401A (HP201A) Cyan',
                  'HP CF402A (HP201A) Yellow',
                  'HP CF403A (HP201A) Magenta',
                  'HP CF410A (HP410A) black',
                  'HP CF411A (HP410A) Cyan',
                  'HP CF412A (HP410A) Yellow',
                  'HP CF413A (HP410A) Magenta',
                  'HP Q2612A Black'
                ],
                'Trashbag': ['XXL size'],
                'Twine': ['plastic'],
                'Wrapping Paper': ['kraft']
              };

              var options = variantMap[val] || null;

              if (otherItemWrap) {
                if (isOthers) {
                  otherItemWrap.classList.remove('d-none');
                  otherItemInput.setAttribute('required', 'required');
                } else {
                  otherItemWrap.classList.add('d-none');
                  otherItemInput.removeAttribute('required');
                  otherItemInput.value = '';
                }
              }

              if (supplyVariantWrap && supplyVariant) {
                if (options) {
                  // populate select
                  supplyVariant.innerHTML = '<option value="">Choose...</option>' + options.map(function(o){ return '<option>' + o + '</option>'; }).join('');
                  supplyVariantWrap.classList.remove('d-none');
                  supplyVariant.setAttribute('required', 'required');
                } else {
                  supplyVariantWrap.classList.add('d-none');
                  supplyVariant.removeAttribute('required');
                  supplyVariant.value = '';
                }
              }
            }
            if (supplyItem) {
              supplyItem.addEventListener('change', toggleOtherItem);
              toggleOtherItem();
            }

            var supplyForm = document.getElementById('supplyForm');
            supplyForm.addEventListener('submit', function(e){
              e.preventDefault();
              if (!supplyForm.checkValidity()) { supplyForm.classList.add('was-validated'); return; }

              var submitBtn = supplyForm.querySelector('button[type="submit"]');
              var submitBtnOriginal = submitBtn ? submitBtn.innerHTML : null;
              if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Requesting...';
              }

              var fd = new FormData(supplyForm);
              if (supplyItem && supplyItem.value === 'Others') {
                var otherValue = (otherItemInput.value || '').trim();
                if (otherValue === '') {
                  if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = submitBtnOriginal; }
                  supplyForm.classList.add('was-validated');
                  return;
                }
                fd.set('item', otherValue);
              }

              // Optimistic UI: hide modal and show immediate success so interaction feels instant.
              var optimisticShown = false;
              try {
                var modalEl = document.getElementById('supplyModal');
                var modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
                if (modal) modal.hide();
                supplyForm.reset();
                supplyForm.classList.remove('was-validated');
                toggleOtherItem();
                var tmp = document.createElement('div');
                tmp.className = 'alert alert-success m-3';
                tmp.textContent = 'Request submitted';
                document.querySelector('.container').insertBefore(tmp, document.querySelector('.container').firstChild);
                setTimeout(function(){ tmp.remove(); }, 3000);
                optimisticShown = true;
              } catch (e) {
                // ignore optimistic UI errors
              }

              fetch('api/supply_create.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                  var alertEl = document.getElementById('supplyAlert');
                  alertEl.innerHTML = '';
                  if (data.success) {
                    if (!optimisticShown) {
                      var modalEl2 = document.getElementById('supplyModal');
                      var modal2 = bootstrap.Modal.getInstance(modalEl2);
                      if (modal2) modal2.hide();
                      supplyForm.reset();
                      supplyForm.classList.remove('was-validated');
                      toggleOtherItem();
                      var tmp2 = document.createElement('div');
                      tmp2.className = 'alert alert-success m-3';
                      tmp2.textContent = data.message || 'Request submitted';
                      document.querySelector('.container').insertBefore(tmp2, document.querySelector('.container').firstChild);
                      setTimeout(function(){ tmp2.remove(); }, 3000);
                    }
                  } else {
                    alertEl.innerHTML = '<div class="alert alert-danger">'+(data.message||'Error')+'</div>';
                    // if we had optimistically shown success, show an error to inform user
                    if (optimisticShown) {
                      var errtmp = document.createElement('div');
                      errtmp.className = 'alert alert-danger m-3';
                      errtmp.textContent = data.message || 'Failed to submit request';
                      document.querySelector('.container').insertBefore(errtmp, document.querySelector('.container').firstChild);
                      setTimeout(function(){ errtmp.remove(); }, 5000);
                    }
                  }
                  if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = submitBtnOriginal; }
                }).catch(err => {
                  document.getElementById('supplyAlert').innerHTML = '<div class="alert alert-danger">Network error</div>';
                  if (optimisticShown) {
                    var errtmp2 = document.createElement('div');
                    errtmp2.className = 'alert alert-danger m-3';
                    errtmp2.textContent = 'Network error while submitting request';
                    document.querySelector('.container').insertBefore(errtmp2, document.querySelector('.container').firstChild);
                    setTimeout(function(){ errtmp2.remove(); }, 5000);
                  }
                  if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = submitBtnOriginal; }
                });
            });

            // OB Slip modal handling
            var obSlipBtn = document.getElementById('obSlipBtn');
            if (obSlipBtn) obSlipBtn.addEventListener('click', function(){
              var modal = new bootstrap.Modal(document.getElementById('obSlipModal'));
              modal.show();
            });

            var obSlipForm = document.getElementById('obSlipForm');
            if (obSlipForm) {
              obSlipForm.addEventListener('submit', function(e){
                e.preventDefault();
                var typeChecked = obSlipForm.querySelector('input[name="ob_type"]:checked');
                var typeErr = document.getElementById('obTypeError');
                if (!typeChecked) {
                  if (typeErr) typeErr.style.display = 'block';
                } else if (typeErr) {
                  typeErr.style.display = 'none';
                }

                if (!obSlipForm.checkValidity() || !typeChecked) {
                  obSlipForm.classList.add('was-validated');
                  return;
                }

                var fd = new FormData(obSlipForm);

                var submitBtn = obSlipForm.querySelector('button[type="submit"]');
                var submitBtnOriginal = submitBtn ? submitBtn.innerHTML : null;
                if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...'; }

                // Optimistic UI: hide modal and show immediate success
                var optimisticShown = false;
                try {
                  var modalEl = document.getElementById('obSlipModal');
                  var modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
                  if (modal) modal.hide();
                  obSlipForm.reset();
                  obSlipForm.classList.remove('was-validated');
                  if (typeErr) typeErr.style.display = 'none';
                  var today = new Date();
                  var mm = String(today.getMonth() + 1).padStart(2, '0');
                  var dd = String(today.getDate()).padStart(2, '0');
                  var yyyy = today.getFullYear();
                  var dateInput = obSlipForm.querySelector('input[name="date"]');
                  if (dateInput) dateInput.value = yyyy + '-' + mm + '-' + dd;
                  var nameInput = obSlipForm.querySelector('input[name="name"]');
                  if (nameInput) nameInput.value = '<?php echo addslashes(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?>';
                  var sectionInput = obSlipForm.querySelector('input[name="section"]');
                  if (sectionInput) sectionInput.value = '<?php echo addslashes((string)($user['division'] ?? '')); ?>';
                  var tmp = document.createElement('div');
                  tmp.className = 'alert alert-success m-3';
                  tmp.textContent = 'OB Slip submitted';
                  document.querySelector('.container').insertBefore(tmp, document.querySelector('.container').firstChild);
                  setTimeout(function(){ tmp.remove(); }, 3000);
                  optimisticShown = true;
                } catch (e) {}

                fetch('api/ob_slip_create.php', { method: 'POST', body: fd })
                  .then(function(res){ return res.json(); })
                  .then(function(data){
                    var alertEl = document.getElementById('obSlipAlert');
                    alertEl.innerHTML = '';
                    if (data.success) {
                      if (!optimisticShown) {
                        var modalEl = document.getElementById('obSlipModal');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                        obSlipForm.reset();
                        obSlipForm.classList.remove('was-validated');
                        if (typeErr) typeErr.style.display = 'none';
                        var tmp2 = document.createElement('div');
                        tmp2.className = 'alert alert-success m-3';
                        tmp2.textContent = data.message || 'OB Slip submitted';
                        document.querySelector('.container').insertBefore(tmp2, document.querySelector('.container').firstChild);
                        setTimeout(function(){ tmp2.remove(); }, 3000);
                      }
                    } else {
                      alertEl.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error') + '</div>';
                      if (optimisticShown) {
                        var errtmp = document.createElement('div');
                        errtmp.className = 'alert alert-danger m-3';
                        errtmp.textContent = data.message || 'Failed to submit OB slip';
                        document.querySelector('.container').insertBefore(errtmp, document.querySelector('.container').firstChild);
                        setTimeout(function(){ errtmp.remove(); }, 5000);
                      }
                    }
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = submitBtnOriginal; }
                  })
                  .catch(function(){
                    document.getElementById('obSlipAlert').innerHTML = '<div class="alert alert-danger">Network error</div>';
                    if (optimisticShown) {
                      var errtmp2 = document.createElement('div');
                      errtmp2.className = 'alert alert-danger m-3';
                      errtmp2.textContent = 'Network error while submitting OB slip';
                      document.querySelector('.container').insertBefore(errtmp2, document.querySelector('.container').firstChild);
                      setTimeout(function(){ errtmp2.remove(); }, 5000);
                    }
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = submitBtnOriginal; }
                  });
              });

              var obDateInput = obSlipForm.querySelector('input[name="date"]');
              if (obDateInput && !obDateInput.value) {
                var now = new Date();
                var m = String(now.getMonth() + 1).padStart(2, '0');
                var d = String(now.getDate()).padStart(2, '0');
                obDateInput.value = now.getFullYear() + '-' + m + '-' + d;
              }
            }

            var freedomWallForm = document.getElementById('freedomWallForm');
            if (freedomWallForm) {
              freedomWallForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!freedomWallForm.checkValidity()) {
                  freedomWallForm.classList.add('was-validated');
                  return;
                }

                var fd = new FormData(freedomWallForm);
                fetch('api/freedom_wall_create.php', { method: 'POST', body: fd })
                  .then(function(res) { return res.json(); })
                  .then(function(data) {
                    var alertEl = document.getElementById('freedomWallAlert');
                    alertEl.innerHTML = '';
                    if (data.success) {
                      var modalEl = document.getElementById('freedomWallModal');
                      var modal = bootstrap.Modal.getInstance(modalEl);
                      if (modal) modal.hide();
                      freedomWallForm.reset();
                      freedomWallForm.classList.remove('was-validated');
                      window.location.reload();
                    } else {
                      alertEl.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error') + '</div>';
                    }
                  })
                  .catch(function() {
                    document.getElementById('freedomWallAlert').innerHTML = '<div class="alert alert-danger">Network error</div>';
                  });
              });
            }

            // Vehicle modal handling
            var vehicleForm = document.getElementById('vehicleForm');
            var passengersWrap = document.getElementById('passengersWrap');
            var addPassengerBtn = document.getElementById('addPassengerBtn');
            var dateApplication = document.getElementById('dateApplication');
            var vehicleCancelBtn = document.getElementById('vehicleCancelBtn');
            var vehicleDateUseInput = document.getElementById('vehicleDateUse');
            var vehicleDepartureDateInput = document.getElementById('vehicleDepartureDate');
            var vehicleExpectedArrivalDateInput = document.getElementById('vehicleExpectedArrivalDate');
            var vehicleDepartureTimeInput = document.getElementById('vehicleDepartureTime');
            var vehicleExpectedArrivalTimeInput = document.getElementById('vehicleExpectedArrivalTime');
            var vehicleDateTargetButtons = document.querySelectorAll('.vehicle-date-target-btn');
            var vehicleDateInputs = vehicleForm ? [vehicleDateUseInput, vehicleDepartureDateInput, vehicleExpectedArrivalDateInput].filter(Boolean) : [];
            var activeDateInput = null;
            var activeTimeInput = null;
            var vehicleMiniCalendar = null;
            var vehicleMiniCalendarEl = document.getElementById('vehicleMiniCalendar');

            function nextDateString(dateStr) {
              var parts = (dateStr || '').split('-');
              if (parts.length !== 3) return dateStr;
              var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]) + 1);
              var m = String(d.getMonth() + 1).padStart(2, '0');
              var day = String(d.getDate()).padStart(2, '0');
              return d.getFullYear() + '-' + m + '-' + day;
            }

            function refreshVehicleMiniCalendarHighlights() {
              if (!vehicleMiniCalendar) return;
              vehicleMiniCalendar.removeAllEvents();

              if (vehicleDateUseInput && vehicleDateUseInput.value) {
                vehicleMiniCalendar.addEvent({
                  title: 'Use Date',
                  start: vehicleDateUseInput.value,
                  end: nextDateString(vehicleDateUseInput.value),
                  display: 'background',
                  backgroundColor: 'rgba(13,110,253,0.28)',
                  borderColor: 'rgba(13,110,253,0.28)'
                });
              }

              if (vehicleDepartureDateInput && vehicleDepartureDateInput.value) {
                vehicleMiniCalendar.addEvent({
                  title: 'Departure',
                  start: vehicleDepartureDateInput.value,
                  end: nextDateString(vehicleDepartureDateInput.value),
                  display: 'background',
                  backgroundColor: 'rgba(25,135,84,0.28)',
                  borderColor: 'rgba(25,135,84,0.28)'
                });
              }

              if (vehicleExpectedArrivalDateInput && vehicleExpectedArrivalDateInput.value) {
                vehicleMiniCalendar.addEvent({
                  title: 'Arrival',
                  start: vehicleExpectedArrivalDateInput.value,
                  end: nextDateString(vehicleExpectedArrivalDateInput.value),
                  display: 'background',
                  backgroundColor: 'rgba(220,53,69,0.24)',
                  borderColor: 'rgba(220,53,69,0.24)'
                });
              }
            }

            function setActiveDateInput(input) {
              activeDateInput = input;
              vehicleDateInputs.forEach(function(el){ el.classList.remove('date-target-active'); });
              if (activeDateInput) {
                activeDateInput.classList.add('date-target-active');
                if (vehicleMiniCalendar) {
                  vehicleMiniCalendar.gotoDate(activeDateInput.value || new Date());
                }
              }
            }

            function setActiveTargetButton(btn) {
              vehicleDateTargetButtons.forEach(function(item){ item.classList.remove('active'); });
              if (btn) btn.classList.add('active');
            }

            function setCalendarTarget(dateInput, timeInput, btn) {
              if (!dateInput) return;
              setActiveDateInput(dateInput);
              activeTimeInput = timeInput || null;
              setActiveTargetButton(btn || null);
            }

            function setTodayApplicationDate() {
              if (!dateApplication) return;
              var d = new Date();
              var m = String(d.getMonth() + 1).padStart(2, '0');
              var day = String(d.getDate()).padStart(2, '0');
              dateApplication.value = d.getFullYear() + '-' + m + '-' + day;
            }

            function addPassengerField(value) {
              if (!passengersWrap) return;
              var row = document.createElement('div');
              row.className = 'passenger-row';
              row.innerHTML = '<input type="text" name="passengers[]" class="form-control" placeholder="Passenger name" required value="' + (value || '') + '">' +
                '<button type="button" class="btn btn-outline-danger btn-sm remove-passenger">Remove</button>';
              passengersWrap.appendChild(row);
              passengersWrap.scrollTop = passengersWrap.scrollHeight;
            }

            if (addPassengerBtn) {
              addPassengerBtn.addEventListener('click', function() {
                addPassengerField('');
              });
            }

            if (passengersWrap) {
              passengersWrap.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-passenger')) {
                  var rows = passengersWrap.querySelectorAll('.passenger-row');
                  if (rows.length > 1) {
                    e.target.closest('.passenger-row').remove();
                  }
                }
              });
            }

            setTodayApplicationDate();
            if (passengersWrap && passengersWrap.querySelectorAll('.passenger-row').length === 0) {
              addPassengerField('');
            }

            if (vehicleCancelBtn && vehicleForm) {
              vehicleCancelBtn.addEventListener('click', function() {
                vehicleForm.reset();
                vehicleForm.classList.remove('was-validated');
                document.getElementById('vehicleAlert').innerHTML = '';
                if (passengersWrap) {
                  passengersWrap.innerHTML = '';
                  addPassengerField('');
                }
                setTodayApplicationDate();
                if (vehicleDateInputs.length) {
                  setActiveDateInput(vehicleDateInputs[0]);
                }
                activeTimeInput = null;
                setActiveTargetButton(null);
                refreshVehicleMiniCalendarHighlights();
              });
            }

            if (vehicleDateInputs.length) {
              vehicleDateInputs.forEach(function(input){
                input.addEventListener('focus', function(){ setActiveDateInput(input); });
                input.addEventListener('click', function(){ setActiveDateInput(input); });
                input.addEventListener('change', refreshVehicleMiniCalendarHighlights);
              });
              setActiveDateInput(vehicleDateInputs[0]);
            }

            if (vehicleDateTargetButtons.length) {
              vehicleDateTargetButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                  var dateInput = document.getElementById(btn.getAttribute('data-target-date') || '');
                  var timeInput = document.getElementById(btn.getAttribute('data-target-time') || '');
                  setCalendarTarget(dateInput, timeInput, btn);
                });
              });
            }

            if (vehicleMiniCalendarEl) {
              vehicleMiniCalendar = new FullCalendar.Calendar(vehicleMiniCalendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                fixedWeekCount: false,
                headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                dateClick: function(info) {
                  if (!activeDateInput) return;
                  activeDateInput.value = info.dateStr;
                  activeDateInput.dispatchEvent(new Event('change', { bubbles: true }));
                  refreshVehicleMiniCalendarHighlights();
                  if (activeTimeInput) {
                    activeTimeInput.focus();
                    if (typeof activeTimeInput.showPicker === 'function') {
                      activeTimeInput.showPicker();
                    }
                  }
                }
                ,
                eventClick: function(info) {
                  var ev = info.event;
                  var props = ev.extendedProps || {};
                  var defaultAvatar = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

                  document.getElementById('eventTitle').textContent = ev.title || 'Vehicle Request';
                  document.getElementById('evPurpose').textContent = (props.purpose || ev.title) || '';
                  document.getElementById('evDestination').textContent = props.destination || '';
                  document.getElementById('evStart').textContent = ev.start ? ev.start.toLocaleString() : '';
                  document.getElementById('evEnd').textContent = ev.end ? ev.end.toLocaleString() : '';
                  document.getElementById('evCreatorName').textContent = props.creator_name || 'Unknown User';
                  document.getElementById('evCreatorDivision').textContent = props.creator_division || '';
                  document.getElementById('evCreatorAvatar').src = props.creator_avatar || defaultAvatar;

                  var modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
                  modal.show();
                }
              });
              vehicleMiniCalendar.render();
              refreshVehicleMiniCalendarHighlights();
              // Load approved vehicle events into the mini calendar
              function loadApprovedVehiclesIntoMiniCalendar() {
                if (!vehicleMiniCalendar) return;
                // remove existing vehicle events to avoid duplicates
                try {
                  vehicleMiniCalendar.getEvents().forEach(function(ev){
                    if (ev && ev.id && String(ev.id).indexOf('vehicle-') === 0) ev.remove();
                  });
                } catch (e) {}

                fetch('api/events.php').then(function(res){ return res.json(); }).then(function(data){
                  if (!Array.isArray(data)) return;
                  data.forEach(function(evt){
                    var props = evt.extendedProps || {};
                    if (props.event_type === 'vehicle' || evt.groupId === 'vehicle') {
                      try {
                        vehicleMiniCalendar.addEvent({
                          id: evt.id,
                          title: evt.title || 'Vehicle',
                          start: evt.start,
                          end: evt.end,
                          backgroundColor: evt.backgroundColor || evt.color || '#6c757d',
                          borderColor: evt.borderColor || evt.backgroundColor || evt.color || '#6c757d',
                          textColor: evt.textColor || '#ffffff',
                          extendedProps: evt.extendedProps || {}
                        });
                      } catch (err) {
                        // ignore add errors
                      }
                    }
                  });
                }).catch(function(){ /* ignore */ });
              }
            }

            var vehicleModalEl = document.getElementById('vehicleModal');
            if (vehicleModalEl) {
              vehicleModalEl.addEventListener('shown.bs.modal', function() {
                if (vehicleMiniCalendar) {
                    vehicleMiniCalendar.updateSize();
                    if (activeDateInput) {
                      vehicleMiniCalendar.gotoDate(activeDateInput.value || new Date());
                    }
                    // load approved vehicle events when modal opens
                    if (typeof loadApprovedVehiclesIntoMiniCalendar === 'function') loadApprovedVehiclesIntoMiniCalendar();
                  }
              });
            }

            if (vehicleForm) {
              vehicleForm.addEventListener('submit', function(e){
                e.preventDefault();

                if (!vehicleForm.checkValidity()) {
                  vehicleForm.classList.add('was-validated');
                  return;
                }

                var passengerInputs = vehicleForm.querySelectorAll('input[name="passengers[]"]');
                var passengerNames = [];
                passengerInputs.forEach(function(input){
                  var value = (input.value || '').trim();
                  if (value !== '') passengerNames.push(value);
                });

                if (passengerNames.length < 1) {
                  document.getElementById('vehicleAlert').innerHTML = '<div class="alert alert-danger">At least one passenger is required.</div>';
                  return;
                }

                var depDate = vehicleForm.querySelector('input[name="departure_date"]').value;
                var depTime = vehicleForm.querySelector('input[name="departure_time"]').value;
                var arrDate = vehicleForm.querySelector('input[name="expected_arrival_date"]').value;
                var arrTime = vehicleForm.querySelector('input[name="expected_arrival_time"]').value;

                var dep = new Date(depDate + 'T' + depTime);
                var arr = new Date(arrDate + 'T' + arrTime);
                if (arr < dep) {
                  document.getElementById('vehicleAlert').innerHTML = '<div class="alert alert-danger">Expected arrival must not be earlier than departure.</div>';
                  return;
                }

                var fd = new FormData(vehicleForm);

                var submitBtn = vehicleForm.querySelector('button[type="submit"]');
                var submitBtnOriginal = submitBtn ? submitBtn.innerHTML : null;
                if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Requesting...'; }

                // Optimistic UI: hide modal and show immediate success
                var optimisticShown = false;
                try {
                  var modalEl = document.getElementById('vehicleModal');
                  var modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
                  if (modal) modal.hide();
                  vehicleForm.reset();
                  vehicleForm.classList.remove('was-validated');
                  passengersWrap.innerHTML = '';
                  addPassengerField('');
                  setTodayApplicationDate();
                  if (vehicleDateInputs.length) {
                    setActiveDateInput(vehicleDateInputs[0]);
                  }
                  refreshVehicleMiniCalendarHighlights();
                  var tmp = document.createElement('div');
                  tmp.className = 'alert alert-success m-3';
                  tmp.textContent = 'Vehicle request submitted';
                  document.querySelector('.container').insertBefore(tmp, document.querySelector('.container').firstChild);
                  setTimeout(function(){ tmp.remove(); }, 3000);
                  optimisticShown = true;
                } catch (e) {}

                fetch('api/vehicle_create.php', { method: 'POST', body: fd })
                  .then(function(res){ return res.json(); })
                  .then(function(data){
                    var alertEl = document.getElementById('vehicleAlert');
                    alertEl.innerHTML = '';
                    if (data.success) {
                      if (!optimisticShown) {
                        var modalEl = document.getElementById('vehicleModal');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                        vehicleForm.reset();
                        vehicleForm.classList.remove('was-validated');
                        passengersWrap.innerHTML = '';
                        addPassengerField('');
                        setTodayApplicationDate();
                        if (vehicleDateInputs.length) {
                          setActiveDateInput(vehicleDateInputs[0]);
                        }
                        refreshVehicleMiniCalendarHighlights();
                        var tmp2 = document.createElement('div');
                        tmp2.className = 'alert alert-success m-3';
                        tmp2.textContent = data.message || 'Vehicle request submitted';
                        document.querySelector('.container').insertBefore(tmp2, document.querySelector('.container').firstChild);
                        setTimeout(function(){ tmp2.remove(); }, 3000);
                      }
                    } else {
                      alertEl.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error') + '</div>';
                      if (optimisticShown) {
                        var errtmp = document.createElement('div');
                        errtmp.className = 'alert alert-danger m-3';
                        errtmp.textContent = data.message || 'Failed to submit vehicle request';
                        document.querySelector('.container').insertBefore(errtmp, document.querySelector('.container').firstChild);
                        setTimeout(function(){ errtmp.remove(); }, 5000);
                      }
                    }
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = submitBtnOriginal; }
                  })
                  .catch(function(){
                    document.getElementById('vehicleAlert').innerHTML = '<div class="alert alert-danger">Network error</div>';
                    if (optimisticShown) {
                      var errtmp2 = document.createElement('div');
                      errtmp2.className = 'alert alert-danger m-3';
                      errtmp2.textContent = 'Network error while submitting vehicle request';
                      document.querySelector('.container').insertBefore(errtmp2, document.querySelector('.container').firstChild);
                      setTimeout(function(){ errtmp2.remove(); }, 5000);
                    }
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = submitBtnOriginal; }
                  });
              });
            }
      });
    </script>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="assets/script.js?v=20260407k2"></script>
  </body>
</html>













