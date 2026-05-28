// static/js/export.js

function exportToExcel() {
    // 1. Headers exactly as requested
    const wsData = [
        [
            "Control No.",
            "Remarks",
            "Form",
            "Date & Time Created",
            "Origin",
            "Subject",
            "Instruction",
            "Authority",
            "Destination",
            "Processor",
            "Action Taken",
            "Status",
            "Date & Time Closed"
        ]
    ];

    let exportedCount = 0;

    // ==========================================
    // METHOD A: JSON DATA SOURCE (divHistory)
    // ==========================================
    if (typeof allHistoryData !== 'undefined') {
        const searchTerm = document.getElementById('searchInput') ? document.getElementById('searchInput').value.toLowerCase() : '';
        const classTerm = document.getElementById('classFilter') ? document.getElementById('classFilter').value.toLowerCase() : '';
        const typeTerm = document.getElementById('typeFilter') ? document.getElementById('typeFilter').value.toLowerCase() : '';
        const statusTerm = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value.toLowerCase() : '';
        const directionTerm = document.getElementById('directionFilter') ? document.getElementById('directionFilter').value.toLowerCase() : '';
        const startTerm = document.getElementById('startDate') ? document.getElementById('startDate').value : '';
        const endTerm = document.getElementById('endDate') ? document.getElementById('endDate').value : '';

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
            alert("No matching records to export.");
            return;
        }

        // 2. Mapping PHP JSON keys to Excel columns
        filteredData.forEach(row => {
            wsData.push([
                row.dts,            // Control No. (Dts No.)
                row.class,          // Remarks (Classification)
                row.type,           // Form (Document Type)
                row.created,        // Date & Time Created
                row.sender,         // Origin (Origin Office/Agency)
                row.subject,        // Subject
                row.particulars,    // Instruction (Particulars)
                row.signatory,      // Authority (Signatory Name)
                row.receiver,       // Destination (Address)
                row.creator,        // Processor (Creator)
                "Forwarded",        // Action Taken (Hardcoded)
                row.status,         // Status
                row.action_date     // Date & Time Closed (Last Update)
            ]);
            exportedCount++;
        });
    }
    // ==========================================
    // METHOD B: FALLBACK (Scraping what is available)
    // ==========================================
    else {
        const rows = document.querySelectorAll('.history-row');
        rows.forEach(row => {
            if (window.getComputedStyle(row).display !== 'none') {
                // Scrapes only what is currently in your HTML table attributes
                wsData.push([
                    row.querySelector('.text-primary')?.innerText || '---',
                    row.querySelector('.class-target')?.innerText || '---',
                    row.querySelector('.type-target')?.innerText || '---',
                    row.querySelectorAll('td')[2]?.innerText.replace(/\n/g, ' ') || '---',
                    '---', // Origin not in basic table
                    row.querySelector('.text-dark.search-target')?.innerText || '---',
                    '---', // Particulars not in basic table
                    row.querySelectorAll('td')[6]?.innerText || '---',
                    row.querySelectorAll('td')[5]?.innerText.replace(/\n/g, ' ') || '---',
                    row.querySelector('.search-target .text-dark')?.innerText || '---',
                    "Forwarded",
                    row.querySelector('.status-target')?.innerText || '---',
                    row.querySelectorAll('td')[3]?.innerText.replace(/\n/g, ' ') || '---'
                ]);
                exportedCount++;
            }
        });
    }

    // EXCEL GENERATION
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(wsData);

    const colWidths = wsData[0].map((_, colIndex) => ({
        wch: Math.max(...wsData.map(row => (row[colIndex] ? row[colIndex].toString().length : 0))) + 5
    }));
    ws['!cols'] = colWidths;

    XLSX.utils.book_append_sheet(wb, ws, "DTS_Records");
    const dateStr = new Date().toISOString().split('T')[0];
    XLSX.writeFile(wb, `DTS_Export_${dateStr}.xlsx`);
}