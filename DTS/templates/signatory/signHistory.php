<?php
// templates/signatory/signHistory.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RD', 'ARD'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Document History";
$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// FETCH DATA VIA CLASS
try {
    $doc_types       = $docManager->getDocumentTypes();
    $classifications = $docManager->getClassifications();
    $history_docs    = $docManager->getSignatoryHistory($user_id);
    $all_attachments = $docManager->getAllAttachmentsGrouped();
} catch (PDOException $e) {
    $error_msg = "Error loading history: " . $e->getMessage();
    $history_docs = [];
}

// ASSETS
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/panel.css">
';

$extra_js = '
<script src="' . BASE_URL . 'static/js/filters.js"></script>
<script src="' . BASE_URL . 'static/js/signatory_history.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">Document History</h2>
            <p class="text-secondary">View finalized documents you have originated, signed, or processed.</p>
        </div>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="filter-bar mb-4">
        <div class="input-group search-container me-2">
            <span class="input-group-text search-icon-group"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" id="searchInput" class="form-control custom-search-input" placeholder="Search DTS No. or Subject...">
        </div>
        <span class="filter-label">Filter by</span>
        <select id="directionFilter" class="form-select custom-select">
            <option value="">All Routes</option>
            <option value="incoming">Incoming</option>
            <option value="outgoing">Outgoing</option>
        </select>
        <select id="typeFilter" class="form-select custom-select">
            <option value="">Document Type</option>
            <?php foreach ($doc_types as $type): ?>
                <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="classFilter" class="form-select custom-select">
            <option value="">Classification</option>
            <?php foreach ($classifications as $class): ?>
                <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="d-flex align-items-center gap-2 border px-2 py-1 rounded bg-white">
            <span class="text-muted small">From:</span>
            <input type="date" id="startDate" class="form-control form-control-sm border-0">
            <span class="text-muted small">To:</span>
            <input type="date" id="endDate" class="form-control form-control-sm border-0">
        </div>
    </div>

    <div class="split-layout-container">
        <div class="table-section">
            <div class="table-container p-0">
                <div class="table-responsive">
                    <table class="data-table" id="historyTable">
                        <thead>
                            <tr>
                                <th>DTS NO.</th>
                                <th>STATUS</th>
                                <th>DEADLINE</th>
                                <th>DATE CREATED</th>
                                <th>DATE APPROVED</th>
                                <th width="20%">SUBJECT</th>
                                <th>ADDRESS</th>
                                <th>CREATED BY</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history_docs)): ?>
                                <tr><td colspan="8" class="text-center py-5 text-muted">No finalized documents found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($history_docs as $doc):
                                    $json_attachments = json_encode($all_attachments[$doc['id']] ?? []);
                                ?>
                                <tr class="history-row clickable-row"
                                    data-dts="<?= htmlspecialchars($doc['dts_no']) ?>"
                                    data-status="<?= htmlspecialchars($doc['status_name']) ?>"
                                    data-statusclass="status <?= strtolower($doc['status_category']) ?>"
                                    data-deadline="<?= $doc['due_date'] ? date('M d, Y', strtotime($doc['due_date'])) : 'No Deadline' ?>"
                                    data-created="<?= date('M d, Y h:i A', strtotime($doc['created_at'])) ?>"
                                    data-approved="<?= date('M d, Y h:i A', strtotime($doc['updated_at'])) ?>"
                                    data-subject="<?= htmlspecialchars($doc['subject']) ?>"
                                    data-particulars="<?= htmlspecialchars($doc['particulars'] ?? '') ?>"
                                    data-address="<?= htmlspecialchars($doc['address_name'] ?? 'N/A') ?>"
                                    data-creator="<?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?>"
                                    data-creatordiv="<?= htmlspecialchars($doc['c_division'] ?? 'System User') ?>"
                                    data-direction="<?= strtolower($doc['doc_direction']) ?>"
                                    data-attachments='<?= htmlspecialchars($json_attachments, ENT_QUOTES, 'UTF-8') ?>'>

                                    <td class="fw-bold text-primary search-target"><?= $doc['dts_no'] ?></td>
                                    <td><span class="status <?= strtolower($doc['status_category']) ?>"><?= $doc['status_name'] ?></span></td>
                                    <td><?= $doc['due_date'] ? date('M d, Y', strtotime($doc['due_date'])) : 'None' ?></td>
                                    <td><?= date('M d, Y', strtotime($doc['created_at'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($doc['updated_at'])) ?></td>
                                    <td class="fw-bold text-dark text-truncate search-target"><?= $doc['subject'] ?></td>
                                    <td class="text-truncate"><?= $doc['address_name'] ?? '---' ?></td>
                                    <td><span class="small"><?= $doc['c_fname'] ?></span></td>
                                    <td class="d-none type-target"><?= $doc['doc_type'] ?></td>
                                    <td class="d-none class-target"><?= $doc['classification'] ?></td>
                                    <td class="d-none direction-target"><?= strtolower($doc['doc_direction']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="side-panel-section" id="sidePanel">
            <div class="side-panel-header">
                <div>
                    <span class="text-primary fw-bold" id="paneDTS">DTS NO</span>
                    <span id="paneStatus" class="ms-2">STATUS</span>
                </div>
                <button type="button" class="btn-close" id="closePanelBtn"></button>
            </div>
            <div class="side-panel-body">
                <div class="row">
                    <div class="col-6 panel-group">
                        <div class="panel-label">DATE CREATED</div>
                        <div class="panel-value" id="paneCreated"></div>
                    </div>
                    <div class="col-6 panel-group">
                        <div class="panel-label">DATE APPROVED</div>
                        <div class="panel-value fw-bold text-success" id="paneApproved"></div>
                    </div>
                </div>
                <div class="panel-group"><div class="panel-label">SUBJECT</div><div class="textarea-style fw-bold text-dark" id="paneSubject"></div></div>
                <div class="panel-group border-top pt-3 mt-3">
                    <div class="panel-label mb-2"><i class="fa-solid fa-paperclip me-1"></i> ATTACHMENTS</div>
                    <div id="paneAttachments"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>