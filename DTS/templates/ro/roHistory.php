<?php
// templates/ro/roHistory.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RO') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "RO Document History";
$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// 2. DATA FETCHING
try {
    $doc_types = $docManager->getDocumentTypes();
    $classifications = $docManager->getClassifications();
    $history_docs = $docManager->getROHistory($user_id);
    $all_attachments = $docManager->getAllAttachmentsGrouped();
} catch (PDOException $e) {
    $error_msg = "Error loading history: " . $e->getMessage();
    $history_docs = [];
}

// 3. ASSETS
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
<script src="' . BASE_URL . 'static/js/history_panel.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">Processed History</h2>
            <p class="text-secondary">Viewing documents that have been finalized (Closed or Completed).</p>
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
            <option value="">All Flow</option>
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
                                <th>CREATED</th>
                                <th>FINALIZED</th>
                                <th width="20%">SUBJECT</th>
                                <th>LOCATION</th>
                                <th>SIGNATORY</th>
                                <th>CREATED BY</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history_docs)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-folder-open mb-3 opacity-25" style="font-size: 3.5rem;"></i><br>
                                        <h6 class="fw-bold text-secondary">History is Empty</h6>
                                    </td>
                                </tr>
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
                                    data-origin="<?= htmlspecialchars($doc['origin_name'] ?? 'N/A') ?>"
                                    data-direction="<?= htmlspecialchars($doc['direction']) ?>"
                                    data-type="<?= htmlspecialchars($doc['doc_type']) ?>"
                                    data-class="<?= htmlspecialchars($doc['classification']) ?>"
                                    data-sig="<?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? 'RD'))) ?>"
                                    data-attachments='<?= htmlspecialchars($json_attachments, ENT_QUOTES, 'UTF-8') ?>'>
                                    <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                    <td><span class="status <?= strtolower($doc['status_category']) ?>"><?= htmlspecialchars($doc['status_name']) ?></span></td>
                                    <td><?= $doc['due_date'] ? date('M d, Y', strtotime($doc['due_date'])) : '<span class="text-muted">None</span>' ?></td>
                                    <td>
                                        <div class="fw-bold"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                                        <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                                    </td>
                                    <td><div class="fw-bold"><?= date('M d, Y', strtotime($doc['updated_at'])) ?></div></td>
                                    <td class="fw-bold text-dark text-truncate search-target" style="max-width: 200px;"><?= htmlspecialchars($doc['subject']) ?></td>
                                    <td class="text-truncate search-target">
                                        <?php if ($doc['direction'] === 'incoming'): ?>
                                            <span class="text-secondary"><?= htmlspecialchars($doc['origin_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-success fw-bold"><?= htmlspecialchars($doc['address_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? 'RD'))) ?></td>
                                    <td><span class="creator-name small"><?= htmlspecialchars($doc['c_fname']) ?></span></td>
                                    <td class="d-none type-target"><?= htmlspecialchars($doc['doc_type']) ?></td>
                                    <td class="d-none class-target"><?= htmlspecialchars($doc['classification']) ?></td>
                                    <td class="d-none direction-target"><?= htmlspecialchars($doc['direction']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Universal Side Panel Section -->
        <div class="side-panel-section" id="sidePanel">
            <div class="side-panel-header">
                <div>
                    <span class="text-primary fw-bold fs-5" id="paneDTS">DTS NO</span>
                    <span id="paneStatus" class="ms-2">STATUS</span>
                </div>
                <button type="button" class="btn-close" id="closePanelBtn"></button>
            </div>
            <div class="side-panel-body">
                <!-- Panel content remains same as original -->
                <div id="dynamicAddressBlock" class="border rounded p-3 mb-4">
                    <div class="panel-label" id="addressLabel">LOCATION</div>
                    <div class="panel-value fw-bold fs-6 mt-1" id="addressValue"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-6"><div class="panel-label">DATE CREATED</div><div class="panel-value" id="paneCreated"></div></div>
                    <div class="col-6"><div class="panel-label">DATE FINALIZED</div><div class="panel-value fw-bold text-success" id="paneApproved"></div></div>
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