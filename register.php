<?php
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// CSRF check
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid request (CSRF).'];
    header('Location: index.php');
    exit;
}

$required = ['firstName','lastName','birthdate','signupEmail','signupPassword','agreeTerms'];
foreach ($required as $r) {
    if (empty($_POST[$r])) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please complete all required fields.'];
        $_SESSION['show_signup'] = true;
        header('Location: index.php');
        exit;
    }
}

$first = trim($_POST['firstName']);
$last = trim($_POST['lastName']);
$middle = trim($_POST['middleName'] ?? '');
$suffix = trim($_POST['suffix'] ?? '');
$birth = $_POST['birthdate'];
$email = filter_var($_POST['signupEmail'], FILTER_VALIDATE_EMAIL);
$password = $_POST['signupPassword'];

$divisionInput = $_POST['division'] ?? [];
if (!is_array($divisionInput)) {
    $divisionInput = [$divisionInput];
}
$divisionInput = array_values(array_unique(array_filter(array_map('trim', $divisionInput), static function ($v) {
    return $v !== '';
})));

if (count($divisionInput) === 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please select at least one division.'];
    $_SESSION['show_signup'] = true;
    header('Location: index.php');
    exit;
}

if (!$email) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid email.'];
    $_SESSION['show_signup'] = true;
    header('Location: index.php');
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Password must be at least 6 characters.'];
    $_SESSION['show_signup'] = true;
    header('Location: index.php');
    exit;
}

$allowed = [
    'Admin Division',
    'Office of the Provincial Director',
    'Consumer Protection Division',
    'Business Development Division',
    'Planning Unit'
];
foreach ($divisionInput as $selectedDivision) {
    if (!in_array($selectedDivision, $allowed, true)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid division selected.'];
        $_SESSION['show_signup'] = true;
        header('Location: index.php');
        exit;
    }
}

$division = implode(', ', $divisionInput);

$hash = password_hash($password, PASSWORD_DEFAULT);

// Insert using MySQLi
$ok = false;
if ($stmt = $mysqli->prepare('INSERT INTO users (first_name,last_name,middle_name,suffix,birthdate,email,password,division,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')) {
    $stmt->bind_param('ssssssss', $first, $last, $middle, $suffix, $birth, $email, $hash, $division);
    if ($stmt->execute()) {
        $ok = true;
    } else {
        $err = $stmt->error;
    }
    $stmt->close();
} else {
    $err = $mysqli->error;
}

if ($ok) {
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account created successfully. You may now sign in.'];
    header('Location: index.php');
    exit;
} else {
    if (isset($err) && stripos($err, 'duplicate') !== false) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'An account with that email already exists.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Registration failed.'];
    }
    $_SESSION['show_signup'] = true;
    header('Location: index.php');
    exit;
}

