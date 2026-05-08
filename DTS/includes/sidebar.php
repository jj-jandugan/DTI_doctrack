<?php
// includes/sidebar.php
$role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar-inner">

    <?php if ($role === 'Admin'): ?>
        <a href="<?= BASE_URL ?>templates/admin/adminDashboard.php" class="sidebar-link <?= ($current_page == 'adminDashboard.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-table-cells-large sidebar-icon me-3"></i>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>templates/admin/manageUsers.php" class="sidebar-link <?= ($current_page == 'manageUsers.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-users-gear sidebar-icon me-3"></i>
            <span class="sidebar-text">Manage Users</span>
        </a>

        <a href="<?= BASE_URL ?>templates/admin/distributionGroups.php" class="sidebar-link <?= ($current_page == 'distributionGroups.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-users-rectangle sidebar-icon me-3"></i>
            <span class="sidebar-text">Distribution Groups</span>
        </a>

        <a href="<?= BASE_URL ?>templates/admin/configuration.php" class="sidebar-link <?= ($current_page == 'configuration.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-sliders sidebar-icon me-3"></i>
            <span class="sidebar-text">System Configurations</span>
        </a>

        <a href="<?= BASE_URL ?>templates/admin/auditLogs.php" class="sidebar-link <?= ($current_page == 'auditLogs.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-shield-halved sidebar-icon me-3"></i>
            <span class="sidebar-text">Audit Logs</span>
        </a>

        <a href="<?= BASE_URL ?>templates/admin/archives.php" class="sidebar-link <?= ($current_page == 'archives.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-box-archive sidebar-icon me-3"></i>
            <span class="sidebar-text">Archives</span>
        </a>
    <?php endif; ?>

    <?php if ($role === 'Division'): ?>
        <a href="<?= BASE_URL ?>templates/division/divDashboard.php" class="sidebar-link <?= ($current_page == 'divDashboard.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-table-cells-large sidebar-icon me-3"></i>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>templates/division/divOutgoing.php" class="sidebar-link <?= ($current_page == 'divOutgoing.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-file-export sidebar-icon me-3"></i>
            <span class="sidebar-text">Outgoing</span>
        </a>

        <a href="<?= BASE_URL ?>templates/division/divOnMyDesk.php" class="sidebar-link <?= ($current_page == 'divOnMyDesk.php') ? 'active' : '' ?>">
            <i class="fa-regular fa-folder sidebar-icon me-3"></i>
            <span class="sidebar-text">On My Desk</span>
        </a>

        <a href="<?= BASE_URL ?>templates/division/divHistory.php" class="sidebar-link <?= ($current_page == 'divHistory.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left sidebar-icon me-3"></i>
            <span class="sidebar-text">History</span>
        </a>
    <?php endif; ?>

    <?php if ($role === 'RO'): ?>
        <a href="<?= BASE_URL ?>templates/ro/roDashboard.php" class="sidebar-link <?= ($current_page == 'roDashboard.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-table-cells-large sidebar-icon me-3"></i>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>templates/ro/roIncoming.php" class="sidebar-link <?= ($current_page == 'roIncoming.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-file-import sidebar-icon me-3"></i>
            <span class="sidebar-text">Incoming</span>
        </a>

        <a href="<?= BASE_URL ?>templates/ro/roOutgoing.php" class="sidebar-link <?= ($current_page == 'roOutgoing.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-file-export sidebar-icon me-3"></i>
            <span class="sidebar-text">Outgoing</span>
        </a>

        <a href="<?= BASE_URL ?>templates/ro/roHistory.php" class="sidebar-link <?= ($current_page == 'roHistory.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left sidebar-icon me-3"></i>
            <span class="sidebar-text">History</span>
        </a>
    <?php endif; ?>

    <?php if ($role === 'RD' || $role === 'ARD'): ?>
        <a href="<?= BASE_URL ?>templates/signatory/signDashboard.php" class="sidebar-link <?= ($current_page == 'signDashboard.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-table-cells-large sidebar-icon me-3"></i>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>templates/signatory/signIncoming.php" class="sidebar-link <?= ($current_page == 'signIncoming.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-file-import sidebar-icon me-3"></i>
            <span class="sidebar-text">Incoming</span>
        </a>

        <a href="<?= BASE_URL ?>templates/signatory/signOutgoing.php" class="sidebar-link <?= ($current_page == 'signOutgoing.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-file-export sidebar-icon me-3"></i>
            <span class="sidebar-text">Outgoing</span>
        </a>

        <a href="<?= BASE_URL ?>templates/signatory/signHistory.php" class="sidebar-link <?= ($current_page == 'signHistory.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left sidebar-icon me-3"></i>
            <span class="sidebar-text">History</span>
        </a>
    <?php endif; ?>

</div>