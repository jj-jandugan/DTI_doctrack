<?php
// templates/admin/distributionGroups.php
require_once '../../classes/database.php';

// Security Check: Only Admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Distribution Groups";

// External CSS References
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/forms.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/distribution.css">
';

$success_msg = '';
$error_msg = '';

// ==========================================
// 1. HANDLE FORM SUBMISSIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        // Add New Group
        if ($action === 'add_group') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');

            $stmt = $pdo->prepare("INSERT INTO records_distributiongroup (group_name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $success_msg = "Distribution Group created successfully!";
        }

        // Edit Group Name/Description
        elseif ($action === 'edit_group') {
            $id = $_POST['id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');

            $stmt = $pdo->prepare("UPDATE records_distributiongroup SET group_name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            $success_msg = "Group details updated successfully!";
        }

        // Delete Group
        elseif ($action === 'delete_group') {
            $id = $_POST['id'];
            $pdo->prepare("DELETE FROM records_distributiongroup_members WHERE group_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM records_distributiongroup WHERE id = ?")->execute([$id]);
            $success_msg = "Distribution Group deleted successfully!";
        }

        // Save Checked Members
        elseif ($action === 'manage_members') {
            $group_id = $_POST['group_id'];
            $pdo->prepare("DELETE FROM records_distributiongroup_members WHERE group_id = ?")->execute([$group_id]);

            if (isset($_POST['members']) && is_array($_POST['members'])) {
                $stmt = $pdo->prepare("INSERT INTO records_distributiongroup_members (group_id, user_id) VALUES (?, ?)");
                foreach ($_POST['members'] as $user_id) {
                    $stmt->execute([$group_id, $user_id]);
                }
            }
            $success_msg = "Group members saved successfully!";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

// ==========================================
// 2. FETCH DATA FOR DISPLAY
// ==========================================
$stmt = $pdo->query("
    SELECT g.*,
           (SELECT COUNT(*) FROM records_distributiongroup_members WHERE group_id = g.id) as member_count,
           (SELECT GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name, ' ', u.username) SEPARATOR ' ')
            FROM records_distributiongroup_members gm
            JOIN auth_user u ON gm.user_id = u.id
            WHERE gm.group_id = g.id) as hidden_member_names
    FROM records_distributiongroup g
    ORDER BY g.group_name ASC
");
$groups = $stmt->fetchAll();

$users = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, p.role, d.abbreviation as division
    FROM auth_user u
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division d ON p.division_id = d.id
    WHERE u.is_active = 1
    ORDER BY p.role ASC, u.first_name ASC
")->fetchAll();

$stmtMembers = $pdo->query("SELECT group_id, user_id FROM records_distributiongroup_members");
$group_members_map = [];
while ($row = $stmtMembers->fetch()) {
    $group_members_map[$row['group_id']][] = $row['user_id'];
}

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">Distribution Groups</h2>
            <p class="text-secondary">Create and manage mailing lists for easier document routing.</p>
        </div>

        <button type="button" class="btn btn-blue" data-bs-toggle="modal" data-bs-target="#addGroupModal">
            <i class="fa-solid fa-users-rectangle me-2"></i> Create New Group
        </button>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="filter-container">
        <div class="row">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Search Group Name, Description, or Member Name...">
                </div>
            </div>
        </div>
    </div>

    <div class="table-container p-0">
        <div class="table-responsive">
            <table class="data-table" id="groupsTable">
                <thead>
                    <tr>
                        <th width="10%">ID</th>
                        <th>GROUP NAME</th>
                        <th>DESCRIPTION</th>
                        <th>MEMBERS</th>
                        <th width="20%">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                    <tr class="group-row">
                        <td>#<?= $group['id'] ?></td>
                        <td class="fw-bold text-dark search-target"><?= htmlspecialchars($group['group_name']) ?></td>
                        <td class="search-target"><?= htmlspecialchars($group['description'] ?? '---') ?></td>

                        <td>
                            <span class="badge bg-primary px-3 py-2 rounded-pill"><i class="fa-solid fa-user me-1"></i> <?= $group['member_count'] ?> Users</span>
                            <span class="d-none search-target"><?= htmlspecialchars($group['hidden_member_names'] ?? '') ?></span>
                        </td>

                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Manage Users" data-bs-toggle="modal" data-bs-target="#manageMembersModal"
                                data-id="<?= $group['id'] ?>"
                                data-name="<?= htmlspecialchars($group['group_name']) ?>">
                                <i class="fa-solid fa-user-plus"></i> Members
                            </button>

                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" title="Edit Group" data-bs-toggle="modal" data-bs-target="#editGroupModal"
                                data-id="<?= $group['id'] ?>"
                                data-name="<?= htmlspecialchars($group['group_name']) ?>"
                                data-desc="<?= htmlspecialchars($group['description'] ?? '') ?>">
                                <i class="fa-solid fa-pen"></i>
                            </button>

                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Group" data-bs-toggle="modal" data-bs-target="#deleteGroupModal"
                                data-id="<?= $group['id'] ?>"
                                data-name="<?= htmlspecialchars($group['group_name']) ?>">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if(empty($groups)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No Distribution Groups found. Create one to get started!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Group -->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-users-rectangle me-2 text-primary"></i> Create Distribution Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <form method="POST">
                    <input type="hidden" name="action" value="add_group">
                    <div class="mb-3">
                        <label class="form-label modal-label">Group Name *</label>
                        <input type="text" name="name" class="form-control custom-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label modal-label">Description (Optional)</label>
                        <input type="text" name="description" class="form-control custom-input" placeholder="What is this group for?">
                    </div>
                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Edit Group -->
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Edit Group Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_group">
                    <input type="hidden" name="id" id="edit_group_id">
                    <div class="mb-3">
                        <label class="form-label modal-label">Group Name *</label>
                        <input type="text" name="name" id="edit_group_name" class="form-control custom-input" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label modal-label">Description</label>
                        <input type="text" name="description" id="edit_group_desc" class="form-control custom-input">
                    </div>
                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Manage Members -->
<div class="modal fade" id="manageMembersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-user-check me-2 text-primary"></i> Manage Members: <span id="manage_group_name" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <form method="POST">
                    <input type="hidden" name="action" value="manage_members">
                    <input type="hidden" name="group_id" id="manage_group_id">

                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="memberSearch" class="form-control border-start-0 ps-0" placeholder="Search for a user...">
                    </div>

                    <div class="user-list-container mb-4">
                        <?php foreach($users as $user): ?>
                            <label class="user-item w-100 m-0" for="user_<?= $user['id'] ?>">
                                <div class="form-check m-0 d-flex align-items-center w-100">
                                    <input class="form-check-input member-checkbox me-3" type="checkbox" name="members[]" value="<?= $user['id'] ?>" id="user_<?= $user['id'] ?>">
                                    <div>
                                        <div class="fw-bold text-dark mb-0 user-name-target"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                        <div class="text-muted small">
                                            <span><?= htmlspecialchars($user['role']) ?></span>
                                            <?php if($user['division']): ?> | <span><?= htmlspecialchars($user['division']) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        <?php if(empty($users)): ?>
                            <div class="text-center text-muted py-4">No active users found.</div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4"><i class="fa-solid fa-floppy-disk me-2"></i> Save Members</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Confirm Delete (Themed Consistency) -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal border-danger">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-danger">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Confirm Deletion
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <p class="mb-4">Are you sure you want to permanently delete the distribution group <strong id="delete_group_name" class="text-dark"></strong>?</p>

                <div class="alert alert-warning text-start" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-circle-info me-1"></i> <strong>Note:</strong> Documents already routed to these users will not be affected, but this group will no longer be available for future routing.
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="id" id="delete_group_id">
                    <div class="d-flex justify-content-center gap-2 pt-3 mt-2">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4">Yes, Delete Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$group_members_json = json_encode($group_members_map);

$extra_js = "
<script>
document.addEventListener('DOMContentLoaded', function() {

    // Edit Modal Population
    const editModal = document.getElementById('editGroupModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('edit_group_id').value = button.getAttribute('data-id');
            document.getElementById('edit_group_name').value = button.getAttribute('data-name');
            document.getElementById('edit_group_desc').value = button.getAttribute('data-desc');
        });
    }

    // Delete Modal Population
    const deleteModal = document.getElementById('deleteGroupModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('delete_group_id').value = button.getAttribute('data-id');
            document.getElementById('delete_group_name').textContent = button.getAttribute('data-name');
        });
    }

    // Manage Members Logic
    const manageModal = document.getElementById('manageMembersModal');
    const groupMembersMap = $group_members_json;

    if (manageModal) {
        manageModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const groupId = button.getAttribute('data-id');

            document.getElementById('manage_group_id').value = groupId;
            document.getElementById('manage_group_name').textContent = button.getAttribute('data-name');

            document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = false);

            if (groupMembersMap[groupId]) {
                groupMembersMap[groupId].forEach(userId => {
                    const checkbox = document.getElementById('user_' + userId);
                    if (checkbox) checkbox.checked = true;
                });
            }
        });
    }

    // Search Functions
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.group-row').forEach(row => {
            const text = Array.from(row.querySelectorAll('.search-target')).map(el => el.innerText.toLowerCase()).join(' ');
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });

    document.getElementById('memberSearch').addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.user-item').forEach(item => {
            item.style.display = item.innerText.toLowerCase().includes(term) ? 'flex' : 'none';
        });
    });
});
</script>
";
require_once BASE_PATH . 'includes/footer.php';
?>