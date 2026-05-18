<?php
// controllers/incoming.php
session_start();
require_once '../classes/database.php';
require_once '../classes/documentManager.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'RO') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_document') {
    try {
        // Build the data array exactly like we did in outgoing.php
        $data = [
            'classification' => !empty($_POST['classification']) ? $_POST['classification'] : ($_POST['hidden_classification'] ?? ''),
            'document_type'  => $_POST['document_type'] ?? '',
            'due_date'       => $_POST['due_date'] ?? null,
            'subject'        => trim($_POST['subject'] ?? ''),
            'particulars'    => trim($_POST['particulars'] ?? ''),
            'signatory'      => $_POST['signatory'] ?? '',

            // Incoming specific fields
            'origin_office'  => trim($_POST['origin_office'] ?? ''),
            'sender_name'    => trim($_POST['sender_name'] ?? ''),

            // Routing
            'route_type'     => $_POST['route_type'] ?? '',
            'route_division' => $_POST['route_division'] ?? '',
            'route_users'    => isset($_POST['route_users']) ? $_POST['route_users'] : [],
            'route_group'    => $_POST['route_group'] ?? '',

            // Multi-Recipient Arrays
            'dti_branch'     => $_POST['dti_branch'] ?? [],
            'dti_contact'    => $_POST['dti_contact'] ?? [],
            'dti_notes'      => $_POST['dti_notes'] ?? [],

            'ext_office'     => $_POST['ext_office'] ?? [],
            'out_ext_name'   => $_POST['out_ext_name'] ?? [],
            'out_notes'      => $_POST['out_notes'] ?? []
        ];

        // Let DocumentManager do the heavy lifting
        $dts_no = $docManager->createIncomingDocument($user_id, $data, $_FILES['document_files'] ?? []);

        $_SESSION['success_msg'] = "Incoming document $dts_no created successfully!";
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Error creating document: " . $e->getMessage();
    }

    header("Location: ../templates/ro/roIncoming.php");
    exit;
}
?>