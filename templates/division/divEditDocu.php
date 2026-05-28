<?php
// templates/division/divEditDocu.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php"); exit;
}

$document_id = $_GET['id'] ?? null;
if (!$document_id) { header("Location: divOutgoing.php"); exit; }

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Fetch Session Messages
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// 1. Fetch Existing Data
$doc = $docManager->getDocumentById($document_id, $user_id);
if (!$doc) { header("Location: divOutgoing.php"); exit; }

$attachments = $docManager->getDocumentAttachments($document_id);
$stmtRecip = $pdo->prepare("SELECT recipient_user_id FROM records_documentrecipient WHERE document_id = ?");
$stmtRecip->execute([$document_id]);
$current_recipients = $stmtRecip->fetchAll(PDO::FETCH_COLUMN);

$history_logs = $docManager->getDocumentTrackingHistory($document_id);

// Logic to determine which Group ID belongs to the current document address
$selected_group_id = 0;
if ($doc['route_type'] === 'group' && isset($doc['address_name'])) {
    $stmtGrp = $pdo->prepare("SELECT id FROM records_distributiongroup WHERE group_name = ? LIMIT 1");
    $stmtGrp->execute([$doc['address_name']]);
    $selected_group_id = $stmtGrp->fetchColumn();
}

// Determine current division if routed by division
$current_div_id = '';
if ($doc['route_type'] === 'division' && !empty($current_recipients)) {
    $current_div_id = $docManager->getUserDivisionId($current_recipients[0]);
}

$classifications = $docManager->getClassifications();
$document_types = $docManager->getDocumentTypes();
$signatories = $docManager->getSignatories();
$divisions = $docManager->getDivisions();
$groups = $docManager->getGroups();
$users_by_div = $docManager->getUsersGroupedByDivision();

// DTI Branches and External Origins
$dti_branches = $docManager->getDtiBranches();
$origins = $pdo->query("SELECT name FROM records_origin ORDER BY name ASC")->fetchAll();

// Fetch existing multi-recipients if within_dti or outside_dti
$existing_dti_recipients = [];
$existing_ext_recipients = [];
if ($doc['route_type'] === 'within_dti' || $doc['route_type'] === 'outside_dti') {
    $stmtExt = $pdo->prepare("SELECT * FROM records_externalrecipient WHERE document_id = ?");
    $stmtExt->execute([$document_id]);
    $results = $stmtExt->fetchAll();
    foreach ($results as $r) {
        if ($r['type'] === 'within_dti') $existing_dti_recipients[] = $r;
        if ($r['type'] === 'outside_dti') $existing_ext_recipients[] = $r;
    }
}

$page_title = "Edit " . ($doc['dts_no'] ?? '');

$status_cat = strtoupper(trim($doc['status_category'] ?? ''));
$status_name = strtoupper(trim($doc['status_name'] ?? ''));
$locked_statuses = ['APPROVED', 'FOR-DISPATCH', 'FOR DISPATCH', 'CLOSED', 'DISPATCHED', 'CANCELLED'];
$is_locked = in_array($status_cat, $locked_statuses) || in_array($status_name, $locked_statuses);

$is_rejected = ($status_cat === 'REJECTED' || $status_name === 'REJECTED');
$reject_reason = $is_rejected ? $docManager->getRejectReason($document_id) : '';

// Visual Tracker calculation
$combined_status = $status_cat . '|' . $status_name;
$level = 1;
if (strpos($combined_status, 'CLOSE') !== false || strpos($combined_status, 'DISPATCHED') !== false) { $level = 5; }
elseif (strpos($combined_status, 'FOR DISPATCH') !== false || strpos($combined_status, 'FOR-DISPATCH') !== false) { $level = 4; }
elseif (strpos($combined_status, 'APPROV') !== false && strpos($combined_status, 'FOR') === false) { $level = 3; }
elseif (strpos($combined_status, 'APPROVAL') !== false || strpos($combined_status, 'ONGOING') !== false || $is_rejected) { $level = 2; }

