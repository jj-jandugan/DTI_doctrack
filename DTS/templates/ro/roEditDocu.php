<?php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RO') {
    header("Location: " . BASE_URL . "index.php"); exit;
}

$document_id = $_GET['id'] ?? null;
if (!$document_id) { header("Location: roIncoming.php"); exit; }

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Fetch Session Messages
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// 1. Fetch Existing Data
$doc = $docManager->getDocumentForEdit($document_id, $user_id);
if (!$doc) { header("Location: roIncoming.php"); exit; }

$attachments = $docManager->getDocumentAttachments($document_id);
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
$users_by_div = $docManager->getUsersGroupedByDivision();
$saved_origins = $pdo->query("SELECT name FROM records_origin ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

$page_title = "Edit " . ($doc['ctrl_no'] ?? $doc['dts_no']);

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
        window.uploader = new UploadQueue("dropZone", "fileInput", "fileQueueDisplay", "dropZoneText");
    });
</script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="mb-4">
        <div class="mb-4">
        <a href="roIncoming.php" id="backToQueue" class="text-decoration-none text-secondary fw-bold" style="font-size: 0.9rem;">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Incoming Queue
        </a>
</div>

    <div class="edit-container">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h3 class="fw-bold text-dark mb-0"><i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Record</h3>
            <div class="ctrl-badge"><?= htmlspecialchars($doc['ctrl_no'] ?? $doc['dts_no']) ?></div>
        </div>

        <?php if ($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

        <!-- Form points to the new Controller -->
        <form id="editDocForm" method="POST" action="../../controllers/ro_controller.php?id=<?= $document_id ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_document">

            <!-- Core Fields [HTML Kept verbatism] -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <label class="form-label modal-label">Classification</label>
                    <select class="form-select custom-input bg-light" disabled>
                        <?php foreach ($classifications as $item): ?>
                            <option value="<?= $item['id'] ?>" <?= ($item['id'] == $doc['classification_id']) ? 'selected' : '' ?>><?= htmlspecialchars($item['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label modal-label">Document Type *</label>
                    <select class="form-select custom-input" name="document_type" required>
                        <?php foreach ($document_types as $type): ?>
                            <option value="<?= $type['id'] ?>" <?= ($type['id'] == $doc['document_type_id']) ? 'selected' : '' ?>><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label modal-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control custom-input" value="<?= $doc['due_date'] ? date('Y-m-d', strtotime($doc['due_date'])) : '' ?>">
                </div>
            </div>

            <!-- Origin Info [HTML Kept verbatism] -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label class="form-label modal-label">Origin (Office/Agency) *</label>
                    <input list="officeOptions" name="origin_office" class="form-control custom-input" value="<?= htmlspecialchars($doc['origin_name']) ?>" required>
                    <datalist id="officeOptions">
                        <?php foreach($saved_origins as $org): ?><option value="<?= htmlspecialchars($org) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-6">
                    <label class="form-label modal-label">Sender Name</label>
                    <input type="text" name="sender_name" class="form-control custom-input" value="<?= htmlspecialchars($doc['sender'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label modal-label">Subject *</label>
                <textarea name="subject" class="form-control custom-input" rows="2" required><?= htmlspecialchars($doc['subject']) ?></textarea>
            </div>

            <!-- Routing [HTML Kept verbatism] -->
            <div class="border rounded p-4 mb-4" style="background-color: #f8fafc; border-color: #e2e8f0 !important;">
                <label class="form-label fw-bold text-success mb-3">Internal Destined Receiver *</label>
                <select class="form-select custom-input mb-3" name="route_type" id="routeType" required>
                    <option value="division" <?= ($doc['route_type'] === 'division') ? 'selected' : '' ?>>Division / Specific Personnel</option>
                    <option value="group" <?= ($doc['route_type'] === 'group') ? 'selected' : '' ?>>Distribution Group</option>
                </select>

                <div id="block-division" class="<?= ($doc['route_type'] === 'division') ? '' : 'd-none' ?>">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <select class="form-select custom-input" id="routeDivision" name="route_division">
                                <option value="">Select Division...</option>
                                <?php foreach ($divisions as $div): ?>
                                    <option value="<?= $div['id'] ?>" <?= ($div['id'] == $current_div_id) ? 'selected' : '' ?>><?= htmlspecialchars($div['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <div id="routeUsersContainer" class="checkbox-container custom-input" style="min-height: 120px;"></div>
                        </div>
                    </div>
                </div>

                <div id="block-group" class="<?= ($doc['route_type'] === 'group') ? '' : 'd-none' ?>">
                    <select class="form-select custom-input" name="route_group">
                        <option value="">Select Group...</option>
                        <?php foreach ($groups as $grp): ?>
                            <option value="<?= $grp['id'] ?>" <?= ($doc['route_type'] === 'group' && !empty($current_recipients) ? 'selected' : '') ?>><?= htmlspecialchars($grp['group_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Signatory [HTML Kept verbatism] -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label modal-label text-primary"><i class="fa-solid fa-signature me-1"></i> Route to Signatory *</label>
                    <select class="form-select custom-input" name="signatory" required>
                        <?php foreach ($signatories as $sig): ?>
                            <option value="<?= $sig['id'] ?>" <?= ($sig['id'] == $doc['signatory_id']) ? 'selected' : '' ?>><?= htmlspecialchars($sig['first_name'] . ' ' . $sig['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Attachments [HTML Kept verbatism] -->
            <div class="mb-4">
                <label class="form-label modal-label">Attachments</label>
                <?php if(!empty($attachments)): ?>
                    <div class="mb-3">
                        <?php foreach($attachments as $att): ?>
                            <div class="existing-file d-flex justify-content-between align-items-center bg-white" id="att-row-<?= $att['id'] ?>" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 10px;">
                                <span class="text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars(basename($att['file_path'])) ?></span>
                                <button type="button" class="btn-remove-file p-0 m-0 text-danger bg-transparent border-0" onclick="queueFileRemoval(event, <?= $att['id'] ?>)" style="font-size: 1.2rem; line-height: 1;">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="drag-drop-box mt-2" id="dropZone">
                    <div class="upload-content text-center w-100">
                        <i class="fa-solid fa-cloud-arrow-up mb-2" style="font-size: 2rem; color: #263D81;"></i>
                        <span id="dropZoneText" class="d-block text-muted mt-1">
                            <span class="fw-bold text-dark">Click to upload</span> or drag and drop<br>
                            <small>Maximum size: 10MB</small>
                        </span>
                        <input type="file" id="fileInput" name="document_files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                        <div id="fileQueueDisplay" class="mt-3 text-start w-100"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3 pt-4 border-top mt-5">
                <button type="button" class="btn btn-secondary px-4 fw-bold" style="border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#cancelEditModal">Cancel</button>
                <button type="submit" id="submitBtn" class="btn btn-blue px-4 fw-bold" style="background-color: #1d4ed8; border-radius: 6px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- All Modal HTML remains exactly as provided -->
<div class="modal fade" id="cancelEditModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <!-- ... Cancel Modal Content ... -->
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #eab308;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-triangle-exclamation text-warning mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Discard Changes?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">Any unsaved data will be lost. Are you sure you want to discard your edits?</p>
                <div class="d-flex flex-column gap-2 mt-4">
                    <button type="button" class="btn btn-warning fw-bold text-dark w-100" onclick="window.location.href='roIncoming.php'">Yes, Discard</button>
                    <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">No, Keep Editing</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Save Confirmation Modal -->
<div class="modal fade" id="saveConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #1d4ed8;">
            <div class="modal-body text-center p-4">
                <!-- Blue Save Icon to match the Discard Modal style -->
                <i class="fa-solid fa-floppy-disk text-primary mb-3" style="font-size: 3rem;"></i>

                <h5 class="fw-bold text-dark">Save Changes?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">Are you sure you want to apply these updates to the document record?</p>

                <div class="d-flex flex-column gap-2 mt-4">
                    <!-- This button triggers the form submission in edit_document.js -->
                    <button type="button" id="confirmSaveBtn" class="btn btn-blue fw-bold w-100" style="background-color: #1d4ed8; color: #fff;">
                        Confirm Save
                    </button>

                    <!-- Closes the modal to let the user review the form again -->
                    <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">
                        Review Edits
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Data Transfer -->
<script id="usersData" type="application/json"><?= json_encode($users_by_div) ?></script>
<script id="recipientsData" type="application/json"><?= json_encode($current_recipients) ?></script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>