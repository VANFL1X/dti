<?php
require_once __DIR__ . '/includes/init.php';
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$user = $_SESSION['user'];
if (!user_has_division($user, 'Admin Division')) {
    echo 'Access denied';
    exit;
}

// Fetch users and their supplies
$users = [];
$res = $mysqli->query("SELECT id, first_name, last_name, email, division, avatar FROM users ORDER BY last_name, first_name");
if ($res) {
    while ($r = $res->fetch_assoc()) {
    // Resolve avatar path (support uploads/ and legacy data/avatars/)
    $avatar = trim((string)($r['avatar'] ?? ''));
    if ($avatar !== '') {
      $uploadPath = __DIR__ . '/uploads/' . $avatar;
      $legacyPath = __DIR__ . '/data/avatars/' . $avatar;
      if (is_file($uploadPath)) {
        $r['avatar'] = 'uploads/' . $avatar;
      } elseif (is_file($legacyPath)) {
        $r['avatar'] = 'data/avatars/' . $avatar;
      } else {
        $r['avatar'] = '';
      }
    } else {
      $r['avatar'] = '';
    }
    $users[] = $r;
    }
}

// Fetch supplies per user
$supplies = [];
$sRes = $mysqli->query("SELECT * FROM user_supplies");
if ($sRes) {
    while ($s = $sRes->fetch_assoc()) {
        $supplies[$s['user_id']][] = $s;
    }
}

// Fetch recent supply requests (used to base variant options in inventory add form)
$sreq = [];
$sreq_items = [];
$srRes = $mysqli->query("SELECT item, variant FROM supply_requests WHERE item <> '' AND variant <> ''");
if ($srRes) {
  while ($r = $srRes->fetch_assoc()) {
    $itOrig = trim((string)($r['item'] ?? ''));
    if ($itOrig === '') continue;
    $itKey = mb_strtolower($itOrig);
    $var = trim((string)($r['variant'] ?? ''));
    if ($var === '' || $var === '0') continue;
    if (!isset($sreq[$itKey])) $sreq[$itKey] = [];
    if (!in_array($var, $sreq[$itKey], true)) $sreq[$itKey][] = $var;
    if (!in_array($itOrig, $sreq_items, true)) $sreq_items[] = $itOrig;
  }
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
    .section-head{display:flex;align-items:center;gap:12px;margin-bottom:12px}
    .section-icon{width:38px;height:38px;border-radius:10px;background:rgba(37,99,235,0.1);color:var(--primary-600);display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;border:1px solid rgba(37,99,235,0.18)}
    .inventory-panel{background:var(--surface);border:1px solid var(--surface-contrast);border-radius:12px;padding:14px;color:var(--text)}
    .avatar-circle{width:36px;height:36px;border-radius:50%;background:rgba(148,163,184,0.22);display:inline-flex;align-items:center;justify-content:center;overflow:hidden}
    .avatar-circle img{width:100%;height:100%;object-fit:cover}
    .avatar-circle.initials{font-weight:600;color:var(--text-dim)}
    .inventory-low{color:#b11}
    .inventory-table td, .inventory-table th{vertical-align:middle}
    .inventory-table thead th{font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);white-space:nowrap}
    .inventory-table td{padding-top:.75rem;padding-bottom:.75rem}
    /* Sticky first column (profile/account) */
    .inventory-table th.sticky-col, .inventory-table td.sticky-col{position:sticky;left:0;background:var(--surface);z-index:3}
    /* Slight border so sticky column stands out */
    .inventory-table td.sticky-col{border-right:1px solid var(--surface-contrast)}
    .inventory-controls{display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
    .inventory-controls .form-control{flex:1;min-width:280px}
    .inventory-controls .btn{height:38px;display:flex;align-items:center;padding:0.5rem 1.25rem}
    .inventory-col-qty,.inventory-col-date,.inventory-col-action{text-align:center}
    .inventory-col-date{white-space:nowrap}
    .inventory-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    @media (max-width: 768px){
      .inventory-controls .form-control{min-width:100%;order:1}
      .inventory-controls .btn{flex:1;justify-content:center}
      .inventory-table th.sticky-col, .inventory-table td.sticky-col{position:static}
      .inventory-pagination{justify-content:center}
    }
    .inventory-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:1050;padding:16px}
    .inventory-modal{background:var(--surface);color:var(--text);border:1px solid var(--surface-contrast);border-radius:12px;max-width:760px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,0.25)}
    .inventory-modal-head{padding:14px 16px;border-bottom:1px solid var(--surface-contrast);display:flex;align-items:center;justify-content:space-between}
    .inventory-modal-body{padding:16px}
    .dark-mode .inventory-table th.sticky-col, .dark-mode .inventory-table td.sticky-col{background:var(--surface)}
    .dark-mode .inventory-panel{box-shadow:0 12px 28px rgba(2,6,23,0.45)}
    .dark-mode .inventory-modal-backdrop{background:rgba(2,6,23,0.72)}
    /* Variant listbox styling when many options are present */
    select.variant-select[size] { max-height: 220px; height: auto !important; overflow-y: auto !important; display: block !important; }
    </style>
    <style>
      /* Ensure footer stays at bottom on short pages */
      .dti-page-flex { display: flex; flex-direction: column; min-height: 100vh; }
      .dti-page-flex .inventory-shell { flex: 1 0 auto; }
    </style>
  </head>
  <body class="p-4 dti-page-flex">
    <?php include __DIR__ . '/includes/header.php'; ?>
    <div class="container mt-4 inventory-shell">
      <div class="section-head">
        <span class="section-icon"><i class="bi bi-box-seam"></i></span>
        <div>
          <h2 class="h5 mb-1">Inventory</h2>
        </div>
        
      </div>

      <div class="row g-3">
        <div class="col-12">
          <div id="inventory-area" class="inventory-panel">
            <p class="text-muted">Inventory overview.</p>
          </div>
        </div>
      </div>
    </div>

