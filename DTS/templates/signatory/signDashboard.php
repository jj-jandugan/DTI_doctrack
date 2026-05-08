<?php
// templates/signatory/signDashboard.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check: Only 'RD' or 'ARD' role can access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RD', 'ARD'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Signatory Dashboard";
$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

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

$formal_title = ($user_info['role'] === 'RD') ? "Office of the Regional Director Region IX Regional Office" : "Assistant Regional Director";

// ==========================================
// 2. FETCH DATA VIA CLASS
// ==========================================
// Card Metrics
$counts = $docManager->getSignatoryMetrics($user_id);

// Graph Data
$monthly_counts = $docManager->getMonthlyVolume($user_id);
$monthly_counts_json = json_encode($monthly_counts);

// Separated Tables (Based on image_10ff9b.png logic)
$recent_incoming = $docManager->getDashboardIncoming($user_id); // From RO (External)
$recent_outgoing = $docManager->getDashboardOutgoing($user_id); // From Divisions (Internal)

// Modular CSS
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/graph.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner" style="overflow-x: hidden; width: 100%;">

    <div class="rd-profile-header mb-4">
        <h2 class="fw-bold text-dark mb-0"><?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></h2>
        <p class="text-secondary fs-5 mt-1"><?= $formal_title ?></p>
    </div>

    <!-- Metrics Cards -->
    <div class="cards-grid mb-5">
        <div class="custom-card card-incoming">
            <div class="card-number"><?= $counts['incoming'] ?></div>
            <div class="card-info"><i class="fa-solid fa-file-import me-2"></i> Incoming</div>
        </div>
        <div class="custom-card card-onhand">
            <div class="card-number"><?= $counts['outgoing'] ?></div>
            <div class="card-info"><i class="fa-solid fa-folder-open me-2"></i> Outgoing</div>
        </div>
        <div class="custom-card card-approval">
            <div class="card-number"><?= $counts['for_approval'] ?></div>
            <div class="card-info"><i class="fa-solid fa-file-signature me-2"></i> For Approval</div>
        </div>
        <div class="custom-card card-closed">
            <div class="card-number"><?= $counts['finalized'] ?></div>
            <div class="card-info"><i class="fa-solid fa-check-double me-2"></i> Closed</div>
        </div>
    </div>

    <!-- Analytics Section -->
    <div class="row mb-5">
        <div class="col-lg-5 mb-4">
            <div class="graph-card">
                <h5 class="fw-bold mb-4">Status Distribution</h5>
                <div class="chart-container"><canvas id="statusPieChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-7 mb-4">
            <div class="graph-card">
                <h5 class="fw-bold mb-4">Monthly Volume</h5>
                <div class="chart-container"><canvas id="volumeBarChart"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Separated Document Tables (Logic from image_10ff9b.png) -->
    <div class="recent-documents-section">
        <h4 class="fw-bold mb-4">Pending for Approval:</h4>
        <div class="row">
            <!-- Incoming (External Documents via RO) -->
            <div class="col-lg-6 mb-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-file-import text-primary me-2"></i> Incoming </h5>
                <div class="table-container p-0 shadow-sm overflow-hidden">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="data-table">
                            <thead class="sticky-top bg-white">
                                <tr><th>DTS NO.</th><th>SUBJECT</th><th>CREATED BY</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_incoming as $doc): ?>
                                <tr class="clickable-row" onclick="location.href='signView.php?id=<?= $doc['id'] ?>&from=signDashboard.php'">
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                    <td class="text-truncate" style="max-width: 150px;"><?= htmlspecialchars($doc['subject']) ?></td>
                                    <td>
                                        <div class="creator-cell">
                                            <div class="creator-avatar" style="width: 24px; height: 24px;"><i class="fa-solid fa-user" style="font-size:0.6rem;"></i></div>
                                            <div class="creator-info">
                                                <span class="creator-name small"><?= htmlspecialchars($doc['c_fname']) ?></span>
                                                <span class="creator-role smaller">Records Officer</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($recent_incoming)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No pending incoming documents.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Outgoing (Internal Division Documents) -->
            <div class="col-lg-6 mb-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-file-export text-warning me-2"></i> Outgoing </h5>
                <div class="table-container p-0 shadow-sm overflow-hidden">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="data-table">
                            <thead class="sticky-top bg-white">
                                <tr><th>DTS NO.</th><th>SUBJECT</th><th>CREATED BY</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_outgoing as $doc): ?>
                                <tr class="clickable-row" onclick="location.href='signView.php?id=<?= $doc['id'] ?>&from=signDashboard.php'">
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                    <td class="text-truncate" style="max-width: 150px;"><?= htmlspecialchars($doc['subject']) ?></td>
                                    <td>
                                        <div class="creator-cell">
                                            <div class="creator-avatar" style="width: 24px; height: 24px;"><i class="fa-solid fa-user" style="font-size:0.6rem;"></i></div>
                                            <div class="creator-info">
                                                <span class="creator-name small"><?= htmlspecialchars($doc['c_fname']) ?></span>
                                                <span class="creator-role smaller"><?= htmlspecialchars($doc['c_division']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($recent_outgoing)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No pending division documents.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctxPie = document.getElementById('statusPieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'pie',
        data: {
            labels: ['Incoming', 'Outgoing', 'For Approval', 'Closed'],
            datasets: [{
                data: [<?= $counts['incoming'] ?>, <?= $counts['outgoing'] ?>, <?= $counts['for_approval'] ?>, <?= $counts['finalized'] ?>],
                backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6', '#10b981'],
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
                label: 'Processed Documents',
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
<!-- Security Action Modal -->
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