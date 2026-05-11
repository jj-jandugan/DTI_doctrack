<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure BASE_URL is defined just in case
if (!defined('BASE_URL'))
    define('BASE_URL', '/DTS/');
if (!defined('BASE_PATH'))
    define('BASE_PATH', dirname(__DIR__) . '/');
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
                <div class="dropdown">
                    <button class="profile-circle-btn border-0 bg-transparent p-0" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="profile-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-user text-white"></i>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2">
                        <li class="px-3 py-2">
                            <strong><?= $_SESSION['full_name'] ?? 'User' ?></strong><br>
                            <small class="text-muted"><?= $_SESSION['role'] ?? "User" ?></small>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                            <li>
                                <a class="dropdown-item"
                                    href="<?= BASE_URL ?>templates/admin/manageUsers.php?edit=<?= $_SESSION['user_id'] ?>">
                                    <i class="fas fa-user me-2"></i>Profile </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form action="<?= BASE_URL ?>logout.php" method="post">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-right-from-bracket me-2"></i>Logout </button>
                            </form>
                        </li>
                    </ul>
                </div>
                <a href="#" class="notification-bell"><i class="fas fa-bell"></i></a>
                <form action="<?= BASE_URL ?>logout.php" method="post" class="m-0">
                    <button type="submit" class="btn-logout"><span>Logout</span></button>
                </form>
            </div>
        </div>
    </nav>
    <div class="d-flex main-wrapper">
        <div id="sidebar-wrapper" class="sidebar-wrapper">
            <?php require BASE_PATH . 'includes/sidebar.php'; ?>
        </div>
        <div class="content-wrapper p-4 w-100" style="overflow-x: hidden;">