<?php
// templates/division/divOutgoing.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check: Only 'Division' role can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Outgoing Documents";
$user_id = $_SESSION['user_id'];

// Grab messages from the universal controller, then clear them
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ==========================================
// FETCH ALL DATA VIA OOP WITH PAGINATION
// ==========================================
$docManager = new DocumentManager($pdo);

// 1. Pagination Math
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // 2. Fetch Reference Data for the Modal
    $classifications = $docManager->getClassifications();
    $document_types  = $docManager->getDocumentTypes();
    $signatories     = $docManager->getSignatories();
    $divisions       = $docManager->getDivisions();
    $groups          = $docManager->getGroups();
    $users_by_div    = $docManager->getUsersGroupedByDivision();
    $dti_branches    = $docManager->getDtiBranches();

    // NEW: Fetch Records Officers for the external routing field
    $records_officers = $pdo->query("SELECT u.id, u.first_name, u.last_name FROM auth_user u JOIN records_userprofile p ON u.id = p.user_id WHERE p.role = 'RO' AND u.is_active = 1")->fetchAll();

    // 3. Fetch the Paginated Outgoing Documents for the Table
    $total_records   = $docManager->getActiveOutgoingTotalCount($user_id);
    $total_pages     = ceil($total_records / $limit);
    $outgoing_docs   = $docManager->getActiveOutgoingPaginated($user_id, $limit, $offset);

    // FETCH RECIPIENT NAMES MAPPED TO DOCUMENTS
    $all_recipients  = $docManager->getRecipientsByDocument();

} catch (Exception $e) {
    $error_msg = "Error loading documents: " . $e->getMessage();
    $outgoing_docs = [];
}

// ==========================================
// ASSETS AND MODULAR LINKS
// ==========================================
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/forms.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/uploader.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
';

// Convert user data to JSON so our external JS file can use it for the checkboxes
$usersByDivJson = json_encode($users_by_div);

// Link the external Javascript files
$extra_js = '
<script id="usersData" type="application/json">' . $usersByDivJson . '</script>
<script src="' . BASE_URL . 'static/js/filters.js"></script>
<script src="' . BASE_URL . 'static/js/upload-queue.js"></script>
<script src="' . BASE_URL . 'static/js/create_document.js"></script>
<script>
    // Logic to show/hide Receiving Officer field specifically for this page
    document.addEventListener("DOMContentLoaded", function() {
        const routeType = document.getElementById("routeType");
        const roField = document.getElementById("roFieldContainer");
        const roSelect = document.getElementById("receivingOfficerSelect");

        if(routeType) {
            routeType.addEventListener("change", function() {
                if (this.value === "within_dti" || this.value === "outside_dti") {
                    roField.classList.remove("d-none");
                    if(roSelect) roSelect.required = true;
                } else {
                    roField.classList.add("d-none");
                    if(roSelect) {
                        roSelect.required = false;
                        roSelect.value = "";
                    }
                }
            });
        }
    });