<script>
const supplies = <?php echo json_encode($supplies); ?>;
const supplyRequestsMap = <?php echo json_encode($sreq); ?>;
const supplyRequestItems = <?php echo json_encode($sreq_items); ?>;
const users = <?php echo json_encode($users); ?>;
const csrf = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>';

function renderUser(id) {
  const u = users.find(x => x.id == id);
  const area = document.getElementById('inventory-area');
  const userSup = supplies[id] || [];
  let html = `<h5>${u.last_name}, ${u.first_name} <small class="text-muted">${u.email}</small></h5>`;
    html += `<form id="inventory-form" class="row g-2 inventory-add-form" onsubmit="return submitForm(event)">`;
    html += '<div class="col-md-6"><label class="form-label small">Employee</label><select name="user_id" class="form-select" required><option value="">Select employee</option>' + userOptions + '</select></div>';
    html += '<div class="col-md-6">';
    html += '<div class="row g-3">';
    // Build item options from supplies data (use global `supplies` JS variable)
    try {
    let __itemsSet = new Set();
    try { Object.keys(supplies || {}).forEach(function(k){ (supplies[k]||[]).forEach(function(s){ if (s && s.item) __itemsSet.add(s.item); }); }); } catch(e) {}
    try { (supplyRequestItems || []).forEach(function(it){ if (it) __itemsSet.add(it); }); } catch(e) {}
    let __itemsArr = Array.from(__itemsSet).sort();
      let __itemsHtml = __itemsArr.map(function(it){ return '<option>' + escapeHtml(it) + '</option>'; }).join('') + '<option>Others</option>';
      html += '<div class="col-12"><label class="form-label small">Item</label><select name="item" id="modal-inv-item" required class="form-select"><option value="">Choose...</option>' + __itemsHtml + '</select>';
      html += '<div id="modalInvOtherWrap" class="mt-2 d-none"><input id="modalInvOtherInput" name="other_item" class="form-control" placeholder="Other item name"></div></div>';
    } catch(e) {
      // fallback to previous static list if anything goes wrong
      html += '<div class="col-12"><label class="form-label small">Item</label><select name="item" id="modal-inv-item" required class="form-select"><option value="">Choose...</option><option>Others</option></select>';
      html += '<div id="modalInvOtherWrap" class="mt-2 d-none"><input id="modalInvOtherInput" name="other_item" class="form-control" placeholder="Other item name"></div></div>';
    }
  html += '<div class="col-12"><label class="form-label small">Variant</label><input name="variant" class="form-control" placeholder="Variant"></div>';
  html += '<div class="col-12"><label class="form-label small">Qty</label><input name="quantity" type="number" min="0" required class="form-control" placeholder="0"></div>';
  html += '<div class="col-12"><label class="form-label small">Unit</label><select name="unit" class="form-select"><option value="">Select unit</option><option value="piece">piece</option><option value="pieces">pieces</option><option value="box">box</option><option value="boxes">boxes</option><option value="ream">ream</option><option value="reams">reams</option><option value="bottle">bottle</option><option value="bottles">bottles</option><option value="pack">pack</option><option value="packs">packs</option></select></div>';
  html += '</div></div>';
  html += '<div class="col-md-2 mt-2"><label class="form-label small">&nbsp;</label><div><button class="btn btn-success w-100">Save</button></div></div>';
  html += `</form>`;

  
  html += '<div class="mt-3"><table class="table inventory-table"><thead><tr><th>Account</th><th>Item Name</th><th>Variant</th><th>Stock Qty</th><th>Updated</th><th></th></tr></thead><tbody>';
  if (userSup.length === 0) {
    html += '<tr><td colspan="6" class="text-muted">No items yet.</td></tr>';
  }
  userSup.forEach(s => {
    const lowClass = '';
    const avatarHtml = u.avatar ? `<img src="${u.avatar}" class="avatar-circle me-2" alt="avatar">` : `<div class="avatar-circle initials me-2">${escapeHtml(((u.first_name||'').charAt(0) || '') + ((u.last_name||'').charAt(0) || ''))}</div>`;
    html += `<tr data-id="${s.id}">`;
    html += `<td class="${lowClass}"><div class="d-flex align-items-center">${avatarHtml}<div><div><strong>${escapeHtml(u.last_name + ', ' + u.first_name)}</strong></div><div class="small text-muted">${escapeHtml(u.email||'')}</div></div></div></td>`;
    html += `<td class="${lowClass}">${escapeHtml(s.item)}</td>`;
    html += `<td>${escapeHtml(s.variant||'')}</td>`;
    html += `<td class="${lowClass}">${s.quantity}</td>`;
    html += `<td>${escapeHtml(s.updated_at || '')}</td>`;
    html += `<td><button class="btn btn-sm btn-primary" onclick="openEdit(${id}, '${escapeJs(s.item)}', '${escapeJs(s.variant||'')}', ${s.quantity}, '${escapeJs(s.unit||'')}')">Edit</button></td>`;
    html += `</tr>`;
  });
  html += '</tbody></table></div>';
  area.innerHTML = html;

  // ensure per-user typeahead is initialized when Manage is clicked later
  try{ setupTypeahead(); }catch(e){}
  // wire up 'Other' toggle for per-user item select
  try {
    var invItem = document.getElementById('inv-item');
    var invOtherWrap = document.getElementById('invOtherWrap');
    var invOtherInput = document.getElementById('invOtherInput');
    if (invItem) {
      invItem.addEventListener('change', function(){
        if (invItem.value === 'Others') {
          if (invOtherWrap) invOtherWrap.style.display = 'block';
          if (invOtherInput) invOtherInput.setAttribute('required','required');
        } else {
          if (invOtherWrap) invOtherWrap.style.display = 'none';
          if (invOtherInput) invOtherInput.removeAttribute('required');
        }
      });
    }
  } catch (e) {}

}

