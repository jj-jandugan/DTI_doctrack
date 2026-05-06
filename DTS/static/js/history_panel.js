// static/js/history_panel.js

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Global Filters using the existing class
    if (typeof TableFilter !== 'undefined') {
        new TableFilter('.history-row');
    }

    // 2. Slide Panel Logic
    const sidePanel = document.getElementById('sidePanel');
    const closeBtn = document.getElementById('closePanelBtn');
    const tableRows = document.querySelectorAll('.history-row');

    if (sidePanel && closeBtn) {
        tableRows.forEach(row => {
            row.addEventListener('click', function() {
                // Highlight active row
                tableRows.forEach(r => r.style.backgroundColor = '');
                this.style.backgroundColor = '#f1f5f9';

                // Populate Header
                document.getElementById('paneDTS').textContent = this.dataset.ctrl_no;

                const statusBadge = document.getElementById('paneStatus');
                statusBadge.className = this.dataset.statusclass;
                statusBadge.textContent = this.dataset.status;

                // Populate Body
                document.getElementById('paneSubject').textContent = this.dataset.subject;
                document.getElementById('paneParticulars').textContent = this.dataset.particulars || 'None';
                document.getElementById('paneType').textContent = this.dataset.type;
                document.getElementById('paneClass').textContent = this.dataset.class;

                // Handle Dynamic Address Block (Incoming vs Outgoing)
                const direction = this.dataset.direction;
                const addrBlock = document.getElementById('dynamicAddressBlock');
                const addrLabel = document.getElementById('addressLabel');
                const addrValue = document.getElementById('addressValue');

                if (direction === 'incoming') {
                    addrBlock.style.backgroundColor = '#fef3c7'; // yellow-ish
                    addrBlock.style.borderColor = '#fde68a';
                    addrLabel.innerHTML = 'RECEIVED FROM';
                    addrValue.innerHTML = this.dataset.origin + '<br><small class="text-muted">' + this.dataset.sender + '</small>';
                } else {
                    addrBlock.style.backgroundColor = '#dcfce7'; // green-ish
                    addrBlock.style.borderColor = '#bbf7d0';
                    addrLabel.innerHTML = 'DISPATCHED TO';
                    addrValue.innerHTML = this.dataset.address;
                }

                // Populate Attachments
                const attachmentsDiv = document.getElementById('paneAttachments');
                attachmentsDiv.innerHTML = '';
                const rawAttachments = this.dataset.attachments;

                if (rawAttachments) {
                    const attachmentsArray = JSON.parse(rawAttachments);
                    if (attachmentsArray.length > 0) {
                        attachmentsArray.forEach(file => {
                            attachmentsDiv.innerHTML += `<a href="${file.url}" target="_blank" class="attachment-link"><i class="fa-regular fa-file-pdf text-danger me-2"></i> ${file.name}</a>`;
                        });
                    } else {
                        attachmentsDiv.innerHTML = '<span class="text-muted fst-italic">No attachments.</span>';
                    }
                }

                sidePanel.classList.add('active');
            });
        });

        // Close Panel
        closeBtn.addEventListener('click', () => {
            sidePanel.classList.remove('active');
            tableRows.forEach(r => r.style.backgroundColor = '');
        });
    }
});