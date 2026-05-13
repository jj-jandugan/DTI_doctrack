<?php
// templates/division/divAcceptDocu.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

// Security Check: Only 'Division' role can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$doc_id = $_GET['id'] ?? null;
if (!$doc_id) {
    header("Location: divOnMyDesk.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);
$error_msg = '';

// ==========================================
// HANDLE "ACCEPT & CLOSE" POST ACTION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_document') {
    try {
        $pdo->beginTransaction();

        // Look for any 'CLOSED' category status, but STRICTLY EXCLUDE 'Rejected' and 'Cancelled'
        $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE name = 'CLOSED' OR category = 'CLOSED' LIMIT 1");
        $closed_status_id = $stmtStatus->fetchColumn();

        if (!$closed_status_id) {
            throw new Exception("Database Error: Status 'CLOSED' does not exist in the records_status table.");
        }

        // Update the recipient table
        $stmtUpdateRec = $pdo->prepare("UPDATE records_documentrecipient SET has_received = 1, received_at = NOW() WHERE document_id = ? AND recipient_user_id = ?");
        $stmtUpdateRec->execute([$doc_id, $user_id]);

        // Update the main document status
        $stmtUpdateDoc = $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdateDoc->execute([$closed_status_id, $doc_id]);

        // Log the action
        $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('RECEIVED & CLOSED', 'Document was accepted and marked as closed by the assigned division user.', NOW(), ?, ?)");
        $stmtLog->execute([$user_id, $doc_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Document successfully accepted and closed! It has been moved to your History.";
        header("Location: divOnMyDesk.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error processing document: " . $e->getMessage();
    }
}

// ==========================================
// FETCH COMPREHENSIVE DOCUMENT DETAILS
// ==========================================
$stmt = $pdo->prepare("
    SELECT d.*,
           c.name as class_name, t.name as type_name,
           s.name as status_name, s.category as status_category,
           orig.name as origin_name, addr.name as address_name,
           u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
           sig.first_name as sig_fname, sig.last_name as sig_lname
    FROM records_document d
    LEFT JOIN records_classification c ON d.classification_id = c.id
    LEFT JOIN records_documenttype t ON d.document_type_id = t.id
    LEFT JOIN records_status s ON d.status_id = s.id
    LEFT JOIN records_origin orig ON d.origin_id = orig.id
    LEFT JOIN records_address addr ON d.address_id = addr.id
    LEFT JOIN auth_user u ON d.creator_id = u.id
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division divi ON p.division_id = divi.id
    LEFT JOIN auth_user sig ON d.signatory_id = sig.id
    WHERE d.id = ?
");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    header("Location: divOnMyDesk.php");
    exit;
}

$page_title = "Accept Document";
$attachments = $docManager->getDocumentAttachments($doc_id);
$is_overdue = ($doc['due_date'] && strtotime($doc['due_date']) < strtotime('today'));

// Assets
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/document.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/accept_docu.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="mb-4">
        <a href="divOnMyDesk.php" class="btn-back text-decoration-none">
            <i class="fa-solid fa-chevron-left me-2"></i>
            <span>Back to List</span>
        </a>
    </div>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="detail-card">
        <div class="detail-header d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">Control No. <span class="text-primary"><?= htmlspecialchars($doc['dts_no']) ?></span></h5>

            <?php if ($is_overdue): ?>
                <span class="status overdue">OVER DUE</span>
            <?php else: ?>
                <span class="status <?= strtolower($doc['status_category']) ?>"><?= htmlspecialchars($doc['status_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="attachment-section mb-4">
            <div class="accordion" id="attachmentAccordion">
                <div class="accordion-item border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold custom-accordion-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAttachments">
                            Attachments (<?= count($attachments) ?>)
                        </button>
                    </h2>
                    <div id="collapseAttachments" class="accordion-collapse collapse" data-bs-parent="#attachmentAccordion">
                        <div class="accordion-body p-0 pt-2">
                            <?php if (!empty($attachments)): ?>
                                <ul class="list-group list-group-flush rounded-3 border">
                                    <?php foreach ($attachments as $att):
                                        $ext = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                                        $icon = 'fa-file';
                                        $color = 'text-secondary';

                                        if ($ext === 'pdf') { $icon = 'fa-file-pdf'; $color = 'text-danger'; }
                                        elseif (in_array($ext, ['doc', 'docx'])) { $icon = 'fa-file-word'; $color = 'text-primary'; }
                                        elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) { $icon = 'fa-image'; $color = 'text-success'; }
                                    ?>
                                        <li class="list-group-item d-flex align-items-center gap-2">
                                            <i class="fa-solid <?= $icon ?> <?= $color ?>"></i>
                                            <a href="<?= BASE_URL . $att['file_path'] ?>" target="_blank" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars(basename($att['file_path'])) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-muted small fst-italic p-3 border rounded-3 bg-light">No files attached to this document.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="data-group">
                    <label>Document Date</label>
                    <p class="data-value"><?= date('m/d/Y', strtotime($doc['created_at'])) ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-group">
                    <label>Document Deadline</label>
                    <p class="data-value <?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                        <?= $doc['due_date'] ? date('m/d/Y', strtotime($doc['due_date'])) : 'None' ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="data-group">
                    <label>Classification</label>
                    <p class="data-value"><?= htmlspecialchars($doc['class_name'] ?? 'N/A') ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="data-group">
                    <label>Document Type</label>
                    <p class="data-value"><?= htmlspecialchars($doc['type_name'] ?? 'N/A') ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="data-group">
                    <label>Signatory</label>
                    <p class="data-value">
                        <?= !empty($doc['sig_fname']) ? htmlspecialchars($doc['sig_fname'] . ' ' . $doc['sig_lname']) : 'None' ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="data-group">
                    <label>Subject</label>
                    <div class="data-value textarea-style"><?= htmlspecialchars($doc['subject']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="data-group">
                    <label>Address / Destination</label>
                    <div class="data-value textarea-style"><?= htmlspecialchars($doc['address_name'] ?? 'Internal Routing') ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-group">
                    <label>Particulars</label>
                    <div class="data-value textarea-style"><?= htmlspecialchars($doc['particulars'] ?? 'No additional details provided.') ?></div>
                </div>
            </div>
        </div>

        <div class="footer-action-container pt-4 border-top mt-5 d-flex justify-content-between align-items-end">
            <div class="created-info">
                <p class="mb-0 text-muted" style="font-size: 0.85rem;">Created by: <span class="fw-bold"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span></p>
                <p class="mb-0 text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($doc['c_division'] ?? 'System User') ?></p>
                <p class="mb-0 text-muted" style="font-size: 0.85rem;"><?= date('F d, Y g:i A', strtotime($doc['created_at'])) ?></p>
            </div>

            <button type="button" class="btn btn-blue" data-bs-toggle="modal" data-bs-target="#acceptConfirmModal">
                <i class="fa-solid fa-check-circle me-2"></i>
                Accept Document
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="acceptConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #1d4ed8;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-circle-check text-primary mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Accept Document?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">Are you sure you want to Accept this document? It will be marked as CLOSED and moved to your history.</p>
                <div class="d-flex flex-column gap-2 mt-4">
                    <form action="divAcceptDocu.php?id=<?= $doc['id'] ?>" method="POST" class="m-0 w-100">
                        <input type="hidden" name="action" value="accept_document">
                        <button type="submit" class="btn btn-blue fw-bold w-100" style="background-color: #1d4ed8; color: #fff;">
                            Yes, Accept
                        </button>
                    </form>
                    <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>