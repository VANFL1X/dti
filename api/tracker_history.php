<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}
$period = strtolower(trim((string)($_GET['period'] ?? 'day')));
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
$employeeId = (int)($_GET['employee_id'] ?? 0);
if ($period !== 'day') $period = 'day';
if ($employeeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'employee_id required']);
    exit;
}
// Build SQL similar to tracker_history_print but filtered to the given employee and day
$historySql = "SELECT * FROM (
    SELECT
        a.user_id,
        TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
        COALESCE(u.division, '') AS division,
        CASE
            WHEN LOCATE('leave', LOWER(COALESCE(a.purpose, ''))) > 0 THEN 'On Leave'
            WHEN LOCATE('travel', LOWER(COALESCE(a.purpose, ''))) > 0 THEN 'On Travel'
            WHEN LOCATE('office', LOWER(COALESCE(a.purpose, ''))) > 0 THEN 'On Office'
            ELSE 'On Business'
        END AS status_label,
        a.start_datetime AS event_start,
        a.end_datetime AS event_end,
        'Activity' AS source_label,
        COALESCE(a.purpose, '') AS user_input
    FROM activities a
    INNER JOIN users u ON u.id = a.user_id

    UNION ALL

    SELECT
        v.user_id,
        TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
        COALESCE(u.division, '') AS division,
        'On Travel' AS status_label,
        CONCAT(v.departure_date, ' ', v.departure_time) AS event_start,
        CONCAT(v.expected_arrival_date, ' ', v.expected_arrival_time) AS event_end,
        'Vehicle Request' AS source_label,
        COALESCE(v.purpose, '') AS user_input
    FROM vehicle_requests v
    INNER JOIN users u ON u.id = v.user_id
    WHERE v.status = 'approved'

    UNION ALL

    SELECT
        e.user_id,
        TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
        COALESCE(u.division, '') AS division,
        CASE LOWER(COALESCE(e.event_type, ''))
            WHEN 'leave' THEN 'On Leave'
            WHEN 'travel' THEN 'On Travel'
            WHEN 'business' THEN 'On Business'
            ELSE 'On Office'
        END AS status_label,
        COALESCE(e.start_datetime, e.updated_at) AS event_start,
        e.end_datetime AS event_end,
        'Manual Status' AS source_label,
        CONCAT(COALESCE(e.event_type, ''), CASE WHEN COALESCE(e.notes, '') <> '' THEN CONCAT(' - ', e.notes) ELSE '' END) AS user_input
    FROM employee_events e
    INNER JOIN users u ON u.id = e.user_id
) history_union";

$where = [];
$params = [];
$types = '';
// Filter by employee
$where[] = 'user_id = ?'; $types .= 'i'; $params[] = $employeeId;
// Filter by day (event_start date)
$where[] = "DATE(event_start) = ?"; $types .= 's'; $params[] = $date;
if (!empty($where)) {
    $historySql .= ' WHERE ' . implode(' AND ', $where);
}
$historySql .= ' ORDER BY event_start ASC';

$stmt = $mysqli->prepare($historySql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}
if (!empty($params)) {
    $bind = array_merge([$types], $params);
    $tmp = [];
    foreach ($bind as $k => $v) $tmp[$k] = &$bind[$k];
    call_user_func_array([$stmt, 'bind_param'], $tmp);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'employee_id' => $employeeId, 'date' => $date, 'rows' => $rows]);
exit;
