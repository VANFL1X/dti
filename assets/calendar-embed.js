document.addEventListener('DOMContentLoaded', function() {
  var calEl = document.getElementById('divisionCalendar');
  if (!calEl) return;

  function isMobileViewport() {
    return window.innerWidth <= 767;
  }

  function getToolbarConfig() {
    if (isMobileViewport()) {
      return { left: 'prev,next', center: 'title', right: 'today' };
    }
    return { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' };
  }

  var mobileToolbarState = isMobileViewport();

  try {
    var calendar = new FullCalendar.Calendar(calEl, {
      initialView: 'dayGridMonth',
      eventDisplay: 'block',
      headerToolbar: getToolbarConfig(),
      windowResize: true,
      windowResizeDelay: 150,
      events: function(fetchInfo, successCallback, failureCallback) {
        var url = 'api/events.php';
        try {
          var div = (calEl.getAttribute('data-division') || '').trim();
          if (div) url += '?division=' + encodeURIComponent(div);
        } catch (e) {}
        fetch(url).then(function(res){ return res.json(); }).then(function(data){
          if (!Array.isArray(data)) return successCallback([]);
          successCallback(data);
        }).catch(function(err){ failureCallback(err); });
      },
      eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
      eventDidMount: function(info) {
        if (info.event.backgroundColor) {
          info.el.style.backgroundColor = info.event.backgroundColor;
          info.el.style.borderColor = info.event.backgroundColor;
        }
        var props = info.event.extendedProps || {};
        var titleEl = info.el.querySelector('.fc-event-title');
        if (titleEl) {
          var mobileMode = isMobileViewport();
          var startStr = info.event.start ? info.event.start.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false }) : '';
          var endStr = info.event.end ? info.event.end.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false }) : '';
          var timeStr = '';
          if (startStr && endStr) timeStr = startStr + ' - ' + endStr;
          else if (startStr) timeStr = startStr;
          var timeHtml = timeStr ? '<div style="font-size:' + (mobileMode ? '0.7rem' : '0.82rem') + ';opacity:.85;line-height:1.1">' + timeStr + '</div>' : '';
          var avatarSize = mobileMode ? 20 : 28;
          titleEl.innerHTML = '<div style="display:flex;align-items:center;gap:' + (mobileMode ? '6px' : '10px') + ';min-width:0">' +
                               (props.creator_avatar ? '<img src="'+props.creator_avatar+'" style="width:' + avatarSize + 'px;height:' + avatarSize + 'px;border-radius:50%;border:1px solid rgba(255,255,255,0.7);flex-shrink:0;object-fit:cover;">' : '') +
                               '<div style="display:flex;flex-direction:column;justify-content:center;min-width:0">' +
                                 '<div style="font-weight:500;line-height:1.15;white-space:normal;word-break:break-word;font-size:' + (mobileMode ? '0.8rem' : '0.92rem') + '">' + info.event.title + '</div>' +
                                 timeHtml +
                               '</div>' +
                               '</div>';
        }
      },
      eventClick: function(info) {
        var ev = info.event;
        var props = ev.extendedProps || {};
        var isBirthday = props.event_type === 'birthday';
        
        // Common elements
        document.getElementById('evCreatorName').textContent = props.creator_name || 'Unknown User';
        document.getElementById('evCreatorDivision').textContent = props.creator_division || 'No division';
        document.getElementById('evCreatorAvatar').src = props.creator_avatar || 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
        
        // Get section references
        var activityDetails = document.getElementById('activityDetails');
        var birthdayDetails = document.getElementById('birthdayDetails');
        
        if (isBirthday) {
          // For birthdays, show only name and birthday date
          document.getElementById('eventTitle').textContent = props.creator_name + "'s Birthday";
          var bdayDate = ev.start ? ev.start.toLocaleDateString('en-US', { month: 'long', day: 'numeric' }) : '';
          document.getElementById('evBirthdayDate').textContent = bdayDate;
          
          // Toggle sections: hide activity, show birthday
          activityDetails.style.display = 'none';
          birthdayDetails.style.display = 'block';
        } else {
          // For regular activities, show all details
          document.getElementById('eventTitle').textContent = ev.title || 'Activity Details';
          document.getElementById('evPurpose').textContent = ev.title || '';
          document.getElementById('evDestination').textContent = props.destination || '';
          document.getElementById('evStart').textContent = ev.start ? ev.start.toLocaleString() : '';
          document.getElementById('evEnd').textContent = ev.end ? ev.end.toLocaleString() : '';
          
          // Toggle sections: show activity, hide birthday
          activityDetails.style.display = 'block';
          birthdayDetails.style.display = 'none';
        }
        
        var modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
        modal.show();
      }
    });
    calendar.render();
    // expose for other handlers (e.g., activity form to refresh)
    window.divisionCalendar = calendar;

    window.addEventListener('resize', function() {
      var nowMobile = isMobileViewport();
      if (nowMobile !== mobileToolbarState) {
        mobileToolbarState = nowMobile;
        calendar.setOption('headerToolbar', getToolbarConfig());
      }
    }, { passive: true });
    
    // Set Activity: handle button if present
    var setBtn = document.getElementById('setActivityBtn');
    var activityModalEl = document.getElementById('activityModal');
    var activityForm = document.getElementById('activityForm');
    if (setBtn && activityModalEl) {
      setBtn.addEventListener('click', function() {
        // set hidden division value if container has data-division
        var divisionName = setBtn.getAttribute('data-division') || '';
        var divInput = document.getElementById('activityDivision');
        if (divInput && divisionName) divInput.value = divisionName;
        var modal = new bootstrap.Modal(activityModalEl);
        modal.show();
      });
    }

    // Division Users button: open users modal and fetch users (support multiple buttons)
    var usersBtns = document.querySelectorAll('#showDivisionUsersBtn');
    var usersModalEl = document.getElementById('divisionUsersModal');
    if (usersBtns && usersBtns.length && usersModalEl) {
      usersBtns.forEach(function(btn){
        btn.addEventListener('click', function() {
          var divisionName = btn.getAttribute('data-division') || (calEl.getAttribute('data-division') || '');
          var listWrap = usersModalEl.querySelector('.division-users-list');
          var alertWrap = document.getElementById('divisionUsersAlert');
          if (!divisionName) return;
          listWrap.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';
          fetch('api/division_users.php?division=' + encodeURIComponent(divisionName)).then(function(res){ return res.json(); }).then(function(data){
            listWrap.innerHTML = '';
            if (!data || !data.success) {
              alertWrap.innerHTML = '<div class="alert alert-danger">Failed to load users.</div>';
              return;
            }
            if (!Array.isArray(data.users) || data.users.length === 0) {
              listWrap.innerHTML = '<div class="text-muted">No users found for this division.</div>';
            } else {
              data.users.forEach(function(u){
                var avatar = u.avatar || 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
                var card = document.createElement('div');
                card.className = 'd-flex align-items-center gap-2 p-2 border rounded user-card';
                card.style.minWidth = '160px';
                card.innerHTML = '<div class="user-card-inner">' +
                                 '<img src="'+avatar+'" class="creator-avatar user-card-avatar">' +
                                 '<div class="mt-2"><div class="fw-semibold">'+(u.name||'')+'</div><div class="text-muted small">'+(u.email||'')+'</div></div>' +
                                 '</div>';
                listWrap.appendChild(card);
              });
            }
            alertWrap.innerHTML = '';
          }).catch(function(){
            listWrap.innerHTML = '';
            alertWrap.innerHTML = '<div class="alert alert-danger">Network error</div>';
          });
          var modal = new bootstrap.Modal(usersModalEl);
          modal.show();
        });
      });
    }

    if (activityForm) {
      activityForm.addEventListener('submit', function(e){
        e.preventDefault();
        if (!activityForm.checkValidity()) { activityForm.classList.add('was-validated'); return; }
        var fd = new FormData(activityForm);
        fetch('api/activity_create.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(res){ return res.json(); })
          .then(function(data){
            var alertEl = document.getElementById('activityAlert');
            alertEl.innerHTML = '';
            if (data && data.success) {
              var modal = bootstrap.Modal.getInstance(activityModalEl);
              if (modal) modal.hide();
              if (window.divisionCalendar && typeof window.divisionCalendar.refetchEvents === 'function') {
                window.divisionCalendar.refetchEvents();
              }
            } else {
              alertEl.innerHTML = '<div class="alert alert-danger">'+(data && data.message ? data.message : 'Error')+'</div>';
            }
          }).catch(function(){
            document.getElementById('activityAlert').innerHTML = '<div class="alert alert-danger">Network error</div>';
          });
      });
    }
  } catch (e) {
    // FullCalendar may not be loaded yet; try later
    console.error('Calendar init error', e);
  }
});
