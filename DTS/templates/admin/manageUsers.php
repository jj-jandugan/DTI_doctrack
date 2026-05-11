<?php
// templates/admin/manageUsers.php
require_once '../../classes/database.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Manage Users";

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<style>
    .filter-container {
        background: #fff;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }
</style>
';

$success_msg = '';
$error_msg = '';

// Initialize Admin Session Vault for temporary plain-text passwords
if (!isset($_SESSION['temp_passwords'])) {
    $_SESSION['temp_passwords'] = [];
}

// ==========================================
// 1. HANDLE FORM SUBMISSIONS (ADD, EDIT, DELETE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        // --- ADD USER (AUTO-GENERATE CREDENTIALS) ---
        if ($action === 'add_user') {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $middle_name = trim($_POST['middle_name']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $division_id = !empty($_POST['division_id']) ? $_POST['division_id'] : null;

            if (!empty($first_name) && !empty($last_name) && !empty($role)) {

                // 1. Auto-Generate Username (e.g., jdelacruz492)
                $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', substr($first_name, 0, 1) . $last_name));
                $username = $base_username . rand(100, 999);

                // Handle Optional Email
                if (empty($email)) {
                    $email = $username . '@dts.local';
                }

                // 2. Auto-Generate Temporary Password (e.g., DTS-A9B4F1)
                $raw_password = 'DTS-' . strtoupper(substr(md5(uniqid()), 0, 6));
                $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

                // Insert User
                $stmtUser = $pdo->prepare("INSERT INTO auth_user (password, username, first_name, last_name, email, is_active, date_joined) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmtUser->execute([$hashed_password, $username, $first_name, $last_name, $email]);

                $new_user_id = $pdo->lastInsertId();

                // Insert Profile
                $stmtProfile = $pdo->prepare("INSERT INTO records_userprofile (middle_name, role, user_id, division_id) VALUES (?, ?, ?, ?)");
                $stmtProfile->execute([$middle_name, $role, $new_user_id, $division_id]);

                // Save plain text password to the Session Vault so it persists for the Print button
                $_SESSION['temp_passwords'][$new_user_id] = $raw_password;

                $success_msg = "User successfully created! You can now click the Print button to get their credentials.";
            } else {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // --- EDIT USER ---
        elseif ($action === 'edit_user') {
            $user_id = $_POST['user_id'];
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $middle_name = trim($_POST['middle_name']);
            $email = trim($_POST['email']);
            $username = trim($_POST['username']);
            $role = $_POST['role'];
            $division_id = !empty($_POST['division_id']) ? $_POST['division_id'] : null;
            $is_active = $_POST['is_active'];

            // Handle Optional Email
            if (empty($email)) {
                $email = $username . '@dts.local';
            }

            $stmtUser = $pdo->prepare("UPDATE auth_user SET username = ?, first_name = ?, last_name = ?, email = ?, is_active = ? WHERE id = ?");
            $stmtUser->execute([$username, $first_name, $last_name, $email, $is_active, $user_id]);

            $stmtProfile = $pdo->prepare("UPDATE records_userprofile SET middle_name = ?, role = ?, division_id = ? WHERE user_id = ?");
            $stmtProfile->execute([$middle_name, $role, $division_id, $user_id]);

            // If a new password was provided during Edit, update it and store it in the Session Vault
            if (!empty($_POST['password'])) {
                $raw_password = trim($_POST['password']);
                $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
                $stmtPass = $pdo->prepare("UPDATE auth_user SET password = ? WHERE id = ?");
                $stmtPass->execute([$hashed_password, $user_id]);

                // Save the newly reset password to the vault
                $_SESSION['temp_passwords'][$user_id] = $raw_password;

                $success_msg = "User updated and new password created! You can now click the Print button to get their updated credentials.";
            } else {
                $success_msg = "User successfully updated!";
            }
        }

        // --- DELETE USER ---
        elseif ($action === 'delete_user') {
            $user_id = $_POST['user_id'];
            $pdo->prepare("DELETE FROM records_userprofile WHERE user_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM auth_user WHERE id = ?")->execute([$user_id]);
            $success_msg = "User deleted successfully!";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            if ($action === 'delete_user') {
                $error_msg = "Cannot delete this user because they have active documents. Please set their status to Inactive instead.";
            } else {
                $error_msg = "Error: Username or Email already exists.";
            }
        } else {
            $error_msg = "System error: " . $e->getMessage();
        }
    }
}

// ==========================================
// 2. FETCH DATA FOR DISPLAY
// ==========================================
$stmt = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.is_active,
           p.role, p.middle_name, p.division_id, d.abbreviation as division
    FROM auth_user u
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division d ON p.division_id = d.id
    ORDER BY u.id DESC
");
$users = $stmt->fetchAll();

$divisions = $pdo->query("SELECT * FROM records_division ORDER BY name")->fetchAll();

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">Manage Users</h2>
            <p class="text-secondary">Add, view, and manage system users and roles.</p>
        </div>
        <button type="button" class="btn btn-blue" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa-solid fa-user-plus me-2"></i> Add New User </button>
    </div>
    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i
                class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>
    <div class="filter-container">
        <div class="row g-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Search name, username, email...">
            </div>
            <div class="col-md-4">
                <select id="roleFilter" class="form-select">
                    <option value="">Filter by Role (All)</option>
                    <option value="Admin">Admin</option>
                    <option value="RO">Records Officer (RO)</option>
                    <option value="RD">Regional Director (RD)</option>
                    <option value="ARD">Asst. Regional Director (ARD)</option>
                    <option value="Division">Division Staff</option>
                </select>
            </div>
            <div class="col-md-4">
                <select id="divFilter" class="form-select">
                    <option value="">Filter by Division (All)</option>
                    <?php foreach ($divisions as $div): ?>
                        <option value="<?= htmlspecialchars($div['abbreviation']) ?>">
                            <?= htmlspecialchars($div['abbreviation']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="table-container p-0">
        <div class="table-responsive">
            <table class="data-table" id="usersTable">
                <thead>
                    <tr>
                        <th>NAME</th>
                        <th>USERNAME</th>
                        <th>EMAIL</th>
                        <th>ROLE</th>
                        <th>DIVISION</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr class="user-row">
                            <td>
                                <div class="creator-cell">
                                    <div class="creator-avatar" style="width: 35px; height: 35px;"><i
                                            class="fa-solid fa-user" style="font-size: 0.9rem;"></i></div>
                                    <div class="creator-info">
                                        <span
                                            class="creator-name fw-bold search-target"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="search-target"><?= htmlspecialchars($user['username']) ?></td>
                            <td class="search-target">
                                <?= strpos($user['email'], '@dts.local') !== false ? '<span class="text-muted fst-italic">None provided</span>' : htmlspecialchars($user['email']) ?>
                            </td>
                            <td class="role-target"><span
                                    class="badge bg-primary px-3 py-2 rounded-pill"><?= htmlspecialchars($user['role'] ?? 'None') ?></span>
                            </td>
                            <td class="div-target">
                                <?php if ($user['division']): ?>
                                    <span
                                        class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($user['division']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="text-success fw-bold" style="font-size: 0.85rem;"><i
                                            class="fa-solid fa-circle me-1"></i> Active</span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold" style="font-size: 0.85rem;"><i
                                            class="fa-solid fa-circle me-1"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                // Pull the password from the Session Vault if it was just generated during this login session
                                $plain_password = isset($_SESSION['temp_passwords'][$user['id']]) ? $_SESSION['temp_passwords'][$user['id']] : '_______________________';
                                ?>
                                <button class="btn btn-sm btn-outline-info me-1" title="Print Account Credentials" onclick="printCredentials(
                                    '<?= addslashes(htmlspecialchars($user['first_name'] . ' ' . $user['last_name'])) ?>',
                                    '<?= addslashes(htmlspecialchars($user['username'])) ?>',
                                    '<?= addslashes(htmlspecialchars($user['role'] ?? 'None')) ?>',
                                    '<?= addslashes(htmlspecialchars($user['division'] ?? 'N/A')) ?>',
                                    '<?= addslashes(htmlspecialchars($plain_password)) ?>'
                                )">
                                    <i class="fa-solid fa-print"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary me-1" title="Edit User"
                                    data-bs-toggle="modal" data-bs-target="#editUserModal" data-id="<?= $user['id'] ?>"
                                    data-fname="<?= htmlspecialchars($user['first_name']) ?>"
                                    data-mname="<?= htmlspecialchars($user['middle_name'] ?? '') ?>"
                                    data-lname="<?= htmlspecialchars($user['last_name']) ?>"
                                    data-email="<?= strpos($user['email'], '@dts.local') !== false ? '' : htmlspecialchars($user['email']) ?>"
                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                    data-role="<?= htmlspecialchars($user['role'] ?? '') ?>"
                                    data-division="<?= htmlspecialchars($user['division_id'] ?? '') ?>"
                                    data-active="<?= $user['is_active'] ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" title="Delete User" data-bs-toggle="modal"
                                    data-bs-target="#deleteUserModal" data-id="<?= $user['id'] ?>"
                                    data-name="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-user-plus me-2"
                        style="color: #263D81;"></i> Create New User</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-info mb-4" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-circle-info me-2"></i> <strong>Account Details Automation:</strong> The
                    Username and a secure Temporary Password will be <strong>automatically generated</strong>.
                </div>
                <form method="POST" action="manageUsers.php" id="addUserForm">
                    <input type="hidden" name="action" value="add_user">
                    <h6 class="text-muted fw-bold mb-3 border-bottom pb-2">Personal Details</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label modal-label">First Name *</label><input
                                type="text" name="first_name" class="form-control custom-input" required></div>
                        <div class="col-md-4"><label class="form-label modal-label">Middle Name</label><input
                                type="text" name="middle_name" class="form-control custom-input"></div>
                        <div class="col-md-4"><label class="form-label modal-label">Last Name *</label><input
                                type="text" name="last_name" class="form-control custom-input" required></div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12"><label class="form-label modal-label">Email Address
                                (Optional)</label><input type="email" name="email" class="form-control custom-input"
                                placeholder="Leave blank if not available"></div>
                    </div>
                    <h6 class="text-muted fw-bold mb-3 border-bottom pb-2">Role & Assignment</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label modal-label">System Role *</label>
                            <select class="form-select custom-input" name="role" required>
                                <option value="" selected disabled>Select Role...</option>
                                <option value="Admin">Admin</option>
                                <option value="RO">Records Officer (RO)</option>
                                <option value="RD">Regional Director (RD)</option>
                                <option value="ARD">Asst. Regional Director (ARD)</option>
                                <option value="Division">Division Staff</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label modal-label">Division (Leave blank if Admin/RO)</label>
                            <select class="form-select custom-input" name="division_id">
                                <option value="" selected>None</option>
                                <?php foreach ($divisions as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['abbreviation']) ?> -
                                        <?= htmlspecialchars($div['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4"><i class="fa-solid fa-check me-2"></i>
                            Auto-Generate & Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-2"
                        style="color: #263D81;"></i> Edit User Details</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <form method="POST" action="manageUsers.php">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <h6 class="text-muted fw-bold mb-3 border-bottom pb-2">Personal Details</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label modal-label">First Name *</label><input
                                type="text" name="first_name" id="edit_fname" class="form-control custom-input"
                                required></div>
                        <div class="col-md-4"><label class="form-label modal-label">Middle Name</label><input
                                type="text" name="middle_name" id="edit_mname" class="form-control custom-input"></div>
                        <div class="col-md-4"><label class="form-label modal-label">Last Name *</label><input
                                type="text" name="last_name" id="edit_lname" class="form-control custom-input" required>
                        </div>
                    </div>
                    <h6 class="text-muted fw-bold mb-3 border-bottom pb-2">Account Details</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label modal-label">Email (Optional)</label><input
                                type="email" name="email" id="edit_email" class="form-control custom-input"></div>
                        <div class="col-md-4"><label class="form-label modal-label">Username *</label><input type="text"
                                name="username" id="edit_username" class="form-control custom-input" required></div>
                        <div class="col-md-4">
                            <label class="form-label modal-label text-primary">Reset Password</label>
                            <input type="text" name="password" class="form-control custom-input"
                                placeholder="Type new password to reset">
                        </div>
                    </div>
                    <h6 class="text-muted fw-bold mb-3 border-bottom pb-2">Role & Assignment</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label modal-label">System Role *</label>
                            <select class="form-select custom-input" name="role" id="edit_role" required>
                                <option value="Admin">Admin</option>
                                <option value="RO">Records Officer (RO)</option>
                                <option value="RD">Regional Director (RD)</option>
                                <option value="ARD">Asst. Regional Director (ARD)</option>
                                <option value="Division">Division Staff</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label modal-label">Division</label>
                            <select class="form-select custom-input" name="division_id" id="edit_division">
                                <option value="">None</option>
                                <?php foreach ($divisions as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['abbreviation']) ?> -
                                        <?= htmlspecialchars($div['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label modal-label">Account Status *</label>
                            <select class="form-select custom-input" name="is_active" id="edit_active" required>
                                <option value="1">Active Account</option>
                                <option value="0">Inactive / Deactivated</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-blue px-4"><i class="fa-solid fa-floppy-disk me-2"></i>
                            Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal border-danger">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>
                    Confirm Deletion</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4 text-center">
                <p class="mb-4">Are you sure you want to permanently delete the user account for <strong
                        id="delete_user_name" class="text-dark"></strong>?</p>
                <div class="alert alert-warning text-start" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-circle-info me-1"></i> If this user has processed documents, the system will
                    prevent deletion. Consider setting them to <strong>Inactive</strong> via the Edit menu instead.
                </div>
                <form method="POST" action="manageUsers.php">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="d-flex justify-content-center gap-2 pt-3 mt-2">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4">Yes, Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> <?php
$extra_js = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() {

    // 1. CLEAR 'ADD USER' FORM ON CLOSE
    const addUserModal = document.getElementById('addUserModal');
    if (addUserModal) {
        addUserModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('addUserForm').reset();
        });
    }

    // 2. FILL EDIT MODAL
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('edit_user_id').value = button.getAttribute('data-id');
            document.getElementById('edit_fname').value = button.getAttribute('data-fname');
            document.getElementById('edit_mname').value = button.getAttribute('data-mname');
            document.getElementById('edit_lname').value = button.getAttribute('data-lname');
            document.getElementById('edit_email').value = button.getAttribute('data-email');
            document.getElementById('edit_username').value = button.getAttribute('data-username');
            document.getElementById('edit_role').value = button.getAttribute('data-role');
            document.getElementById('edit_division').value = button.getAttribute('data-division');
            document.getElementById('edit_active').value = button.getAttribute('data-active');
        });
    }

    // 3. FILL DELETE MODAL
    const deleteModal = document.getElementById('deleteUserModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('delete_user_id').value = button.getAttribute('data-id');
            document.getElementById('delete_user_name').textContent = button.getAttribute('data-name');
        });
    }

    // 4. LIVE SEARCH & FILTER
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const divFilter = document.getElementById('divFilter');
    const tableRows = document.querySelectorAll('.user-row');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const roleTerm = roleFilter.value.toLowerCase();
        const divTerm = divFilter.value.toLowerCase();

        tableRows.forEach(row => {
            const textContent = row.innerText.toLowerCase();
            const roleContent = row.querySelector('.role-target').innerText.toLowerCase();
            const divContent = row.querySelector('.div-target').innerText.toLowerCase();

            const matchesSearch = textContent.includes(searchTerm);
            const matchesRole = roleTerm === '' || roleContent.includes(roleTerm);
            const matchesDiv = divTerm === '' || divContent.includes(divTerm);

            if (matchesSearch && matchesRole && matchesDiv) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if(searchInput) searchInput.addEventListener('keyup', filterTable);
    if(roleFilter) roleFilter.addEventListener('change', filterTable);
    if(divFilter) divFilter.addEventListener('change', filterTable);

    const params = new URLSearchParams(window.location.search);
    const editUserId = params.get('edit');

    if (editUserId) {
        const editButton = document.querySelector(
            '[data-bs-target="#editUserModal"][data-id="' + editUserId + '"]'
        );

        if (editButton) {
            editButton.click();
        }
    }
});

// 5. PRINT CREDENTIALS FUNCTION
function printCredentials(name, username, role, division, password) {
    const printWindow = window.open('', '_blank', 'width=600,height=450');
    printWindow.document.write(`
        <html>
        <head>
            <title>Account Credentials - \${name}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; color: #333; }
                .card-container { border: 2px dashed #94a3b8; padding: 30px; border-radius: 10px; max-width: 400px; margin: 0 auto; background: #f8fafc; }
                h2 { text-align: center; color: #263D81; margin-top: 0; font-size: 22px; }
                .info { margin-bottom: 12px; font-size: 16px; }
                .creds-box { background: #ffffff; border: 1px solid #cbd5e1; padding: 15px; border-radius: 6px; margin-top: 20px;}
                .warning { font-size: 13px; color: #64748b; margin-top: 25px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 15px;}
                .alert-text { color: #b91c1c; font-weight: bold; display: block; margin-top: 8px;}
            </style>
        </head>
        <body>
            <div class='card-container'>
                <h2>System Account Details</h2>
                <div class='info'><strong>Name:</strong> \${name}</div>
                <div class='info'><strong>Role:</strong> \${role}</div>
                <div class='info'><strong>Division:</strong> \${division}</div>

                <div class='creds-box'>
                    <div class='info'><strong>Username:</strong> \${username}</div>
                    <div class='info'><strong>Temp Password:</strong> \${password}</div>
                </div>

                <div class='warning'>
                    <strong>Security Notice:</strong> Please keep these credentials safe.
                    <span class='alert-text'>⚠️ IMPORTANT: You MUST change your Username and Password immediately upon your first login!</span>
                </div>
            </div>
            <script>
                window.print();
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>
JS;

require_once BASE_PATH . 'includes/footer.php';
?>