<?php
// templates/division/divDashboard.php
require_once '../../classes/database.php';
require_once '../../classes/Dashboard.php';
require_once '../../classes/documentManager.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Dashboard Overview";
$user_id = $_SESSION['user_id'];

$cred_error = $_SESSION['cred_error'] ?? '';
$cred_success = $_SESSION['cred_success'] ?? '';
unset($_SESSION['cred_error'], $_SESSION['cred_success']);

// ==========================================
// 2. DATA FETCHING LOGIC
// ==========================================

$stmtUser = $pdo->prepare("
    SELECT u.first_name, u.last_name, u.username, u.must_change_password, d.name as division_name
    FROM auth_user u
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division d ON p.division_id = d.id
    WHERE u.id = ?
");
$stmtUser->execute([$user_id]);
$user_info = $stmtUser->fetch() ?: ['first_name' => 'Division', 'last_name' => 'User', 'username' => 'unknown', 'must_change_password' => 0];

$must_change = $user_info['must_change_password'];

$dashboard = new Dashboard($pdo); // Instantiate the updated class
$docManager = new DocumentManager($pdo);

// FETCHING THE 6 CORE CATEGORY NUMBERS
$count_incoming  = $dashboard->getIncomingCount($user_id);
$count_approved = $dashboard->getApprovedOutgoingCount($user_id);
$count_approval  = $dashboard->getApprovalCount($user_id);

// For the total 'Closed' count, the Dashboard class now includes Rejected and Cancelled
// per our previous update, but we can also fetch them individually if your class supports it:
$count_closed    = $dashboard->getStrictUserClosedCount($user_id);

$count_upcoming  = 0;
$count_overdue   = 0;

$incoming_docs   = $docManager->getDashboardDivIncoming($user_id);

// ==========================================
// 3. ASSETS & MODULAR LINKS
// ==========================================
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <h2 class="user-name text-dark fw-bold mb-0"><?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></h2>
    <h5 class="user-dept text-secondary fw-normal mb-4"><?= htmlspecialchars($user_info['division_name'] ?? 'Division Staff') ?></h5>

    <?php if ($cred_success): ?>
        <div class="alert alert-success mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($cred_success) ?></div>
    <?php endif; ?>

    <div class="cards-grid">
        <a href="divOnMyDesk.php" class="card-link">
            <div class="custom-card card-incoming">
                <div class="card-number"><?= $count_incoming ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-file-import"></i></div>
                    <span>Incoming</span>
                </div>
            </div>
        </a>
        <a href="divOutgoing.php" class="card-link">
            <div class="custom-card card-onhand">
                <div class="card-number"><?= $count_approved ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-folder-open"></i></div>
                    <span>Approved</span>
                </div>
            </div>
        </a>
        <a href="divOutgoing.php" class="card-link">
            <div class="custom-card card-approval">
                <div class="card-number"><?= $count_approval ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-file-signature"></i></div>
                    <span>For Approval</span>
                </div>
            </div>
        </a>

        <a href="divHistory.php" class="card-link">
            <div class="custom-card card-closed">
                <div class="card-number"><?= $count_closed ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <span>Closed</span>
                </div>
            </div>
        </a>

        <a href="divOnMyDesk.php" class="card-link">
            <div class="custom-card card-overdue">
                <div class="card-number"><?= $count_overdue ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <span>Overdue</span>
                </div>
            </div>
        </a>
    </div>

    <div class="mt-5">
    <h4 class="mb-3 fw-bold table-main-title">Incoming Documents:</h4>
    <div class="table-container p-0 shadow-sm border rounded">
        <div class="table-responsive">
            <table class="data-table w-100 mb-0" style="table-layout: fixed; min-width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 12%;">DTS NO.</th>
                        <th style="width: 12%;">STATUS</th>
                        <th style="width: 15%;">DEADLINE</th>
                        <th style="width: 25%;">SUBJECT</th>
                        <th style="width: 18%;">SIGNATORY</th>
                        <th style="width: 18%;">CREATED BY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($incoming_docs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fa-solid fa-inbox mb-3 opacity-25" style="font-size: 3rem;"></i><br>
                                <h6 class="fw-bold text-secondary">No Incoming Documents</h6>
                                <p class="small mb-0">No documents have been routed to you today.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($incoming_docs as $doc): ?>
                            <tr class="clickable-row" onclick="window.location.href='divAcceptDocu.php?id=<?= $doc['id'] ?>'" style="cursor: pointer;">
                                <td class="fw-bold text-primary text-truncate"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                <td>
                                    <span class="status <?= strtolower($doc['status_category']) ?> small">
                                        <?= htmlspecialchars($doc['status_name']) ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?php if($doc['due_date']): ?>
                                        <span class="text-danger fw-bold"><i class="fa-regular fa-calendar-xmark me-1"></i> <?= date('M d, Y', strtotime($doc['due_date'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-dark text-truncate" title="<?= htmlspecialchars($doc['subject']) ?>">
                                    <?= htmlspecialchars($doc['subject']) ?>
                                </td>
                                <td class="small text-truncate" title="<?= htmlspecialchars($doc['address_name'] ?? '---') ?>">
                                    <?= htmlspecialchars($doc['address_name'] ?? '---') ?>
                                </td>
                                <td>
                                    <div class="creator-cell m-0 p-0 d-flex align-items-center bg-transparent border-0">
                                        <div class="creator-avatar sm me-2" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                        <div class="creator-info overflow-hidden">
                                            <span class="creator-name small d-block text-truncate"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span>
                                            <span class="creator-role smaller d-block text-muted text-truncate"><?= htmlspecialchars($doc['c_division'] ?? 'System User') ?></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($must_change == 1 || !empty($cred_error)): ?>
<?php endif; ?>

<script src="<?= BASE_URL ?>static/js/dashboard.js"></script>
<?php require_once BASE_PATH . 'includes/footer.php'; ?>