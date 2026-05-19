<?php
// templates/signatory/signViewHist.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RD', 'ARD'])) {
    header("Location: " . BASE_URL . "login.php"); exit;
}

$doc_id = $_GET['id'] ?? null;
if (!$doc_id) { header("Location: signHistory.php"); exit; }

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Fetch Document Details
$stmt = $pdo->prepare("
    SELECT d.*, c.name as class_name, t.name as type_name, s.name as status_name, s.category as status_category,
           orig.name as origin_name, addr.name as address_name, u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division, sig.first_name as sig_fname, sig.last_name as sig_lname
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

if (!$doc) { header("Location: signHistory.php"); exit; }

$page_title = "Historical Record - " . htmlspecialchars($doc['dts_no']);
$attachments = $docManager->getDocumentAttachments($doc_id);
$history_logs = $docManager->getDocumentTrackingHistory($doc_id);

// Visual Tracker Calculation
$combined_status = strtoupper(trim($doc['status_category'])) . '|' . strtoupper(trim($doc['status_name']));
$level = 1; $is_rejected = false;
if (strpos($combined_status, 'REJECT') !== false || strpos($combined_status, 'CANCEL') !== false) { $is_rejected = true; }
if (strpos($combined_status, 'CLOSE') !== false || strpos($combined_status, 'DISPATCHED') !== false) { $level = 5; }
elseif (strpos($combined_status, 'FOR DISPATCH') !== false || strpos($combined_status, 'FOR-DISPATCH') !== false) { $level = 4; }
elseif (strpos($combined_status, 'APPROV') !== false && strpos($combined_status, 'FOR') === false) { $level = 3; }
elseif (strpos($combined_status, 'APPROVAL') !== false || strpos($combined_status, 'ONGOING') !== false) { $level = 2; }

$step1 = 'completed'; $step2 = ($level > 2) ? 'completed' : (($level == 2) ? 'active' : ''); $step3 = ($level > 3) ? 'completed' : (($level == 3) ? 'active' : ''); $step4 = ($level > 4) ? 'completed' : (($level == 4) ? 'active' : ''); $step5 = ($level == 5) ? 'completed' : '';
if ($is_rejected) { if ($level <= 2) $step2 = 'danger'; elseif ($level == 3) $step3 = 'danger'; elseif ($level == 4) $step4 = 'danger'; elseif ($level == 5) $step5 = 'danger'; }
$progress_width = ($level == 2) ? 25 : (($level == 3) ? 50 : (($level == 4) ? 75 : (($level == 5) ? 100 : 0)));

// DYNAMIC TRACKER LABELS & ICONS (Based on Destination)
$is_to_ro = in_array($doc['route_type'] ?? '', ['outside_dti', 'within_dti']);

$label_step4 = $is_to_ro ? 'Waiting to Dispatch' : 'Waiting to be Received';
$label_step5 = $is_to_ro ? 'Dispatch' : 'Received';

$icon_step4 = $is_to_ro ? '<i class="fa-solid fa-boxes-packing"></i>' : '<i class="fa-solid fa-inbox"></i>';
$icon_step5_success = $is_to_ro ? '<i class="fa-solid fa-paper-plane"></i>' : '<i class="fa-solid fa-check-double"></i>';
// ==========================================

$extra_css = '<link rel="stylesheet" href="' . BASE_URL . 'static/css/document.css"><link rel="stylesheet" href="' . BASE_URL . 'static/css/accept_docu.css"><link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css"><link rel="stylesheet" href="' . BASE_URL . 'static/css/track.css"><style>.visual-stepper::before { display: none !important; }</style>';
$extra_js = '<script src="' . BASE_URL . 'static/js/track.js"></script>';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-3">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <a href="signHistory.php" class="btn-back text-decoration-none text-secondary"><i class="fa-solid fa-chevron-left me-2"></i><span class="fw-bold">Back to History</span></a>
    </div>

    <div class="detail-card border rounded shadow-sm bg-white p-4">
        <div class="detail-header d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <h5 class="fw-bold mb-0">DTS No. <span class="text-primary"><?= htmlspecialchars($doc['dts_no']) ?></span></h5>
            <span class="status <?= strtolower(str_replace(' ', '-', $doc['status_category'])) ?>"><?= htmlspecialchars($doc['status_name']) ?></span>
        </div>

        <div class="bg-light border rounded p-4 mb-4 shadow-sm">
            <h6 class="fw-bold text-dark mb-4 text-center text-uppercase" style="letter-spacing: 1px;"><i class="fa-solid fa-route text-secondary me-2"></i> Document Status</h6>
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
                    <div class="circle"><?= $icon_step4 ?></div>
                    <div class="label"><?= $label_step4 ?></div>
                </div>
                <div class="step <?= $step5 ?>">
                    <div class="circle">
                        <?php if($is_rejected): ?>
                            <i class="fa-solid fa-xmark"></i>
                        <?php else: ?>
                            <?= $icon_step5_success ?>
                        <?php endif; ?>
                    </div>
                    <div class="label"><?= ($is_rejected) ? 'Rejected' : $label_step5 ?></div>
                </div>
            </div>

        <div class="border rounded p-4 mb-5 shadow-sm" style="background-color: #f8fafc;">
            <h6 class="fw-bold text-dark mb-4"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Activity Log Timeline</h6>
            <div class="timeline-container">
                <?php foreach ($history_logs as $index => $log):
                    $icon = 'fa-arrow-right'; $bg = '#1d4ed8';
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
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary rounded-pill px-2 py-1" style="font-size: 0.7rem;"><?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?></span>
                        </div>
                        <p class="mb-2 text-secondary" style="font-size: 0.85rem;"><?= htmlspecialchars($log['remarks']) ?></p>
                        <div class="text-muted" style="font-size: 0.75rem;"><i class="fa-solid fa-user me-1 text-primary"></i> <span class="fw-bold text-dark"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></span> (<?= htmlspecialchars($log['role'] ?? 'User') ?> - <?= htmlspecialchars($log['division_name'] ?? 'System') ?>)</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(count($history_logs) > 3): ?>
                    <div class="text-center mt-4"><button type="button" id="toggleLogsBtn" class="btn btn-sm btn-outline-primary rounded-pill px-4 py-2 fw-bold" onclick="toggleActivityLogs()">See More <i class="fa-solid fa-chevron-down ms-1"></i></button></div>
                <?php endif; ?>
            </div>
        </div>

        <h6 class="border-bottom pb-2 mb-4 text-secondary mt-4" style="font-size: 0.9rem;"><i class="fa-solid fa-circle-info me-2"></i>Historical Data (Read-Only)</h6>

        <div class="row mb-3"><div class="col-12"><div class="data-group"><label class="text-muted text-uppercase small">Subject</label><div class="data-value textarea-style text-dark bg-light py-2 px-3 border-0"><?= htmlspecialchars($doc['subject']) ?></div></div></div></div>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><div class="data-group"><label class="text-muted text-uppercase small">Destination / Agency</label><div class="data-value textarea-style bg-light text-dark py-2 px-3 border-0"><?= htmlspecialchars($doc['address_name'] ?? 'External Office') ?></div></div></div>
            <div class="col-md-6"><div class="data-group"><label class="text-muted text-uppercase small">Particulars</label><div class="data-value textarea-style bg-light text-dark py-2 px-3 border-0"><?= nl2br(htmlspecialchars($doc['particulars'] ?? 'No particulars provided.')) ?></div></div></div>
        </div>

        <?php if (!empty($attachments)): ?>
            <label class="text-muted text-uppercase small mt-3 mb-2 d-block">Original Attachments</label>
            <style> .file-link { text-decoration: underline; transition: color 0.2s ease; } .file-link:hover { color: #1d4ed8 !important; } </style>
            <ul class="list-group list-group-flush rounded border">
                <?php foreach ($attachments as $att):
                    $ext = strtolower(pathinfo($att['file_path'], PATHINFO_EXTENSION));
                    $icon = 'fa-file'; $color = 'text-secondary';

                    if (in_array($ext, ['pdf'])) { $icon = 'fa-file-pdf'; $color = 'text-danger'; }
                    elseif (in_array($ext, ['doc', 'docx'])) { $icon = 'fa-file-word'; $color = 'text-primary'; }
                    elseif (in_array($ext, ['xls', 'xlsx'])) { $icon = 'fa-file-excel'; $color = 'text-success'; }
                    elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) { $icon = 'fa-image'; $color = 'text-warning'; }

                    $file_name = htmlspecialchars(basename($att['file_path']));
                    $file_url = '../../uploads/' . $file_name;
                ?>
                    <li class="list-group-item py-2 px-3 bg-light">
                        <div class="d-flex align-items-center gap-2 text-truncate">
                            <i class="fa-solid <?= $icon ?> <?= $color ?>" style="font-size: 0.85rem;"></i>
                            <a href="<?= $file_url ?>" target="_blank" class="text-dark small fw-bold text-truncate file-link" title="<?= $file_name ?>">
                                <?= $file_name ?>
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php require_once BASE_PATH . 'includes/footer.php'; ?>