$step1 = 'completed';
$step2 = ($level > 2) ? 'completed' : (($level == 2) ? 'active' : '');
$step3 = ($level > 3) ? 'completed' : (($level == 3) ? 'active' : '');
$step4 = ($level > 4) ? 'completed' : (($level == 4) ? 'active' : '');
$step5 = ($level == 5) ? 'completed' : '';
if ($is_rejected) { $step2 = 'danger'; }
$progress_width = ($level == 2) ? 25 : (($level == 3) ? 50 : (($level == 4) ? 75 : (($level == 5) ? 100 : 0)));

// ==========================================
// DYNAMIC TRACKER LABELS & ICONS
// ==========================================
$is_to_ro = in_array($doc['route_type'] ?? '', ['outside_dti', 'within_dti']);
$label_step4 = $is_to_ro ? 'Waiting to Dispatch' : 'Waiting to be Received';
$label_step5 = $is_to_ro ? 'Dispatch' : 'Received';
$icon_step4 = $is_to_ro ? '<i class="fa-solid fa-boxes-packing"></i>' : '<i class="fa-solid fa-inbox"></i>';
$icon_step5_success = $is_to_ro ? '<i class="fa-solid fa-paper-plane"></i>' : '<i class="fa-solid fa-check-double"></i>';
// ==========================================

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/forms.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/edit.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/uploader.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/track.css">
<style>.visual-stepper::before { display: none !important; }</style>
';

$extra_js = '
<script src="' . BASE_URL . 'static/js/upload-queue.js"></script>
<script src="' . BASE_URL . 'static/js/create_document.js"></script>
<script src="' . BASE_URL . 'static/js/track.js"></script>
<script>
    function showReadonlyAlert() {
        var myModal = new bootstrap.Modal(document.getElementById("readonlyModal"));
        myModal.show();
    }
