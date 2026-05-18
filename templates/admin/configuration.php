<?php
// templates/admin/configuration.php
require_once '../../classes/database.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "System Configurations";

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<style>
    .nav-tabs .nav-link { color: #64748b; font-weight: 600; border: none; border-bottom: 3px solid transparent; padding: 12px 24px; margin-bottom: -1px; cursor: pointer; }
    .nav-tabs .nav-link:hover { border-color: transparent; color: #263D81; }
    .nav-tabs .nav-link.active { color: #263D81; background-color: transparent; border-color: transparent; border-bottom: 3px solid #263D81; }
    .nav-tabs { border-bottom: 1px solid #e2e8f0; margin-bottom: 24px; }
</style>
';

$success_msg = '';
$error_msg = '';
$active_tab = 'classifications'; // Default tab

// ==========================================
// 1. HANDLE FORM SUBMISSIONS (ADD, EDIT, DELETE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if (isset($_POST['active_tab'])) {
        $active_tab = $_POST['active_tab']; // Remember which tab we are on!
    }

    try {
        // --- ADD ACTIONS ---
        if ($action === 'add_classification' && !empty(trim($_POST['name']))) {
            $stmt = $pdo->prepare("INSERT INTO records_classification (name) VALUES (?)");
            $stmt->execute([trim($_POST['name'])]);
            $success_msg = "Classification added successfully!";
        }
        elseif ($action === 'add_type' && !empty(trim($_POST['name']))) {
            $stmt = $pdo->prepare("INSERT INTO records_documenttype (name) VALUES (?)");
            $stmt->execute([trim($_POST['name'])]);
            $success_msg = "Document Type added successfully!";
        }
        elseif ($action === 'add_division' && !empty(trim($_POST['name'])) && !empty(trim($_POST['abbreviation']))) {
            $stmt = $pdo->prepare("INSERT INTO records_division (name, abbreviation) VALUES (?, ?)");
            $stmt->execute([trim($_POST['name']), trim($_POST['abbreviation'])]);
            $success_msg = "Division added successfully!";
        }
        elseif ($action === 'add_status' && !empty(trim($_POST['name'])) && !empty(trim($_POST['category']))) {
            $stmt = $pdo->prepare("INSERT INTO records_status (name, category) VALUES (?, ?)");
            $stmt->execute([trim($_POST['name']), trim($_POST['category'])]);
            $success_msg = "Status added successfully!";
        }
        elseif ($action === 'add_dtibranch' && !empty(trim($_POST['name']))) {
            $stmt = $pdo->prepare("INSERT INTO records_dtibranch (name) VALUES (?)");
            $stmt->execute([trim($_POST['name'])]);
            $success_msg = "DTI Branch added successfully!";
        }

        // --- EDIT ACTIONS ---
        elseif ($action === 'edit_classification') {
            $stmt = $pdo->prepare("UPDATE records_classification SET name = ? WHERE id = ?");
            $stmt->execute([trim($_POST['name']), $_POST['id']]);
            $success_msg = "Classification updated successfully!";
        }
        elseif ($action === 'edit_type') {
            $stmt = $pdo->prepare("UPDATE records_documenttype SET name = ? WHERE id = ?");
            $stmt->execute([trim($_POST['name']), $_POST['id']]);
            $success_msg = "Document Type updated successfully!";
        }
        elseif ($action === 'edit_division') {
            $stmt = $pdo->prepare("UPDATE records_division SET name = ?, abbreviation = ? WHERE id = ?");
            $stmt->execute([trim($_POST['name']), trim($_POST['abbreviation']), $_POST['id']]);
            $success_msg = "Division updated successfully!";
        }
        elseif ($action === 'edit_status') {
            $stmt = $pdo->prepare("UPDATE records_status SET name = ?, category = ? WHERE id = ?");
            $stmt->execute([trim($_POST['name']), trim($_POST['category']), $_POST['id']]);
            $success_msg = "Status updated successfully!";
        }
        elseif ($action === 'edit_dtibranch') {
            $stmt = $pdo->prepare("UPDATE records_dtibranch SET name = ? WHERE id = ?");
            $stmt->execute([trim($_POST['name']), $_POST['id']]);
            $success_msg = "DTI Branch updated successfully!";
        }

        // --- DELETE ACTIONS ---
        elseif (str_starts_with($action, 'delete_')) {
            $id = $_POST['id'];
            if ($action === 'delete_classification') $table = 'records_classification';
            if ($action === 'delete_type') $table = 'records_documenttype';
            if ($action === 'delete_division') $table = 'records_division';
            if ($action === 'delete_status') $table = 'records_status';
            if ($action === 'delete_dtibranch') $table = 'records_dtibranch';

            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $success_msg = "Item deleted successfully!";
        }
    } catch (PDOException $e) {
        // If they try to delete something that is already being used in a document
        if ($e->getCode() == 23000) {
            $error_msg = "Cannot delete this item because it is currently assigned to a user or document.";
        } else {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// ==========================================
// 2. FETCH ALL REFERENCE DATA
// ==========================================
$classifications = $pdo->query("SELECT * FROM records_classification ORDER BY id DESC")->fetchAll();
$doc_types       = $pdo->query("SELECT * FROM records_documenttype ORDER BY id DESC")->fetchAll();
$divisions       = $pdo->query("SELECT * FROM records_division ORDER BY name ASC")->fetchAll();
$statuses        = $pdo->query("SELECT * FROM records_status ORDER BY category ASC, name ASC")->fetchAll();
$dti_branches    = $pdo->query("SELECT * FROM records_dtibranch ORDER BY name ASC")->fetchAll();

require_once BASE_PATH . 'includes/header.php';
?>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs" id="configTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?= $active_tab == 'classifications' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#classifications">Classifications</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $active_tab == 'doctypes' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#doctypes">Document Types</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $active_tab == 'dtibranches' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#dtibranches">DTI Branches</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $active_tab == 'divisions' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#divisions">Divisions</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $active_tab == 'statuses' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#statuses">Statuses</button>
        </li>
    </ul>

    <div class="tab-content">

        <div class="tab-pane fade <?= $active_tab == 'classifications' ? 'show active' : '' ?>" id="classifications">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark m-0">Document Classifications</h5>
                <form method="POST" action="configuration.php" class="d-flex gap-2">
                    <input type="hidden" name="action" value="add_classification">
                    <input type="hidden" name="active_tab" value="classifications">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="New Classification..." required style="width: 250px;">
                    <button type="submit" class="btn btn-blue btn-sm"><i class="fa-solid fa-plus me-1"></i> Add</button>
                </form>
            </div>

            <div class="table-container p-0">
                <table class="data-table">
                    <thead><tr><th width="10%">ID</th><th>CLASSIFICATION NAME</th><th width="15%">ACTIONS</th></tr></thead>
                    <tbody>
                        <?php foreach ($classifications as $class): ?>
                        <tr>
                            <td>#<?= $class['id'] ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($class['name']) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editClassModal" data-id="<?= $class['id'] ?>" data-name="<?= htmlspecialchars($class['name']) ?>"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?= $class['id'] ?>" data-name="<?= htmlspecialchars($class['name']) ?>" data-action="delete_classification" data-tab="classifications"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade <?= $active_tab == 'doctypes' ? 'show active' : '' ?>" id="doctypes">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark m-0">Document Types</h5>
                <form method="POST" action="configuration.php" class="d-flex gap-2">
                    <input type="hidden" name="action" value="add_type">
                    <input type="hidden" name="active_tab" value="doctypes">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="New Document Type..." required style="width: 250px;">
                    <button type="submit" class="btn btn-blue btn-sm"><i class="fa-solid fa-plus me-1"></i> Add</button>
                </form>
            </div>

            <div class="table-container p-0">
                <table class="data-table">
                    <thead><tr><th width="10%">ID</th><th>DOCUMENT TYPE</th><th width="15%">ACTIONS</th></tr></thead>
                    <tbody>
                        <?php foreach ($doc_types as $type): ?>
                        <tr>
                            <td>#<?= $type['id'] ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($type['name']) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editTypeModal" data-id="<?= $type['id'] ?>" data-name="<?= htmlspecialchars($type['name']) ?>"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?= $type['id'] ?>" data-name="<?= htmlspecialchars($type['name']) ?>" data-action="delete_type" data-tab="doctypes"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade <?= $active_tab == 'dtibranches' ? 'show active' : '' ?>" id="dtibranches">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark m-0">DTI Branches</h5>
                <form method="POST" action="configuration.php" class="d-flex gap-2">
                    <input type="hidden" name="action" value="add_dtibranch">
                    <input type="hidden" name="active_tab" value="dtibranches">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="New Branch Name..." required style="width: 250px;">
                    <button type="submit" class="btn btn-blue btn-sm"><i class="fa-solid fa-plus me-1"></i> Add</button>
                </form>
            </div>

            <div class="table-container p-0">
                <table class="data-table">
                    <thead><tr><th width="10%">ID</th><th>BRANCH NAME</th><th width="15%">ACTIONS</th></tr></thead>
                    <tbody>
                        <?php foreach ($dti_branches as $branch): ?>
                        <tr>
                            <td>#<?= $branch['id'] ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($branch['name']) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editBranchModal" data-id="<?= $branch['id'] ?>" data-name="<?= htmlspecialchars($branch['name']) ?>"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?= $branch['id'] ?>" data-name="<?= htmlspecialchars($branch['name']) ?>" data-action="delete_dtibranch" data-tab="dtibranches"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade <?= $active_tab == 'divisions' ? 'show active' : '' ?>" id="divisions">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark m-0">Registered Divisions</h5>
                <button type="button" class="btn btn-blue btn-sm" data-bs-toggle="modal" data-bs-target="#addDivisionModal"><i class="fa-solid fa-plus me-1"></i> Add Division</button>
            </div>

            <div class="table-container p-0">
                <table class="data-table">
                    <thead><tr><th width="10%">ID</th><th>ABBREVIATION</th><th>FULL DIVISION NAME</th><th width="15%">ACTIONS</th></tr></thead>
                    <tbody>
                        <?php foreach ($divisions as $div): ?>
                        <tr>
                            <td>#<?= $div['id'] ?></td>
                            <td><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($div['abbreviation']) ?></span></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($div['name']) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editDivisionModal" data-id="<?= $div['id'] ?>" data-abbr="<?= htmlspecialchars($div['abbreviation']) ?>" data-name="<?= htmlspecialchars($div['name']) ?>"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?= $div['id'] ?>" data-name="<?= htmlspecialchars($div['abbreviation']) ?>" data-action="delete_division" data-tab="divisions"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade <?= $active_tab == 'statuses' ? 'show active' : '' ?>" id="statuses">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark m-0">System Statuses</h5>
                <button type="button" class="btn btn-blue btn-sm" data-bs-toggle="modal" data-bs-target="#addStatusModal"><i class="fa-solid fa-plus me-1"></i> Add Status</button>
            </div>

            <div class="table-container p-0">
                <table class="data-table">
                    <thead><tr><th width="10%">ID</th><th>STATUS NAME</th><th>CATEGORY</th><th width="15%">ACTIONS</th></tr></thead>
                    <tbody>
                        <?php foreach ($statuses as $status): ?>
                        <tr>
                            <td>#<?= $status['id'] ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($status['name']) ?></td>
                            <td><span class="text-muted" style="font-size: 0.85rem; text-transform: uppercase;"><?= htmlspecialchars($status['category']) ?></span></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editStatusModal" data-id="<?= $status['id'] ?>" data-name="<?= htmlspecialchars($status['name']) ?>" data-category="<?= htmlspecialchars($status['category']) ?>"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?= $status['id'] ?>" data-name="<?= htmlspecialchars($status['name']) ?>" data-action="delete_status" data-tab="statuses"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addDivisionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="fw-bold text-dark"><i class="fa-solid fa-building me-2" style="color: #263D81;"></i> Add Division</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <form method="POST">
                    <input type="hidden" name="action" value="add_division">
                    <input type="hidden" name="active_tab" value="divisions">

                    <div class="mb-3">
                        <label class="form-label modal-label">Abbreviation *</label>
                        <input type="text" name="abbreviation" class="form-control custom-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label modal-label">Full Name *</label>
                        <input type="text" name="name" class="form-control custom-input" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="fw-bold text-dark"><i class="fa-solid fa-tag me-2" style="color: #263D81;"></i> Add Status</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <form method="POST">
                    <input type="hidden" name="action" value="add_status">
                    <input type="hidden" name="active_tab" value="statuses">

                    <div class="mb-3">
                        <label class="form-label modal-label">Name *</label>
                        <input type="text" name="name" class="form-control custom-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label modal-label">Category *</label>
                        <select name="category" class="form-select custom-input" required>
                            <option value="ONGOING">ONGOING</option>
                            <option value="FOR-APPROVAL">FOR-APPROVAL</option>
                            <option value="APPROVED">APPROVED</option>
                            <option value="CANCELLED">CANCELLED</option>
                            <option value="REJECTED">REJECTED</option>
                            <option value="CLOSED">CLOSED</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-body p-4">
                <h5 class="fw-bold mb-4">Edit Classification</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_classification">
                    <input type="hidden" name="active_tab" value="classifications">
                    <input type="hidden" name="id" id="editClassId">

                    <input type="text" name="name" id="editClassName" class="form-control custom-input mb-4" required>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editTypeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-body p-4">
                <h5 class="fw-bold mb-4">Edit Document Type</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_type">
                    <input type="hidden" name="active_tab" value="doctypes">
                    <input type="hidden" name="id" id="editTypeId">

                    <input type="text" name="name" id="editTypeName" class="form-control custom-input mb-4" required>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editBranchModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-body p-4">
                <h5 class="fw-bold mb-4">Edit DTI Branch</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_dtibranch">
                    <input type="hidden" name="active_tab" value="dtibranches">
                    <input type="hidden" name="id" id="editBranchId">

                    <input type="text" name="name" id="editBranchName" class="form-control custom-input mb-4" required>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editDivisionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-body p-4">
                <h5 class="fw-bold mb-4">Edit Division</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_division">
                    <input type="hidden" name="active_tab" value="divisions">
                    <input type="hidden" name="id" id="editDivId">

                    <div class="mb-3">
                        <label class="form-label">Abbreviation</label>
                        <input type="text" name="abbreviation" id="editDivAbbr" class="form-control custom-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" id="editDivName" class="form-control custom-input" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-body p-4">
                <h5 class="fw-bold mb-4">Edit Status</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_status">
                    <input type="hidden" name="active_tab" value="statuses">
                    <input type="hidden" name="id" id="editStatusId">

                    <div class="mb-3">
                        <label class="form-label">Status Name</label>
                        <input type="text" name="name" id="editStatusName" class="form-control custom-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Category</label>
                        <select name="category" id="editStatusCat" class="form-select custom-input" required>
                            <option value="ONGOING">ONGOING</option>
                            <option value="FOR-APPROVAL">FOR-APPROVAL</option>
                            <option value="APPROVED">APPROVED</option>
                            <option value="CANCELLED">CANCELLED</option>
                            <option value="REJECTED">REJECTED</option>
                            <option value="CLOSED">CLOSED</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal border-danger">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <p class="mb-4">Are you sure you want to permanently delete <strong id="delete_item_name" class="text-dark"></strong>?</p>
                <form method="POST" action="configuration.php">
                    <input type="hidden" name="action" id="delete_action">
                    <input type="hidden" name="active_tab" id="delete_tab">
                    <input type="hidden" name="id" id="delete_id">

                    <div class="d-flex justify-content-center gap-2 pt-3 mt-2">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4">Yes, Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Link the external JavaScript for managing modals
$extra_js = '<script src="' . BASE_URL . 'static/js/configuration.js"></script>';

require_once BASE_PATH . 'includes/footer.php';
?>