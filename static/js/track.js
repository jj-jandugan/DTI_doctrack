// Initialization for Uploader inside Edit Document
document.addEventListener("DOMContentLoaded", () => {
    if (typeof UploadQueue !== "undefined") {
        window.uploader = new UploadQueue("dropZone", "fileInput", "fileQueueDisplay", "dropZoneText");
    }
});

// File Removal Logic
function queueFileRemoval(event, attachmentId) {
    event.preventDefault();

    // Hide the file row visually
    const row = document.getElementById("att-row-" + attachmentId);
    if (row) row.style.display = "none";

    // Add a hidden input to the form so the backend knows to delete it
    const container = document.getElementById("removedAttachmentsContainer");
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "remove_attachments[]";
    input.value = attachmentId;
    container.appendChild(input);
}

// See More / See Less Toggle for Activity Logs
function toggleActivityLogs() {
    const extraLogs = document.querySelectorAll('.extra-log');
    const btn = document.getElementById('toggleLogsBtn');
    let isExpanded = false;

    extraLogs.forEach(log => {
        if (log.classList.contains('d-none')) {
            log.classList.remove('d-none');
            isExpanded = true;
        } else {
            log.classList.add('d-none');
        }
    });

    if (isExpanded) {
        btn.innerHTML = 'See Less <i class="fa-solid fa-chevron-up ms-1"></i>';
    } else {
        btn.innerHTML = 'See More <i class="fa-solid fa-chevron-down ms-1"></i>';
    }
}