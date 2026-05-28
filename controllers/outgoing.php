<?php
// controllers/outgoing.php
session_start();
require_once '../classes/database.php';
require_once '../classes/documentManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_document') {
    try {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $docManager = new DocumentManager($pdo);
        $files = isset($_FILES['document_files']) ? $_FILES['document_files'] : ['name' => []];

        $data = [
            'classification' => !empty($_POST['classification']) ? $_POST['classification'] : ($_POST['hidden_classification'] ?? ''),
            'document_type' => $_POST['document_type'] ?? '',
            'due_date' => $_POST['due_date'] ?? null,
            'subject' => trim($_POST['subject'] ?? ''),
            'particulars' => trim($_POST['particulars'] ?? ''),
            'signatory' => $_POST['signatory'] ?? '',
            'route_type' => $_POST['route_type'] ?? '',
            'route_division' => $_POST['route_division'] ?? '',
            'route_users' => isset($_POST['route_users']) ? $_POST['route_users'] : [],
            'route_group' => $_POST['route_group'] ?? '',

            // ADDED: Map the receiving officer from POST
            'receiving_officer' => $_POST['receiving_officer'] ?? null,

            'dti_branch' => $_POST['dti_branch'] ?? [],
            'dti_contact' => $_POST['dti_contact'] ?? [],
            'dti_notes' => $_POST['dti_notes'] ?? [],

            'ext_office' => $_POST['ext_office'] ?? [],
            'out_ext_name' => $_POST['out_ext_name'] ?? [],
            'out_notes' => $_POST['out_notes'] ?? []
        ];

        $dts_no = $docManager->createDocument($user_id, $data, $files);

        $_SESSION['success_msg'] = "Document " . htmlspecialchars($dts_no) . " created and routed successfully!";

    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Error creating document: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;

} else {
    header("Location: ../login.php");
    exit;
}
?>