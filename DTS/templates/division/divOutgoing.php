<?php
// templates/division/divOutgoing.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

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
// FETCH ALL DATA FOR VIEW VIA OOP
// ==========================================
$docManager = new DocumentManager($pdo);

$classifications = $docManager->getClassifications();
$document_types  = $docManager->getDocumentTypes();
$signatories     = $docManager->getSignatories();
$divisions       = $docManager->getDivisions();
$groups          = $docManager->getGroups();
$users_by_div    = $docManager->getUsersGroupedByDivision();
$outgoing_docs   = $docManager->getActiveOutgoing($user_id);

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

$usersByDivJson = json_encode($users_by_div);
$extra_js = '
<script id="usersData" type="application/json">' . $usersByDivJson . '</script>
<script src="' . BASE_URL . 'static/js/filters.js"></script>
<script src="' . BASE_URL . 'static/js/upload-queue.js"></script>
<script src="' . BASE_URL . 'static/js/create_document.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <!-- PAGE NAME AND DESCRIPTION -->
    <div class="mb-4">
        <h2 class="text-dark fw-bold mb-0">Outgoing Documents</h2>
        <p class="text-secondary">Draft, manage, and route documents originating from your division for approval or release.</p>
    </div>

    <!-- Success and Error Messages -->
    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <button type="button" class="btn btn-new-document flex-shrink-0" data-bs-toggle="modal" data-bs-target="#newDocModal">
            <div class="icon-circle"><i class="fas fa-plus"></i></div>
            <span class="btn-text">New Document</span>
        </button>

        <div class="filter-bar justify-content-end">
            <select id="classFilter" class="form-select custom-select">
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

            <div class="input-group ms-3 search-container" style="width: 250px;">
                <span class="input-group-text search-icon-group"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="searchInput" class="form-control custom-search-input" placeholder="Search Control No. or Subject...">
            </div>
        </div>
    </div>

    <div class="table-container p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>CONTROL NO.</th>
                        <th>STATUS</th>
                        <th>DEADLINE</th>
                        <th>DATE CREATED</th>
                        <th>SUBJECT</th>
                        <th>DESTINATION</th>
                        <th>SIGNATORY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($outgoing_docs)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fa-solid fa-file-export mb-3 opacity-25" style="font-size: 2.5rem;"></i><br>
                                <h6 class="fw-bold text-secondary">No Active Outgoing Documents</h6>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($outgoing_docs as $doc): ?>
                            <tr class="doc-row clickable-row" onclick="window.location.href='divEditDocu.php?id=<?= $doc['id'] ?>'" style="cursor: pointer;">
                                <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                <td><span class="status <?= strtolower($doc['status_category']) ?>"><?= htmlspecialchars($doc['status_name']) ?></span></td>
                                <td>
                                    <?php if ($doc['due_date']): ?>
                                        <span class="text-dark small"><i class="fa-regular fa-calendar-xmark me-1"></i> <?= date('M d, Y', strtotime($doc['due_date'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-dark fw-bold"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                                    <div class="text-muted" style="font-size: 0.8rem;"><?= date('h:i A', strtotime($doc['created_at'])) ?></div>
                                    <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                                </td>
                                <td class="fw-bold text-dark search-target"><?= htmlspecialchars($doc['subject']) ?></td>
                                <td class="text-truncate small" style="max-width: 200px;" title="<?= htmlspecialchars($doc['sender'] ?? '') ?>">
                                    <?= htmlspecialchars($doc['sender'] ?? '---') ?>
                                </td>
                                <td class="small"><?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? '---'))) ?></td>
                                <td class="d-none class-target"><?= htmlspecialchars($doc['class_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- NEW DOCUMENT MODAL -->
<div class="modal fade" id="newDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" style="color: #1e293b; font-size: 1.25rem;">
                    <span style="color: #263D81;"><i class="fa-solid fa-file-circle-plus me-2"></i></span>
                    Encode New Outgoing
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body px-4 py-4">
                <form method="POST" action="../../controllers/Outgoing.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_document">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="modal-label">Classification *</label>
                            <select class="form-select custom-input" name="classification" required>
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

                    <!-- ROUTING SECTION -->
                    <div class="form-section bg-light border p-3 rounded mb-3">
                        <label class="form-label fw-bold text-primary mb-3 small"><i class="fa-solid fa-route me-1"></i> Destination & Routing</label>
                        <select class="form-select custom-input mb-3" name="route_type" id="routeType" required>
                            <option value="" selected disabled>Select Routing Path...</option>
                            <option value="division">Personnel (Internal)</option>
                            <option value="group">Group (Distribution)</option>
                            <option value="within_dti">Within DTI (Other Branch)</option>
                            <option value="outside_dti">Outside Agency (External)</option>
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
                            <select class="form-select custom-input" name="route_group">
                                <option value="">Select Distribution Group...</option>
                                <?php foreach ($groups as $grp): ?>
                                    <option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="block-external" class="routing-block d-none">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" class="form-control custom-input" name="ext_office" placeholder="Office / Agency Name">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control custom-input" name="ext_name" placeholder="Contact Person">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="modal-label">Signatory (Optional)</label>
                            <select class="form-select custom-input" name="signatory">
                                <option value="" selected>None...</option>
                                <?php foreach ($signatories as $sig): ?>
                                    <option value="<?= $sig['id'] ?>"><?= htmlspecialchars($sig['first_name'] . ' ' . $sig['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="modal-label">Particulars</label>
                            <input type="text" name="particulars" class="form-control custom-input" placeholder="Additional details">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="modal-label">Attachment</label>
                        <div class="drag-drop-box" id="dropZone">
                            <div class="upload-content text-center w-100">
                                <i class="fa-solid fa-cloud-arrow-up mb-2 text-primary" style="font-size: 2rem;"></i>
                                <span id="dropZoneText" class="d-block text-muted small">
                                    <b>Click to upload</b> or drag and drop
                                </span>
                            </div>
                            <input type="file" id="fileInput" name="document_file" hidden>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Create & Route</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>