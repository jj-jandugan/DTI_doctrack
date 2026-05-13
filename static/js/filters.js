// static/js/filters.js

class TableFilter {
    constructor(rowSelector, rowsPerPage = 10) {
        this.rows = Array.from(document.querySelectorAll(rowSelector));
        this.rowsPerPage = rowsPerPage;
        this.currentPage = 1;
        this.filteredRows = [...this.rows]; // Initially, all rows are matches

        // Grab filter inputs
        this.searchInput = document.getElementById('searchInput');
        this.classFilter = document.getElementById('classFilter');
        this.typeFilter = document.getElementById('typeFilter');
        this.statusFilter = document.getElementById('statusFilter');
        this.directionFilter = document.getElementById('directionFilter');
        this.startDate = document.getElementById('startDate');
        this.endDate = document.getElementById('endDate');

        // Find the container where we will draw the JS pagination buttons
        this.paginationContainer = document.getElementById('pagination-container');

        this.initEvents();
        this.filter(); // Run immediately to setup page 1
    }

    initEvents() {
        // When any filter changes, jump back to page 1 and run the filter
        const triggerFilter = () => {
            this.currentPage = 1;
            this.filter();
        };

        if (this.searchInput) this.searchInput.addEventListener('keyup', triggerFilter);
        if (this.classFilter) this.classFilter.addEventListener('change', triggerFilter);
        if (this.typeFilter) this.typeFilter.addEventListener('change', triggerFilter);
        if (this.statusFilter) this.statusFilter.addEventListener('change', triggerFilter);
        if (this.directionFilter) this.directionFilter.addEventListener('change', triggerFilter);
        if (this.startDate) this.startDate.addEventListener('change', triggerFilter);
        if (this.endDate) this.endDate.addEventListener('change', triggerFilter);
    }

    filter() {
        const searchTerm = this.searchInput ? this.searchInput.value.toLowerCase() : '';
        const classTerm = this.classFilter ? this.classFilter.value.toLowerCase() : '';
        const typeTerm = this.typeFilter ? this.typeFilter.value.toLowerCase() : '';
        const statusTerm = this.statusFilter ? this.statusFilter.value.toLowerCase() : '';
        const directionTerm = this.directionFilter ? this.directionFilter.value.toLowerCase() : '';
        const startTerm = this.startDate ? this.startDate.value : '';
        const endTerm = this.endDate ? this.endDate.value : '';

        this.filteredRows = [];

        this.rows.forEach(row => {
            const searchTargets = Array.from(row.querySelectorAll('.search-target'));
            const searchContent = row.textContent.toLowerCase();
            const matchesSearch = searchContent.includes(searchTerm);

            const classContent = row.querySelector('.class-target') ? row.querySelector('.class-target').innerText.toLowerCase() : '';
            const matchesClass = classTerm === '' || classContent.includes(classTerm);

            const typeContent = row.querySelector('.type-target') ? row.querySelector('.type-target').innerText.toLowerCase() : '';
            const matchesType = typeTerm === '' || typeContent.includes(typeTerm);

            const statusContent = row.querySelector('.status-target') ? row.querySelector('.status-target').innerText.toLowerCase() : '';
            const matchesStatus = statusTerm === '' || statusContent.includes(statusTerm);

            const directionContent = row.querySelector('.direction-target') ? row.querySelector('.direction-target').innerText.toLowerCase() : '';
            const matchesDirection = directionTerm === '' || directionContent === directionTerm;

            const docDateEl = row.querySelector('.date-target');
            const docDate = docDateEl ? docDateEl.innerText : null;
            let matchesDate = true;

            if (docDate) {
                if (startTerm && docDate < startTerm) matchesDate = false;
                if (endTerm && docDate > endTerm) matchesDate = false;
            }

            // If it matches all filters, save it to our filtered array
            if (matchesSearch && matchesClass && matchesType && matchesStatus && matchesDirection && matchesDate) {
                this.filteredRows.push(row);
            } else {
                row.style.display = 'none'; // Hide non-matches immediately
            }
        });

        this.renderPage();
    }

    renderPage() {
        // Hide ALL matched rows first
        this.filteredRows.forEach(row => row.style.display = 'none');

        // Calculate the slice for the current page
        const startIndex = (this.currentPage - 1) * this.rowsPerPage;
        const endIndex = startIndex + this.rowsPerPage;

        // Show only the 10 rows for the current page
        const rowsToShow = this.filteredRows.slice(startIndex, endIndex);
        rowsToShow.forEach(row => row.style.display = '');

        this.renderPaginationControls();
    }

    renderPaginationControls() {
        if (!this.paginationContainer) return;
        this.paginationContainer.innerHTML = '';

        const totalRecords = this.filteredRows.length;
        const totalPages = Math.ceil(totalRecords / this.rowsPerPage);

        // If there are no records, hide the pagination container completely
        if (totalRecords === 0) {
            this.paginationContainer.style.display = 'none';
            return;
        } else {
            this.paginationContainer.style.display = '';
        }

        // 1. Update the container classes to perfectly match your PHP layout
        this.paginationContainer.className = "pagination-wrapper d-flex justify-content-between align-items-center mt-4 px-3 w-100";

        // 2. Build the Pagination Info section (Showing X of Y pages...)
        const infoDiv = document.createElement('div');
        infoDiv.className = 'pagination-info';
        infoDiv.innerHTML = `
            <span class="text-muted small fw-medium">
                Showing <span class="text-dark">${this.currentPage}</span> of <span class="text-dark">${totalPages}</span> pages
                <span class="mx-1 text-opacity-25">|</span>
                Total of <span class="text-primary fw-bold">${totalRecords}</span> records
            </span>
        `;
        this.paginationContainer.appendChild(infoDiv);

        // 3. Only build the clickable Next/Prev buttons if there is more than 1 page
        if (totalPages > 1) {
            const ul = document.createElement('ul');
            ul.className = 'pagination mb-0';

            // Previous Button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${this.currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<button class="page-link shadow-none" style="cursor: pointer;">Previous</button>`;
            prevLi.onclick = (e) => { e.preventDefault(); this.goToPage(this.currentPage - 1); };
            ul.appendChild(prevLi);

            // Page Numbers
            for (let i = 1; i <= totalPages; i++) {
                const pageLi = document.createElement('li');
                pageLi.className = `page-item ${this.currentPage === i ? 'active' : ''}`;
                pageLi.innerHTML = `<button class="page-link shadow-none" style="cursor: pointer;">${i}</button>`;
                pageLi.onclick = (e) => { e.preventDefault(); this.goToPage(i); };
                ul.appendChild(pageLi);
            }

            // Next Button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${this.currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<button class="page-link shadow-none" style="cursor: pointer;">Next</button>`;
            nextLi.onclick = (e) => { e.preventDefault(); this.goToPage(this.currentPage + 1); };
            ul.appendChild(nextLi);

            this.paginationContainer.appendChild(ul);
        }
    }

    goToPage(page) {
        const totalPages = Math.ceil(this.filteredRows.length / this.rowsPerPage);
        if (page < 1 || page > totalPages) return;
        this.currentPage = page;
        this.renderPage();
    }
}