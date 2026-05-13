<?php
// templates/ro/roDashboard.php
require_once '../../classes/database.php';
require_once '../../classes/dashboard.php'; // Changed to use the main dashboard class
require_once '../../classes/DocumentManager.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RO') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Records Officer Dashboard";
$user_id = $_SESSION['user_id'];

// 2. DATA FETCHING LOGIC
$stmtUser = $pdo->prepare("
    SELECT u.first_name, u.last_name, u.username, u.must_change_password, d.name as division_name
    FROM auth_user u
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division d ON p.division_id = d.id
    WHERE u.id = ?
");
$stmtUser->execute([$user_id]);
$user_info = $stmtUser->fetch() ?: ['first_name' => 'User', 'last_name' => '', 'username' => 'unknown', 'must_change_password' => 0];

$must_change = $user_info['must_change_password'] ?? 0;
$formal_title = !empty($user_info['division_name']) ? $user_info['division_name'] : "Records Officer";

// ==========================================
// INITIALIZE CLASSES
// ==========================================
$dashboard = new Dashboard($pdo); // Instantiate the updated class
$docManager = new DocumentManager($pdo);

// ==========================================
// FETCH METRICS FOR CARDS
// ==========================================
// Using the explicit RO methods
$incoming_count = $dashboard->getROIncomingCount($user_id);
$outgoing_count = $dashboard->getRODispatchCount($user_id);
$overdue_count  = $dashboard->getROOverdueCount($user_id);
$closed_count   = $dashboard->getROClosedCount($user_id);

$count_upcoming = 0;
$count_overdue  = 0;

// ==========================================
// FETCH CHART DATA
// ==========================================
// Using the explicit RO methods
$monthly_counts = $dashboard->getMonthlyVolume($user_id);

$pieData = [
    $dashboard->getROStatusCount($user_id, 'ONGOING'),
    $dashboard->getROStatusCount($user_id, 'FOR-APPROVAL'),
    $dashboard->getROStatusCount($user_id, 'APPROVED'),
    $dashboard->getROStatusCount($user_id, 'REJECTED'),
    $dashboard->getROStatusCount($user_id, 'CANCELLED'),
    $dashboard->getROStatusCount($user_id, 'CLOSED')
];

// Fetch Recent Dispatch Table
$recent_dispatch = $docManager->getDashboardRODispatch();

// 3. ASSETS & HEADERS
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/graph.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
';

// Prepare metrics for external JS
$js_metrics = [
    'pieData' => $pieData,
    'barData' => $monthly_counts
];

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="rd-profile-header mb-4">
        <h2 class="fw-bold text-dark mb-0"><?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></h2>
        <p class="text-secondary fs-5 mt-1"><?= htmlspecialchars($formal_title) ?></p>
    </div>

    <div class="cards-grid mb-5">
        <div class="custom-card card-incoming">
            <div class="card-number"><?= $incoming_count ?></div>
            <div class="card-info"><i class="fa-solid fa-file-import me-2"></i> Incoming</div>
        </div>
        <div class="custom-card card-approval">
            <div class="card-number"><?= $outgoing_count ?></div>
            <div class="card-info"><i class="fa-solid fa-paper-plane me-2"></i> Outgoing</div>
        </div>
        <div class="custom-card card-overdue">
            <div class="card-number"><?= $overdue_count ?></div>
            <div class="card-info"><i class="fa-solid fa-clock-rotate-left me-2"></i> Over Due</div>
        </div>
        <div class="custom-card card-closed">
            <div class="card-number"><?= $closed_count ?></div>
            <div class="card-info"><i class="fa-solid fa-check-double me-2"></i> Closed</div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-lg-5 mb-4">
            <div class="graph-card h-100 shadow-sm border p-4 bg-white rounded">
                <h5 class="fw-bold mb-4">RO Document Distribution</h5>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="statusPieChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-7 mb-4">
            <div class="graph-card h-100 shadow-sm border p-4 bg-white rounded">
                <h5 class="fw-bold mb-4">Monthly Processing Volume</h5>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="volumeBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5">
    <h4 class="mb-3 fw-bold table-main-title">Today's Outgoing Documents:</h4>
    <div class="table-container p-0 shadow-sm border rounded">
        <div class="table-responsive">
    <table class="data-table w-100 mb-0" style="table-layout: fixed;">
                <thead>
                    <tr>
                        <th style="width: 15%;">DTS NO.</th>
                        <th style="width: 15%;">STATUS</th>
                        <th style="width: 20%;">DEADLINE</th>
                        <th style="width: 30%;">SUBJECT</th>
                        <th style="width: 20%;">CREATED BY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_dispatch)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fa-solid fa-folder-open mb-3 opacity-25" style="font-size: 3rem;"></i>
                                <h6 class="fw-bold text-secondary">Queue is Empty</h6>
                                <p class="small mb-0">No approved documents are waiting to be dispatched for today.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($recent_dispatch as $doc): ?>
                        <tr class="clickable-row" onclick="window.location.href='roOutgoing.php'" style="cursor: pointer;">
                            <td class="fw-bold text-primary text-truncate"><?= htmlspecialchars($doc['dts_no']) ?></td>
                            <td>
                                <span class="status <?= strtolower($doc['status_category']) ?> small">
                                    <?= htmlspecialchars($doc['status_name']) ?>
                                </span>
                            </td>
                            <td class="small">
                                <?php if ($doc['due_date']): ?>
                                    <span class="text-dark"><i class="fa-regular fa-calendar-xmark me-1"></i> <?= date('M d, Y', strtotime($doc['due_date'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-dark text-truncate" title="<?= htmlspecialchars($doc['subject']) ?>">
                                <?= htmlspecialchars($doc['subject']) ?>
                            </td>
                            <td>
                                <div class="creator-cell m-0 p-0 d-flex align-items-center bg-transparent border-0">
                                    <div class="creator-avatar sm me-2" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                        <i class="fa-solid fa-building"></i>
                                    </div>
                                    <div class="creator-info overflow-hidden">
                                        <span class="creator-name small d-block text-truncate"><?= htmlspecialchars($doc['c_division'] ?? 'Division') ?></span>
                                        <span class="creator-role smaller d-block text-muted text-truncate"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script> const dashboardData = <?= json_encode($js_metrics) ?>; </script>
<script src="<?= BASE_URL ?>static/js/dashboard.js"></script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>