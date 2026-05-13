<?php
// templates/ro/roIncoming.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RO') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Encoded Incoming Documents";
$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Session Messages
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// 1. FETCH REFERENCE DATA
$classifications = $docManager->getClassifications();
$document_types  = $docManager->getDocumentTypes();
$signatories     = $docManager->getSignatories();
$divisions       = $docManager->getDivisions();
$groups          = $docManager->getGroups();
$users_by_div    = $docManager->getUsersGroupedByDivision();

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

    // 3. Fetch the Paginated INCOMING Documents for the Table
    $total_records   = $docManager->getEncodedIncomingTotalCount($user_id);
    $total_pages     = ceil($total_records / $limit);

    // FIX: Using the correct Incoming functions and variable name
    $my_encoded_docs = $docManager->getEncodedIncomingPaginated($user_id, $limit, $offset);

} catch (Exception $e) {
    $error_msg = "Error loading documents: " . $e->getMessage();
    $my_encoded_docs = [];
}

// 3. ASSETS & CUSTOM STYLES
// Added a compact table style to reduce the wide gaps between columns
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/page.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/forms.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/uploader.css">
<style>
    /* Compact Table Styling */
    .data-table th, .data-table td {
        padding: 10px 12px !important;
    }
</style>
';

$extra_js = '
<script id="usersData" type="application/json">' . json_encode($users_by_div) . '</script>
<script src="' . BASE_URL . 'static/js/filters.js"></script>
<script src="' . BASE_URL . 'static/js/upload-queue.js"></script>
<script src="' . BASE_URL . 'static/js/create_document.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">

    <?php if ($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
        <button type="button" class="btn btn-new-document flex-shrink-0" data-bs-toggle="modal" data-bs-target="#newDocModal">
            <div class="icon-circle"><i class="fas fa-plus"></i></div>
            <span class="btn-text">New Document</span>
        </button>

        <div class="filter-bar d-flex flex-wrap align-items-center gap-2 justify-content-end">
            <select id="statusFilter" class="form-select custom-select shadow-sm" style="width: 140px;">
                <option value="">All Statuses</option>
                <option value="For Approval">For Approval</option>
                <option value="Approved">Approved</option>
            </select>

            <select id="classFilter" class="form-select custom-select shadow-sm" style="width: 150px;">
                <option value="">Classification</option>
                <?php foreach ($classifications as $class): ?>
                    <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="typeFilter" class="form-select custom-select shadow-sm" style="width: 160px;">
                <option value="">Document Type</option>
                <?php foreach ($document_types as $type): ?>
                    <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="d-flex align-items-center gap-2 border px-2 py-1 rounded bg-white shadow-sm" style="height: 38px;">
                <span class="text-muted small fw-bold">From:</span>
                <input type="date" id="startDate" class="form-control form-control-sm border-0 bg-transparent p-0" style="width: 110px;">
                <div class="vr mx-1" style="opacity: 0.2;"></div>
                <span class="text-muted small fw-bold">To:</span>
                <input type="date" id="endDate" class="form-control form-control-sm border-0 bg-transparent p-0" style="width: 110px;">
            </div>

            <div class="input-group search-container shadow-sm" style="width: 250px; border-radius: 6px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Search DTS No. or Subject...">
            </div>
        </div>
    </div>

    <div class="table-container p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">DTS NO.</th>
                        <th style="width: 12%;">STATUS</th>
                        <th style="width: 14%;">DATE & TIME CREATED</th>
                        <th style="width: 17%;">ORIGIN</th>
                        <th style="width: 16%;">ADDRESS</th>
                        <th style="width: 20%;">SUBJECT</th>
                        <th style="width: 9%;">SIGNATORY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_encoded_docs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fa-solid fa-inbox mb-3 opacity-25" style="font-size: 3rem;"></i><br>
                                <h6 class="fw-bold text-secondary">No Records Found</h6>
                                <p class="small mb-0">No active incoming documents found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($my_encoded_docs as $doc): ?>
                        <tr class="doc-row clickable-row" onclick="window.location.href='roEditDocu.php?id=<?= $doc['id'] ?>'">
                            <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>
                            <td>
                                <span class="status <?= strtolower(str_replace(' ', '-', $doc['status_category'])) ?>">
                                    <?= htmlspecialchars($doc['status_name']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-dark"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                                <div class="text-muted" style="font-size: 0.8rem;"><?= date('h:i A', strtotime($doc['created_at'])) ?></div>
                                <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                            </td>
                            <td class="search-target">
                                <div class="text-dark text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($doc['origin_name']) ?>"><?= htmlspecialchars($doc['origin_name']) ?></div>
                                <div class="text-muted small"><?= !empty($doc['sender']) ? htmlspecialchars(trim($doc['sender'])) : 'N/A' ?></div>
                            </td>
                            <td class="search-target">
                                <div class="text-dark text-wrap" style="word-break: break-word;">
                                    <?= htmlspecialchars($doc['destination_name'] ?? 'Internal Routing') ?>
                                </div>
                                <div class="text-muted small text-wrap" style="word-break: break-word;">
                                    <?= !empty($doc['receiver_name']) ? htmlspecialchars($doc['receiver_name']) : 'N/A' ?>
                                </div>
                            </td>
                            <td class="text-dark search-target text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($doc['subject']) ?>">
                                <?= htmlspecialchars($doc['subject']) ?>
                            </td>
                            <td class="small"><?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? 'None'))) ?></td>
                            <td class="d-none class-target"><?= htmlspecialchars($doc['class_name']) ?></td>
                            <td class="d-none type-target"><?= htmlspecialchars($doc['doc_type']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php include BASE_PATH . 'includes/page.php'; ?>
    </div>
</div>

<div class="modal" id="newDocModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-file-import me-2 text-success"></i> Encode Incoming Document</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <form method="POST" action="../../controllers/incoming.php" enctype="multipart/form-data">
                 <input type="hidden" name="action" value="create_document">

                    <div class="row g-4 mb-4">
                        <div class="col-md-4"><label class="modal-label">Classification *</label>
                            <select class="form-select custom-input" name="classification" required>
                                <option value="" selected disabled>Select...</option>
                                <?php foreach ($classifications as $item): ?><option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="modal-label">Document Type *</label>
                            <select class="form-select custom-input" name="document_type" required>
                                <option value="" selected disabled>Select...</option>
                                <?php foreach ($document_types as $type): ?><option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="modal-label">Due Date (Optional)</label><input type="date" name="due_date" class="form-control custom-input"></div>
                    </div>
                    <div class="row g-4 mb-4 mt-4 border p-3 rounded bg-light">
                        <div class="col-md-6"><label class="modal-label">Origin (Office/Agency) *</label>
                            <input list="officeOptions" name="origin_office" class="form-control custom-input border-warning" required autocomplete="off">
                            <datalist id="officeOptions">
                                <?php foreach($pdo->query("SELECT name FROM records_origin")->fetchAll(PDO::FETCH_COLUMN) as $o): ?><option value="<?= htmlspecialchars($o) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6"><label class="modal-label">Contact Person</label><input type="text" name="sender_name" class="form-control custom-input"></div>
                    </div>
                    <div class="mb-4"><label class="modal-label">Subject *</label><textarea name="subject" class="form-control custom-input" rows="2" required></textarea></div>
                    <div class="border rounded p-4 mb-4 bg-light">
                        <label class="fw-bold text-success mb-3"><i class="fa-solid fa-sitemap me-1"></i> Address (Internal Destination) *</label>
                        <select class="form-select custom-input mb-3 border-success" name="route_type" id="routeType" required>
                            <option value="" selected disabled>Select internal routing path...</option>
                            <option value="division">Internal Division / Specific Personnel</option>
                            <option value="group">Distribution Group</option>
                        </select>
                        <div id="block-division" class="routing-block d-none">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <select class="form-select custom-input" id="routeDivision" name="route_division">
                                        <option value="">1. Select Division...</option>
                                        <?php foreach ($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-7"><div id="routeUsersContainer" class="checkbox-container custom-input" style="min-height: 120px;"></div></div>
                            </div>
                        </div>
                        <div id="block-group" class="routing-block d-none">
                            <select class="form-select custom-input" name="route_group">
                                <option value="">Select Group...</option>
                                <?php foreach ($groups as $grp): ?><option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6"><label class="modal-label">Route to Signatory *</label>
                            <select class="form-select custom-input border-primary" name="signatory" required>
                                <option value="" selected disabled>Select RD/ARD...</option>
                                <?php foreach ($signatories as $sig): ?><option value="<?= $sig['id'] ?>"><?= htmlspecialchars($sig['first_name'] . ' ' . $sig['last_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="modal-label">Particulars</label><input type="text" name="particulars" class="form-control custom-input"></div>
                    </div>
                    <div class="mb-4">
                        <label class="modal-label">Attachments</label>
                        <div class="drag-drop-box mt-2" id="dropZone">
                            <input type="file" id="fileInput" name="document_files[]" multiple style="display:none">
                            <div id="fileQueueDisplay" class="w-100 text-center">
                                <i class="fa-solid fa-cloud-arrow-up display-6 text-primary"></i>
                                <div class="mt-3" id="dropZoneText"><b>Click to upload</b> or drag and drop</div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Create Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="cancelConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-triangle-exclamation text-warning mb-3 display-4"></i>
                <h5 class="fw-bold">Discard?</h5>
                <p class="text-muted small">Any unsaved information will be cleared.</p>
                <div class="d-flex flex-column gap-2 mt-4">
                    <button type="button" class="btn btn-warning fw-bold w-100" onclick="location.reload()">Yes, Discard</button>
                    <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">No, Keep Editing</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>