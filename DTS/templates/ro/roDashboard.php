<?php
// templates/ro/roDashboard.php
require_once '../../classes/database.php';

// 1. SECURITY CHECK[cite: 1]
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RO') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Records Officer Dashboard";
$user_id = $_SESSION['user_id'];

// 2. DATA FETCHING LOGIC (Must come before HTML)[cite: 1]

// Fetch User Profile & Dynamic Division[cite: 1]
$stmtUser = $pdo->prepare("
    SELECT u.first_name, u.last_name, u.username, u.must_change_password, d.name as division_name
    FROM auth_user u
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division d ON p.division_id = d.id
    WHERE u.id = ?
");
$stmtUser->execute([$user_id]);
$user_info = $stmtUser->fetch();

// Ensure $user_info is not null to avoid "Trying to access array offset" warning[cite: 1]
if (!$user_info) {
    $user_info = ['first_name' => 'User', 'last_name' => '', 'username' => 'unknown', 'must_change_password' => 0];
}

$must_change = $user_info['must_change_password'] ?? 0;

// Define $formal_title properly[cite: 1]
$formal_title = !empty($user_info['division_name']) ? $user_info['division_name'] : "Records Officer";

// Fetch Metrics for Cards[cite: 1]
$stmtIncoming = $pdo->prepare("SELECT COUNT(*) FROM records_document WHERE creator_id = ?");
$stmtIncoming->execute([$user_id]);
$count_incoming = $stmtIncoming->fetchColumn();

$stmtDispatch = $pdo->query("
    SELECT COUNT(d.id) FROM records_document d
    JOIN records_status s ON d.status_id = s.id
    WHERE s.category = 'APPROVED' AND d.route_type IN ('outside_dti', 'within_dti')
");
$count_dispatch = $stmtDispatch->fetchColumn();

$stmtClosed = $pdo->query("
    SELECT COUNT(d.id) FROM records_document d
    JOIN records_status s ON d.status_id = s.id
    WHERE s.category = 'CLOSED'
");
$count_closed = $stmtClosed->fetchColumn();

$stmtOnHand = $pdo->prepare("
    SELECT COUNT(d.id) FROM records_document d
    JOIN records_status s ON d.status_id = s.id
    WHERE d.creator_id = ? AND s.category NOT IN ('APPROVED', 'CLOSED', 'REJECTED')
");
$stmtOnHand->execute([$user_id]);
$count_onhand = $stmtOnHand->fetchColumn();

$count_upcoming = 0;
$count_overdue = 0;

// Dynamic Monthly Chart Data[cite: 1]
$stmtMonthly = $pdo->prepare("
    SELECT MONTH(timestamp) as month_num, COUNT(DISTINCT document_id) as doc_count
    FROM records_trackinghistory
    WHERE acted_by_id = ? AND YEAR(timestamp) = YEAR(CURDATE())
    GROUP BY MONTH(timestamp)
");
$stmtMonthly->execute([$user_id]);
$monthly_data = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

$monthly_counts = array_fill(0, 12, 0);
foreach ($monthly_data as $row) {
    $month_index = (int)$row['month_num'] - 1;
    $monthly_counts[$month_index] = (int)$row['doc_count'];
}

// Fetch Recent Dispatch Table[cite: 1]
$stmtRecentDispatch = $pdo->query("
    SELECT d.id, d.dts_no, d.subject, d.due_date, d.updated_at, a.name as address_name,
           divi.name as c_division, u.first_name, u.last_name, s.name as status_name, s.category as status_category
    FROM records_document d
    JOIN records_status s ON d.status_id = s.id
    LEFT JOIN records_address a ON d.address_id = a.id
    LEFT JOIN auth_user u ON d.creator_id = u.id
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division divi ON p.division_id = divi.id
    WHERE s.category = 'APPROVED' AND d.route_type IN ('outside_dti', 'within_dti')
    ORDER BY d.updated_at DESC LIMIT 10
");
$recent_dispatch = $stmtRecentDispatch->fetchAll();

// 3. ASSETS & HEADERS[cite: 1, 4]
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/graph.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
';

// Prepare metrics for external JS[cite: 1, 5]
$js_metrics = [
    'pieData' => [$count_incoming, $count_dispatch, $count_onhand, $count_closed],
    'barData' => $monthly_counts
];

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner">
    <div class="rd-profile-header mb-4">
        <h2 class="fw-bold text-dark mb-0"><?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></h2>
        <p class="text-secondary fs-5 mt-1"><?= htmlspecialchars($formal_title) ?></p>
    </div>

    <!-- Cards Grid -->
    <div class="cards-grid mb-5">
        <div class="custom-card card-incoming">
            <div class="card-number"><?= $count_incoming ?></div>
            <div class="card-info"><i class="fa-solid fa-file-import me-2"></i> Encoded (Incoming)</div>
        </div>
        <div class="custom-card card-approval">
            <div class="card-number"><?= $count_dispatch ?></div>
            <div class="card-info"><i class="fa-solid fa-paper-plane me-2"></i> Ready for Dispatch</div>
        </div>
        <div class="custom-card card-onhand">
            <div class="card-number"><?= $count_onhand ?></div>
            <div class="card-info"><i class="fa-solid fa-folder-open me-2"></i> On Hand</div>
        </div>
        <div class="custom-card card-upcoming">
            <div class="card-number"><?= $count_upcoming ?></div>
            <div class="card-info"><i class="fa-solid fa-calendar-day me-2"></i> Upcoming Due Date</div>
        </div>
        <div class="custom-card card-overdue">
            <div class="card-number"><?= $count_overdue ?></div>
            <div class="card-info"><i class="fa-solid fa-clock-rotate-left me-2"></i> Over Due</div>
        </div>
        <div class="custom-card card-closed">
            <div class="card-number"><?= $count_closed ?></div>
            <div class="card-info"><i class="fa-solid fa-check-double me-2"></i> Closed / Released</div>
        </div>
    </div>

    <!-- Graphs -->
    <div class="row mb-5">
        <div class="col-lg-5 mb-4">
            <div class="graph-card">
                <h5 class="fw-bold mb-4">RO Document Distribution</h5>
                <div class="chart-container">
                    <canvas id="statusPieChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-7 mb-4">
            <div class="graph-card">
                <h5 class="fw-bold mb-4">Monthly Processing Volume</h5>
                <div class="chart-container">
                    <canvas id="volumeBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Dispatch Table[cite: 1] -->
    <div class="mt-5">
        <h4 class="mb-3 fw-bold table-main-title">Ready for Dispatch:</h4>
        <div class="table-container p-0">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>DTS NO.</th>
                            <th>STATUS</th>
                            <th>DEADLINE</th>
                            <th>DATE APPROVED</th>
                            <th>TIME APPROVED</th>
                            <th>SUBJECT</th>
                            <th>DESTINATION</th>
                            <th>CREATED BY</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_dispatch)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-folder-open mb-3 opacity-25" style="font-size: 3rem;"></i>
                                    <h6 class="fw-bold text-secondary">Queue is Empty</h6>
                                    <p class="small mb-0">No approved documents are waiting to be dispatched at this time.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($recent_dispatch as $doc): ?>
                            <tr class="clickable-row" onclick="window.location.href='roOutgoing.php'">
                                <td class="fw-bold text-primary"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                <td><span class="status <?= strtolower($doc['status_category']) ?>"><?= htmlspecialchars($doc['status_name']) ?></span></td>
                                <td>
                                    <?php if ($doc['due_date']): ?>
                                        <span class="text-dark"><i class="fa-regular fa-calendar-xmark me-1"></i> <?= date('M d, Y', strtotime($doc['due_date'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($doc['updated_at'])) ?></td>
                                <td class="text-muted small"><?= date('h:i A', strtotime($doc['updated_at'])) ?></td>
                                <td class="fw-bold text-dark text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($doc['subject']) ?>"><?= htmlspecialchars($doc['subject']) ?></td>
                                <td><span class="fw-bold text-success"><i class="fa-solid fa-paper-plane me-1"></i> <?= htmlspecialchars($doc['address_name'] ?? 'External Agency') ?></span></td>
                                <td>
                                    <div class="creator-cell m-0 p-0 bg-transparent border-0">
                                        <div class="creator-avatar sm"><i class="fa-solid fa-building"></i></div>
                                        <div class="creator-info">
                                            <span class="creator-name small"><?= htmlspecialchars($doc['c_division'] ?? 'Division') ?></span>
                                            <span class="creator-role smaller"><?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?></span>
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
</div>

<!-- Data Bridge for JS[cite: 5] -->
<script> const dashboardData = <?= json_encode($js_metrics) ?>; </script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>static/js/dashboard.js"></script>

<?php require_once BASE_PATH . 'includes/header.php'; ?>