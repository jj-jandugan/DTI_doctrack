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
        // --- BULLETPROOF FOLDER FIX ---
        // Guarantees the uploads folder exists before DocumentManager tries to save to it
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // 1. Instantiate the DocumentManager
        $docManager = new DocumentManager($pdo);

        // 2. Safely grab the uploaded files
        $files = isset($_FILES['document_files']) ? $_FILES['document_files'] : ['name' => []];

        // 3. Explicitly map the form data to ensure arrays (like checkboxes) and external addresses are never dropped!
        $data = [
            'classification' => !empty($_POST['classification']) ? $_POST['classification'] : ($_POST['hidden_classification'] ?? ''),
            'document_type'  => $_POST['document_type'] ?? '',
            'due_date'       => $_POST['due_date'] ?? null,
            'subject'        => trim($_POST['subject'] ?? ''),
            'particulars'    => trim($_POST['particulars'] ?? ''),
            'signatory'      => $_POST['signatory'] ?? '',
            'route_type'     => $_POST['route_type'] ?? '',
            'route_division' => $_POST['route_division'] ?? '',
            'route_users'    => isset($_POST['route_users']) ? $_POST['route_users'] : [],
            'route_group'    => $_POST['route_group'] ?? '',

            // THESE ARE NOW ARRAYS [] TO HANDLE MULTIPLE ROWS
            'dti_branch'     => $_POST['dti_branch'] ?? [],
            'dti_contact'    => $_POST['dti_contact'] ?? [],
            'dti_notes'      => $_POST['dti_notes'] ?? [],

            'ext_office'     => $_POST['ext_office'] ?? [],
            'out_ext_name'   => $_POST['out_ext_name'] ?? [],
            'out_notes'      => $_POST['out_notes'] ?? []
        ];
        
        // 4. Pass the strictly formatted $data array to the DocumentManager
        $dts_no = $docManager->createDocument($user_id, $data, $files);

        // 5. Set success message
        $_SESSION['success_msg'] = "Document " . htmlspecialchars($dts_no) . " created and routed successfully!";

    } catch (Exception $e) {
        // If anything fails, catch it safely here
        $_SESSION['error_msg'] = "Error creating document: " . $e->getMessage();
    }

    // Redirect back to the page the user came from
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;

} else {
    // If someone tries to access this file directly without posting a form
    header("Location: ../login.php");
    exit;
}
?>