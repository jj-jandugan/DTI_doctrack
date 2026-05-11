<?php
// templates/ro/roViewOutgoing.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

if (!isset($_GET['id'])) {
    header("Location: roOutgoing.php");
    exit;
}

$doc_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Fetch Data via Class
$doc = $docManager->getDocumentDetails($doc_id);
if (!$doc) {
    header("Location: roOutgoing.php");
    exit;
}

$attachments = $docManager->getDocumentAttachments($doc_id);

$page_title = "View Document - " . $doc['dts_no'];
$extra_css = '<link rel="stylesheet" href="' . BASE_URL . 'static/css/accept_docu.css">';

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-4">
    <a href="roOutgoing.php" class="btn-back mb-4"><i class="fa-solid fa-arrow-left"></i> Back to Queue</a>
    <div class="detail-card">
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
            <div>
                <h3 class="fw-bold text-primary mb-1"><?= htmlspecialchars($doc['dts_no']) ?></h3>
                <span class="status received"><?= htmlspecialchars($doc['status_name']) ?></span>
            </div>
            <div class="text-end">
                <div class="small text-muted">APPROVED ON</div>
                <div class="fw-bold"><?= date('M d, Y | h:i A', strtotime($doc['updated_at'])) ?></div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-8">
                <div class="data-group mb-4">
                    <label>Subject</label>
                    <div class="textarea-style fw-bold"><?= htmlspecialchars($doc['subject']) ?></div>
                </div>
                <div class="data-group mb-4">
                    <label>Particulars</label>
                    <div class="textarea-style"><?= htmlspecialchars($doc['particulars'] ?? 'No additional notes.') ?>
                    </div>
                </div>
                <div class="data-group">
                    <label>Attachments</label>
                    <div class="mt-2">
                        <?php if (empty($attachments)): ?>
                            <span class="text-muted small fst-italic">No attachments.</span>
                        <?php else: ?>
                            <?php foreach ($attachments as $file): ?>
                                <a href="<?= BASE_URL . $file['file_path'] ?>" target="_blank"
                                    class="attachment-link d-block mb-2">
                                    <i class="fa-solid fa-file-pdf text-danger me-2"></i> <?= basename($file['file_path']) ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4 border-start ps-4">
                <div class="data-group mb-3">
                    <label>Destination</label>
                    <div class="data-value fw-bold text-success">
                        
                        <?= htmlspecialchars($doc['address_name'] ?? 'External') ?></div>
                </div>
                <div class="data-group mb-3">
                    <label>Signatory</label>
                    <div class="data-value"><?= htmlspecialchars($doc['sig_fname'] . ' ' . $doc['sig_lname']) ?></div>
                </div>
                <div class="data-group mb-4">
                    <label>Deadline</label>

                        
                                        <div class="data-value text-danger fw-bold">
                        <?= $doc['due_date'] ? date('M d, Y', strtotime($doc['due_date'])) : 'None' ?></div>
                </div>
                <div class="border-top pt-4">
                    <!-- Pointing to the central dispatch controller -->
                    <form method="POST" action="../../controllers/dispatch.php">
                        <input type="hidden" name="action" value="dispatch_document">
                        <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                        <button type="submit" class="btn btn-accept w-100"
                            onclick="return confirm('Mark as Dispatched?')">
                            <i class="fa-solid fa-paper-plane me-2"></i> Mark as Dispatched </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once BASE_PATH . 'includes/footer.php'; ?>