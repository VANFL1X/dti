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
        
        <!-- Activity Details Section -->
        <div id="activityDetails">
          <p><strong>Purpose:</strong> <span id="evPurpose"></span></p>
          <p><strong>Destination:</strong> <span id="evDestination"></span></p>
          <p><strong>Start:</strong> <span id="evStart"></span></p>
          <p><strong>End:</strong> <span id="evEnd"></span></p>
        </div>
        
        <!-- Birthday Details Section -->
        <div id="birthdayDetails" style="display: none;">
          <p><strong>Birthday:</strong> <span id="evBirthdayDate"></span></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
