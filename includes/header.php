<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure BASE_URL is defined just in case
if (!defined('BASE_URL')) define('BASE_URL', '/DTS/');
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__) . '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>doctrack | <?= isset($page_title) ? $page_title : 'Dashboard' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>static/css/header.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>static/css/sidebar.css">

    <?= isset($extra_css) ? $extra_css : '' ?>
</head>
<body>

    <?php if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] > 0): ?>
        <div class="modal fade show d-block" id="forceUpdateModal" tabindex="-1" style="background: rgba(15, 23, 42, 0.9); z-index: 9999;" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                    <div class="modal-header text-white" style="background-color: #1d4ed8;">
                        <h5 class="modal-title fw-bold">
                            <i class="fa-solid fa-shield-halved me-2"></i>
                            <?= ($_SESSION['must_change_password'] == 1) ? 'Account Setup Required' : 'Security Update Required' ?>
                        </h5>
                    </div>
                    <form action="<?= BASE_URL ?>controllers/user.php" method="POST">
                        <div class="modal-body p-4 bg-white">
                            <input type="hidden" name="action" value="force_update_credentials">

                            <div class="alert alert-warning small mb-4" style="background-color: #fffbeb; border-color: #fef08a; color: #854d0e;">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                <?= ($_SESSION['must_change_password'] == 1)
                                    ? "Welcome! Before you can access the system, you must set up your personalized username and a secure password."
                                    : "Your password has been reset by an administrator. Please set a new secure password to continue." ?>
                            </div>

                            <?php if (isset($_SESSION['cred_error'])): ?>
                                <div class="alert alert-danger py-2 small fw-bold">
                                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($_SESSION['cred_error']) ?>
                                </div>
                                <?php unset($_SESSION['cred_error']); ?>
                            <?php endif; ?>

                            <?php if ($_SESSION['must_change_password'] == 1): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">New Username <span class="text-danger">*</span></label>
                                    <input type="text" name="new_username" class="form-control" required placeholder="Choose a unique username" autocomplete="off">
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Retype new password">
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top-0 pt-0 pb-4 px-4">
                            <button type="submit" class="btn w-100 fw-bold text-white" style="background-color: #1d4ed8; border-radius: 8px;">Save & Continue</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <style>body { overflow: hidden !important; }</style>
    <?php endif; ?>
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm custom-header p-0">
        <div id="navBgSlider" class="nav-bg-slider"></div>

        <div class="d-flex w-100 h-100 position-relative" style="z-index: 1;">
            <div class="burger-box d-flex align-items-center justify-content-center">
                <button id="sidebarToggle" class="hamburger-btn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="brand-container d-flex align-items-center ps--1">
                <img src="<?= BASE_URL ?>static/images/DTI_logo.png" height="45" alt="DTI Logo" class="brand-logo">
                <span class="brand-text ms-1">Region IX</span>
            </div>

           <div class="ms-auto d-flex align-items-center pe-4 gap-4">
                 <div class="profile-circle"></div>
                <a href="#" class="notification-bell"><i class="fas fa-bell"></i></a>
                <form action="<?= BASE_URL ?>logout.php" method="POST" class="m-0 p-0">
                    <button type="submit" class="btn-logout d-flex align-items-center gap-2">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="d-flex main-wrapper">
        <div id="sidebar-wrapper" class="sidebar-wrapper">
            <?php require BASE_PATH . 'includes/sidebar.php'; ?>
        </div>

        <div class="content-wrapper p-4 w-100" style="overflow-x: hidden;">