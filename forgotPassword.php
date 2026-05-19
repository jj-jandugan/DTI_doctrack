<?php
// forgotPassword.php
session_start();
require_once 'classes/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') header("Location: templates/admin/adminDashboard.php");
    elseif ($_SESSION['role'] === 'RO') header("Location: templates/ro/roDashboard.php");
    elseif ($_SESSION['role'] === 'Division') header("Location: templates/division/divDashboard.php");
    elseif (in_array($_SESSION['role'], ['RD', 'ARD'])) header("Location: templates/signatory/signDashboard.php");
    exit;
}

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - DTI Document Tracking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="static/css/password.css">
</head>
<body>
    <div class="password-wrapper">
        <div class="password-card">

            <div class="text-center">
                <img src="static/images/DTI_logo.png" alt="DTI Logo" class="password-logo">
                <h4 class="password-title">Reset Password</h4>
                <p class="password-subtitle">Enter your email address and we'll send you a secure link to reset your password.</p>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    <div><?= htmlspecialchars($success_msg) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <div><?= htmlspecialchars($error_msg) ?></div>
                </div>
            <?php endif; ?>

            <form action="controllers/processForgot.php" method="POST" id="forgotForm">
                <div class="mb-4">
                    <label for="email" class="form-label fw-bold text-secondary small text-uppercase">Email Address</label>
                    <div class="input-group custom-input-group">
                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@dti.gov.ph" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-password w-100 mb-3" id="btnSubmit">
                    Send Reset Link <i class="fa-solid fa-paper-plane ms-1"></i>
                </button>

                <div class="text-center mt-4">
                    <a href="login.php" class="back-link">
                        <i class="fa-solid fa-arrow-left me-2"></i> Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Simple loading effect to prevent double submissions
        document.getElementById('forgotForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i> Sending...';
        });
    </script>
</body>
</html>