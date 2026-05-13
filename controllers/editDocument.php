<?php
// controllers/editDocument.php
session_start();
require_once '../classes/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $doc_id = $_POST['doc_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if (!$doc_id) {
        header("Location: ../templates/division/divOutgoing.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // --- ACTION: UPDATE DOCUMENT ---
        if ($_POST['action'] === 'update_document') {
            // Updated query excludes classification_id to keep it locked
            $stmt = $pdo->prepare("UPDATE records_document SET
                document_type_id = ?,
                due_date = ?,
                subject = ?,
                particulars = ?,
                signatory_id = ?,
                sender = ?,
                route_type = ?,
                updated_at = NOW()
                WHERE id = ? AND creator_id = ?");

            $stmt->execute([
                $_POST['document_type'],
                !empty($_POST['due_date']) ? $_POST['due_date'] : null,
                $_POST['subject'],
                $_POST['particulars'],
                !empty($_POST['signatory']) ? $_POST['signatory'] : null,
                $_POST['sender_notes'],
                $_POST['route_type'],
                $doc_id,
                $user_id
            ]);

            // Sync Recipients
            $pdo->prepare("DELETE FROM records_documentrecipient WHERE document_id = ?")->execute([$doc_id]);
            $recipients = [];
            if ($_POST['route_type'] === 'division' && !empty($_POST['route_users'])) {
                $recipients = $_POST['route_users'];
            } elseif ($_POST['route_type'] === 'group' && !empty($_POST['route_group'])) {
                $stmtGrp = $pdo->prepare("SELECT user_id FROM records_distributiongroup_members WHERE group_id = ?");
                $stmtGrp->execute([$_POST['route_group']]);
                $recipients = $stmtGrp->fetchAll(PDO::FETCH_COLUMN);
            }

            if (!empty($recipients)) {
                $stmtRec = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id) VALUES (?, ?)");
                foreach ($recipients as $rid) {
                    $stmtRec->execute([$doc_id, $rid]);
                }
            }

            // Handle File Deletions
            if (!empty($_POST['remove_attachments'])) {
                foreach ($_POST['remove_attachments'] as $att_id) {
                    $stmtAtt = $pdo->prepare("SELECT file_path FROM records_documentattachment WHERE id = ?");
                    $stmtAtt->execute([$att_id]);
                    $path = $stmtAtt->fetchColumn();
                    if ($path && file_exists('../' . $path)) unlink('../' . $path);
                    $pdo->prepare("DELETE FROM records_documentattachment WHERE id = ?")->execute([$att_id]);
                }
            }

            // Handle New File Uploads
            if (!empty($_FILES['document_files']['name'][0])) {
                $upload_dir = '../uploads/';
                foreach ($_FILES['document_files']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['document_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $fname = time() . '_' . $_FILES['document_files']['name'][$key];
                        if (move_uploaded_file($tmp_name, $upload_dir . $fname)) {
                            $pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())")
                                ->execute([$doc_id, 'uploads/' . $fname]);
                        }
                    }
                }
            }

            $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id)
                           VALUES ('EDITED', 'Document updated by Division User.', NOW(), ?, ?)")
                ->execute([$user_id, $doc_id]);

            $_SESSION['success_msg'] = "Changes saved successfully.";

        // --- ACTION: CANCEL DOCUMENT (Move to History) ---
        } elseif ($_POST['action'] === 'cancel_document') {

            // 1. STRICTLY look for the 'CANCELLED' category
            $stmtStatus = $pdo->prepare("SELECT id FROM records_status WHERE category = 'CANCELLED' LIMIT 1");
            $stmtStatus->execute();
            $status_id = $stmtStatus->fetchColumn();

            // 2. If it is missing, auto-create it securely under the CANCELLED category
            if (!$status_id) {
                $pdo->prepare("INSERT INTO records_status (name, category) VALUES ('Cancelled', 'CANCELLED')")->execute();
                $status_id = $pdo->lastInsertId();
            }

            // 3. Update document status
            $stmtCancel = $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ? AND creator_id = ?");
            $stmtCancel->execute([$status_id, $doc_id, $user_id]);

            // 4. Log action as CANCELLED in history
            $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id)
                           VALUES ('CANCELLED', 'Document entry cancelled and moved to history.', NOW(), ?, ?)")
                ->execute([$user_id, $doc_id]);

            $_SESSION['success_msg'] = "Document successfully cancelled and archived.";
        }

        $pdo->commit();
        header("Location: ../templates/division/divOutgoing.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error processing request: " . $e->getMessage();
        header("Location: ../templates/division/divEditDocu.php?id=" . $doc_id);
        exit;
    }
} else {
    header("Location: ../templates/division/divOutgoing.php");
    exit;
}
?>