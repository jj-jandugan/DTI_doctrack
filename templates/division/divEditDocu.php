<?php
// templates/division/divEditDocu.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

// Security Check: Only 'Division' role can access
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

// UPDATED LOCK LOGIC: Now includes all 4 Terminal States
$is_locked = in_array(strtoupper($doc['status_category']), ['APPROVED', 'CLOSED', 'REJECTED', 'CANCELLED']);

$attachments = $docManager->getDocumentAttachments($document_id);

// Fetch current recipients for pre-populating routing
$stmtRecip = $pdo->prepare("SELECT recipient_user_id FROM records_documentrecipient WHERE document_id = ?");
$stmtRecip->execute([$document_id]);
$current_recipients = $stmtRecip->fetchAll(PDO::FETCH_COLUMN);

// Determine current division if routed by division
$current_div_id = '';
if ($doc['route_type'] === 'division' && !empty($current_recipients)) {
    $stmtFindDiv = $pdo->prepare("SELECT division_id FROM records_userprofile WHERE user_id = ? LIMIT 1");
    $stmtFindDiv->execute([$current_recipients[0]]);
    $current_div_id = $stmtFindDiv->fetchColumn();
}

// 2. Fetch Reference Data
$classifications = $docManager->getClassifications();
$document_types = $docManager->getDocumentTypes();
$signatories = $docManager->getSignatories();
$divisions = $docManager->getDivisions();
$groups = $docManager->getGroups();
$users_by_div = $docManager->getUsersGroupedByDivision($user_id);

$page_title = "Edit " . ($doc['dts_no']);

// CSS Links
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/forms.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/edit.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/uploader.css">
';

// JS Assets
$extra_js = '
<script src="' . BASE_URL . 'static/js/upload-queue.js"></script>
<script src="' . BASE_URL . 'static/js/edit_document.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        if(document.getElementById("dropZone")) {
            window.uploader = new UploadQueue("dropZone", "fileInput", "fileQueueDisplay", "dropZoneText");
        }
    });
