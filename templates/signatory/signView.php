<?php
// templates/signatory/signView.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

// Security Check: Only 'RD' or 'ARD' role can access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RD', 'ARD'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$doc_id = $_GET['id'] ?? null;
if (!$doc_id) {
    header("Location: signDashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Handle Session Messages so errors are visible!
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Smart tracking: Safely parse the URL to ignore '?id=...' so it redirects correctly
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$parsed_url = parse_url($referer, PHP_URL_PATH);
$return_page = $parsed_url ? basename($parsed_url) : 'signDashboard.php';

if ($return_page === 'signView.php' || empty($return_page)) {
    $return_page = 'signDashboard.php'; // Fallback
}

// Custom Query
$stmt = $pdo->prepare("
    SELECT d.*,
           s.name as status_name, s.category as status_category,
           addr.name as address_name,
           orig.name as origin_name,
           sig.first_name as sig_fname, sig.last_name as sig_lname,
           u.first_name as c_fname, u.last_name as c_lname,
           p.role as c_role, divi.name as c_division,
           c.name as class_name, t.name as type_name
    FROM records_document d
    LEFT JOIN records_status s ON d.status_id = s.id
    LEFT JOIN records_address addr ON d.address_id = addr.id
    LEFT JOIN records_origin orig ON d.origin_id = orig.id
    LEFT JOIN auth_user sig ON d.signatory_id = sig.id
    LEFT JOIN auth_user u ON d.creator_id = u.id
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division divi ON p.division_id = divi.id
    LEFT JOIN records_classification c ON d.classification_id = c.id
    LEFT JOIN records_documenttype t ON d.document_type_id = t.id
    WHERE d.id = ?
");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    echo "<div class='p-5 text-center'><h3>Document not found.</h3></div>";
    exit;
}

// Fetch Attachments and Recipients
$attachments = $docManager->getDocumentAttachments($doc_id);
$all_recipients = $docManager->getRecipientsByDocument();
$recipients = $all_recipients[$doc_id] ?? [];

// Determine if Incoming
$is_incoming = ($doc['c_role'] === 'RO');

// Format Destination Office & Names
$dest_office = $doc['address_name'] ?? 'Internal Routing';
$dest_names_html = '<span class="text-muted fst-italic">No specific personnel assigned</span>';

if (!$is_incoming && in_array($doc['route_type'], ['outside_dti', 'within_dti'])) {
    if (!empty($doc['sender'])) {
        $dest_names_html = htmlspecialchars($doc['sender']);
    }
} else {
    if (!empty($recipients)) {
        $clean_names = array_map(function($person) {
            return explode(' (', $person)[0];
        }, $recipients);
        $dest_names_html = implode('<br>', array_map('htmlspecialchars', $clean_names));
    }
}

$page_title = "Document Details - " . htmlspecialchars($doc['dts_no']);

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/document.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/accept_docu.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<style>
    .data-group { margin-bottom: 0; }
</style>
';

// Link the new JS file for action buttons
$extra_js = '<script src="' . BASE_URL . 'static/js/signatory_action.js"></script>';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-3">
    <div class="mb-3">
        <a href="<?= htmlspecialchars($return_page) ?>" class="btn-back text-decoration-none text-secondary">
            <i class="fa-solid fa-chevron-left me-2"></i>
            <span class="fw-bold">Back to List</span>
        </a>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="detail-card border rounded shadow-sm bg-white p-4">

        <div class="detail-header d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
            <h5 class="fw-bold mb-0">DTS No. <span class="text-primary"><?= htmlspecialchars($doc['dts_no']) ?></span></h5>
            <span class="status <?= strtolower($doc['status_category']) ?> status-<?= strtolower(str_replace(' ', '-', $doc['status_name'])) ?>">
                <?= htmlspecialchars($doc['status_name']) ?>
            </span>
        </div>

        <div class="attachment-section mb-3">
            <div class="accordion" id="attachmentAccordion">
                <div class="accordion-item border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed custom-accordion-btn bg-light rounded py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAttachments">
                            <i class="fa-solid fa-paperclip me-2"></i> Attachments (<?= count($attachments) ?>)
                        </button>
                    </h2>
                    <div id="collapseAttachments" class="accordion-collapse collapse" data-bs-parent="#attachmentAccordion">
                        <div class="accordion-body p-0 pt-1">
                            <?php if (!empty($attachments)): ?>
                                <ul class="list-group list-group-flush rounded border">
                                    <?php foreach ($attachments as $att):
                                        $ext = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                                        $icon = 'fa-file'; $color = 'text-secondary';
                                        if (in_array($ext, ['pdf'])) { $icon = 'fa-file-pdf'; $color = 'text-danger'; }
                                        elseif (in_array($ext, ['doc', 'docx'])) { $icon = 'fa-file-word'; $color = 'text-primary'; }
                                        elseif (in_array($ext, ['xls', 'xlsx'])) { $icon = 'fa-file-excel'; $color = 'text-success'; }
                                        elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) { $icon = 'fa-image'; $color = 'text-warning'; }
                                    ?>
                                        <li class="list-group-item d-flex align-items-center gap-2 py-1 px-2">
                                            <i class="fa-solid <?= $icon ?> <?= $color ?>" style="font-size: 0.85rem;"></i>
                                            <a href="<?= BASE_URL . htmlspecialchars($att['file_path']) ?>" target="_blank" class="text-decoration-none text-dark small text-truncate">
                                                <?= htmlspecialchars(basename($att['file_path'])) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-muted p-2 border rounded small">No attachments uploaded.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Deadline</label>
                    <p class="data-value text-danger"><?= !empty($doc['due_date']) ? date('m/d/Y', strtotime($doc['due_date'])) : 'None' ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Classification</label>
                    <p class="data-value text-dark"><?= htmlspecialchars($doc['class_name'] ?? 'N/A') ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Document Type</label>
                    <p class="data-value text-dark"><?= htmlspecialchars($doc['type_name'] ?? 'N/A') ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Signatory</label>
                    <p class="data-value text-dark"><?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? ''))) ?></p>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-12">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Subject</label>
                    <div class="data-value textarea-style text-dark bg-light py-2 px-3 border-0"><?= htmlspecialchars($doc['subject']) ?></div>
                </div>
            </div>
        </div>

        <?php if ($is_incoming): ?>
        <h6 class="border-bottom pb-2 mb-2 text-secondary" style="font-size: 0.9rem;">
            <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Origin Information
        </h6>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Origin Office / Agency</label>
                    <div class="data-value textarea-style bg-light text-dark py-2 px-3 border-0"><?= htmlspecialchars($doc['origin_name'] ?? 'External Office') ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Sender / Contact Person</label>
                    <div class="data-value textarea-style bg-light text-dark py-2 px-3 border-0"><?= htmlspecialchars($doc['sender'] ?? 'Unknown Contact') ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <h6 class="border-bottom pb-2 mb-2 text-secondary" style="font-size: 0.9rem;">
            <i class="fa-solid fa-location-dot me-2"></i>Address
        </h6>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Office / Division / Group</label>
                    <div class="data-value textarea-style bg-light text-dark py-2 px-3 border-0">
                        <?= htmlspecialchars($dest_office) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Recipient Name(s)</label>
                    <div class="data-value textarea-style bg-light text-dark py-2 px-3 border-0">
                        <?= $dest_names_html ?>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="border-bottom pb-2 mb-2 text-secondary" style="font-size: 0.9rem;">
            <i class="fa-solid fa-circle-info me-2"></i>Additional Details
        </h6>
        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="data-group">
                    <label class="text-muted text-uppercase small">Particulars / Remarks</label>
                    <div class="data-value textarea-style bg-light text-dark py-2 px-3 border-0">
                        <?= nl2br(htmlspecialchars($doc['particulars'] ?? 'No particulars provided.')) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-action-container pt-3 border-top mt-4 d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div class="created-info">
                <p class="fw-bold mb-0 text-muted" style="font-size: 0.85rem;">Created by: <span class="text-dark"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span></p>
                <p class="mb-0 text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($doc['c_division'] ?? 'System User') ?></p>
                <p class="mb-0 text-muted" style="font-size: 0.85rem;"><?= date('F d, Y g:i A', strtotime($doc['created_at'])) ?></p>
            </div>

            <?php if (in_array(strtoupper($doc['status_name']), ['FOR APPROVAL', 'FOR-APPROVAL']) && $doc['signatory_id'] == $user_id): ?>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-red" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fa-solid fa-xmark-circle me-2"></i> Reject Document
                </button>
                <button type="button" class="btn btn-blue" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="fa-solid fa-check-circle me-2"></i> Approve Document
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #0d6efd;">
            <form action="../../controllers/signatory.php" method="POST" id="approveForm">
                <div class="modal-body text-center p-4">
                    <i class="fa-solid fa-check-circle text-primary mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark">Approve Document?</h5>
                    <p class="text-muted" style="font-size: 0.9rem;">Are you sure you want to approve document <b><?= htmlspecialchars($doc['dts_no']) ?></b>?</p>

                    <div class="p-2 bg-light rounded border mt-3 mb-3 text-start">
                        <span class="d-block small text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem;">Routing Action:</span>
                        <?php if (in_array($doc['route_type'], ['division', 'group'])): ?>
                            <span class="text-dark" style="font-size: 0.8rem;">This document will be directly routed to the division/personnel assigned.</span>
                        <?php else: ?>
                            <span class="text-dark" style="font-size: 0.8rem;">This document will be routed back to the Receiving Officer for external dispatch.</span>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="action" value="approve_document">
                    <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                    <input type="hidden" name="from_page" value="<?= htmlspecialchars($return_page) ?>">

                    <div class="d-flex flex-column gap-2 mt-4">
                        <button type="submit" class="btn btn-blue fw-bold w-100" id="btnApproveSubmit">Yes, Approve</button>
                        <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #dc3545;">
            <form action="../../controllers/signatory.php" method="POST" id="rejectForm">
                <div class="modal-body text-center p-4">
                    <i class="fa-solid fa-xmark-circle text-danger mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark">Reject Document?</h5>
                    <p class="text-muted mb-3" style="font-size: 0.9rem;">You are about to reject document <b><?= htmlspecialchars($doc['dts_no']) ?></b>. It will be returned to the creator.</p>

                    <div class="data-group m-0 text-start">
                        <label class="text-muted small text-uppercase" style="font-size: 0.7rem;">Reason for Rejection (Optional)</label>
                        <textarea name="reject_reason" class="form-control shadow-none mt-1" rows="3" placeholder="Provide instructions or reasons..." style="font-size: 0.85rem;"></textarea>
                    </div>

                    <input type="hidden" name="action" value="reject_document">
                    <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                    <input type="hidden" name="from_page" value="<?= htmlspecialchars($return_page) ?>">

                    <div class="d-flex flex-column gap-2 mt-4">
                        <button type="submit" class="btn btn-danger fw-bold w-100" id="btnRejectSubmit">Yes, Reject</button>
                        <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        if(document.referrer.includes('signIncoming')) {
            sidebarLinks.forEach(l => { if(l.getAttribute('href').includes('signIncoming')) l.classList.add('active'); else l.classList.remove('active'); });
        } else if (document.referrer.includes('signOutgoing')) {
            sidebarLinks.forEach(l => { if(l.getAttribute('href').includes('signOutgoing')) l.classList.add('active'); else l.classList.remove('active'); });
        }
    });
</script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>