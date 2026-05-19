// static/js/edit_document.js

function queueFileRemoval(e, attachmentId) {
    if (e) e.stopPropagation();

    if(confirm('Remove this attachment permanently on save?')) {
        document.getElementById('att-row-' + attachmentId).style.display = 'none';
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'remove_attachments[]';
        hiddenInput.value = attachmentId;
        document.getElementById('editDocForm').appendChild(hiddenInput);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const backLink = document.getElementById('backToQueue');
    const cancelModalEl = document.getElementById('cancelEditModal');

    if (backLink && cancelModalEl) {
        const cancelModal = new bootstrap.Modal(cancelModalEl);
        backLink.addEventListener('click', function(e) {
            e.preventDefault();
            cancelModal.show();
        });
    }

    const form = document.getElementById('editDocForm');
    const saveModalEl = document.getElementById('saveConfirmModal');

    if (form && saveModalEl) {
        const saveModal = new bootstrap.Modal(saveModalEl);
        form.addEventListener('submit', function(e) {
            if (!form.dataset.confirmed) {
                e.preventDefault();
                saveModal.show();
            }
        });

        document.getElementById('confirmSaveBtn').addEventListener('click', function() {
            form.dataset.confirmed = 'true';
            form.submit();
        });
    }

    // 2. Dynamic Routing (4-Tier System)
    const routeType = document.getElementById('routeType');
    const blockDivision = document.getElementById('block-division');
    const blockGroup = document.getElementById('block-group');
    const blockExternal = document.getElementById('block-external');

    const routeDivision = document.getElementById('routeDivision');
    const usersContainer = document.getElementById('routeUsersContainer');

    // Grabbing elements for filtering
    const docCreatorIdInput = document.getElementById('docCreatorId');
    const signatorySelect = document.querySelector('select[name="signatory"]');

    const usersDataEl = document.getElementById('usersData');
    const recipientsDataEl = document.getElementById('recipientsData');

    const usersByDiv = usersDataEl ? JSON.parse(usersDataEl.textContent) : {};
    const currentRecipients = recipientsDataEl ? JSON.parse(recipientsDataEl.textContent) : [];

    if (routeType) {
        routeType.addEventListener('change', function() {
            if(blockDivision) blockDivision.classList.toggle('d-none', this.value !== 'division');
            if(blockGroup) blockGroup.classList.toggle('d-none', this.value !== 'group');
            if(blockExternal) blockExternal.classList.toggle('d-none', !(this.value === 'within_dti' || this.value === 'outside_dti'));
        });
    }

    function populateUsers(divId) {
        usersContainer.innerHTML = '';
        if (divId && usersByDiv[divId]) {
            // 1. Get Creator ID
            const creatorId = docCreatorIdInput ? String(docCreatorIdInput.value) : null;

            // 2. Get ALL Signatory IDs from the dropdown options
            const allSignatoryIds = signatorySelect ? Array.from(signatorySelect.options).map(opt => String(opt.value)).filter(val => val !== "") : [];

            // NEW: Permanently filter out the Creator and ALL users who are Signatories
            const filteredUsers = usersByDiv[divId].filter(user => {
                const uid = String(user.id);
                return uid !== creatorId && !allSignatoryIds.includes(uid);
            });

            if (filteredUsers.length > 0) {
                filteredUsers.forEach(user => {
                    const div = document.createElement('div');
                    div.className = 'form-check d-flex align-items-center justify-content-start mb-2';

                    const isChecked = currentRecipients.map(String).includes(String(user.id)) ? 'checked' : '';

                    div.innerHTML = `
                        <input class="form-check-input mt-0" type="checkbox" name="route_users[]"
                               value="${user.id}" id="user_${user.id}" ${isChecked}
                               style="margin-right: 10px; cursor: pointer;">
                        <label class="form-check-label text-dark mb-0" for="user_${user.id}"
                               style="cursor: pointer; font-size: 0.85rem; white-space: nowrap;">
                            ${user.first_name} ${user.last_name}
                        </label>
                    `;
                    usersContainer.appendChild(div);
                });
            } else {
                usersContainer.innerHTML = '<span class="text-muted small p-2">No other personnel available.</span>';
            }
        } else {
            usersContainer.innerHTML = '<span class="text-muted small p-2">No personnel found.</span>';
        }
    }

    if (routeDivision) {
        routeDivision.addEventListener('change', (e) => populateUsers(e.target.value));
        if (routeDivision.value) populateUsers(routeDivision.value);
    }
});