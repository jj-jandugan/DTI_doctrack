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

        // 1. Bulletproof Fetch for 'CLOSED' status
        $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE name = 'CLOSED' OR category = 'CLOSED' LIMIT 1");
        $closed_status_id = $stmtStatus->fetchColumn();

        if (!$closed_status_id) {
            $stmtInsert = $pdo->prepare("INSERT INTO records_status (name, category) VALUES ('CLOSED', 'CLOSED')");
            $stmtInsert->execute();
            $closed_status_id = $pdo->lastInsertId();
        }

        // 2. Update Document Status AND the updated_at timestamp
        // This ensures the "Last Update" column in your History tables shows the Dispatch time.
        $stmtUpdateDoc = $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdateDoc->execute([$closed_status_id, $doc_id]);

        // 3. Log the action as 'CLOSED' for the tracking timeline
        $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id)
                                  VALUES ('CLOSED', 'Released to external destination and officially dispatched.', NOW(), ?, ?)");
        $stmtLog->execute([$user_id, $doc_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Document officially marked as dispatched and closed.";

        // 4. IMPROVED REDIRECT: Since it is now closed, take them straight to History
        // so they can see the document they just finished processing.
        header("Location: ../templates/ro/roHistory.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "System Error: " . $e->getMessage();

        // If error, go back to the previous page
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../templates/ro/roOutgoing.php'));
        exit;
    }
}