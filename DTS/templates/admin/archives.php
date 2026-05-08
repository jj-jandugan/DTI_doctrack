<?php
// templates/admin/archives.php
require_once '../../classes/database.php';

// Security Check: Only Admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$page_title = "System Archives";

$extra_css = '
<link rel="stylesheet" href="' . BASE_URL . 'static/css/cards.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/table.css">
<link rel="stylesheet" href="' . BASE_URL . 'static/css/button.css">
<style>
    .archive-icon {
        color: #94a3b8;
        font-size: 1.5rem;
    }
    .date-block {
        font-size: 0.85rem;
        color: #64748b;
    }
    .date-block strong {
        color: #1e293b;
        display: block;
    }
</style>
';

// ==========================================
// FETCH ARCHIVED (CLOSED) DOCUMENTS
// ==========================================
// We specifically look for documents where the status category is 'CLOSED'
try {
    $stmt = $pdo->query("
        SELECT d.id, d.dts_no, d.subject, d.created_at, d.updated_at,
               c.name as classification,
               t.name as doc_type,
               s.name as status_name,
               u.first_name, u.last_name
        FROM records_document d
        LEFT JOIN records_classification c ON d.classification_id = c.id
        LEFT JOIN records_documenttype t ON d.document_type_id = t.id
        LEFT JOIN records_status s ON d.status_id = s.id
        LEFT JOIN auth_user u ON d.creator_id = u.id
        WHERE s.category = 'CLOSED'
        ORDER BY d.updated_at DESC
    ");
    $archives = $stmt->fetchAll();

    // Automatically extract the unique years from the closed documents to populate the filter dropdown
    $available_years = [];
    foreach ($archives as $doc) {
        $year = date('Y', strtotime($doc['updated_at']));
        if (!in_array($year, $available_years)) {
            $available_years[] = $year;
        }
    }
    rsort($available_years); // Sort years from newest to oldest

} catch (PDOException $e) {
    $error_msg = "Error loading archives: " . $e->getMessage();
    $archives = [];
}

require_once BASE_PATH . 'includes/header.php';
?>

<div class="dashboard-inner p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-dark fw-bold mb-0">System Archives</h2>
            <p class="text-secondary">A secure, read-only vault of all completed and closed documents.</p>
        </div>

        <div class="d-flex gap-2">
            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search DTS No. or Subject..." style="width: 250px;">

            <select id="yearFilter" class="form-select form-select-sm" style="width: 120px;">
                <option value="">All Years</option>
                <?php foreach ($available_years as $yr): ?>
                    <option value="<?= $yr ?>"><?= $yr ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="table-container p-0">
        <div class="table-responsive">
            <table class="data-table" id="archivesTable">
                <thead>
                    <tr>
                        <th width="5%"></th>
                        <th width="15%">DTS NUMBER</th>
                        <th width="30%">SUBJECT / DETAILS</th>
                        <th width="15%">DOCUMENT TYPE</th>
                        <th width="15%">DATE CREATED</th>
                        <th width="15%">DATE CLOSED</th>
                        <th width="5%">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archives as $doc): ?>
                    <tr class="archive-row">
                        <td class="text-center"><i class="fa-solid fa-box-archive archive-icon"></i></td>

                        <td><span class="fw-bold text-primary search-target"><?= htmlspecialchars($doc['dts_no']) ?></span></td>

                        <td>
                            <div class="fw-bold text-dark text-truncate search-target" style="max-width: 300px;" title="<?= htmlspecialchars($doc['subject']) ?>">
                                <?= htmlspecialchars($doc['subject']) ?>
                            </div>
                            <div class="text-muted search-target" style="font-size: 0.8rem;">
                                Created by: <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?>
                            </div>
                        </td>

                        <td class="search-target">
                            <span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($doc['doc_type']) ?></span>
                            <div class="text-muted mt-1" style="font-size: 0.75rem;"><?= htmlspecialchars($doc['classification']) ?></div>
                        </td>

                        <td>
                            <div class="date-block">
                                <strong><?= date('M d, Y', strtotime($doc['created_at'])) ?></strong>
                                <?= date('h:i A', strtotime($doc['created_at'])) ?>
                            </div>
                        </td>

                        <td class="year-target">
                            <div class="date-block">
                                <strong><?= date('M d, Y', strtotime($doc['updated_at'])) ?></strong>
                                <?= date('h:i A', strtotime($doc['updated_at'])) ?>
                            </div>
                            <span class="d-none pure-year"><?= date('Y', strtotime($doc['updated_at'])) ?></span>
                        </td>

                        <td>
                            <button class="btn btn-sm btn-outline-primary" title="View Document Details">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if(empty($archives)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fa-solid fa-box-open mb-3" style="font-size: 2.5rem; color: #cbd5e1;"></i><br>
                                <h6 class="fw-bold text-secondary">The Archive is Empty</h6>
                                <p style="font-size: 0.9rem;">Documents will automatically appear here once their routing status is marked as "CLOSED".</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$extra_js = "
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const yearFilter = document.getElementById('yearFilter');
    const tableRows = document.querySelectorAll('.archive-row');

    function filterArchives() {
        const searchTerm = searchInput.value.toLowerCase();
        const yearTerm = yearFilter.value;

        tableRows.forEach(row => {
            // Search through text content of specific elements inside the row
            const textContent = row.innerText.toLowerCase();

            // Get the specific year this document was closed
            const docYear = row.querySelector('.pure-year').textContent;

            const matchesSearch = textContent.includes(searchTerm);
            const matchesYear = yearTerm === '' || docYear === yearTerm;

            if (matchesSearch && matchesYear) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('keyup', filterArchives);
    if (yearFilter) yearFilter.addEventListener('change', filterArchives);
});
</script>
";
require_once BASE_PATH . 'includes/footer.php';
?>