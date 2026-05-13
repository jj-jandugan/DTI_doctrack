<?php
// templates/signatory/signDashboard.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';
require_once '../../classes/dashboard.php'; // Ensure Dashboard class is loaded

// Security Check: Only 'RD' or 'ARD' role can access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RD', 'ARD'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Signatory Dashboard";
$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);
$dashboard = new Dashboard($pdo); // Initialize Dashboard Logic

// Initialize notification variables
$cred_error = $_SESSION['cred_error'] ?? '';
$cred_success = $_SESSION['cred_success'] ?? '';
unset($_SESSION['cred_error'], $_SESSION['cred_success']);

// ==========================================
// 1. FETCH USER PROFILE & SECURITY STATUS
// ==========================================
$stmtUser = $pdo->prepare("
    SELECT u.first_name, u.last_name, u.username, u.must_change_password, p.role
    FROM auth_user u
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmtUser->execute([$user_id]);
$user_info = $stmtUser->fetch();
$must_change = $user_info['must_change_password'] ?? 0;

$formal_title = ($user_info['role'] === 'RD') ? "Office of the Regional Director" : "Assistant Regional Director";

// ==========================================
// 2. FETCH DATA VIA CLASS (SIGNATORY COUNTS)
// ==========================================
$incoming_count = $dashboard->getSignatoryIncomingCount($user_id);
$outgoing_count = $dashboard->getSignatoryOutgoingCount($user_id);
$approved_count = $dashboard->getSignatoryCountByStatus($user_id, 'APPROVED');
$rejected_count = $dashboard->getSignatoryCountByStatus($user_id, 'REJECTED');
$closed_count   = $dashboard->getSignatoryCountByStatus($user_id, 'CLOSED');

// Graph Data (Monthly Volume)
$monthly_counts = $dashboard->getMonthlyVolume($user_id);
$monthly_counts_json = json_encode($monthly_counts);

// Separated Tables
$recent_incoming = $docManager->getDashboardIncoming($user_id); // From RO (External)
$recent_outgoing = $docManager->getDashboardOutgoing($user_id); // From Divisions (Internal)

// Modular CSS
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/graph.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">

<style>
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr) !important;
        gap: 1.25rem;
    }

    /* Table Fixes: Prevent Overflow & Shrink Gaps */
    .dashboard-table {
        table-layout: fixed !important;
        width: 100% !important;
        min-width: 0 !important;
        margin-bottom: 0;
    }

    .dashboard-table th, .dashboard-table td {
        padding: 12px 15px !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis; /* Adds "..." if text is too long */
        vertical-align: middle;
    }

    /* Precise Column Widths = 100% */
    .col-dts { width: 30%; }
    .col-subject { width: 40%; }
    .col-creator { width: 30%; }

    @media (max-width: 1200px) {
        .cards-grid { grid-template-columns: repeat(3, 1fr) !important; }
    }
    @media (max-width: 768px) {
        .cards-grid { grid-template-columns: repeat(2, 1fr) !important; }
    }
