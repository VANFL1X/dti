<?php
require_once __DIR__ . '/includes/init.php';

$user = $_SESSION['user'] ?? null;

// Check if user is from Admin Division
if (!$user || !user_has_division($user, 'Admin Division')) {
    header('Location: login.php');
    exit;
}

$mysqli = getDB();

// Get all divisions from users
$divisionRes = $mysqli->query("SELECT DISTINCT division FROM users WHERE division != '' ORDER BY division");
$allDivisions = [];
while ($row = $divisionRes->fetch_assoc()) {
    // Handle comma-separated divisions
    $divs = array_map('trim', explode(',', $row['division']));
    foreach ($divs as $d) {
        if ($d && !in_array($d, $allDivisions)) {
            $allDivisions[] = $d;
        }
    }
}
sort($allDivisions);

// Handle form submission for adding/updating deadline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_deadline') {
        $division = $_POST['division'] ?? '';
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $report_type = trim((string)($_POST['report_type'] ?? ''));
        $deadline_date = $_POST['deadline_date'] ?? '';
        $deadline_time = $_POST['deadline_time'] ?? '17:00:00';
        $notify_before_days = (int)($_POST['notify_before_days'] ?? 3);
        $remarks = $_POST['remarks'] ?? '';
        
        if ($division && $report_type && $deadline_date) {
            // Check if deadline already exists
            $checkStmt = $mysqli->prepare("SELECT id FROM report_deadlines WHERE division = ? AND report_type = ? AND deadline_date = ? AND (user_id = ? OR (user_id IS NULL AND ? IS NULL))");
            $checkStmt->bind_param('sssii', $division, $report_type, $deadline_date, $user_id, $user_id);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            
            if ($existing) {
                // Update existing
                $updateStmt = $mysqli->prepare("UPDATE report_deadlines SET deadline_time = ?, notify_before_days = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param('sisi', $deadline_time, $notify_before_days, $remarks, $existing['id']);
                $updateStmt->execute();
                $message = "Deadline updated successfully!";
                $messageType = "success";
            } else {
                // Insert new
                $insertStmt = $mysqli->prepare("INSERT INTO report_deadlines (division, user_id, report_type, deadline_date, deadline_time, notify_before_days, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->bind_param('sssssis', $division, $user_id, $report_type, $deadline_date, $deadline_time, $notify_before_days, $remarks);
                $insertStmt->execute();
                $message = "Deadline created successfully!";
                $messageType = "success";
            }
        }
    } elseif ($action === 'delete_deadline') {
        $deadline_id = (int)$_POST['deadline_id'];
        if ($deadline_id > 0) {
            $deleteStmt = $mysqli->prepare("DELETE FROM report_deadlines WHERE id = ?");
            $deleteStmt->bind_param('i', $deadline_id);
            $deleteStmt->execute();
            $message = "Deadline deleted successfully!";
            $messageType = "success";
        }
    } elseif ($action === 'update_status') {
        $deadline_id = (int)$_POST['deadline_id'];
        $status = $_POST['status'] ?? 'active';
        
        $updateStmt = $mysqli->prepare("UPDATE report_deadlines SET status = ? WHERE id = ?");
        $updateStmt->bind_param('si', $status, $deadline_id);
        $updateStmt->execute();
        $message = "Status updated successfully!";
        $messageType = "success";
    }
}

