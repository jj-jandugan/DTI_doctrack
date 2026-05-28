<?php
// templates/ro/roOutgoing.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RO') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Ready for Dispatch (Outgoing)";
$user_id = $_SESSION['user_id'];

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

$docManager = new DocumentManager($pdo);
$doc_types = $docManager->getDocumentTypes();
$classifications = $docManager->getClassifications();

// 1. Fetch the documents (Now includes external_receivers column)
$outgoing_docs = $docManager->getApprovedForDispatch();
// 2. Fetch internal recipients map
$all_recipients = $docManager->getRecipientsByDocument();

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
<style>
    .address-cell { line-height: 1.2; }
    .address-main { font-weight: bold; color: #198754; display: block; }
    .address-sub { font-size: 0.75rem; color: #6c757d; display: block; }
</style>
';

$extra_js = '<script src="' . BASE_URL . 'static/js/filters.js"></script>';

require_once BASE_PATH . 'includes/header.php';
?>
    <?php if ($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

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

    <div class="table-container p-0 border-0 shadow-none">
        <div class="table-responsive">
            <table class="data-table" id="outgoingTable">
                <thead>
                    <tr>
                        <th>DTS NO.</th>
                        <th>STATUS</th>
                        <th>DATE & TIME CREATED</th>
                        <th>DEADLINE</th>
                        <th>ADDRESS</th>
                        <th>SUBJECT</th>
                        <th>SIGNATORY</th>
                        <th>CREATED BY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($outgoing_docs)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">No approved documents waiting for dispatch.</td></tr>
                    <?php else: ?>
                        <?php foreach ($outgoing_docs as $doc): ?>
                        <tr class="doc-row clickable-row" onclick="window.location.href='roViewOutgoing.php?id=<?= $doc['id'] ?>'" style="cursor: pointer;">
                            <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>
                            <td><span class="status approved">APPROVED</span></td>
                            <td>
                                <div class="text-dark"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                                <div class="text-muted small"><?= date('h:i A', strtotime($doc['created_at'])) ?></div>
                            </td>
                            <td>
                                <?php if($doc['due_date']): ?>
                                    <span class="text-danger fw-bold"><?= date('M d, Y', strtotime($doc['due_date'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="address-cell">
                                <span class="address-main"><?= htmlspecialchars($doc['address_name'] ?? 'External Agency') ?></span>
                                <span class="address-sub">
                                    <?php
                                        $sub_label = 'No specific recipient';

                                        // 1. If it is an external route, prioritize the new external_receivers column
                                        if (in_array($doc['route_type'], ['outside_dti', 'within_dti'])) {
                                            if (!empty($doc['external_receivers'])) {
                                                $sub_label = $doc['external_receivers'];
                                            } else {
                                                $sub_label = $doc['sender'] ?: 'No contact person provided';
                                            }
                                        }
                                        // 2. If it is internal (Division/Group), use the recipient names from our map
                                        else {
                                            if (isset($all_recipients[$doc['id']]) && !empty($all_recipients[$doc['id']])) {
                                                $clean_names = array_map(function($person) {
                                                    return explode(' (', $person)[0];
                                                }, $all_recipients[$doc['id']]);
                                                $sub_label = implode(', ', $clean_names);
                                            }
                                        }
                                        echo htmlspecialchars($sub_label);
                                    ?>
                                </span>
                            </td>
                            <td class="text-dark text-truncate search-target" style="max-width: 200px;" title="<?= htmlspecialchars($doc['subject']) ?>">
                                <?= htmlspecialchars($doc['subject']) ?>
                            </td>
                            <td class="small"><?= htmlspecialchars($doc['sig_fname'] . ' ' . $doc['sig_lname']) ?></td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span>
                                    <span class="text-muted small"><?= htmlspecialchars($doc['c_division'] ?? 'System User') ?></span>
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

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof TableFilter !== 'undefined') { new TableFilter('.doc-row'); }
    });
</script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>