<?php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['user'] ?? null;
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$dateApplication = trim($_POST['date_application'] ?? '');
$dateUse = trim($_POST['date_use'] ?? '');
$departureDate = trim($_POST['departure_date'] ?? '');
$departureTime = trim($_POST['departure_time'] ?? '');
$expectedArrivalDate = trim($_POST['expected_arrival_date'] ?? '');
$expectedArrivalTime = trim($_POST['expected_arrival_time'] ?? '');
$vehiclePlateNo = trim($_POST['vehicle_plate_no'] ?? '');
$destination = trim($_POST['destination'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$driverName = trim($_POST['driver_name'] ?? '');
$transportationIncharge = trim($_POST['transportation_incharge'] ?? '');
$passengers = $_POST['passengers'] ?? [];

if (
    $dateApplication === '' || $dateUse === '' || $departureDate === '' || $departureTime === '' ||
    $expectedArrivalDate === '' || $expectedArrivalTime === '' || $vehiclePlateNo === '' ||
    $destination === '' || $purpose === '' || $driverName === '' || $transportationIncharge === ''
) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$da = DateTime::createFromFormat('Y-m-d', $dateApplication);
$du = DateTime::createFromFormat('Y-m-d', $dateUse);
$dd = DateTime::createFromFormat('Y-m-d', $departureDate);
$ed = DateTime::createFromFormat('Y-m-d', $expectedArrivalDate);
$dt = DateTime::createFromFormat('H:i', $departureTime);
$et = DateTime::createFromFormat('H:i', $expectedArrivalTime);

if (!$da || !$du || !$dd || !$ed || !$dt || !$et) {
    echo json_encode(['success' => false, 'message' => 'Invalid date/time format']);
    exit;
}

$departureDateTime = strtotime($departureDate . ' ' . $departureTime);
$arrivalDateTime = strtotime($expectedArrivalDate . ' ' . $expectedArrivalTime);
if ($departureDateTime === false || $arrivalDateTime === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid departure or arrival date/time']);
    exit;
}
if ($arrivalDateTime < $departureDateTime) {
    echo json_encode(['success' => false, 'message' => 'Expected arrival must not be earlier than departure']);
    exit;
}

if (!is_array($passengers)) {
    echo json_encode(['success' => false, 'message' => 'Invalid passengers data']);
    exit;
}

$cleanPassengers = [];
foreach ($passengers as $p) {
    $name = trim((string)$p);
    if ($name !== '') {
        $cleanPassengers[] = $name;
    }
}

if (count($cleanPassengers) < 1) {
    echo json_encode(['success' => false, 'message' => 'At least one passenger is required']);
    exit;
}

$mysqli->begin_transaction();

try {
    $stmt = $mysqli->prepare("INSERT INTO vehicle_requests (user_id, date_application, date_use, departure_date, departure_time, expected_arrival_date, expected_arrival_time, vehicle_plate_no, destination, purpose, driver_name, transportation_incharge, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }

    $stmt->bind_param(
        'isssssssssss',
        $user['id'],
        $dateApplication,
        $dateUse,
        $departureDate,
        $departureTime,
        $expectedArrivalDate,
        $expectedArrivalTime,
        $vehiclePlateNo,
        $destination,
        $purpose,
        $driverName,
        $transportationIncharge
    );

    if (!$stmt->execute()) {
        throw new Exception('Insert vehicle request failed: ' . $stmt->error);
    }

    $requestId = (int)$stmt->insert_id;
    $stmtPassenger = $mysqli->prepare("INSERT INTO passengers (request_id, passenger_name) VALUES (?, ?)");
    if (!$stmtPassenger) {
        throw new Exception('Prepare passenger insert failed: ' . $mysqli->error);
    }

    foreach ($cleanPassengers as $passengerName) {
        $stmtPassenger->bind_param('is', $requestId, $passengerName);
        if (!$stmtPassenger->execute()) {
            throw new Exception('Insert passenger failed: ' . $stmtPassenger->error);
        }
    }

    $mysqli->commit();

    // respond quickly to client before performing slower tasks (like sending email)
    $response = ['success' => true, 'message' => 'Vehicle request submitted'];
    echo json_encode($response);

    // attempt to flush response and close connection so user sees quick submit
    ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        @ob_end_flush();
        @ob_flush();
        flush();
    }

    // notify requester by email (runs after response is sent)
    try {
        $recipient = $user['email'] ?? null;
            if ($recipient) {
            $subject = 'Vehicle Request Submitted';

            $displayName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
            $recipientEmail = htmlspecialchars($recipient);

            $itemList = '';
            $itemList .= '<div class="list-item"><div style="font-weight:600">Date of use:</div><div>' . htmlspecialchars($dateUse) . '</div></div>';
            $itemList .= '<div class="list-item"><div style="font-weight:600">Departure:</div><div>' . htmlspecialchars($departureDate) . ' ' . htmlspecialchars($departureTime) . '</div></div>';
            $itemList .= '<div class="list-item"><div style="font-weight:600">Expected arrival:</div><div>' . htmlspecialchars($expectedArrivalDate) . ' ' . htmlspecialchars($expectedArrivalTime) . '</div></div>';
            $itemList .= '<div class="list-item"><div style="font-weight:600">Destination:</div><div>' . htmlspecialchars($destination) . '</div></div>';
            $itemList .= '<div class="list-item"><div style="font-weight:600">Purpose:</div><div>' . nl2br(htmlspecialchars($purpose)) . '</div></div>';
            $itemList .= '<div class="list-item"><div style="font-weight:600">Passengers:</div><div>' . htmlspecialchars(implode(', ', $cleanPassengers)) . '</div></div>';

            $body = '<!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width">'
                . '<style>'
                . 'body{font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background:#f4f6f8; margin:0; padding:20px;}'
                . '.card{max-width:720px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(11,15,30,0.08)}'
                . '.card-header{background:linear-gradient(90deg,#2563EB,#6F42C1);padding:18px;color:#fff}'
                . '.card-body{padding:20px;color:#0b1220}'
                . '.meta{color:#6b7280;font-size:13px;margin-bottom:12px}'
                . '.list{background:#f8fafc;border-radius:8px;padding:12px;margin:12px 0}'
                . '.list-item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(11,15,30,0.04)}'
                . '.list-item:last-child{border-bottom:0}'
                . '</style></head><body>'
                . '<div class="card">'
                . '<div class="card-header"><h2 style="margin:0;font-size:18px">DTI R2 Nueva Vizcaya</h2></div>'
                . '<div class="card-body">'
                . '<p class="meta">Hello ' . $displayName . ', your vehicle request has been received.</p>'
                . '<div class="list">'
                . $itemList
                . '</div>'
                . '</div></div></body></html>';

            $alt = "Vehicle Request Submitted\n\nDate of use: " . $dateUse . "\nDeparture: " . $departureDate . ' ' . $departureTime . "\nExpected arrival: " . $expectedArrivalDate . ' ' . $expectedArrivalTime . "\nDestination: " . $destination . "\nPassengers: " . implode(', ', $cleanPassengers);

            send_email($recipient, $subject, $body, $alt);

            // notify Admin Division users
            try {
                $adminDivision = 'Admin Division';
                $admStmt = $mysqli->prepare("SELECT email FROM users WHERE email <> '' AND FIND_IN_SET(?, REPLACE(division, ', ', ',')) > 0");
                if ($admStmt) {
                    $admStmt->bind_param('s', $adminDivision);
                    $admStmt->execute();
                    $admRes = $admStmt->get_result();
                    while ($admRow = $admRes->fetch_assoc()) {
                        $adminEmail = $admRow['email'] ?? null;
                        if ($adminEmail) {
                            $adminSubject = 'New vehicle request submitted';
                            $adminDisplay = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
                            $adminItemList = '';
                            $adminItemList .= '<div class="list-item"><div style="font-weight:600">Date of use:</div><div>' . htmlspecialchars($dateUse) . '</div></div>';
                            $adminItemList .= '<div class="list-item"><div style="font-weight:600">Departure:</div><div>' . htmlspecialchars($departureDate) . ' ' . htmlspecialchars($departureTime) . '</div></div>';
                            $adminItemList .= '<div class="list-item"><div style="font-weight:600">Expected arrival:</div><div>' . htmlspecialchars($expectedArrivalDate) . ' ' . htmlspecialchars($expectedArrivalTime) . '</div></div>';
                            $adminItemList .= '<div class="list-item"><div style="font-weight:600">Destination:</div><div>' . htmlspecialchars($destination) . '</div></div>';
                            $adminItemList .= '<div class="list-item"><div style="font-weight:600">Purpose:</div><div>' . nl2br(htmlspecialchars($purpose)) . '</div></div>';
                            $adminItemList .= '<div class="list-item"><div style="font-weight:600">Passengers:</div><div>' . htmlspecialchars(implode(', ', $cleanPassengers)) . '</div></div>';

                            $adminBody = '<!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width">'
                                . '<style>'
                                . 'body{font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background:#f4f6f8; margin:0; padding:20px;}'
                                . '.card{max-width:720px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(11,15,30,0.08)}'
                                . '.card-header{background:linear-gradient(90deg,#2563EB,#6F42C1);padding:18px;color:#fff}'
                                . '.card-body{padding:20px;color:#0b1220}'
                                . '.meta{color:#6b7280;font-size:13px;margin-bottom:12px}'
                                . '.list{background:#f8fafc;border-radius:8px;padding:12px;margin:12px 0}'
                                . '.list-item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(11,15,30,0.04)}'
                                . '.list-item:last-child{border-bottom:0}'
                                . '</style></head><body>'
                                . '<div class="card">'
                                . '<div class="card-header"><h2 style="margin:0;font-size:18px">DTI R2 Nueva Vizcaya</h2></div>'
                                . '<div class="card-body">'
                                . '<p class="meta">A new vehicle request was submitted by ' . $adminDisplay . ' (' . htmlspecialchars($recipient) . ').</p>'
                                . '<div class="list">'
                                . $adminItemList
                                . '</div>'
                                . '</div></div></body></html>';

                            send_email($adminEmail, $adminSubject, $adminBody);
                        }
                    }
                    $admStmt->close();
                }
            } catch (Throwable $e) {
                error_log('Failed to notify admin about vehicle request: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Failed to send vehicle notification: ' . $e->getMessage());
    }
} catch (Throwable $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

