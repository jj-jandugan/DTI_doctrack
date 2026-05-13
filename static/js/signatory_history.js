// static/js/signatory_history.js

document.addEventListener('DOMContentLoaded', function() {
    // ==========================================
    // 1. INITIALIZE MODULAR FILTERS
    // ==========================================
    // This utilizes your existing filters.js file automatically
    if (typeof TableFilter !== 'undefined') {
        new TableFilter('.history-row');
    }

    // ==========================================
    // 2. SLIDE-IN PANEL LOGIC
    // ==========================================
    const sidePanel = document.getElementById('sidePanel');
    const closeBtn = document.getElementById('closePanelBtn');

    if (sidePanel && closeBtn) {
        document.querySelectorAll('.history-row').forEach(row => {
            row.addEventListener('click', function() {
                // Highlight row
                document.querySelectorAll('.history-row').forEach(r => r.style.backgroundColor = '');
                this.style.backgroundColor = '#f1f5f9';

                // Populate Headers
                document.getElementById('paneDTS').textContent = this.dataset.dts;

                const statusBadge = document.getElementById('paneStatus');
                if (statusBadge) {
                    statusBadge.className = this.dataset.statusclass;
                    statusBadge.textContent = this.dataset.status;
                }

                // Populate Body Strings (Including the Approved Date)
                if (document.getElementById('paneCreated')) document.getElementById('paneCreated').textContent = this.dataset.created;
                if (document.getElementById('paneApproved')) document.getElementById('paneApproved').textContent = this.dataset.approved;
                if (document.getElementById('paneReceived')) document.getElementById('paneReceived').textContent = this.dataset.received;
                if (document.getElementById('paneDeadline')) document.getElementById('paneDeadline').textContent = this.dataset.deadline;
                if (document.getElementById('paneSubject')) document.getElementById('paneSubject').textContent = this.dataset.subject;
                if (document.getElementById('paneParticulars')) document.getElementById('paneParticulars').textContent = this.dataset.particulars || 'No particulars provided.';
                if (document.getElementById('paneAddress')) document.getElementById('paneAddress').textContent = this.dataset.address;

                // Handle conditional blocks (Incoming vs Outgoing differences)
                const direction = this.dataset.direction;
                const incomingFields = document.getElementById('incomingFieldsBlock');
                const incomingCreator = document.getElementById('incomingCreatorBlock');

                if (direction === 'incoming') {
                    if (incomingFields) incomingFields.style.display = 'block';
                    if (incomingCreator) incomingCreator.style.display = 'block';

                    if (document.getElementById('paneSender')) document.getElementById('paneSender').textContent = this.dataset.sender;
                    if (document.getElementById('paneOrigin')) document.getElementById('paneOrigin').textContent = this.dataset.origin;
                    if (document.getElementById('paneCreatorName')) document.getElementById('paneCreatorName').textContent = this.dataset.creator;
                    if (document.getElementById('paneCreatorDiv')) document.getElementById('paneCreatorDiv').textContent = this.dataset.creatordiv;
                } else {
                    if (incomingFields) incomingFields.style.display = 'none';
                    if (incomingCreator) incomingCreator.style.display = 'none';
                }

                // Attachments Array Render
                const attachmentsDiv = document.getElementById('paneAttachments');
                if (attachmentsDiv) {
                    attachmentsDiv.innerHTML = '';
                    const rawAttachments = this.dataset.attachments;

                    if (rawAttachments) {
                        const attachmentsArray = JSON.parse(rawAttachments);
                        if (attachmentsArray.length > 0) {
                            attachmentsArray.forEach(file => {
                                attachmentsDiv.innerHTML += `
                                    <a href="${file.url}" target="_blank" class="attachment-link">
                                        <i class="fa-regular fa-file-pdf text-danger me-2"></i> ${file.name}
                                    </a>`;
                            });
                        } else {
                            attachmentsDiv.innerHTML = '<span class="text-muted fst-italic">No attachments.</span>';
                        }
                    }
                }

                sidePanel.classList.add('active');
            });
        });

        // Close logic
        closeBtn.addEventListener('click', function() {
            sidePanel.classList.remove('active');
            document.querySelectorAll('.history-row').forEach(r => r.style.backgroundColor = '');
        });
    }
});