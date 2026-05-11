<?php
// templates/admin/adminDashboard.php
require_once '../../classes/database.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Admin Dashboard";

// Include standard CSS plus graph CSS if you have it
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/graph.css">
<style>
    .chart-container {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        height: 350px;
        position: relative;
    }
</style>
';

// Include Chart.js library
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// ==========================================
// 1. FETCH DATA FOR SUMMARY CARDS
// ==========================================
$total_users = $pdo->query("SELECT COUNT(*) FROM auth_user WHERE is_active = 1")->fetchColumn();
$total_divisions = $pdo->query("SELECT COUNT(*) FROM records_division")->fetchColumn();
$total_docs = $pdo->query("SELECT COUNT(*) FROM records_document")->fetchColumn();

// Count documents that are still in progress (Ongoing or For Approval)
$active_docs = $pdo->query("
    SELECT COUNT(*) FROM records_document d
    JOIN records_status s ON d.status_id = s.id
    WHERE s.category IN ('ONGOING', 'FOR-APPROVAL')
")->fetchColumn();


// ==========================================
// 2. FETCH DATA FOR GRAPHS
// ==========================================
// Graph 1: Document Status Breakdown (Doughnut Chart)
$stmtStatuses = $pdo->query("
    SELECT s.name, COUNT(d.id) as doc_count
    FROM records_status s
    LEFT JOIN records_document d ON s.id = d.status_id
    GROUP BY s.id
");
$statusData = $stmtStatuses->fetchAll();

$statusLabels = [];
$statusCounts = [];
foreach ($statusData as $row) {
    $statusLabels[] = $row['name'];
    $statusCounts[] = $row['doc_count'];
}

// Graph 2: Users per Division (Bar Chart)
$stmtDivs = $pdo->query("
    SELECT d.abbreviation, COUNT(p.id) as user_count
    FROM records_division d
    LEFT JOIN records_userprofile p ON d.id = p.division_id
    GROUP BY d.id
");
$divData = $stmtDivs->fetchAll();

$divLabels = [];
$divCounts = [];
foreach ($divData as $row) {
    $divLabels[] = $row['abbreviation'];
    $divCounts[] = $row['user_count'];
}

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-4">
    <div class="mb-4">
        <h2 class="text-dark fw-bold mb-0">System Overview</h2>
        <p class="text-secondary">Monitor system health, user statistics, and document tracking data.</p>
    </div>
    <div class="cards-grid mb-5">
        <div class="custom-card card-incoming">
            <div class="card-number"><?= number_format($total_docs) ?></div>
            <div class="card-info">
                <div class="icon-box"><i class="fa-solid fa-file-lines"></i></div>
                <span>Total<br>Documents</span>
            </div>
        </div>
        <div class="custom-card card-approval">
            <div class="card-number"><?= number_format($active_docs) ?></div>
            <div class="card-info">
                <div class="icon-box"><i class="fa-solid fa-spinner"></i></div>
                <span>Active<br>Routings</span>
            </div>
        </div>
        <div class="custom-card card-closed">
            <div class="card-number"><?= number_format($total_users) ?></div>
            <div class="card-info">
                <div class="icon-box"><i class="fa-solid fa-users"></i></div>
                <span>Active<br>System Users</span>
            </div>
        </div>
        <div class="custom-card card-onhand">
            <div class="card-number"><?= number_format($total_divisions) ?></div>
            <div class="card-info">
                <div class="icon-box"><i class="fa-solid fa-building"></i></div>
                <span>Registered<br>Divisions</span>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="chart-container">
                <h6 class="fw-bold mb-3 text-secondary text-center">Documents by Status</h6>
                <canvas id="statusChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h6 class="fw-bold mb-3 text-secondary text-center">System Users per Division</h6>
                <canvas id="divisionChart"></canvas>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function () {

        // Pass PHP arrays safely to JavaScript
        const statusLabels = <?= json_encode($statusLabels) ?>;
        const statusCounts = <?= json_encode($statusCounts) ?>;

        const divLabels = <?= json_encode($divLabels) ?>;
        const divCounts = <?= json_encode($divCounts) ?>;

        // 1. Status Doughnut Chart
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                cutout: '70%' // Makes the doughnut thinner and modern
            }
        });

        // 2. Division Bar Chart
        const ctxDiv = document.getElementById('divisionChart').getContext('2d');
        new Chart(ctxDiv, {
            type: 'bar',
            data: {
                labels: divLabels,
                datasets: [{
                    label: 'Total Users',
                    data: divCounts,
                    backgroundColor: '#263D81',
                    borderRadius: 4 // Rounds the top of the bars
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false } // Hide legend for single dataset
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 } // Ensure it counts by whole numbers
                    }
                }
            }
        });
    });
</script>
<?php require_once BASE_PATH . 'includes/footer.php'; ?>