// static/js/export.js

function exportToExcel() {
    const wsData = [
        ["DTS No.", "Date & Time Created", "Classification", "Document Type", "Subject", "Sender (Origin/Name)", "Receiver (Destination/Name)", "Signatory", "Status"]
    ];

    let exportedCount = 0;

    // ==========================================
    // METHOD A: UNPAGINATED JSON EXPORT (divHistory)
    // ==========================================
    if (typeof allHistoryData !== 'undefined') {

        // 1. Get current filter states from the DOM
        const searchTerm = document.getElementById('searchInput') ? document.getElementById('searchInput').value.toLowerCase() : '';
        const classTerm = document.getElementById('classFilter') ? document.getElementById('classFilter').value.toLowerCase() : '';
        const typeTerm = document.getElementById('typeFilter') ? document.getElementById('typeFilter').value.toLowerCase() : '';
        const statusTerm = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value.toLowerCase() : '';
        const directionTerm = document.getElementById('directionFilter') ? document.getElementById('directionFilter').value.toLowerCase() : '';
        const startTerm = document.getElementById('startDate') ? document.getElementById('startDate').value : '';
        const endTerm = document.getElementById('endDate') ? document.getElementById('endDate').value : '';

        // 2. Filter the entire hidden database payload
        const filteredData = allHistoryData.filter(row => {
            const matchesSearch = row.search.includes(searchTerm);
            const matchesClass = classTerm === '' || (row.class || '').toLowerCase().includes(classTerm);
            const matchesType = typeTerm === '' || (row.type || '').toLowerCase().includes(typeTerm);
            const matchesStatus = statusTerm === '' || (row.status || '').toLowerCase().includes(statusTerm);
            const matchesDirection = directionTerm === '' || row.direction === directionTerm;

            let matchesDate = true;
            if (startTerm && row.date_raw < startTerm) matchesDate = false;
            if (endTerm && row.date_raw > endTerm) matchesDate = false;

            return matchesSearch && matchesClass && matchesType && matchesStatus && matchesDirection && matchesDate;
        });

        if (filteredData.length === 0) {
            alert("No matching records to export. Please adjust your filters.");
            return;
        }

        // 3. Map filtered data to Excel rows
        filteredData.forEach(row => {
            wsData.push([row.dts, row.created, row.class, row.type, row.subject, row.sender, row.receiver, row.signatory, row.status]);
            exportedCount++;
        });
    }
    // ==========================================
    // METHOD B: DOM SCRAPING FALLBACK (Other Pages)
    // ==========================================
    else {
        const rows = document.querySelectorAll('.doc-row, .desk-row, .history-row');
        rows.forEach(row => {
            if (window.getComputedStyle(row).display !== 'none') {
                const dts = row.dataset.dts || '---';
                const dateCreated = row.dataset.createdfull || '---';
                const classification = row.dataset.class || '---';
                const docType = row.dataset.type || '---';
                const subject = row.dataset.subject || '---';
                const sender = row.dataset.sender || '---';
                const receiver = row.dataset.receiver || '---';
                const signatory = row.dataset.signatory || '---';
                const status = row.dataset.status || '---';

                wsData.push([dts, dateCreated, classification, docType, subject, sender, receiver, signatory, status]);
                exportedCount++;
            }
        });

        if (exportedCount === 0) {
            alert("No visible records to export. Please adjust your filters.");
            return;
        }
    }

    // ==========================================
    // EXCEL FORMATTING AND DOWNLOAD
    // ==========================================
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(wsData);

    // Auto-fit column widths
    const colWidths = wsData[0].map((_, colIndex) => ({
        wch: Math.max(...wsData.map(row => (row[colIndex] ? row[colIndex].toString().length : 0))) + 2
    }));
    ws['!cols'] = colWidths;

    // Freeze header
    ws['!views'] = [{ state: 'frozen', ySplit: 1 }];

    XLSX.utils.book_append_sheet(wb, ws, "DTS_Records");

    const dateStr = new Date().toISOString().split('T')[0];
    XLSX.writeFile(wb, `DTS_Export_${dateStr}.xlsx`);
}