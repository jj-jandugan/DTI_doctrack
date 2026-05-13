// static/js/upload-queue.js
class UploadQueue {
    constructor(dropZoneId, fileInputId, displayId, labelId) {
        this.dropZone = document.getElementById(dropZoneId);
        this.fileInput = document.getElementById(fileInputId);
        this.display = document.getElementById(displayId);
        this.label = document.getElementById(labelId);
        this.queue = [];
        this.init();
    }

    init() {
        if (!this.dropZone) return;

        // Prevent click if the target is a remove button
        this.dropZone.onclick = (e) => {
            // If they clicked the X button (or the icon inside it), do nothing
            if (e.target.closest('.btn-remove-file')) return;
            this.fileInput.click();
        };
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev =>
            this.dropZone.addEventListener(ev, (e) => { e.preventDefault(); e.stopPropagation(); }));

        this.dropZone.ondrop = (e) => {
            Array.from(e.dataTransfer.files).forEach(f => this.queue.push(f));
            this.sync();
        };

        this.fileInput.onchange = () => {
            Array.from(this.fileInput.files).forEach(f => this.queue.push(f));
            this.sync();
        };
    }

    sync() {
        const dt = new DataTransfer();
        this.queue.forEach(f => dt.items.add(f));
        this.fileInput.files = dt.files;
        this.render();
    }

    render() {
        this.display.innerHTML = this.queue.map((f, i) => `
            <div class="file-item-row">
                <span>${f.name}</span>
                <button type="button" class="btn-remove-file" onclick="window.uploader.remove(${i})">×</button>
            </div>`).join('');
    }

    remove(idx) {
        this.queue.splice(idx, 1);
        this.sync();
    }
}