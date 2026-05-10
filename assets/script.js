// Basic form validation and modal switching
(function () {
  'use strict'

  // Disable background image on load (use theme colors only)
  document.documentElement.style.setProperty('--bg-image', 'none')
  document.body.style.backgroundImage = 'none'

  // Bootstrap modal elements
  const loginModalEl = document.getElementById('loginModal')
  const signupModalEl = document.getElementById('signupModal')
  const hasBootstrapModal = typeof window.bootstrap !== 'undefined' && typeof window.bootstrap.Modal === 'function'

  const loginModal = hasBootstrapModal && loginModalEl ? new bootstrap.Modal(loginModalEl) : null
  const signupModal = hasBootstrapModal && signupModalEl ? new bootstrap.Modal(signupModalEl) : null

  // Show signup from login
  const showCreateBtn = document.getElementById('showCreate')
  if (showCreateBtn && loginModal && signupModal) {
    showCreateBtn.addEventListener('click', function () {
      loginModal.hide()
      signupModal.show()
    })
  }

  // Apply bootstrap validation to forms
  const forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms).forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault()
        event.stopPropagation()
      }
      form.classList.add('was-validated')
    }, false)
  })
  // Theme handling removed: single neutral theme enforced

  // High-contrast feature removed from UI (button deleted); CSS retained.

  // Theme toggle (dark / light full-site)
  const themeToggle = document.getElementById('themeToggle')
  function applyTheme(t) {
    try { localStorage.setItem('dti_theme', t) } catch (e) {}
    if (t === 'dark') {
      document.documentElement.classList.add('dark-mode')
      if (themeToggle) themeToggle.textContent = '☀️'
      // Disable background images — use color variables + overlay for contrast
      document.documentElement.style.setProperty('--bg-image', 'none')
      document.body.style.backgroundImage = 'none'
      document.documentElement.style.setProperty('--overlay-start', 'rgba(0,0,0,0.36)')
      document.documentElement.style.setProperty('--overlay-end', 'rgba(255,255,255,0.02)')
      swapLogo(true)
    } else {
      document.documentElement.classList.remove('dark-mode')
      if (themeToggle) themeToggle.textContent = '🌙'
      document.documentElement.style.setProperty('--bg-image', 'none')
      document.body.style.backgroundImage = 'none'
      document.documentElement.style.setProperty('--overlay-start', 'rgba(255,255,255,0.04)')
      document.documentElement.style.setProperty('--overlay-end', 'rgba(11,15,30,0.06)')
      swapLogo(false)
    }
  }

  // Swap header logo to dark variant if present, else fallback to filter
  function swapLogo(dark) {
    const logos = document.querySelectorAll('.dti-logo, .footer-logo')
    if (!logos || logos.length === 0) return
    logos.forEach((logo) => {
      if (dark) {
        const l = new Image()
        l.onload = function () {
          logo.src = 'assets/logoDTI-dark.png'
          logo.classList.remove('invert-fallback')
        }
        l.onerror = function () {
          // fallback to invert filter class
          logo.classList.add('invert-fallback')
        }
        l.src = 'assets/logoDTI-dark.png'
      } else {
        const l2 = new Image()
        l2.onload = function () {
          logo.src = 'assets/logoDTI.png'
          logo.classList.remove('invert-fallback')
        }
        l2.onerror = function () {
          logo.classList.remove('invert-fallback')
        }
        l2.src = 'assets/logoDTI.png'
      }
    })
  }

  const storedTheme = (function(){ try { return localStorage.getItem('dti_theme') } catch(e){ return null } })()
  if (storedTheme) {
    applyTheme(storedTheme)
  } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    applyTheme('dark')
  } else {
    applyTheme('light')
  }

  // ensure logo matches initial theme
  swapLogo(document.documentElement.classList.contains('dark-mode'))

  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      const isDark = document.documentElement.classList.contains('dark-mode')
      applyTheme(isDark ? 'light' : 'dark')
    })
  }
})()

