// static/js/create_document.js

window.addRecipientRow = function(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const template = container.firstElementChild;
    const newRow = template.cloneNode(true);

    newRow.querySelectorAll('input').forEach(input => input.value = '');
    newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);

    const removeBtn = newRow.querySelector('.remove-recipient-btn');
    if (removeBtn) removeBtn.classList.remove('d-none');

    newRow.style.opacity = '0';
    newRow.style.transition = 'opacity 0.2s ease-in-out';
    container.appendChild(newRow);

    setTimeout(() => newRow.style.opacity = '1', 50);
};

window.removeRow = function(button) {
    const row = button.closest('.bg-white.border.rounded');
    if (!row) return;

    row.style.transition = 'opacity 0.2s ease-in-out';
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 200);
};

document.addEventListener('DOMContentLoaded', function() {

    // 1. GLOBAL MODAL CLEANUP
    document.addEventListener('hidden.bs.modal', function () {
        if (!document.querySelector('.modal.show')) {
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    });

    // 2. TABLE FILTERING & UPLOADER
    if (typeof TableFilter !== 'undefined') new TableFilter('.doc-row');
    if(document.getElementById('dropZone')) window.uploader = new UploadQueue('dropZone', 'fileInput', 'fileQueueDisplay');

    // 3. CACHE CLASSIFICATIONS
    const classSelect = document.getElementById('classification');
    const classHidden = document.getElementById('hidden_classification');
    let internalId = "", externalId = "";
    if (classSelect) {
        Array.from(classSelect.options).forEach(opt => {
            if (opt.text.toLowerCase().includes('internal')) internalId = opt.value;
            if (opt.text.toLowerCase().includes('external')) externalId = opt.value;
        });
    }

    // 4. OUTGOING LOGIC (Destination -> Classification)
    const routeTypeSelect = document.getElementById('routeType');
    if (routeTypeSelect) {
        routeTypeSelect.addEventListener('change', function() {
            document.querySelectorAll('.routing-block').forEach(el => el.classList.add('d-none'));

            const routeDiv = document.getElementById('routeDivision');
            const routeGrp = document.getElementById('route_group');
            const dtiInput = document.querySelector('#dti-recipient-container .dti-branch-input');
            const extInput = document.querySelector('#ext-recipient-container .ext-office-input');

            if(routeDiv) routeDiv.required = false;
            if(routeGrp) routeGrp.required = false;
            if (dtiInput) dtiInput.required = false;
            if (extInput) extInput.required = false;

            const val = this.value;

            if (val === 'division' || val === 'group') {
                if (val === 'division') {
                    document.getElementById('block-division').classList.remove('d-none');
                    if(routeDiv) routeDiv.required = true;
                } else {
                    document.getElementById('block-group').classList.remove('d-none');
                    if(routeGrp) routeGrp.required = true;
                }

                if (classSelect) { classSelect.style.pointerEvents = 'auto'; classSelect.classList.remove('bg-light'); }
                if (classHidden) classHidden.value = "";
            }
            else if (val === 'within_dti') {
                document.getElementById('block-dtibranch').classList.remove('d-none');
                if (dtiInput) dtiInput.required = true;
                if (classSelect) { classSelect.value = internalId; classSelect.style.pointerEvents = 'none'; classSelect.classList.add('bg-light'); }
                if (classHidden) classHidden.value = internalId;
            }
            else if (val === 'outside_dti') {
                document.getElementById('block-external').classList.remove('d-none');
                if (extInput) extInput.required = true;
                if (classSelect) { classSelect.value = externalId; classSelect.style.pointerEvents = 'none'; classSelect.classList.add('bg-light'); }
                if (classHidden) classHidden.value = externalId;
            }
        });
    }

    // 5. INCOMING LOGIC (Origin -> Classification)
    const originTypeSelect = document.getElementById('originType');
    if (originTypeSelect) {
        originTypeSelect.addEventListener('change', function() {
            document.getElementById('block-origin-dti').classList.add('d-none');
            document.getElementById('block-origin-ext').classList.add('d-none');

            document.querySelectorAll('#block-origin-dti input, #block-origin-dti select').forEach(el => { el.disabled = true; el.required = false; });
            document.querySelectorAll('#block-origin-ext input, #block-origin-ext select').forEach(el => { el.disabled = true; el.required = false; });

            const val = this.value;

            if (val === 'within_dti') {
                document.getElementById('block-origin-dti').classList.remove('d-none');
                document.querySelectorAll('#block-origin-dti input, #block-origin-dti select').forEach(el => { el.disabled = false; });
                document.getElementById('originDti').required = true;

                if (classSelect) { classSelect.value = internalId; classSelect.style.pointerEvents = 'none'; classSelect.classList.add('bg-light'); }
                if (classHidden) classHidden.value = internalId;
            }
            else if (val === 'outside_dti') {
                document.getElementById('block-origin-ext').classList.remove('d-none');
                document.querySelectorAll('#block-origin-ext input, #block-origin-ext select').forEach(el => { el.disabled = false; });
                document.getElementById('originExt').required = true;

                if (classSelect) { classSelect.value = externalId; classSelect.style.pointerEvents = 'none'; classSelect.classList.add('bg-light'); }
                if (classHidden) classHidden.value = externalId;
            }
        });
    }

    const routeTypeIncoming = document.getElementById('routeTypeIncoming');
    if (routeTypeIncoming) {
        routeTypeIncoming.addEventListener('change', function() {
            document.getElementById('block-division').classList.add('d-none');
            document.getElementById('block-group').classList.add('d-none');

            const routeDiv = document.getElementById('routeDivision');
            const routeGrp = document.getElementById('route_group');
            if(routeDiv) routeDiv.required = false;
            if(routeGrp) routeGrp.required = false;

            if (this.value === 'division') {
                document.getElementById('block-division').classList.remove('d-none');
                if(routeDiv) routeDiv.required = true;
            } else if (this.value === 'group') {
                document.getElementById('block-group').classList.remove('d-none');
                if(routeGrp) routeGrp.required = true;
            }
        });
    }

    // ==========================================
    // 6. POPULATE USERS BASED ON DIVISION
    // ==========================================
    const routeDiv = document.getElementById('routeDivision');
    if (routeDiv) {
        routeDiv.addEventListener('change', function() {
            const divId = this.value;
            const usersData = document.getElementById('usersData');

            if (!usersData) return;

            const usersByDiv = JSON.parse(usersData.textContent);
            const container = document.getElementById('routeUsersContainer');

            // 1. Grab the Creator ID
            const docCreatorIdInput = document.getElementById('docCreatorId');
            const creatorId = docCreatorIdInput ? String(docCreatorIdInput.value) : null;

            // 2. Grab ALL Signatory IDs from the dropdown options
            const signatorySelect = document.querySelector('select[name="signatory"]');
            const allSignatoryIds = signatorySelect ? Array.from(signatorySelect.options).map(opt => String(opt.value)).filter(val => val !== "") : [];

            container.innerHTML = '';
            if (usersByDiv[divId] && usersByDiv[divId].length > 0) {

                // NEW: Permanently filter out the Creator and ALL users who are Signatories
                const filteredUsers = usersByDiv[divId].filter(user => {
                    const uid = String(user.id);
                    return uid !== creatorId && !allSignatoryIds.includes(uid);
                });

                if(filteredUsers.length > 0) {
                    filteredUsers.forEach(user => {
                        container.innerHTML += `
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="route_users[]" value="${user.id}" id="user_${user.id}">
                                <label class="form-check-label text-dark small" for="user_${user.id}">${user.first_name} ${user.last_name}</label>
                            </div>`;
                    });
                } else {
                     container.innerHTML = '<span class="text-muted small">No other personnel available.</span>';
                }
            } else {
                container.innerHTML = '<span class="text-muted small">No personnel available.</span>';
            }
        });
    }

    // 7. CUSTOM FORM VALIDATION BEFORE SUBMIT
    const btnFakeSubmit = document.getElementById('btnFakeSubmit');
    if(btnFakeSubmit) {
        btnFakeSubmit.addEventListener('click', function(e) {
            const form = document.getElementById('createDocumentForm');
            if (!form.checkValidity()) {
                form.reportValidity();
            } else {
                form.submit();
            }
        });
    }
});