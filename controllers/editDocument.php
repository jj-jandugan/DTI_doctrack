<?php
// controllers/editDocument.php
session_start();
require_once '../classes/database.php';
require_once '../classes/documentManager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // THE FIX: Accept both naming conventions to prevent silent crashes
    $doc_id = $_POST['document_id'] ?? $_POST['doc_id'] ?? null;

    $user_id = $_SESSION['user_id'];
    $docManager = new DocumentManager($pdo);

    if (!$doc_id) {
        header("Location: ../templates/division/divOutgoing.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($_POST['action'] === 'update_document') {

            $document_type_id = !empty($_POST['document_type']) ? $_POST['document_type'] : null;
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $subject = trim($_POST['subject']);
            $particulars = trim($_POST['particulars'] ?? '');
            $signatory_id = !empty($_POST['signatory']) ? $_POST['signatory'] : null;
            $route_type = $_POST['route_type'] ?? '';

            // Handle Origin (if present in form)
            $origin_id = null;
            if (!empty($_POST['origin_office'])) {
                $origin_id = $docManager->findOrCreateOrigin(trim($_POST['origin_office']));
            }

            // Handle Destination Address (Extracts cleanly for the Address Table)
            $destination_name = 'Unassigned Internal Routing';
            if ($route_type === 'division' && !empty($_POST['route_division'])) {
                $stmtDiv = $pdo->prepare("SELECT name FROM records_division WHERE id = ?");
                $stmtDiv->execute([$_POST['route_division']]);
                $destination_name = $stmtDiv->fetchColumn();
            } elseif ($route_type === 'group' && !empty($_POST['route_group'])) {
                $stmtGrp = $pdo->prepare("SELECT group_name FROM records_distributiongroup WHERE id = ?");
                $stmtGrp->execute([$_POST['route_group']]);
                $destination_name = $stmtGrp->fetchColumn();
            } elseif ($route_type === 'within_dti' && !empty($_POST['dti_branch'])) {
                $stmt = $pdo->prepare("SELECT name FROM records_dtibranch WHERE id = ?");
                $stmt->execute([$_POST['dti_branch'][0]]);
                $first_name = $stmt->fetchColumn();
                $count = count($_POST['dti_branch']);
                $destination_name = ($count > 1) ? $first_name . ' and ' . ($count - 1) . ' more' : $first_name;
            } elseif ($route_type === 'outside_dti' && !empty($_POST['ext_office'])) {
                $first_name = trim($_POST['ext_office'][0]);
                $count = count($_POST['ext_office']);
                $destination_name = ($count > 1) ? $first_name . ' and ' . ($count - 1) . ' more' : $first_name;
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

            // REACTIVATE: Force status back to FOR-APPROVAL when saved
            $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE category = 'FOR-APPROVAL' LIMIT 1");
            $status_id = $stmtStatus->fetchColumn();

            // Execute the Main Update
            if ($origin_id) {
                $stmtUpdate = $pdo->prepare("
                    UPDATE records_document SET
                    document_type_id = ?, due_date = ?, subject = ?, particulars = ?, signatory_id = ?, sender = ?, origin_id = ?, address_id = ?, route_type = ?,
                    status_id = ?, updated_at = NOW()
                    WHERE id = ? AND creator_id = ?");
                $stmtUpdate->execute([
                    $document_type_id, $due_date, $subject, $particulars, $signatory_id, trim($_POST['sender_name'] ?? ''), $origin_id, $address_id, $route_type,
                    $status_id, $doc_id, $user_id
                ]);
            } else {
                $stmtUpdate = $pdo->prepare("
                    UPDATE records_document SET
                    document_type_id = ?, due_date = ?, subject = ?, particulars = ?, signatory_id = ?, sender = ?, address_id = ?, route_type = ?,
                    status_id = ?, updated_at = NOW()
                    WHERE id = ? AND creator_id = ?");
                $stmtUpdate->execute([
                    $document_type_id, $due_date, $subject, $particulars, $signatory_id, trim($_POST['sender_name'] ?? ''), $address_id, $route_type,
                    $status_id, $doc_id, $user_id
                ]);
            }

            // Re-assign Internal Recipients
            $pdo->prepare("DELETE FROM records_documentrecipient WHERE document_id = ?")->execute([$doc_id]);
            $recipients = [];
            if ($route_type === 'division' && !empty($_POST['route_users'])) {
                $recipients = $_POST['route_users'];
            } elseif ($route_type === 'group' && !empty($_POST['route_group'])) {
                $stmtGrpM = $pdo->prepare("SELECT user_id FROM records_distributiongroup_members WHERE group_id = ?");
                $stmtGrpM->execute([$_POST['route_group']]);
                $recipients = $stmtGrpM->fetchAll(PDO::FETCH_COLUMN);
            }
            if (!empty($recipients)) {
                $stmtIns = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id) VALUES (?, ?)");
                foreach ($recipients as $uid) { $stmtIns->execute([$doc_id, $uid]); }
            }

            // Re-assign External / Multi-Recipients (Supports the dynamic clone feature)
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

            // Handle File Removals safely
            if (!empty($_POST['remove_attachments'])) {
                foreach ($_POST['remove_attachments'] as $att_id) {
                    $stmtAtt = $pdo->prepare("SELECT file_path FROM records_documentattachment WHERE id = ?");
                    $stmtAtt->execute([$att_id]);
                    $path = $stmtAtt->fetchColumn();
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
                        $fname = time() . '_' . $k . '_' . basename($_FILES['document_files']['name'][$k]);
                        if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                            $pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())")
                                ->execute([$doc_id, 'uploads/' . $fname]);
                        }
                    }
                }
            }

            // SMART LOGGING: Differentiate Edit vs Resubmit automatically
            $action_log = ($old_status === 'REJECTED') ? 'RESUBMITTED' : 'EDITED';
            $remarks_log = ($old_status === 'REJECTED') ? 'Document edited and resubmitted to Signatory.' : 'Document updated successfully.';

            $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES (?, ?, NOW(), ?, ?)")
                ->execute([$action_log, $remarks_log, $user_id, $doc_id]);

            $_SESSION['success_msg'] = ($old_status === 'REJECTED') ? "Document successfully resubmitted!" : "Document updated successfully!";

        } elseif ($_POST['action'] === 'cancel_document') {
            $stmtStatus = $pdo->prepare("SELECT id FROM records_status WHERE category = 'CANCELLED' LIMIT 1");
            $stmtStatus->execute();
            $status_id = $stmtStatus->fetchColumn();

            if (!$status_id) {
                $pdo->prepare("INSERT INTO records_status (name, category) VALUES ('Cancelled', 'CANCELLED')")->execute();
                $status_id = $pdo->lastInsertId();
            }

            $pdo->prepare("UPDATE records_document SET status_id = ?, updated_at = NOW() WHERE id = ? AND creator_id = ?")->execute([$status_id, $doc_id, $user_id]);
            $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('CANCELLED', 'Document cancelled and moved to history.', NOW(), ?, ?)")->execute([$user_id, $doc_id]);

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