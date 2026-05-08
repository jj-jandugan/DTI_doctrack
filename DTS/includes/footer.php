<?php
// includes/footer.php
?>
        </div> </div> <script>
    document.addEventListener("DOMContentLoaded", function() {
        const toggleBtn = document.getElementById("sidebarToggle");
        const sidebar = document.getElementById("sidebar-wrapper");
        const navBgSlider = document.getElementById("navBgSlider");

        if(toggleBtn && sidebar) {
            toggleBtn.addEventListener("click", function() {
                sidebar.classList.toggle("collapsed");
                if (navBgSlider) {
                    navBgSlider.classList.toggle("collapsed");
                }
            });
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?= isset($extra_js) ? $extra_js : '' ?>
</body>
</html>