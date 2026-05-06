<?php
// templates/division/divEditDocu.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check: Only 'Division' role can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// Redirect if no document ID is provided
if (!isset($_GET['id'])) {
    header("Location: divOutgoing.php");
    exit;
}

$doc_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$page_title = "Manage Document";

// Grab messages from the controller, then clear them
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// ==========================================
// FETCH ALL DATA VIA OOP
// ==========================================
$docManager = new DocumentManager($pdo);

$doc = $docManager->getDocumentById($doc_id, $user_id);
if (!$doc) {
    header("Location: divOutgoing.php");
    exit;
}

// Lock checks
$is_locked = in_array(strtoupper($doc['status_category']), ['APPROVED', 'CLOSED']);
$current_recipients = $docManager->getDocumentRecipients($doc_id);

// Dropdown Data
$classifications = $docManager->getClassifications();
$document_types  = $docManager->getDocumentTypes();
$divisions       = $docManager->getDivisions();
$groups          = $docManager->getGroups();
$users_by_div    = $docManager->getUsersGroupedByDivision();

// 1. LINK MODULAR CSS
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/forms.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/edit.css">
';

// 2. LINK MODULAR JS
$usersByDivJson = json_encode($users_by_div);
$extra_js = '
<script id="usersData" type="application/json">' . $usersByDivJson . '</script>
<script src="' . BASE_URL . 'static/js/edit_document.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="divOutgoing.php" class="text-decoration-none text-muted mb-2 d-inline-block"><i class="fa-solid fa-arrow-left me-1"></i> Back to Outgoing</a>
            <h2 class="text-dark fw-bold mb-0">Manage Document</h2>
            <p class="text-secondary">Control No: <span class="text-primary fw-bold"><?= htmlspecialchars($doc['dts_no']) ?></span></p>
        </div>
        <div>
            <span class="status-badge <?= strtolower($doc['status_category']) ?>"><?= htmlspecialchars($doc['status_name']) ?></span>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-7">
            <form method="POST" action="../../controllers/editDocument.php?id=<?= $doc_id ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_document">
                <input type="hidden" name="document_id" value="<?= $doc_id ?>">

                <div class="form-section">
                    <h5 class="section-title"><i class="fa-solid fa-file-lines me-2"></i> Core Details</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="modal-label">Classification *</label>
                            <select class="form-select custom-input" name="classification" required <?= $is_locked ? 'disabled' : '' ?>>
                                <?php foreach ($classifications as $item): ?>
                                    <option value="<?= $item['id'] ?>" <?= $doc['classification_id'] == $item['id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="modal-label">Document Type *</label>
                            <select class="form-select custom-input" name="document_type" required <?= $is_locked ? 'disabled' : '' ?>>
                                <?php foreach ($document_types as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= $doc['document_type_id'] == $type['id'] ? 'selected' : '' ?>><?= htmlspecialchars($type['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="modal-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control custom-input" value="<?= htmlspecialchars($doc['due_date'] ?? '') ?>" <?= $is_locked ? 'readonly' : '' ?>>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="modal-label">Subject *</label>
                        <textarea name="subject" class="form-control custom-input" rows="2" required <?= $is_locked ? 'readonly' : '' ?>><?= htmlspecialchars($doc['subject']) ?></textarea>
                    </div>
                </div>

                <?php if (!$is_locked): ?>
                    <div class="d-flex justify-content-end mb-4">
                        <button type="submit" class="btn btn-blue px-5 fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i> Save Details</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="col-md-5">
            <!-- Routing Section -->
            <div class="form-section">
                <h5 class="section-title"><i class="fa-solid fa-share-nodes me-2"></i> Route Document</h5>
                <?php if (!$is_locked): ?>
                    <form method="POST" action="../../controllers/editDocument.php?id=<?= $doc_id ?>" class="mb-4 pb-4 border-bottom">
                        <input type="hidden" name="action" value="route_document">
                        <input type="hidden" name="document_id" value="<?= $doc_id ?>">

                        <label class="modal-label mb-2">ROUTE TO NEW DESTINATION</label>
                        <select class="form-select custom-input mb-3" name="route_type" id="routeType" required>
                            <option value="" selected disabled>Select routing path...</option>
                            <option value="division">Personnel</option>
                            <option value="group">Group</option>
                        </select>

                        <div id="block-division" class="routing-block d-none mb-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <select class="form-select custom-input" id="routeDivision" name="route_division">
                                        <option value="">Division...</option>
                                        <?php foreach ($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-select custom-input" id="routeUser" name="route_user">
                                        <option value="">Select User...</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="block-group" class="routing-block d-none mb-3">
                            <select class="form-select custom-input" name="route_group">
                                <option value="">Select Group...</option>
                                <?php foreach ($groups as $grp): ?><option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-blue w-100">Route Document</button>
                    </form>
                <?php endif; ?>

                <h6 class="text-muted fw-bold mb-3 small">RECIPIENTS</h6>
                <div class="recipient-list">
                    <?php if (empty($current_recipients)): ?>
                        <div class="text-muted small fst-italic">No active recipients assigned.</div>
                    <?php else: ?>
                        <?php foreach($current_recipients as $rec): ?>
                            <div class="recipient-item d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                <div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($rec['role']) ?></div>
                                </div>
                                <span class="badge <?= $rec['has_received'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $rec['has_received'] ? 'Received' : 'Pending' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>