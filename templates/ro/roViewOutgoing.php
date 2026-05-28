<?php
// templates/ro/roViewOutgoing.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

// Security Check: Only 'RO' role can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RO') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$doc_id = $_GET['id'] ?? null;
if (!$doc_id) {
    header("Location: roOutgoing.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Handle Session Messages
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Custom Query to fetch all necessary details
$stmt = $pdo->prepare("
    SELECT d.*,
           s.name as status_name, s.category as status_category,
           addr.name as address_name,
           orig.name as origin_name,
           sig.first_name as sig_fname, sig.last_name as sig_lname,
           u.first_name as c_fname, u.last_name as c_lname,
           p.role as c_role, divi.name as c_division,
           c.name as class_name, t.name as type_name
    FROM records_document d
    LEFT JOIN records_status s ON d.status_id = s.id
    LEFT JOIN records_address addr ON d.address_id = addr.id
    LEFT JOIN records_origin orig ON d.origin_id = orig.id
    LEFT JOIN auth_user sig ON d.signatory_id = sig.id
    LEFT JOIN auth_user u ON d.creator_id = u.id
    LEFT JOIN records_userprofile p ON u.id = p.user_id
    LEFT JOIN records_division divi ON p.division_id = divi.id
    LEFT JOIN records_classification c ON d.classification_id = c.id
    LEFT JOIN records_documenttype t ON d.document_type_id = t.id
    WHERE d.id = ?
");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    echo "<div class='p-5 text-center'><h3>Document not found.</h3></div>";
    exit;
}

// Fetch Attachments & History
$attachments = $docManager->getDocumentAttachments($doc_id);
$history_logs = $docManager->getDocumentTrackingHistory($doc_id);
$is_overdue = (!empty($doc['due_date']) && strtotime($doc['due_date']) < strtotime('today'));

// --- FIXED ADDRESS & RECIPIENT LOGIC ---
$full_offices = $docManager->getFullOfficeList($doc_id);
if ($full_offices) {
    $display_offices = $full_offices;
} else {
    $raw_addr = $doc['address_name'] ?? 'Internal Routing';
    $display_offices = preg_replace('/\s+and\s+\d+\s+more/i', '', $raw_addr);
}

$all_recipients = $docManager->getRecipientsByDocument();
$recipients = $all_recipients[$doc_id] ?? [];
$dest_names_html = '<span class="text-muted fst-italic">No specific personnel assigned</span>';

if (in_array($doc['route_type'], ['outside_dti', 'within_dti'])) {
    $stmtExt = $pdo->prepare("SELECT contact_person FROM records_externalrecipient WHERE document_id = ?");
    $stmtExt->execute([$doc_id]);
    $ext_contacts = $stmtExt->fetchAll(PDO::FETCH_COLUMN);
    $valid_contacts = array_filter($ext_contacts, function($c) { return trim($c) !== ''; });
    if (!empty($valid_contacts)) {
        $dest_names_html = implode(', ', array_map('htmlspecialchars', $valid_contacts));
    }
} else {
    if (!empty($recipients)) {
        $clean_names = array_map(function($person) { return explode(' (', $person)[0]; }, $recipients);
        $dest_names_html = implode(', ', array_map('htmlspecialchars', array_unique($clean_names)));
    }
}

// --- VISUAL TRACKER LOGIC ---
$cat = strtoupper(trim($doc['status_category'] ?? ''));
$nam = strtoupper(trim($doc['status_name'] ?? ''));
$combined_status = $cat . '|' . $nam;
$level = 1;
$is_rejected = (strpos($combined_status, 'REJECT') !== false || strpos($combined_status, 'CANCEL') !== false);

if (strpos($combined_status, 'CLOSE') !== false || strpos($combined_status, 'DISPATCHED') !== false) { $level = 5; }
elseif (strpos($combined_status, 'FOR DISPATCH') !== false || strpos($combined_status, 'FOR-DISPATCH') !== false) { $level = 4; }
elseif (strpos($combined_status, 'APPROV') !== false && strpos($combined_status, 'FOR') === false) { $level = 3; }
elseif (strpos($combined_status, 'APPROVAL') !== false || strpos($combined_status, 'ONGOING') !== false) { $level = 2; }

$step1 = 'completed';
$step2 = ($level > 2) ? 'completed' : (($level == 2) ? 'active' : '');
$step3 = ($level > 3) ? 'completed' : (($level == 3) ? 'active' : '');
$step4 = ($level > 4) ? 'completed' : (($level == 4) ? 'active' : '');
$step5 = ($level == 5) ? 'completed' : '';
if ($is_rejected) {
    if ($level <= 2) $step2 = 'danger';
    elseif ($level == 3) $step3 = 'danger';
    elseif ($level == 4) $step4 = 'danger';
    elseif ($level == 5) $step5 = 'danger';
}
$progress_width = ($level == 2) ? 25 : (($level == 3) ? 50 : (($level == 4) ? 75 : (($level == 5) ? 100 : 0)));

$is_to_ro = in_array($doc['route_type'] ?? '', ['outside_dti', 'within_dti']);
$label_step4 = $is_to_ro ? 'Waiting to Dispatch' : 'Waiting to be Received';
$label_step5 = $is_to_ro ? 'Dispatch' : 'Received';
$icon_step4 = $is_to_ro ? '<i class="fa-solid fa-boxes-packing"></i>' : '<i class="fa-solid fa-inbox"></i>';
$icon_step5_success = $is_to_ro ? '<i class="fa-solid fa-paper-plane"></i>' : '<i class="fa-solid fa-check-double"></i>';

$page_title = "Dispatch Document - " . htmlspecialchars($doc['dts_no']);

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/document.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/accept_docu.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/track.css">
<style>
    .data-group { margin-bottom: 0; }
    .visual-stepper::before { display: none !important; }
    .data-value { font-weight: 700 !important; color: #0f172a !important; font-size: 0.95rem !important; }
    .textarea-style { background-color: #f8fafc !important; border: 1px solid #cbd5e1 !important; color: #0f172a !important; font-weight: 600 !important; border-radius: 8px !important; }
    .info-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 15px; height: 100%; text-align: left; }
    .section-divider { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; margin-top: 25px; margin-bottom: 15px; display: flex; align-items: center; }
    .section-divider::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; margin-left: 10px; }

     .activity-section {
        background: #ffffff;
        border-radius: 12px;
    }
    .activity-title {
        font-size: 1.4rem;
        font-weight: 800;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 2px;
    }
    .activity-subtitle {
        color: #64748b;
        font-size: 0.95rem;
        margin-bottom: 30px;
    }

    /* Vertical Timeline Line */
    .timeline-wrapper {
        position: relative;
        padding-left: 45px;
    }
    .timeline-wrapper::before {
        content: "";
        position: absolute;
        left: 17px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e2e8f0;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 25px;
    }

    /* Green Check Icon */
    .timeline-dot {
        position: absolute;
        left: -38px;
        top: 5px;
        width: 22px;
        height: 22px;
        background: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #10b981; /* Green color */
        z-index: 2;
        font-size: 1.1rem;
    }

    /* The Log Card */
    .activity-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .log-header-action {
        font-weight: 800;
        font-size: 0.95rem;
        color: #0f172a;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .log-header-date {
        font-size: 0.8rem;
        color: #94a3b8;
        margin-bottom: 12px;
    }

    .log-body-remarks {
        font-size: 0.95rem;
        color: #64748b;
        line-height: 1.5;
        margin-bottom: 15px;
    }

    /* Divider inside card */
    .log-divider {
        border-top: 1px solid #f1f5f9;
        margin: 0 -18px 15px -18px;
    }

    /* User Info Footer */
    .log-footer {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .user-avatar-icon {
        font-size: 1.5rem;
        color: #64748b;
        margin-top: 2px;
    }
    .user-details .user-name {
        display: block;
        font-weight: 700;
        color: #1e293b;
        font-size: 0.9rem;
    }
    .user-details .user-dept {
        display: block;
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.3;
    }
</style>
';

$extra_js = '
<script src="' . BASE_URL . 'static/js/track.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-3">
    <div class="mb-3">
        <a href="roOutgoing.php" class="btn-back text-decoration-none text-secondary">
            <i class="fa-solid fa-chevron-left me-2"></i>
            <span class="fw-bold">Back to Queue</span>
        </a>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="detail-card border rounded shadow-sm bg-white p-4">

        <div class="detail-header d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <h5 class="fw-bold mb-0">DTS No. <span class="text-primary"><?= htmlspecialchars($doc['dts_no']) ?></span></h5>
            <?php if ($is_overdue): ?>
                <span class="status overdue">OVER DUE</span>
            <?php else: ?>
                <span class="status <?= strtolower(str_replace(' ', '-', $doc['status_category'])) ?>">
                    <?= htmlspecialchars($doc['status_name']) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- STATUS TRACKER -->
        <div class="bg-light border rounded p-4 mb-4 shadow-sm">
            <h6 class="fw-bold text-dark mb-4 text-center text-uppercase" style="letter-spacing: 1px;"><i class="fa-solid fa-route text-secondary me-2"></i> Document Status</h6>
            <div class="position-relative mt-4 mb-2">
                <div style="position: absolute; top: 15px; left: 10%; right: 10%; height: 4px; background: #e2e8f0; z-index: 1;"></div>
                <div style="position: absolute; top: 15px; left: 10%; width: calc(80% * <?= $progress_width ?> / 100); height: 4px; background: <?= $is_rejected ? '#dc3545' : '#10b981' ?>; z-index: 2; transition: width 0.5s ease;"></div>
                <div class="visual-stepper d-flex justify-content-between position-relative" style="z-index: 3; padding: 0;">
                    <div class="step <?= $step1 ?>"><div class="circle"><i class="fa-solid fa-file-import"></i></div><div class="label">Encoded</div></div>
                    <div class="step <?= $step2 ?>"><div class="circle"><i class="fa-solid fa-file-signature"></i></div><div class="label">For Approval</div></div>
                    <div class="step <?= $step3 ?>"><div class="circle"><i class="fa-solid fa-stamp"></i></div><div class="label">Approved</div></div>
                    <div class="step <?= $step4 ?>"><div class="circle"><?= $icon_step4 ?></div><div class="label"><?= $label_step4 ?></div></div>
                    <div class="step <?= $step5 ?>"><div class="circle"><?php if($is_rejected): ?><i class="fa-solid fa-xmark"></i><?php else: ?><?= $icon_step5_success ?><?php endif; ?></div><div class="label"><?= ($is_rejected) ? 'Cancelled/Rejected' : $label_step5 ?></div></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- LEFT: DETAILS -->
            <div class="col-lg-8">
                <h6 class="border-bottom pb-2 mb-3 text-secondary" style="font-size: 0.9rem;"><i class="fa-solid fa-circle-info me-2"></i>Document Details</h6>

                <div class="row g-3 mb-3">
                    <div class="col-md-6"><div class="data-group"><label class="text-muted small text-uppercase">Classification</label><p class="data-value text-primary"><?= htmlspecialchars($doc['class_name'] ?? 'N/A') ?></p></div></div>
                    <div class="col-md-6"><div class="data-group"><label class="text-muted small text-uppercase">Document Type</label><p class="data-value"><?= htmlspecialchars($doc['type_name'] ?? 'N/A') ?></p></div></div>
                    <div class="col-md-6"><div class="data-group"><label class="text-muted small text-uppercase">Due Date</label><p class="data-value text-danger"><?= !empty($doc['due_date']) ? date('M d, Y', strtotime($doc['due_date'])) : 'None' ?></p></div></div>
                    <div class="col-md-6"><div class="data-group"><label class="text-muted small text-uppercase">Signatory</label><p class="data-value"><?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? ''))) ?: 'N/A' ?></p></div></div>
                </div>

                <div class="row mb-3">
                    <div class="col-12"><div class="data-group"><label class="text-muted small text-uppercase">Subject</label><div class="data-value textarea-style py-2 px-3"><?= htmlspecialchars($doc['subject']) ?></div></div></div>
                </div>

                <h6 class="border-bottom pb-2 mb-2 text-secondary mt-4" style="font-size: 0.9rem;"><i class="fa-solid fa-location-dot me-2"></i>Destination Information</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="data-group">
                            <label class="text-muted small text-uppercase">Office / Agency</label>
                            <div class="data-value textarea-style py-2 px-3"><?= htmlspecialchars($display_offices) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="data-group">
                            <label class="text-muted small text-uppercase">Recipient Name(s)</label>
                            <div class="data-value textarea-style py-2 px-3"><?= $dest_names_html ?></div>
                        </div>
                    </div>
                </div>

                <h6 class="border-bottom pb-2 mb-2 text-secondary mt-4" style="font-size: 0.9rem;"><i class="fa-solid fa-circle-info me-2"></i>Additional Details</h6>
                <div class="row g-3 mb-3">
                    <div class="col-12"><div class="data-group"><label class="text-muted small text-uppercase">Particulars / Remarks</label><div class="data-value textarea-style py-2 px-3"><?= nl2br(htmlspecialchars($doc['particulars'] ?? 'No particulars provided.')) ?></div></div></div>
                </div>

                <div class="section-divider">Attachments</div>
                <?php if (empty($attachments)): ?>
                    <p class="text-muted small ms-2">No files attached.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($attachments as $att):
                            $ext = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                            $icon = ($ext === 'pdf') ? 'fa-file-pdf text-danger' : 'fa-file-word text-primary';
                        ?>
                            <div class="info-box d-flex align-items-center">
                                <i class="fa-solid <?= $icon ?> fs-4 me-3"></i>
                                <div class="flex-grow-1">
                                    <div class="info-value small"><?= basename($att['file_path']) ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;">Click to view attachment</div>
                                </div>
                                <a href="<?= BASE_URL . $att['file_path'] ?>" target="_blank" class="text-muted"><i class="fa-solid fa-up-right-from-square"></i></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: ACTIVITY LOG -->
