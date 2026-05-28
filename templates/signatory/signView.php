<?php
// templates/signatory/signView.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check: Only 'RD' or 'ARD' role can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Signatory') {
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

// Handle Session Messages
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$parsed_url = parse_url($referer, PHP_URL_PATH);
$return_page = $parsed_url ? basename($parsed_url) : 'signDashboard.php';

if ($return_page === 'signView.php' || empty($return_page)) {
    $return_page = 'signDashboard.php';
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

$attachments = $docManager->getDocumentAttachments($doc_id);
$history_logs = $docManager->getDocumentTrackingHistory($doc_id);
$all_recipients = $docManager->getRecipientsByDocument();
$recipients = $all_recipients[$doc_id] ?? [];

// --- FIXED DESTINATION & OFFICE LOGIC ---
$dest_names_html = '<span class="text-muted fst-italic">No specific personnel assigned</span>';

// 1. GET FULL OFFICE LIST (Bypasses the "1 more" summary in the address table)
$full_offices = $docManager->getFullOfficeList($doc_id);
$display_offices = $full_offices ?: ($doc['address_name'] ?? 'Internal Routing');

// 2. GET RECIPIENT NAMES
if (in_array($doc['route_type'], ['outside_dti', 'within_dti'])) {
    $stmtExt = $pdo->prepare("SELECT contact_person FROM records_externalrecipient WHERE document_id = ?");
    $stmtExt->execute([$doc_id]);
    $ext_contacts = $stmtExt->fetchAll(PDO::FETCH_COLUMN);
    $valid_contacts = array_filter($ext_contacts, function ($c) {
        return trim($c) !== ''; });
    if (!empty($valid_contacts)) {
        $dest_names_html = implode(', ', array_map('htmlspecialchars', $valid_contacts));
    }
} else {
    if (!empty($recipients)) {
        $clean_names = array_map(function ($person) {
            return explode(' (', $person)[0]; }, $recipients);
        $dest_names_html = implode(', ', array_map('htmlspecialchars', array_unique($clean_names)));
    }
}
// ------------------------------------------

// Visual Tracker Calculation
$cat = strtoupper(trim($doc['status_category'] ?? ''));
$nam = strtoupper(trim($doc['status_name'] ?? ''));
$combined_status = $cat . '|' . $nam;
$level = 1;
$is_rejected = (strpos($combined_status, 'REJECT') !== false || strpos($combined_status, 'CANCEL') !== false);

if (strpos($combined_status, 'CLOSE') !== false || strpos($combined_status, 'DISPATCHED') !== false) {
    $level = 5;
} elseif (strpos($combined_status, 'FOR DISPATCH') !== false || strpos($combined_status, 'FOR-DISPATCH') !== false) {
    $level = 4;
} elseif (strpos($combined_status, 'APPROV') !== false && strpos($combined_status, 'FOR') === false) {
    $level = 3;
} elseif (strpos($combined_status, 'APPROVAL') !== false || strpos($combined_status, 'ONGOING') !== false) {
    $level = 2;
}

$step1 = 'completed';
$step2 = ($level > 2) ? 'completed' : (($level == 2) ? 'active' : '');
$step3 = ($level > 3) ? 'completed' : (($level == 3) ? 'active' : '');
$step4 = ($level > 4) ? 'completed' : (($level == 4) ? 'active' : '');
$step5 = ($level == 5) ? 'completed' : '';
if ($is_rejected) {
    if ($level <= 2)
        $step2 = 'danger';
    elseif ($level == 3)
        $step3 = 'danger';
    elseif ($level == 4)
        $step4 = 'danger';
    elseif ($level == 5)
        $step5 = 'danger';
}
$progress_width = ($level == 2) ? 25 : (($level == 3) ? 50 : (($level == 4) ? 75 : (($level == 5) ? 100 : 0)));

$is_to_ro = in_array($doc['route_type'] ?? '', ['outside_dti', 'within_dti']);
$label_step4 = $is_to_ro ? 'Waiting to Dispatch' : 'Waiting to be Received';
$label_step5 = $is_to_ro ? 'Dispatch' : 'Received';
$icon_step4 = $is_to_ro ? '<i class="fa-solid fa-boxes-packing"></i>' : '<i class="fa-solid fa-inbox"></i>';
$icon_step5_success = $is_to_ro ? '<i class="fa-solid fa-paper-plane"></i>' : '<i class="fa-solid fa-check-double"></i>';

$page_title = "Document Details - " . htmlspecialchars($doc['dts_no']);

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/document.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/accept_docu.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/track.css">
<style>
    .data-group { margin-bottom: 0; }
    .visual-stepper::before { display: none !important; }

    /* VISIBILITY IMPROVEMENTS */
    .data-value {
        font-weight: 700 !important;
        color: #0f172a !important;
        font-size: 0.95rem !important;
    }
    .textarea-style {
        background-color: #f8fafc !important;
        border: 1px solid #cbd5e1 !important;
        color: #0f172a !important;
        font-weight: 600 !important;
        border-radius: 8px !important;
    }
    .text-muted.small.text-uppercase {
        color: #64748b !important;
        font-weight: 800 !important;
        letter-spacing: 0.5px;
    }

    /* Info Boxes */
    .info-box {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 15px;
        height: 100%;
        text-align: left;
    }
    .info-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    .info-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: #1e293b;
    }
    .section-divider {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #94a3b8;
        margin-top: 25px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    .section-divider::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; margin-left: 10px; }

    /* History Timeline */
    .track-item { display: flex; position: relative; padding-bottom: 25px; }
    .track-item::before {
        content: ""; position: absolute; left: 17px; top: 35px; bottom: 0; width: 2px; background: #e2e8f0;
    }
    .track-item:last-child::before { display: none; }
    .track-icon {
        width: 35px; height: 35px; border-radius: 50%; background: #fff; border: 1px solid #e2e8f0;
        display: flex; align-items: center; justify-content: center; z-index: 2; flex-shrink: 0;
    }
    .history-box {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-left: 15px; width: 100%;
    }
