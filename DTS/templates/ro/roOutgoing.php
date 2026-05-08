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
$outgoing_docs = $docManager->getApprovedForDispatch();

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
';

$extra_js = '<script src="' . BASE_URL . 'static/js/filters.js"></script>';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">Ready for Dispatch</h2>
            <p class="text-secondary">Review approved documents ready for external release.</p>
        </div>
    </div>

    <?php if ($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="filter-bar mb-4">
        <div class="input-group search-container me-2">
            <span class="input-group-text search-icon-group"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" id="searchInput" class="form-control custom-search-input" placeholder="Search DTS No. or Subject...">
        </div>
        <select id="classFilter" class="form-select custom-select">
            <option value="">Classification</option>
            <?php foreach ($classifications as $class): ?>
                <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="typeFilter" class="form-select custom-select">
            <option value="">Document Type</option>
            <?php foreach ($doc_types as $type): ?>
                <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="d-flex align-items-center gap-2 border px-2 py-1 rounded bg-white">
            <span class="text-muted small">From:</span>
            <input type="date" id="startDate" class="form-control form-control-sm border-0">
            <span class="text-muted small">To:</span>
            <input type="date" id="endDate" class="form-control form-control-sm border-0">
        </div>
    </div>

    <div class="table-container p-0">
        <div class="table-responsive">
            <table class="data-table" id="outgoingTable">
                <thead>
                    <tr>
                        <th>DTS NO.</th>
                        <th>STATUS</th>
                        <th>DEADLINE</th>
                        <th>SUBJECT</th>
                        <th>DESTINATION</th>
                        <th>SIGNATORY</th>
                        <th>CREATED BY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($outgoing_docs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No approved documents waiting for dispatch.</td></tr>
                    <?php else: ?>
                        <?php foreach ($outgoing_docs as $doc): ?>
                        <tr class="doc-row clickable-row" onclick="window.location.href='roViewOutgoing.php?id=<?= $doc['id'] ?>'">
                            <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>
                            <td><span class="status approved">APPROVED</span></td>
                            <td><?= $doc['due_date'] ? date('M d, Y', strtotime($doc['due_date'])) : '<span class="text-muted">None</span>' ?></td>
                            <td class="fw-bold text-dark text-truncate search-target" style="max-width: 250px;"><?= htmlspecialchars($doc['subject']) ?></td>
                            <td class="search-target text-success fw-bold"><?= htmlspecialchars($doc['address_name'] ?? 'External Agency') ?></td>
                            <td><?= htmlspecialchars($doc['sig_fname'] . ' ' . $doc['sig_lname']) ?></td>
                            <td>
                                <div class="creator-cell p-0 bg-transparent">
                                    <span class="creator-name small"><?= htmlspecialchars($doc['c_fname']) ?></span>
                                    <span class="d-none class-target"><?= htmlspecialchars($doc['classification']) ?></span>
                                    <span class="d-none type-target"><?= htmlspecialchars($doc['doc_type']) ?></span>
                                    <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
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