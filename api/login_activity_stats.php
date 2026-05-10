<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json');

$period = strtolower(trim((string)($_GET['period'] ?? '7d')));
$division = trim((string)($_GET['division'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($period === '7d') {
    $where[] = 'l.login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($period === '30d') {
    $where[] = 'l.login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
} elseif ($period === '90d') {
    $where[] = 'l.login_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
} elseif ($period === 'month') {
    $where[] = 'YEAR(l.login_at) = YEAR(CURDATE()) AND MONTH(l.login_at) = MONTH(CURDATE())';
} else {
    $period = '7d';
    $where[] = 'l.login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}

$whereDivision = $where;
$paramsDivision = $params;
$typesDivision = $types;
if ($division !== '') {
    $whereDivision[] = 'FIND_IN_SET(?, REPLACE(u.division, ", ", ",")) > 0';
    $paramsDivision[] = $division;
    $typesDivision .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$whereDivisionSql = $whereDivision ? ('WHERE ' . implode(' AND ', $whereDivision)) : '';

function runQuery(mysqli $mysqli, string $sql, string $types, array $params): array {
    $rows = [];
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return $rows;

    if ($types !== '' && !empty($params)) {
        $bind = array_merge([$types], $params);
        $tmp = [];
        foreach ($bind as $k => $v) {
            $tmp[$k] = &$bind[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $tmp);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

$divisionSql = "SELECT u.division, COUNT(l.id) AS login_count
                FROM user_login_logs l
                JOIN users u ON u.id = l.user_id
                $whereSql
                GROUP BY u.division
                ORDER BY login_count DESC, u.division ASC";

$accountsSql = "SELECT u.first_name, u.last_name, u.email, COUNT(l.id) AS login_count
                FROM user_login_logs l
                JOIN users u ON u.id = l.user_id
                $whereDivisionSql
                GROUP BY u.id, u.first_name, u.last_name, u.email
                ORDER BY login_count DESC, u.last_name ASC, u.first_name ASC
                LIMIT 20";

$divisionRows = runQuery($mysqli, $divisionSql, $types, $params);
$accountRows = [];
if ($division !== '') {
    $accountRows = runQuery($mysqli, $accountsSql, $typesDivision, $paramsDivision);
}

$knownDivisions = [
    'Admin Division',
    'Office of the Provincial Director',
    'Consumer Protection Division',
    'Business Development Division',
    'Planning Unit',
];

$divisionCountMap = [];
foreach ($knownDivisions as $name) {
    $divisionCountMap[$name] = 0;
}

$divisionLabels = [];
$divisionCounts = [];
foreach ($divisionRows as $row) {
    $count = (int)$row['login_count'];

    $rawDivision = trim((string)($row['division'] ?? ''));
    if ($rawDivision === '') {
        continue;
    }

    // Support users assigned to multiple divisions in CSV format.
    $parts = array_values(array_filter(array_map('trim', explode(',', $rawDivision)), static function ($v) {
        return $v !== '';
    }));

    foreach ($parts as $label) {
        if (array_key_exists($label, $divisionCountMap)) {
            $divisionCountMap[$label] += $count;
        }
    }
}

uasort($divisionCountMap, function (int $a, int $b): int {
    return $b <=> $a;
});

foreach ($divisionCountMap as $label => $count) {
    $divisionLabels[] = $label;
    $divisionCounts[] = $count;
}

$accountLabels = [];
$accountCounts = [];
foreach ($accountRows as $row) {
    $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    if ($name === '') $name = (string)($row['email'] ?? 'Unknown Account');
    $accountLabels[] = $name;
    $accountCounts[] = (int)$row['login_count'];
}

echo json_encode([
    'success' => true,
    'period' => $period,
    'divisionFilter' => $division,
    'division' => ['labels' => $divisionLabels, 'counts' => $divisionCounts],
    'accounts' => ['labels' => $accountLabels, 'counts' => $accountCounts],
]);

