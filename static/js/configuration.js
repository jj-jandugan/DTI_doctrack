// static/js/configuration.js

document.addEventListener('DOMContentLoaded', function() {

    // --- Edit Modals Population ---

    const editClassModal = document.getElementById('editClassModal');
    if (editClassModal) {
        editClassModal.addEventListener('show.bs.modal', function(e) {
            document.getElementById('editClassId').value = e.relatedTarget.dataset.id;
            document.getElementById('editClassName').value = e.relatedTarget.dataset.name;
        });
    }

    const editTypeModal = document.getElementById('editTypeModal');
    if (editTypeModal) {
        editTypeModal.addEventListener('show.bs.modal', function(e) {
            document.getElementById('editTypeId').value = e.relatedTarget.dataset.id;
            document.getElementById('editTypeName').value = e.relatedTarget.dataset.name;
        });
    }

    const editDivisionModal = document.getElementById('editDivisionModal');
    if (editDivisionModal) {
        editDivisionModal.addEventListener('show.bs.modal', function(e) {
            document.getElementById('editDivId').value = e.relatedTarget.dataset.id;
            document.getElementById('editDivAbbr').value = e.relatedTarget.dataset.abbr;
            document.getElementById('editDivName').value = e.relatedTarget.dataset.name;
        });
    }

    const editStatusModal = document.getElementById('editStatusModal');
    if (editStatusModal) {
        editStatusModal.addEventListener('show.bs.modal', function(e) {
            document.getElementById('editStatusId').value = e.relatedTarget.dataset.id;
            document.getElementById('editStatusName').value = e.relatedTarget.dataset.name;
            document.getElementById('editStatusCat').value = e.relatedTarget.dataset.category;
        });
    }

    // --- Delete Confirmation Modal Population ---

    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    if (deleteConfirmModal) {
        deleteConfirmModal.addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            document.getElementById('delete_id').value = btn.dataset.id;
            document.getElementById('delete_action').value = btn.dataset.action;
            document.getElementById('delete_tab').value = btn.dataset.tab;
            document.getElementById('delete_item_name').textContent = btn.dataset.name;
        });
    }
});