// Get all deadlines
$deadlinesRes = $mysqli->query("
    SELECT rd.*, 
           IFNULL(CONCAT(u.last_name, ', ', u.first_name), 'All users') as assigned_to
    FROM report_deadlines rd
    LEFT JOIN users u ON rd.user_id = u.id
    ORDER BY rd.division, rd.report_type, rd.deadline_date DESC
");
$deadlines = [];
while ($row = $deadlinesRes->fetch_assoc()) {
    $deadlines[] = $row;
}

// Get users for dropdown (filtered by selected division)
$selectedDivision = $_GET['division'] ?? '';
$usersForDivision = [];
if ($selectedDivision) {
    $userRes = $mysqli->query("
        SELECT id, first_name, last_name, email 
        FROM users 
        WHERE FIND_IN_SET('" . $mysqli->real_escape_string($selectedDivision) . "', REPLACE(division, ', ', ',')) > 0
        ORDER BY last_name, first_name
    ");
    while ($row = $userRes->fetch_assoc()) {
        $usersForDivision[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Deadlines Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .deadline-card {
            background: var(--card-bg);
            border: 1px solid var(--surface-contrast);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
        }
        .deadline-info {
            flex: 1;
        }
        .deadline-type {
            font-weight: 600;
            color: var(--text);
            text-transform: capitalize;
        }
        .deadline-date {
            font-size: 0.9rem;
            color: var(--muted);
        }
        .deadline-actions {
            display: flex;
            gap: 0.5rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }
        .status-active {
            background: rgba(34, 197, 94, 0.14);
            color: #16a34a;
        }
        .status-inactive {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
        }
        .deadline-remarks {
            color: var(--muted);
            font-style: italic;
        }
        .dark-mode .deadline-card {
            background: rgba(15, 23, 42, 0.72);
            border-color: rgba(148, 163, 184, 0.22);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.28);
        }
        .dark-mode .status-active {
            background: rgba(34, 197, 94, 0.18);
            color: #4ade80;
        }
        .dark-mode .status-inactive {
            background: rgba(239, 68, 68, 0.16);
            color: #f87171;
        }
        .dark-mode .deadline-date,
        .dark-mode .deadline-remarks {
            color: #cbd5e1;
        }
    </style>
</head>
<body class="p-4">
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container-lg mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1"><i class="bi bi-clock-history"></i> Report Deadlines Management</h1>
                <p class="text-muted">Set and manage report submission deadlines for divisions and individual users</p>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Deadline Form -->
            <div class="col-lg-5 mb-4" id="deadlineFormCard">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Deadline</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_deadline">
                            
                            <div class="mb-3">
                                <label for="division" class="form-label">Division <span class="text-danger">*</span></label>
                                <select class="form-select" id="division" name="division" required>
                                    <option value="">Select Division</option>
                                    <?php foreach ($allDivisions as $div): ?>
                                        <option value="<?php echo htmlspecialchars($div); ?>"><?php echo htmlspecialchars($div); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="user_id" class="form-label">User (Optional - leave empty for all users in division)</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">All Users</option>
                                </select>
                                <small class="form-text text-muted">Select division first to see users</small>
                            </div>

                            <div class="mb-3">
                                <label for="report_type" class="form-label">Report Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="report_type" name="report_type" placeholder="Enter the report type" required>
                            </div>

                            <div class="mb-3">
                                <label for="deadline_date" class="form-label">Deadline Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="deadline_date" name="deadline_date" required>
                            </div>

                            <div class="mb-3">
                                <label for="deadline_time" class="form-label">Deadline Time</label>
                                <input type="time" class="form-control" id="deadline_time" name="deadline_time" value="17:00">
                            </div>

                            <div class="mb-3">
                                <label for="notify_before_days" class="form-label">Notify Before (days)</label>
                                <input type="number" class="form-control" id="notify_before_days" name="notify_before_days" value="3" min="0" max="30">
                                <small class="form-text text-muted">Send notification this many days before deadline</small>
                            </div>

                            <div class="mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Add any notes about this deadline..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Create Deadline</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Deadlines List -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Scheduled Deadlines</h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($deadlines)): ?>
                            <p class="text-muted text-center py-4">No deadlines scheduled yet.</p>
                        <?php else: ?>
                            <?php foreach ($deadlines as $deadline): ?>
                                <div class="deadline-card">
                                    <div class="deadline-info">
                                        <div class="deadline-type">
                                            <?php echo ucfirst($deadline['report_type']); ?> Report
                                            <span class="status-badge <?php echo $deadline['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo ucfirst($deadline['status']); ?>
                                            </span>
                                        </div>
                                        <div class="deadline-date">
                                            <strong><?php echo htmlspecialchars($deadline['division']); ?></strong> - <?php echo htmlspecialchars($deadline['assigned_to']); ?>
                                        </div>
                                        <div class="deadline-date">
                                            ðŸ“… <?php echo date('M d, Y', strtotime($deadline['deadline_date'])); ?> at <?php echo $deadline['deadline_time']; ?>
                                        </div>
                                        <div class="deadline-date">
                                            ðŸ”” Notify <?php echo $deadline['notify_before_days']; ?> days before
                                        </div>
                                        <?php if ($deadline['remarks']): ?>
                                            <div class="deadline-remarks">
                                                ðŸ“ <?php echo htmlspecialchars($deadline['remarks']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="deadline-actions">
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="deadline_id" value="<?php echo $deadline['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $deadline['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Toggle status">
                                                <i class="bi bi-toggle-<?php echo $deadline['status'] === 'active' ? 'on' : 'off'; ?>"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-primary notify-deadline-btn" data-deadline-id="<?php echo $deadline['id']; ?>" title="Notify now">
                                            <i class="bi bi-bell-fill"></i>
                                        </button>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this deadline?');">
                                            <input type="hidden" name="action" value="delete_deadline">
                                            <input type="hidden" name="deadline_id" value="<?php echo $deadline['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <div class="modal fade" id="notifyResultModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notifyResultTitle">Notification Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="notifyResultMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/script.js?v=20260407k2"></script>
    <script>
        const notifyResultModalEl = document.getElementById('notifyResultModal');
        const notifyResultModal = new bootstrap.Modal(notifyResultModalEl);
        const notifyResultTitle = document.getElementById('notifyResultTitle');
        const notifyResultMessage = document.getElementById('notifyResultMessage');

        function showNotifyResult(title, message) {
            notifyResultTitle.textContent = title;
            notifyResultMessage.textContent = message;
            notifyResultModal.show();
        }

        document.querySelectorAll('.notify-deadline-btn').forEach((button) => {
            button.addEventListener('click', async function () {
                const deadlineId = this.getAttribute('data-deadline-id');
                const originalHtml = this.innerHTML;

                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                try {
                    const response = await fetch('./api/report_deadline_notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: new URLSearchParams({ action: 'manual', deadline_id: deadlineId }).toString()
                    });

                    const result = await response.json();
                    if (!response.ok || result.status !== 'success') {
                        throw new Error(result.message || 'Failed to send notification.');
                    }

                    showNotifyResult(
                        'Notification Sent',
                        'Notification sent: ' + (result.notifications_sent || 0) + ' in-app, ' + (result.emails_sent || 0) + ' email(s).'
                    );
                } catch (error) {
                    showNotifyResult('Notification Failed', error.message || 'Unable to send notification now.');
                } finally {
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                }
            });
        });

        // Update user dropdown when division changes
        const divisionSelect = document.getElementById('division');
        const userSelect = document.getElementById('user_id');
        
        divisionSelect.addEventListener('change', async function() {
            const division = this.value;
            userSelect.innerHTML = '<option value="">All Users</option>';
            
            if (!division) return;
            
            try {
                const response = await fetch(`api/get_division_users.php?division=${encodeURIComponent(division)}`);
                const users = await response.json();
                
                users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = `${user.last_name}, ${user.first_name} (${user.email})`;
                    userSelect.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading users:', error);
            }
        });
    </script>
</body>
</html>











