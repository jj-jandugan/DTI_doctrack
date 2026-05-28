<?php
// templates/signatory/signIncoming.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Signatory') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Incoming for Approval";
$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Handle Session Messages from Controller
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// FETCH DATA VIA CLASS
$classifications = $docManager->getClassifications();
$incoming_docs = $docManager->getIncomingForApproval($user_id);
$all_recipients = $docManager->getRecipientsByDocument();

// Use the existing modular CSS
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
';

// LINK THE FILTERS.JS FILE
$extra_js = '<script src="' . BASE_URL . 'static/js/filters.js"></script>';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="filter-bar d-flex flex-wrap align-items-center gap-3 mb-4 w-100">

        <div class="input-group search-container m-0 shadow-sm" style="flex: 1 1 280px; max-width: 400px; border-radius: 6px;">
            <span class="input-group-text search-icon-group bg-white border-end-0 text-muted">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="searchInput" class="form-control custom-search-input border-start-0 ps-0" placeholder="Search DTS No. or Subject...">
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3 flex-grow-1">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-filter text-muted" style="font-size: 0.85rem;"></i>
                <span class="text-muted fw-bold small text-nowrap me-1">Filter by:</span>
            </div>

            <select id="classFilter" class="form-select custom-select shadow-sm" style="width: 170px; cursor: pointer;">
                <option value="">All Classifications</option>
                <?php foreach ($classifications as $class): ?>
                    <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="d-flex align-items-center gap-2 border px-3 py-1 rounded bg-white shadow-sm" style="height: 38px;">
                <span class="text-muted small text-nowrap fw-bold">From</span>
                <input type="date" id="startDate" class="form-control form-control-sm border-0 text-secondary p-0 bg-transparent shadow-none" style="width: 110px; cursor: pointer;">

                <div class="vr mx-1" style="opacity: 0.1;"></div>

                <span class="text-muted small text-nowrap fw-bold">To</span>
                <input type="date" id="endDate" class="form-control form-control-sm border-0 text-secondary p-0 bg-transparent shadow-none" style="width: 110px; cursor: pointer;">
            </div>
        </div>
    </div>

    <div class="table-container p-0">
        <div class="table-responsive">
            <table class="data-table" id="incomingTable">
                <thead>
                    <tr>
                        <th>DTS NO.</th>
                        <th>STATUS</th>
                        <th>DEADLINE</th>
                        <th>DATE & TIME CREATED</th>
                        <th>SENDER</th>
                        <th class="col-subject">SUBJECT</th>
                        <th>ADDRESS</th>
                        <th>CREATED BY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incoming_docs as $doc): ?>
                    <tr class="doc-row clickable-row" onclick="window.location.href='signView.php?id=<?= $doc['id'] ?>'">

                        <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>

                        <td><span class="status for-approval"><?= htmlspecialchars($doc['status_name']) ?></span></td>

                        <td class="deadline-target align-middle">
                            <?php if ($doc['due_date']): ?>
                                <?php
                                    $due = new DateTime($doc['due_date']);
                                    $today = new DateTime('today');
                                    $diff = $today->diff($due);
                                    $days = (int)$diff->format('%R%a'); // Negative if past, Positive if future
                                ?>
                                <?php if ($days < 0): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-2 py-1 mb-1" style="font-size:0.7rem;">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i>Overdue
                                    </span>
                                    <div class="small fw-bold text-danger"><?= date('M d, Y', strtotime($doc['due_date'])) ?></div>
                                <?php elseif ($days === 0): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning rounded-pill px-2 py-1 mb-1" style="font-size:0.7rem;">
                                        <i class="fa-solid fa-clock me-1"></i>Due Today
                                    </span>
                                    <div class="small fw-bold text-dark"><?= date('M d, Y', strtotime($doc['due_date'])) ?></div>
                                <?php else: ?>
                                    <div class="text-dark small fw-medium">
                                        <i class="fa-regular fa-calendar me-1 text-muted"></i> <?= date('M d, Y', strtotime($doc['due_date'])) ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem;">in <?= $days ?> day(s)</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">None</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="text-dark"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                            <div class="text-muted" style="font-size: 0.8rem;"><?= date('h:i A', strtotime($doc['created_at'])) ?></div>
                        </td>

                        <td class="search-target">
                            <div class="text-dark" style="font-size: 0.85rem;">
                                <?= htmlspecialchars($doc['origin_name'] ?? 'External Office') ?>
                            </div>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                <?= htmlspecialchars($doc['sender'] ?? 'Unknown Contact') ?>
                            </div>
                        </td>

                        <td class="text-dark text-truncate search-target"><?= htmlspecialchars($doc['subject']) ?></td>

                        <td class="search-target">
                            <div class="text-dark" style="font-size: 0.85rem;">
                                <?= htmlspecialchars($doc['address_name'] ?? 'Internal Routing') ?>
                            </div>
                            <div class="text-muted mt-1" style="font-size: 0.75rem; line-height: 1.2;">
                                <?php
                                    if (isset($all_recipients[$doc['id']]) && !empty($all_recipients[$doc['id']])) {
                                        // Use explode to split the string at " (" and keep only the name part
                                        $clean_names = array_map(function($person) {
                                            return explode(' (', $person)[0];
                                        }, $all_recipients[$doc['id']]);

                                        echo implode('<br>', array_map('htmlspecialchars', $clean_names));
                                    } else {
                                        echo 'No specific personnel assigned';
                                    }
                                ?>
                            </div>
                        </td>

                        <td>
                            <div class="creator-cell d-flex align-items-center m-0 p-0 bg-transparent border-0">
                                <div class="creator-avatar d-flex justify-content-center align-items-center bg-secondary text-white rounded-circle me-2 flex-shrink-0" style="width: 32px; height: 32px;">
                                    <i class="fa-solid fa-user" style="font-size: 0.85rem;"></i>
                                </div>
                                <div class="creator-info d-flex flex-column">
                                    <span class="creator-name fw-bold text-dark" style="font-size: 0.85rem; line-height: 1.2;">
                                        <?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?>
                                    </span>
                                    <span class="text-muted" style="font-size: 0.75rem; line-height: 1.2;">
                                        <?= htmlspecialchars($doc['c_division'] ?? 'Receiving Officer') ?>
                                    </span>

                                    <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                                    <span class="d-none class-target"><?= htmlspecialchars($doc['classification'] ?? '') ?></span>
                                </div>
                            </div>
                        </td>

                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($incoming_docs)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">No incoming documents waiting for approval.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof TableFilter !== 'undefined') {
        new TableFilter('.doc-row');
    } else {
        console.error("filters.js failed to load. Check your file paths.");
    }
});
</script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>