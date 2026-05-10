<?php
require_once __DIR__ . '/includes/init.php';

if (empty($_SESSION['user'])) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please sign in to edit your profile.'];
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid request (CSRF).'];
        header('Location: profile.php');
        exit;
    }

    $first = trim($_POST['firstName'] ?? '');
    $last = trim($_POST['lastName'] ?? '');
    $middle = trim($_POST['middleName'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $divisionInput = $_POST['division'] ?? [];
    $newPassword = $_POST['password'] ?? '';
    $currentPassword = $_POST['currentPassword'] ?? '';

    if (!is_array($divisionInput)) {
      $divisionInput = [$divisionInput];
    }
    $divisionInput = array_values(array_unique(array_filter(array_map('trim', $divisionInput), static function ($v) {
      return $v !== '';
    })));
    $division = implode(', ', $divisionInput);

    if (!$first || !$last || !$email || count($divisionInput) === 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please fill required fields.'];
        header('Location: profile.php');
        exit;
    }

    // validate division
    $allowed = [
        'Admin Division',
        'Office of the Provincial Director',
        'Consumer Protection Division',
        'Business Development Division',
        'Planning Unit'
    ];
    foreach ($divisionInput as $selectedDivision) {
      if (!in_array($selectedDivision, $allowed, true)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid division.'];
        header('Location: profile.php');
        exit;
      }
    }

    // check email uniqueness
    if ($stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1')) {
      $stmt->bind_param('si', $email, $userId);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res && $res->fetch_assoc()) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email already in use by another account.'];
        header('Location: profile.php');
        exit;
      }
      $stmt->close();
    }

    // Build update dynamically to keep params in order
    $updateFields = ['first_name = ?', 'last_name = ?', 'middle_name = ?', 'suffix = ?', 'email = ?', 'division = ?'];
    $params = [$first, $last, $middle, $suffix, $email, $division];

    // handle password change: require current password verification
    if (!empty($newPassword)) {
      if (strlen($newPassword) < 6) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'New password must be at least 6 characters.'];
        header('Location: profile.php');
        exit;
      }
      if (empty($currentPassword)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Enter your current password to change to a new one.'];
        header('Location: profile.php');
        exit;
      }
      // fetch current hash from DB
      if ($stmt = $mysqli->prepare('SELECT password FROM users WHERE id = ? LIMIT 1')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
      } else {
        $row = null;
      }
      if (!$row || !password_verify($currentPassword, $row['password'])) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Current password is incorrect.'];
        header('Location: profile.php');
        exit;
      }
      $hash = password_hash($newPassword, PASSWORD_DEFAULT);
      $updateFields[] = 'password = ?';
      $params[] = $hash;
    }

    // handle avatar upload (store in uploads/)
    if (!empty($_FILES['avatar']['name'])) {
      $file = $_FILES['avatar'];
      if ($file['error'] === UPLOAD_ERR_OK) {
        // size limit ~3MB
        if ($file['size'] > 3 * 1024 * 1024) {
          $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Avatar exceeds 3MB size limit.'];
          header('Location: profile.php');
          exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowedMimes[$mime])) {
          $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid avatar file type.'];
          header('Location: profile.php');
          exit;
        }
        $ext = $allowedMimes[$mime];
        $dir = __DIR__ . '/uploads';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $avatarFilename = 'user_' . $userId . '.' . $ext;
        $dest = $dir . '/' . $avatarFilename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
          $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to save avatar.'];
          header('Location: profile.php');
          exit;
        }
        // remove previous avatar if exists
        if (!empty($user['avatar'])) {
          $previousPaths = [__DIR__ . '/uploads/' . $user['avatar'], __DIR__ . '/data/avatars/' . $user['avatar']];
          foreach ($previousPaths as $p) {
            if (is_file($p)) @unlink($p);
          }
        }
        $updateFields[] = 'avatar = ?';
        $params[] = $avatarFilename;
      } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Avatar upload error.'];
        header('Location: profile.php');
        exit;
      }
    }

    $params[] = $userId;
    $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = ?';

    // execute update using MySQLi
    $ok = false;
    if ($stmt = $mysqli->prepare($sql)) {
      // build types: all strings for fields, final param is integer id
      $numParams = count($params);
      $types = '';
      if ($numParams > 1) {
        $types = str_repeat('s', $numParams - 1) . 'i';
      } else {
        $types = 'i';
      }
      // bind_param requires references
      $bindValues = [];
      foreach ($params as $k => $v) {
        $bindValues[] = &$params[$k];
      }
      array_unshift($bindValues, $types);
      call_user_func_array([$stmt, 'bind_param'], $bindValues);
      if ($stmt->execute()) {
        $ok = true;
      }
      $stmt->close();
    }

    if ($ok) {
        if ($stmt = $mysqli->prepare('SELECT id, first_name, last_name, middle_name, suffix, birthdate, email, division, created_at, avatar FROM users WHERE id = ? LIMIT 1')) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $updated = $res ? $res->fetch_assoc() : null;
            $stmt->close();
        } else {
            $updated = null;
        }
        if ($updated) unset($updated['password']);
        $_SESSION['user'] = $updated ?: $_SESSION['user'];
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated.'];
        header('Location: profile.php');
        exit;
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Update failed.'];
        header('Location: profile.php');
        exit;
    }
}

