<?php
// templates/signatory/signView.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RD', 'ARD'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$doc_id = $_GET['id'] ?? null;
$from_page = $_GET['from'] ?? 'signDashboard.php';

if (!$doc_id) {
    header("Location: " . $from_page);
    exit;
}

$docManager = new DocumentManager($pdo);
$doc = $docManager->getDocumentDetails($doc_id); // Reusing universal detail fetcher

if (!$doc) {
    header("Location: " . $from_page);
    exit;
}

$attachments = $docManager->getDocumentAttachments($doc_id);
$page_title = "Review Document - " . $doc['dts_no'];

$extra_css = '<link rel="stylesheet" href="' . BASE_URL . 'static/css/accept_docu.css">';
require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <!-- Dynamic Back Button based on the source queue -->
    <a href="<?= htmlspecialchars($from_page) ?>" class="btn btn-link text-secondary mb-4 p-0 text-decoration-none">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Queue
    </a>

    <div class="detail-card bg-white p-4 rounded shadow-sm">
        <div class="d-flex justify-content-between border-bottom pb-3 mb-4">
            <div>
                <h3 class="fw-bold text-primary mb-0"><?= htmlspecialchars($doc['dts_no']) ?></h3>
                <span class="status for-approval mt-2 d-inline-block">PENDING SIGNATURE</span>
            </div>
            <div class="text-end">
                <div class="small text-muted">RECEIVED ON</div>
                <div class="fw-bold"><?= date('M d, Y | h:i A', strtotime($doc['created_at'])) ?></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="data-group mb-4">
                    <label class="text-muted small fw-bold mb-2 d-block">SUBJECT</label>
                    <div class="p-3 bg-light rounded fw-bold border"><?= htmlspecialchars($doc['subject']) ?></div>
                </div>

                <div class="data-group mb-4">
                    <label class="text-muted small fw-bold mb-2 d-block">PARTICULARS / REMARKS</label>
                    <div class="p-3 bg-light rounded border" style="min-height: 100px;">
                        <?= nl2br(htmlspecialchars($doc['particulars'] ?? 'No additional details provided.')) ?>
                    </div>
                </div>

                <div class="data-group">
                    <label class="text-muted small fw-bold mb-2 d-block">ATTACHMENTS</label>
                    <div class="mt-2">
                        <?php if(empty($attachments)): ?>
                            <p class="text-muted small fst-italic">No files attached.</p>
                        <?php else: ?>
                            <?php foreach($attachments as $file): ?>
                                <a href="<?= BASE_URL . $file['file_path'] ?>" target="_blank" class="d-flex align-items-center p-3 border rounded mb-2 text-decoration-none bg-white attachment-item">
                                    <i class="fa-solid fa-file-pdf text-danger me-3 fs-4"></i>
                                    <span class="text-dark fw-bold"><?= htmlspecialchars(basename($file['file_path'])) ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4 border-start ps-4">
                <div class="mb-4">
                    <label class="text-muted small fw-bold d-block mb-1">ORIGINATING OFFICE</label>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($doc['origin_name'] ?? 'Internal Division') ?></div>
                </div>

                <div class="mb-4">
                    <label class="text-muted small fw-bold d-block mb-1">DESTINATION</label>
                    <div class="text-success fw-bold"><?= htmlspecialchars($doc['address_name'] ?? 'N/A') ?></div>
                </div>

                <div class="mb-4">
                    <label class="text-muted small fw-bold d-block mb-1">DEADLINE</label>
                    <div class="<?= ($doc['due_date'] && strtotime($doc['due_date']) < time()) ? 'text-danger' : 'text-dark' ?> fw-bold">
                        <i class="fa-regular fa-calendar me-1"></i> <?= $doc['due_date'] ? date('M d, Y', strtotime($doc['due_date'])) : 'No Deadline' ?>
                    </div>
                </div>

                <div class="pt-4 border-top">
                    <!-- Action Form points to the central signatory controller -->
                    <form method="POST" action="../../controllers/signatory.php">
                        <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                        <input type="hidden" name="from_page" value="<?= htmlspecialchars($from_page) ?>">

                        <button type="submit" name="action" value="approve_document" class="btn btn-success w-100 py-3 fw-bold mb-3 shadow-sm">
                            <i class="fa-solid fa-file-signature me-2"></i> Approve & Sign
                        </button>

                        <button type="submit" name="action" value="reject_document" class="btn btn-outline-danger w-100 py-2 fw-bold" onclick="return confirm('Are you sure you want to REJECT this document?')">
                            <i class="fa-solid fa-xmark me-2"></i> Reject Document
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>