</style>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner" style="overflow-x: hidden; width: 100%;">

    <div class="rd-profile-header mb-4">
        <h2 class="fw-bold text-dark mb-0"><?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></h2>
        <p class="text-secondary fs-5 mt-1"><?= $formal_title ?> IX</p>
    </div>

    <div class="cards-grid mb-5">
        <div class="custom-card card-incoming">
            <div class="card-number"><?= number_format($incoming_count) ?></div>
            <div class="card-info"><i class="fa-solid fa-file-import me-2"></i> Incoming</div>
        </div>
        <div class="custom-card card-approval">
            <div class="card-number"><?= number_format($outgoing_count) ?></div>
            <div class="card-info"><i class="fa-solid fa-file-export me-2"></i> Outgoing</div>
        </div>
        <div class="custom-card card-onhand">
            <div class="card-number"><?= number_format($approved_count) ?></div>
            <div class="card-info"><i class="fa-solid fa-check-double me-2"></i> Approved</div>
        </div>
        <div class="custom-card card-overdue">
            <div class="card-number"><?= number_format($rejected_count) ?></div>
            <div class="card-info"><i class="fa-solid fa-file-circle-xmark me-2"></i> Rejected</div>
        </div>
        <div class="custom-card card-closed">
            <div class="card-number"><?= number_format($closed_count) ?></div>
            <div class="card-info"><i class="fa-solid fa-folder-closed me-2"></i> Closed</div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-lg-5 mb-4">
            <div class="graph-card h-100">
                <h5 class="fw-bold mb-4">Status Distribution</h5>
                <div class="chart-container"><canvas id="statusPieChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-7 mb-4">
            <div class="graph-card h-100">
                <h5 class="fw-bold mb-4">Monthly Action Volume</h5>
                <div class="chart-container"><canvas id="volumeBarChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="recent-documents-section">
        <h4 class="fw-bold mb-4">Pending for Approval:</h4>
        <div class="row">

            <div class="col-lg-6 mb-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-file-import text-primary me-2"></i> Incoming </h5>
                <div class="table-container p-0 shadow-sm overflow-hidden">
                    <div class="table-responsive">
                        <table class="data-table dashboard-table">
                            <thead class="bg-white border-bottom">
                                <tr>
                                    <th class="col-dts text-muted">DTS NO.</th>
                                    <th class="col-subject text-muted">SUBJECT</th>
                                    <th class="col-creator text-muted">CREATED BY</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_incoming as $doc): ?>
                                <tr class="clickable-row" onclick="location.href='signView.php?id=<?= $doc['id'] ?>&from=signDashboard.php'">
                                    <td class="fw-bold text-primary text-truncate" title="<?= htmlspecialchars($doc['dts_no']) ?>"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                    <td class="text-truncate" title="<?= htmlspecialchars($doc['subject']) ?>"><?= htmlspecialchars($doc['subject']) ?></td>
                                    <td class="overflow-hidden">
                                        <div class="creator-cell m-0 p-0 d-flex align-items-center bg-transparent border-0">
                                            <div class="creator-avatar" style="width: 24px; height: 24px;"><i class="fa-solid fa-user" style="font-size:0.6rem;"></i></div>
                                            <div class="creator-info overflow-hidden ms-2">
                                                <span class="creator-name small d-block text-truncate"><?= htmlspecialchars($doc['c_fname']) ?></span>
                                                <span class="creator-role smaller d-block text-muted text-truncate">Records Officer</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($recent_incoming)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No pending incoming documents for today.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-file-export text-warning me-2"></i> Outgoing </h5>
                <div class="table-container p-0 shadow-sm overflow-hidden">
                    <div class="table-responsive">
                        <table class="data-table dashboard-table">
                            <thead class="bg-white border-bottom">
                                <tr>
                                    <th class="col-dts text-muted">DTS NO.</th>
                                    <th class="col-subject text-muted">SUBJECT</th>
                                    <th class="col-creator text-muted">CREATED BY</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_outgoing as $doc): ?>
                                <tr class="clickable-row" onclick="location.href='signView.php?id=<?= $doc['id'] ?>&from=signDashboard.php'">
                                    <td class="fw-bold text-primary text-truncate" title="<?= htmlspecialchars($doc['dts_no']) ?>"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                    <td class="text-truncate" title="<?= htmlspecialchars($doc['subject']) ?>"><?= htmlspecialchars($doc['subject']) ?></td>
                                    <td class="overflow-hidden">
                                        <div class="creator-cell m-0 p-0 d-flex align-items-center bg-transparent border-0">
                                            <div class="creator-avatar" style="width: 24px; height: 24px;"><i class="fa-solid fa-user" style="font-size:0.6rem;"></i></div>
                                            <div class="creator-info overflow-hidden ms-2">
                                                <span class="creator-name small d-block text-truncate"><?= htmlspecialchars($doc['c_fname']) ?></span>
                                                <span class="creator-role smaller d-block text-muted text-truncate"><?= htmlspecialchars($doc['c_division']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($recent_outgoing)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No pending outgoing documents for today.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Status Distribution Pie Chart (Updated for 5 metrics)
    const ctxPie = document.getElementById('statusPieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'pie',
        data: {
            labels: ['Incoming', 'Outgoing', 'Approved', 'Rejected', 'Closed'],
            datasets: [{
                data: [<?= $incoming_count ?>, <?= $outgoing_count ?>, <?= $approved_count ?>, <?= $rejected_count ?>, <?= $closed_count ?>],
                backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#6366f1'],
                borderWidth: 2, borderColor: '#ffffff'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    const ctxBar = document.getElementById('volumeBarChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Documents Acted Upon',
                data: <?= $monthly_counts_json ?>,
                backgroundColor: '#263D81',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } },
            plugins: { legend: { display: false } }
        }
    });
</script>

<?php if ($must_change == 1): ?>
<div class="modal fade" id="forceUpdateModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal border-danger">
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-shield-halved text-danger mb-3 display-4"></i>
                <h5 class="fw-bold">Security Action Required</h5>
                <p class="small text-muted mb-4">Please update your credentials before proceeding.</p>
                <form method="POST" action="../../controllers/user.php">
                    <input type="hidden" name="action" value="force_update_credentials">
                    <input type="text" name="new_username" class="form-control mb-3" value="<?= htmlspecialchars($user_info['username']) ?>" required>
                    <input type="password" name="new_password" class="form-control mb-3" placeholder="New Password" required>
                    <input type="password" name="confirm_password" class="form-control mb-4" placeholder="Confirm Password" required>
                    <button type="submit" class="btn btn-danger w-100">Update & Secure Account</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        new bootstrap.Modal(document.getElementById('forceUpdateModal')).show();
    });
</script>
<?php endif; ?>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>