</script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="mb-4">
        <a href="divOutgoing.php" class="text-decoration-none text-secondary fw-bold" style="font-size: 0.9rem;">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Outgoing List
        </a>
    </div>

    <?php if ($success_msg): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="detail-card bg-white p-4 rounded shadow-sm border">

        <div class="detail-header d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice text-primary me-2"></i> Control No. <span class="text-primary"><?= htmlspecialchars($doc['dts_no']) ?></span></h4>
            <span class="status <?= strtolower(str_replace(' ', '-', $doc['status_category'])) ?> fs-6 px-3 py-2"><?= htmlspecialchars($doc['status_name']) ?></span>
        </div>

        <?php if ($is_rejected): ?>
            <div class="alert alert-danger mb-4 d-flex align-items-start shadow-sm" style="border-left: 5px solid #dc3545; background-color: #fef2f2;">
                <i class="fa-solid fa-circle-xmark fs-4 me-3 mt-1 text-danger"></i>
                <div>
                    <h6 class="fw-bold mb-1 text-danger">Document Rejected</h6>
                    <p class="mb-0 text-dark" style="font-size: 0.9rem;"><strong>Signatory Reason:</strong> <?= nl2br(htmlspecialchars(str_replace('Document rejected by Signatory. Reason: ', '', $reject_reason ?: 'No specific reason provided.'))) ?></p>
                    <p class="mb-0 mt-2 text-muted fw-bold" style="font-size: 0.85rem;">You can edit the details below to fix the issue. Saving your changes will automatically resubmit it for approval.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-light border rounded p-4 mb-4 shadow-sm">
            <h6 class="fw-bold text-dark mb-4 text-center text-uppercase" style="letter-spacing: 1px;"><i class="fa-solid fa-route text-secondary me-2"></i> Document Status</h6>
            <div class="position-relative mt-4 mb-2">
                <div style="position: absolute; top: 15px; left: 10%; right: 10%; height: 4px; background: #e2e8f0; z-index: 1;"></div>
                <div style="position: absolute; top: 15px; left: 10%; width: calc(80% * <?= $progress_width ?> / 100); height: 4px; background: <?= $is_rejected ? '#dc3545' : '#10b981' ?>; z-index: 2; transition: width 0.5s ease;"></div>
                <div class="visual-stepper d-flex justify-content-between position-relative" style="z-index: 3; padding: 0;">
                    <div class="step <?= $step1 ?>"><div class="circle"><i class="fa-solid fa-file-import"></i></div><div class="label">Encoded</div></div>
                    <div class="step <?= $step2 ?>"><div class="circle"><i class="fa-solid fa-file-signature"></i></div><div class="label">For Approval</div></div>
                    <div class="step <?= $step3 ?>"><div class="circle"><i class="fa-solid fa-stamp"></i></div><div class="label">Approved</div></div>
                        <div class="step <?= $step4 ?>">
                    <div class="circle"><?= $icon_step4 ?></div>
                    <div class="label"><?= $label_step4 ?></div>
                </div>
                <div class="step <?= $step5 ?>">
                    <div class="circle">
                        <?php if($is_rejected): ?>
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

        <div class="border rounded p-4 mb-5 shadow-sm" style="background-color: #f8fafc;">
            <h6 class="fw-bold text-dark mb-4"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Activity Log Timeline</h6>
            <div class="timeline-container">
                <?php if(empty($history_logs)): ?>
                    <p class="text-muted small fst-italic">No history records found for this document.</p>
                <?php else: ?>
                    <?php foreach ($history_logs as $index => $log):
                        $icon = 'fa-arrow-right'; $bg = '#1d4ed8';
                        if(strtoupper($log['action_taken']) === 'ENCODED') { $icon = 'fa-plus'; $bg = '#10b981'; }
                        elseif(strtoupper($log['action_taken']) === 'EDITED' || strtoupper($log['action_taken']) === 'RESUBMITTED') { $icon = 'fa-pen'; $bg = '#f59e0b'; }
                        elseif(strtoupper($log['action_taken']) === 'CANCELLED' || strtoupper($log['action_taken']) === 'REJECTED') { $icon = 'fa-xmark'; $bg = '#dc3545'; }
                        elseif(strtoupper($log['action_taken']) === 'APPROVED' || strtoupper($log['action_taken']) === 'CLOSED') { $icon = 'fa-check'; $bg = '#10b981'; }
                        $hidden_class = ($index >= 3) ? 'd-none extra-log' : '';
                    ?>
                    <div class="timeline-item <?= $hidden_class ?>">
                        <div class="timeline-icon" style="background-color: <?= $bg ?>;"><i class="fa-solid <?= $icon ?>"></i></div>
                        <div class="timeline-content bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars(strtoupper($log['action_taken'])) ?></span>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary rounded-pill px-2 py-1" style="font-size: 0.7rem;"><?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?></span>
                            </div>
                            <p class="mb-2 text-secondary" style="font-size: 0.85rem;"><?= htmlspecialchars($log['remarks']) ?></p>
                            <div class="text-muted" style="font-size: 0.75rem;"><i class="fa-solid fa-user me-1 text-primary"></i> <span class="fw-bold text-dark"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></span> (<?= htmlspecialchars($log['role'] ?? 'User') ?> - <?= htmlspecialchars($log['division_name'] ?? 'System') ?>)</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if(count($history_logs) > 3): ?>
                        <div class="text-center mt-4"><button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-4 py-2 fw-bold" onclick="toggleActivityLogs()">See More <i class="fa-solid fa-chevron-down ms-1"></i></button></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <h6 class="fw-bold text-dark mb-4 mt-2 border-top pt-4">
            <i class="fa-solid <?= $is_locked ? 'fa-lock text-secondary' : 'fa-pen-to-square text-primary' ?> me-2"></i>
            Document Information <?= $is_locked ? '<span class="text-danger small ms-2">(View Only)</span>' : '' ?>
        </h6>

        <div class="position-relative">
            <?php if ($is_locked): ?>
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10; cursor: not-allowed;" onclick="showReadonlyAlert()"></div>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const form = document.getElementById('editDocForm');
                        if(form) {
                            const inputs = form.querySelectorAll('input, select, textarea, button:not(.btn-close)');
                            inputs.forEach(input => input.disabled = true);
                        }
                    });
                </script>
            <?php endif; ?>

            <fieldset <?= $is_locked ? 'disabled' : '' ?>>
                <form id="editDocForm" method="POST" action="<?= $is_locked ? '#' : '../../controllers/editDocument.php' ?>" enctype="multipart/form-data" <?= $is_locked ? 'onsubmit="return false;"' : '' ?>>
                <input type="hidden" name="action" value="update_document">
                <input type="hidden" name="document_id" value="<?= htmlspecialchars($document_id) ?>">
                <input type="hidden" name="hidden_classification" id="hidden_classification" value="<?= $doc['classification_id'] ?>">

                <input type="hidden" id="docCreatorId" value="<?= htmlspecialchars($doc['creator_id'] ?? $_SESSION['user_id']) ?>">

                    <div id="removedAttachmentsContainer"></div>

                    <div class="form-section bg-light border p-3 rounded mb-3">
                        <label class="form-label fw-bold text-primary mb-3 small"><i class="fa-solid fa-map-location-dot me-1"></i> Address Information (Destination) *</label>
                        <select class="form-select custom-input mb-3" name="route_type" id="routeType" required>
                            <option value="division" <?= ($doc['route_type'] === 'division') ? 'selected' : '' ?>>Division / Personnel (Internal)</option>
                            <option value="group" <?= ($doc['route_type'] === 'group') ? 'selected' : '' ?>>Distribution Group</option>
                            <option value="within_dti" <?= ($doc['route_type'] === 'within_dti') ? 'selected' : '' ?>>Within DTI (Other Branches)</option>
                            <option value="outside_dti" <?= ($doc['route_type'] === 'outside_dti') ? 'selected' : '' ?>>Outside Offices / External Agency</option>
                        </select>

                        <div id="block-division" class="routing-block <?= ($doc['route_type'] === 'division') ? '' : 'd-none' ?>">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <select class="form-select custom-input" id="routeDivision" name="route_division">
                                        <option value="">1. Select Division...</option>
                                        <?php foreach ($divisions as $div): ?>
                                            <option value="<?= $div['id'] ?>" <?= ($div['id'] == $current_div_id) ? 'selected' : '' ?>><?= htmlspecialchars($div['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div id="routeUsersContainer" class="checkbox-container border rounded p-2 bg-white" style="max-height: 120px; overflow-y: auto;">
                                        <!-- Checkboxes will be populated by JS script below -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="block-group" class="routing-block <?= ($doc['route_type'] === 'group') ? '' : 'd-none' ?>">
                            <select class="form-select custom-input" name="route_group" id="route_group">
                                <option value="">Select Distribution Group...</option>
                                <?php foreach ($groups as $grp): ?>
                                    <option value="<?= $grp['id'] ?>" <?= ($doc['route_type'] === 'group' && $selected_group_id == $grp['id']) ? 'selected' : '' ?>><?= htmlspecialchars($grp['group_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="block-dtibranch" class="routing-block <?= ($doc['route_type'] === 'within_dti') ? '' : 'd-none' ?>">
                            <div id="dti-recipient-container">
                                <?php if (!empty($existing_dti_recipients)): ?>
                                    <?php foreach ($existing_dti_recipients as $idx => $r): ?>
                                        <div class="dti-recipient-row bg-white border rounded p-3 mb-2 position-relative shadow-sm">
                                            <div class="row g-2">
                                                <div class="col-md-5">
                                                    <select class="form-select custom-input dti-branch-input" name="dti_branch[]" <?= ($idx===0) ? 'required' : '' ?>>
                                                        <option value="">Select DTI Branch...</option>
                                                        <?php foreach ($dti_branches as $branch): ?>
                                                            <option value="<?= $branch['id'] ?>" <?= ($branch['id'] == $r['dtibranch_id']) ? 'selected' : '' ?>><?= htmlspecialchars($branch['name']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3"><input type="text" class="form-control custom-input dti-contact-input" name="dti_contact[]" placeholder="Contact Person" value="<?= htmlspecialchars($r['contact_person'] ?? '') ?>"></div>
                                                <div class="col-md-3"><input type="text" class="form-control custom-input" name="dti_notes[]" placeholder="Notes (Optional)" value="<?= htmlspecialchars($r['notes'] ?? '') ?>"></div>
                                                <div class="col-md-1 d-flex align-items-center justify-content-center">
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-recipient-btn <?= ($idx===0) ? 'd-none' : '' ?>" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="dti-recipient-row bg-white border rounded p-3 mb-2 position-relative shadow-sm">
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <select class="form-select custom-input dti-branch-input" name="dti_branch[]"><option value="">Select DTI Branch...</option><?php foreach ($dti_branches as $branch): ?><option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option><?php endforeach; ?></select>
                                            </div>
                                            <div class="col-md-3"><input type="text" class="form-control custom-input dti-contact-input" name="dti_contact[]" placeholder="Contact Person"></div>
                                            <div class="col-md-3"><input type="text" class="form-control custom-input" name="dti_notes[]" placeholder="Notes (Optional)"></div>
                                            <div class="col-md-1 d-flex align-items-center justify-content-center"><button type="button" class="btn btn-sm btn-outline-danger remove-recipient-btn d-none" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold mt-1" onclick="addRecipientRow('dti-recipient-container')"><i class="fa-solid fa-plus me-1"></i> Add Another Recipient</button>
                        </div>

                        <div id="block-external" class="routing-block <?= ($doc['route_type'] === 'outside_dti') ? '' : 'd-none' ?>">
                            <div id="ext-recipient-container">
                                <?php if (!empty($existing_ext_recipients)): ?>
                                    <?php foreach ($existing_ext_recipients as $idx => $r): ?>
                                        <div class="ext-recipient-row bg-white border rounded p-3 mb-2 position-relative shadow-sm">
                                            <div class="row g-2">
                                                <div class="col-md-5">
                                                    <input type="text" list="agencySuggestions" class="form-control custom-input ext-office-input" name="ext_office[]" placeholder="Type or select Agency..." value="<?= htmlspecialchars($r['ext_office'] ?? '') ?>" <?= ($idx===0) ? 'required' : '' ?>>
                                                </div>
                                                <div class="col-md-3"><input type="text" class="form-control custom-input ext-contact-input" name="out_ext_name[]" placeholder="Contact Person" value="<?= htmlspecialchars($r['contact_person'] ?? '') ?>"></div>
                                                <div class="col-md-3"><input type="text" class="form-control custom-input" name="out_notes[]" placeholder="Notes (Optional)" value="<?= htmlspecialchars($r['notes'] ?? '') ?>"></div>
                                                <div class="col-md-1 d-flex align-items-center justify-content-center">
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-recipient-btn <?= ($idx===0) ? 'd-none' : '' ?>" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="ext-recipient-row bg-white border rounded p-3 mb-2 position-relative shadow-sm">
                                        <div class="row g-2">
                                            <div class="col-md-5"><input type="text" list="agencySuggestions" class="form-control custom-input ext-office-input" name="ext_office[]" placeholder="Type or select Agency..."></div>
                                            <div class="col-md-3"><input type="text" class="form-control custom-input ext-contact-input" name="out_ext_name[]" placeholder="Contact Person (Optional)"></div>
                                            <div class="col-md-3"><input type="text" class="form-control custom-input" name="out_notes[]" placeholder="Notes (Optional)"></div>
                                            <div class="col-md-1 d-flex align-items-center justify-content-center"><button type="button" class="btn btn-sm btn-outline-danger remove-recipient-btn d-none" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <datalist id="agencySuggestions">
                                <?php foreach($origins as $org): ?><option value="<?= htmlspecialchars($org['name']) ?>"><?php endforeach; ?>
                            </datalist>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold mt-1" onclick="addRecipientRow('ext-recipient-container')"><i class="fa-solid fa-plus me-1"></i> Add Another Recipient</button>
                        </div>
                    </div>

                    <div class="row g-3 mb-3 mt-1">
                        <div class="col-md-4">
                            <label class="modal-label">Classification *</label>
                            <select class="form-select custom-input <?= ($doc['route_type'] === 'within_dti' || $doc['route_type'] === 'outside_dti') ? 'bg-light' : '' ?>" name="classification" id="classification" required <?= ($doc['route_type'] === 'within_dti' || $doc['route_type'] === 'outside_dti') ? 'style="pointer-events:none;"' : '' ?>>
                                <option value="">Select...</option>
                                <?php foreach ($classifications as $item): ?>
                                    <option value="<?= $item['id'] ?>" <?= ($item['id'] == $doc['classification_id']) ? 'selected' : '' ?>><?= htmlspecialchars($item['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="modal-label">Document Type *</label>
                            <select class="form-select custom-input" name="document_type" required>
                                <option value="">Select...</option>
                                <?php foreach ($document_types as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= ($type['id'] == $doc['document_type_id']) ? 'selected' : '' ?>><?= htmlspecialchars($type['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="modal-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control custom-input" value="<?= $doc['due_date'] ? date('Y-m-d', strtotime($doc['due_date'])) : '' ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="modal-label">Subject *</label>
                        <textarea name="subject" class="form-control custom-input" rows="2" required><?= htmlspecialchars($doc['subject']) ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="modal-label">Signatory (Optional)</label>
                            <select class="form-select custom-input border-primary" name="signatory">
                                <option value="">None...</option>
                                <?php foreach ($signatories as $sig): ?>
                                    <option value="<?= $sig['id'] ?>" <?= ($sig['id'] == $doc['signatory_id']) ? 'selected' : '' ?>><?= htmlspecialchars($sig['first_name'] . ' ' . $sig['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="modal-label">Particulars (Optional)</label>
                            <input type="text" name="particulars" class="form-control custom-input" value="<?= htmlspecialchars($doc['particulars'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4 border rounded p-4" style="background-color: #fff;">
                        <label class="form-label modal-label"><i class="fa-solid fa-paperclip me-2 text-primary"></i> Attachments</label>

                        <?php if(!empty($attachments)): ?>
                            <div class="mb-3 mt-2">
                                <style> .file-link { text-decoration: underline; transition: color 0.2s ease; } .file-link:hover { color: #1d4ed8 !important; } </style>
                                <?php foreach($attachments as $att):
                                    $ext = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                                    $icon = 'fa-file'; $color = 'text-secondary';
                                    if ($ext === 'pdf') { $icon = 'fa-file-pdf'; $color = 'text-danger'; }
                                    elseif (in_array($ext, ['doc', 'docx'])) { $icon = 'fa-file-word'; $color = 'text-primary'; }
                                    elseif (in_array($ext, ['xls', 'xlsx'])) { $icon = 'fa-file-excel'; $color = 'text-success'; }
                                    elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) { $icon = 'fa-image'; $color = 'text-warning'; }

                                    $file_name = htmlspecialchars(basename($att['file_path']));
                                    $file_url = '../../uploads/' . $file_name;
                                ?>
                                    <div class="existing-file d-flex justify-content-between align-items-center bg-light position-relative" id="att-row-<?= $att['id'] ?>" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 10px; z-index: 20;">
                                        <div class="d-flex align-items-center text-truncate me-3">
                                            <i class="fa-solid <?= $icon ?> <?= $color ?> me-2 fs-5"></i>
                                            <a href="<?= $file_url ?>" target="_blank" class="text-dark fw-bold text-truncate file-link" style="font-size: 0.9rem; position: relative; z-index: 20;" title="<?= $file_name ?>">
                                                <?= $file_name ?>
                                            </a>
                                        </div>
                                        <div class="d-flex align-items-center flex-shrink-0" style="position: relative; z-index: 20;">
                                            <?php if (!$is_locked): ?>
                                                <button type="button" class="btn-remove-file p-0 m-0 text-danger bg-transparent border-0 ms-2" onclick="promptRemoveFile(event, <?= $att['id'] ?>)" style="font-size: 1.2rem; line-height: 1;" title="Remove Attachment">&times;</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small fst-italic">No files currently attached.</p>
                        <?php endif; ?>

                        <?php if (!$is_locked): ?>
                            <div class="drag-drop-box mt-3" id="dropZone">
                                <div class="upload-content text-center w-100">
                                    <i class="fa-solid fa-cloud-arrow-up mb-2" style="font-size: 2rem; color: #263D81;"></i>
                                    <span id="dropZoneText" class="d-block text-muted mt-1">
                                        <span class="fw-bold text-dark">Click to upload additional files</span> or drag and drop<br>
                                        <small>Maximum size: 10MB</small>
                                    </span>
                                    <input type="file" id="fileInput" name="document_files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="display: none;">
                                    <div id="fileQueueDisplay" class="mt-3 text-start w-100"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </fieldset>
        </div>

        <?php if (!$is_locked): ?>
            <div class="d-flex justify-content-end gap-3 pt-4 border-top mt-5">
                <button type="button" class="btn btn-danger px-4 fw-bold me-auto" style="border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#cancelEntryModal">
                    <i class="fa-solid fa-ban me-1"></i> Cancel Document
                </button>
                <button type="button" class="btn btn-secondary px-4 fw-bold" style="border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#cancelEditModal">Discard Edits</button>

                <button type="button" class="btn btn-blue px-4 fw-bold" style="background-color: #1d4ed8; border-radius: 6px;" id="btnSaveTrigger">
                    <?= $is_rejected ? 'Save & Resubmit' : 'Save Changes' ?>
                </button>
            </div>
        <?php endif; ?>

    </div>
</div>

<div class="modal fade" id="readonlyModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #6c757d;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-lock text-secondary mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Document Locked</h5>
                <p class="text-muted" style="font-size: 0.9rem;">This document is currently marked as <b><?= htmlspecialchars($doc['status_name']) ?></b>. It is in View-Only mode and can no longer be modified.</p>
                <button type="button" class="btn btn-secondary w-100 fw-bold mt-2" data-bs-dismiss="modal">Understood</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelEditModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #eab308;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-triangle-exclamation text-warning mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Discard Changes?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">Any unsaved data will be lost. Are you sure you want to discard your edits?</p>
                <div class="d-flex flex-column gap-2 mt-4">
                    <button type="button" class="btn btn-warning fw-bold text-dark w-100" onclick="window.location.href='divOutgoing.php'">Yes, Discard</button>
                    <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">No, Keep Editing</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="saveConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #1d4ed8;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-floppy-disk text-primary mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Save & Route?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">Are you sure you want to apply these updates and route the document?</p>
                <div class="d-flex flex-column gap-2 mt-4">
                    <button type="button" id="confirmSaveBtn" class="btn btn-blue fw-bold w-100" style="background-color: #1d4ed8; color: #fff;" onclick="document.getElementById('editDocForm').submit();">
                        Yes, Submit
                    </button>
                    <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">Review Edits</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelEntryModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #dc3545;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-ban text-danger mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Cancel this Document?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">This document will be marked as CANCELLED and moved to your History. Continue?</p>
                <form action="../../controllers/editDocument.php" method="POST">
                    <input type="hidden" name="action" value="cancel_document">
                    <input type="hidden" name="doc_id" value="<?= htmlspecialchars($doc['id']) ?>">
                    <div class="d-flex flex-column gap-2 mt-4">
                        <button type="submit" class="btn btn-danger fw-bold w-100">Confirm Cancellation</button>
                        <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">Go Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="removeFileModal" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #dc3545;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-trash-can text-danger mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Remove File?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">Are you sure you want to remove this attachment? It will be deleted permanently when you save changes.</p>
                <div class="d-flex flex-column gap-2 mt-4">
                    <button type="button" class="btn btn-danger fw-bold w-100" onclick="executeFileRemoval()">Yes, Remove</button>
                    <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DATA SCRIPTS FOR JS FILTERS -->
<script id="usersData" type="application/json"><?= json_encode($users_by_div) ?></script>
<script id="recipientsData" type="application/json"><?= json_encode($current_recipients) ?></script>

<script>
    // INITIALIZATION: Fix for the blank Division Recipients field
    document.addEventListener("DOMContentLoaded", function() {
        const routeDivision = document.getElementById('routeDivision');
        const usersByDiv = JSON.parse(document.getElementById('usersData').textContent);
        const selectedRecipients = JSON.parse(document.getElementById('recipientsData').textContent);
        const container = document.getElementById('routeUsersContainer');

        function populateCheckboxes(divId) {
            if (!divId || !usersByDiv[divId]) {
                container.innerHTML = '<span class="text-muted small italic">Select division first...</span>';
                return;
            }
            container.innerHTML = '';
            usersByDiv[divId].forEach(user => {
                // EXCLUSION: Don't show the person who created the doc or a system user
                const isChecked = selectedRecipients.includes(String(user.id)) || selectedRecipients.includes(parseInt(user.id)) ? 'checked' : '';
                const div = document.createElement('div');
                div.className = 'form-check mb-1';
                div.innerHTML = `
                    <input class="form-check-input" type="checkbox" name="route_users[]" value="${user.id}" id="u${user.id}" ${isChecked}>
                    <label class="form-check-label small fw-bold" for="u${user.id}">${user.first_name} ${user.last_name}</label>
                `;
                container.appendChild(div);
            });
        }

        // Run immediately if division is already set
        if (routeDivision && routeDivision.value) {
            populateCheckboxes(routeDivision.value);
        }

        // Add listener for future changes
        if (routeDivision) {
            routeDivision.addEventListener('change', function() {
                populateCheckboxes(this.value);
            });
        }
    });

    // 1. Form Validation BEFORE opening the Save Modal
    document.getElementById('btnSaveTrigger')?.addEventListener('click', function() {
        const form = document.getElementById('editDocForm');
        if (!form.checkValidity()) {
            form.reportValidity(); // Highlights missing required fields automatically
        } else {
            var saveModal = new bootstrap.Modal(document.getElementById('saveConfirmModal'));
            saveModal.show();
        }
    });

    // 2. File Removal Logic
    let currentFileToRemove = null;

    function promptRemoveFile(event, attachmentId) {
        event.preventDefault(); // Stop any parent links from triggering
        currentFileToRemove = attachmentId;

        var modalEl = document.getElementById('removeFileModal');
        var removeModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        removeModal.show();
    }

    function executeFileRemoval() {
        if (currentFileToRemove !== null) {

            // COMPLETELY remove the element from the DOM
            const fileRow = document.getElementById('att-row-' + currentFileToRemove);
            if (fileRow) {
                fileRow.remove();
            }

            // Add hidden input to tell the backend controller to delete this file upon form submission
            const container = document.getElementById('removedAttachmentsContainer');
            if (container) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_attachments[]';
                input.value = currentFileToRemove;
                container.appendChild(input);
            }

            // Gracefully close the modal
            var modalEl = document.getElementById('removeFileModal');
            var modalInstance = bootstrap.Modal.getInstance(modalEl);
            if(modalInstance) {
                modalInstance.hide();
            }

            currentFileToRemove = null;
        }
    }
</script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>