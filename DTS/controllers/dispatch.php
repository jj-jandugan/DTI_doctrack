<?php
// controllers/dispatch.php
session_start();
require_once '../classes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'RO') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dispatch_document') {
    $doc_id = $_POST['document_id'];
    try {
        $pdo->beginTransaction();

        $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE category = 'CLOSED' LIMIT 1");
        $closed_status_id = $stmtStatus->fetchColumn();

        if (empty($closed_status_id))
            throw new Exception("Required 'CLOSED' status missing.");

        $stmtUpdateDoc = $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdateDoc->execute([$closed_status_id, $doc_id]);

        $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('DISPATCHED', 'Released to external destination.', NOW(), ?, ?)");
        $stmtLog->execute([$user_id, $doc_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Document officially marked as dispatched and closed.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}