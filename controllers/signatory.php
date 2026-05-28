<?php
// controllers/signatory.php
session_start();
require_once '../classes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Signatory') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $doc_id = $_POST['document_id'];

    try {
        $pdo->beginTransaction();

        // Check route type to determine where it goes
        $stmtRoute = $pdo->prepare("SELECT route_type, dts_no FROM records_document WHERE id = ?");
        $stmtRoute->execute([$doc_id]);
        $doc_info = $stmtRoute->fetch();

        if ($_POST['action'] === 'approve_document') {

            // NEW LOGIC: Fetch status 'Approved' with category 'ONGOING'
            // This ensures it returns to the RO and is NOT visible to the Division yet.
            $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE name = 'Approved' AND category = 'ONGOING' LIMIT 1");
            $new_status_id = $stmtStatus->fetchColumn();

            // Safety check: if the specific pair isn't found, fallback to name-based search
            if (!$new_status_id) {
                $new_status_id = $pdo->query("SELECT id FROM records_status WHERE name = 'APPROVED' LIMIT 1")->fetchColumn();
            }

            if (!$new_status_id) {
                throw new Exception("Database Error: Status 'APPROVED' does not exist in the records_status table.");
            }

            $log_action = "APPROVED";

            // Revised Remarks: Document is returned to RO for signed upload
            $log_remarks = "Approved by Signatory. Returned to Records Officer for signed document upload.";

            $_SESSION['success_msg'] = "Document " . $doc_info['dts_no'] . " approved and returned to RO for final scan.";

        } elseif ($_POST['action'] === 'reject_document') {

            // Fix: Changed 'category' to 'name' to match the new 6-status system
            $new_status_id = $pdo->query("SELECT id FROM records_status WHERE name = 'REJECTED' LIMIT 1")->fetchColumn();

            // Safety check to prevent foreign key errors
            if (!$new_status_id) {
                throw new Exception("Database Error: Status 'REJECTED' does not exist in the records_status table.");
            }

            $log_action = "REJECTED";

            $reason = trim($_POST['reject_reason'] ?? '');
            if (!empty($reason)) {
                $log_remarks = "Document rejected by Signatory. Reason: " . $reason;
            } else {
                $log_remarks = "Document rejected by Signatory and returned to creator.";
            }
            $_SESSION['success_msg'] = "Document " . $doc_info['dts_no'] . " has been rejected.";
        }

        // Update Document Status
        $stmtUpdate = $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$new_status_id, $doc_id]);

        // Insert Tracking History
        $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES (?, ?, NOW(), ?, ?)");
        $stmtLog->execute([$log_action, $log_remarks, $user_id, $doc_id]);

        $pdo->commit();

        // Redirect safely back to where they came from
        $redirect = isset($_POST['from_page']) ? $_POST['from_page'] : 'signDashboard.php';
        header("Location: ../templates/signatory/" . $redirect);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}