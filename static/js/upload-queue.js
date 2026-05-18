// static/js/upload-queue.js
class UploadQueue {
    constructor(dropZoneId, fileInputId, displayId, labelId) {
        this.dropZone = document.getElementById(dropZoneId);
        this.fileInput = document.getElementById(fileInputId);
        this.display = document.getElementById(displayId);
        this.label = document.getElementById(labelId);
        this.queue = [];

        // Define all allowed file extensions here
        this.allowedExtensions = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.jpeg', '.png'];

        this.init();
    }

    init() {
        if (!this.dropZone) return;

        // Prevent click if the target is a remove button
        this.dropZone.onclick = (e) => {
            if (e.target.closest('.btn-remove-file')) return;
            this.fileInput.click();
        };

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev =>
            this.dropZone.addEventListener(ev, (e) => { e.preventDefault(); e.stopPropagation(); }));

        this.dropZone.ondrop = (e) => {
            this.processFiles(e.dataTransfer.files);
        };

        this.fileInput.onchange = () => {
            this.processFiles(this.fileInput.files);
        };
    }

    processFiles(files) {
        let invalidFiles = [];

        Array.from(files).forEach(f => {
            // Get the file extension
            const ext = '.' + f.name.split('.').pop().toLowerCase();

            // Check if the extension is in our allowed list
            if (this.allowedExtensions.includes(ext)) {
                this.queue.push(f);
            } else {
                invalidFiles.push(f.name);
            }
        });

        // Alert the user if they tried to upload restricted files
        if (invalidFiles.length > 0) {
            alert("The following files are not supported:\n" + invalidFiles.join('\n') + "\n\nPlease upload only PDF, Word, Excel, or Image files.");
        }

        this.sync();
    }

    sync() {
        const dt = new DataTransfer();
        this.queue.forEach(f => dt.items.add(f));
        this.fileInput.files = dt.files;
        this.render();
    }

    render() {
        this.display.innerHTML = this.queue.map((f, i) => `
            <div class="file-item-row d-flex justify-content-between align-items-center bg-white border rounded px-3 py-2 mb-2 shadow-sm">
                <span class="text-dark small fw-bold text-truncate me-3"><i class="fa-solid fa-file text-secondary me-2"></i>${f.name}</span>
                <button type="button" class="btn-remove-file text-danger bg-transparent border-0 fs-5 p-0 m-0" onclick="window.uploader.remove(${i})" title="Remove File">&times;</button>
            </div>`).join('');
    }

    remove(idx) {
        this.queue.splice(idx, 1);
        this.sync();
    }
}