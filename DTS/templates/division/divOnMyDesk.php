<?php
// templates/division/divOnMyDesk.php
require_once '../../classes/database.php';

// Security Check: Only 'Division' role can access[cite: 1]
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Division') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "On My Desk";
$user_id = $_SESSION['user_id'];

// 1. LINK MODULAR CSS
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/modal.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
';

$success_msg = '';
$error_msg = '';

// ==========================================
// 1. HANDLE "ACCEPT & CLOSE" ACTION[cite: 1]
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_document') {
    $doc_id = $_POST['document_id'];

    try {
        $pdo->beginTransaction();

        $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE category = 'CLOSED' LIMIT 1");
        $closed_status_id = $stmtStatus->fetchColumn();

        if (!$closed_status_id) {
            throw new Exception("No 'CLOSED' status category found in configurations. Please ask Admin to create one.");
        }

        $stmtUpdateRec = $pdo->prepare("UPDATE records_documentrecipient SET has_received = 1, received_at = NOW() WHERE document_id = ? AND recipient_user_id = ?");
        $stmtUpdateRec->execute([$doc_id, $user_id]);

        $stmtUpdateDoc = $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdateDoc->execute([$closed_status_id, $doc_id]);

        $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('RECEIVED & CLOSED', 'Document was accepted and marked as closed by the assigned division user.', NOW(), ?, ?)");
        $stmtLog->execute([$user_id, $doc_id]);

        $pdo->commit();
        $success_msg = "Document successfully accepted and closed! It has been moved to your History.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error processing document: " . $e->getMessage();
    }
}

// ==========================================
// 2. FETCH REFERENCE DATA FOR DROPDOWNS[cite: 1]
// ==========================================
$doc_types = $pdo->query("SELECT name FROM records_documenttype ORDER BY name ASC")->fetchAll();
$classifications = $pdo->query("SELECT name FROM records_classification ORDER BY name ASC")->fetchAll();