</style>
';

$extra_js = '
<script src="' . BASE_URL . 'static/js/signatory_action.js"></script>
<script src="' . BASE_URL . 'static/js/track.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-3">
    <div class="mb-3">
        <a href="<?= htmlspecialchars($return_page) ?>" class="text-decoration-none text-secondary small fw-bold">
            <i class="fa-solid fa-chevron-left me-1"></i> Back to List </a>
    </div>
    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show"><i
                class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?><button type="button"
                class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i
                class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?><button type="button"
                class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="detail-card border rounded shadow-sm bg-white p-4">
        <div class="detail-header d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <h5 class="fw-bold mb-0">DTS No. <span class="text-primary"><?= htmlspecialchars($doc['dts_no']) ?></span>
            </h5>
            <span class="status <?= strtolower(str_replace(' ', '-', $doc['status_category'])) ?>">
                <?= htmlspecialchars($doc['status_name']) ?>
            </span>
        </div>
        <!-- STATUS TRACKER -->
        <div class="bg-light border rounded p-4 mb-4 shadow-sm">
            <h6 class="fw-bold text-dark mb-4 text-center text-uppercase" style="letter-spacing: 1px;"><i
                    class="fa-solid fa-route text-secondary me-2"></i> Document Status</h6>
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
                        <div class="circle"><?php if ($is_rejected): ?><i
                                    class="fa-solid fa-xmark"></i><?php else: ?><?= $icon_step5_success ?><?php endif; ?>
                        </div>
                        <div class="label"><?= ($is_rejected) ? 'Cancelled/Rejected' : $label_step5 ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <!-- LEFT: DETAILS -->
            <div class="col-lg-8">
                <h6 class="border-bottom pb-2 mb-3 text-secondary" style="font-size: 0.9rem;"><i
                        class="fa-solid fa-circle-info me-2"></i>Document Details</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="data-group"><label class="text-muted small text-uppercase">Classification</label>
                            <p class="data-value text-primary"><?= htmlspecialchars($doc['class_name'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="data-group"><label class="text-muted small text-uppercase">Document Type</label>
                            <p class="data-value"><?= htmlspecialchars($doc['type_name'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="data-group"><label class="text-muted small text-uppercase">Due Date</label>
                            <p class="data-value text-danger">
                                <?= !empty($doc['due_date']) ? date('M d, Y', strtotime($doc['due_date'])) : 'None' ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="data-group"><label class="text-muted small text-uppercase">Signatory</label>
                            <p class="data-value">
                                <?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? ''))) ?: 'N/A' ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="data-group"><label class="text-muted small text-uppercase">Subject</label>
                            <div class="data-value textarea-style py-2 px-3"><?= htmlspecialchars($doc['subject']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ADDRESS -->
                <h6 class="border-bottom pb-2 mb-2 text-secondary mt-4" style="font-size: 0.9rem;"><i
                        class="fa-solid fa-location-dot me-2"></i>Address</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="data-group">
                            <label class="text-muted small text-uppercase">Division / Group / Office</label>
                            <div class="data-value textarea-style py-2 px-3"><?= htmlspecialchars($display_offices) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="data-group">
                            <label class="text-muted small text-uppercase">Recipient Name(s)</label>
                            <div class="data-value textarea-style py-2 px-3"><?= $dest_names_html ?></div>
                        </div>
                    </div>
                </div>
                <!-- SENDER/ORIGIN INFORMATION -->
                <?php if (!empty($doc['origin_name']) && strtolower($doc['origin_name']) !== 'internal dti'): ?>
                    <h6 class="border-bottom pb-2 mb-2 text-secondary mt-4" style="font-size: 0.9rem;"><i
                            class="fa-solid fa-arrow-right-to-bracket me-2"></i>Origin Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="data-group"><label class="text-muted small text-uppercase">Origin Office /
                                    Agency</label>
                                <div class="data-value textarea-style py-2 px-3">
                                    <?= htmlspecialchars($doc['origin_name']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="data-group"><label class="text-muted small text-uppercase">Sender / Contact
                                    Person</label>
                                <div class="data-value textarea-style py-2 px-3">
                                    <?= htmlspecialchars($doc['sender'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <h6 class="border-bottom pb-2 mb-2 text-secondary mt-4" style="font-size: 0.9rem;"><i
                        class="fa-solid fa-circle-info me-2"></i>Additional Details</h6>
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <div class="data-group"><label class="text-muted small text-uppercase">Particulars /
                                Remarks</label>
                            <div class="data-value textarea-style py-2 px-3">
                                <?= nl2br(htmlspecialchars($doc['particulars'] ?? 'No particulars provided.')) ?></div>
                        </div>
                    </div>
                </div>
                <div class="section-divider">Attachments</div>
                <?php if (empty($attachments)): ?>
                    <p class="text-muted small ms-2">No files attached.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($attachments as $att):
                            // FIXED CONSTANT NAME BELOW
                            $ext = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                            $icon = ($ext === 'pdf') ? 'fa-file-pdf text-danger' : 'fa-file-word text-primary';
                            ?>
                            <div class="info-box d-flex align-items-center">
                                <i class="fa-solid <?= $icon ?> fs-4 me-3"></i>
                                <div class="flex-grow-1">
                                    <div class="info-value small"><?= basename($att['file_path']) ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;">Click to view attachment</div>
                                </div>
                                <a href="<?= BASE_URL . $att['file_path'] ?>" target="_blank" class="text-muted"><i
                                        class="fa-solid fa-up-right-from-square"></i></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <!-- RIGHT: ACTIVITY LOG -->
            <div class="col-lg-4">
                <h6 class="border-bottom pb-2 mb-3 text-secondary" style="font-size: 0.9rem;"><i
                        class="fa-solid fa-clock-rotate-left me-2"></i>Activity Log</h6>
                <div class="timeline-container">
                    <?php if (empty($history_logs)): ?>
                        <p class="text-muted small fst-italic">No history records found.</p><?php else: ?>
                        <?php foreach ($history_logs as $index => $log):
                            $icon = 'fa-arrow-right';
                            $bg = '#1d4ed8';
                            if (strtoupper($log['action_taken']) === 'ENCODED') {
                                $icon = 'fa-plus';
                                $bg = '#10b981';
                            } elseif (strtoupper($log['action_taken']) === 'REJECTED') {
                                $icon = 'fa-xmark';
                                $bg = '#dc3545';
                            } elseif (strtoupper($log['action_taken']) === 'APPROVED') {
                                $icon = 'fa-check';
                                $bg = '#10b981';
                            }
                            $hidden_class = ($index >= 4) ? 'd-none extra-log' : '';
                            ?>
                            <div class="timeline-item <?= $hidden_class ?>">
                                <div class="timeline-icon" style="background-color: <?= $bg ?>;"><i
                                        class="fa-solid <?= $icon ?> small"></i></div>
                                <div class="timeline-content bg-white border p-2 rounded shadow-sm mb-3">
                                    <div class="fw-bold text-dark" style="font-size: 0.8rem;">
                                        <?= htmlspecialchars(strtoupper($log['action_taken'])) ?></div>
                                    <div class="text-muted" style="font-size: 0.65rem;">
                                        <?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?></div>
                                    <p class="mb-1 text-secondary small"><?= htmlspecialchars($log['remarks']) ?></p>
                                    <div style="font-size: 0.75rem;">
                                        <div class="fw-bold text-dark">
                                            <?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></div>
                                        <div class="text-muted"><?= htmlspecialchars($log['division_name']) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($history_logs) > 4): ?>
                            <div class="text-center mt-2"><button type="button" id="toggleLogsBtn"
                                    class="btn btn-sm btn-link fw-bold text-decoration-none" onclick="toggleActivityLogs()">See
                                    More</button></div><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="footer-action-container pt-3 border-top mt-4 d-flex justify-content-between align-items-center">
            <div class="created-info">
                <div class="fw-bold text-dark small">Created By: <span
                        class="text-primary"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span></div>
                <div class="text-muted" style="font-size: 0.7rem;">
                    <?= htmlspecialchars($doc['c_division']) ?>
                    <p class="mb-0"><?= date('F d, Y h:i A', strtotime($doc['created_at'])) ?></p>
                </div>
            </div>
            <?php if (in_array(strtoupper($doc['status_name']), ['FOR APPROVAL', 'FOR-APPROVAL']) && $doc['signatory_id'] == $user_id): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-red btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal"><i
                            class="fa-solid fa-xmark-circle me-1"></i> Reject</button>
                    <button type="button" class="btn btn-blue btn-sm" data-bs-toggle="modal"
                        data-bs-target="#approveModal"><i class="fa-solid fa-check-circle me-1"></i> Approve</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- MODALS -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #0d6efd;">
            <form action="../../controllers/signatory.php" method="POST" id="approveForm">
                <div class="modal-body text-center p-4">
                    <i class="fa-solid fa-check-circle text-primary mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark">Approve Document?</h5>
                    <p class="text-muted" style="font-size: 0.9rem;">Are you sure you want to approve document
                        <b><?= htmlspecialchars($doc['dts_no']) ?></b>?</p>
                    <div class="p-2 bg-light rounded border mt-3 mb-3 text-start">
                        <span class="d-block small text-uppercase text-muted fw-bold mb-1"
                            style="font-size: 0.7rem;">Routing Action:</span>
                        <?php if (in_array($doc['route_type'], ['division', 'group'])): ?>
                            <span class="text-dark" style="font-size: 0.8rem;">This document will be directly routed to the
                                division/personnel assigned.</span>
                        <?php else: ?>
                            <span class="text-dark" style="font-size: 0.8rem;">This document will be routed back to the
                                Receiving Officer for external dispatch.</span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="action" value="approve_document">
                    <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                    <input type="hidden" name="from_page" value="<?= htmlspecialchars($return_page) ?>">
                    <div class="d-flex flex-column gap-2 mt-4">
                        <button type="submit" class="btn btn-blue fw-bold w-100" id="btnApproveSubmit">Yes,
                            Approve</button>
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
                    <p class="text-muted mb-3" style="font-size: 0.9rem;">You are about to reject document
                        <b><?= htmlspecialchars($doc['dts_no']) ?></b>. It will be returned to the creator.</p>
                    <div class="data-group m-0 text-start">
                        <label class="text-muted small text-uppercase" style="font-size: 0.7rem;">Reason for Rejection
                            (Required)</label>
                        <textarea name="reject_reason" class="form-control shadow-none mt-1" rows="3"
                            placeholder="Provide instructions or reasons..." style="font-size: 0.85rem;"
                            required></textarea>
                    </div>
                    <input type="hidden" name="action" value="reject_document">
                    <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                    <input type="hidden" name="from_page" value="<?= htmlspecialchars($return_page) ?>">
                    <div class="d-flex flex-column gap-2 mt-4">
                        <button type="submit" class="btn btn-danger fw-bold w-100" id="btnRejectSubmit">Yes,
                            Reject</button>
                        <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once BASE_PATH . 'includes/footer.php'; ?>