function openEdit(userId, item, variant, qty, unit) {
  const area = document.getElementById('inventory-area');
  const form = area.querySelector('#inventory-form');
  if (!form) return;
  form.user_id.value = userId;
  // set item select or fallback to 'Others' + fill other_item
  try {
    var sel = form.querySelector('select[name="item"]');
    if (sel) {
      var found = Array.from(sel.options).some(opt => opt.text === item || opt.value === item);
      if (found) { sel.value = item; var other = form.querySelector('input[name="other_item"]'); if (other) { other.value = ''; other.removeAttribute('required'); var wrap = document.getElementById('invOtherWrap'); if (wrap) wrap.style.display = 'none'; } }
      else { sel.value = 'Others'; var other = form.querySelector('input[name="other_item"]'); if (other) { other.value = item; other.setAttribute('required','required'); var wrap = document.getElementById('invOtherWrap'); if (wrap) wrap.style.display = 'block'; } }
    } else {
      form.item.value = item;
    }
  } catch (e) { form.item.value = item; }
  form.variant.value = variant;
  form.quantity.value = qty;
  form.unit.value = unit;
  form.item.focus();
}

function submitForm(e) {
  e.preventDefault();
  const f = e && e.target && e.target.tagName === 'FORM' ? e.target : document.getElementById('inventory-form');
  if (!f) return false;
  if (!f.checkValidity()) { f.classList.add('was-validated'); return false; }
  const data = new FormData();
  data.append('csrf_token', csrf);
  data.append('user_id', f.user_id.value);
  data.append('item', f.item.value.trim());
  // If 'Others' selected, prefer other_item input if present
  var itemVal = (f.item.value || '').trim();
  if (itemVal === 'Others' && (f.other_item && (f.other_item.value || '').trim() !== '')) {
    itemVal = f.other_item.value.trim();
  }
  data.append('item', itemVal);
  data.append('variant', (f.variant.value || '').trim());
  data.append('quantity', f.quantity.value);
  data.append('unit', (f.unit.value || '').trim());
  var submitBtn = f.querySelector('button[type="submit"]');
  var submitBtnOriginal = submitBtn ? submitBtn.innerHTML : null;
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
  }

  // If 'Others' selected, ensure other_item is used (forms may differ)
  if ((f.item && f.item.value === 'Others') && (f.other_item && (f.other_item.value || '').trim() !== '')) {
    data.set('item', (f.other_item.value || '').trim());
  }

  // Optimistic UI: hide modal and show temporary success so interaction feels instant
  var optimisticShown = false;
  try {
    var modalEl = document.getElementById('inventoryModal');
    var modal = modalEl ? (window.bootstrap ? window.bootstrap.Modal.getInstance(modalEl) : null) : null;
    if (modal) modal.hide();
    f.reset();
    f.classList.remove('was-validated');
    var tmp = document.createElement('div');
    tmp.className = 'alert alert-success m-3';
    tmp.textContent = 'Inventory saved';
    var container = document.querySelector('.container');
    if (container) container.insertBefore(tmp, container.firstChild);
    setTimeout(function(){ tmp.remove(); }, 3000);
    optimisticShown = true;
  } catch (e) {
    // ignore optimistic UI errors
  }

  fetch('api/inventory_update.php', { method: 'POST', body: data })
    .then(res => res.json())
    .then(j => {
      if (j && j.success) {
        if (!optimisticShown) {
          try {
            var modalEl2 = document.getElementById('inventoryModal');
            var modal2 = modalEl2 ? (window.bootstrap ? window.bootstrap.Modal.getInstance(modalEl2) : null) : null;
            if (modal2) modal2.hide();
            f.reset();
            f.classList.remove('was-validated');
            var tmp2 = document.createElement('div');
            tmp2.className = 'alert alert-success m-3';
            tmp2.textContent = j.message || 'Inventory saved';
            var container2 = document.querySelector('.container');
            if (container2) container2.insertBefore(tmp2, container2.firstChild);
            setTimeout(function(){ tmp2.remove(); }, 3000);
          } catch (e) {}
        }
        // Optionally refresh view here if needed
      } else {
        try {
          var tmp3 = document.createElement('div');
          tmp3.className = 'alert alert-danger m-3';
          tmp3.textContent = 'Error: ' + (j && j.message ? j.message : 'Unknown error');
          var container3 = document.querySelector('.container');
          if (container3) container3.insertBefore(tmp3, container3.firstChild);
          setTimeout(function(){ tmp3.remove(); }, 5000);
        } catch (e) {}
      }
    })
    .catch(err => {
      try {
        var tmpErr = document.createElement('div');
        tmpErr.className = 'alert alert-danger m-3';
        tmpErr.textContent = 'Network error';
        var containerErr = document.querySelector('.container');
        if (containerErr) containerErr.insertBefore(tmpErr, containerErr.firstChild);
        setTimeout(function(){ tmpErr.remove(); }, 5000);
      } catch (e) {}
    })
    .finally(function(){ if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = submitBtnOriginal; } });

  return false;
}

