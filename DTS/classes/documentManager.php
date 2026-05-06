<?php
// classes/DocumentManager.php

class DocumentManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getClassifications() {
        return $this->pdo->query("SELECT id, name FROM records_classification ORDER BY name ASC")->fetchAll();
    }

    public function getDocumentTypes() {
        return $this->pdo->query("SELECT id, name FROM records_documenttype ORDER BY name ASC")->fetchAll();
    }

    public function getSignatories() {
        return $this->pdo->query("
            SELECT u.id, u.first_name, u.last_name, p.role
            FROM auth_user u
            JOIN records_userprofile p ON u.id = p.user_id
            WHERE p.role IN ('RD', 'ARD') AND u.is_active = 1
        ")->fetchAll();
    }

    public function getDivisions() {
        return $this->pdo->query("SELECT id, name FROM records_division ORDER BY name ASC")->fetchAll();
    }

    public function getGroups() {
        return $this->pdo->query("SELECT id, group_name FROM records_distributiongroup ORDER BY group_name ASC")->fetchAll();
    }

    public function getUsersGroupedByDivision() {
        $all_users = $this->pdo->query("
            SELECT u.id, u.first_name, u.last_name, p.division_id
            FROM auth_user u
            JOIN records_userprofile p ON u.id = p.user_id
            WHERE u.is_active = 1
        ")->fetchAll();

        $users_by_div = [];
        foreach ($all_users as $u) {
            $users_by_div[$u['division_id']][] = $u;
        }
        return $users_by_div;
    }

    public function getActiveOutgoing($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.due_date, d.created_at, d.sender,
                   s.name as status_name, s.category as status_category,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   c.name as class_name
            FROM records_document d
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            LEFT JOIN records_classification c ON d.classification_id = c.id
            WHERE d.creator_id = ? AND s.category NOT IN ('CLOSED', 'APPROVED')
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    // Add this to classes/documentManager.php

    public function getDocumentById($doc_id, $user_id) {
        $stmt = $this->pdo->prepare("
            SELECT d.*, s.name as status_name, s.category as status_category
            FROM records_document d
            LEFT JOIN records_status s ON d.status_id = s.id
            WHERE d.id = ? AND d.creator_id = ?
        ");
        $stmt->execute([$doc_id, $user_id]);
        return $stmt->fetch();
    }

    public function getDocumentRecipients($doc_id) {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.first_name, u.last_name, p.role
            FROM records_documentrecipient r
            JOIN auth_user u ON r.recipient_user_id = u.id
            JOIN records_userprofile p ON u.id = p.user_id
            WHERE r.document_id = ?
        ");
        $stmt->execute([$doc_id]);
        return $stmt->fetchAll();
    }

    public function getUserHistory($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT d.id, d.dts_no, d.subject, d.created_at, d.due_date, d.sender,
                   c.name as classification,
                   t.name as doc_type,
                   s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname,
                   divi.name as c_division,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   addr.name as address_name,
                   orig.name as origin_name,
                   dr.received_at,
                   CASE
                       WHEN d.creator_id = :uid THEN 'Outgoing'
                       ELSE 'Incoming'
                   END as doc_direction
            FROM records_document d
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_documenttype t ON d.document_type_id = t.id
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            LEFT JOIN records_origin orig ON d.origin_id = orig.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            LEFT JOIN auth_user u ON d.creator_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            LEFT JOIN records_documentrecipient dr ON d.id = dr.document_id AND dr.recipient_user_id = :uid
            WHERE d.creator_id = :uid OR dr.recipient_user_id = :uid
            ORDER BY d.created_at DESC
        ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }

    public function getAllAttachmentsGrouped() {
        $attStmt = $this->pdo->query("SELECT document_id, file_path FROM records_documentattachment");
        $all_attachments = [];
        while ($row = $attStmt->fetch()) {
            $all_attachments[$row['document_id']][] = [
                'name' => basename($row['file_path']),
                'url'  => BASE_URL . $row['file_path']
            ];
        }
        return $all_attachments;
    }

    public function getOnMyDeskDocuments($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.particulars, d.due_date, d.created_at, d.sender,
                   c.name as classification,
                   t.name as doc_type,
                   s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname,
                   divi.name as c_division,
                   sig.first_name as s_fname, sig.last_name as s_lname,
                   addr.name as address_name,
                   orig.name as origin_name
            FROM records_documentrecipient dr
            JOIN records_document d ON dr.document_id = d.id
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_documenttype t ON d.document_type_id = t.id
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            LEFT JOIN records_origin orig ON d.origin_id = orig.id
            LEFT JOIN auth_user u ON d.creator_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            WHERE dr.recipient_user_id = :uid AND s.category != 'CLOSED'
            ORDER BY d.created_at DESC
        ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }

    // Fetch all attachments and calculate their file sizes
    public function getAttachmentsWithSize() {
        $attStmt = $this->pdo->query("SELECT document_id, file_path FROM records_documentattachment");
        $all_attachments = [];

        while ($row = $attStmt->fetch()) {
            $full_path = BASE_PATH . $row['file_path'];
            $filesize = file_exists($full_path) ? round(filesize($full_path) / 1024 / 1024, 2) . ' MB' : 'Unknown Size';

            $all_attachments[$row['document_id']][] = [
                'name' => basename($row['file_path']),
                'url'  => BASE_URL . $row['file_path'],
                'size' => $filesize
            ];
        }
        return $all_attachments;
    }

    public function getApprovedForDispatch() {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.particulars, d.created_at, d.updated_at as approved_at, d.due_date, d.route_type,
                   c.name as classification, t.name as doc_type,
                   s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
                   addr.name as address_name, sig.first_name as sig_fname, sig.last_name as sig_lname
            FROM records_document d
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_documenttype t ON d.document_type_id = t.id
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            LEFT JOIN auth_user u ON d.creator_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            WHERE s.category = 'APPROVED' AND d.route_type IN ('outside_dti', 'within_dti')
            ORDER BY d.updated_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getEncodedIncoming($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.due_date, d.created_at, d.sender, orig.name as origin_name,
                   s.name as status_name, s.category as status_category,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   c.name as class_name, t.name as doc_type, addr.name as destination_name
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_origin orig ON d.origin_id = orig.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_documenttype t ON d.document_type_id = t.id
            WHERE d.creator_id = ?
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    /**
     * Logic to find or create an originating office
     */
    public function findOrCreateOrigin($name) {
        $stmtFind = $this->pdo->prepare("SELECT id FROM records_origin WHERE name = ?");
        $stmtFind->execute([$name]);
        $id = $stmtFind->fetchColumn();

        if (!$id) {
            $stmtInsert = $this->pdo->prepare("INSERT INTO records_origin (name) VALUES (?)");
            $stmtInsert->execute([$name]);
            $id = $this->pdo->lastInsertId();
        }
        return $id;
    }

    public function getROHistory($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT d.id, d.dts_no, d.subject, d.created_at, d.updated_at, d.due_date,
                   d.sender, d.particulars, orig.name as origin_name, a.name as address_name,
                   c.name as classification, t.name as doc_type,
                   s.name as status_name, s.category as status_category,
                   creator.first_name as c_fname, creator.last_name as c_lname, divi.name as c_division,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   CASE
                       WHEN d.creator_id = :uid THEN 'incoming'
                       ELSE 'outgoing'
                   END as direction
            FROM records_document d
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_documenttype t ON d.document_type_id = t.id
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_address a ON d.address_id = a.id
            LEFT JOIN records_origin orig ON d.origin_id = orig.id
            LEFT JOIN auth_user creator ON d.creator_id = creator.id
            LEFT JOIN records_userprofile p ON creator.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            LEFT JOIN records_trackinghistory th ON d.id = th.document_id AND th.acted_by_id = :uid
            WHERE (d.creator_id = :uid OR th.acted_by_id = :uid)
            AND s.category IN ('CLOSED', 'COMPLETED', 'CANCELLED', 'REJECTED')
            ORDER BY d.updated_at DESC
        ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }

    public function getDocumentForEdit($doc_id, $user_id) {
    $stmt = $this->pdo->prepare("
        SELECT d.*, orig.name as origin_name
        FROM records_document d
        LEFT JOIN records_origin orig ON d.origin_id = orig.id
        WHERE d.id = ? AND d.creator_id = ?
    ");
    $stmt->execute([$doc_id, $user_id]);
    return $stmt->fetch();
    }

    /**
     * Fetch existing attachments for a specific document
     */
    public function getDocumentAttachments($doc_id) {
        $stmt = $this->pdo->prepare("SELECT id, file_path FROM records_documentattachment WHERE document_id = ?");
        $stmt->execute([$doc_id]);
        return $stmt->fetchAll();
    }

    public function getDocumentDetails($doc_id) {
    $stmt = $this->pdo->prepare("
        SELECT d.*,
               s.name as status_name, s.category as status_category,
               addr.name as address_name,
               orig.name as origin_name,
               sig.first_name as sig_fname, sig.last_name as sig_lname
        FROM records_document d
        LEFT JOIN records_status s ON d.status_id = s.id
        LEFT JOIN records_address addr ON d.address_id = addr.id
        LEFT JOIN records_origin orig ON d.origin_id = orig.id
        LEFT JOIN auth_user sig ON d.signatory_id = sig.id
        WHERE d.id = ?
    ");
    $stmt->execute([$doc_id]);
    return $stmt->fetch();
    }

    public function getSignatoryMetrics($user_id) {
    $metrics = [];

    // Incoming (Routed to them but not yet 'received' in the recipient table)
    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM records_documentrecipient WHERE recipient_user_id = ? AND has_received = 0");
    $stmt->execute([$user_id]);
    $metrics['incoming'] = $stmt->fetchColumn();

    // For Approval (Specifically documents where they are the assigned Signatory)
    $stmt = $this->pdo->prepare("SELECT COUNT(d.id) FROM records_document d JOIN records_status s ON d.status_id = s.id WHERE d.signatory_id = ? AND s.category = 'FOR-APPROVAL'");
    $stmt->execute([$user_id]);
    $metrics['for_approval'] = $stmt->fetchColumn();

    // Closed/Approved (Their finalized work)
    $stmt = $this->pdo->prepare("SELECT COUNT(d.id) FROM records_document d JOIN records_status s ON d.status_id = s.id WHERE d.signatory_id = ? AND s.category IN ('APPROVED', 'CLOSED')");
    $stmt->execute([$user_id]);
    $metrics['finalized'] = $stmt->fetchColumn();

    // Outgoing (Documents they authored)
    $stmt = $this->pdo->prepare("SELECT COUNT(id) FROM records_document WHERE creator_id = ?");
    $stmt->execute([$user_id]);
    $metrics['outgoing'] = $stmt->fetchColumn();

    return $metrics;
    }

/**
 * Fetch monthly document volume for the current year
    */
    public function getMonthlyVolume($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT MONTH(created_at) as month_num, COUNT(id) as doc_count
            FROM records_document
            WHERE (creator_id = ? OR signatory_id = ?) AND YEAR(created_at) = YEAR(CURDATE())
            GROUP BY MONTH(created_at)
        ");
        $stmt->execute([$user_id, $user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = array_fill(0, 12, 0);
        foreach ($data as $row) {
            $counts[(int)$row['month_num'] - 1] = (int)$row['doc_count'];
        }
        return $counts;
    }

    public function getRecentSignatoryDocs($user_id, $category_type) {
    $whereClause = ($category_type === 'approval')
        ? "s.category = 'FOR-APPROVAL'"
        : "s.category IN ('APPROVED', 'CLOSED')";

    $stmt = $this->pdo->prepare("
        SELECT d.id, d.dts_no, d.subject, a.name as address_name,
            u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division
        FROM records_document d
        JOIN records_status s ON d.status_id = s.id
        LEFT JOIN records_address a ON d.address_id = a.id
        LEFT JOIN auth_user u ON d.creator_id = u.id
        LEFT JOIN records_userprofile p ON u.id = p.user_id
        LEFT JOIN records_division divi ON p.division_id = divi.id
        WHERE d.signatory_id = ? AND $whereClause
        ORDER BY d.updated_at DESC LIMIT 5
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
    }

    public function getDivisionDocsForApproval($user_id) {
    $stmt = $this->pdo->prepare("
        SELECT d.id, d.dts_no, d.subject, d.particulars, d.created_at, d.due_date, d.sender as routing_notes, d.route_type,
               c.name as classification, t.name as doc_type,
               s.name as status_name, s.category as status_category,
               u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
               addr.name as address_name, orig.name as origin_name
        FROM records_document d
        LEFT JOIN records_classification c ON d.classification_id = c.id
        LEFT JOIN records_documenttype t ON d.document_type_id = t.id
        LEFT JOIN records_status s ON d.status_id = s.id
        LEFT JOIN records_address addr ON d.address_id = addr.id
        LEFT JOIN records_origin orig ON d.origin_id = orig.id
        LEFT JOIN auth_user u ON d.creator_id = u.id
        LEFT JOIN records_userprofile p ON u.id = p.user_id
        LEFT JOIN records_division divi ON p.division_id = divi.id
        WHERE d.signatory_id = :uid AND s.category = 'FOR-APPROVAL' AND p.role = 'Division'
        ORDER BY d.updated_at ASC
    ");
    $stmt->execute(['uid' => $user_id]);
    return $stmt->fetchAll();
    }

    public function getIncomingForApproval($user_id) {
    $stmt = $this->pdo->prepare("
        SELECT d.id, d.dts_no, d.subject, d.created_at, d.due_date, d.sender,
               orig.name as origin_name, t.name as doc_type,
               s.name as status_name, s.category as status_category,
               u.first_name as c_fname, u.last_name as c_lname
        FROM records_document d
        JOIN records_status s ON d.status_id = s.id
        LEFT JOIN records_origin orig ON d.origin_id = orig.id
        LEFT JOIN records_documenttype t ON d.document_type_id = t.id
        LEFT JOIN auth_user u ON d.creator_id = u.id
        WHERE d.signatory_id = ? AND s.category = 'FOR-APPROVAL'
        ORDER BY d.created_at ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
    }

    public function getRecipientsByDocument() {
        $stmt = $this->pdo->query("
            SELECT dr.document_id, u.first_name, u.last_name, divi.name as div_name
            FROM records_documentrecipient dr
            JOIN auth_user u ON dr.recipient_user_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
        ");
        $all_recipients = [];
        while ($row = $stmt->fetch()) {
            $name = trim($row['first_name'] . ' ' . $row['last_name']);
            $div = $row['div_name'] ? " (" . $row['div_name'] . ")" : "";
            $all_recipients[$row['document_id']][] = $name . $div;
        }
        return $all_recipients;
    }

    public function getOutgoingForApproval($user_id) {
    $stmt = $this->pdo->prepare("
        SELECT d.id, d.dts_no, d.subject, d.created_at, d.due_date, d.route_type,
               c.name as classification, t.name as doc_type,
               s.name as status_name, s.category as status_category,
               u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division
        FROM records_document d
        JOIN records_status s ON d.status_id = s.id
        LEFT JOIN records_classification c ON d.classification_id = c.id
        LEFT JOIN records_documenttype t ON d.document_type_id = t.id
        LEFT JOIN auth_user u ON d.creator_id = u.id
        LEFT JOIN records_userprofile p ON u.id = p.user_id
        LEFT JOIN records_division divi ON p.division_id = divi.id
        WHERE d.signatory_id = ? AND s.category = 'FOR-APPROVAL' AND p.role = 'Division'
        ORDER BY d.created_at ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
    }

    public function getSignatoryHistory($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT d.id, d.dts_no, d.subject, d.created_at, d.updated_at, d.due_date, d.sender, d.particulars,
                   c.name as classification,
                   t.name as doc_type,
                   s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname,
                   divi.name as c_division,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   addr.name as address_name,
                   orig.name as origin_name,
                   dr.received_at,
                   CASE
                       WHEN d.creator_id = :uid THEN 'Outgoing'
                       ELSE 'Incoming'
                   END as doc_direction
            FROM records_document d
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_documenttype t ON d.document_type_id = t.id
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            LEFT JOIN records_origin orig ON d.origin_id = orig.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            LEFT JOIN auth_user u ON d.creator_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            LEFT JOIN records_documentrecipient dr ON d.id = dr.document_id AND dr.recipient_user_id = :uid
            WHERE (d.creator_id = :uid OR d.signatory_id = :uid OR dr.recipient_user_id = :uid)
              AND s.category IN ('CLOSED', 'REJECTED', 'COMPLETED', 'CANCELLED')
            ORDER BY d.created_at DESC
        ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }

    public function getDashboardIncoming($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, a.name as address_name,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            JOIN auth_user u ON d.creator_id = u.id
            JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            LEFT JOIN records_address a ON d.address_id = a.id
            WHERE d.signatory_id = ?
              AND s.category = 'FOR-APPROVAL'
              AND p.role = 'RO'
            ORDER BY d.created_at DESC LIMIT 5
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch internal division documents waiting for Signatory approval
     */
    public function getDashboardOutgoing($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, a.name as address_name,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            JOIN auth_user u ON d.creator_id = u.id
            JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            LEFT JOIN records_address a ON d.address_id = a.id
            WHERE d.signatory_id = ?
              AND s.category = 'FOR-APPROVAL'
              AND p.role = 'Division'
            ORDER BY d.created_at DESC LIMIT 5
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    // Add to classes/documentManager.php

public function updateIncomingDocument($doc_id, $user_id, $data, $files, $remove_attachments) {
    try {
        $this->pdo->beginTransaction();

        // 1. Handle Origin
        $origin_id = $this->findOrCreateOrigin($data['origin_office']);

        // 2. Handle Address/Destination
        $destination_name = 'Unassigned Internal Routing';
        if ($data['route_type'] === 'division' && !empty($data['route_division'])) {
            $stmt = $this->pdo->prepare("SELECT name FROM records_division WHERE id = ?");
            $stmt->execute([$data['route_division']]);
            $destination_name = $stmt->fetchColumn();
        } elseif ($data['route_type'] === 'group' && !empty($data['route_group'])) {
            $stmt = $this->pdo->prepare("SELECT group_name FROM records_distributiongroup WHERE id = ?");
            $stmt->execute([$data['route_group']]);
            $destination_name = $stmt->fetchColumn();
        }

        $stmtAddr = $this->pdo->prepare("SELECT id FROM records_address WHERE name = ? LIMIT 1");
        $stmtAddr->execute([$destination_name]);
        $address_id = $stmtAddr->fetchColumn();

        if (!$address_id) {
            $stmtIns = $this->pdo->prepare("INSERT INTO records_address (name) VALUES (?)");
            $stmtIns->execute([$destination_name]);
            $address_id = $this->pdo->lastInsertId();
        }

        // 3. Update Main Document
        $stmtUpdate = $this->pdo->prepare("
            UPDATE records_document
            SET subject = ?, particulars = ?, due_date = ?, updated_at = NOW(),
                document_type_id = ?, signatory_id = ?,
                origin_id = ?, sender = ?, address_id = ?, route_type = ?
            WHERE id = ? AND creator_id = ?
        ");
        $stmtUpdate->execute([
            $data['subject'], $data['particulars'], $data['due_date'], $data['document_type'],
            $data['signatory'], $origin_id, $data['sender_name'], $address_id, $data['route_type'],
            $doc_id, $user_id
        ]);

        // 4. Re-assign Recipients
        $this->pdo->prepare("DELETE FROM records_documentrecipient WHERE document_id = ?")->execute([$doc_id]);

        $recipient_ids = [];
        if ($data['route_type'] === 'division' && !empty($data['route_users'])) {
            $recipient_ids = $data['route_users'];
        } elseif ($data['route_type'] === 'group' && !empty($data['route_group'])) {
            $stmtGrp = $this->pdo->prepare("SELECT user_id FROM records_distributiongroup_members WHERE group_id = ?");
            $stmtGrp->execute([$data['route_group']]);
            $recipient_ids = $stmtGrp->fetchAll(PDO::FETCH_COLUMN);
        }

        $stmtInsRecip = $this->pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
        foreach ($recipient_ids as $uid) {
            $stmtInsRecip->execute([$doc_id, $uid]);
        }

        // 5. Handle File Removals
        if (!empty($remove_attachments)) {
            foreach ($remove_attachments as $att_id) {
                $stmtFile = $this->pdo->prepare("SELECT file_path FROM records_documentattachment WHERE id = ? AND document_id = ?");
                $stmtFile->execute([$att_id, $doc_id]);
                $path = $stmtFile->fetchColumn();
                if ($path && file_exists('../../' . $path)) unlink('../../' . $path);
                $this->pdo->prepare("DELETE FROM records_documentattachment WHERE id = ?")->execute([$att_id]);
            }
        }

        // 6. Handle New Uploads
        if (!empty($files['name'][0])) {
            $upload_dir = '../../uploads/';
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . $i . '_' . basename($files['name'][$i]);
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $file_name)) {
                        $this->pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())")
                                  ->execute([$doc_id, 'uploads/' . $file_name]);
                    }
                }
            }
        }

        // 7. Log History
        $this->pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('EDITED', 'Document details updated by Records Officer.', NOW(), ?, ?)")
                  ->execute([$user_id, $doc_id]);

        $this->pdo->commit();
        return true;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        throw $e;
    }
}
}
?>