<?php
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// CSRF
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    // Log CSRF failure for debugging
    @file_put_contents(__DIR__ . '/data/login_debug.log', date('c') . " CSRF_FAIL session=" . session_id() . "\n", FILE_APPEND | LOCK_EX);
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid request (CSRF).'];
    header('Location: index.php');
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

// Debug helper: record attempt start (do not log password)
@file_put_contents(__DIR__ . '/data/login_debug.log', date('c') . " ATTEMPT session=" . session_id() . " email=" . ($email ?: '<invalid>') . "\n", FILE_APPEND | LOCK_EX);

if (!$email || !$password) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please enter email and password.'];
    $_SESSION['show_login'] = true;
    header('Location: index.php');
    exit;
}

if ($stmt = $mysqli->prepare('SELECT id, first_name, last_name, middle_name, suffix, birthdate, email, password, division, created_at, avatar FROM users WHERE email = ? LIMIT 1')) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} else {
    $user = null;
}

$isValidLogin = false;
if ($user) {
    $storedPassword = (string)($user['password'] ?? '');
    $isHashed = password_get_info($storedPassword)['algo'] !== null;

    if ($isHashed) {
        $isValidLogin = password_verify($password, $storedPassword);
    } else {
        // Backward compatibility for legacy rows that stored plaintext passwords.
        $isValidLogin = hash_equals($storedPassword, (string)$password);
        if ($isValidLogin) {
            $upgradedHash = password_hash($password, PASSWORD_DEFAULT);
            if ($upd = $mysqli->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1')) {
                $uid = (int)$user['id'];
                $upd->bind_param('si', $upgradedHash, $uid);
                $upd->execute();
                $upd->close();
            }
        }
    }
}

// Log result of verification
@file_put_contents(__DIR__ . '/data/login_debug.log', date('c') . " RESULT session=" . session_id() . " email=" . ($email ?: '<invalid>') . " user_found=" . ($user ? '1' : '0') . " is_hashed=" . (isset($isHashed) && $isHashed ? '1' : '0') . " valid=" . ($isValidLogin ? '1' : '0') . "\n", FILE_APPEND | LOCK_EX);

if (!$isValidLogin) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Incorrect email or password.'];
    $_SESSION['show_login'] = true;
    header('Location: index.php');
    exit;
}

// Successful login
unset($user['password']);
$_SESSION['user'] = $user;

// Log successful login for activity monitoring
if (!empty($user['id'])) {
    if ($logStmt = $mysqli->prepare('INSERT INTO user_login_logs (user_id, login_at) VALUES (?, NOW())')) {
        $uid = (int)$user['id'];
        $logStmt->bind_param('i', $uid);
        $logStmt->execute();
        $logStmt->close();
    }
}

header('Location: dashboard.php');
exit;

