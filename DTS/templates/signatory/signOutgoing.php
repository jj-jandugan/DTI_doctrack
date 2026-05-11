<?php
// templates/signatory/signOutgoing.php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

// Security Check: Only 'RD' or 'ARD' role can access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RD', 'ARD'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Outgoing for Approval";
$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

// Session Notifications from Controller
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// FETCH DATA VIA CLASS
$classifications = $docManager->getClassifications();
$outgoing_docs = $docManager->getDashboardOutgoing($user_id);
$all_attachments = $docManager->getAllAttachmentsGrouped();

// Your exact original CSS/Styles
$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<style>
    .status { font-size: 0.75rem; padding: 4px 10px; border-radius: 20px; font-weight: bold; letter-spacing: 0.5px; }
    .status.for-approval { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .filter-bar { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
    .filter-label { color: #64748b; font-weight: 600; font-size: 0.95rem; white-space: nowrap; }
    .custom-search-input { border-start-start-radius: 0; border-end-start-radius: 0; border-left: none; }
    .search-icon-group { background-color: #fff; border-end-end-radius: 0; border-start-end-radius: 0; color: #94a3b8; }
    .split-layout-container { display: flex; gap: 20px; align-items: flex-start; transition: all 0.3s ease; }
    .table-section { flex: 1; transition: all 0.3s ease; min-width: 0; }
    .side-panel-section { width: 0; opacity: 0; overflow: hidden; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: -4px 0 15px rgba(0,0,0,0.03); transition: all 0.3s ease; flex-shrink: 0; visibility: hidden; }
    .side-panel-section.active { width: 450px; opacity: 1; visibility: visible; padding: 0; }
    .side-panel-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0; }
    .side-panel-body { padding: 20px; max-height: calc(100vh - 200px); overflow-y: auto; }
    .data-group { margin-bottom: 15px; }
    .data-group label { font-size: 0.70rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; display: block;}
    .data-value { font-size: 0.95rem; color: #1e293b; font-weight: 500; }
    .textarea-style { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; min-height: 60px; white-space: pre-wrap;}
    .attachment-link { display: flex; align-items: center; padding: 10px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; margin-bottom: 8px; text-decoration: none; color: #1e293b; font-weight: 600; font-size: 0.85rem; transition: 0.2s;}
    .attachment-link:hover { background: #eff6ff; border-color: #93c5fd; }
</style>
';

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">Outgoing Documents</h2>
            <p class="text-secondary">Review internal division documents that require your signature.</p>
        </div>
    </div>
    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><i
                class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>
    <!-- Filter Bar remains identical -->
    <div class="filter-bar">
        <span class="filter-label">Filter by</span>
        <div class="input-group" style="width: 250px;">
            <span class="input-group-text search-icon-group"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" id="searchInput" class="form-control custom-search-input"
                placeholder="Search DTS No. or Subject...">
        </div>
        <select id="classFilter" class="form-select" style="width: 150px; color: #475569;">
            <option value="">Classification</option>
            <?php foreach ($classifications as $class): ?>
                <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="d-flex align-items-center gap-2 border px-2 py-1 rounded bg-white">
            <span class="text-muted" style="font-size: 0.85rem;">From:</span>
            <input type="date" id="startDate" class="form-control form-control-sm border-0 text-secondary"
                style="width: 110px;">
            <span class="text-muted" style="font-size: 0.85rem;">To:</span>
            <input type="date" id="endDate" class="form-control form-control-sm border-0 text-secondary"
                style="width: 110px;">
        </div>
    </div>
    <div class="split-layout-container">
        <div class="table-section">
            <div class="table-container p-0">
                <div class="table-responsive">
                    <table class="data-table" id="outgoingTable">
                        <thead>
                            <tr>
                                <th>DTS NO.</th>
                                <th>STATUS</th>
                                <th>DESTINATION</th>
                                <th width="20%">SUBJECT</th>
                                <th>CREATED BY</th>
                                <th>DATE CREATED</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outgoing_docs as $doc):
                                $json_attachments = json_encode($all_attachments[$doc['id']] ?? []);

                                // Route display logic
                                $route_display = "External Office";
                                if (in_array($doc['route_type'], ['division', 'group'])) {
                                    $route_display = "Internal (Skips RO)";
                                } elseif ($doc['route_type'] == 'within_dti') {
                                    $route_display = "DTI Branch (Via RO)";
                                }
                                ?>
                                <tr class="doc-row clickable-row" style="cursor: pointer;" data-id="<?= $doc['id'] ?>"
                                    data-dts="<?= htmlspecialchars($doc['dts_no']) ?>"
                                    data-status="<?= htmlspecialchars($doc['status_name']) ?>"
                                    data-deadline="<?= $doc['due_date'] ? date('m/d/Y', strtotime($doc['due_date'])) : 'None' ?>"
                                    data-created="<?= date('F d, Y h:i A', strtotime($doc['created_at'])) ?>"
                                    data-subject="<?= htmlspecialchars($doc['subject']) ?>"
                                    data-particulars="<?= htmlspecialchars($doc['particulars'] ?? '') ?>"
                                    data-type="<?= htmlspecialchars($doc['doc_type']) ?>"
                                    data-class="<?= htmlspecialchars($doc['classification']) ?>"
                                    data-address="<?= htmlspecialchars($doc['address_name'] ?? 'N/A') ?>"
                                    data-routetype="<?= htmlspecialchars($route_display) ?>"
                                    data-creator="<?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?>"
                                    data-creatordiv="<?= htmlspecialchars($doc['c_division'] ?? 'Division') ?>"
                                    data-attachments='<?= htmlspecialchars($json_attachments, ENT_QUOTES, 'UTF-8') ?>'>
                                    <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?>
                                    </td>
                                    <td><span
                                            class="status for-approval"><?= htmlspecialchars($doc['status_name']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($doc['address_name'] ?? 'External Office') ?></td>
                                    <td class="fw-bold text-dark text-truncate search-target">
                                        <?= htmlspecialchars($doc['subject']) ?></td>
                                    <td>
                                        <div class="creator-cell m-0 p-0 bg-transparent border-0">
                                            <div class="creator-avatar" style="width: 28px; height: 28px;"><i
                                                    class="fa-solid fa-user" style="font-size: 0.7rem;"></i></div>
                                            <div class="creator-info">
                                                <span class="creator-name fw-bold"
                                                    style="font-size: 0.8rem;"><?= htmlspecialchars($doc['c_fname'] . ' ' . $doc['c_lname']) ?></span>
                                                <span
                                                    class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                                                <span
                                                    class="d-none class-target"><?= htmlspecialchars($doc['classification']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($doc['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($outgoing_docs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">No outgoing documents waiting for
                                        signature.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Side Panel remains identical in structure -->
        <div class="side-panel-section" id="sidePanel">
            <div class="side-panel-header">
                <div>
                    <span class="text-primary fw-bold" style="font-size: 1.1rem;" id="paneDTS">DTS NO</span>
                    <span class="status for-approval ms-2" id="paneStatus">FOR APPROVAL</span>
                </div>
                <button type="button" class="btn-close" id="closePanelBtn" aria-label="Close"></button>
            </div>
            <div class="side-panel-body">
                <div class="mb-4 border rounded p-3 bg-light">
                    <div class="data-group mb-2"><label><i class="fa-solid fa-paperclip me-1"></i> ATTACHED
                            FILES</label></div>
                    <div id="paneAttachments"></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="data-group"><label>Date Drafted</label>
                            <div class="data-value" id="paneCreated"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="data-group"><label>Deadline</label>
                            <div class="data-value text-danger fw-bold" id="paneDeadline"></div>
                        </div>
                    </div>
                </div>
                <div class="row g-2 mb-3 border-top pt-3">
                    <div class="col-6">
                        <div class="data-group"><label>Classification</label>
                            <div class="data-value" id="paneClass"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="data-group"><label>Routing Path</label>
                            <div class="data-value text-primary fw-bold" id="paneRouteType"></div>
                        </div>
                    </div>
                </div>
                <div class="data-group mb-3">
                    <label>Subject</label>
                    <div class="data-value textarea-style fw-bold" id="paneSubject"></div>
                </div>
                <div class="row g-2 mb-4">
                    <div class="col-12">
                        <div class="data-group"><label>Address (Destination)</label>
                            <div class="data-value textarea-style" style="min-height:40px;" id="paneAddress"></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="data-group"><label>Particulars</label>
                            <div class="data-value textarea-style" id="paneParticulars"></div>
                        </div>
                    </div>
                </div>
                <div class="border-top pt-4">
                    <div class="mb-3">
                        <p class="mb-0 text-muted" style="font-size: 0.75rem;">Drafted by: <span
                                class="fw-bold text-dark" id="paneCreator"></span> (<span id="paneCreatorDiv"></span>)
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <!-- Points to Central Signatory Controller -->
                        <form method="POST" action="../../controllers/signatory.php" class="m-0 w-50">
                            <input type="hidden" name="action" value="reject_document">
                            <input type="hidden" name="document_id" class="targetDocId">
                            <button type="submit" class="btn btn-red w-100"
                                onclick="return confirm('Reject this outgoing document?');">
                                <i class="fa-solid fa-xmark me-2"></i> Reject </button>
                        </form>
                        <form method="POST" action="../../controllers/signatory.php" class="m-0 w-50">
                            <input type="hidden" name="action" value="approve_document">
                            <input type="hidden" name="document_id" class="targetDocId">
                            <button type="submit" class="btn btn-blue w-100"
                                onclick="return confirm('Approve this document for release?');">
                                <i class="fa-solid fa-check me-2"></i> Approve </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Keep your exact Filter Logic from original
        const searchInput = document.getElementById('searchInput');
        const classFilter = document.getElementById('classFilter');
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');
        const tableRows = document.querySelectorAll('.doc-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const classTerm = classFilter.value.toLowerCase();
            const startTerm = startDate.value;
            const endTerm = endDate.value;

            tableRows.forEach(row => {
                const searchContent = Array.from(row.querySelectorAll('.search-target'))
                    .map(el => el.innerText.toLowerCase()).join(' ');
                const classContent = row.querySelector('.class-target').innerText.toLowerCase();
                const docDate = row.querySelector('.date-target').innerText;

                const matchesSearch = searchContent.includes(searchTerm);
                const matchesClass = classTerm === '' || classContent.includes(classTerm);

                let matchesDate = true;
                if (startTerm && docDate < startTerm) matchesDate = false;
                if (endTerm && docDate > endTerm) matchesDate = false;

                row.style.display = (matchesSearch && matchesClass && matchesDate) ? '' : 'none';
            });
        }

        if (searchInput) searchInput.addEventListener('keyup', filterTable);
        if (classFilter) classFilter.addEventListener('change', filterTable);
        if (startDate) startDate.addEventListener('change', filterTable);
        if (endDate) endDate.addEventListener('change', filterTable);

        // Keep your exact Side Panel Logic
        const sidePanel = document.getElementById('sidePanel');
        const closeBtn = document.getElementById('closePanelBtn');

        document.querySelectorAll('.doc-row').forEach(row => {
            row.addEventListener('click', function () {
                document.querySelectorAll('.doc-row').forEach(r => r.style.backgroundColor = '');
                this.style.backgroundColor = '#f1f5f9';

                const docId = this.getAttribute('data-id');
                document.querySelectorAll('.targetDocId').forEach(input => input.value = docId);

                document.getElementById('paneDTS').textContent = this.getAttribute('data-dts');
                document.getElementById('paneCreated').textContent = this.getAttribute('data-created');
                document.getElementById('paneDeadline').textContent = this.getAttribute('data-deadline');
                document.getElementById('paneClass').textContent = this.getAttribute('data-class');
                document.getElementById('paneSubject').textContent = this.getAttribute('data-subject');
                document.getElementById('paneAddress').textContent = this.getAttribute('data-address');
                document.getElementById('paneParticulars').textContent = this.getAttribute('data-particulars') || 'No particulars provided.';
                document.getElementById('paneRouteType').textContent = this.getAttribute('data-routetype');
                document.getElementById('paneCreator').textContent = this.getAttribute('data-creator');
                document.getElementById('paneCreatorDiv').textContent = this.getAttribute('data-creatordiv');

                const attachmentsDiv = document.getElementById('paneAttachments');
                attachmentsDiv.innerHTML = '';
                const rawAttachments = this.getAttribute('data-attachments');
                if (rawAttachments) {
                    const attachmentsArray = JSON.parse(rawAttachments);
                    if (attachmentsArray.length > 0) {
                        attachmentsArray.forEach(file => {
                            attachmentsDiv.innerHTML += `
                            <a href="${file.url}" target="_blank" class="attachment-link">
                                <i class="fa-regular fa-file-pdf text-danger me-2" style="font-size: 1.2rem;"></i> ${file.name}
                            </a>`;
                        });
                    } else {
                        attachmentsDiv.innerHTML = '<span class="text-muted fst-italic">No attachments.</span>';
                    }
                }

                sidePanel.classList.add('active');
            });
        });

        closeBtn.addEventListener('click', function () {
            sidePanel.classList.remove('active');
            document.querySelectorAll('.doc-row').forEach(r => r.style.backgroundColor = '');
        });
    });
</script>
<?php require_once BASE_PATH . 'includes/footer.php'; ?>