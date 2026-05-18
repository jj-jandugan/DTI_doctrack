<?php
// templates/division/divOnMyDesk.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check: Only 'Division' role can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "On My Desk";
$user_id = $_SESSION['user_id'];

// Grab messages from the session
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// 1. LINK MODULAR CSS
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
';

// ==========================================
// 2. FETCH DATA VIA DOCUMENT MANAGER
// ==========================================
$docManager = new DocumentManager($pdo);

// Pagination Setup
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Dropdown Data
    $doc_types       = $docManager->getDocumentTypes();
    $classifications = $docManager->getClassifications();

    // FETCH ON MY DESK PAGINATED DATA
    $total_records   = $docManager->getOnMyDeskTotalCount($user_id);
    $total_pages     = ceil($total_records / $limit);
    // FIXED: Changed from History to OnMyDesk method
    $desk_docs       = $docManager->getOnMyDeskPaginated($user_id, $limit, $offset);

} catch (Exception $e) {
    $error_msg = "Error loading your desk: " . $e->getMessage();
    $desk_docs = [];
}

// 3. LINK MODULAR JS
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

    <div class="filter-section mb-4">

        <div class="mb-3">
            <div class="input-group search-container shadow-sm" style="max-width: 400px; border-radius: 6px;">
                <span class="input-group-text search-icon-group bg-white border-end-0 text-muted">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" id="searchInput" class="form-control custom-search-input border-start-0 ps-0" placeholder="Search Control No. or Subject...">
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3 w-100">
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <i class="fa-solid fa-filter text-muted" style="font-size: 0.85rem;"></i>
                <span class="text-muted fw-bold small text-nowrap me-1">Filter by:</span>
            </div>

            <select id="directionFilter" class="form-select custom-select flex-shrink-0 shadow-sm" style="width: 140px; cursor: pointer;">
                <option value="">All Routes</option>
                <option value="incoming">Incoming</option>
                <option value="division">Division</option>
            </select>

            <select id="typeFilter" class="form-select custom-select flex-shrink-0 shadow-sm" style="width: 170px; cursor: pointer;">
                <option value="">All Document Types</option>
                <?php foreach ($doc_types as $type): ?>
                    <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="classFilter" class="form-select custom-select flex-shrink-0 shadow-sm" style="width: 170px; cursor: pointer;">
                <option value="">All Classifications</option>
                <?php foreach ($classifications as $class): ?>
                    <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="d-flex align-items-center gap-2 border px-3 py-1 rounded bg-white flex-shrink-0 shadow-sm" style="height: 38px;">
                <span class="text-muted small text-nowrap fw-bold">From</span>
                <input type="date" id="startDate" class="form-control form-control-sm border-0 text-secondary p-0 bg-transparent shadow-none" style="width: 110px; cursor: pointer;">
                <div class="vr mx-1" style="opacity: 0.1;"></div>
                <span class="text-muted small text-nowrap fw-bold">To</span>
                <input type="date" id="endDate" class="form-control form-control-sm border-0 text-secondary p-0 bg-transparent shadow-none" style="width: 110px; cursor: pointer;">
            </div>
        </div>

    </div>

    <div class="table-container pb-4">
        <div class="table-responsive">
            <table class="data-table" id="deskTable">
                <thead>
                    <tr>
                        <th>DTS NO.</th>
                        <th>STATUS</th>
                        <th>DEADLINE</th>
                        <th>Date & Time Created</th>
                        <th>SUBJECT</th>
                        <th>Created By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($desk_docs as $doc):
                        $is_overdue = false;
                        if ($doc['due_date'] && strtotime($doc['due_date']) < strtotime('today')) {
                            $is_overdue = true;
                        }
                    ?>
                    <tr class="desk-row clickable-row" onclick="window.location.href='divAcceptDocu.php?id=<?= $doc['id'] ?>'" style="cursor: pointer;">

                        <td class="text-primary fw-bold search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>

                        <td>
                            <?php if ($is_overdue): ?>
                                <span class="status overdue status-target">OVER DUE</span>
                            <?php else: ?>
                                <span class="status <?= strtolower($doc['status_category']) ?> status-target">
                                    <?= htmlspecialchars($doc['status_name']) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($doc['due_date']): ?>
                                <span class="<?= $is_overdue ? 'text-danger' : 'text-dark' ?>">
                                    <?= date('M d, Y', strtotime($doc['due_date'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="text-dark"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                            <div class="text-muted" style="font-size: 0.8rem;"><?= date('h:i A', strtotime($doc['created_at'])) ?></div>
                            <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                        </td>

                        <td class="text-dark search-target"><?= htmlspecialchars($doc['subject']) ?></td>

                        <td class="search-target">
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center me-2 flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span>
                                    <span class="text-muted" style="font-size: 0.75rem; font-weight: normal; line-height: 1.1;"><?= htmlspecialchars($doc['c_division'] ?? 'System User') ?></span>
                                </div>
                            </div>
                        </td>

                        <td class="d-none type-target"><?= htmlspecialchars($doc['doc_type'] ?? '') ?></td>
                        <td class="d-none class-target"><?= htmlspecialchars($doc['classification'] ?? '') ?></td>
                        <?php
                            $direction = ($doc['c_role'] === 'RO') ? 'incoming' : 'division';
                        ?>
                        <td class="d-none direction-target"><?= $direction ?></td>

                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($desk_docs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <h6 class="text-secondary fw-normal mb-1">Your Desk is Clear!</h6>
                                <p class="small mb-0">No active documents are currently routed to you.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php include BASE_PATH . 'includes/page.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof TableFilter !== 'undefined') {
        new TableFilter('.desk-row');
    }
});
</script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>