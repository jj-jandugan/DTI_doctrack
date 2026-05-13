// DTS/static/js/sign_outgoing.js
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const classFilter = document.getElementById('classFilter');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const tableRows = document.querySelectorAll('.doc-row');

    function filterTable() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const classTerm = classFilter ? classFilter.value.toLowerCase() : '';
        const startTerm = startDate ? startDate.value : '';
        const endTerm = endDate ? endDate.value : '';

        tableRows.forEach(row => {
            const searchContent = Array.from(row.querySelectorAll('.search-target'))
                                       .map(el => el.innerText.toLowerCase()).join(' ');
            const classContent = row.querySelector('.class-target') ? row.querySelector('.class-target').innerText.toLowerCase() : '';
            const docDate = row.querySelector('.date-target') ? row.querySelector('.date-target').innerText : '';

            const matchesSearch = searchContent.includes(searchTerm);
            const matchesClass = classTerm === '' || classContent.includes(classTerm);

            let matchesDate = true;
            if (startTerm && docDate < startTerm) matchesDate = false;
            if (endTerm && docDate > endTerm) matchesDate = false;

            row.style.display = (matchesSearch && matchesClass && matchesDate) ? '' : 'none';
        });
    }

    if(searchInput) searchInput.addEventListener('keyup', filterTable);
    if(classFilter) classFilter.addEventListener('change', filterTable);
    if(startDate) startDate.addEventListener('change', filterTable);
    if(endDate) endDate.addEventListener('change', filterTable);
});

// Add this at the bottom of outgoing.js
document.addEventListener('DOMContentLoaded', function() {
    const lockedModal = document.getElementById('lockedModal');
    if (lockedModal) {
        lockedModal.addEventListener('show.bs.modal', function (event) {
            // event.relatedTarget is the <tr> row that was clicked
            const button = event.relatedTarget;
            const dtsNo = button.getAttribute('data-dts');
            const modalDtsSpan = lockedModal.querySelector('#lockedDtsNo');

            if (modalDtsSpan) {
                modalDtsSpan.textContent = dtsNo;
            }
        });
    }
});