<?php
// controllers/Desk.php
session_start();
require_once '../classes/database.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle Accept & Close Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_document') {
    $doc_id = $_POST['document_id'];

    try {
        $pdo->beginTransaction();

        $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE category = 'CLOSED' LIMIT 1");
        $closed_status_id = $stmtStatus->fetchColumn();

        if (!$closed_status_id) {
            throw new Exception("No 'CLOSED' status category found in configurations. Please ask Admin to create one.");
        }

        // 1. Mark as received by the specific user
        $stmtUpdateRec = $pdo->prepare("UPDATE records_documentrecipient SET has_received = 1, received_at = NOW() WHERE document_id = ? AND recipient_user_id = ?");
        $stmtUpdateRec->execute([$doc_id, $user_id]);

        // 2. Mark the actual document as closed
        $stmtUpdateDoc = $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdateDoc->execute([$closed_status_id, $doc_id]);

        // 3. Log the history
        $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('RECEIVED & CLOSED', 'Document was accepted and marked as closed by the assigned division user.', NOW(), ?, ?)");
        $stmtLog->execute([$user_id, $doc_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Document successfully accepted and closed! It has been moved to your History.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error processing document: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
} else {
    header("Location: ../login.php");
    exit;
}