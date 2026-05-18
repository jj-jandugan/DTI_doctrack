<?php
// templates/signatory/signHistory.php
require_once '../../classes/database.php';
require_once '../../classes/DocumentManager.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RD', 'ARD'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Document History";
$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

try {
    $doc_types       = $docManager->getDocumentTypes();
    $classifications = $docManager->getClassifications();

    // NEW FETCHING LOGIC: getSignatoryHistory natively handles the exact lifecycle
    // (Rejected -> Resubmitted, Approved -> Closed)
    $history_docs    = $docManager->getSignatoryHistory($user_id);

    $all_recipients  = $docManager->getRecipientsByDocument();

    $export_payload = [];
    foreach ($history_docs as $doc) {
        $receiver_names = 'N/A';
        if (isset($all_recipients[$doc['id']])) {
            $clean_names = array_map(function($p) { return explode(' (', $p)[0]; }, $all_recipients[$doc['id']]);
            $receiver_names = implode(', ', $clean_names);
        } elseif (!empty($doc['sender'])) {
            $receiver_names = $doc['sender'];
        }

        $receiver = trim(($doc['address_name'] ?? 'Internal Routing') . ' - ' . $receiver_names, " -");

        // --- SENDER LOGIC ---
        $raw_origin = !empty($doc['origin_name']) ? trim($doc['origin_name']) : '';
        if ($raw_origin && strtolower($raw_origin) !== 'internal dti') {
            // Document is from Outside Offices / Other Branches
            $display_office = $raw_origin;
            $display_person = !empty($doc['sender']) ? trim($doc['sender']) : 'Unknown Sender';
        } else {
            // Document is Internal (From a Division)
            $display_office = !empty($doc['c_division']) ? trim($doc['c_division']) : 'System User';
            $display_person = trim(($doc['c_fname'] ?? '') . ' ' . ($doc['c_lname'] ?? ''));
        }

        // Excel Format: "Office/Division, Name"
        $sender = $display_office . ', ' . $display_person;

        // Combine ALL text columns so the Javascript export filter can find everything
        $search_text = strtolower(
            ($doc['dts_no'] ?? '') . ' ' .
            ($doc['subject'] ?? '') . ' ' .
            $display_office . ' ' .
            $display_person . ' ' .
            ($doc['address_name'] ?? '') . ' ' .
            $receiver_names . ' ' .
            trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? '')) . ' ' .
            ($doc['status_name'] ?? '') . ' ' .
            ($doc['classification'] ?? '') . ' ' .
            ($doc['doc_type'] ?? '')
        );

        $export_payload[] = [
            'dts'       => $doc['dts_no'] ?? '',
            'created'   => date('F d, Y g:i A', strtotime($doc['created_at'])),
            'date_raw'  => date('Y-m-d', strtotime($doc['created_at'])),
            'class'     => $doc['classification'] ?? 'N/A',
            'type'      => $doc['doc_type'] ?? 'N/A',
            'subject'   => $doc['subject'] ?? '',
            'sender'    => $sender,
            'receiver'  => $receiver,
            'signatory' => trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? 'None')),
            'status'    => $doc['status_name'] ?? '',
            'direction' => strtolower($doc['doc_direction'] ?? ''),
            'search'    => $search_text
        ];
    }
    $export_json = json_encode($export_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

} catch (Exception $e) {
    $error_msg = "Error loading history: " . $e->getMessage();
    $history_docs = [];
    $export_json = "[]";
}

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/document.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/accept_docu.css">
';

