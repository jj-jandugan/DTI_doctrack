// static/js/history_panel.js

document.addEventListener('DOMContentLoaded', () => {
    const layoutContainer = document.getElementById('mainSplitLayout');
    const closeBtn = document.getElementById('closePanelBtn');
    const rows = document.querySelectorAll('.history-row');

    if (typeof TableFilter !== 'undefined') {
        new TableFilter('.history-row');
    }

    rows.forEach(row => {
        row.addEventListener('click', () => {
            // Highlight active row
            rows.forEach(r => r.classList.remove('table-active'));
            row.classList.add('table-active');

            // 1. Populate Main Info & Status
            if (document.getElementById('paneCtrl')) document.getElementById('paneCtrl').textContent = row.dataset.dts;

            const statusSpan = document.getElementById('paneStatus');
            if (statusSpan) {
                statusSpan.textContent = row.dataset.status;
                statusSpan.className = 'status ' + (row.dataset.statusclass || '').toLowerCase();
            }

            // 2. Handle Rejection Reason Logic
            const rejectReason = row.dataset.rejectreason;
            const rejectContainer = document.getElementById('rejectionReasonBlock');
            const rejectText = document.getElementById('paneRejectReason');

            if (rejectContainer && rejectText) {
                if (rejectReason && rejectReason.trim() !== '') {
                    rejectText.innerText = rejectReason;
                    rejectContainer.classList.remove('d-none');
                } else {
                    rejectContainer.classList.add('d-none');
                    rejectText.innerText = '';
                }
            }

            // 3. Dynamic Date Label (Incoming vs Outgoing)
            const direction = row.dataset.direction;
            const dynamicDateLabel = document.getElementById('dynamicDateLabel');
            if (dynamicDateLabel) {
                if (direction === 'incoming') {
                    dynamicDateLabel.innerText = 'Date & Time Received';
                } else {
                    dynamicDateLabel.innerText = 'Date & Time Dispatched';
                }
            }

            // 4. Populate Grid Data Safely
            const safeSetText = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = value;
            };

            safeSetText('paneReceived', row.dataset.received);
            safeSetText('paneDeadline', row.dataset.deadline);
            safeSetText('paneClass', row.dataset.class);
            safeSetText('paneType', row.dataset.type);
            safeSetText('paneSignatory', row.dataset.signatory);
            safeSetText('paneSubject', row.dataset.subject);
            safeSetText('paneOrigin', row.dataset.origin);
            safeSetText('paneSender', row.dataset.sender);
            safeSetText('paneAddress', row.dataset.address);
            safeSetText('paneReceiverName', row.dataset.receiver || 'N/A');
            safeSetText('paneParticulars', row.dataset.particulars);

            // 5. Footer Creator Info (Date encoded moved here)
            safeSetText('paneCreatorName', row.dataset.creator);
            safeSetText('paneCreatorDiv', row.dataset.creatordiv);
            safeSetText('paneCreatorDate', row.dataset.createdfull);

            // 6. Render Accordion Attachments
            const attContainer = document.getElementById('paneAttachments');
            const attachCount = document.getElementById('paneAttachCount');

            if (attContainer && attachCount) {
                attContainer.innerHTML = '';
                try {
                    const atts = JSON.parse(row.dataset.attachments || '[]');
                    attachCount.textContent = atts.length;

                    if (atts.length > 0) {
                        let listHtml = '<ul class="list-group list-group-flush rounded-3 border">';
                        atts.forEach(file => {
                            let ext = file.name.split('.').pop().toLowerCase();
                            let icon = 'fa-file';
                            let color = 'text-secondary';

                            if (ext === 'pdf') { icon = 'fa-file-pdf'; color = 'text-danger'; }
                            else if (['doc', 'docx'].includes(ext)) { icon = 'fa-file-word'; color = 'text-primary'; }
                            else if (['jpg', 'jpeg', 'png'].includes(ext)) { icon = 'fa-image'; color = 'text-success'; }

                            listHtml += `
                                <li class="list-group-item d-flex align-items-center gap-2 py-3">
                                    <i class="fa-solid ${icon} ${color}"></i>
                                    <a href="${file.url}" target="_blank" class="text-decoration-none text-dark fw-bold">${file.name}</a>
                                </li>
                            `;
                        });
                        listHtml += '</ul>';
                        attContainer.innerHTML = listHtml;
                    } else {
                        attContainer.innerHTML = '<div class="text-muted small fst-italic p-3 border rounded-3 bg-light">No files attached to this document.</div>';
                    }
                } catch (e) {
                    console.error("Error parsing attachments JSON", e);
                }
            }

            // Open the panel
            layoutContainer.classList.add('panel-open');
        });
    });

    // Close panel
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            layoutContainer.classList.remove('panel-open');
            rows.forEach(r => r.classList.remove('table-active'));
        });
    }
});