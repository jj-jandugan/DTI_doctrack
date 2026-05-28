<?php
// templates/division/divAcceptDocu.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

// Security Check: Only 'Division' role can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$doc_id = $_GET['id'] ?? null;
if (!$doc_id) {
    header("Location: divOnMyDesk.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);
$error_msg = '';

// ==========================================
// HANDLE "ACCEPT & CLOSE" POST ACTION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_document') {
    try {
        $pdo->beginTransaction();

        // Look for any 'CLOSED' category status, but STRICTLY EXCLUDE 'Rejected' and 'Cancelled'
        $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE name = 'CLOSED' OR category = 'CLOSED' LIMIT 1");
        $closed_status_id = $stmtStatus->fetchColumn();

        if (!$closed_status_id) {
            throw new Exception("Database Error: Status 'CLOSED' does not exist in the records_status table.");
        }

        // Update the recipient table
        $stmtUpdateRec = $pdo->prepare("UPDATE records_documentrecipient SET has_received = 1, received_at = NOW() WHERE document_id = ? AND recipient_user_id = ?");
        $stmtUpdateRec->execute([$doc_id, $user_id]);

        // Update the main document status
        $stmtUpdateDoc = $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdateDoc->execute([$closed_status_id, $doc_id]);

        // Log the action
        $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('RECEIVED & CLOSED', 'Document was accepted and marked as closed by the assigned division user.', NOW(), ?, ?)");
        $stmtLog->execute([$user_id, $doc_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Document successfully accepted and closed! It has been moved to your History.";
        header("Location: divOnMyDesk.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error processing document: " . $e->getMessage();
    }
}

// ==========================================
// FETCH COMPREHENSIVE DOCUMENT DETAILS
// ==========================================
$stmt = $pdo->prepare("
    SELECT d.*,
           c.name as class_name, t.name as type_name,
           s.name as status_name, s.category as status_category,
           orig.name as origin_name, addr.name as address_name,
           u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
           sig.first_name as sig_fname, sig.last_name as sig_lname
    FROM records_document d
    LEFT JOIN records_classification c ON d.classification_id = c.id
    LEFT JOIN records_documenttype t ON d.document_type_id = t.id
    LEFT JOIN records_status s ON d.status_id = s.id
    LEFT JOIN records_origin orig ON d.origin_id = orig.id
    LEFT JOIN records_address addr ON d.address_id = addr.id
    LEFT JOIN auth_user u ON d.creator_id = u.id
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division divi ON p.division_id = divi.id
    LEFT JOIN auth_user sig ON d.signatory_id = sig.id
    WHERE d.id = ?
");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    header("Location: divOnMyDesk.php");
    exit;
}

$page_title = "Accept Document";

// --- FETCH CATEGORIZED ATTACHMENTS ---
$organized = $docManager->getDocumentAttachmentsByCategory($doc_id);
$original_files = $organized['original'];
$signed_files   = $organized['signed'];

$history_logs = $docManager->getDocumentTrackingHistory($doc_id);
$is_overdue = ($doc['due_date'] && strtotime($doc['due_date']) < strtotime('today'));

// ==========================================
// CUSTOM DESK VISUAL TRACKER LOGIC
// ==========================================
$cat = strtoupper(trim($doc['status_category'] ?? ''));
$nam = strtoupper(trim($doc['status_name'] ?? ''));
$combined_status = $cat . '|' . $nam;

$level = 4; // Since it's routed to this user, it's already 'Waiting to be Receive'
$is_rejected = false;

if (strpos($combined_status, 'REJECT') !== false || strpos($combined_status, 'CANCEL') !== false) {
    $is_rejected = true;
}

if (strpos($combined_status, 'CLOSE') !== false) {
    $level = 5; // Document has been Received & Closed
}

$step1 = 'completed';
$step2 = 'completed';
$step3 = 'completed';
$step4 = ($level > 4) ? 'completed' : 'active';
$step5 = ($level == 5) ? 'completed' : '';

if ($is_rejected) {
    $step4 = 'danger';
    $step5 = 'danger';
}

$progress_width = ($level == 4) ? 75 : 100;
// ==========================================

// Assets
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/document.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/accept_docu.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/track.css">
<style>
    .visual-stepper::before { display: none !important; }
    .signed-badge { background-color: #f0fdf4; border: 1px solid #bcf0da; color: #166534; padding: 15px; border-radius: 10px; }
</style>
';

$extra_js = '
<script src="' . BASE_URL . 'static/js/track.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="mb-4">
        <a href="divOnMyDesk.php" class="btn-back text-decoration-none">
            <i class="fa-solid fa-chevron-left me-2"></i>
            <span>Back to List</span>
        </a>
    </div>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="detail-card">
        <div class="detail-header d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h5 class="fw-bold mb-0">Control No. <span class="text-primary"><?= htmlspecialchars($doc['dts_no']) ?></span></h5>

            <?php if ($is_overdue): ?>
                <span class="status overdue">OVER DUE</span>
            <?php else: ?>
                <span class="status <?= strtolower(str_replace(' ', '-', $doc['status_category'])) ?> fs-6 px-3 py-2"><?= htmlspecialchars($doc['status_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="bg-light border rounded p-4 mb-4 shadow-sm">
            <h6 class="fw-bold text-dark mb-4 text-center text-uppercase" style="letter-spacing: 1px;">
                <i class="fa-solid fa-route text-secondary me-2"></i> Document Status
            </h6>

            <div class="position-relative mt-4 mb-2">
                <div style="position: absolute; top: 15px; left: 10%; right: 10%; height: 4px; background: #e2e8f0; z-index: 1;"></div>
                <div style="position: absolute; top: 15px; left: 10%; width: calc(80% * <?= $progress_width ?> / 100); height: 4px; background: <?= $is_rejected ? '#dc3545' : '#10b981' ?>; z-index: 2; transition: width 0.5s ease;"></div>

                <div class="visual-stepper d-flex justify-content-between position-relative" style="z-index: 3; padding: 0;">
                    <div class="step <?= $step1 ?>">
                        <div class="circle"><i class="fa-solid fa-file-import"></i></div>
                        <div class="label">Encoded</div>
                    </div>
                    <div class="step <?= $step2 ?>">
                        <div class="circle"><i class="fa-solid fa-file-signature"></i></div>
                        <div class="label">For Approval</div>
                    </div>
                    <div class="step <?= $step3 ?>">
                        <div class="circle"><i class="fa-solid fa-stamp"></i></div>
                        <div class="label">Approved</div>
                    </div>
                    <div class="step <?= $step4 ?>">
                        <div class="circle"><i class="fa-solid fa-inbox"></i></div>
                        <div class="label">Waiting to be Received</div>
                    </div>
                    <div class="step <?= $step5 ?>">
                        <div class="circle">
                            <?php if($is_rejected): ?>
                                <i class="fa-solid fa-xmark"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-check-double"></i>
                            <?php endif; ?>
                        </div>
                        <div class="label"><?= ($is_rejected) ? 'Rejected' : 'Received' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border rounded p-4 mb-5 shadow-sm" style="background-color: #f8fafc;">
            <h6 class="fw-bold text-dark mb-4"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Activity Log Timeline</h6>
            <div class="timeline-container">
                <?php if(empty($history_logs)): ?>
                    <p class="text-muted small fst-italic">No history records found for this document.</p>
                <?php else: ?>
                    <?php foreach ($history_logs as $index => $log):
                        $icon = 'fa-arrow-right';
                        $bg = '#1d4ed8';
                        if(strtoupper($log['action_taken']) === 'ENCODED') { $icon = 'fa-plus'; $bg = '#10b981'; }
                        elseif(strtoupper($log['action_taken']) === 'EDITED') { $icon = 'fa-pen'; $bg = '#f59e0b'; }
                        elseif(strtoupper($log['action_taken']) === 'CANCELLED' || strtoupper($log['action_taken']) === 'REJECTED') { $icon = 'fa-xmark'; $bg = '#dc3545'; }
                        elseif(strtoupper($log['action_taken']) === 'APPROVED' || strpos(strtoupper($log['action_taken']), 'CLOSED') !== false) { $icon = 'fa-check'; $bg = '#10b981'; }

                        $hidden_class = ($index >= 3) ? 'd-none extra-log' : '';
                    ?>
                    <div class="timeline-item <?= $hidden_class ?>">
                        <div class="timeline-icon" style="background-color: <?= $bg ?>;"><i class="fa-solid <?= $icon ?>"></i></div>
                        <div class="timeline-content bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars(strtoupper($log['action_taken'])) ?></span>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary rounded-pill px-2 py-1" style="font-size: 0.7rem;">
                                    <?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?>
                                </span>
                            </div>
                            <p class="mb-2 text-secondary" style="font-size: 0.85rem;"><?= htmlspecialchars($log['remarks']) ?></p>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-user me-1 text-primary"></i>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></span>
                                (<?= htmlspecialchars($log['role'] ?? 'User') ?> - <?= htmlspecialchars($log['division_name'] ?? 'System') ?>)
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if(count($history_logs) > 3): ?>
                        <div class="text-center mt-4">
                            <button type="button" id="toggleLogsBtn" class="btn btn-sm btn-outline-primary rounded-pill px-4 py-2 fw-bold" onclick="toggleActivityLogs()">
                                See More <i class="fa-solid fa-chevron-down ms-1"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SIGNED DOCUMENTS SECTION (THE NEW FEATURE) -->
        <!-- ========================================== -->
        <?php if (!empty($signed_files)): ?>
        <div class="signed-badge mb-4 shadow-sm">
            <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-file-signature me-2"></i> Final Signed Version (Official Scan)</h6>
            <div class="list-group list-group-flush rounded border bg-white">
                <?php foreach ($signed_files as $att):
                    $file_name = htmlspecialchars(basename($att['file_path']));
                    $file_url = '../../uploads/' . $file_name;
                ?>
                    <a href="<?= $file_url ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                        <span class="text-dark fw-bold small"><i class="fa-solid fa-file-pdf text-danger me-2"></i><?= $file_name ?></span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3">Download / View</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <h6 class="fw-bold text-dark mb-4 mt-2 border-top pt-4">
            <i class="fa-solid fa-circle-info me-2 text-primary"></i>
            Document Details
        </h6>

        <div class="attachment-section mb-4">
            <div class="accordion" id="attachmentAccordion">
                <div class="accordion-item border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold custom-accordion-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAttachments">
                            Original Reference Files (<?= count($original_files) ?>)
                        </button>
                    </h2>
                    <div id="collapseAttachments" class="accordion-collapse collapse" data-bs-parent="#attachmentAccordion">
                        <div class="accordion-body p-0 pt-2">
                            <?php if (!empty($original_files)): ?>
                                <style> .file-link { text-decoration: underline; transition: color 0.2s ease; } .file-link:hover { color: #1d4ed8 !important; } </style>
                                <ul class="list-group list-group-flush rounded-3 border">
                                    <?php foreach ($original_files as $att):
                                        $ext = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                                        $icon = 'fa-file';
                                        $color = 'text-secondary';
                                        if ($ext === 'pdf') { $icon = 'fa-file-pdf'; $color = 'text-danger'; }
                                        elseif (in_array($ext, ['doc', 'docx'])) { $icon = 'fa-file-word'; $color = 'text-primary'; }
                                        elseif (in_array($ext, ['xls', 'xlsx'])) { $icon = 'fa-file-excel'; $color = 'text-success'; }
                                        elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) { $icon = 'fa-image'; $color = 'text-warning'; }

                                        $file_name = htmlspecialchars(basename($att['file_path']));
                                        $file_url = '../../uploads/' . $file_name;
                                    ?>
                                        <li class="list-group-item d-flex align-items-center gap-2">
                                            <i class="fa-solid <?= $icon ?> <?= $color ?>"></i>
                                            <a href="<?= $file_url ?>" target="_blank" class="text-dark text-truncate file-link" title="<?= $file_name ?>">
                                                <?= $file_name ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-muted small fst-italic p-3 border rounded-3 bg-light">No original files attached.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="data-group">
                    <label>Document Date</label>
                    <p class="data-value"><?= date('m/d/Y', strtotime($doc['created_at'])) ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-group">
                    <label>Document Deadline</label>
                    <p class="data-value <?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                        <?= $doc['due_date'] ? date('m/d/Y', strtotime($doc['due_date'])) : 'None' ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="data-group">
                    <label>Classification</label>
                    <p class="data-value"><?= htmlspecialchars($doc['class_name'] ?? 'N/A') ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="data-group">
                    <label>Document Type</label>
                    <p class="data-value"><?= htmlspecialchars($doc['type_name'] ?? 'N/A') ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="data-group">
                    <label>Signatory</label>
                    <p class="data-value">
                        <?= !empty($doc['sig_fname']) ? htmlspecialchars($doc['sig_fname'] . ' ' . $doc['sig_lname']) : 'None' ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="data-group">
                    <label>Subject</label>
                    <div class="data-value textarea-style"><?= htmlspecialchars($doc['subject']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="data-group">
                    <label>Address / Destination</label>
                    <div class="data-value textarea-style"><?= htmlspecialchars($doc['address_name'] ?? 'Internal Routing') ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-group">
                    <label>Particulars</label>
                    <div class="data-value textarea-style"><?= htmlspecialchars($doc['particulars'] ?? 'No additional details provided.') ?></div>
                </div>
            </div>
        </div>

        <div class="footer-action-container pt-4 border-top mt-5 d-flex justify-content-between align-items-end">
            <div class="created-info">
                <p class="mb-0 text-muted" style="font-size: 0.85rem;">Created by: <span class="fw-bold"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span></p>
                <p class="mb-0 text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($doc['c_division'] ?? 'System User') ?></p>
                <p class="mb-0 text-muted" style="font-size: 0.85rem;"><?= date('F d, Y g:i A', strtotime($doc['created_at'])) ?></p>
            </div>

            <button type="button" class="btn btn-blue" data-bs-toggle="modal" data-bs-target="#acceptConfirmModal">
                <i class="fa-solid fa-check-circle me-2"></i>
                Accept Document
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="acceptConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #1d4ed8;">
            <div class="modal-body text-center p-4">
                <i class="fa-solid fa-circle-check text-primary mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark">Accept Document?</h5>
                <p class="text-muted" style="font-size: 0.9rem;">Are you sure you want to Accept this document? It will be marked as CLOSED and moved to your history.</p>
                <div class="d-flex flex-column gap-2 mt-4">
                    <form action="divAcceptDocu.php?id=<?= $doc['id'] ?>" method="POST" class="m-0 w-100" id="acceptForm">
                        <input type="hidden" name="action" value="accept_document">
                        <button type="submit" id="btnSubmitAccept" class="btn btn-blue fw-bold w-100" style="background-color: #1d4ed8; color: #fff; transition: all 0.3s ease;">
                            Yes, Accept
                        </button>
                    </form>
                    <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const acceptForm = document.getElementById('acceptForm');
        const btnSubmitAccept = document.getElementById('btnSubmitAccept');

        if (acceptForm && btnSubmitAccept) {
            acceptForm.addEventListener('submit', function() {
                btnSubmitAccept.disabled = true;
                btnSubmitAccept.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i> Accepting...';
                btnSubmitAccept.style.opacity = '0.8';
                btnSubmitAccept.style.cursor = 'not-allowed';
            });
        }
    });

    function toggleActivityLogs() {
        const extraLogs = document.querySelectorAll('.extra-log');
        const btn = document.getElementById('toggleLogsBtn');
        const isHidden = extraLogs[0].classList.contains('d-none');

        extraLogs.forEach(log => {
            if (isHidden) {
                log.classList.remove('d-none');
            } else {
                log.classList.add('d-none');
            }
        });

        if (isHidden) {
            btn.innerHTML = 'Show Less <i class="fa-solid fa-chevron-up ms-1"></i>';
        } else {
            btn.innerHTML = 'See More <i class="fa-solid fa-chevron-down ms-1"></i>';
        }
    }
</script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>