// Typeahead setup
function setupTypeahead() {
  const input = document.getElementById('user-display');
  const hidden = document.getElementById('user_id');
  const box = document.getElementById('user-suggestions');
  if (!input || !hidden || !box) return;

  function showMatches(q) {
    const v = (q||'').trim().toLowerCase();
    box.innerHTML = '';
    if (v === '') { box.style.display = 'none'; return; }
    const matches = users.filter(u => {
      const txt = (u.first_name + ' ' + u.last_name + ' ' + u.email + ' ' + (u.division||'')).toLowerCase();
      return txt.indexOf(v) !== -1;
    }).slice(0, 20);
    if (matches.length === 0) { box.style.display = 'none'; return; }
    matches.forEach(m => {
      const el = document.createElement('div');
      el.className = 'typeahead-item';
      el.tabIndex = 0;
      el.dataset.id = m.id;
      el.innerHTML = `<div><strong>${m.last_name}, ${m.first_name}</strong> <small class="text-muted">${m.email}</small></div><div class="small text-muted">${m.division||''}</div>`;
      el.addEventListener('click', function(){
        input.value = `${m.last_name}, ${m.first_name} \u2014 ${m.email}`;
        hidden.value = m.id;
        box.style.display = 'none';
        renderUser(m.id);
      });
      el.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); el.click(); } });
      box.appendChild(el);
    });
    box.style.display = 'block';
  }

  input.addEventListener('input', function(){
    showMatches(this.value);
  });

  input.addEventListener('focus', function(){ showMatches(this.value); });
  document.addEventListener('click', function(e){ if (!input.contains(e.target) && !box.contains(e.target)) box.style.display = 'none'; });
}

// initialize typeahead after initial render
setTimeout(()=>{ try{ setupTypeahead(); }catch(e){} }, 200);

// Inventory overview state for search/pagination
let inventoryRows = [];
let inventoryFilterQuery = '';
let inventoryPage = 1;
let inventoryPerPage = 10;

function formatDateShort(s) {
  if (!s) return '';
  // Try to parse common MySQL DATETIME format
  const d = new Date(s);
  if (!isNaN(d)) {
    return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
  }
  // Fallback: attempt YYYY-MM-DD
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (m) {
    const dt = new Date(parseInt(m[1],10), parseInt(m[2],10)-1, parseInt(m[3],10));
    return dt.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
  }
  return s;
}

