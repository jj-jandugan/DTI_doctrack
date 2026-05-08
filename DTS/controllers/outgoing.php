<?php
// controllers/outgoing.php
session_start();
require_once '../classes/database.php';
require_once '../classes/DocumentManager.php'; // Required to use the secure logic

// Security Check: Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_document') {
    try {
        // 1. Instantiate the DocumentManager
        $docManager = new DocumentManager($pdo);

        // 2. Safely grab the uploaded files (matching the name="document_files[]" from your HTML form)
        $files = isset($_FILES['document_files']) ? $_FILES['document_files'] : ['name' => []];

        // 3. Let the DocumentManager handle all the complex routing, ID creation, and file uploads.
        // It will return the generated DTS Number upon success.
        $dts_no = $docManager->createDocument($user_id, $_POST, $files);

        // 4. Set success message
        $_SESSION['success_msg'] = "Document " . htmlspecialchars($dts_no) . " created and routed successfully!";

    } catch (Exception $e) {
        // If anything fails (like a missing file or a database constraint), catch it safely here
        $_SESSION['error_msg'] = "Error creating document: " . $e->getMessage();
    }

    // Redirect back to the page the user came from (e.g., divOutgoing.php)
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;

} else {
    // If someone tries to access this file directly without posting a form
    header("Location: ../login.php");
    exit;
}