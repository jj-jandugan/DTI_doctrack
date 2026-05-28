<?php
// templates/division/divViewHist.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$doc_id = $_GET['id'] ?? null;

if (!$doc_id) {
    header("Location: divHistory.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

$stmt = $pdo->prepare("
    SELECT d.*,
           c.name as class_name,
           t.name as type_name,
           s.name as status_name,
           s.category as status_category,
           orig.name as origin_name,
           addr.name as address_name,
           u.first_name as c_fname,
           u.last_name as c_lname,
           p.role as c_role,
           divi.name as c_division,
           sig.first_name as sig_fname,
           sig.last_name as sig_lname
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
    header("Location: divHistory.php");
    exit;
}

// RECIPIENT NAMES CALCULATION
$stmtRecipients = $pdo->prepare("
    SELECT u.first_name, u.last_name, divi.name as div_name
    FROM records_documentrecipient dr
    JOIN auth_user u ON dr.recipient_user_id = u.id
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division divi ON p.division_id = divi.id
    WHERE dr.document_id = ?
");

$stmtRecipients->execute([$doc_id]);
$recipients = $stmtRecipients->fetchAll();

$receiver_names = 'No specific personnel assigned';

if (!empty($recipients)) {
    $names = array_map(function ($r) {
        return trim($r['first_name'] . ' ' . $r['last_name']);
    }, $recipients);

    $receiver_names = implode(', ', $names);
}

$attachments = $docManager->getDocumentAttachments($doc_id);
$history = $docManager->getDocumentTrackingHistory($doc_id);

// IDENTIFY RECEIVING OFFICER (Only for documents created by Division)
$receiving_officer = 'N/A';
if ($doc['c_role'] === 'Division') {
    foreach ($history as $log) {
        if (isset($log['role']) && $log['role'] === 'RO') {
            $receiving_officer = $log['first_name'] . ' ' . $log['last_name'];
            break; // Grab the first Records Officer involved in the history
        }
    }
}

/*
|--------------------------------------------------------------------------
| VISUAL TRACKER LOGIC (Robust string checks mapped to your styling)
|--------------------------------------------------------------------------
*/

$combined_status = strtoupper(trim($doc['status_category'] ?? '')) . '|' . strtoupper(trim($doc['status_name'] ?? ''));

$is_rejected = (
    strpos($combined_status, 'REJECT') !== false ||
    strpos($combined_status, 'CANCEL') !== false
);

$reject_reason = $is_rejected ? $docManager->getRejectReason($doc_id) : null;

$level = 1;

if ($is_rejected) {
    $level = 5;
} elseif (strpos($combined_status, 'CLOSE') !== false || strpos($combined_status, 'DISPATCHED') !== false || strpos($combined_status, 'RECEIVED') !== false) {
    $level = 5;
} elseif (strpos($combined_status, 'FOR DISPATCH') !== false || strpos($combined_status, 'FOR-DISPATCH') !== false || strpos($combined_status, 'ONGOING') !== false || strpos($combined_status, 'ROUTED') !== false || strpos($combined_status, 'SENT') !== false) {
    $level = 4;
} elseif (strpos($combined_status, 'APPROV') !== false && strpos($combined_status, 'FOR') === false) {
    $level = 3;
} elseif (strpos($combined_status, 'APPROVAL') !== false) {
    $level = 2;
}

// Maps directly to your .step.active and .step.danger CSS
$step1 = 'active';
$step2 = ($level >= 2) ? 'active' : '';
$step3 = ($level >= 3) ? 'active' : '';
$step4 = ($level >= 4) ? 'active' : '';
$step5 = ($level >= 5) ? 'active' : '';

if ($is_rejected) {
    $step5 = 'danger';
}

$progress_map = [
    1 => 0,
    2 => 25,
    3 => 50,
    4 => 75,
    5 => 100
];

$progress_width = $progress_map[$level] ?? 0;

/*
|--------------------------------------------------------------------------
| DYNAMIC TRACKER LABELS
|--------------------------------------------------------------------------
*/

$is_to_ro = in_array(
    $doc['route_type'] ?? '',
    ['outside_dti', 'within_dti']
);

$label_step4 = $is_to_ro
    ? 'Waiting to Dispatch'
    : 'Waiting to be Received';

$label_step5 = $is_to_ro
    ? 'Dispatched'
    : 'Received';

$icon_step4 = $is_to_ro
    ? '<i class="fa-solid fa-boxes-packing"></i>'
    : '<i class="fa-solid fa-inbox"></i>';

$icon_step5_success = $is_to_ro
    ? '<i class="fa-solid fa-paper-plane"></i>'
    : '<i class="fa-solid fa-check-double"></i>';

$page_title = "Document View - " . $doc['dts_no'];

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/document.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/track.css">


<style>
    .detail-label{
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: .5px;
        margin-bottom: 4px;
    }

    .detail-value{
        font-size: 0.97rem;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.6;
    }

    .detail-card{
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px;
        height: 100%;
    }
    .section-title{
        font-size: 0.85rem;
        font-weight: 800;
        letter-spacing: .7px;
        color: #64748b;
        text-transform: uppercase;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .progress-line{
        position: absolute;
        top: 29px;
        left: 9%;
        right: 9%;
        height: 5px;
        background: #e2e8f0;
        border-radius: 20px;
        z-index: 1;
    }

    .progress-line-fill{
        position: absolute;
        top: 29px;
        left: 9%;
        height: 5px;
        background: #0d6efd;
        border-radius: 20px;
        z-index: 2;
        transition: width .4s ease;
    }

    .attachment-card{
        transition: 0.2s ease;
    }

    .attachment-card:hover{
        background: #f1f5f9 !important;
        transform: translateY(-1px);
    }

    .tracking-card{
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 14px;
    }

    .visual-stepper::before { display: none !important; }
</style>
';

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="divHistory.php" class="text-decoration-none text-muted mb-2 d-inline-block fw-semibold">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to History </a>
            <h3 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-file-lines me-2 text-primary"></i>
                <?= htmlspecialchars($doc['dts_no']) ?>
            </h3>
        </div>
        <button class="btn btn-outline-primary fw-bold shadow-sm" data-bs-toggle="modal"
            data-bs-target="#reuseConfirmModal">
            <i class="fa-solid fa-copy me-2"></i> Reuse Record </button>
    </div>
    <!-- ---------------------------------------------------- -->
    <!-- DYNAMIC DOCUMENT STATUS VISUAL TRACKER DESIGN        -->
    <!-- ---------------------------------------------------- -->
    <div class="bg-light border rounded p-4 mb-4 shadow-sm">
        <h6 class="fw-bold text-dark mb-4 text-center text-uppercase" style="letter-spacing: 1px;"><i
                class="fa-solid fa-route text-secondary me-2"></i> DOCUMENT STATUS</h6>
        <div class="position-relative mt-4 mb-2">
            <div
                style="position: absolute; top: 15px; left: 10%; right: 10%; height: 4px; background: #e2e8f0; z-index: 1;">
            </div>
            <div
                style="position: absolute; top: 15px; left: 10%; width: calc(80% * <?= $progress_width ?> / 100); height: 4px; background: <?= $is_rejected ? '#dc3545' : '#10b981' ?>; z-index: 2; transition: width 0.5s ease;">
            </div>
            <div class="visual-stepper d-flex justify-content-between position-relative"
                style="z-index: 3; padding: 0;">
                <div class="step <?= $step1 ?>">
                    <div class="circle"><i class="fa-solid fa-file-import"></i></div>
                    <div class="label">Encoded</div>
                </div>
                <div class="step <?= $step2 ?>">
                    <div class="circle"><i class="fa-solid fa-file-signature"></i></div>
                    <div class="label">For Approval</div>
                </div>
                <div class="step <?= $step3 ?>">
                    <div class="circle"><i class="fa-solid fa-stamp"></i></div>
                    <div class="label">Approved</div>
                </div>
                <div class="step <?= $step4 ?>">
                    <div class="circle"><?= $icon_step4 ?></div>
                    <div class="label"><?= $label_step4 ?></div>
                </div>
                <div class="step <?= $step5 ?>">
                    <div class="circle">
                        <?php if ($is_rejected): ?>
                            <i class="fa-solid fa-xmark"></i>
                        <?php else: ?>
                            <?= $icon_step5_success ?>
                        <?php endif; ?>
                    </div>
                    <div class="label"><?= ($is_rejected) ? 'Rejected' : $label_step5 ?></div>
                </div>
            </div>
        </div>
    </div>
    <!-- ---------------------------------------------------- -->
    <!-- MAIN INFORMATION LAYOUT COLUMNS                     -->
    <!-- ---------------------------------------------------- -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="bg-white rounded-4 shadow-sm p-4 border h-100">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">
                            <i class="fa-solid fa-circle-info me-2 text-secondary"></i> Document Details
                        </h4>
                        <p class="text-muted mb-0 small"> Complete information and metadata of the selected document.
                        </p>
                    </div>
                    <span class="badge bg-<?= $is_rejected ? 'danger' : 'secondary' ?> px-3 py-2 rounded-pill">
                        <?= htmlspecialchars($doc['status_name']) ?>
                    </span>
                </div>
                <!-- SPECIFICATIONS -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">Classification</div>
                            <div class="detail-value text-primary">
                                <?= htmlspecialchars($doc['class_name'] ?? 'N/A') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">Document Type</div>
                            <div class="detail-value">
                                <?= htmlspecialchars($doc['type_name'] ?? 'N/A') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">Due Date</div>
                            <div class="detail-value <?= empty($doc['due_date']) ? 'text-muted' : 'text-danger' ?>">
                                <?= empty($doc['due_date']) ? 'None' : date('F d, Y', strtotime($doc['due_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">Particulars</div>
                            <div class="detail-value fw-normal">
                                <?= htmlspecialchars($doc['particulars'] ?? 'None provided.') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="detail-card">
                            <div class="detail-label">Subject</div>
                            <div class="detail-value fs-5">
                                <?= htmlspecialchars($doc['subject']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <h6 class="section-title">Routing Information</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">Signatory</div>
                            <div class="detail-value">
                                <?= htmlspecialchars(
                                    trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? ''))
                                ) ?: 'None' ?>
                            </div>
                        </div>
                    </div>
                    <!-- RECEIVING OFFICER FIELD: Only for Division created documents -->
                    <?php if ($doc['c_role'] === 'Division'): ?>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">Receiving Officer</div>
                                <div class="detail-value">
                                    <?= htmlspecialchars($receiving_officer) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- ADDRESS SECTION -->
                <h6 class="section-title">Address Information</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">Division / Group / Office Name</div>
                            <div class="detail-value">
                                <?= htmlspecialchars($doc['address_name'] ?? 'Internal Routing') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-card">
                            <div class="detail-label">Recipient Name(s)</div>
                            <div class="detail-value">
                                <?= htmlspecialchars($receiver_names) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ORIGIN SECTION: Hidden for Outgoing Division Documents -->
                <?php if (!empty($doc['origin_name'])): ?>
                    <h6 class="section-title">Origin Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">Office / Agency</div>
                                <div class="detail-value">
                                    <?= htmlspecialchars($doc['origin_name']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-card">
                                <div class="detail-label">Sender / Contact Person</div>
                                <div class="detail-value">
                                    <?= htmlspecialchars($doc['sender'] ?? 'N/A') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <h6 class="section-title">Attachments</h6>
                <?php if (empty($attachments)): ?>
                    <div class="alert alert-light border text-muted mb-0">
                        <i class="fa-solid fa-paperclip me-2"></i> No files attached.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($attachments as $att):
                            // DYNAMIC ICON LOGIC
                            $ext = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                            $icon = 'fa-file';
                            $color = 'text-secondary';

                            if ($ext === 'pdf') {
                                $icon = 'fa-file-pdf';
                                $color = 'text-danger';
                            } elseif (in_array($ext, ['doc', 'docx'])) {
                                $icon = 'fa-file-word';
                                $color = 'text-primary';
                            } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                $icon = 'fa-file-excel';
                                $color = 'text-success';
                            } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                $icon = 'fa-image';
                                $color = 'text-warning';
                            }
                            ?>
                            <a href="<?= BASE_URL . $att['file_path'] ?>" target="_blank"
                                class="attachment-card text-decoration-none bg-light border rounded-4 p-3 d-flex align-items-center text-dark">
                                <div class="me-3">
                                    <i class="fa-solid <?= $icon ?> <?= $color ?> fs-3"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark">
                                        <?= basename($att['file_path']) ?>
                                    </div>
                                    <small class="text-muted"> Click to view attachment </small>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square text-muted"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <?php if ($is_rejected && $reject_reason): ?>
                <div class="bg-white rounded-4 shadow-sm p-4 border border-danger mb-4">
                    <h6 class="fw-bold text-danger mb-3">
                        <i class="fa-solid fa-circle-exclamation me-2"></i> Rejection Remarks
                    </h6>
                    <p class="mb-0 text-dark" style="white-space: pre-wrap; line-height: 1.6;">
                        <?= htmlspecialchars($reject_reason) ?>
                    </p>
                </div>
            <?php endif; ?>
            <div class="bg-white rounded-4 shadow-sm p-4 border h-100">
                <div class="border-bottom pb-3 mb-4">
                    <h4 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-clock-rotate-left me-2 text-secondary"></i> Tracking History
                    </h4>
                    <p class="text-muted mb-0 small"> All actions and movements related to this document. </p>
                </div>
                <!-- ---------------------------------------------------- -->
                <!-- TRACKING TIMELINE LOGS WITH "SEE MORE" COLLAPSIBLE LOGIC -->
                <!-- ---------------------------------------------------- -->
                <div class="tracking-history px-1">
                    <?php if (empty($history)): ?>
                        <div class="alert alert-light border text-muted"> No history recorded yet. </div>
                    <?php else: ?>
                        <?php foreach ($history as $idx => $entry):
                            // Determine whether to collapse entries past the first 3 indices using bootstrap d-none
                            $is_collapsible = ($idx >= 3);
                            $entry_class = $is_collapsible ? 'track-item-collapsible d-none' : '';
                            ?>
                            <div class="track-item d-flex mb-4 position-relative <?= $entry_class ?>">
                                <?php if ($idx !== count($history) - 1): ?>
                                    <div class="track-line position-absolute"
                                        style="left: 18px; top: 42px; bottom: -30px; width: 2px; background-color: #e2e8f0;">
                                    </div>
                                <?php endif; ?>
                                <div class="track-icon rounded-circle bg-light border d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width: 38px; height: 38px; z-index: 2;">
                                    <?php
                                    $action = strtoupper($entry['action_taken']);

                                    if ($action === 'ENCODED' || $action === 'CREATED')
                                        echo '<i class="fa-solid fa-asterisk text-primary"></i>';
                                    elseif ($action === 'UPDATED' || $action === 'EDITED')
                                        echo '<i class="fa-solid fa-pen text-secondary"></i>';
                                    elseif ($action === 'RECEIVED')
                                        echo '<i class="fa-solid fa-check text-success"></i>';
                                    elseif ($action === 'APPROVED')
                                        echo '<i class="fa-solid fa-stamp text-success"></i>';
                                    elseif ($action === 'REJECTED')
                                        echo '<i class="fa-solid fa-xmark text-danger"></i>';
                                    elseif ($action === 'DISPATCHED' || $action === 'ROUTED')
                                        echo '<i class="fa-solid fa-paper-plane text-info"></i>';
                                    elseif ($action === 'CANCELLED')
                                        echo '<i class="fa-solid fa-ban text-warning"></i>';
                                    elseif ($action === 'CLOSED')
                                        echo '<i class="fa-solid fa-lock text-secondary"></i>';
                                    else
                                        echo '<i class="fa-solid fa-clock-rotate-left text-muted"></i>';
                                    ?>
                                </div>
                                <div class="ms-3 w-100">
                                    <div class="tracking-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <div class="fw-bold text-dark">
                                                    <?= htmlspecialchars($entry['action_taken']) ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('F d, Y - h:i A', strtotime($entry['timestamp'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <p class="text-secondary mb-3" style="font-size: 0.9rem; line-height: 1.6;">
                                            <?= htmlspecialchars($entry['remarks']) ?>
                                        </p>
                                        <div class="d-flex align-items-center border-top pt-3">
                                            <i class="fa-solid fa-user-circle text-muted fs-5 me-2"></i>
                                            <div>
                                                <div class="fw-bold text-dark" style="font-size: 0.85rem;">
                                                    <?= htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']) ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($entry['role']) ?>
                                                    <?= $entry['division_name'] ? '- ' . htmlspecialchars($entry['division_name']) : '' ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <!-- See More Toggle Controller -->
                        <?php if (count($history) > 3): ?>
                            <div class="text-center mt-3 mb-2">
                                <button id="btn-toggle-history"
                                    class="btn btn-sm btn-outline-primary fw-bold shadow-sm px-4 rounded-pill">
                                    <i class="fa-solid fa-chevron-down me-2"></i>See More (<?= count($history) - 3 ?> more)
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer Metadata -->
    <div class="mt-4 pt-4 border-top d-flex align-items-center">
        <div class="creator-avatar me-2"
            style="width: 34px; height: 34px; background: #e2e8f0; color: #475569; display:flex; align-items:center; justify-content:center; border-radius:50%;">
            <i class="fa-solid fa-user"></i>
        </div>
        <div class="text-dark mb-0">
            <div class="fw-bold text-dark small">Created By: <span
                    class="text-primary"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span></div>
            <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($doc['c_division']) ?>
                <p>
                    <?= date('F d, Y h:i A', strtotime($doc['created_at'])) ?>
                </p>
            </div>
        </div>
    </div>
</div>
</div>
<div class="modal fade" id="reuseConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #0d6efd; border-radius: 20px;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-copy text-primary mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Reuse Record?</h5>
                <p class="text-muted" style="font-size: 0.92rem; line-height: 1.6;"> Are you sure you want to reuse this
                    document? A new draft will be created with the same details and attachments. </p>
                <form action="../../controllers/reuseDocument.php" method="POST" class="m-0 w-100">
                    <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                    <button type="submit" class="btn btn-primary fw-bold w-100">Yes, Reuse</button>
                </form>
                <button type="button" class="btn btn-light border w-100" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
</div>
<!-- Simple Vanilla Javascript to handle the See More collapsible flow toggle smoothly -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const collapsibles = document.querySelectorAll('.track-item-collapsible');
        const toggleButton = document.getElementById('btn-toggle-history');

        if (toggleButton && collapsibles.length > 0) {
            toggleButton.addEventListener('click', function () {
                const isCurrentlyCollapsed = collapsibles[0].classList.contains('d-none');

                collapsibles.forEach(function (element) {
                    if (isCurrentlyCollapsed) {
                        element.classList.remove('d-none');
                        // Simple CSS fade-in
                        element.style.opacity = '0';
                        element.style.transition = 'opacity 0.25s ease-in-out';
                        setTimeout(function () {
                            element.style.opacity = '1';
                        }, 50);
                    } else {
                        element.classList.add('d-none');
                    }
                });

                if (isCurrentlyCollapsed) {
                    toggleButton.innerHTML = '<i class="fa-solid fa-chevron-up me-2"></i>Show Less';
                } else {
                    toggleButton.innerHTML = '<i class="fa-solid fa-chevron-down me-2"></i>See More (' + collapsibles.length + ' more)';
                }
            });
        }
    });
</script>
<?php require_once BASE_PATH . 'includes/footer.php'; ?>