function drawInventory() {
  const area = document.getElementById('inventory-area');
  const q = (inventoryFilterQuery || '').toLowerCase().trim();
  const filtered = inventoryRows.filter(r => {
    if (!q) return true;
    const u = r.user || {};
    const s = r.supply || {};
    const txt = ((u.first_name||'') + ' ' + (u.last_name||'') + ' ' + (u.email||'') + ' ' + (s.item||'') + ' ' + (s.variant||'')).toLowerCase();
    return txt.indexOf(q) !== -1;
  });

  const total = filtered.length;
  const pages = Math.max(1, Math.ceil(total / inventoryPerPage));
  if (inventoryPage > pages) inventoryPage = pages;
  const start = (inventoryPage - 1) * inventoryPerPage;
  const pageRows = filtered.slice(start, start + inventoryPerPage);
  const userOptions = users.map(u => `<option value="${u.id}">${escapeHtml(u.last_name + ', ' + u.first_name + ' (' + u.email + ')')}</option>`).join('');

  let html = ``;
  html += '<div class="inventory-controls">';
  html += `<input id="inventory-filter" class="form-control" placeholder="Search user, item or variant" value="${escapeHtml(inventoryFilterQuery)}">`;
  html += '<button id="inventory-search-btn" class="btn btn-primary" type="button">Search</button>';
  html += '<button id="open-inventory-modal" class="btn btn-success" type="button">Add Inventory</button>';
  html += '</div>';

  html += '<div class="mt-1 table-responsive"><table class="table inventory-table mb-0"><thead><tr><th class="sticky-col">Account</th><th>Item Name</th><th>Variant</th><th class="inventory-col-qty">Stock Qty</th><th class="inventory-col-date">Updated</th><th class="inventory-col-action">Action</th></tr></thead><tbody>';
  if (pageRows.length === 0) {
    html += '<tr><td colspan="6" class="text-muted">No items yet.</td></tr>';
  }
  pageRows.forEach(r => {
    const u = r.user || { first_name:'', last_name:'', email:'', avatar:null };
    const s = r.supply || {};
    const lowClass = '';
    const avatarHtml = u.avatar ? `<img src="${u.avatar}" class="avatar-circle me-2" alt="avatar">` : `<div class="avatar-circle initials me-2">${escapeHtml(((u.first_name||'').charAt(0) || '') + ((u.last_name||'').charAt(0) || ''))}</div>`;
    html += `<tr data-id="${s.id}">`;
    html += `<td class="sticky-col ${lowClass}"><div class="d-flex align-items-center">${avatarHtml}<div><div><strong>${escapeHtml(u.last_name + ', ' + u.first_name)}</strong></div><div class="small text-muted">${escapeHtml(u.email||'')}</div></div></div></td>`;
    html += `<td class="${lowClass}">${escapeHtml(s.item)}</td>`;
    html += `<td>${escapeHtml(s.variant||'')}</td>`;
    html += `<td class="${lowClass} inventory-col-qty">${s.quantity}</td>`;
    html += `<td class="inventory-col-date">${escapeHtml(formatDateShort(s.updated_at || ''))}</td>`;
    html += `<td class="inventory-col-action"><button class="btn btn-primary" onclick="openEditModal(${u.id}, ${s.id}, '${escapeJs(s.item)}', '${escapeJs(s.variant||'')}', ${s.quantity}, '${escapeJs(s.unit||'')}')">Edit</button></td>`;
    html += `</tr>`;
  });
  html += '</tbody></table></div>';

  // pagination
  html += '<div class="inventory-pagination mt-2">';
  html += `<div class="small text-muted">Showing ${start+1 > total ? 0 : start+1}â€“${Math.min(start+pageRows.length,total)} of ${total}</div>`;
  html += '<div>';
  html += `<button id="inv-prev" class="btn btn-outline-secondary me-1" ${inventoryPage<=1? 'disabled' : ''}>&lt; Prev</button>`;
  html += `<span class="mx-1">Page ${inventoryPage} / ${pages}</span>`;
  html += `<button id="inv-next" class="btn btn-outline-secondary ms-1" ${inventoryPage>=pages? 'disabled' : ''}>Next &gt;</button>`;
  html += '</div></div>';

  // modal (used for both Add and Edit) - Bootstrap-style layout
  const isEditMode = modalEditItemId !== null;
  const modalTitle = isEditMode ? `Edit Inventory Entry` : `Add Inventory Entry`;
  html += '<div class="modal fade" id="inventoryModal" tabindex="-1" aria-hidden="true">';
  html += '<div class="modal-dialog modal-lg modal-dialog-centered">';
  html += '<div class="modal-content">';
  html += '<div class="modal-header">';
  html += '<h5 id="inventory-modal-title" class="modal-title mb-0">' + escapeHtml(modalTitle) + '</h5>';
  html += '<button type="button" id="close-inventory-modal" class="btn btn-light">Close</button>';
  html += '</div>';
  html += '<div class="modal-body">';
  html += '<form id="inventory-form-overview" class="row g-3 inventory-add-form" onsubmit="return submitForm(event)">';
  html += '<div class="col-12"><label class="form-label small">Employee</label><select name="user_id" class="form-select" required><option value="">Select employee</option>' + userOptions + '</select></div>';
  html += '<div class="col-12"><label class="form-label small">Item</label><select name="item" id="modal-inv-item" required class="form-select"><option value="">Choose...</option>' +
    '<option>Bond Paper</option>' +
    '<option>Photo Paper</option>' +
    '<option>Folder</option>' +
    '<option>Envelope</option>' +
    '<option>Stapler</option>' +
    '<option>Staples</option>' +
    '<option>Ballpen</option>' +
    '<option>Gel Pen</option>' +
    '<option>Sticker Paper</option>' +
    '<option>Stamp Pad</option>' +
    '<option>Correction Tape</option>' +
    '<option>Binder Clip</option>' +
    '<option>Fastener</option>' +
    '<option>Tissue</option>' +
    '<option>Acetate</option>' +
    '<option>Air Freshener</option>' +
    '<option>Alcohol</option>' +
    '<option>Battery</option>' +
    '<option>Binding and Punching Machine</option>' +
    '<option>Binding Ring/Com</option>' +
    '<option>Broom</option>' +
    '<option>Calculator</option>' +
    '<option>Carbon Film</option>' +
    '<option>Cartolina</option>' +
    '<option>Chalk</option>' +
    '<option>Cleaner</option>' +
    '<option>Cleanser</option>' +
    '<option>Clearbook</option>' +
    '<option>Clip</option>' +
    '<option>Computer Continuous Form</option>' +
    '<option>Computer Mouse</option>' +
    '<option>Cutter/Utility Knife</option>' +
    '<option>Data File Box</option>' +
    '<option>Data Folder</option>' +
    '<option>Dater Stamp</option>' +
    '<option>Detergent Powder</option>' +
    '<option>Digital Voice Recorder</option>' +
    '<option>Disinfectant Spray</option>' +
    '<option>Drum Cart</option>' +
    '<option>Dustpan</option>' +
    '<option>Index Tab</option>' +
    '<option>Ink Cartridge</option>' +
    '<option>Eraser</option>' +
    '<option>External Hard Drive</option>' +
    '<option>File Organizer</option>' +
    '<option>File Tab Divider</option>' +
    '<option>Furniture Cleaner</option>' +
    '<option>Glue</option>' +
    '<option>Hand Sanitizer</option>' +
    '<option>Hand Soap</option>' +
    '<option>Notepad</option>' +
    '<option>Pad Paper</option>' +
    '<option>Paper Clip</option>' +
    '<option>Paper Shedder</option>' +
    '<option>Paper Timmer/Cutting Machine</option>' +
    '<option>Paper</option>' +
    '<option>Marker</option>' +
    '<option>Pencil Sharpener</option>' +
    '<option>Record Book</option>' +
    '<option>Ribbon Cart</option>' +
    '<option>Ribbon Cartridge</option>' +
    '<option>Rubber Band</option>' +
    '<option>Ruler</option>' +
    '<option>Scissors</option>' +
    '<option>Scouring Pad</option>' +
    '<option>Sign Pen</option>' +
    '<option>Staple Remover</option>' +
    '<option>Staple Wire</option>' +
    '<option>Steno Notebook</option>' +
    '<option>Stapler</option>' +
    '<option>Tape</option>' +
    '<option>Toner Cart</option>' +
    '<option>Toner Cartridge</option>' +
    '<option>Trashbag</option>' +
    '<option>Twine</option>' +
    '<option>Wrapping Paper</option>' +
    '<option>Others</option></select></div>';
  html += '<div class="col-12"><div id="modalInvOtherWrap" class="mt-2 d-none"><input id="modalInvOtherInput" name="other_item" class="form-control" placeholder="Other item name"></div></div>';
  html += '<div id="modalInvVariantWrap" class="col-12"><label class="form-label small">Variant / Type</label><select name="variant" id="modal-inv-variant" class="form-select variant-select" required><option value="">Choose...</option><option>Short</option><option>Long</option><option>A4</option><option>Expanding</option></select></div>';
  html += '<div class="col-12"><label class="form-label small">Qty</label><input name="quantity" type="number" min="0" required class="form-control" placeholder="0"></div>';
  html += '<div class="col-12"><label class="form-label small">Unit</label><select name="unit" class="form-select"><option value="">Choose...</option><option>piece</option><option>pieces</option><option>box</option><option>boxes</option><option>pack</option><option>packs</option><option>bottle</option><option>bottles</option><option>ream</option><option>reams</option><option>roll</option><option>can</option><option>gallon</option><option>bundle</option><option>tube</option><option>pouch</option><option>cart</option><option>set</option><option>jar</option><option>pad</option><option>book</option></select></div>';
  html += '<div class="col-12 d-flex gap-2 justify-content-end mt-3">';
  html += '<button type="button" id="cancel-inventory-modal" class="btn btn-outline-secondary">Cancel</button>';
  html += '<button class="btn btn-success" type="submit">Save</button>';
  html += '</div>';
  html += '</form>';
  html += '</div>';
  html += '</div></div></div>';

  area.innerHTML = html;

  // wire up controls
  const filterEl = document.getElementById('inventory-filter');
  const searchBtn = document.getElementById('inventory-search-btn');
  const prev = document.getElementById('inv-prev');
  const next = document.getElementById('inv-next');
  const openModalBtn = document.getElementById('open-inventory-modal');
  const closeModalBtn = document.getElementById('close-inventory-modal');
  const cancelModalBtn = document.getElementById('cancel-inventory-modal');
  const modalEl = document.getElementById('inventoryModal');
  let bsModal = null;
  if (modalEl && typeof bootstrap !== 'undefined') {
    try { bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false }); } catch(e) { bsModal = null; }
  }
  function applySearch(){
    if (!filterEl) return;
    inventoryFilterQuery = filterEl.value;
    inventoryPage = 1;
    drawInventory();
  }
  if (filterEl) filterEl.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); applySearch(); } });
  if (searchBtn) searchBtn.addEventListener('click', applySearch);
  if (prev) prev.addEventListener('click', function(){ if (inventoryPage>1){ inventoryPage--; drawInventory(); } });
  if (next) next.addEventListener('click', function(){ if (inventoryPage<pages){ inventoryPage++; drawInventory(); } });
  if (openModalBtn) {
    openModalBtn.addEventListener('click', function(){
      resetEditModal();
      if (bsModal) {
        bsModal.show();
        modalEl.addEventListener('shown.bs.modal', function onShown(){
          modalEl.removeEventListener('shown.bs.modal', onShown);
          const firstInput = modalEl.querySelector('select[name="user_id"]');
          if (firstInput) firstInput.focus();
        });
      } else {
        const backdrop = document.getElementById('inventory-modal-backdrop');
        if (backdrop) { backdrop.style.display = 'flex'; const firstInput = backdrop.querySelector('select[name="user_id"]'); if (firstInput) firstInput.focus(); }
      }
      // ensure modal variant select is populated for current item
      try { const mid = document.getElementById('modal-inv-item'); if (mid) populateVariantOptions(mid.value); } catch(e) {}
    });
  }
  // wire up modal item 'Other' toggle
  try {
    var modalInvItem = document.getElementById('modal-inv-item');
    var modalInvOtherWrap = document.getElementById('modalInvOtherWrap');
    var modalInvOtherInput = document.getElementById('modalInvOtherInput');
    if (modalInvItem) {
      modalInvItem.addEventListener('change', function(){
        // toggle Other input
        if (modalInvItem.value === 'Others') {
          if (modalInvOtherWrap) modalInvOtherWrap.classList.remove('d-none');
          if (modalInvOtherInput) modalInvOtherInput.setAttribute('required','required');
        } else {
          if (modalInvOtherWrap) modalInvOtherWrap.classList.add('d-none');
          if (modalInvOtherInput) modalInvOtherInput.removeAttribute('required');
        }
        // populate variant options for selected item and open if long list
        try { populateVariantOptions(modalInvItem.value, '', true); } catch(e) {}
      });
    }
  } catch (e) {}

  // Return variant options array based on item name
  function getVariantOptions(item) {
    const v = (item||'').toString();
    const trimmed = v.trim();
    // First prefer variants that appeared in supply requests for this item
    try {
      var __supplyVariants = [];
      try {
        var lk = (trimmed || '').toString().toLowerCase();
        if (supplyRequestsMap && supplyRequestsMap[lk]) {
          (supplyRequestsMap[lk]||[]).forEach(function(v){ if (v) __supplyVariants.push(v); });
        }
      } catch(e) {}
      // fallback: collect variants that exist in user_supplies
      if (!__supplyVariants.length) {
        Object.keys(supplies || {}).forEach(function(k){ (supplies[k]||[]).forEach(function(s){ if (s && (s.item||'').toString().trim() === trimmed && s.variant) __supplyVariants.push(s.variant); }); });
      }
      __supplyVariants = __supplyVariants.map(function(x){ return (x||'').toString().trim(); }).filter(function(x){ return x !== '' && x !== '0'; });
      __supplyVariants = Array.from(new Set(__supplyVariants));
      var __supplyFound = __supplyVariants.length > 0;
    } catch(e) { /* ignore and fall back to map */ }

    // Explicit map borrowed from supply modal for consistent options
    const variantMap = {
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
      'Stamp Pad': ['Felt','Ink'],
      'Tape': ['electrical','masking 24mm','masking 48mm','packaging 48mm','transparent 24mm','transparent 48mm'],
      'Tissue': ['interfolded paper towel','toilet tissue paper 2ply'],
      'Toner Cart': ['BROTHER TN-2130 Black','BROTHER TN-3320 Black','BROTHER TN-3350 Black','BROTHER TN-3478 Black','HP CE400A Black','HP CE401A Cyan','HP CE402A Yellow','HP CE403A Magenta','HP Q7553A Black','SAMSUNG ML-D2850B Black','SAMSUNG MLT-D104S Black','SAMSUNG MLT-D108S Black','SAMSUNG SCX-D6555A Black'],
      'Toner Cartridge': ['Brother TN-456 Black High Yield','Brother TN-456 Cyan High Yield','Brother TN-456 Magenta High','Brother TN-456 Yellow High Yield','Canon CRG-324 II','HP CB435A Black','HP CE255A Black','HP CE278A Black','HP CE285A (HP85A) Black','HP CE310A Black','HP CE311A Cyan','HP CE312A Yellow','HP CE313A Magenta','HP CE505A Black','HP CF217A (HP17A) Black','HP CF226A (HP26A) Black','HP CF281A (HP81A) Black','HP CF283A (HP83A) LaserJet','HP CF283XC (HP83X) Blk Contract L','HP CF287A (HP87) Black','HP CF325XC (HP25X) Black LaserJet','HP CF350A Black LJ','HP CF351A Cyan LJ','HP CF352A Yellow LJ','HP CF353A Magenta LJ','HP CF360A (HP508A) Black','HP CF361A (HP508A) Cyan','HP CF362A (HP508A) Yellow','HP CF363A (HP508A) Magenta','HP CF400A (HP201A) Black','HP CF401A (HP201A) Cyan','HP CF402A (HP201A) Yellow','HP CF403A (HP201A) Magenta','HP CF410A (HP410A) black','HP CF411A (HP410A) Cyan','HP CF412A (HP410A) Yellow','HP CF413A (HP410A) Magenta','HP Q2612A Black'],
      'Trashbag': ['XXL size'],
      'Twine': ['plastic'],
      'Wrapping Paper': ['kraft']
    };

    if (variantMap.hasOwnProperty(trimmed)) {
      // If we have supply-derived variants, merge them with the map for completeness
      try {
        if (__supplyFound) {
          var merged = Array.from(new Set((variantMap[trimmed] || []).concat(__supplyVariants)));
          return merged;
        }
      } catch(e) {}
      return variantMap[trimmed];
    }

    const lower = trimmed.toLowerCase();
    // Explicitly hide variants for Ballpen and Gelpen (no variants)
    if (lower === 'ballpen' || lower === 'ball pen') return [];
    if (lower === 'gel' || lower === 'gelpen' || lower === 'gel pen') return [];
    if (/paper|bond|photo|cartolina|pad paper|pad/i.test(lower)) return ['A4'];
    if (/folder|data folder|file folder/i.test(lower)) return ['Expanding'];
    if (/pen|ballpen|gel|marker|sign/i.test(lower)) return ['Short','Long'];
    if (/stapler|staples|staple/i.test(lower)) return ['Standard'];
    // If supply-derived variants exist, return them as a sensible fallback
    if (typeof __supplyFound !== 'undefined' && __supplyFound) return __supplyVariants;
    return ['Short','Long','A4','Expanding'];
  }

  // Populate variant select element (works for modal and inline forms)
  function populateVariantOptionsForSelect(selEl, item, selectedVariant, openNow) {
    if (!selEl) return;
    selectedVariant = selectedVariant || '';
    const opts = getVariantOptions(item);
    selEl.innerHTML = '<option value="">Choose...</option>' + opts.map(o => '<option>' + o + '</option>').join('');
    // Hide wrapper if there are no options
    try {
      const wrap = selEl.closest('#modalInvVariantWrap') || selEl.parentNode;
      if (!opts || opts.length === 0) {
        if (wrap) wrap.classList.add('d-none');
        selEl.removeAttribute('required');
        return;
      } else {
        if (wrap) wrap.classList.remove('d-none');
        selEl.setAttribute('required','required');
      }
    } catch(e) {}
    // If many options, make the select expand to a scrollable list while focused/clicked
    const MAX_VISIBLE = 20;
    selEl.classList.toggle('variant-select', opts.length > MAX_VISIBLE);
    if (opts.length > MAX_VISIBLE) {
      makeSelectExpandable(selEl, MAX_VISIBLE);
      // If this is the modal's main variant select we want it expanded immediately
      try {
        const isModalSelect = selEl.id === 'modal-inv-variant' || !!selEl.closest('#inventoryModal');
        if (isModalSelect || openNow) {
          selEl.setAttribute('size', String(MAX_VISIBLE));
          try { selEl.focus(); } catch (e) {}
        }
      } catch (e) {}
    } else {
      selEl.removeAttribute('size');
    }
    if (selectedVariant) {
      const exists = Array.from(selEl.options).some(opt => opt.text === selectedVariant || opt.value === selectedVariant);
      if (!exists) {
        const opt = document.createElement('option'); opt.text = selectedVariant; opt.value = selectedVariant; selEl.add(opt);
      }
      selEl.value = selectedVariant;
    }
  }

  // Make a select temporarily expand to a listbox of given size while interacted with
  function makeSelectExpandable(sel, size) {
    if (!sel) return;
    // avoid adding multiple listeners
    if (sel._expandable) return;
    sel._expandable = true;
    function open() { sel.setAttribute('size', String(size)); sel.classList.add('variant-select'); }
    function close() { sel.removeAttribute('size'); }
    // Use pointerdown/mousedown to expand immediately before default dropdown opens
    sel.addEventListener('pointerdown', function(e){ open(); });
    sel.addEventListener('mousedown', function(e){ open(); });
    sel.addEventListener('focus', open);
    sel.addEventListener('blur', function(){ setTimeout(close, 150); });
    sel.addEventListener('change', close);
  }

  // Backwards-compatible wrapper that targets the modal variant select
  function populateVariantOptions(item, selectedVariant, openNow) {
    const vsel = document.getElementById('modal-inv-variant');
    if (!vsel) return;
    populateVariantOptionsForSelect(vsel, item, selectedVariant, !!openNow);
  }
  function closeModal(){ 
    if (bsModal) bsModal.hide();
    else { const backdrop = document.getElementById('inventory-modal-backdrop'); if (backdrop) backdrop.style.display = 'none'; }
    resetEditModal();
  }
  if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
  if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);
}

