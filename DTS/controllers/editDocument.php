<?php
// controllers/editDocument.php
session_start();
require_once '../classes/database.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$doc_id = $_GET['id'] ?? $_POST['document_id'] ?? null;

if (!$doc_id) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();

        // ACTION 1: UPDATE CORE DETAILS
        if ($_POST['action'] === 'update_document') {
            $classification_id = !empty($_POST['classification']) ? $_POST['classification'] : null;
            $document_type_id = !empty($_POST['document_type']) ? $_POST['document_type'] : null;
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $subject = trim($_POST['subject']);

            $stmt = $pdo->prepare("UPDATE records_document SET classification_id = ?, document_type_id = ?, due_date = ?, subject = ?, updated_at = NOW() WHERE id = ? AND creator_id = ?");
            $stmt->execute([$classification_id, $document_type_id, $due_date, $subject, $doc_id, $user_id]);

            $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('UPDATED', 'Core document details were updated.', NOW(), ?, ?)");
            $stmtLog->execute([$user_id, $doc_id]);

            $_SESSION['success_msg'] = "Document details updated successfully!";
        }

        // ACTION 2: RE-ROUTE TO NEW DESTINATION
        if ($_POST['action'] === 'route_document') {
            $route_type = $_POST['route_type'] ?? '';

            // Clear old recipients
            $pdo->prepare("DELETE FROM records_documentrecipient WHERE document_id = ?")->execute([$doc_id]);

            if ($route_type === 'division') {
                $recipient_id = $_POST['route_user'] ?? null;
                if ($recipient_id) {
                    $stmtIns = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
                    $stmtIns->execute([$doc_id, $recipient_id]);
                }
            } elseif ($route_type === 'group' && !empty($_POST['route_group'])) {
                $stmtGrpUsers = $pdo->prepare("SELECT user_id FROM records_distributiongroup_members WHERE group_id = ?");
                $stmtGrpUsers->execute([$_POST['route_group']]);
                $grpUsers = $stmtGrpUsers->fetchAll(PDO::FETCH_COLUMN);

                $stmtIns = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
                foreach ($grpUsers as $uid) {
                    $stmtIns->execute([$doc_id, $uid]);
                }
            }

            $stmtUpdateRoute = $pdo->prepare("UPDATE records_document SET route_type = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpdateRoute->execute([$route_type, $doc_id]);

            $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('ROUTED', 'Document was routed to a new destination.', NOW(), ?, ?)");
            $stmtLog->execute([$user_id, $doc_id]);

            $_SESSION['success_msg'] = "Document routed successfully!";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error updating document: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
} else {
    header("Location: ../login.php");
    exit;
}