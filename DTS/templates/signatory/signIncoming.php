<?php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

// Security Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RD', 'ARD'])) {
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
$all_attachments = $docManager->getAllAttachmentsGrouped();
$all_recipients = $docManager->getRecipientsByDocument();

// Use the existing modular CSS
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/panel.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">Incoming Documents</h2>
            <p class="text-secondary">Review external documents forwarded by the Records Officer.</p>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- FULL FILTER BAR -->
    <div class="filter-bar mb-4 d-flex align-items-center gap-3 flex-wrap">
        <div class="input-group" style="width: 250px;">
            <span class="input-group-text search-icon-group bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Search DTS No. or Subject...">
        </div>

        <span class="text-muted small fw-bold">Filter by:</span>

        <select id="classFilter" class="form-select" style="width: 180px;">
            <option value="">All Classifications</option>
            <?php foreach ($classifications as $class): ?>
                <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="d-flex align-items-center gap-2 border px-2 py-1 rounded bg-white shadow-sm">
            <span class="text-muted small">From:</span>
            <input type="date" id="startDate" class="form-control form-control-sm border-0 small">
            <span class="text-muted small">To:</span>
            <input type="date" id="endDate" class="form-control form-control-sm border-0 small">
        </div>
    </div>

    <div class="split-layout-container">
        <div class="table-section">
            <div class="table-container p-0">
                <div class="table-responsive">
                    <table class="data-table" id="incomingTable">
                        <thead>
                            <tr>
                                <th>DTS NO.</th>
                                <th>STATUS</th>
                                <th>ORIGIN</th>
                                <th width="20%">SUBJECT</th>
                                <th>RECEIVED BY</th>
                                <th>DATE RECEIVED</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incoming_docs as $doc):
                                $json_attachments = json_encode($all_attachments[$doc['id']] ?? []);
                                $destined_receiver = isset($all_recipients[$doc['id']]) ? implode(", ", $all_recipients[$doc['id']]) : 'Not Assigned Yet';
                            ?>
                            <tr class="doc-row clickable-row" style="cursor: pointer;"
                            onclick="window.location.href='signView.php?id=<?= $doc['id'] ?>'"
                                data-id="<?= (int)$doc['id'] ?>"
                                data-dts="<?= htmlspecialchars($doc['dts_no']) ?>"
                                data-status="<?= htmlspecialchars($doc['status_name']) ?>"
                                data-deadline="<?= $doc['due_date'] ? date('m/d/Y', strtotime($doc['due_date'])) : 'None' ?>"
                                data-created="<?= date('F d, Y h:i A', strtotime($doc['created_at'])) ?>"
                                data-subject="<?= htmlspecialchars($doc['subject']) ?>"
                                data-particulars="<?= htmlspecialchars($doc['particulars'] ?? '') ?>"
                                data-type="<?= htmlspecialchars($doc['doc_type'] ?? 'N/A') ?>"
                                data-class="<?= htmlspecialchars($doc['classification'] ?? 'N/A') ?>"
                                data-sender="<?= htmlspecialchars($doc['routing_notes'] ?? 'System User') ?>"
                                data-origin="<?= htmlspecialchars($doc['origin_name'] ?? 'N/A') ?>"
                                data-address="<?= htmlspecialchars($doc['address_name'] ?? 'N/A') ?>"
                                data-destined="<?= htmlspecialchars($destined_receiver) ?>"
                                data-creator="<?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?>"
                                data-attachments='<?= htmlspecialchars($json_attachments, ENT_QUOTES, 'UTF-8') ?>'>

                                <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                <td><span class="status for-approval"><?= htmlspecialchars($doc['status_name']) ?></span></td>
                                <td><?= htmlspecialchars($doc['origin_name']) ?></td>
                                <td class="text-truncate fw-bold text-dark search-target"><?= htmlspecialchars($doc['subject']) ?></td>
                                <td><span class="small fw-bold"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span></td>
                                <td>
                                    <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                                    <!-- Hidden spans for JS filtering -->
                                    <span class="d-none class-target"><?= htmlspecialchars($doc['classification'] ?? '') ?></span>
                                    <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($incoming_docs)): ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No pending incoming documents.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Side Panel remains the same -->
        <div class="side-panel-section" id="sidePanel">
            <div class="side-panel-header">
                <div>
                    <span class="text-primary fw-bold" id="paneDTS">DTS NO</span>
                    <span class="status for-approval ms-2" id="paneStatus">FOR APPROVAL</span>
                </div>
                <button type="button" class="btn-close" id="closePanelBtn"></button>
            </div>
            <div class="side-panel-body">
                <div class="mb-4 border rounded p-3 bg-light">
                    <div class="data-group mb-2"><label><i class="fa-solid fa-paperclip me-1"></i> ATTACHED FILES</label></div>
                    <div id="paneAttachments"></div>
                </div>

                <div class="row g-2 mb-3 border-bottom pb-3">
                    <div class="col-6">
                        <label class="small text-muted fw-bold">DATE RECEIVED</label>
                        <div id="paneCreated" class="small"></div>
                    </div>
                    <div class="col-6">
                        <label class="small text-muted fw-bold text-danger">DEADLINE</label>
                        <div id="paneDeadline" class="small text-danger fw-bold"></div>
                    </div>
                </div>

                <div class="data-group mb-3">
                    <label class="small text-muted fw-bold">SUBJECT</label>
                    <div id="paneSubject" class="fw-bold text-dark p-2 bg-white border rounded"></div>
                </div>

                <div class="data-group mb-4">
                    <label class="small text-muted fw-bold">DESTINED RECEIVER(S)</label>
                    <div id="paneDestined" class="fw-bold text-success p-2 bg-white border rounded" style="border-color: #bbf7d0 !important;"></div>
                </div>

                <div class="d-flex gap-2 border-top pt-4">
                    <form method="POST" action="../../controllers/signatory.php" class="m-0 w-50">
                        <input type="hidden" name="action" value="reject_document">
                        <input type="hidden" name="document_id" class="targetDocId">
                        <button type="submit" class="btn btn-red w-100" onclick="return confirm('Reject this document?')">Reject</button>
                    </form>
                    <form method="POST" action="../../controllers/signatory.php" class="m-0 w-50">
                        <input type="hidden" name="action" value="approve_document">
                        <input type="hidden" name="document_id" class="targetDocId">
                        <button type="submit" class="btn btn-blue w-100" onclick="return confirm('Approve this document?')">Approve</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>