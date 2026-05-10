<!-- Set Activity Modal -->
<div class="modal fade" id="activityModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="activityForm" class="needs-validation" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Set Activity</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="division" id="activityDivision" value="">
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
