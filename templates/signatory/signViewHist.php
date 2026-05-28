<?php
// templates/signatory/signViewHist.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Signatory') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$doc_id = $_GET['id'] ?? null;
if (!$doc_id) {
    header("Location: signHistory.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Fetch Document Details
$stmt = $pdo->prepare("
    SELECT d.*, c.name as class_name, t.name as type_name, s.name as status_name, s.category as status_category,
           orig.name as origin_name, addr.name as address_name, u.first_name as c_fname, u.last_name as c_lname,
           p.role as c_role, divi.name as c_division, sig.first_name as sig_fname, sig.last_name as sig_lname
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
    header("Location: signHistory.php");
    exit;
}

$attachments = $docManager->getDocumentAttachments($doc_id);
$history_logs = $docManager->getDocumentTrackingHistory($doc_id);

// IDENTIFY RECEIVING OFFICER (Only for documents created by Division)
$receiving_officer = 'N/A';
if ($doc['c_role'] === 'Division') {
    foreach ($history_logs as $log) {
        if (isset($log['role']) && $log['role'] === 'RO') {
            $receiving_officer = $log['first_name'] . ' ' . $log['last_name'];
            break; // Grab the first Records Officer involved
        }
    }
}

$full_offices = $docManager->getFullOfficeList($doc_id);
$display_address = $full_offices ?: ($doc['address_name'] ?? 'Internal Routing');


// Recipient Logic (Same as roViewHist)
$all_recipients = $docManager->getRecipientsByDocument();
$recipients = $all_recipients[$doc_id] ?? [];
$dest_names_html = 'No specific personnel assigned';

if (in_array($doc['route_type'], ['outside_dti', 'within_dti'])) {
    $stmtExt = $pdo->prepare("SELECT contact_person FROM records_externalrecipient WHERE document_id = ?");
    $stmtExt->execute([$doc_id]);
    $ext_contacts = $stmtExt->fetchAll(PDO::FETCH_COLUMN);
    $valid_contacts = array_filter($ext_contacts, function ($c) {
        return trim($c) !== ''; });
    if (!empty($valid_contacts)) {
        $dest_names_html = implode(', ', array_map('htmlspecialchars', $valid_contacts));
    }
} else {
    if (!empty($recipients)) {
        $clean_names = array_map(function ($person) {
            return explode(' (', $person)[0]; }, $recipients);
        $dest_names_html = implode(', ', array_map('htmlspecialchars', $clean_names));
    }
}

/* VISUAL TRACKER LOGIC */
$combined_status = strtoupper(trim($doc['status_category'] ?? '')) . '|' . strtoupper(trim($doc['status_name'] ?? ''));
$is_rejected = (strpos($combined_status, 'REJECT') !== false || strpos($combined_status, 'CANCEL') !== false);
$level = 1;

if ($is_rejected) {
    $level = 5;
} elseif (strpos($combined_status, 'CLOSE') !== false || strpos($combined_status, 'DISPATCHED') !== false || strpos($combined_status, 'RECEIVED') !== false) {
    $level = 5;
} elseif (strpos($combined_status, 'FOR DISPATCH') !== false || strpos($combined_status, 'FOR-DISPATCH') !== false || strpos($combined_status, 'ONGOING') !== false || strpos($combined_status, 'ROUTED') !== false || strpos($combined_status, 'SENT') !== false) {
    $level = 4;
} elseif (strpos($combined_status, 'APPROV') !== false && strpos($combined_status, 'FOR') === false) {
    $level = 3;
} elseif (strpos($combined_status, 'APPROVAL') !== false) {
    $level = 2;
}

$progress_map = [1 => 0, 2 => 25, 3 => 50, 4 => 75, 5 => 100];
$progress_width = $progress_map[$level] ?? 0;

$step1 = 'active';
$step2 = ($level >= 2) ? 'active' : '';
$step3 = ($level >= 3) ? 'active' : '';
$step4 = ($level >= 4) ? 'active' : '';
$step5 = ($level >= 5) ? 'active' : '';
if ($is_rejected) {
    $step5 = 'danger';
}

$is_to_ro = in_array($doc['route_type'] ?? '', ['outside_dti', 'within_dti']);
$label_step4 = $is_to_ro ? 'Waiting to Dispatch' : 'Waiting to be Received';
$label_step5 = $is_to_ro ? 'Dispatched' : 'Received';
$icon_step4 = $is_to_ro ? '<i class="fa-solid fa-boxes-packing"></i>' : '<i class="fa-solid fa-inbox"></i>';
$icon_step5_success = $is_to_ro ? '<i class="fa-solid fa-paper-plane"></i>' : '<i class="fa-solid fa-check-double"></i>';

$page_title = "Historical Record - " . $doc['dts_no'];

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/document.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/track.css">
<style>
    body { background-color: #f1f5f9; }

    /* Visual Tracker Styling */
    .tracker-container {
        position: relative;
        padding: 40px 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 15px;
    }
    .tracker-line-bg {
        position: absolute;
        top: 62px;
        left: 8%;
        right: 8%;
        height: 4px;
        background: #e2e8f0;
        z-index: 1;
    }
    .tracker-line-fill {
        position: absolute;
        top: 62px;
        left: 8%;
        height: 4px;
        background: #10b981;
        z-index: 2;
        transition: width 0.6s ease;
    }
    /* Info Boxes */
    .info-box {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 15px;
        height: 100%;
        text-align: left;
    }
    .info-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    .info-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: #1e293b;
    }
    .section-divider {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #94a3b8;
        margin-top: 25px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    .section-divider::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; margin-left: 10px; }

    /* History Timeline */
    .track-item { display: flex; position: relative; padding-bottom: 25px; }
    .track-item::before {
        content: ""; position: absolute; left: 17px; top: 35px; bottom: 0; width: 2px; background: #e2e8f0;
    }
    .track-item:last-child::before { display: none; }
    .track-icon {
        width: 35px; height: 35px; border-radius: 50%; background: #fff; border: 1px solid #e2e8f0;
        display: flex; align-items: center; justify-content: center; z-index: 2; flex-shrink: 0;
    }
    .history-box {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-left: 15px; width: 100%;
    }
    .visual-stepper::before { display: none !important; }
</style>
';

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-4">
    <!-- Header -->
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="signHistory.php" class="text-decoration-none text-muted small fw-bold">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to History </a>
            <h2 class="fw-bold text-dark mt-2 mb-0"><?= htmlspecialchars($doc['dts_no']) ?></h2>
        </div>
    </div>
    <!-- DOCUMENT STATUS TRACKER -->
    <div class="bg-white border rounded-4 p-4 mb-4 shadow-sm">
        <h6 class="fw-bold text-dark mb-4 text-center text-uppercase" style="letter-spacing: 1px;"><i
                class="fa-solid fa-route text-secondary me-2"></i> DOCUMENT STATUS</h6>
        <div class="position-relative mt-4 mb-2">
            <div
                style="position: absolute; top: 22px; left: 10%; right: 10%; height: 4px; background: #e2e8f0; z-index: 1;">
            </div>
            <div
                style="position: absolute; top: 22px; left: 10%; width: calc(80% * <?= $progress_width ?> / 100); height: 4px; background: <?= $is_rejected ? '#dc3545' : '#10b981' ?>; z-index: 2; transition: width 0.5s ease;">
            </div>
            <div class="visual-stepper d-flex justify-content-between position-relative"
                style="z-index: 3; padding: 0;">
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
                    <div class="circle"><?= $icon_step4 ?></div>
                    <div class="label"><?= $label_step4 ?></div>
                </div>
                <div class="step <?= $step5 ?>">
                    <div class="circle">
                        <?php if ($is_rejected): ?><i class="fa-solid fa-xmark"></i>
                        <?php else: ?>    <?= $icon_step5_success ?><?php endif; ?>
                    </div>
                    <div class="label"><?= ($is_rejected) ? 'Rejected' : $label_step5 ?></div>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <!-- LEFT COLUMN: DOCUMENT DETAILS (8 units) -->
            <div class="col-lg-8">
                <div class="bg-white rounded-4 shadow-sm p-4 border h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-circle-info me-2 text-primary"></i>
                                Document Details</h5>
                            <p class="text-muted small mb-0">Historical metadata and routing information.</p>
                        </div>
                        <span
                            class="badge rounded-pill bg-secondary px-3 py-2"><?= htmlspecialchars($doc['status_name']) ?></span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">Classification</div>
                                <div class="info-value text-primary">
                                    <?= htmlspecialchars($doc['class_name'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">Document Type</div>
                                <div class="info-value"><?= htmlspecialchars($doc['type_name'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">Due Date</div>
                                <div class="info-value text-danger">
                                    <?= !empty($doc['due_date']) ? date('M d, Y', strtotime($doc['due_date'])) : 'None' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">Particulars</div>
                                <div class="info-value fw-normal"><?= htmlspecialchars($doc['particulars'] ?? 'N/A') ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-box">
                                <div class="info-label">Subject</div>
                                <div class="info-value fs-6"><?= htmlspecialchars($doc['subject']) ?></div>
                            </div>
                        </div>
                    </div>
                    <!-- ROUTING INFORMATION SECTION -->
                    <div class="section-divider">Routing Information</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">Signatory (Authority)</div>
                                <div class="info-value">
                                    <?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? ''))) ?: 'None' ?>
                                </div>
                            </div>
                        </div>
                        <!-- RECEIVING OFFICER FIELD -->
                        <?php if ($doc['c_role'] === 'Division'): ?>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-label">Receiving Officer (Processor)</div>
                                    <div class="info-value"><?= htmlspecialchars($receiving_officer) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- ADDRESS SECTION -->
                    <div class="section-divider">Address Information</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">Division / Group / Office Name</div>
                                <!-- FIXED: Pulls the full list from $display_address -->
                                <div class="info-value small"><?= htmlspecialchars($display_address) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">Recipient Name(s)</div>
                                <div class="info-value small"><?= $dest_names_html ?></div>
                            </div>
                        </div>
                    </div>
                    <!-- ORIGIN / SENDER INFORMATION SECTION: Hidden for Outgoing Division Documents -->
                    <?php if (!empty($doc['origin_name'])): ?>
                        <div class="section-divider">Origin Information</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-label">Office / Agency</div>
                                    <div class="info-value"><?= htmlspecialchars($doc['origin_name']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-label">Sender / Contact Person</div>
                                    <div class="info-value"><?= htmlspecialchars($doc['sender'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($doc['c_role'] !== 'Division'): ?>
                        <div class="section-divider">Sender Information</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-label">Division</div>
                                    <div class="info-value"><?= htmlspecialchars($doc['c_division'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-label">Sender Name</div>
                                    <div class="info-value">
                                        <?= htmlspecialchars(trim($doc['c_fname'] . ' ' . $doc['c_lname'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
                                    <a href="<?= BASE_URL . $att['file_path'] ?>" target="_blank" class="text-muted"><i
                                            class="fa-solid fa-up-right-from-square"></i></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- RIGHT COLUMN: TRACKING HISTORY (4 units) -->
            <div class="col-lg-4">
                <div class="bg-white rounded-4 shadow-sm p-4 border h-100">
                    <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-clock-rotate-left me-2 text-secondary"></i>
                        Activity Log</h5>
                    <p class="text-muted small mb-4">Activity log for this document.</p>
                    <div class="tracking-timeline">
                        <?php foreach ($history_logs as $idx => $log):
                            $is_collapsible = ($idx >= 4);
                            $entry_class = $is_collapsible ? 'track-item-collapsible d-none' : '';
                            ?>
                            <div class="track-item <?= $entry_class ?>">
                                <div class="track-icon">
                                    <i class="fa-solid fa-circle-check text-success" style="font-size: 0.8rem;"></i>
                                </div>
                                <div class="history-box shadow-sm">
                                    <div class="fw-bold text-dark small mb-1"><?= htmlspecialchars($log['action_taken']) ?>
                                    </div>
                                    <div class="text-muted mb-2" style="font-size: 0.7rem;">
                                        <?= date('M d, Y - h:i A', strtotime($log['timestamp'])) ?></div>
                                    <p class="text-secondary small mb-2"><?= htmlspecialchars($log['remarks']) ?></p>
                                    <div class="pt-2 border-top mt-2 d-flex align-items-center">
                                        <i class="fa-solid fa-user-circle text-muted me-2"></i>
                                        <div style="font-size: 0.75rem;">
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></div>
                                            <div class="text-muted">
                                                <?= htmlspecialchars($log['division_name'] ?? 'System') ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($history_logs) > 4): ?>
                            <div class="text-center mt-3">
                                <button id="toggle-history-btn" class="btn btn-sm btn-link text-decoration-none fw-bold">See
                                    More History</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer Metadata -->
        <div class="mt-4 pt-4 border-top d-flex align-items-center">
            <div class="creator-avatar me-2"
                style="width: 34px; height: 34px; background: #e2e8f0; color: #475569; display:flex; align-items:center; justify-content:center; border-radius:50%;">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="text-dark mb-0">
                <div class="fw-bold text-dark small">Created By by: <span
                        class="text-primary"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span></div>
                <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($doc['c_division']) ?>
                    <p>
                        <?= date('F d, Y h:i A', strtotime($doc['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const collapsibles = document.querySelectorAll('.track-item-collapsible');
        const toggleButton = document.getElementById('toggle-history-btn');
        if (toggleButton) {
            toggleButton.addEventListener('click', function () {
                const isHidden = collapsibles[0].classList.contains('d-none');
                collapsibles.forEach(el => el.classList.toggle('d-none'));
                toggleButton.textContent = isHidden ? 'Show Less' : 'See More History';
            });
        }
    });
</script>
<?php require_once BASE_PATH . 'includes/header.php'; ?>