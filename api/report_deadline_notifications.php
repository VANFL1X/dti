<?php
/**
 * Send report deadline notifications.
 *
 * Default behavior sends reminders for deadlines that are due soon.
 * Admins can also trigger immediate notifications by passing:
 * - action=manual
 * - deadline_id=<id>
 */

require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

$mysqli = getDB();

function report_deadline_send_notification($mysqli, $deadline, $user, $notificationType)
{
    $deadlineId = (int)$deadline['id'];
    $userId = (int)$user['id'];
    $reportType = ucfirst((string)$deadline['report_type']);
    $division = (string)$deadline['division'];
    $deadlineDate = (string)$deadline['deadline_date'];
    $deadlineTime = (string)$deadline['deadline_time'];
    $remarks = (string)($deadline['remarks'] ?? '');

    $checkStmt = $mysqli->prepare("SELECT id FROM report_deadline_notifications WHERE deadline_id = ? AND user_id = ? AND notification_type = ? LIMIT 1");
    if (!$checkStmt) {
        throw new Exception('Failed to prepare notification check query: ' . $mysqli->error);
    }
    $checkStmt->bind_param('iis', $deadlineId, $userId, $notificationType);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        return ['sent' => false, 'skipped' => true, 'email' => false];
    }

    $deadlineDateTime = new DateTime($deadlineDate . ' ' . $deadlineTime);
    $now = new DateTime();
    $daysUntil = (int)$now->diff($deadlineDateTime)->format('%r%a');

    $title = $notificationType === 'manual'
        ? $reportType . ' Report Deadline Now'
        : $reportType . ' Report Due Soon';

    $body = $notificationType === 'manual'
        ? 'Your ' . $reportType . ' report for ' . $division . ' is due now on ' . date('F d, Y', strtotime($deadlineDate)) . ' at ' . date('h:i A', strtotime($deadlineTime)) . '.'
        : 'Your ' . $reportType . ' report for ' . $division . ' is due in ' . max(0, $daysUntil) . ' days on ' . date('F d, Y', strtotime($deadlineDate)) . ' at ' . date('h:i A', strtotime($deadlineTime)) . '.';

    $notifStmt = $mysqli->prepare("INSERT INTO notifications (user_id, type, ref_id, title, body, is_read, created_at) VALUES (?, 'report_deadline', ?, ?, ?, 0, NOW())");
    if (!$notifStmt) {
        throw new Exception('Failed to prepare in-app notification query: ' . $mysqli->error);
    }
    $notifStmt->bind_param('iiss', $userId, $deadlineId, $title, $body);
    $notifStmt->execute();
    $notifStmt->close();

    $recordStmt = $mysqli->prepare("INSERT INTO report_deadline_notifications (deadline_id, user_id, notification_type, sent_at) VALUES (?, ?, ?, NOW())");
    if (!$recordStmt) {
        throw new Exception('Failed to prepare notification history query: ' . $mysqli->error);
    }
    $recordStmt->bind_param('iis', $deadlineId, $userId, $notificationType);
    $recordStmt->execute();
    $recordStmt->close();

    $recipientEmail = (string)($user['email'] ?? '');
    $recipientName = trim((string)($user['last_name'] ?? '') . ', ' . (string)($user['first_name'] ?? ''));
    $subject = ($notificationType === 'manual' ? 'Report Deadline Now: ' : 'Report Deadline Reminder: ') . $reportType . ' Report';

    $htmlBody = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937; background: #f8fafc; padding: 24px;">
            <div style="max-width: 640px; margin: 0 auto; background: #ffffff; padding: 24px; border: 1px solid #e5e7eb; border-radius: 12px;">
                <h2 style="margin: 0 0 16px; color: #0f172a; border-bottom: 3px solid #2563eb; padding-bottom: 12px;">Report Deadline ' . ($notificationType === 'manual' ? 'Notice' : 'Reminder') . '</h2>
                <p>Hello <strong>' . htmlspecialchars($recipientName) . '</strong>,</p>
                <p>This is a ' . ($notificationType === 'manual' ? 'now' : 'deadline') . ' notification for your <strong>' . htmlspecialchars($reportType) . '</strong> report.</p>
                <div style="background: #f1f5f9; padding: 16px; border-left: 4px solid #2563eb; margin: 20px 0; border-radius: 8px;">
                    <p style="margin: 6px 0;"><strong>Division:</strong> ' . htmlspecialchars($division) . '</p>
                    <p style="margin: 6px 0;"><strong>Report Type:</strong> ' . htmlspecialchars($reportType) . '</p>
                    <p style="margin: 6px 0;"><strong>Due Date:</strong> ' . date('F d, Y', strtotime($deadlineDate)) . '</p>
                    <p style="margin: 6px 0;"><strong>Due Time:</strong> ' . date('h:i A', strtotime($deadlineTime)) . '</p>
                </div>
                <p>' . ($remarks !== '' ? '<strong>Remarks:</strong> ' . htmlspecialchars($remarks) : 'Please submit your report on time.') . '</p>
                <p style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 0.9em; color: #6b7280;">This is an automated notification from the DTI Management System.</p>
            </div>
        </body>
        </html>
    ';

    $altBody = "Report Deadline " . ($notificationType === 'manual' ? 'Notice' : 'Reminder') . "\n\n" .
        "Hello " . $recipientName . ",\n\n" .
        "Division: " . $division . "\n" .
        "Report Type: " . $reportType . "\n" .
        "Due Date: " . date('F d, Y', strtotime($deadlineDate)) . "\n" .
        "Due Time: " . date('h:i A', strtotime($deadlineTime)) . "\n\n" .
        ($remarks !== '' ? "Remarks: " . $remarks . "\n\n" : '') .
        "Please submit your report on time.";

    $emailSent = false;
    if ($recipientEmail !== '') {
        $emailSent = send_email($recipientEmail, $subject, $htmlBody, $altBody);
    }

    return ['sent' => true, 'skipped' => false, 'email' => $emailSent];
}

