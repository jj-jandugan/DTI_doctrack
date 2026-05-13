<?php
// controllers/incoming.php
session_start();
require_once '../classes/database.php';
require_once '../classes/documentManager.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'RO') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$docManager = new DocumentManager($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_document') {
    try {
        $pdo->beginTransaction();

        $classification_id = $_POST['classification'];
        $document_type_id  = $_POST['document_type'];
        $due_date          = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $subject           = trim($_POST['subject']);
        $particulars       = trim($_POST['particulars'] ?? '');
        $signatory_id      = $_POST['signatory'];
        $origin_id         = $docManager->findOrCreateOrigin(trim($_POST['origin_office']));
        $sender_name       = trim($_POST['sender_name']);
        $route_type        = $_POST['route_type'];

        // --- SAFE STATUS FETCH ---
        // 1. Strictly look for the 'FOR-APPROVAL' category
        $stmtStatus = $pdo->prepare("SELECT id FROM records_status WHERE UPPER(category) = 'FOR-APPROVAL' LIMIT 1");
        $stmtStatus->execute();
        $status_id = $stmtStatus->fetchColumn();

        // 2. If it is missing, auto-create it securely so the database doesn't crash!
        if (!$status_id) {
            $pdo->prepare("INSERT INTO records_status (name, category) VALUES ('For Approval', 'FOR-APPROVAL')")->execute();
            $status_id = $pdo->lastInsertId();
        }

        // --- GENERATE DTS_NO ---
        $stmtClass = $pdo->prepare("SELECT name FROM records_classification WHERE id = ?");
        $stmtClass->execute([$classification_id]);
        $prefix = (strtoupper(trim($stmtClass->fetchColumn())) === 'EXTERNAL') ? 'EX' : 'IN';
        $pattern = $prefix . date('y') . '%';

        $stmtLast = $pdo->prepare("SELECT dts_no FROM records_document WHERE dts_no LIKE ? ORDER BY dts_no DESC LIMIT 1");
        $stmtLast->execute([$pattern]);
        $last_dts = $stmtLast->fetchColumn();
        $next_num = $last_dts ? ((int) substr($last_dts, -6)) + 1 : 1;
        $dts_no = $prefix . date('y') . str_pad($next_num, 6, '0', STR_PAD_LEFT);

        // --- HANDLE DESTINATION ADDRESS ---
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
        $address_id = $stmtFindAddr->fetchColumn();

        // Safe Address Creation
        if (!$address_id) {
            $pdo->prepare("INSERT IGNORE INTO records_address (name) VALUES (?)")->execute([$destination_name]);
            // Re-fetch to ensure we get the ID even if IGNORE triggered
            $stmtFindAddr->execute([$destination_name]);
            $address_id = $stmtFindAddr->fetchColumn();
        }

        // --- INSERT DOCUMENT ---
        $stmt = $pdo->prepare("INSERT INTO records_document
            (dts_no, route_type, subject, particulars, due_date, created_at, updated_at, classification_id, document_type_id, status_id, creator_id, signatory_id, origin_id, sender, address_id)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$dts_no, $route_type, $subject, $particulars, $due_date, $classification_id, $document_type_id, $status_id, $user_id, $signatory_id, $origin_id, $sender_name, $address_id]);
        $document_id = $pdo->lastInsertId();

        // --- HANDLE RECIPIENTS (THE FIX) ---
        // Array Bypass Failsafe securely captures multiple checkboxes AND Groups
        $recipient_ids = [];
        if ($route_type === 'division') {
            if (isset($_POST['route_users']) && is_array($_POST['route_users'])) {
                $recipient_ids = $_POST['route_users'];
            } elseif (!empty($_POST['route_users'])) {
                $recipient_ids = is_array($_POST['route_users']) ? $_POST['route_users'] : explode(',', $_POST['route_users']);
            }
        } elseif ($route_type === 'group' && !empty($_POST['route_group'])) {
            $stmtGrpM = $pdo->prepare("SELECT user_id FROM records_distributiongroup_members WHERE group_id = ?");
            $stmtGrpM->execute([$_POST['route_group']]);
            $recipient_ids = $stmtGrpM->fetchAll(PDO::FETCH_COLUMN);
        }

        if (!empty($recipient_ids)) {
            $stmtRec = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
            foreach ($recipient_ids as $rid) {
                if (trim($rid) !== '') {
                    $stmtRec->execute([$document_id, trim($rid)]);
                }
            }
        }

        // --- HANDLE ATTACHMENTS ---
        if (!empty($_FILES['document_files']['name'][0])) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); // Ensure dir exists

            foreach ($_FILES['document_files']['tmp_name'] as $key => $tmp_name) {
                $file_name = time() . '_' . $_FILES['document_files']['name'][$key];
                if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                    $pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())")
                        ->execute([$document_id, 'uploads/' . $file_name]);
                }
            }
        }

        $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('ENCODED', 'Incoming external document logged by RO.', NOW(), ?, ?)")
            ->execute([$user_id, $document_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Document $dts_no created successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}