<?php
// controllers/Outgoing.php
session_start();
require_once '../classes/database.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_document') {
    try {
        $pdo->beginTransaction();

        $classification_id = !empty($_POST['classification']) ? $_POST['classification'] : null;
        $document_type_id = !empty($_POST['document_type']) ? $_POST['document_type'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $subject = trim($_POST['subject']);
        $particulars = trim($_POST['particulars'] ?? '');
        $signatory_id = !empty($_POST['signatory']) ? $_POST['signatory'] : null;

        $stmtStatus = $pdo->query("SELECT id FROM records_status WHERE category = 'ONGOING' LIMIT 1");
        $status_id = $stmtStatus->fetchColumn();

        if (!$status_id) {
            throw new Exception("No 'ONGOING' status configured.");
        }

        // --- AUTO-GENERATE CONTROL NUMBER ---
        $stmtClass = $pdo->prepare("SELECT name FROM records_classification WHERE id = ?");
        $stmtClass->execute([$classification_id]);
        $class_name = strtoupper($stmtClass->fetchColumn());
        $prefix = (strpos($class_name, 'EXTERNAL') !== false) ? 'EX' : 'IN';

        $current_year_2d = date('y');
        $search_pattern = $prefix . $current_year_2d . '%';

        $stmtLastDoc = $pdo->prepare("SELECT dts_no FROM records_document WHERE dts_no LIKE ? ORDER BY dts_no DESC LIMIT 1");
        $stmtLastDoc->execute([$search_pattern]);
        $last_dts = $stmtLastDoc->fetchColumn();

        $next_number = $last_dts ? ((int) substr($last_dts, -6)) + 1 : 1;
        $dts_no = $prefix . $current_year_2d . str_pad($next_number, 6, '0', STR_PAD_LEFT);

        // --- PROCESS DYNAMIC ROUTING DESTINATION ---
        $route_type = $_POST['route_type'] ?? '';
        $destination_text = '';

        if ($route_type === 'division') {
            $div_id = $_POST['route_division'];
            $usr_ids = $_POST['route_users'] ?? [];
            $stmtDiv = $pdo->prepare("SELECT name FROM records_division WHERE id = ?");
            $stmtDiv->execute([$div_id]);
            $div_name = $stmtDiv->fetchColumn();

            $usr_names = [];
            if (!empty($usr_ids)) {
                $inQuery = implode(',', array_fill(0, count($usr_ids), '?'));
                $stmtUsr = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM auth_user WHERE id IN ($inQuery)");
                $stmtUsr->execute($usr_ids);
                $usr_names = $stmtUsr->fetchAll(PDO::FETCH_COLUMN);
            }
            $destination_text = "Division: $div_name" . (!empty($usr_names) ? " - " . implode(', ', $usr_names) : "");

        } elseif ($route_type === 'group') {
            $grp_id = $_POST['route_group'];
            $stmtGrp = $pdo->prepare("SELECT group_name FROM records_distributiongroup WHERE id = ?");
            $stmtGrp->execute([$grp_id]);
            $destination_text = "Group: " . $stmtGrp->fetchColumn();

        } elseif ($route_type === 'within_dti' || $route_type === 'outside_dti') {
            $prefix_text = ($route_type === 'within_dti') ? "DTI Branch" : "External";
            $destination_text = "$prefix_text: " . trim($_POST['ext_office']) . (trim($_POST['ext_name']) ? " - " . trim($_POST['ext_name']) : "");
        }

        // Insert Document
        $stmt = $pdo->prepare("INSERT INTO records_document
            (dts_no, subject, particulars, due_date, created_at, updated_at, classification_id, document_type_id, status_id, creator_id, signatory_id, sender)
            VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$dts_no, $subject, $particulars, $due_date, $classification_id, $document_type_id, $status_id, $user_id, $signatory_id, $destination_text]);
        $document_id = $pdo->lastInsertId();

        // --- AUTOMATIC RECIPIENT INSERTION ---
        if ($route_type === 'division' && !empty($_POST['route_users'])) {
            $stmtIns = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
            foreach ($_POST['route_users'] as $uid) {
                $stmtIns->execute([$document_id, $uid]);
            }
        } elseif ($route_type === 'group' && !empty($_POST['route_group'])) {
            $stmtGrpUsers = $pdo->prepare("SELECT user_id FROM records_distributiongroup_members WHERE group_id = ?");
            $stmtGrpUsers->execute([$_POST['route_group']]);
            $grpUsers = $stmtGrpUsers->fetchAll(PDO::FETCH_COLUMN);

            $stmtIns = $pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
            foreach ($grpUsers as $uid) {
                $stmtIns->execute([$document_id, $uid]);
            }
        }

        // --- HANDLE FILE UPLOAD ---
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $file_name = time() . '_' . basename($_FILES['document_file']['name']);
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_path)) {
                $db_path = 'uploads/' . $file_name;
                $stmtFile = $pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())");
                $stmtFile->execute([$document_id, $db_path]);
            }
        }

        $stmtLog = $pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('CREATED', 'New document created and routed.', NOW(), ?, ?)");
        $stmtLog->execute([$user_id, $document_id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Document " . htmlspecialchars($dts_no) . " created and routed successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error creating document: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
} else {
    header("Location: ../login.php");
    exit;
}