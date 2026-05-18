<?php
// controllers/reuseDocument.php
session_start();
require_once '../classes/database.php';
require_once '../classes/documentManager.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'])) {
    $docManager = new DocumentManager($pdo);
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    try {
        // Clone the document and its physical files using the Manager
        $new_doc_id = $docManager->reuseDocument($_POST['document_id'], $user_id);

        $_SESSION['success_msg'] = "Document successfully cloned! You can now edit and submit it.";

        // Smart Redirect based on Role
        if ($role === 'RO') {
            header("Location: ../templates/ro/roEditDocu.php?id=" . $new_doc_id);
        } else {
            header("Location: ../templates/division/divEditDocu.php?id=" . $new_doc_id);
        }
        exit;

    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Error reusing document: " . $e->getMessage();
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
} else {
    // If accessed directly without a form submission
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>