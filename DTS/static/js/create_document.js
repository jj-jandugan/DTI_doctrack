// static/js/create_document.js

document.addEventListener('DOMContentLoaded', function() {

    const newDocModalEl = document.getElementById('newDocModal');
    const cancelConfirmModalEl = document.getElementById('cancelConfirmModal');

    if (newDocModalEl && cancelConfirmModalEl) {
        const encoderModal = new bootstrap.Modal(newDocModalEl);

        // This listener detects when the "Discard" warning is closed
        cancelConfirmModalEl.addEventListener('hidden.bs.modal', function () {
            // Check if the user is staying on the page (didn't click 'Yes, Discard')
            // We check if the 'Yes, Discard' button was NOT the cause of closing
            if (document.activeElement.id !== 'confirmDiscardBtn') {
                encoderModal.show();
            }
        });
    }
    
    // 1. Initialize Table Filtering if the class exists
    if (typeof TableFilter !== 'undefined') {
        new TableFilter('.doc-row');
    }

    // 2. Initialize the Upload Queue if the class exists
    if (typeof UploadQueue !== 'undefined') {
        // We attach to window so the inline 'x' buttons can trigger it
        window.uploader = new UploadQueue("dropZone", "fileInput", "fileQueueDisplay", "dropZoneText");
    }

    // 3. Dynamic Routing Logic (Division vs Group)
    const routeType = document.getElementById('routeType');
    const blockDivision = document.getElementById('block-division');
    const blockGroup = document.getElementById('block-group');

    const routeDivision = document.getElementById('routeDivision');
    const usersContainer = document.getElementById('routeUsersContainer');

    const usersDataEl = document.getElementById('usersData');
    const usersByDiv = usersDataEl ? JSON.parse(usersDataEl.textContent) : {};

    // Toggle between Division or Group routing
    if (routeType) {
        routeType.addEventListener('change', function() {
            blockDivision.classList.add('d-none');
            blockGroup.classList.add('d-none');

            if (this.value === 'division') {
                blockDivision.classList.remove('d-none');
            } else if (this.value === 'group') {
                blockGroup.classList.remove('d-none');
            }
        });
    }

    const blockExternal = document.getElementById('block-external');

if (routeType) {
    routeType.addEventListener('change', function() {
        blockDivision.classList.add('d-none');
        blockGroup.classList.add('d-none');
        if (blockExternal) blockExternal.classList.add('d-none'); // Safe check for Division pages

        if (this.value === 'division') {
            blockDivision.classList.remove('d-none');
        } else if (this.value === 'group') {
            blockGroup.classList.remove('d-none');
        } else if (this.value === 'within_dti' || this.value === 'outside_dti') {
            if (blockExternal) blockExternal.classList.remove('d-none');
        }
    });
}

    // Populate personnel checkboxes based on selected division
    if (routeDivision) {
        routeDivision.addEventListener('change', function() {
            const divId = this.value;
            usersContainer.innerHTML = '';

            if (divId && usersByDiv[divId] && usersByDiv[divId].length > 0) {
                usersByDiv[divId].forEach(user => {
                    const div = document.createElement('div');
                    div.className = 'form-check mb-1';

                    const input = document.createElement('input');
                    input.className = 'form-check-input';
                    input.type = 'checkbox';
                    input.name = 'route_users[]';
                    input.value = user.id;
                    input.id = 'user_' + user.id;

                    const label = document.createElement('label');
                    label.className = 'form-check-label text-dark';
                    label.setAttribute('for', 'user_' + user.id);
                    label.style.fontSize = '0.85rem';
                    label.textContent = user.first_name + ' ' + user.last_name;

                    div.appendChild(input);
                    div.appendChild(label);
                    usersContainer.appendChild(div);
                });
            } else {
                usersContainer.innerHTML = '<span class="text-muted" style="font-size: 0.85rem;">No personnel found in this division.</span>';
            }
        });
    }
});