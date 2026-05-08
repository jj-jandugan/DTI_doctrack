<?php
// templates/division/divHistory.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

// Security Check: Only 'Division' role can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Document History";
$user_id = $_SESSION['user_id'];

// ==========================================
// FETCH ALL DATA VIA OOP
// ==========================================
$docManager = new DocumentManager($pdo);

try {
    $doc_types       = $docManager->getDocumentTypes();
    $classifications = $docManager->getClassifications();
    $history_docs    = $docManager->getUserHistory($user_id);
    $all_attachments = $docManager->getAllAttachmentsGrouped();
} catch (Exception $e) {
    $error_msg = "Error loading history: " . $e->getMessage();
    $history_docs = [];
}

// ==========================================
// ASSETS AND MODULAR LINKS
// ==========================================
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
<script src="' . BASE_URL . 'static/js/div_history.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">History</h2>
            <p class="text-secondary">View all documents you have created, processed, or received.</p>
        </div>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- BULLETPROOF INLINE FILTER BAR -->
    <div class="filter-bar d-flex flex-nowrap align-items-center gap-2 mb-4 w-100" style="overflow-x: auto; padding-bottom: 5px;">
        <span class="text-muted fw-bold small text-nowrap flex-shrink-0">Filter by</span>

        <div class="input-group search-container flex-shrink-0 m-0" style="width: 250px;">
            <span class="input-group-text search-icon-group"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" id="searchInput" class="form-control custom-search-input" placeholder="Search Control No. or Subject...">
        </div>

        <select id="directionFilter" class="form-select custom-select flex-shrink-0" style="width: 130px;">
            <option value="">All Routes</option>
            <option value="incoming">Incoming</option>
            <option value="outgoing">Outgoing</option>
        </select>

        <select id="typeFilter" class="form-select custom-select flex-shrink-0" style="width: 150px;">
            <option value="">Document Type</option>
            <?php foreach ($doc_types as $type): ?>
                <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select id="classFilter" class="form-select custom-select flex-shrink-0" style="width: 150px;">
            <option value="">Classification</option>
            <?php foreach ($classifications as $class): ?>
                <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="d-flex align-items-center gap-2 border px-2 py-1 rounded bg-white flex-shrink-0">
            <span class="text-muted small text-nowrap">From:</span>
            <input type="date" id="startDate" class="form-control form-control-sm border-0 text-secondary" style="width: 110px;">
            <span class="text-muted small text-nowrap">To:</span>
            <input type="date" id="endDate" class="form-control form-control-sm border-0 text-secondary" style="width: 110px;">
        </div>
    </div>

    <!-- SPLIT-SCREEN LAYOUT -->
    <div class="split-layout-container">
        <div class="table-section">
            <div class="table-container p-0">
                <div class="table-responsive">
                    <table class="data-table" id="historyTable">
                        <thead>
                            <tr>
                                <th>CONTROL NO.</th>
                                <th>STATUS</th>
                                <th>DEADLINE</th>
                                <th>CREATED</th>
                                <th>RECEIVED</th>
                                <th width="20%">SUBJECT</th>
                                <th>ADDRESS</th>
                                <th>SIGNATORY</th>
                                <th>CREATED BY</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_docs as $doc):
                                $doc_attachments = isset($all_attachments[$doc['id']]) ? $all_attachments[$doc['id']] : [];
                                $json_attachments = json_encode($doc_attachments);
                            ?>
                            <tr class="history-row clickable-row" style="cursor: pointer;"
                                data-dts="<?= htmlspecialchars($doc['dts_no']) ?>"
                                data-status="<?= htmlspecialchars($doc['status_name']) ?>"
                                data-statusclass="status <?= strtolower($doc['status_category']) ?>"
                                data-deadline="<?= $doc['due_date'] ? date('M d, Y', strtotime($doc['due_date'])) : 'No Deadline' ?>"
                                data-created="<?= date('M d, Y h:i A', strtotime($doc['created_at'])) ?>"
                                data-received="<?= $doc['received_at'] ? date('M d, Y h:i A', strtotime($doc['received_at'])) : 'N/A' ?>"
                                data-subject="<?= htmlspecialchars($doc['subject']) ?>"
                                data-address="<?= htmlspecialchars($doc['address_name'] ?? 'N/A') ?>"
                                data-signatory="<?= htmlspecialchars(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? 'N/A')) ?>"
                                data-creator="<?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?>"
                                data-creatordiv="<?= htmlspecialchars($doc['c_division'] ?? 'System User') ?>"
                                data-sender="<?= htmlspecialchars($doc['sender'] ?? 'N/A') ?>"
                                data-origin="<?= htmlspecialchars($doc['origin_name'] ?? 'N/A') ?>"
                                data-direction="<?= strtolower($doc['doc_direction']) ?>"
                                data-attachments='<?= htmlspecialchars($json_attachments, ENT_QUOTES, 'UTF-8') ?>'>

                                <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                <td><span class="status <?= strtolower($doc['status_category']) ?>"><?= htmlspecialchars($doc['status_name']) ?></span></td>
                                <td><?= $doc['due_date'] ? date('M d, Y', strtotime($doc['due_date'])) : '<span class="text-muted">None</span>' ?></td>
                                <td>
                                    <div class="text-dark fw-bold"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                                    <div class="text-muted" style="font-size: 0.8rem;"><?= date('h:i A', strtotime($doc['created_at'])) ?></div>
                                    <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                                </td>
                                <td>
                                    <?php if ($doc['received_at']): ?>
                                        <div class="text-dark fw-bold"><?= date('M d, Y', strtotime($doc['received_at'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.8rem;"><?= date('h:i A', strtotime($doc['received_at'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark text-truncate search-target" style="max-width: 200px;" title="<?= htmlspecialchars($doc['subject']) ?>">
                                        <?= htmlspecialchars($doc['subject']) ?>
                                    </div>
                                </td>
                                <td class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($doc['address_name'] ?? 'N/A') ?>"><?= htmlspecialchars($doc['address_name'] ?? '---') ?></td>
                                <td><?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? '---'))) ?></td>
                                <td>
                                    <?php if ($doc['doc_direction'] === 'Outgoing'): ?>
                                        <!-- No creator info needed for your own outgoing -->
                                    <?php else: ?>
                                        <div class="creator-cell m-0 p-0 bg-transparent border-0">
                                            <div class="creator-avatar sm"><i class="fa-solid fa-user"></i></div>
                                            <div class="creator-info">
                                                <span class="creator-name fw-bold" style="font-size: 0.8rem;"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="d-none type-target"><?= htmlspecialchars($doc['doc_type']) ?></td>
                                <td class="d-none class-target"><?= htmlspecialchars($doc['classification']) ?></td>
                                <td class="d-none direction-target"><?= strtolower($doc['doc_direction']) ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($history_docs)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-folder-open mb-3 opacity-25" style="font-size: 3rem;"></i><br>
                                        <h6 class="fw-bold text-secondary">History is Empty</h6>
                                        <p style="font-size: 0.9rem;">Documents you create or process will appear here.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SIDE PANEL -->
        <div class="side-panel-section" id="sidePanel">
            <div class="side-panel-header">
                <div>
                    <span class="text-primary fw-bold" style="font-size: 1.1rem;" id="paneCtrl">CONTROL NO</span>
                    <span id="paneStatus" class="ms-2">STATUS</span>
                </div>
                <button type="button" class="btn-close" id="closePanelBtn" aria-label="Close"></button>
            </div>
            <div class="side-panel-body">
                <div class="row">
                    <div class="col-6 panel-group">
                        <div class="panel-label">DATE & TIME CREATED</div>
                        <div class="panel-value" id="paneCreated"></div>
                    </div>
                    <div class="col-6 panel-group">
                        <div class="panel-label">DATE & TIME RECEIVED</div>
                        <div class="panel-value" id="paneReceived"></div>
                    </div>
                </div>

                <div class="panel-group">
                    <div class="panel-label">DEADLINE</div>
                    <div class="panel-value text-danger fw-bold" id="paneDeadline"></div>
                </div>

                <div id="incomingFieldsBlock">
                    <div class="row">
                        <div class="col-6 panel-group">
                            <div class="panel-label">SENDER</div>
                            <div class="panel-value" id="paneSender"></div>
                        </div>
                        <div class="col-6 panel-group">
                            <div class="panel-label">ORIGIN</div>
                            <div class="panel-value" id="paneOrigin"></div>
                        </div>
                    </div>
                </div>

                <div class="panel-group">
                    <div class="panel-label">SUBJECT</div>
                    <div class="panel-value fw-bold text-dark" id="paneSubject"></div>
                </div>

                <div class="panel-group">
                    <div class="panel-label">ADDRESS</div>
                    <div class="panel-value" id="paneAddress"></div>
                </div>

                <div class="panel-group">
                    <div class="panel-label">SIGNATORY</div>
                    <div class="panel-value" id="paneSignatory"></div>
                </div>

                <div id="incomingCreatorBlock" class="panel-group border-top pt-3 mt-2">
                    <div class="panel-label">CREATED BY</div>
                    <div class="creator-cell m-0 p-0 bg-transparent border-0 mt-1">
                        <div class="creator-avatar sm"><i class="fa-solid fa-user"></i></div>
                        <div class="creator-info">
                            <span class="creator-name fw-bold" style="font-size: 0.85rem;" id="paneCreatorName"></span>
                            <span class="creator-role text-muted" style="font-size: 0.75rem;" id="paneCreatorDiv"></span>
                        </div>
                    </div>
                </div>

                <div class="panel-group border-top pt-3 mt-3">
                    <div class="panel-label mb-2"><i class="fa-solid fa-paperclip me-1"></i> ATTACHMENTS</div>
                    <div id="paneAttachments"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>