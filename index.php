 <?php
require_once __DIR__ . '/includes/init.php';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']); 
$showSignup = !empty($_SESSION['show_signup']);
unset($_SESSION['show_signup']);
$showLogin = !empty($_SESSION['show_login']);
unset($_SESSION['show_login']);
// If already logged in, redirect to dashboard
if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DTI &mdash; Department of Trade and Industry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
      html, body { height: 100%; }
      body { display: flex; flex-direction: column; min-height: 100vh; }
      .landing-hero {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        text-align: center;
        gap: 3.5rem;
        padding: 4rem 0;
      }
      .landing-hero-content {
        max-width: 600px;
        z-index: 10;
      }
      .landing-hero h1 {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 1rem;
        color: var(--text);
        letter-spacing: -0.02em;
      }
      .landing-hero p {
        font-size: 1.25rem;
        color: var(--muted);
        margin-bottom: 2.5rem;
        line-height: 1.6;
      }
      .landing-hero .filipino-tagline {
        font-size: 1.15rem;
        color: var(--primary);
        font-weight: 700;
        font-style: italic;
        margin-top: 0.25rem;
        margin-bottom: 2rem;
      }
      .landing-hero .hero-accent {
        width: 80px;
        height: 6px;
        background: linear-gradient(90deg, #2563eb, #0ea5e9);
        border-radius: 6px;
        margin: 0 auto 1.5rem;
      }
      .landing-hero-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
      }
      .btn-hero {
        padding: 0.9rem 2rem;
        font-size: 1rem;
        font-weight: 700;
        border-radius: 8px;
        transition: all .22s ease;
      }
      .btn-hero-primary {
        background: var(--primary);
        color: #fff;
        border: none;
      }
      .btn-hero-primary:hover {
        background: var(--primary-600);
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(37, 99, 235, 0.2);
      }
      .btn-hero-secondary {
        background: transparent;
        color: var(--primary);
        border: 2px solid var(--primary);
      }
      .btn-hero-secondary:hover {
        background: var(--primary);
        color: #fff;
        transform: translateY(-2px);
      }
      .dti-logo-large {
        width: 120px;
        height: 120px;
        margin-bottom: 1rem;
      }
      @media (max-width: 640px) {
        .landing-hero h1 {
          font-size: 2rem;
        }
        .landing-hero p {
          font-size: 1.1rem;
        }
        .landing-hero-actions {
          flex-direction: column;
        }
        .btn-hero {
          width: 100%;
        }
      }
    </style>
    <style>
      /* Smaller footer when shown on the landing page */
      .landing-hero + footer.dti-footer {
        padding: 0.25rem 0.5rem !important;
        font-size: 0.85rem !important;
        border-radius: 8px !important;
      }
      footer.dti-footer {
        margin-top: auto;
      }
      .landing-hero + footer.dti-footer .footer-logo {
        width: 20px !important;
        height: 20px !important;
      }
      .landing-hero + footer.dti-footer .social-text {
        display: none !important;
      }
      @media (max-width: 520px) {
        .landing-hero + footer.dti-footer { font-size: 0.8rem !important; }
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="landing-hero">
      <div class="landing-hero-content">
        <?php if (file_exists(__DIR__ . '/assets/logoDTI.png')): ?>
          <img src="assets/logoDTI.png" alt="DTI Logo" class="dti-logo-large">
        <?php endif; ?>
        
        <div class="landing-hero-accent"></div>
        
        <h1>Department of Trade and Industry</h1>
        <p class="filipino-tagline">Serbisyong Higit pa sa Inaasahan</p>
        
        <?php if ($flash): ?>
          <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
          </div>
        <?php endif; ?>
        
        <div class="landing-hero-actions">
          <button class="btn btn-hero btn-hero-primary" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
          </button>
          <button class="btn btn-hero btn-hero-secondary" data-bs-toggle="modal" data-bs-target="#signupModal">
            <i class="bi bi-person-plus me-2"></i>Create Account
          </button>
        </div>
      </div>
    </div>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Sign In</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form id="loginForm" class="needs-validation" novalidate method="post" action="login.php">
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <div class="mb-3">
                <label for="loginEmail" class="form-label">Email</label>
                <input name="email" type="email" class="form-control" id="loginEmail" required>
                <div class="invalid-feedback">Please enter a valid email.</div>
              </div>
              <div class="mb-3">
                <label for="loginPassword" class="form-label">Password</label>
                <input name="password" type="password" class="form-control" id="loginPassword" required>
                <div class="invalid-feedback">Please enter your password.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-link me-auto" id="showCreate">Don't have an account?</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Sign In</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Sign Up Modal -->
    <div class="modal fade" id="signupModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Create Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form id="signupForm" class="needs-validation" novalidate method="post" action="register.php">
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">First name</label>
                  <input name="firstName" type="text" class="form-control" id="firstName" required>
                  <div class="invalid-feedback">First name is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Last name</label>
                  <input name="lastName" type="text" class="form-control" id="lastName" required>
                  <div class="invalid-feedback">Last name is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Middle name <span class="text-muted">(optional)</span></label>
                  <input name="middleName" type="text" class="form-control" id="middleName">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Suffix <span class="text-muted">(optional)</span></label>
                  <input name="suffix" type="text" class="form-control" id="suffix">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Birthdate</label>
                  <input name="birthdate" type="date" class="form-control" id="birthdate" required>
                  <div class="invalid-feedback">Birthdate is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input name="signupEmail" type="email" class="form-control" id="signupEmail" required>
                  <div class="invalid-feedback">Valid email is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Password</label>
                  <input name="signupPassword" type="password" class="form-control" id="signupPassword" required minlength="6">
                  <div class="invalid-feedback">Password (min 6 chars) is required.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Division(s)</label>
                  <select id="division" name="division[]" class="form-select" required multiple size="5">
                    <option>Admin Division</option>
                    <option>Office of the Provincial Director</option>
                    <option>Consumer Protection Division</option>
                    <option>Business Development Division</option>
                    <option>Planning Unit</option>
                  </select>
                  <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple divisions.</div>
                  <div class="invalid-feedback">Please select at least one division.</div>
                </div>
              </div>

              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" id="agreeTerms" name="agreeTerms" required>
                <label class="form-check-label" for="agreeTerms">
                  I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and the <a href="https://privacy.gov.ph/data-privacy-act/" target="_blank" rel="noopener">Data Privacy Act of 2012</a>.
                </label>
                <div class="invalid-feedback">You must accept the terms and data privacy statement.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/script.js?v=20260407k2"></script>
    <script>
      // Open modals if server requested (e.g., on validation failure)
      (function(){
        const showSignup = <?php echo $showSignup ? 'true' : 'false'; ?>;
        const showLogin = <?php echo $showLogin ? 'true' : 'false'; ?>;
        const flash = <?php echo json_encode($flash ?: null); ?>;
        if (showSignup) {
          var signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
          signupModal.show();
        }
        if (showLogin) {
          var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
          loginModal.show();
        }
        // If server sent a flash message, show SweetAlert for feedback
        if (flash) {
          const iconMap = { 'danger': 'error', 'success': 'success', 'info': 'info', 'warning': 'warning' };
          const icon = iconMap[flash.type] || 'info';
          // If login modal is shown, wait briefly so modal is visible underneath the alert
          const showSwal = () => {
            Swal.fire({
              icon: icon,
              title: flash.type === 'danger' ? 'Sign in failed' : flash.message,
              text: flash.type === 'danger' ? flash.message : undefined,
              confirmButtonText: 'OK'
            });
          };
          if (showLogin) {
            setTimeout(showSwal, 250);
          } else {
            showSwal();
          }
        }
        // Handle create account link in login modal
        const showCreate = document.getElementById('showCreate');
        if (showCreate) {
          showCreate.addEventListener('click', function() {
            var loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
            if (loginModal) loginModal.hide();
            var signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
            signupModal.show();
          });
        }
      })();
    </script>
  </body>
</html>