</script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="mb-4">
        <a href="divOutgoing.php" id="backToQueue" class="text-decoration-none text-secondary fw-bold" style="font-size: 0.9rem;">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Outgoing Queue
        </a>
    </div>

    <div class="edit-container">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h3 class="fw-bold text-dark mb-0"><i class="fa-solid fa-pen-to-square text-primary me-2"></i> Manage Document</h3>
            <div class="ctrl-badge"><?= htmlspecialchars($doc['dts_no']) ?></div>
        </div>

        <?php if ($is_locked): ?>
            <div class="alert alert-warning border-warning mb-4" style="background-color: #fffbeb;">
                <i class="fa-solid fa-lock me-2 text-warning"></i>
                <strong>Document Locked.</strong> This document has reached a terminal state (Approved, Closed, Rejected, or Cancelled) and can no longer be edited.
            </div>
        <?php endif; ?>

        <?php if ($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

        <form id="editDocForm" method="POST" action="../../controllers/editDocument.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_document">
            <input type="hidden" name="doc_id" value="<?= htmlspecialchars($doc['id']) ?>">

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <label class="form-label modal-label">Classification</label>
                    <select class="form-select custom-input bg-light" name="classification" disabled>
                        <?php foreach ($classifications as $item): ?>
                            <option value="<?= $item['id'] ?>" <?= ($item['id'] == $doc['classification_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($item['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="classification" value="<?= $doc['classification_id'] ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label modal-label">Document Type *</label>
                    <select class="form-select custom-input <?= $is_locked ? 'bg-light' : '' ?>" name="document_type" required <?= $is_locked ? 'disabled' : '' ?>>
                        <option value="">Select...</option>
                        <?php foreach ($document_types as $type): ?>
                            <option value="<?= $type['id'] ?>" <?= ($type['id'] == $doc['document_type_id']) ? 'selected' : '' ?>><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label modal-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control custom-input <?= $is_locked ? 'bg-light' : '' ?>" value="<?= $doc['due_date'] ? date('Y-m-d', strtotime($doc['due_date'])) : '' ?>" <?= $is_locked ? 'readonly' : '' ?>>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label modal-label">Subject *</label>
                <textarea name="subject" class="form-control custom-input <?= $is_locked ? 'bg-light' : '' ?>" rows="2" required <?= $is_locked ? 'readonly' : '' ?>><?= htmlspecialchars($doc['subject']) ?></textarea>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label class="form-label modal-label">Particulars</label>
                    <textarea name="particulars" class="form-control custom-input <?= $is_locked ? 'bg-light' : '' ?>" rows="2" <?= $is_locked ? 'readonly' : '' ?>><?= htmlspecialchars($doc['particulars'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label modal-label text-primary"><i class="fa-solid fa-signature me-1"></i> Signatory *</label>
                    <select class="form-select custom-input <?= $is_locked ? 'bg-light' : '' ?>" name="signatory" id="signatorySelect" required <?= $is_locked ? 'disabled' : '' ?>>
                        <option value="">Select Signatory...</option>
                        <?php foreach ($signatories as $sig): ?>
                            <option value="<?= $sig['id'] ?>" <?= ($sig['id'] == $doc['signatory_id']) ? 'selected' : '' ?>><?= htmlspecialchars($sig['first_name'] . ' ' . $sig['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="border rounded p-4 mb-4" style="background-color: #f8fafc; border-color: #e2e8f0 !important;">
                <label class="form-label fw-bold text-success mb-3">Address *</label>

                <select class="form-select custom-input mb-3 <?= $is_locked ? 'bg-light' : '' ?>" name="route_type" id="routeType" required <?= $is_locked ? 'disabled' : '' ?>>
                    <option value="" <?= empty($doc['route_type']) ? 'selected' : '' ?> disabled>Select Destination Type...</option>
                    <option value="division" <?= ($doc['route_type'] === 'division') ? 'selected' : '' ?>>Division / Specific Personnel</option>
                    <option value="group" <?= ($doc['route_type'] === 'group') ? 'selected' : '' ?>>Distribution Group</option>
                    <option value="within_dti" <?= ($doc['route_type'] === 'within_dti') ? 'selected' : '' ?>>Within DTI (Other Branch)</option>
                    <option value="outside_dti" <?= ($doc['route_type'] === 'outside_dti') ? 'selected' : '' ?>>Outside Agency</option>
                </select>

                <div id="block-division" class="<?= ($doc['route_type'] === 'division') ? '' : 'd-none' ?>">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <select class="form-select custom-input <?= $is_locked ? 'bg-light' : '' ?>" id="routeDivision" name="route_division" <?= $is_locked ? 'disabled' : '' ?>>
                                <option value="">Select Division...</option>
                                <?php foreach ($divisions as $div): ?>
                                    <option value="<?= $div['id'] ?>" <?= ($div['id'] == $current_div_id) ? 'selected' : '' ?>><?= htmlspecialchars($div['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <div id="routeUsersContainer" class="checkbox-container custom-input <?= $is_locked ? 'bg-light' : '' ?>" style="min-height: 120px;">
                                <?php if ($is_locked && !empty($current_recipients)): ?>
                                    <span class="text-muted small">Recipients locked.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="block-group" class="<?= ($doc['route_type'] === 'group') ? '' : 'd-none' ?>">
                    <select class="form-select custom-input <?= $is_locked ? 'bg-light' : '' ?>" name="route_group" <?= $is_locked ? 'disabled' : '' ?>>
                        <option value="">Select Group...</option>
                        <?php foreach ($groups as $grp): ?>
                            <option value="<?= $grp['id'] ?>" <?= ($doc['route_type'] === 'group' && !empty($current_recipients) ? 'selected' : '') ?>><?= htmlspecialchars($grp['group_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="block-external" class="<?= in_array($doc['route_type'], ['within_dti', 'outside_dti']) ? '' : 'd-none' ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold">Office / Agency Name *</label>
                            <input type="text" name="ext_office" class="form-control custom-input <?= $is_locked ? 'bg-light' : '' ?>" placeholder="e.g. DENR Main Office" value="<?= htmlspecialchars($doc['address_name'] ?? '') ?>" <?= $is_locked ? 'readonly' : '' ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold">Contact Person *</label>
                            <?php
                                // Automatically erase the old 'System Generated' database glitch
                                $contact_person = $doc['sender'] ?? '';
                                if (strtolower(trim($contact_person)) === 'system generated') {
                                    $contact_person = '';
                                }
                            ?>
                            <input type="text" name="ext_name" class="form-control custom-input <?= $is_locked ? 'bg-light' : '' ?>" placeholder="e.g. Attn: Juan Dela Cruz" value="<?= htmlspecialchars($contact_person) ?>" <?= $is_locked ? 'readonly' : '' ?>>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4 border rounded p-4" style="background-color: #fff;">
                <label class="form-label modal-label"><i class="fa-solid fa-paperclip me-2 text-primary"></i> Attachments</label>

                <?php if(!empty($attachments)): ?>
                    <div class="mb-3 mt-2">
                        <?php foreach($attachments as $att): ?>
                            <div class="existing-file d-flex justify-content-between align-items-center bg-light" id="att-row-<?= $att['id'] ?>" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 10px;">
                                <div>
                                    <i class="fa-regular fa-file-pdf text-danger me-2"></i>
                                    <a href="<?= BASE_URL . $att['file_path'] ?>" target="_blank" class="text-dark fw-bold text-decoration-none" style="font-size: 0.9rem;"><?= htmlspecialchars(basename($att['file_path'])) ?></a>
                                </div>
                                <?php if (!$is_locked): ?>
                                    <button type="button" class="btn-remove-file p-0 m-0 text-danger bg-transparent border-0" onclick="queueFileRemoval(event, <?= $att['id'] ?>)" style="font-size: 1.2rem; line-height: 1;" title="Remove Attachment">&times;</button>
                                <?php endif; ?>
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
                            <input type="file" id="fileInput" name="document_files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                            <div id="fileQueueDisplay" class="mt-3 text-start w-100"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_locked): ?>
                <div class="d-flex justify-content-end gap-3 pt-4 border-top mt-5">
                    <button type="button" class="btn btn-danger px-4 fw-bold me-auto" style="border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#cancelEntryModal">
                    <i class="fa-solid fa-ban me-1"></i> Cancel Document
                    </button>
                    <button type="button" class="btn btn-secondary px-4 fw-bold" style="border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#cancelEditModal">Cancel</button>
                    <button type="button" class="btn btn-blue px-4 fw-bold" style="background-color: #1d4ed8; border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#saveConfirmModal">Save Changes</button>
                </div>
            <?php endif; ?>
        </form>
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
                <h5 class="fw-bold text-dark">Save Changes?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">Are you sure you want to apply these updates to the document record?</p>
                <div class="d-flex flex-column gap-2 mt-4">
                    <button type="button" id="confirmSaveBtn" class="btn btn-blue fw-bold w-100" style="background-color: #1d4ed8; color: #fff;">
                        Confirm Save
                    </button>
                    <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">
                        Review Edits
                    </button>
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

<script id="usersData" type="application/json"><?= json_encode($users_by_div) ?></script>
<script id="recipientsData" type="application/json"><?= json_encode($current_recipients) ?></script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>