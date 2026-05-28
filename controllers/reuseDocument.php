<?php
// controllers/reuseDocument.php
require_once '../classes/database.php';
require_once '../classes/DocumentManager.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'])) {
    $docManager = new DocumentManager($pdo);
    try {
        $new_id = $docManager->reuseDocument($_POST['doc_id'], $_SESSION['user_id']);
        $_SESSION['success_msg'] = "Record reused successfully. You are now editing the copy.";
        // REDIRECT TO EDIT PAGE
        header("Location: ../templates/division/divEditDocu.php?id=" . $new_id);
        exit;
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Reuse failed: " . $e->getMessage();
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}