// Generic listener: when any item select changes inside a form, update that form's variant select
document.addEventListener('change', function(e){
  try {
    const tgt = e.target;
    if (!tgt) return;
    if (tgt.tagName === 'SELECT' && tgt.name === 'item') {
      const form = tgt.closest('form');
      if (!form) return;
      const variantSel = form.querySelector('select[name="variant"]');
      // If variant is an input (legacy), try to replace it with a select to enable options
      if (!variantSel) {
        const variantInput = form.querySelector('input[name="variant"]');
        if (variantInput) {
          const sel = document.createElement('select');
          sel.name = 'variant';
          sel.className = (variantInput.className || 'form-control') + ' variant-select';
          variantInput.parentNode.replaceChild(sel, variantInput);
          populateVariantOptionsForSelect(sel, tgt.value);
          return;
        }
        return;
      }
      populateVariantOptionsForSelect(variantSel, tgt.value, true);
    }
  } catch (e) { /* silent */ }
});

// Render full inventory across users (overview) when page loads
function renderAll() {
  inventoryRows = [];
  Object.keys(supplies).forEach(uid => {
    const user = users.find(u => u.id == uid) || { first_name: '', last_name: '', email: '', avatar: null };
    (supplies[uid] || []).forEach(s => inventoryRows.push({ user: user, supply: s }));
  });
  inventoryPage = 1;
  drawInventory();
}

