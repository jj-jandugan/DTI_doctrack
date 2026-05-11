<?php
session_start();
require_once '../classes/database.php';
require_once '../classes/documentManager.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'RO') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$doc_id = $_GET['id'] ?? $_POST['document_id'] ?? null;
$docManager = new DocumentManager($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_document') {
    try {
        $pdo->beginTransaction();

        $document_type_id = !empty($_POST['document_type']) ? $_POST['document_type'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $subject = trim($_POST['subject']);
        $particulars = trim($_POST['particulars'] ?? '');
        $signatory_id = !empty($_POST['signatory']) ? $_POST['signatory'] : null;
        $route_type = $_POST['route_type'] ?? '';

        // Handle Origin
        $origin_id = $docManager->findOrCreateOrigin(trim($_POST['origin_office']));

        // Handle Destination Address
        $destination_name = 'Unassigned Internal Routing';
        if ($route_type === 'division' && !empty($_POST['route_division'])) {
            $stmtDiv = $pdo->prepare("SELECT name FROM records_division WHERE id = ?");
            $stmtDiv->execute([$_POST['route_division']]);
            $destination_name = $stmtDiv->fetchColumn();
        } elseif ($route_type === 'group' && !empty($_POST['route_group'])) {
            $stmtGrp = $pdo->prepare("SELECT group_name FROM records_distributiongroup WHERE id = ?");
            $stmtGrp->execute([$_POST['route_group']]);
            $destination_name = $stmtGrp->fetchColumn();
        }

        $stmtFindAddr = $pdo->prepare("SELECT id FROM records_address WHERE name = ? LIMIT 1");
        $stmtFindAddr->execute([$destination_name]);
        $address_id = $stmtFindAddr->fetchColumn() ?: null;

        if (!$address_id && $destination_name) {
            $pdo->prepare("INSERT INTO records_address (name) VALUES (?)")->execute([$destination_name]);
            $address_id = $pdo->lastInsertId();
        }

        // Update Document
        $stmtUpdate = $pdo->prepare("
            UPDATE records_document
            SET subject = ?, particulars = ?, due_date = ?, updated_at = NOW(),
                document_type_id = ?, signatory_id = ?, origin_id = ?,
                sender = ?, address_id = ?, route_type = ?
            WHERE id = ? AND creator_id = ?
        ");
        $stmtUpdate->execute([$subject, $particulars, $due_date, $document_type_id, $signatory_id, $origin_id, trim($_POST['sender_name']), $address_id, $route_type, $doc_id, $user_id]);

        // Re-assign Recipients
        $pdo->prepare("DELETE FROM records_documentrecipient WHERE document_id = ?")->execute([$doc_id]);
        if ($route_type === 'division' && !empty($_POST['route_users'])) {
            $stmtIns = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id) VALUES (?, ?)");
            foreach ($_POST['route_users'] as $uid) {
                $stmtIns->execute([$doc_id, $uid]);
            }
        }

        // Handle File Removals
        if (!empty($_POST['remove_attachments'])) {
            foreach ($_POST['remove_attachments'] as $att_id) {
                $stmtFile = $pdo->prepare("SELECT file_path FROM records_documentattachment WHERE id = ?");
                $stmtFile->execute([$att_id]);
                $path = $stmtFile->fetchColumn();
                if ($path && file_exists('../' . $path))
                    unlink('../' . $path);
                $pdo->prepare("DELETE FROM records_documentattachment WHERE id = ?")->execute([$att_id]);
            }
        }

        // Handle New Uploads
        if (!empty($_FILES['document_files']['name'][0])) {
            $upload_dir = '../uploads/';
            foreach ($_FILES['document_files']['tmp_name'] as $k => $tmp) {
                $fname = time() . '_' . $k . '_' . $_FILES['document_files']['name'][$k];
                if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                    $pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())")
                        ->execute([$doc_id, 'uploads/' . $fname]);
                }
            }
        }

        $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('EDITED', 'Document updated by RO.', NOW(), ?, ?)")
            ->execute([$user_id, $doc_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Document updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: ../templates/ro/roIncoming.php");
    exit;
}