// Avatar preview on profile page
document.addEventListener('DOMContentLoaded', function () {
  var input = document.querySelector('input[name="avatar"], #avatarInput');
  var preview = document.getElementById('avatarPreview');
  if (!input || !preview) return;
  input.addEventListener('change', function (e) {
    var f = input.files && input.files[0];
    if (!f) return;
    var reader = new FileReader();
    reader.onload = function (ev) { preview.src = ev.target.result; };
    reader.readAsDataURL(f);
  });
});

// Login activity modal (division graph + account drill-down)
(function () {
  var modalEl = document.getElementById('loginActivityModal');
  if (!modalEl) return;

  var periodEl = document.getElementById('activityPeriodSelect');
  var subtitleEl = document.getElementById('activitySubtitle');
  var noDataEl = document.getElementById('activityNoData');
  var divisionSection = document.getElementById('divisionChartSection');
  var divisionCanvas = document.getElementById('divisionActivityChart');
  var accountCanvas = document.getElementById('accountActivityChart');
  var drilldownSection = document.getElementById('accountDrilldownSection');
  var drilldownTitle = document.getElementById('accountDrilldownTitle');
  var resetBtn = document.getElementById('resetActivityDivision');

  var divisionChart = null;
  var accountChart = null;
  var selectedDivision = '';

  var divisionColorMap = {
    'Admin Division': '#2563EB',
    'Office of the Provincial Director': '#DC3545',
    'Consumer Protection Division': '#198754',
    'Business Development Division': '#FF6B35',
    'Planning Unit': '#6F42C1'
  };

  function getDivisionColor(name) {
    return divisionColorMap[name] || '#6c757d';
  }

  function hexToRgba(hex, alpha) {
    var clean = String(hex || '').replace('#', '');
    if (clean.length === 3) {
      clean = clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
    }
    var intVal = parseInt(clean, 16);
    if (isNaN(intVal)) return 'rgba(108,117,125,' + alpha + ')';
    var r = (intVal >> 16) & 255;
    var g = (intVal >> 8) & 255;
    var b = intVal & 255;
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  function ensureChartJs() {
    if (window.Chart) return Promise.resolve();
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[data-chartjs="1"]');
      if (existing) {
        existing.addEventListener('load', function () { resolve(); }, { once: true });
        existing.addEventListener('error', function () { reject(new Error('Chart.js load failed')); }, { once: true });
        return;
      }

      function loadFrom(url, done, fail) {
        var s = document.createElement('script');
        s.src = url;
        s.setAttribute('data-chartjs', '1');
        s.onload = function () { done(); };
        s.onerror = function () { fail(); };
        document.head.appendChild(s);
      }

      // Primary CDN + fallback CDN for environments where one endpoint is blocked.
      loadFrom(
        'https://cdn.jsdelivr.net/npm/chart.js',
        function () { resolve(); },
        function () {
          loadFrom(
            'https://unpkg.com/chart.js',
            function () { resolve(); },
            function () { reject(new Error('Chart.js load failed')); }
          );
        }
      );
    });
  }

  function showNoData(show) {
    if (!noDataEl) return;
    noDataEl.classList.toggle('d-none', !show);
  }

  function setDrilldownVisible(show) {
    if (!drilldownSection) return;
    drilldownSection.classList.toggle('d-none', !show);
  }

  function setDivisionVisible(show) {
    if (!divisionSection) return;
    divisionSection.classList.toggle('d-none', !show);
  }

  function clearAccountsChart() {
    if (accountChart) {
      accountChart.destroy();
      accountChart = null;
    }
  }

  function renderDivisionFallback(labels, counts) {
    if (!divisionSection) return;
    var wrap = divisionSection.querySelector('.activity-chart-wrap');
    if (!wrap) return;

    var html = '<div class="list-group">';
    for (var i = 0; i < labels.length; i++) {
      var label = String(labels[i] || 'Unknown Division');
      var count = Number(counts[i] || 0);
      html += '<div class="list-group-item d-flex justify-content-between align-items-center">'
        + '<span>' + label + '</span>'
        + '<span class="badge rounded-pill" style="background:' + getDivisionColor(label) + ';color:#fff;">' + count + '</span>'
        + '</div>';
    }
    html += '</div>';
    wrap.innerHTML = html;
  }

  function restoreDivisionCanvas() {
    if (!divisionSection) return;
    var wrap = divisionSection.querySelector('.activity-chart-wrap');
    if (!wrap) return;
    if (!wrap.querySelector('canvas#divisionActivityChart')) {
      wrap.innerHTML = '<canvas id="divisionActivityChart"></canvas>';
      divisionCanvas = document.getElementById('divisionActivityChart');
    }
  }

  function renderDivision(labels, counts) {
    if (!divisionCanvas) return;
    if (divisionChart) divisionChart.destroy();

    function fitTextInside(ctx, text, maxWidth) {
      var value = String(text || '');
      if (maxWidth <= 8) return '';
      if (ctx.measureText(value).width <= maxWidth) return value;

      var ellipsis = '...';
      var lo = 0;
      var hi = value.length;
      while (lo < hi) {
        var mid = Math.ceil((lo + hi) / 2);
        var candidate = value.slice(0, mid) + ellipsis;
        if (ctx.measureText(candidate).width <= maxWidth) {
          lo = mid;
        } else {
          hi = mid - 1;
        }
      }
      return value.slice(0, lo) + ellipsis;
    }

    var divisionBarLabelPlugin = {
      id: 'divisionBarLabelPlugin',
      afterDatasetsDraw: function (chart) {
        var ctx = chart.ctx;
        var dataset = chart.data.datasets[0];
        var meta = chart.getDatasetMeta(0);
        if (!dataset || !meta || !meta.data) return;

        ctx.save();
        ctx.font = '600 13px Inter, sans-serif';
        ctx.fillStyle = '#ffffff';
        ctx.textBaseline = 'middle';

        for (var i = 0; i < meta.data.length; i++) {
          var bar = meta.data[i];
          if (!bar) continue;
          var fullLabel = (labels[i] || '') + ' (' + (counts[i] || 0) + ')';

          var leftX = Math.min(bar.base, bar.x);
          var rightX = Math.max(bar.base, bar.x);
          var textX = leftX + 12;
          var textY = bar.y;
          var labelMaxWidth = Math.max(0, (rightX - leftX) - 20);
          var label = fitTextInside(ctx, fullLabel, labelMaxWidth);
          if (!label) continue;
          var topY = bar.y - (bar.height || 12) / 2;
          var bottomY = bar.y + (bar.height || 12) / 2;
          var radius = Math.min(10, Math.max(4, Math.round((bottomY - topY) / 2) - 1));

          // Keep labels visually inside the bar shape even for short bars.
          ctx.save();
          roundRect(ctx, leftX + 1, topY + 1, Math.max(1, rightX - leftX - 2), Math.max(2, bottomY - topY - 2), radius);
          ctx.clip();
          ctx.fillText(label, textX, textY);
          ctx.restore();
        }

        ctx.restore();
      }
    };

    var divisionBarGlassPlugin = {
      id: 'divisionBarGlassPlugin',
      afterDatasetsDraw: function (chart) {
        var ctx = chart.ctx;
        var meta = chart.getDatasetMeta(0);
        if (!meta || !meta.data) return;
        ctx.save();
        for (var i = 0; i < meta.data.length; i++) {
          var bar = meta.data[i];
          if (!bar) continue;
          var leftX = Math.min(bar.base, bar.x);
          var rightX = Math.max(bar.base, bar.x);
          var width = Math.max(0, rightX - leftX);
          var topY = bar.y - (bar.height || 12) / 2;
          var bottomY = bar.y + (bar.height || 12) / 2;

          // Slight inset for the glossy overlay
          var inset = Math.max(4, Math.round(Math.min(width, 12) * 0.18));
          var gLeft = leftX + inset;
          var gRight = rightX - inset;
          if (gRight <= gLeft) continue;

          var grad = ctx.createLinearGradient(gLeft, topY, gLeft, bottomY);
          grad.addColorStop(0, 'rgba(255,255,255,0.22)');
          grad.addColorStop(0.18, 'rgba(255,255,255,0.12)');
          grad.addColorStop(0.5, 'rgba(255,255,255,0.04)');
          grad.addColorStop(1, 'rgba(255,255,255,0)');

          ctx.fillStyle = grad;
          var radius = Math.min(12, Math.max(6, Math.round((bottomY - topY) / 2)));
          // draw rounded rect overlay
          roundRect(ctx, gLeft, topY + 1, Math.max(1, gRight - gLeft), Math.max(3, bottomY - topY - 2), radius);
          ctx.fill();
        }
        ctx.restore();
      }
    };

    // helper: rounded rect path
    function roundRect(ctx, x, y, w, h, r) {
      var min = Math.min(w, h) / 2;
      if (r > min) r = min;
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.arcTo(x + w, y, x + w, y + h, r);
      ctx.arcTo(x + w, y + h, x, y + h, r);
      ctx.arcTo(x, y + h, x, y, r);
      ctx.arcTo(x, y, x + w, y, r);
      ctx.closePath();
    }

    divisionChart = new Chart(divisionCanvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          data: counts,
          backgroundColor: labels.map(function (name) { return getDivisionColor(name); }),
          borderColor: labels.map(function (name) { return getDivisionColor(name); }),
          hoverBackgroundColor: labels.map(function (name) { return hexToRgba(getDivisionColor(name), 0.92); }),
          hoverBorderColor: labels.map(function (name) { return getDivisionColor(name); }),
          minBarLength: 28,
          borderWidth: 2,
          borderRadius: 12,
          borderSkipped: false
        }]
      },
      options: {
        indexAxis: 'y',
        maintainAspectRatio: false,
        animation: { duration: 700, easing: 'easeOutQuart' },
        plugins: { 
          legend: { display: false },
          tooltip: {
            enabled: true,
            backgroundColor: '#ffffff',
            titleColor: '#0b1220',
            bodyColor: '#0b1220',
            borderColor: 'rgba(15,23,42,0.08)',
            borderWidth: 1,
            titleFont: { weight: 700 },
            padding: 10
          }
        },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0 } },
          y: { grid: { display: false }, ticks: { display: false } }
        },
        onClick: function (_evt, activeEls) {
          if (!activeEls || !activeEls.length) return;
          var idx = activeEls[0].index;
          selectedDivision = labels[idx] || '';
          loadActivity();
        },
        onHover: function (evt, activeEls) {
          divisionCanvas.style.cursor = (activeEls && activeEls.length) ? 'pointer' : 'default';
        }
      },
      plugins: [divisionBarGlassPlugin, divisionBarLabelPlugin]
    });
  }

  function renderAccounts(labels, counts) {
    if (!accountCanvas) return;
    if (accountChart) accountChart.destroy();

    var activeColor = getDivisionColor(selectedDivision);
    var accountWrap = accountCanvas.closest('.activity-chart-wrap');
    if (accountWrap) {
      var targetHeight = Math.max(150, Math.min(230, 84 + (labels.length * 30)));
      accountWrap.style.height = targetHeight + 'px';
    }

    function fitTextInside(ctx, text, maxWidth) {
      var value = String(text || '');
      if (maxWidth <= 8) return '';
      if (ctx.measureText(value).width <= maxWidth) return value;

      var ellipsis = '...';
      var lo = 0;
      var hi = value.length;
      while (lo < hi) {
        var mid = Math.ceil((lo + hi) / 2);
        var candidate = value.slice(0, mid) + ellipsis;
        if (ctx.measureText(candidate).width <= maxWidth) {
          lo = mid;
        } else {
          hi = mid - 1;
        }
      }
      return value.slice(0, lo) + ellipsis;
    }

    function roundRect(ctx, x, y, w, h, r) {
      var min = Math.min(w, h) / 2;
      if (r > min) r = min;
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.arcTo(x + w, y, x + w, y + h, r);
      ctx.arcTo(x + w, y + h, x, y + h, r);
      ctx.arcTo(x, y + h, x, y, r);
      ctx.arcTo(x, y, x + w, y, r);
      ctx.closePath();
    }

    var accountBarLabelPlugin = {
      id: 'accountBarLabelPlugin',
      afterDatasetsDraw: function (chart) {
        var ctx = chart.ctx;
        var dataset = chart.data.datasets[0];
        var meta = chart.getDatasetMeta(0);
        if (!dataset || !meta || !meta.data) return;

        ctx.save();
        ctx.font = '600 13px Inter, sans-serif';
        ctx.fillStyle = '#ffffff';
        ctx.textBaseline = 'middle';

        for (var i = 0; i < meta.data.length; i++) {
          var bar = meta.data[i];
          if (!bar) continue;
          var accountName = String(labels[i] || 'Unknown Account');
          var fullLabel = accountName + ' (' + (counts[i] || 0) + ')';

          var leftX = Math.min(bar.base, bar.x);
          var rightX = Math.max(bar.base, bar.x);
          var textX = leftX + 12;
          var textY = bar.y;
          var labelMaxWidth = Math.max(0, (rightX - leftX) - 20);
          var label = fitTextInside(ctx, fullLabel, labelMaxWidth);
          if (!label) continue;
          var topY = bar.y - (bar.height || 10) / 2;
          var bottomY = bar.y + (bar.height || 10) / 2;
          var radius = Math.min(10, Math.max(4, Math.round((bottomY - topY) / 2) - 1));

          // Keep account labels fully inside the rounded bar shape.
          ctx.save();
          roundRect(ctx, leftX + 1, topY + 1, Math.max(1, rightX - leftX - 2), Math.max(2, bottomY - topY - 2), radius);
          ctx.clip();
          ctx.fillText(label, textX, textY);
          ctx.restore();
        }

        ctx.restore();
      }
    };

    var accountBarGlassPlugin = {
      id: 'accountBarGlassPlugin',
      afterDatasetsDraw: function (chart) {
        var ctx = chart.ctx;
        var meta = chart.getDatasetMeta(0);
        if (!meta || !meta.data) return;
        ctx.save();
        for (var i = 0; i < meta.data.length; i++) {
          var bar = meta.data[i];
          if (!bar) continue;
          var leftX = Math.min(bar.base, bar.x);
          var rightX = Math.max(bar.base, bar.x);
          var width = Math.max(0, rightX - leftX);
          var topY = bar.y - (bar.height || 10) / 2;
          var bottomY = bar.y + (bar.height || 10) / 2;

          var inset = Math.max(3, Math.round(Math.min(width, 10) * 0.18));
          var gLeft = leftX + inset;
          var gRight = rightX - inset;
          if (gRight <= gLeft) continue;

          var grad = ctx.createLinearGradient(gLeft, topY, gLeft, bottomY);
          grad.addColorStop(0, 'rgba(255,255,255,0.22)');
          grad.addColorStop(0.2, 'rgba(255,255,255,0.12)');
          grad.addColorStop(0.6, 'rgba(255,255,255,0.04)');
          grad.addColorStop(1, 'rgba(255,255,255,0)');

          ctx.fillStyle = grad;
          var radius = Math.min(12, Math.max(5, Math.round((bottomY - topY) / 2)));
          roundRect(ctx, gLeft, topY + 1, Math.max(1, gRight - gLeft), Math.max(3, bottomY - topY - 2), radius);
          ctx.fill();
        }
        ctx.restore();
      }
    };

    accountChart = new Chart(accountCanvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          data: counts,
          backgroundColor: labels.map(function () { return hexToRgba(activeColor, 0.72); }),
          borderColor: labels.map(function () { return activeColor; }),
          hoverBackgroundColor: labels.map(function () { return hexToRgba(activeColor, 0.92); }),
          hoverBorderColor: labels.map(function () { return activeColor; }),
          minBarLength: 28,
          maxBarThickness: 38,
          categoryPercentage: 0.78,
          barPercentage: 0.84,
          borderWidth: 2,
          borderRadius: 12,
          borderSkipped: false
        }]
      },
      options: {
        indexAxis: 'y',
        maintainAspectRatio: false,
        animation: { duration: 700, easing: 'easeOutQuart' },
        plugins: { 
          legend: { display: false },
          tooltip: {
            enabled: true,
            backgroundColor: '#ffffff',
            titleColor: '#0b1220',
            bodyColor: '#0b1220',
            borderColor: 'rgba(15,23,42,0.08)',
            borderWidth: 1,
            titleFont: { weight: 700 },
            padding: 10
          }
        },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0 } },
          y: { grid: { display: false }, ticks: { display: false } }
        },
        onHover: function (evt, activeEls) {
          accountCanvas.style.cursor = (activeEls && activeEls.length) ? 'default' : 'default';
        }
      },
      plugins: [accountBarGlassPlugin, accountBarLabelPlugin]
    });
  }

  function loadActivity() {
    var period = periodEl ? periodEl.value : '30d';
    var qs = new URLSearchParams();
    qs.set('period', period);
    if (selectedDivision) qs.set('division', selectedDivision);

    fetch('api/login_activity_stats.php?' + qs.toString(), { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) throw new Error('API request failed');
        return res.json();
      })
      .then(function (json) {
        var d = json && json.division ? json.division : { labels: [], counts: [] };
        var a = json && json.accounts ? json.accounts : { labels: [], counts: [] };

        var hasDivisionRows = (d.labels || []).length > 0;
        showNoData(!hasDivisionRows);
        if (!hasDivisionRows) return;

        ensureChartJs()
          .then(function () {
            restoreDivisionCanvas();
            renderDivision(d.labels || [], d.counts || []);
          })
          .catch(function () {
            renderDivisionFallback(d.labels || [], d.counts || []);
          });

        if (selectedDivision) {
          setDivisionVisible(false);
          setDrilldownVisible(true);
          if (subtitleEl) subtitleEl.textContent = 'Showing users from ' + selectedDivision;
          if (drilldownTitle) drilldownTitle.textContent = 'User Login Counts - ' + selectedDivision;
          ensureChartJs()
            .then(function () {
              renderAccounts(a.labels || [], a.counts || []);
            })
            .catch(function () {
              clearAccountsChart();
            });
        } else {
          setDivisionVisible(true);
          setDrilldownVisible(false);
          if (subtitleEl) subtitleEl.textContent = 'Track login patterns across divisions';
          clearAccountsChart();
        }
      })
      .catch(function () {
        showNoData(true);
      });
  }

  modalEl.addEventListener('shown.bs.modal', function () {
    selectedDivision = '';
    setDivisionVisible(true);
    setDrilldownVisible(false);
    if (subtitleEl) subtitleEl.textContent = 'Track login patterns across divisions';
    clearAccountsChart();
    loadActivity();
  });

  if (periodEl) {
    periodEl.addEventListener('change', function () {
      selectedDivision = '';
      setDivisionVisible(true);
      setDrilldownVisible(false);
      if (subtitleEl) subtitleEl.textContent = 'Track login patterns across divisions';
      loadActivity();
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      selectedDivision = '';
      setDivisionVisible(true);
      setDrilldownVisible(false);
      if (subtitleEl) subtitleEl.textContent = 'Track login patterns across divisions';
      loadActivity();
    });
  }
})();
