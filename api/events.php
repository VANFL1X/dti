<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

// optionally filter by division (exact match) via GET param
$events = [];
$divisionFilter = trim((string)($_GET['division'] ?? ''));

$baseActivitySql = "SELECT a.id, a.user_id, a.purpose, a.destination, a.start_datetime, a.end_datetime, a.division_scope,
               u.first_name, u.last_name, u.division, u.avatar
        FROM activities a
        LEFT JOIN users u ON u.id = a.user_id";

if ($divisionFilter !== '') {
    // Division calendars only show activities explicitly scoped to that division.
    $activitySql = $baseActivitySql . " WHERE TRIM(a.division_scope) = ? AND a.is_global = 0 ORDER BY a.start_datetime ASC";
    $stmt = $mysqli->prepare($activitySql);
    if ($stmt) {
        $stmt->bind_param('s', $divisionFilter);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $safeDivision = $mysqli->real_escape_string($divisionFilter);
        $res = $mysqli->query($baseActivitySql . " WHERE TRIM(a.division_scope) = '" . $safeDivision . "' AND a.is_global = 0 ORDER BY a.start_datetime ASC");
    }
} else {
    // Indicative calendar shows only global/indicative activities, not division-scoped ones.
    $res = $mysqli->query($baseActivitySql . " WHERE a.is_global = 1 ORDER BY a.start_datetime ASC");
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $avatarUrl = '';
        if (!empty($row['avatar'])) {
            $uploadPath = __DIR__ . '/../uploads/' . $row['avatar'];
            $legacyPath = __DIR__ . '/../data/avatars/' . $row['avatar'];
            if (is_file($uploadPath)) {
                $avatarUrl = 'uploads/' . $row['avatar'];
            } elseif (is_file($legacyPath)) {
                $avatarUrl = 'data/avatars/' . $row['avatar'];
            }
        }

        $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($fullName === '') {
            $fullName = 'Unknown User';
        }

        // Color map based on division
        $divisionColors = [
            'Admin Division' => '#2563EB',
            'Office of the Provincial Director' => '#DC3545',
            'Consumer Protection Division' => '#198754',
            'Business Development Division' => '#FF6B35',
            'Planning Unit' => '#6F42C1'
        ];
        // Prefer explicit activity scope for color (supports multi-division users).
        $eventDivision = trim((string)($row['division_scope'] ?? ''));
        if ($eventDivision === '') {
            $eventDivision = trim((string)($row['division'] ?? ''));
            // If user has multiple divisions in CSV, use the first as fallback.
            if (strpos($eventDivision, ',') !== false) {
                $parts = array_map('trim', explode(',', $eventDivision));
                $eventDivision = (string)($parts[0] ?? '');
            }
        }
        $bgColor = $divisionColors[$eventDivision] ?? '#6C757D';

        $events[] = [
            'id' => (int)$row['id'],
            'groupId' => 'activity',
            'title' => $row['purpose'],
            'start' => $row['start_datetime'],
            'end' => $row['end_datetime'],
            'backgroundColor' => $bgColor,
            'borderColor' => $bgColor,
            'textColor' => '#FFFFFF',
            'extendedProps' => [
                'event_type' => 'activity',
                'destination' => $row['destination'],
                'user_id' => (int)$row['user_id'],
                'creator_name' => $fullName,
                'creator_division' => $eventDivision !== '' ? $eventDivision : (string)($row['division'] ?? ''),
                'creator_avatar' => $avatarUrl
            ]
        ];
    }
}

