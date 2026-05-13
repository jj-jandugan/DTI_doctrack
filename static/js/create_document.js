// static/js/create_document.js

document.addEventListener('DOMContentLoaded', function() {

    // ==========================================
    // 1. GLOBAL MODAL CLEANUP (Fixes the Logout Block Issue)
    // ==========================================
    // This listens to every modal. When ANY modal finishes closing, it wipes stuck backgrounds.
    document.addEventListener('hidden.bs.modal', function () {
        // Check if there are any other modals still open on the screen
        if (!document.querySelector('.modal.show')) {
            // Forcefully remove any stuck invisible backgrounds
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());

            // Remove the Bootstrap lock class from the body
            document.body.classList.remove('modal-open');

            // Reset scrolling and padding
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    });

    // ==========================================
    // 2. TABLE FILTERING
    // ==========================================
    if (typeof TableFilter !== 'undefined') {
        new TableFilter('.doc-row');
    }

    // ==========================================
    // 3. UPLOAD QUEUE & FORM VALIDATION
    // ==========================================
    const form = document.querySelector('form');
    const dropZone = document.getElementById('dropZone');
    const fileError = document.getElementById('fileError');

    if (typeof UploadQueue !== 'undefined') {
        window.uploader = new UploadQueue("dropZone", "fileInput", "fileQueueDisplay", "dropZoneText");
    }

    if (form && dropZone && fileError) {
        form.addEventListener('submit', (e) => {
            const hasFiles = window.uploader && window.uploader.queue && window.uploader.queue.length > 0;

            // If no files attached, stop submission and show red warning box
            if (!hasFiles) {
                e.preventDefault();
                e.stopPropagation();

                fileError.style.display = 'block';
                dropZone.style.borderColor = '#dc3545'; // Bootstrap red
                dropZone.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                fileError.style.display = 'none';
                dropZone.style.borderColor = '#d1d5db';
            }
        });

        // Reset error when user clicks the dropzone
        dropZone.addEventListener('click', () => {
            fileError.style.display = 'none';
            dropZone.style.borderColor = '#d1d5db';
        });
    }

    // ==========================================
    // 4. DYNAMIC ROUTING LOGIC
    // ==========================================
    const routeType = document.getElementById('routeType');
    const blockDivision = document.getElementById('block-division');
    const blockGroup = document.getElementById('block-group');
    const blockExternal = document.getElementById('block-external');

    const routeDivision = document.getElementById('routeDivision');
    const usersContainer = document.getElementById('routeUsersContainer');
    const sigSelect = document.querySelector('select[name="signatory"]');

    const usersDataEl = document.getElementById('usersData');
    const usersByDiv = usersDataEl ? JSON.parse(usersDataEl.textContent) : {};

    if (routeType) {
        routeType.addEventListener('change', function() {
            if(blockDivision) blockDivision.classList.toggle('d-none', this.value !== 'division');
            if(blockGroup) blockGroup.classList.toggle('d-none', this.value !== 'group');

            // Show the external block if Within DTI or Outside Agency is selected
            if(blockExternal) blockExternal.classList.toggle('d-none', !(this.value === 'within_dti' || this.value === 'outside_dti'));
        });
    }

    // ==========================================
    // 5. SMART PERSONNEL CHECKBOX POPULATION
    // ==========================================
    function populateUsers() {
        const divId = routeDivision ? routeDivision.value : null;
        if (!usersContainer) return;

        usersContainer.innerHTML = '';

        // Grab the currently selected signatory so we can hide them
        const selectedSigId = sigSelect ? sigSelect.value : null;

        if (divId && usersByDiv[divId] && usersByDiv[divId].length > 0) {
            let userCount = 0;

            usersByDiv[divId].forEach(user => {
                // SECURITY: Never show the selected signatory in the receiver list!
                if (String(user.id) === String(selectedSigId)) return;

                userCount++;
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

            // If the only person in the division was the Signatory, show empty message
            if (userCount === 0) {
                usersContainer.innerHTML = '<span class="text-muted" style="font-size: 0.85rem;">No other personnel available in this division.</span>';
            }
        } else {
            usersContainer.innerHTML = '<span class="text-muted" style="font-size: 0.85rem;">No personnel found in this division.</span>';
        }
    }

    // Trigger checkbox rendering when the Division changes
    if (routeDivision) {
        routeDivision.addEventListener('change', populateUsers);
    }

    // Instantly re-render checkboxes if the Signatory changes
    if (sigSelect) {
        sigSelect.addEventListener('change', populateUsers);
    }
});