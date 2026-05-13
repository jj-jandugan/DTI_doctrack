// static/js/div_history.js

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Filters using your modular class
    if (typeof TableFilter !== 'undefined') {
        new TableFilter('.history-row');
    }

    // 2. Slide Panel Logic
    const sidePanel = document.getElementById('sidePanel');
    const closeBtn = document.getElementById('closePanelBtn');

    if (sidePanel && closeBtn) {
        document.querySelectorAll('.history-row').forEach(row => {
            row.addEventListener('click', function() {
                // Highlight row
                document.querySelectorAll('.history-row').forEach(r => r.style.backgroundColor = '');
                this.style.backgroundColor = '#f1f5f9';

                // Headers
                if(document.getElementById('paneDTS')) document.getElementById('paneDTS').textContent = this.dataset.dts;
                const statusBadge = document.getElementById('paneStatus');
                if (statusBadge) {
                    statusBadge.className = this.dataset.statusclass;
                    statusBadge.textContent = this.dataset.status;
                }

                // Body
                if(document.getElementById('paneCreated')) document.getElementById('paneCreated').textContent = this.dataset.created;
                if(document.getElementById('paneReceived')) document.getElementById('paneReceived').textContent = this.dataset.received;
                if(document.getElementById('paneDeadline')) document.getElementById('paneDeadline').textContent = this.dataset.deadline;
                if(document.getElementById('paneSubject')) document.getElementById('paneSubject').textContent = this.dataset.subject;
                if(document.getElementById('paneAddress')) document.getElementById('paneAddress').textContent = this.dataset.address;
                if(document.getElementById('paneSignatory')) document.getElementById('paneSignatory').textContent = this.dataset.signatory;

                // Incoming conditional blocks
                const direction = this.dataset.direction;
                const incomingFields = document.getElementById('incomingFieldsBlock');
                const incomingCreator = document.getElementById('incomingCreatorBlock');

                if (direction === 'incoming') {
                    if(incomingFields) incomingFields.style.display = 'block';
                    if(incomingCreator) incomingCreator.style.display = 'block';
                    if(document.getElementById('paneSender')) document.getElementById('paneSender').textContent = this.dataset.sender;
                    if(document.getElementById('paneOrigin')) document.getElementById('paneOrigin').textContent = this.dataset.origin;
                    if(document.getElementById('paneCreatorName')) document.getElementById('paneCreatorName').textContent = this.dataset.creator;
                    if(document.getElementById('paneCreatorDiv')) document.getElementById('paneCreatorDiv').textContent = this.dataset.creatordiv;
                } else {
                    if(incomingFields) incomingFields.style.display = 'none';
                    if(incomingCreator) incomingCreator.style.display = 'none';
                }

                // Attachments
                const attachmentsDiv = document.getElementById('paneAttachments');
                if (attachmentsDiv) {
                    attachmentsDiv.innerHTML = '';
                    const rawAttachments = this.dataset.attachments;
                    if (rawAttachments) {
                        const files = JSON.parse(rawAttachments);
                        if (files.length > 0) {
                            files.forEach(f => {
                                attachmentsDiv.innerHTML += `
                                    <a href="${f.url}" target="_blank" class="attachment-link">
                                        <i class="fa-regular fa-file-pdf text-danger me-2"></i> ${f.name}
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

        // Close Panel
        closeBtn.addEventListener('click', function() {
            sidePanel.classList.remove('active');
            document.querySelectorAll('.history-row').forEach(r => r.style.backgroundColor = '');
        });
    }
});