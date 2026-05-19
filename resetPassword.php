<?php
// resetPassword.php
session_start();
require_once 'classes/database.php';

$token = $_GET['token'] ?? '';
$is_valid = false;
$error_msg = '';

if (empty($token)) {
    $error_msg = "Invalid password reset link.";
} else {
    // Check if token exists AND has not expired
    $stmt = $pdo->prepare("SELECT id FROM auth_user WHERE reset_token = ? AND token_expires > NOW() LIMIT 1");
    $stmt->execute([$token]);
    if ($stmt->fetch()) {
        $is_valid = true;
    } else {
        $error_msg = "This password reset link is invalid or has expired. Please request a new one.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - DTI Document Tracking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="static/css/login.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card p-5 shadow-lg rounded-4 bg-white" style="max-width: 450px; width: 100%;">

            <div class="text-center mb-4">
                <img src="static/images/DTI_logo.png" alt="DTI Logo" class="img-fluid mb-3" style="max-height: 80px;">
                <h4 class="fw-bold text-dark mb-1">Create New Password</h4>
            </div>

            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error_msg']) ?></div>
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>

            <?php if (!$is_valid): ?>
                <div class="alert alert-danger text-center">
                    <i class="fa-solid fa-circle-xmark fs-1 text-danger mb-2"></i><br>
                    <strong>Link Expired!</strong><br>
                    <?= htmlspecialchars($error_msg) ?>
                </div>
                <div class="text-center mt-4">
                    <a href="forgotPassword.php" class="btn btn-primary fw-bold w-100">Request New Link</a>
                </div>
            <?php else: ?>
                <form action="controllers/updatePassword.php" method="POST" id="resetForm">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small text-uppercase">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control border-start-0 ps-0 bg-light" name="new_password" id="new_password" required minlength="8" placeholder="Must be at least 8 characters">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary small text-uppercase">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-shield-check"></i></span>
                            <input type="password" class="form-control border-start-0 ps-0 bg-light" name="confirm_password" id="confirm_password" required placeholder="Type password again">
                        </div>
                        <small id="passwordMatchError" class="text-danger d-none mt-1 fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i> Passwords do not match!</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mb-3" id="btnSubmit">
                        Secure My Account <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const form = document.getElementById('resetForm');
        const pass1 = document.getElementById('new_password');
        const pass2 = document.getElementById('confirm_password');
        const errorMsg = document.getElementById('passwordMatchError');
        const btnSubmit = document.getElementById('btnSubmit');

        if (form) {
            form.addEventListener('submit', function(e) {
                if (pass1.value !== pass2.value) {
                    e.preventDefault();
                    errorMsg.classList.remove('d-none');
                    pass2.classList.add('border-danger');
                } else {
                    errorMsg.classList.add('d-none');
                    btnSubmit.disabled = true;
                    btnSubmit.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i> Updating...';
                }
            });
        }
    </script>
</body>
</html>