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

        // Get Status ID for FOR-APPROVAL
        $status_id = $pdo->query("SELECT id FROM records_status WHERE category = 'FOR-APPROVAL' LIMIT 1")->fetchColumn();

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

        if (!$address_id) {
            $pdo->prepare("INSERT INTO records_address (name) VALUES (?)")->execute([$destination_name]);
            $address_id = $pdo->lastInsertId();
        }

        // --- INSERT DOCUMENT ---
        $stmt = $pdo->prepare("INSERT INTO records_document
            (dts_no, route_type, subject, particulars, due_date, created_at, updated_at, classification_id, document_type_id, status_id, creator_id, signatory_id, origin_id, sender, address_id)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$dts_no, $route_type, $subject, $particulars, $due_date, $classification_id, $document_type_id, $status_id, $user_id, $signatory_id, $origin_id, $sender_name, $address_id]);
        $document_id = $pdo->lastInsertId();

        // --- HANDLE RECIPIENTS ---
        if ($route_type === 'division' && !empty($_POST['route_users'])) {
            $stmtRec = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
            foreach ($_POST['route_users'] as $uid) { $stmtRec->execute([$document_id, $uid]); }
        }

        // --- HANDLE ATTACHMENTS ---
        if (!empty($_FILES['document_files']['name'][0])) {
            $upload_dir = '../uploads/';
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