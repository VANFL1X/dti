<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$division = trim((string)($_GET['division'] ?? ''));
if ($division === '') {
    echo json_encode(['success' => false, 'message' => 'Division required']);
    exit;
}

// Match users whose division CSV contains the requested division
$sql = "SELECT id, first_name, last_name, email, division, avatar FROM users WHERE FIND_IN_SET(?, REPLACE(division, ', ', ',')) > 0 ORDER BY last_name, first_name";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed']);
    exit;
}
$stmt->bind_param('s', $division);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
    $avatarUrl = '';
    if (!empty($r['avatar'])) {
        $uploadPath = __DIR__ . '/../uploads/' . $r['avatar'];
        $legacyPath = __DIR__ . '/../data/avatars/' . $r['avatar'];
        if (is_file($uploadPath)) $avatarUrl = 'uploads/' . $r['avatar'];
        elseif (is_file($legacyPath)) $avatarUrl = 'data/avatars/' . $r['avatar'];
    }
    $rows[] = [
        'id' => (int)$r['id'],
        'name' => trim($r['first_name'] . ' ' . $r['last_name']),
        'email' => $r['email'],
        'division' => $r['division'],
        'avatar' => $avatarUrl
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'division' => $division, 'users' => $rows], JSON_UNESCAPED_UNICODE);
