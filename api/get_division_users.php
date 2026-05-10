<?php
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

$division = $_GET['division'] ?? '';

if (!$division) {
    http_response_code(400);
    echo json_encode(['error' => 'Division parameter required']);
    exit;
}

$mysqli = getDB();

// Fetch users for this division
$stmt = $mysqli->prepare("
    SELECT id, first_name, last_name, email
    FROM users
    WHERE FIND_IN_SET(?, REPLACE(division, ', ', ',')) > 0
    ORDER BY last_name, first_name
");
$stmt->bind_param('s', $division);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode($users);
