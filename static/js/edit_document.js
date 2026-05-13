// Note the new 'e' parameter
function queueFileRemoval(e, attachmentId) {
    // 1. Stop the click from triggering the dropZone file picker
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
            // 1. Prevent the browser from immediately going to roIncoming.php
            e.preventDefault();

            // 2. Show the "Discard Changes?" modal instead
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
    const blockExternal = document.getElementById('block-external'); // Added the new block!

    const routeDivision = document.getElementById('routeDivision');
    const usersContainer = document.getElementById('routeUsersContainer');

    // Safely parse JSON injected from the backend
    const usersDataEl = document.getElementById('usersData');
    const recipientsDataEl = document.getElementById('recipientsData');

    const usersByDiv = usersDataEl ? JSON.parse(usersDataEl.textContent) : {};
    const currentRecipients = recipientsDataEl ? JSON.parse(recipientsDataEl.textContent) : [];

    if (routeType) {
        routeType.addEventListener('change', function() {
            if(blockDivision) blockDivision.classList.toggle('d-none', this.value !== 'division');
            if(blockGroup) blockGroup.classList.toggle('d-none', this.value !== 'group');

            // Show the external block if Within DTI or Outside Agency is selected
            if(blockExternal) blockExternal.classList.toggle('d-none', !(this.value === 'within_dti' || this.value === 'outside_dti'));
        });
    }

    function populateUsers(divId) {
    usersContainer.innerHTML = '';
    if (divId && usersByDiv[divId]) {
        usersByDiv[divId].forEach(user => {
            const div = document.createElement('div');
            // 'justify-content-start' ensures they don't spread out
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
        usersContainer.innerHTML = '<span class="text-muted small p-2">No personnel found.</span>';
    }
}

    if (routeDivision) {
        routeDivision.addEventListener('change', (e) => populateUsers(e.target.value));
        if (routeDivision.value) populateUsers(routeDivision.value); // Trigger on load if pre-filled
    }
});