</script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
        <button type="button" class="btn btn-new-document flex-shrink-0" data-bs-toggle="modal" data-bs-target="#newDocModal">
            <div class="icon-circle"><i class="fas fa-plus"></i></div>
            <span class="btn-text">New Document</span>
        </button>

        <div class="filter-bar justify-content-end d-flex flex-wrap gap-2 align-items-center">
            <select id="statusFilter" class="form-select custom-select" style="width: auto;">
                <option value="">All Statuses</option>
                <option value="For Approval">For Approval</option>
                <option value="Approved">Approved</option>
            </select>

            <select id="classFilter" class="form-select custom-select" style="width: auto;">
                <option value="">Classification</option>
                <?php foreach ($classifications as $class): ?>
                    <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="d-flex align-items-center gap-2 border px-2 py-1 rounded bg-white">
                <span class="text-muted small">From:</span>
                <input type="date" id="startDate" class="form-control form-control-sm border-0 text-secondary" style="width: 110px;">
                <span class="text-muted small">To:</span>
                <input type="date" id="endDate" class="form-control form-control-sm border-0 text-secondary" style="width: 110px;">
            </div>

            <div class="input-group search-container" style="width: 250px;">
                <span class="input-group-text search-icon-group"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="searchInput" class="form-control custom-search-input" placeholder="Search Control No. or Subject...">
            </div>
        </div>
    </div>

     <div class="table-container pb-4">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>DTS NO.</th>
                        <th>STATUS</th>
                        <th>DEADLINE</th>
                        <th>DATE CREATED</th>
                        <th>SUBJECT</th>
                        <th>ADDRESS</th>
                        <th>SIGNATORY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($outgoing_docs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5"><span class="text-secondary">No Active Outgoing Documents</span></td></tr>
                    <?php else: ?>
                        <?php foreach ($outgoing_docs as $doc): ?>
                            <tr class="doc-row clickable-row" onclick="window.location.href='divEditDocu.php?id=<?= $doc['id'] ?>'" style="cursor: pointer;">
                                <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                <td><span class="status <?= strtolower($doc['status_category']) ?> status-target"><?= htmlspecialchars($doc['status_name']) ?></span></td>
                                <td class="small"><?= $doc['due_date'] ? date('M d, Y', strtotime($doc['due_date'])) : 'None' ?></td>
                                <td class="small"><?= date('M d, Y', strtotime($doc['created_at'])) ?></td>
                                <td class="text-dark search-target"><?= htmlspecialchars($doc['subject']) ?></td>

                                <td class="small search-target" style="max-width: 200px;">
                                    <div class="text-truncate fw-bold text-dark" title="<?= htmlspecialchars($doc['address_name'] ?? '') ?>">
                                        <?= htmlspecialchars($doc['address_name'] ?? '---') ?>
                                    </div>
                                    <div class="text-truncate text-muted" style="font-size: 0.75rem;">
                                        <?php
                                            $receivers = "";
                                            // 1. Check for External Names first
                                            if (!empty($doc['external_names'])) {
                                                $receivers = $doc['external_names'];
                                            }
                                            // 2. Check for Single External Contact in the 'sender' column
                                            elseif (in_array($doc['route_type'], ['outside_dti', 'within_dti']) && !empty($doc['sender'])) {
                                                $receivers = $doc['sender'];
                                            }
                                            // 3. Fallback to Internal Recipients
                                            elseif (isset($all_recipients[$doc['id']]) && !empty($all_recipients[$doc['id']])) {
                                                $clean_names = array_map(function($person) { return explode(' (', $person)[0]; }, $all_recipients[$doc['id']]);
                                                $receivers = implode(', ', $clean_names);
                                            }
                                            echo htmlspecialchars($receivers);
                                        ?>
                                    </div>
                                </td>

                                <td class="small search-target"><?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? '---'))) ?></td>
                                <td class="d-none class-target"><?= htmlspecialchars($doc['class_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php include BASE_PATH . 'includes/page.php'; ?>
    </div>
</div>


<div class="modal fade" id="newDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" style="color: #1e293b; font-size: 1.25rem;">
                    <span style="color: #263D81;"><i class="fa-solid fa-file-circle-plus me-2"></i></span>
                    Encode New Outgoing
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-4 py-4">
                <form method="POST" action="../../controllers/outgoing.php" enctype="multipart/form-data" id="createDocumentForm">
                <input type="hidden" name="action" value="create_document">
                <input type="hidden" name="hidden_classification" id="hidden_classification" value="">

                <input type="hidden" id="docCreatorId" value="<?= $_SESSION['user_id'] ?>">

                <div class="form-section bg-light border p-3 rounded mb-3">
                        <label class="form-label fw-bold text-primary mb-3 small"><i class="fa-solid fa-map-location-dot me-1"></i> Address Information (Destination) *</label>
                        <select class="form-select custom-input mb-3" name="route_type" id="routeType" required>
                            <option value="" selected disabled>Select Destination Type...</option>
                            <option value="division">Division / Personnel (Internal)</option>
                            <option value="group">Distribution Group</option>
                            <option value="within_dti">Within DTI (Other Branches)</option>
                            <option value="outside_dti">Outside Offices / External Agency</option>
                        </select>

                        <div id="block-division" class="routing-block d-none">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <select class="form-select custom-input" id="routeDivision" name="route_division">
                                        <option value="">1. Select Division...</option>
                                        <?php foreach ($divisions as $div): ?>
                                            <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div id="routeUsersContainer" class="checkbox-container">
                                        <span class="text-muted small">Select division first...</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="block-group" class="routing-block d-none">
                            <select class="form-select custom-input" name="route_group" id="route_group">
                                <option value="">Select Distribution Group...</option>
                                <?php foreach ($groups as $grp): ?>
                                    <option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="block-dtibranch" class="routing-block d-none">
                            <div id="dti-recipient-container">
                                <div class="dti-recipient-row bg-white border rounded p-3 mb-2 position-relative shadow-sm">
                                    <div class="row g-2">
                                        <div class="col-md-5">
                                            <select class="form-select custom-input dti-branch-input" name="dti_branch[]">
                                                <option value="">Select DTI Branch...</option>
                                                <?php foreach ($dti_branches as $branch): ?>
                                                    <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control custom-input dti-contact-input" name="dti_contact[]" placeholder="Contact Person">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control custom-input" name="dti_notes[]" placeholder="Notes (Optional)">
                                        </div>
                                        <div class="col-md-1 d-flex align-items-center justify-content-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-recipient-btn d-none" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold mt-1" onclick="addRecipientRow('dti-recipient-container')"><i class="fa-solid fa-plus me-1"></i> Add Another Recipient</button>
                        </div>

                        <div id="block-external" class="routing-block d-none">
                            <div id="ext-recipient-container">
                                <div class="ext-recipient-row bg-white border rounded p-3 mb-2 position-relative shadow-sm">
                                    <div class="row g-2">
                                        <div class="col-md-5">
                                            <input type="text" class="form-control custom-input ext-office-input" name="ext_office[]" placeholder="Office / Agency Name">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control custom-input ext-contact-input" name="out_ext_name[]" placeholder="Contact Person (Optional)">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control custom-input" name="out_notes[]" placeholder="Notes (Optional)">
                                        </div>
                                        <div class="col-md-1 d-flex align-items-center justify-content-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-recipient-btn d-none" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold mt-1" onclick="addRecipientRow('ext-recipient-container')"><i class="fa-solid fa-plus me-1"></i> Add Another Recipient</button>
                        </div>
                    </div>

                    <div id="roFieldContainer" class="mb-4 d-none p-3 border rounded" style="background-color: #f0fdf4; border-color: #bcf0da !important;">
                        <label class="modal-label text-success fw-bold"><i class="fa-solid fa-user-tie me-1"></i> Route to Receiving Officer *</label>
                        <select class="form-select custom-input border-success" name="receiving_officer" id="receivingOfficerSelect">
                            <option value="" selected disabled>Select RO to receive this record...</option>
                            <?php foreach ($records_officers as $ro): ?>
                                <option value="<?= $ro['id'] ?>"><?= htmlspecialchars($ro['first_name'] . ' ' . $ro['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted mt-1 d-block">For external docs, the selected Records Officer will handle the final dispatch.</small>
                    </div>

                    <div class="row g-3 mb-3 mt-1">
                        <div class="col-md-4">
                            <label class="modal-label">Classification *</label>
                            <select class="form-select custom-input" name="classification" id="classification" required>
                                <option value="" selected disabled>Select...</option>
                                <?php foreach ($classifications as $item): ?>
                                    <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="modal-label">Document Type *</label>
                            <select class="form-select custom-input" name="document_type" required>
                                <option value="" selected disabled>Select...</option>
                                <?php foreach ($document_types as $type): ?>
                                    <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="modal-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control custom-input">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="modal-label">Subject *</label>
                        <textarea name="subject" class="form-control custom-input" rows="2" placeholder="Enter document subject..." required></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="modal-label">Signatory (Optional)</label>
                            <select class="form-select custom-input border-primary" name="signatory">
                                <option value="" selected>None...</option>
                                <?php foreach ($signatories as $sig): ?>
                                    <option value="<?= $sig['id'] ?>"><?= htmlspecialchars($sig['first_name'] . ' ' . $sig['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="modal-label">Particulars (Optional)</label>
                            <input type="text" name="particulars" class="form-control custom-input" placeholder="Additional details">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="modal-label">Attachments</label>

                        <div class="drag-drop-box" id="dropZone" style="cursor: pointer; border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px;">
                            <div class="upload-content text-center w-100">
                                <i class="fa-solid fa-cloud-arrow-up mb-2 text-primary" style="font-size: 2rem;"></i>
                                <span id="dropZoneText" class="d-block text-muted mt-1">
                                    <span class="fw-bold text-dark">Click to upload</span> or drag and drop<br>
                                    <small>Maximum size: 10MB</small>
                                </span>
                                <input type="file" id="fileInput" name="document_files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="display: none;">
                                <div id="fileQueueDisplay" class="mt-3 text-start w-100"></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn btn-cancel" data-bs-toggle="modal" data-bs-target="#cancelConfirmModal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4" id="btnFakeSubmit">Create & Route</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #eab308;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-triangle-exclamation text-warning mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Discard progress?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">Any information entered will be lost. Are you sure?</p>

                <div class="d-flex flex-column gap-2 mt-4">
                    <button type="button" class="btn btn-warning fw-bold text-dark w-100" onclick="window.location.reload()">
                        Yes, Discard
                    </button>
                    <button type="button" class="btn btn-light w-100 border" data-bs-target="#newDocModal" data-bs-toggle="modal">
                        No, Keep Editing
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>