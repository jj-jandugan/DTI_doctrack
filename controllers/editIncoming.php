<?php
// controllers/editIncoming.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();

        // --- ACTION: UPDATE DOCUMENT ---
        if ($_POST['action'] === 'update_document') {

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

            // FIND OLD STATUS (To check if it was rejected)
            $stmtOldStatus = $pdo->prepare("SELECT s.category FROM records_document d JOIN records_status s ON d.status_id = s.id WHERE d.id = ?");
            $stmtOldStatus->execute([$doc_id]);
            $old_status = strtoupper(trim($stmtOldStatus->fetchColumn()));

            // Update Document (Force back to FOR-APPROVAL)
            $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE category = 'FOR-APPROVAL' LIMIT 1");
            $status_id = $stmtStatus->fetchColumn();

            $stmtUpdate = $pdo->prepare("
                UPDATE records_document
                SET subject = ?, particulars = ?, due_date = ?, updated_at = NOW(),
                    document_type_id = ?, signatory_id = ?, origin_id = ?,
                    sender = ?, address_id = ?, route_type = ?, status_id = ?
                WHERE id = ? AND creator_id = ?
            ");
            $stmtUpdate->execute([$subject, $particulars, $due_date, $document_type_id, $signatory_id, $origin_id, trim($_POST['sender_name']), $address_id, $route_type, $status_id, $doc_id, $user_id]);

            // Re-assign Internal Recipients
            $pdo->prepare("DELETE FROM records_documentrecipient WHERE document_id = ?")->execute([$doc_id]);
            if ($route_type === 'division' && !empty($_POST['route_users'])) {
                $stmtIns = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id) VALUES (?, ?)");
                foreach ($_POST['route_users'] as $uid) { $stmtIns->execute([$doc_id, $uid]); }
            }

            // Re-assign External / Multi-Recipients (Safety feature for UI consistency)
            $pdo->prepare("DELETE FROM records_externalrecipient WHERE document_id = ?")->execute([$doc_id]);
            if ($route_type === 'within_dti' && isset($_POST['dti_branch'])) {
                $stmtExt = $pdo->prepare("INSERT INTO records_externalrecipient (document_id, type, dtibranch_id, contact_person, notes) VALUES (?, 'within_dti', ?, ?, ?)");
                for ($i = 0; $i < count($_POST['dti_branch']); $i++) {
                    if (!empty($_POST['dti_branch'][$i])) {
                        $stmtExt->execute([$doc_id, $_POST['dti_branch'][$i], $_POST['dti_contact'][$i] ?? null, $_POST['dti_notes'][$i] ?? null]);
                    }
                }
            } elseif ($route_type === 'outside_dti' && isset($_POST['ext_office'])) {
                $stmtExt = $pdo->prepare("INSERT INTO records_externalrecipient (document_id, type, ext_office, contact_person, notes) VALUES (?, 'outside_dti', ?, ?, ?)");
                for ($i = 0; $i < count($_POST['ext_office']); $i++) {
                    if (!empty($_POST['ext_office'][$i])) {
                        $stmtExt->execute([$doc_id, $_POST['ext_office'][$i], $_POST['out_ext_name'][$i] ?? null, $_POST['out_notes'][$i] ?? null]);
                    }
                }
            }

            // Handle File Removals
            if (!empty($_POST['remove_attachments'])) {
                foreach ($_POST['remove_attachments'] as $att_id) {
                    $stmtFile = $pdo->prepare("SELECT file_path FROM records_documentattachment WHERE id = ?");
                    $stmtFile->execute([$att_id]);
                    $path = $stmtFile->fetchColumn();
                    if ($path && file_exists('../' . $path)) unlink('../' . $path);
                    $pdo->prepare("DELETE FROM records_documentattachment WHERE id = ?")->execute([$att_id]);
                }
            }

            // Handle New Uploads
            if (!empty($_FILES['document_files']['name'][0])) {
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                foreach ($_FILES['document_files']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['document_files']['error'][$k] === UPLOAD_ERR_OK) {
                        $fname = time() . '_' . $k . '_' . $_FILES['document_files']['name'][$k];
                        if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                            $pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())")
                                ->execute([$doc_id, 'uploads/' . $fname]);
                        }
                    }
                }
            }

            // SMART LOGGING: Differentiate Edit vs Resubmit
            $action_log = ($old_status === 'REJECTED') ? 'RESUBMITTED' : 'EDITED';
            $remarks_log = ($old_status === 'REJECTED') ? 'Document edited and resubmitted to Signatory by RO.' : 'Document updated by RO.';

            $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES (?, ?, NOW(), ?, ?)")
                ->execute([$action_log, $remarks_log, $user_id, $doc_id]);

            $_SESSION['success_msg'] = ($old_status === 'REJECTED') ? "Document successfully resubmitted!" : "Document updated successfully!";

        // --- ACTION: CANCEL DOCUMENT (Move to History) ---
        } elseif ($_POST['action'] === 'cancel_document') {

            $stmtStatus = $pdo->prepare("SELECT id FROM records_status WHERE category = 'CANCELLED' LIMIT 1");
            $stmtStatus->execute();
            $status_id = $stmtStatus->fetchColumn();

            if (!$status_id) {
                $pdo->prepare("INSERT INTO records_status (name, category) VALUES ('Cancelled', 'CANCELLED')")->execute();
                $status_id = $pdo->lastInsertId();
            }

            $stmtCancel = $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ? AND creator_id = ?");
            $stmtCancel->execute([$status_id, $doc_id, $user_id]);

            $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id)
                           VALUES ('CANCELLED', 'Incoming document entry cancelled by Records Officer.', NOW(), ?, ?)")
                ->execute([$user_id, $doc_id]);

            $_SESSION['success_msg'] = "Incoming Document successfully cancelled and archived.";
        }

        $pdo->commit();
        header("Location: ../templates/ro/roIncoming.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
        header("Location: ../templates/ro/roEditDocu.php?id=" . $doc_id);
        exit;
    }
} else {
    header("Location: ../templates/ro/roIncoming.php");
    exit;
}
?>