// static/js/signatory_action.js

document.addEventListener("DOMContentLoaded", function() {

    const approveForm = document.getElementById('approveForm');
    const rejectForm = document.getElementById('rejectForm');

    // Handle Approve Form Submit
    if (approveForm) {
        approveForm.addEventListener('submit', function() {
            const btn = document.getElementById('btnApproveSubmit');
            // Change button to loading state
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Processing...';
            // Disable button to prevent double submission
            btn.classList.add('disabled');
            btn.style.pointerEvents = 'none';
        });
    }

    // Handle Reject Form Submit
    if (rejectForm) {
        rejectForm.addEventListener('submit', function() {
            const btn = document.getElementById('btnRejectSubmit');
            // Change button to loading state
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Processing...';
            // Disable button to prevent double submission
            btn.classList.add('disabled');
            btn.style.pointerEvents = 'none';
        });
    }
});