// Edit modal state
let modalEditItemId = null;

function resetEditModal() {
  modalEditItemId = null;
  const form = document.getElementById('inventory-form-overview');
  if (form) {
    form.reset();
    form.user_id.value = '';
  }
  const title = document.getElementById('inventory-modal-title');
  if (title) title.textContent = 'Add Inventory Entry';
}

function openEditModal(userId, itemId, item, variant, qty, unit) {
  modalEditItemId = itemId;
  const form = document.getElementById('inventory-form-overview');
  const backdrop = document.getElementById('inventory-modal-backdrop');
  const title = document.getElementById('inventory-modal-title');
  
  if (form) {
    form.user_id.value = userId;
    try {
      var sel = form.querySelector('select[name="item"]');
      if (sel) {
        var found = Array.from(sel.options).some(opt => opt.text === item || opt.value === item);
        if (found) { sel.value = item; var other = form.querySelector('input[name="other_item"]'); if (other) { other.value = ''; other.removeAttribute('required'); var wrap = document.getElementById('modalInvOtherWrap'); if (wrap) wrap.classList.add('d-none'); } }
        else { sel.value = 'Others'; var other = form.querySelector('input[name="other_item"]'); if (other) { other.value = item; other.setAttribute('required','required'); var wrap = document.getElementById('modalInvOtherWrap'); if (wrap) wrap.classList.remove('d-none'); } }
      } else {
        form.item.value = item;
      }
    } catch (e) { form.item.value = item; }
    // ensure variant select/options exist and are populated
    try {
      var vsel = form.querySelector('select[name="variant"]');
      if (!vsel) {
        var vin = form.querySelector('input[name="variant"]');
        if (vin) {
          var nsel = document.createElement('select'); nsel.name = 'variant'; nsel.className = (vin.className||'form-control') + ' variant-select'; vin.parentNode.replaceChild(nsel, vin); vsel = nsel;
        }
      }
      if (vsel) populateVariantOptionsForSelect(vsel, sel ? sel.value : item, variant);
    } catch (e) { try { form.variant.value = variant; } catch(e){} }
    form.quantity.value = qty;
    form.unit.value = unit;
  }
  if (title) title.textContent = 'Edit Inventory Entry';
  if (backdrop) {
    backdrop.style.display = 'flex';
    if (form) form.item.focus();
  }
}

// show overview on page load
setTimeout(()=>{ try{ renderAll(); }catch(e){} }, 60);

function escapeHtml(s) { return (s+'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escapeJs(s) { return (s+'').replace(/'/g, "\\'").replace(/"/g,'\"'); }

// attach click handlers
// delegate clicks from user list
// Main left-side typeahead search (shows suggestions, selects user)
// main search removed: left-side "Find user" UI is removed to always show overview
</script>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/script.js?v=20260407k2"></script>
  </body>
</html>