// ==========================================
// 3. FETCH "ON MY DESK" DOCUMENTS[cite: 1]
// ==========================================
try {
    $stmt = $pdo->prepare("
        SELECT d.id, d.dts_no, d.subject, d.particulars, d.due_date, d.created_at, d.sender,
               c.name as classification,
               t.name as doc_type,
               s.name as status_name, s.category as status_category,
               u.first_name as c_fname, u.last_name as c_lname,
               divi.name as c_division,
               sig.first_name as s_fname, sig.last_name as s_lname,
               addr.name as address_name,
               orig.name as origin_name
        FROM records_documentrecipient dr
        JOIN records_document d ON dr.document_id = d.id
        LEFT JOIN records_classification c ON d.classification_id = c.id
        LEFT JOIN records_documenttype t ON d.document_type_id = t.id
        LEFT JOIN records_status s ON d.status_id = s.id
        LEFT JOIN records_address addr ON d.address_id = addr.id
        LEFT JOIN records_origin orig ON d.origin_id = orig.id
        LEFT JOIN auth_user u ON d.creator_id = u.id
        LEFT JOIN records_userprofile p ON u.id = p.user_id
        LEFT JOIN records_division divi ON p.division_id = divi.id
        LEFT JOIN auth_user sig ON d.signatory_id = sig.id
        WHERE dr.recipient_user_id = :uid AND s.category != 'CLOSED'
        ORDER BY d.created_at DESC
    ");
    $stmt->execute(['uid' => $user_id]);
    $desk_docs = $stmt->fetchAll();

    $attStmt = $pdo->query("SELECT document_id, file_path FROM records_documentattachment");
    $all_attachments = [];
    while ($row = $attStmt->fetch()) {
        $full_path = BASE_PATH . $row['file_path'];
        $filesize = file_exists($full_path) ? round(filesize($full_path) / 1024 / 1024, 2) . ' MB' : 'Unknown Size';

        $all_attachments[$row['document_id']][] = [
            'name' => basename($row['file_path']),
            'url' => BASE_URL . $row['file_path'],
            'size' => $filesize
        ];
    }
} catch (PDOException $e) {
    $error_msg = "Error loading your desk: " . $e->getMessage();
    $desk_docs = [];
}

// 4. LINK MODULAR JS
$extra_js = '
<script src="' . BASE_URL . 'static/js/filters.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-4">
    <div class="mb-4">
        <h2 class="text-dark fw-bold mb-0">On My Desk</h2>
        <p class="text-secondary">Review and accept active documents routed to you.</p>
    </div>
    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i
                class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>
    <!-- BULLETPROOF INLINE FILTER BAR (Original Order Restored)[cite: 1] -->
    <div class="filter-bar d-flex flex-nowrap align-items-center gap-3 mb-4 w-100"
        style="overflow-x: auto; padding-bottom: 5px;">
        <span class="text-muted fw-bold small text-nowrap flex-shrink-0">Filter by</span>
        <div class="input-group search-container flex-shrink-0 m-0" style="width: 250px;">
            <span class="input-group-text search-icon-group"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" id="searchInput" class="form-control custom-search-input"
                placeholder="Search DTS No. or Subject...">
        </div>
        <select id="typeFilter" class="form-select custom-select flex-shrink-0" style="width: 160px;">
            <option value="">Document Type</option>
            <?php foreach ($doc_types as $type): ?>
                <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="classFilter" class="form-select custom-select flex-shrink-0" style="width: 160px;">
            <option value="">Classification</option>
            <?php foreach ($classifications as $class): ?>
                <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="d-flex align-items-center gap-2 border px-2 py-1 rounded bg-white flex-shrink-0">
            <span class="text-muted small text-nowrap">From:</span>
            <input type="date" id="startDate" class="form-control form-control-sm border-0 text-secondary"
                style="width: 110px;">
            <span class="text-muted small text-nowrap">To:</span>
            <input type="date" id="endDate" class="form-control form-control-sm border-0 text-secondary"
                style="width: 110px;">
        </div>
    </div>
    <div class="table-container p-0">
        <div class="table-responsive">
            <table class="data-table" id="deskTable">
                <thead>
                    <tr>
                        <th>DTS NO.</th>
                        <!-- ADDED SUBJECT COLUMN -->
                        <th>SUBJECT</th>
                        <th>DOCUMENT TYPE</th>
                        <th>CLASSIFICATION</th>
                        <th>DEADLINE</th>
                        <th>STATUS</th>
                        <th>DATE & TIME RECEIVED</th>
                        <th>SENDER</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($desk_docs as $doc):
                        $is_overdue = false;
                        if ($doc['due_date'] && strtotime($doc['due_date']) < strtotime('today')) {
                            $is_overdue = true;
                        }
                        $doc_attachments = isset($all_attachments[$doc['id']]) ? $all_attachments[$doc['id']] : [];
                        $json_attachments = json_encode($doc_attachments);
                        ?>
                        <tr class="desk-row clickable-row" data-bs-toggle="modal" data-bs-target="#documentModal"
                            data-id="<?= $doc['id'] ?>" data-dts="<?= htmlspecialchars($doc['dts_no']) ?>"
                            data-subject="<?= htmlspecialchars($doc['subject']) ?>"
                            data-particulars="<?= htmlspecialchars($doc['particulars'] ?? 'No additional details provided.') ?>"
                            data-type="<?= htmlspecialchars($doc['doc_type']) ?>"
                            data-class="<?= htmlspecialchars($doc['classification']) ?>"
                            data-status="<?= htmlspecialchars($doc['status_name']) ?>"
                            data-statuscat="<?= htmlspecialchars(strtolower($doc['status_category'])) ?>"
                            data-origin="<?= htmlspecialchars($doc['origin_name'] ?? 'N/A') ?>"
                            data-address="<?= htmlspecialchars($doc['address_name'] ?? 'N/A') ?>"
                            data-sender="<?= htmlspecialchars($doc['sender'] ?? 'N/A') ?>"
                            data-creator="<?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?>"
                            data-creator-div="<?= htmlspecialchars($doc['c_division'] ?? 'System User') ?>"
                            data-deadline="<?= $doc['due_date'] ? date('F d, Y', strtotime($doc['due_date'])) : 'None' ?>"
                            data-isoverdue="<?= $is_overdue ? 'true' : 'false' ?>"
                            data-attachments='<?= htmlspecialchars($json_attachments, ENT_QUOTES, 'UTF-8') ?>'>
                            <!-- DTS AND SUBJECT SEPARATED -->
                            <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></td>
                            <td class="fw-bold text-dark search-target"><?= htmlspecialchars($doc['subject']) ?></td>
                            <td class="type-target"><?= htmlspecialchars($doc['doc_type']) ?></td>
                            <td class="class-target"><span
                                    class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($doc['classification']) ?></span>
                            </td>
                            <td>
                                <?php if ($doc['due_date']): ?>
                                    <span class="<?= $is_overdue ? 'text-danger fw-bold' : 'text-dark' ?>">
                                        <i class="fa-regular fa-calendar-xmark me-1"></i>
                                        <?= date('M d, Y', strtotime($doc['due_date'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_overdue): ?>
                                    <span class="status overdue">OVER DUE</span>
                                <?php else: ?>
                                    <span
                                        class="status <?= strtolower($doc['status_category']) ?>"><?= htmlspecialchars($doc['status_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-dark fw-bold"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                                <div class="text-muted" style="font-size: 0.8rem;">
                                    <?= date('h:i A', strtotime($doc['created_at'])) ?>
                                </div>
                                <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                            </td>
                            <td class="search-target">
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
                    <?php if (empty($desk_docs)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fa-solid fa-inbox mb-3 opacity-25" style="font-size: 3rem;"></i><br>
                                <h6 class="fw-bold text-secondary">Your Desk is Clear!</h6>
                                <p class="small mb-0">No active documents are currently routed to you.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- DOCUMENT MODAL utilizing modal.css classes -->
<div class="modal fade" id="documentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content custom-modal">
            <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-file-circle-check me-2 text-primary"></i> Review Incoming Document
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-md-5 border-end pe-4">
                        <div class="mb-3 data-group">
                            <label class="modal-label">DTS NO.</label>
                            <div class="data-value fw-bold text-primary" id="modalDTS"
                                style="background: transparent; border: none; padding: 0;"></div>
                        </div>
                        <div class="mb-3 data-group">
                            <label class="modal-label">DOCUMENT TYPE</label>
                            <div class="data-value fw-bold text-dark" id="modalType"
                                style="background: transparent; border: none; padding: 0;"></div>
                        </div>
                        <div class="mb-3 data-group">
                            <label class="modal-label">CLASSIFICATION</label>
                            <div><span class="badge bg-light text-dark border px-2 py-1" id="modalClass"></span></div>
                        </div>
                        <div class="mb-3 data-group">
                            <label class="modal-label">STATUS</label>
                            <div><span class="status" id="modalStatus"></span></div>
                        </div>
                        <div class="mb-3 data-group">
                            <label class="modal-label">DEADLINE</label>
                            <div id="modalDeadlineContainer" style="font-size: 0.95rem;"></div>
                        </div>
                        <div class="mb-3 data-group">
                            <label class="modal-label">ORIGIN</label>
                            <div class="data-value" id="modalOrigin"
                                style="background: transparent; border: none; padding: 0;"></div>
                        </div>
                        <div class="mb-3 data-group">
                            <label class="modal-label">ADDRESS</label>
                            <div class="data-value" id="modalAddress"
                                style="background: transparent; border: none; padding: 0;"></div>
                        </div>
                        <div class="mb-3 data-group">
                            <label class="modal-label">ROUTED FROM (Sender)</label>
                            <div class="data-value" id="modalSender"
                                style="background: transparent; border: none; padding: 0;"></div>
                        </div>
                        <div class="mb-2 data-group">
                            <label class="modal-label">CREATED BY</label>
                            <div class="creator-cell mt-1">
                                <div class="creator-avatar sm"><i class="fa-solid fa-user"></i></div>
                                <div class="creator-info">
                                    <span class="creator-name small" id="modalCreator"></span>
                                    <span class="creator-role smaller" id="modalCreatorDiv"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Right Column -->
                    <div class="col-md-7 ps-4">
                        <div class="mb-4 data-group">
                            <label class="modal-label">SUBJECT</label>
                            <h6 class="fw-bold text-dark" id="modalSubject" style="line-height: 1.4;"></h6>
                        </div>
                        <div class="mb-4 data-group">
                            <label class="modal-label">PARTICULARS</label>
                            <div class="data-value" id="modalParticulars"
                                style="min-height: 100px; white-space: pre-wrap;"></div>
                        </div>
                        <div class="mb-2 data-group">
                            <label class="modal-label mb-3"><i class="fa-solid fa-paperclip me-1"></i> ATTACHED
                                FILES</label>
                            <div id="modalAttachments"></div>
                        </div>
                    </div>
                </div>
                <form method="POST" action="divOnMyDesk.php">
                    <input type="hidden" name="action" value="accept_document">
                    <input type="hidden" name="document_id" id="modalDocId">
                    <div class="d-flex justify-content-end gap-2 border-top pt-4 mt-3">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Close Window</button>
                        <button type="submit" class="btn btn-blue px-4"
                            onclick="return confirm('Are you sure you want to Accept this document? It will be marked as CLOSED and moved to your history.');">
                            <i class="fa-solid fa-check-double me-2"></i> Accept & Close Document </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Initialize Universal Filters
        if (typeof TableFilter !== 'undefined') {
            new TableFilter('.desk-row');
        }

        // 2. Modal Population Logic
        const documentModal = document.getElementById('documentModal');
        if (documentModal) {
            documentModal.addEventListener('show.bs.modal', function (event) {
                const row = event.relatedTarget;
                document.getElementById('modalDocId').value = row.dataset.id;

                // Left Column
                document.getElementById('modalDTS').textContent = row.dataset.dts;
                document.getElementById('modalType').textContent = row.dataset.type;
                document.getElementById('modalClass').textContent = row.dataset.class;

                const statusEl = document.getElementById('modalStatus');
                const isOverdue = row.dataset.isoverdue === 'true';
                if (isOverdue) {
                    statusEl.textContent = 'OVER DUE';
                    statusEl.className = 'status overdue';
                } else {
                    statusEl.textContent = row.dataset.status;
                    statusEl.className = 'status ' + row.dataset.statuscat;
                }

                const deadlineVal = row.dataset.deadline;
                const deadlineContainer = document.getElementById('modalDeadlineContainer');
                if (deadlineVal === 'None') {
                    deadlineContainer.innerHTML = '<span class="text-muted small">None</span>';
                } else {
                    deadlineContainer.innerHTML = `<span class="${isOverdue ? 'text-danger fw-bold' : 'text-dark fw-bold'}"><i class="fa-regular fa-calendar-xmark me-1"></i> ${deadlineVal}</span>`;
                }

                document.getElementById('modalOrigin').textContent = row.dataset.origin;
                document.getElementById('modalAddress').textContent = row.dataset.address;
                document.getElementById('modalSender').textContent = row.dataset.sender;
                document.getElementById('modalCreator').textContent = row.dataset.creator;
                document.getElementById('modalCreatorDiv').textContent = row.dataset.creator_div;

                // Right Column
                document.getElementById('modalSubject').textContent = row.dataset.subject;
                document.getElementById('modalParticulars').textContent = row.dataset.particulars;

                // Attachments
                const attachmentsDiv = document.getElementById('modalAttachments');
                attachmentsDiv.innerHTML = '';
                const rawAttachments = row.dataset.attachments;
                if (rawAttachments) {
                    const attachmentsArray = JSON.parse(rawAttachments);
                    if (attachmentsArray.length > 0) {
                        attachmentsArray.forEach(file => {
                            const link = document.createElement('a');
                            link.href = file.url;
                            link.target = '_blank';
                            link.className = 'd-flex justify-content-between align-items-center p-3 bg-white border rounded mb-2 text-decoration-none';
                            link.innerHTML = `
                            <div><i class='fa-regular fa-file-pdf me-2 text-danger'></i> <span class='text-dark fw-bold small'>${file.name}</span></div>
                            <div class='text-muted small'>${file.size}</div>
                        `;
                            attachmentsDiv.appendChild(link);
                        });
                    } else {
                        attachmentsDiv.innerHTML = '<span class="text-muted fst-italic small">No files attached to this document.</span>';
                    }
                }
            });
        }
    });
</script>
<?php require_once BASE_PATH . 'includes/footer.php'; ?>