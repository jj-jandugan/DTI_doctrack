<?php
// templates/division/divDashboard.php
require_once '../../classes/database.php';
require_once '../../classes/Dashboard.php'; // Include our new class!

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Dashboard Overview";
$user_id = $_SESSION['user_id'];

// Grab credential update messages from the controller, then clear them
$cred_error = $_SESSION['cred_error'] ?? '';
$cred_success = $_SESSION['cred_success'] ?? '';
unset($_SESSION['cred_error'], $_SESSION['cred_success']);

// ==========================================
// 2. DATA FETCHING LOGIC (Using OOP)
// ==========================================

// Fetch User's Profile & Division Info
$stmtUser = $pdo->prepare("
    SELECT u.first_name, u.last_name, u.username, u.must_change_password, d.name as division_name
    FROM auth_user u
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division d ON p.division_id = d.id
    WHERE u.id = ?
");
$stmtUser->execute([$user_id]);
$user_info = $stmtUser->fetch() ?: ['first_name' => 'Division', 'last_name' => 'User', 'username' => 'unknown', 'must_change_password' => 0];

$must_change = $user_info['must_change_password'];

// Initialize our new Dashboard class
$dashboard = new Dashboard($pdo);

// Call the class methods to get our numbers and table data
$count_incoming = $dashboard->getIncomingCount($user_id);
$count_onhand = $dashboard->getOnHandCount($user_id);
$count_approval = $dashboard->getApprovalCount($user_id);
$count_closed = $dashboard->getClosedCount($user_id);
$count_upcoming = 0; // Placeholder for future logic
$count_overdue = 0; // Placeholder for future logic

$incoming_docs = $dashboard->getIncomingDocuments($user_id);

// ==========================================
// 3. ASSETS & MODULAR LINKS
// ==========================================
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
';

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-4">
    <h2 class="user-name text-dark fw-bold mb-0">
        <?= htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']) ?></h2>
    <h5 class="user-dept text-secondary fw-normal mb-4">
        <?= htmlspecialchars($user_info['division_name'] ?? 'Division Staff') ?></h5>
    <!-- Success Feedback if Password was updated -->
    <?php if ($cred_success): ?>
        <div class="alert alert-success mb-4"><i
                class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($cred_success) ?></div>
    <?php endif; ?>
    <!-- Cards Grid -->
    <div class="cards-grid">
        <a href="divOnMyDesk.php" class="card-link active">
            <div class="custom-card card-incoming">
                <div class="card-number"><?= $count_incoming ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-file-import"></i></div>
                    <span>Incoming</span>
                </div>
            </div>
        </a>
        <a href="divOnMyDesk.php" class="card-link">
            <div class="custom-card card-onhand">
                <div class="card-number"><?= $count_onhand ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-folder-open"></i></div>
                    <span>On Hand</span>
                </div>
            </div>
        </a>
        <a href="divOutgoing.php" class="card-link">
            <div class="custom-card card-approval">
                <div class="card-number"><?= $count_approval ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-file-signature"></i></div>
                    <span>For Approval</span>
                </div>
            </div>
        </a>
        <a href="divOnMyDesk.php" class="card-link">
            <div class="custom-card card-upcoming">
                <div class="card-number"><?= $count_upcoming ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-calendar-day"></i></div>
                    <span>Upcoming Due Date</span>
                </div>
            </div>
        </a>
        <a href="divOnMyDesk.php" class="card-link">
            <div class="custom-card card-overdue">
                <div class="card-number"><?= $count_overdue ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <span>Over Due</span>
                </div>
            </div>
        </a>
        <a href="divHistory.php" class="card-link">
            <div class="custom-card card-closed">
                <div class="card-number"><?= $count_closed ?></div>
                <div class="card-info">
                    <div class="icon-box"><i class="fa-solid fa-check-double"></i></div>
                    <span>Closed</span>
                </div>
            </div>
        </a>
    </div>
    <!-- Table Section -->
    <div class="mt-5">
        <h4 class="mb-3 fw-bold table-main-title">Incoming Documents:</h4>
        <div class="table-container p-0">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>DTS NO.</th>
                            <th>STATUS</th>
                            <th>DEADLINE</th>
                            <th>DATE RECEIVED</th>
                            <th>TIME RECEIVED</th>
                            <th>SUBJECT</th>
                            <th>ADDRESS</th>
                            <th>SIGNATORY</th>
                            <th>CREATED BY</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incoming_docs)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-inbox mb-3 opacity-25" style="font-size: 3rem;"></i><br>
                                    <h6 class="fw-bold text-secondary">No Incoming Documents</h6>
                                    <p class="small mb-0">You have no new documents routed to your desk.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($incoming_docs as $doc): ?>
                                <tr class="clickable-row"
                                    onclick="window.location.href='divAcceptDocu.php?id=<?= $doc['id'] ?>'"
                                    style="cursor: pointer;">
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($doc['dts_no']) ?></td>
                                    <td><span
                                            class="status <?= strtolower($doc['status_category']) ?>"><?= htmlspecialchars($doc['status_name']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($doc['due_date']): ?>
                                            <span class="text-danger fw-bold"><i class="fa-regular fa-calendar-xmark me-1"></i>
                                                <?= date('M d, Y', strtotime($doc['due_date'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($doc['updated_at'])) ?></td>
                                    <td class="small text-muted"><?= date('h:i A', strtotime($doc['updated_at'])) ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($doc['subject']) ?></td>
                                    <td class="small"><?= htmlspecialchars($doc['address_name'] ?? '---') ?></td>
                                    <td class="small"><?= htmlspecialchars($doc['sig_fname'] . ' ' . $doc['sig_lname']) ?></td>
                                    <td>
                                        <div class="creator-cell">
                                            <div class="creator-avatar sm"><i class="fa-solid fa-user"></i></div>
                                            <div class="creator-info">
                                                <span
                                                    class="creator-name small"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span>
                                                <span
                                                    class="creator-role smaller"><?= htmlspecialchars($doc['c_division'] ?? 'System User') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- Security Modal -->
<?php if ($must_change == 1 || !empty($cred_error)): ?>
    <div class="modal fade" id="forceUpdateModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content custom-modal security-border">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-shield-halved me-2"></i> Security
                        Action Required</h5>
                </div>
                <div class="modal-body px-4 py-4">
                    <?php if ($cred_error): ?>
                        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($cred_error) ?></div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 small">
                            <i class="fa-solid fa-lock me-1"></i> Update your auto-generated credentials before accessing the
                            system.
                        </div>
                    <?php endif; ?>
                    <!-- FORM NOW POINTS TO THE UNIVERSAL USER CONTROLLER -->
                    <form method="POST" action="../../controllers/User.php">
                        <input type="hidden" name="action" value="force_update_credentials">
                        <div class="mb-3">
                            <label class="modal-label">Choose a New Username *</label>
                            <input type="text" name="new_username" class="form-control custom-input"
                                value="<?= htmlspecialchars($user_info['username']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="modal-label">New Password *</label>
                            <input type="password" name="new_password" class="form-control custom-input" minlength="6"
                                required>
                        </div>
                        <div class="mb-4">
                            <label class="modal-label">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-control custom-input" minlength="6"
                                required>
                        </div>
                        <div class="d-flex justify-content-end border-top pt-3">
                            <button type="submit" class="btn btn-danger px-4 w-100">Update & Secure Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<!-- External JS -->
<script src="<?= BASE_URL ?>static/js/dashboard.js"></script>
<?php require_once BASE_PATH . 'includes/footer.php'; ?>