// static/js/filters.js

class TableFilter {
    /**
     * @param {string} rowSelector - The CSS class for the table rows to filter (e.g., '.doc-row' or '.history-row')
     */
    constructor(rowSelector) {
        this.rows = document.querySelectorAll(rowSelector);

        // Grab filter inputs if they exist on the page
        this.searchInput = document.getElementById('searchInput');
        this.classFilter = document.getElementById('classFilter');
        this.typeFilter = document.getElementById('typeFilter');
        this.directionFilter = document.getElementById('directionFilter');
        this.startDate = document.getElementById('startDate');
        this.endDate = document.getElementById('endDate');

        this.initEvents();
    }

    initEvents() {
        // Attach event listeners only to elements that actually exist on the current page
        if (this.searchInput) this.searchInput.addEventListener('keyup', () => this.filter());
        if (this.classFilter) this.classFilter.addEventListener('change', () => this.filter());
        if (this.typeFilter) this.typeFilter.addEventListener('change', () => this.filter());
        if (this.directionFilter) this.directionFilter.addEventListener('change', () => this.filter());
        if (this.startDate) this.startDate.addEventListener('change', () => this.filter());
        if (this.endDate) this.endDate.addEventListener('change', () => this.filter());
    }

    filter() {
        // Get current values (default to empty string if the filter doesn't exist)
        const searchTerm = this.searchInput ? this.searchInput.value.toLowerCase() : '';
        const classTerm = this.classFilter ? this.classFilter.value.toLowerCase() : '';
        const typeTerm = this.typeFilter ? this.typeFilter.value.toLowerCase() : '';
        const directionTerm = this.directionFilter ? this.directionFilter.value.toLowerCase() : '';
        const startTerm = this.startDate ? this.startDate.value : '';
        const endTerm = this.endDate ? this.endDate.value : '';

        this.rows.forEach(row => {
            // 1. Search text (combines all elements with .search-target)
            const searchTargets = Array.from(row.querySelectorAll('.search-target'));
            const searchContent = searchTargets.map(el => el.innerText.toLowerCase()).join(' ');
            const matchesSearch = searchContent.includes(searchTerm);

            // 2. Dropdown exact matches (using hidden columns/data attributes)
            const classContent = row.querySelector('.class-target') ? row.querySelector('.class-target').innerText.toLowerCase() : '';
            const matchesClass = classTerm === '' || classContent.includes(classTerm);

            const typeContent = row.querySelector('.type-target') ? row.querySelector('.type-target').innerText.toLowerCase() : '';
            const matchesType = typeTerm === '' || typeContent.includes(typeTerm);

            const directionContent = row.querySelector('.direction-target') ? row.querySelector('.direction-target').innerText.toLowerCase() : '';
            const matchesDirection = directionTerm === '' || directionContent === directionTerm;

            // 3. Date Range logic
            const docDateEl = row.querySelector('.date-target');
            const docDate = docDateEl ? docDateEl.innerText : null;
            let matchesDate = true;

            if (docDate) {
                if (startTerm && docDate < startTerm) matchesDate = false;
                if (endTerm && docDate > endTerm) matchesDate = false;
            }

            // Apply visibility
            if (matchesSearch && matchesClass && matchesType && matchesDirection && matchesDate) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
}