<div class="col-lg-4">
    <div class="activity-section p-2">
        <div class="activity-title">
            <i class="fa-solid fa-clock-rotate-left"></i> Activity Log
        </div>
        <div class="activity-subtitle">Activity log for this document.</div>

        <div class="timeline-wrapper">
            <?php if(empty($history_logs)): ?>
                <p class="text-muted small fst-italic">No history records found.</p>
            <?php else: ?>
                <?php foreach ($history_logs as $index => $log):
                    $hidden_class = ($index >= 4) ? 'd-none extra-log' : '';
                ?>
                <div class="timeline-item <?= $hidden_class ?>">
                    <!-- Green Check Icon -->
                    <div class="timeline-dot">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <!-- Log Card -->
                    <div class="activity-card">
                        <div class="log-header-action">
                            <?= htmlspecialchars(strtoupper($log['action_taken'])) ?>
                        </div>
                        <div class="log-header-date">
                            <?= date('F d, Y - h:i A', strtotime($log['timestamp'])) ?>
                        </div>

                        <div class="log-body-remarks">
                            <?= htmlspecialchars($log['remarks']) ?>
                        </div>

                        <div class="log-divider"></div>

                        <div class="log-footer">
                            <div class="user-avatar-icon">
                                <i class="fa-solid fa-user-circle"></i>
                            </div>
                            <div class="user-details">
                                <span class="user-name"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></span>
                                <span class="user-dept"><?= htmlspecialchars($log['division_name']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(count($history_logs) > 4): ?>
                    <div class="text-center">
                        <button type="button" id="toggleLogsBtn" class="btn btn-sm btn-link fw-bold text-decoration-none text-primary" onclick="toggleActivityLogs()">
                            Show More
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

        <div class="footer-action-container pt-3 border-top mt-4 d-flex justify-content-between align-items-center">
            <div class="created-info">
                <div class="fw-bold text-dark small">Created By: <span class="text-primary"><?= htmlspecialchars($doc['c_fname'].' '.$doc['c_lname']) ?></span></div>
                <div class="text-muted" style="font-size: 0.7rem;">
                    <?= htmlspecialchars($doc['c_division']) ?>
                    <p class="mb-0"><?= date('F d, Y h:i A', strtotime($doc['created_at'])) ?></p>
                </div>
            </div>

            <?php if ($level < 5 && !$is_rejected): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-blue btn-sm px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#dispatchModal">
                        <i class="fa-solid fa-paper-plane me-2"></i> Mark as Dispatched
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($level < 5 && !$is_rejected): ?>
<div class="modal fade" id="dispatchModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content custom-modal" style="border: 2px solid #0d6efd;">
            <form action="../../controllers/dispatch.php" method="POST">
                <div class="modal-body text-center p-4">
                    <i class="fa-solid fa-paper-plane text-primary mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-dark">Confirm Dispatch?</h5>
                    <p class="text-muted" style="font-size: 0.9rem;">Are you sure you want to mark document <b><?= htmlspecialchars($doc['dts_no']) ?></b> as dispatched? This will officially CLOSE the document.</p>
                    <input type="hidden" name="action" value="dispatch_document">
                    <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                    <div class="d-flex flex-column gap-2 mt-4">
                        <button type="submit" class="btn btn-blue fw-bold w-100">Yes, Dispatch</button>
                        <button type="button" class="btn btn-light w-100 border" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>