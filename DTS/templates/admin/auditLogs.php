<?php
// templates/admin/auditLogs.php
require_once '../../classes/database.php';

// Security Check: Only Admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "System Audit Logs";

// Link existing stylesheets from the css folder
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
';

// Helper function to colorize the action badges
function getActionStyle($action) {
    $action = strtoupper($action);
    if (strpos($action, 'CREATE') !== false) return 'action-CREATED';
    if (strpos($action, 'ROUTE') !== false || strpos($action, 'FORWARD') !== false || strpos($action, 'SEND') !== false) return 'action-ROUTED';
    if (strpos($action, 'APPROVE') !== false || strpos($action, 'SIGN') !== false || strpos($action, 'CLOSE') !== false) return 'action-APPROVED';
    if (strpos($action, 'UPDATE') !== false || strpos($action, 'EDIT') !== false) return 'action-UPDATED';
    return 'action-DEFAULT';
}

// Fetch the 500 most recent history logs
try {
    $stmt = $pdo->query("
        SELECT h.id, h.action_taken, h.remarks, h.timestamp,
               u.first_name, u.last_name, p.role,
               d.dts_no
        FROM records_trackinghistory h
        LEFT JOIN auth_user u ON h.acted_by_id = u.id
        LEFT JOIN records_userprofile p ON u.id = p.user_id
        LEFT JOIN records_document d ON h.document_id = d.id
        ORDER BY h.timestamp DESC
        LIMIT 500
    ");
    $logs = $stmt->fetchAll();

    // Automatically extract unique actions for the filter dropdown
    $available_actions = [];
    foreach ($logs as $log) {
        $action = $log['action_taken'];
        if (!in_array($action, $available_actions) && !empty($action)) {
            $available_actions[] = $action;
        }
    }
    sort($available_actions);

} catch (PDOException $e) {
    $error_msg = "Error loading logs: " . $e->getMessage();
    $logs = [];
}

// HANDLE CSV EXPORT
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="DTS_Audit_Logs_' . date('Ymd_Hi') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['DATE & TIME', 'DOCUMENT DTS NO.', 'ACTION TAKEN', 'PERFORMED BY', 'ROLE', 'REMARKS']);

    foreach ($logs as $log) {
        fputcsv($output, [
            date('M d, Y h:i A', strtotime($log['timestamp'])),
            $log['dts_no'] ?: 'System Level',
            $log['action_taken'],
            $log['first_name'] ? $log['first_name'] . ' ' . $log['last_name'] : 'System Auto-Action',
            $log['role'] ?: 'System',
            $log['remarks']
        ]);
    }
    fclose($output);
    exit;
}

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">System Audit Logs</h2>
            <p class="text-secondary">Comprehensive history of all document movements and user actions.</p>
        </div>

        <a href="?export=csv" class="btn btn-blue">
            <i class="fa-solid fa-download me-2"></i> Export Logs
        </a>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Filter Section using classes from filter.css and table.css -->
    <div class="filter-container mb-4">
        <div class="row g-3">
            <div class="col-md-6">
                <input type="text" id="searchInput" class="form-control" placeholder="Search DTS No., Performed By, or Remarks...">
            </div>
            <div class="col-md-6">
                <select id="actionFilter" class="form-select">
                    <option value="">Filter by Action Taken (All)</option>
                    <?php foreach ($available_actions as $act): ?>
                        <option value="<?= htmlspecialchars($act) ?>"><?= htmlspecialchars($act) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="table-container p-0">
        <div class="table-responsive">
            <table class="data-table" id="logsTable">
                <thead>
                    <tr>
                        <th width="15%">DATE & TIME</th>
                        <th width="15%">DOCUMENT DTS NO.</th>
                        <th width="15%">ACTION TAKEN</th>
                        <th width="20%">PERFORMED BY</th>
                        <th width="35%">REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr class="log-row">
                        <td>
                            <div class="log-date"><i class="fa-regular fa-calendar me-1"></i> <?= date('M d, Y', strtotime($log['timestamp'])) ?></div>
                            <div class="log-time"><i class="fa-regular fa-clock me-1"></i> <?= date('h:i A', strtotime($log['timestamp'])) ?></div>
                        </td>

                        <td>
                            <?php if ($log['dts_no']): ?>
                                <span class="fw-bold text-primary search-target"><?= htmlspecialchars($log['dts_no']) ?></span>
                            <?php else: ?>
                                <span class="text-muted small search-target">System Level</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="action-badge action-target <?= getActionStyle($log['action_taken']) ?>">
                                <?= htmlspecialchars($log['action_taken']) ?>
                            </span>
                        </td>

                        <td class="search-target">
                            <?php if ($log['first_name']): ?>
                                <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($log['role'] ?? 'User') ?></div>
                            <?php else: ?>
                                <span class="text-danger fw-bold"><i class="fa-solid fa-robot me-1"></i> System Auto-Action</span>
                            <?php endif; ?>
                        </td>

                        <td class="search-target">
                            <span class="text-secondary" style="font-size: 0.9rem;">
                                <?= htmlspecialchars($log['remarks'] ?? 'No remarks provided.') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if(empty($logs)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-5"><i class="fa-solid fa-clipboard-list mb-3" style="font-size: 2rem; color: #cbd5e1;"></i><br>No system logs recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$extra_js = "
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const actionFilter = document.getElementById('actionFilter');
    const tableRows = document.querySelectorAll('.log-row');

    function filterLogs() {
        const searchTerm = searchInput.value.toLowerCase();
        const actionTerm = actionFilter.value.toLowerCase();

        tableRows.forEach(row => {
            const textContent = Array.from(row.querySelectorAll('.search-target'))
                                     .map(el => el.innerText.toLowerCase())
                                     .join(' ');
            const actionContent = row.querySelector('.action-target').innerText.toLowerCase();
            const matchesSearch = textContent.includes(searchTerm);
            const matchesAction = actionTerm === '' || actionContent === actionTerm;

            row.style.display = (matchesSearch && matchesAction) ? '' : 'none';
        });
    }

    if(searchInput) searchInput.addEventListener('keyup', filterLogs);
    if(actionFilter) actionFilter.addEventListener('change', filterLogs);
});
</script>
";
require_once BASE_PATH . 'includes/footer.php';
?>