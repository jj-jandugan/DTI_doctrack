<?php
require_once '../../classes/database.php';
require_once '../../classes/documentManager.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "Global Document Records";
$docManager = new DocumentManager($pdo);

// 1. DATA FOR TABLE (PAGINATED)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$total_records = $docManager->getGlobalHistoryTotalCount();
$total_pages = ceil($total_records / $limit);
$records = $docManager->getGlobalHistoryPaginated($limit, $offset);

// 2. DATA FOR EXCEL (FETCH ALL)
$all_records_for_export = $docManager->getGlobalHistoryAll();

$export_payload = [];
foreach ($all_records_for_export as $doc) {
    // Sanitize strings to prevent JS breaks
    $clean_subject = str_replace(["\r", "\n"], ' ', $doc['subject']);
    $clean_instruction = str_replace(["\r", "\n"], ' ', $doc['particulars'] ?? 'N/A');

    // MAPPING DATA TO YOUR SPECIFIC EXCEL HEADERS
    $export_payload[] = [
        'Control No.' => $doc['dts_no'],
        'Remarks' => $doc['classification'] ?? 'N/A',
        'Form' => $doc['doc_type'] ?? 'N/A',
        'Date&Time Created' => date('M d, Y h:i A', strtotime($doc['created_at'])),
        'Origin' => $doc['origin_name'] ?? ($doc['c_division'] ?? 'Internal'),
        'Subject' => $clean_subject,
        'Instruction' => $clean_instruction,
        'Authority' => trim(($doc['sig_fname'] ?? '') . ' ' . ($doc['sig_lname'] ?? 'None')),
        'Destination' => $doc['address_name'] ?? 'Internal Routing',
        'Processor' => ($doc['c_fname'] ?? 'System') . ' ' . ($doc['c_lname'] ?? '') . ' (' . ($doc['c_division'] ?? 'RO') . ')',
        'Action Taken' => 'Forwarded', // Hardcoded as per request
        'Status' => $doc['status_name'],
        'Date&Time Closed' => date('M d, Y h:i A', strtotime($doc['updated_at']))
    ];
}

$export_json = json_encode($export_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

$doc_types = $docManager->getDocumentTypes();

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/filter.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/status.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/creator.css">
<style>
    .table-responsive { overflow-x: auto !important; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; }
    .data-table { min-width: 2600px; border-collapse: separate; border-spacing: 0; }
    .data-table th, .data-table td { padding: 16px 20px !important; vertical-align: top !important; border-bottom: 1px solid #f1f5f9; }
    .col-subject { width: 450px; }
    .col-instruction { width: 400px; }
    .text-wrap-multi { white-space: normal !important; line-height: 1.6; word-break: break-word; }
</style>
';

$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script>
    const allHistoryData = ' . $export_json . ';

    function exportToExcel() {
        if (!allHistoryData || allHistoryData.length === 0) {
            alert("No records found to export.");
            return;
        }

        try {
            const worksheet = XLSX.utils.json_to_sheet(allHistoryData);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Document Records");

            // Set column widths for a clean Excel look
            const wscols = [
                {wch: 18}, {wch: 15}, {wch: 15}, {wch: 22}, {wch: 25},
                {wch: 45}, {wch: 35}, {wch: 20}, {wch: 25}, {wch: 25},
                {wch: 15}, {wch: 15}, {wch: 22}
            ];
            worksheet["!cols"] = wscols;

            const filename = "DTS_Master_Records_" + new Date().toISOString().slice(0,10) + ".xlsx";
            XLSX.writeFile(workbook, filename);
        } catch (error) {
            console.error("Excel Export Error:", error);
            alert("Export failed. Check console for details.");
        }
    }
</script>
<script src="' . BASE_URL . 'static/js/filters.js"></script>
';

require_once BASE_PATH . 'includes/header.php';
?> <div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark m-0"><i class="fa-solid fa-database me-2 text-primary"></i> System Document
                Records</h4>
            <p class="text-muted small mb-0">Centralized logs for all Closed and Cancelled documents.</p>
        </div>
        <button type="button" class="btn fw-bold shadow-sm d-flex align-items-center" onclick="exportToExcel()"
            style="background-color: #10b981; color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="fa-solid fa-file-excel me-2" style="font-size: 1.1rem;"></i> Export to Excel </button>
    </div>
    <!-- Filters -->
    <div class="filter-section mb-4 bg-white p-3 rounded-4 border shadow-sm">
        <div class="row g-2">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i
                            class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0"
                        placeholder="Search DTS, Subject, or Processor...">
                </div>
            </div>
            <div class="col-md-2">
                <select id="statusFilter" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="CLOSED">Closed</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="directionFilter" class="form-select">
                    <option value="">All Routes</option>
                    <option value="Incoming">Incoming</option>
                    <option value="Outgoing">Outgoing</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="typeFilter" class="form-select">
                    <option value="">All Types</option>
                    <?php foreach ($doc_types as $t): ?>
                        <option value="<?= htmlspecialchars($t['name']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group"><input type="date" id="startDate" class="form-control"><input type="date"
                        id="endDate" class="form-control"></div>
            </div>
        </div>
    </div>
    <div class="table-container p-0 border-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 160px;">Control No.</th>
                        <th style="width: 140px;">Status</th>
                        <th style="width: 180px;">Date Created</th>
                        <th style="width: 180px;">Date Closed</th>
                        <th style="width: 150px;">Remarks</th>
                        <th style="width: 150px;">Form</th>
                        <th style="width: 250px;">Origin</th>
                        <th class="col-subject">Subject</th>
                        <th class="col-instruction">Instruction</th>
                        <th style="width: 220px;">Authority</th>
                        <th style="width: 300px;">Destination</th>
                        <th style="width: 250px;">Processor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-5">No records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr class="history-row">
                                <td class="fw-bold text-primary search-target"><?= $row['dts_no'] ?></td>
                                <td><span
                                        class="status <?= strtolower($row['status_category']) ?> status-target"><?= $row['status_name'] ?></span>
                                </td>
                                <td class="small">
                                    <div><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                    <div class="text-muted"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td class="small">
                                    <div><?= date('M d, Y', strtotime($row['updated_at'])) ?></div>
                                    <div class="text-muted"><?= date('h:i A', strtotime($row['updated_at'])) ?></div>
                                </td>
                                <td class="class-target"><?= $row['classification'] ?></td>
                                <td class="type-target"><?= $row['doc_type'] ?></td>
                                <td class="small"><?= htmlspecialchars($row['origin_name'] ?? $row['c_division']) ?></td>
                                <td class="text-wrap-multi search-target fw-bold text-dark">
                                    <?= htmlspecialchars($row['subject']) ?></td>
                                <td class="text-wrap-multi small text-muted">
                                    <?= htmlspecialchars($row['particulars'] ?: '---') ?></td>
                                <td class="small"><?= htmlspecialchars($row['sig_fname'] . ' ' . $row['sig_lname']) ?></td>
                                <td class="small"><?= htmlspecialchars($row['address_name']) ?></td>
                                <td class="search-target">
                                    <span
                                        class="small d-block fw-bold"><?= htmlspecialchars($row['c_fname'] . ' ' . $row['c_lname']) ?></span>
                                    <span
                                        class="smaller text-muted d-block"><?= htmlspecialchars($row['c_division'] ?? 'Records') ?></span>
                                </td>
                                <td class="d-none direction-target"><?= $row['doc_direction'] ?></td>
                                <td class="d-none date-target"><?= date('Y-m-d', strtotime($row['updated_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php include BASE_PATH . 'includes/page.php'; ?>
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