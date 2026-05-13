<?php
// includes/pagination.php
if (isset($total_pages) && $total_pages > 1): ?>
<div class="pagination-wrapper d-flex justify-content-between align-items-center mt-4 px-3">
    <div class="pagination-info">
        <span class="text-muted smaller fw-medium">
            Showing <span class="text-dark"><?= $page ?></span> of <span class="text-dark"><?= $total_pages ?></span> pages
            <span class="mx-1 text-opacity-25">|</span>
            Total of <span class="text-primary fw-bold"><?= $total_records ?></span> records
        </span>
    </div>

    <nav aria-label="Table navigation">
        <ul class="pagination pagination-modern mb-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            </li>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            for($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
</div>
<?php endif; ?>