try {
    $action = $_POST['action'] ?? 'scheduled';
    $deadlineId = isset($_POST['deadline_id']) ? (int)$_POST['deadline_id'] : 0;

    if ($action === 'manual' && $deadlineId > 0) {
        $deadlineStmt = $mysqli->prepare("SELECT * FROM report_deadlines WHERE id = ? AND status = 'active' LIMIT 1");
        if (!$deadlineStmt) {
            throw new Exception('Failed to prepare deadline lookup: ' . $mysqli->error);
        }
        $deadlineStmt->bind_param('i', $deadlineId);
        $deadlineStmt->execute();
        $deadlineRes = $deadlineStmt->get_result();
        $deadline = $deadlineRes->fetch_assoc();
        $deadlineStmt->close();

        if (!$deadline) {
            throw new Exception('Deadline not found or inactive.');
        }

        $users = [];
        if (!empty($deadline['user_id'])) {
            $userStmt = $mysqli->prepare("SELECT id, first_name, last_name, email, division FROM users WHERE id = ? LIMIT 1");
            if (!$userStmt) {
                throw new Exception('Failed to prepare target user lookup: ' . $mysqli->error);
            }
            $targetUserId = (int)$deadline['user_id'];
            $userStmt->bind_param('i', $targetUserId);
            $userStmt->execute();
            $userRes = $userStmt->get_result();
            while ($row = $userRes->fetch_assoc()) {
                $users[] = $row;
            }
            $userStmt->close();
        } else {
            $division = (string)$deadline['division'];
            $userStmt = $mysqli->prepare("SELECT id, first_name, last_name, email, division FROM users WHERE FIND_IN_SET(?, REPLACE(division, ', ', ',')) > 0 ORDER BY last_name, first_name");
            if (!$userStmt) {
                throw new Exception('Failed to prepare division user lookup: ' . $mysqli->error);
            }
            $userStmt->bind_param('s', $division);
            $userStmt->execute();
            $userRes = $userStmt->get_result();
            while ($row = $userRes->fetch_assoc()) {
                $users[] = $row;
            }
            $userStmt->close();
        }

        $notificationsSent = 0;
        $emailsSent = 0;
        $errors = [];

        foreach ($users as $user) {
            try {
                $result = report_deadline_send_notification($mysqli, $deadline, $user, 'manual');
                if (!empty($result['sent'])) {
                    $notificationsSent++;
                }
                if (!empty($result['email'])) {
                    $emailsSent++;
                }
            } catch (Exception $e) {
                $errors[] = 'Failed for ' . trim(($user['last_name'] ?? '') . ', ' . ($user['first_name'] ?? '')) . ': ' . $e->getMessage();
            }
        }

        echo json_encode([
            'status' => 'success',
            'mode' => 'manual',
            'deadline_id' => $deadlineId,
            'notifications_sent' => $notificationsSent,
            'emails_sent' => $emailsSent,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    $queryRes = $mysqli->query("\n        SELECT rd.*, u.id as target_user_id, u.email, u.first_name, u.last_name, u.division\n        FROM report_deadlines rd\n        LEFT JOIN users u ON (\n            (rd.user_id = u.id) OR \n            (rd.user_id IS NULL AND FIND_IN_SET(rd.division, REPLACE(u.division, ', ', ',')))\n        )\n        WHERE rd.status = 'active'\n        AND DATE_SUB(rd.deadline_date, INTERVAL rd.notify_before_days DAY) <= DATE(NOW())\n        AND rd.deadline_date > DATE(NOW())\n        AND u.id IS NOT NULL\n        AND u.email IS NOT NULL\n        AND u.email != ''\n    ");

    $notificationsSent = 0;
    $emailsSent = 0;
    $errors = [];

    while ($deadline = $queryRes->fetch_assoc()) {
        try {
            $user = [
                'id' => $deadline['target_user_id'],
                'email' => $deadline['email'],
                'first_name' => $deadline['first_name'],
                'last_name' => $deadline['last_name'],
                'division' => $deadline['division'],
            ];
            $result = report_deadline_send_notification($mysqli, $deadline, $user, 'approaching');
            if (!empty($result['sent'])) {
                $notificationsSent++;
            }
            if (!empty($result['email'])) {
                $emailsSent++;
            }
        } catch (Exception $e) {
            $errors[] = 'Failed for deadline #' . (int)$deadline['id'] . ': ' . $e->getMessage();
        }
    }

    echo json_encode([
        'status' => 'success',
        'mode' => 'scheduled',
        'notifications_sent' => $notificationsSent,
        'emails_sent' => $emailsSent,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    error_log('Report deadline notification error: ' . $e->getMessage());
}