// Render form
// compute avatar path for preview
$avatarPath = '';
if (!empty($user['avatar'])) {
    $candidate = 'uploads/' . $user['avatar'];
    if (file_exists(__DIR__ . '/' . $candidate)) {
        $avatarPath = $candidate;
    } elseif (file_exists(__DIR__ . '/data/avatars/' . $user['avatar'])) {
        $avatarPath = 'data/avatars/' . $user['avatar'];
    }
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">
  </head>
  <body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <div class="container-fluid px-4 px-lg-5 py-5">
      <div class="row justify-content-center">
        <div class="col-12">
          <div class="landing-card p-0 overflow-hidden">
            <div class="row g-0">
              <aside class="col-md-4 bg-white p-4 d-flex flex-column align-items-center text-center">
                <img id="avatarPreview" src="<?php echo htmlspecialchars($avatarPath ?: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='); ?>" alt="avatar" class="profile-img mb-3">
                <h4 class="mb-0"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                <p class="text-muted mb-1"><?php echo htmlspecialchars($user['division']); ?></p>
                <p class="text-muted small" style="word-break:break-word"><?php echo htmlspecialchars($user['email']); ?></p>
              </aside>
              <main class="col-md-8 p-4">
                <h2 class="mb-3">Edit Profile</h2>
                <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                  <div class="alert alert-<?php echo htmlspecialchars($f['type']); ?>"><?php echo htmlspecialchars($f['message']); ?></div>
                <?php endif; ?>

                <form method="post" action="profile.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">First name</label>
                      <input name="firstName" type="text" class="form-control" required value="<?php echo htmlspecialchars($user['first_name']); ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Last name</label>
                      <input name="lastName" type="text" class="form-control" required value="<?php echo htmlspecialchars($user['last_name']); ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Middle name</label>
                      <input name="middleName" type="text" class="form-control" value="<?php echo htmlspecialchars($user['middle_name']); ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Suffix</label>
                      <input name="suffix" type="text" class="form-control" value="<?php echo htmlspecialchars($user['suffix']); ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Birthdate</label>
                      <input type="date" class="form-control" value="<?php echo htmlspecialchars($user['birthdate']); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Email</label>
                      <input name="email" type="email" class="form-control" required value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Division(s)</label>
                      <select name="division[]" class="form-select" required multiple size="5">
                        <?php
                        $opts = ['Admin Division','Office of the Provincial Director','Consumer Protection Division','Business Development Division','Planning Unit'];
                        $selectedDivisions = parse_user_divisions($user['division'] ?? '');
                        foreach ($opts as $o) {
                          $sel = in_array($o, $selectedDivisions, true) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($o) . "\" $sel>" . htmlspecialchars($o) . "</option>";
                        }
                        ?>
                      </select>
                    </div>
                    <div class="col-12">
                      <div class="row g-3">
                        <div class="col-6">
                          <label class="form-label">Current password</label>
                          <input name="currentPassword" type="password" class="form-control">
                        </div>
                        <div class="col-6">
                          <label class="form-label">New password</label>
                          <input name="password" type="password" class="form-control" minlength="6">
                        </div>
                      </div>
                    </div>

                    <div class="col-12">
                      <label class="form-label">Profile picture</label>
                      <input id="avatarInput" name="avatar" type="file" accept="image/*" class="form-control">
                      <div class="form-text">Allowed types: JPG, PNG, WEBP. Max ~3MB recommended. Preview updates immediately.</div>
                    </div>
                  </div>

                  <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save changes</button>
                    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                  </div>
                </form>
              </main>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/script.js?v=20260407k2"></script>
  </body>
</html>