// Append approved vehicle requests only for the global/indicative calendar.
if ($divisionFilter === '') {
    $vehicleBase = "SELECT vr.id, vr.user_id, vr.date_use, vr.departure_date, vr.departure_time,
                          vr.expected_arrival_date, vr.expected_arrival_time, vr.vehicle_plate_no,
                          vr.destination, vr.purpose, vr.driver_name, vr.transportation_incharge,
                          u.first_name, u.last_name, u.division, u.avatar
                   FROM vehicle_requests vr
                   LEFT JOIN users u ON u.id = vr.user_id
                   WHERE vr.status = 'approved'";

    $vehicleRes = $mysqli->query($vehicleBase . " ORDER BY vr.departure_date ASC, vr.departure_time ASC");

    if ($vehicleRes) {
        while ($row = $vehicleRes->fetch_assoc()) {
            $avatarUrl = '';
            if (!empty($row['avatar'])) {
                $uploadPath = __DIR__ . '/../uploads/' . $row['avatar'];
                $legacyPath = __DIR__ . '/../data/avatars/' . $row['avatar'];
                if (is_file($uploadPath)) {
                    $avatarUrl = 'uploads/' . $row['avatar'];
                } elseif (is_file($legacyPath)) {
                    $avatarUrl = 'data/avatars/' . $row['avatar'];
                }
            }

            $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            if ($fullName === '') {
                $fullName = 'Unknown User';
            }

            $divisionColors = [
                'Admin Division' => '#2563EB',
                'Office of the Provincial Director' => '#DC3545',
                'Consumer Protection Division' => '#198754',
                'Business Development Division' => '#FF6B35',
                'Planning Unit' => '#6F42C1'
            ];
            $division = (string)($row['division'] ?? '');
            $baseColor = $divisionColors[$division] ?? '#6C757D';

            $start = $row['departure_date'] . ' ' . $row['departure_time'];
            $end = $row['expected_arrival_date'] . ' ' . $row['expected_arrival_time'];

            $events[] = [
                'id' => 'vehicle-' . (int)$row['id'],
                'groupId' => 'vehicle',
                'title' => 'Vehicle: ' . $row['purpose'],
                'start' => $start,
                'end' => $end,
                'backgroundColor' => $baseColor,
                'borderColor' => $baseColor,
                'textColor' => '#FFFFFF',
                'extendedProps' => [
                    'event_type' => 'vehicle',
                    'destination' => $row['destination'],
                    'user_id' => (int)$row['user_id'],
                    'creator_name' => $fullName,
                    'creator_division' => $division,
                    'creator_avatar' => $avatarUrl,
                    'vehicle_plate_no' => (string)$row['vehicle_plate_no'],
                    'driver_name' => (string)$row['driver_name'],
                    'transportation_incharge' => (string)$row['transportation_incharge']
                ]
            ];
        }
    }
}

// Add birthday events only for division calendars (not indicative calendar)
if ($divisionFilter !== '') {
    // Fetch all users in the requested division
    $birthdaySql = "SELECT id, first_name, last_name, birthdate, division, avatar FROM users WHERE FIND_IN_SET(?, REPLACE(division, ', ', ',')) > 0";
    $bdayStmt = $mysqli->prepare($birthdaySql);
    if ($bdayStmt) {
        $bdayStmt->bind_param('s', $divisionFilter);
        $bdayStmt->execute();
        $bdayRes = $bdayStmt->get_result();
        
        $divisionColors = [
            'Admin Division' => '#2563EB',
            'Office of the Provincial Director' => '#DC3545',
            'Consumer Protection Division' => '#198754',
            'Business Development Division' => '#FF6B35',
            'Planning Unit' => '#6F42C1'
        ];
        
        while ($row = $bdayRes->fetch_assoc()) {
            $birthdate = $row['birthdate'];
            if (!empty($birthdate)) {
                // Parse the birthdate and generate all-day event for this year
                $bdayParts = explode('-', $birthdate);
                if (count($bdayParts) === 3) {
                    $bdayMonth = (int)$bdayParts[1];
                    $bdayDay = (int)$bdayParts[2];
                    // Create an all-day event for the birthday (use current year)
                    $bdayDate = date('Y') . '-' . str_pad($bdayMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad($bdayDay, 2, '0', STR_PAD_LEFT);
                    
                    $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
                    if ($fullName === '') {
                        $fullName = 'Unknown User';
                    }
                    
                    $avatarUrl = '';
                    if (!empty($row['avatar'])) {
                        $uploadPath = __DIR__ . '/../uploads/' . $row['avatar'];
                        $legacyPath = __DIR__ . '/../data/avatars/' . $row['avatar'];
                        if (is_file($uploadPath)) {
                            $avatarUrl = 'uploads/' . $row['avatar'];
                        } elseif (is_file($legacyPath)) {
                            $avatarUrl = 'data/avatars/' . $row['avatar'];
                        }
                    }
                    
                    $division = (string)($row['division'] ?? '');
                    $baseColor = $divisionColors[$divisionFilter] ?? '#E74C3C';
                    
                    $events[] = [
                        'id' => 'birthday-' . (int)$row['id'],
                        'groupId' => 'birthday',
                        'title' => '🎂 ' . $fullName . "'s Birthday",
                        'start' => $bdayDate,
                        'allDay' => true,
                        'backgroundColor' => '#E74C3C',
                        'borderColor' => '#E74C3C',
                        'textColor' => '#FFFFFF',
                        'extendedProps' => [
                            'event_type' => 'birthday',
                            'user_id' => (int)$row['id'],
                            'creator_name' => $fullName,
                            'creator_division' => $divisionFilter,
                            'creator_avatar' => $avatarUrl
                        ]
                    ];
                }
            }
        }
        $bdayStmt->close();
    }
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);