// Removed panel.js, kept export and filters
$extra_js = '
<script>const allHistoryData = ' . $export_json . ';</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script src="' . BASE_URL . 'static/js/export.js"></script>
<script src="' . BASE_URL . 'static/js/filters.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="filter-section mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
            <button type="button" class="btn fw-bold shadow-sm d-flex align-items-center flex-shrink-0" onclick="exportToExcel()" style="background-color: #10b981; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; transition: 0.2s;">
                <i class="fa-solid fa-file-excel me-2" style="font-size: 1.1rem;"></i> Export to Excel
            </button>

            <div class="input-group search-container shadow-sm" style="max-width: 400px; border-radius: 6px;">
                <span class="input-group-text search-icon-group bg-white border-end-0 text-muted">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" id="searchInput" class="form-control custom-search-input border-start-0 ps-0" placeholder="Search Control No. or Subject...">
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3 w-100">
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <i class="fa-solid fa-filter text-muted" style="font-size: 0.85rem;"></i>
                <span class="text-muted fw-bold small text-nowrap me-1">Filter by:</span>
            </div>

            <select id="statusFilter" class="form-select custom-select flex-shrink-0 shadow-sm" style="width: 140px; cursor: pointer;">
                <option value="">All Statuses</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
                <option value="Cancelled">Cancelled</option>
                <option value="Closed">Closed</option>
            </select>

            <select id="directionFilter" class="form-select custom-select flex-shrink-0 shadow-sm" style="width: 130px; cursor: pointer;">
                <option value="">All Routes</option>
                <option value="incoming">Incoming</option>
                <option value="outgoing">Outgoing</option>
            </select>

            <select id="typeFilter" class="form-select custom-select flex-shrink-0 shadow-sm" style="width: 170px; cursor: pointer;">
                <option value="">All Document Types</option>
                <?php foreach ($doc_types as $type): ?>
                    <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="classFilter" class="form-select custom-select flex-shrink-0 shadow-sm" style="width: 170px; cursor: pointer;">
                <option value="">All Classifications</option>
                <?php foreach ($classifications as $class): ?>
                    <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="d-flex align-items-center gap-2 border px-3 py-1 rounded bg-white flex-shrink-0 shadow-sm" style="height: 38px;">
                <span class="text-muted small text-nowrap fw-bold">From</span>
                <input type="date" id="startDate" class="form-control form-control-sm border-0 text-secondary p-0 bg-transparent shadow-none" style="width: 110px; cursor: pointer;">
                <div class="vr mx-1" style="opacity: 0.1;"></div>
                <span class="text-muted small text-nowrap fw-bold">To</span>
                <input type="date" id="endDate" class="form-control form-control-sm border-0 text-secondary p-0 bg-transparent shadow-none" style="width: 110px; cursor: pointer;">
            </div>
        </div>
    </div>

    <div class="table-container p-0 shadow-sm">
        <div class="table-responsive">
            <table class="data-table" id="historyTable">
                <thead>
                    <tr>
                        <th>DTS NO.</th>
                        <th>STATUS</th>
                        <th>DATE & TIME CREATED</th>
                        <th>DATE & TIME APPROVED</th>
                        <th>SUBJECT</th>
                        <th>ADDRESS</th>
                        <th>SIGNATORY</th>
                        <th>CREATED BY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history_docs)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No historical records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history_docs as $doc):
                            // Receiver mapping
                            $receiver_names = 'N/A';
                            if (isset($all_recipients[$doc['id']])) {
                                $clean_names = array_map(function($p) { return explode(' (', $p)[0]; }, $all_recipients[$doc['id']]);
                                $receiver_names = implode(', ', $clean_names);
                            } elseif (!empty($doc['sender'])) {
                                $receiver_names = $doc['sender'];
                            }
                        ?>

                        <tr class="history-row clickable-row" onclick="window.location.href='signViewHist.php?id=<?= $doc['id'] ?>'" style="cursor: pointer;">

                            <td class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no'] ?? '') ?></td>

                            <td>
                                <span class="status <?= strtolower(str_replace(' ', '-', $doc['status_category'] ?? '')) ?> status-target">
                                    <?= htmlspecialchars($doc['status_name'] ?? '') ?>
                                </span>
                            </td>

                            <td>
                                <div class="text-dark"><?= date('M d, Y', strtotime($doc['created_at'])) ?></div>
                                <div class="text-muted small"><?= date('h:i A', strtotime($doc['created_at'])) ?></div>
                                <span class="d-none date-target"><?= date('Y-m-d', strtotime($doc['created_at'])) ?></span>
                            </td>

                            <td>
                                <?php if (!empty($doc['updated_at'])): ?>
                                    <div class="text-dark"><?= date('M d, Y', strtotime($doc['updated_at'])) ?></div>
                                    <div class="text-muted small"><?= date('h:i A', strtotime($doc['updated_at'])) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-dark search-target text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($doc['subject'] ?? '') ?>">
                                <?= htmlspecialchars($doc['subject'] ?? '') ?>
                            </td>

                            <td class="search-target">
                                <div class="text-dark small text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($doc['address_name'] ?? 'Internal Routing') ?>">
                                    <?= htmlspecialchars($doc['address_name'] ?? 'Internal Routing') ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($receiver_names) ?></div>
                            </td>

                            <td class="small">
                                <?= htmlspecialchars(trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? 'None'))) ?>
                            </td>

                            <td class="search-target">
                                <span class="text-dark small"><?= htmlspecialchars(($doc['c_fname'] ?? '') . ' ' . ($doc['c_lname'] ?? '')) ?></span><br>
                                <span class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($doc['c_division'] ?? 'System') ?></span>
                            </td>

                            <td class="d-none type-target"><?= htmlspecialchars($doc['doc_type'] ?? '') ?></td>
                            <td class="d-none class-target"><?= htmlspecialchars($doc['classification'] ?? '') ?></td>
                            <td class="d-none direction-target"><?= strtolower($doc['doc_direction'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="pagination-container" class="d-flex justify-content-end mt-3 border-top pt-3"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof TableFilter !== 'undefined') {
            new TableFilter('.history-row');
        }
    });
</script>

<?php require_once BASE_PATH . 'includes/footer.php'; ?>