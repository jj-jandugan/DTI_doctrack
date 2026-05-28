<?php
// classes/documentManager.php

class DocumentManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getClassifications()
    {
        return $this->pdo->query("SELECT id, name FROM records_classification ORDER BY name ASC")->fetchAll();
    }

    public function getDocumentTypes()
    {
        return $this->pdo->query("SELECT id, name FROM records_documenttype ORDER BY name ASC")->fetchAll();
    }

    public function getSignatories()
    {
        return $this->pdo->query("
        SELECT u.id, u.first_name, u.last_name, p.role
        FROM auth_user u
        JOIN records_userprofile p ON u.id = p.user_id
        WHERE p.role = 'Signatory' AND u.is_active = 1
    ")->fetchAll();
    }

    public function getDtiBranches()
    {
        try {
            return $this->pdo->query("SELECT id, name FROM records_dtibranch ORDER BY name ASC")->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getDivisions()
    {
        return $this->pdo->query("SELECT id, name FROM records_division ORDER BY name ASC")->fetchAll();
    }

    public function getGroups()
    {
        return $this->pdo->query("SELECT id, group_name FROM records_distributiongroup ORDER BY group_name ASC")->fetchAll();
    }

    public function getUsersGroupedByDivision($current_user_id = null)
    {
        $sql = "
            SELECT u.id, u.first_name, u.last_name, p.division_id
            FROM auth_user u
            JOIN records_userprofile p ON u.id = p.user_id
            WHERE u.is_active = 1 AND p.role != 'RO'
        ";

        if ($current_user_id) {
            $sql .= " AND u.id != " . intval($current_user_id);
        }

        $all_users = $this->pdo->query($sql)->fetchAll();

        $users_by_div = [];
        foreach ($all_users as $u) {
            $users_by_div[$u['division_id']][] = $u;
        }
        return $users_by_div;
    }

    public function getActiveOutgoing($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.due_date, d.created_at, d.sender,
                   s.name as status_name, s.category as status_category,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   c.name as class_name, addr.name as address_name,
                   (SELECT GROUP_CONCAT(CONCAT(ru.first_name, ' ', ru.last_name) SEPARATOR ', ')
                    FROM records_documentrecipient dr2
                    LEFT JOIN auth_user ru ON dr2.recipient_user_id = ru.id
                    WHERE dr2.document_id = d.id) as receiver_name
            FROM records_document d
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            WHERE d.creator_id = ? AND s.category IN ('ONGOING', 'FOR-APPROVAL', 'APPROVED', 'REJECTED')
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function getDocumentById($doc_id, $user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, s.name as status_name, s.category as status_category
            FROM records_document d
            LEFT JOIN records_status s ON d.status_id = s.id
            WHERE d.id = ? AND d.creator_id = ?
        ");
        $stmt->execute([$doc_id, $user_id]);
        return $stmt->fetch();
    }

    public function getDocumentRecipients($doc_id)
    {
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

    public function getUserHistory($user_id)
    {
        $stmt = $this->pdo->prepare("
        SELECT DISTINCT d.id, d.dts_no, d.subject, d.particulars, d.created_at, d.updated_at, d.due_date, d.sender,
               c.name as classification, t.name as doc_type, s.name as status_name, s.category as status_category,
               u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
               sig.first_name as sig_fname, sig.last_name as sig_lname,
               addr.name as address_name, orig.name as origin_name, dr.received_at,
               CASE WHEN d.creator_id = :uid THEN 'Outgoing' ELSE 'Incoming' END as doc_direction
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
       WHERE (d.creator_id = :uid OR dr.recipient_user_id = :uid)
          AND s.category IN ('CLOSED', 'CANCELLED')
          AND UPPER(s.name) != 'REJECTED'
        ORDER BY d.created_at DESC
    ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }

    public function getAllAttachmentsGrouped()
    {
        $attStmt = $this->pdo->query("SELECT document_id, file_path FROM records_documentattachment");
        $all_attachments = [];
        while ($row = $attStmt->fetch()) {
            $all_attachments[$row['document_id']][] = [
                'name' => basename($row['file_path']),
                'url' => BASE_URL . $row['file_path']
            ];
        }
        return $all_attachments;
    }

    public function getOnMyDeskDocuments($user_id)
    {
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
            WHERE dr.recipient_user_id = :uid AND s.category NOT IN ('CLOSED', 'CANCELLED', 'REJECTED')
            ORDER BY d.created_at DESC
        ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }

    public function getAttachmentsWithSize()
    {
        $attStmt = $this->pdo->query("SELECT document_id, file_path FROM records_documentattachment");
        $all_attachments = [];

        while ($row = $attStmt->fetch()) {
            $full_path = BASE_PATH . $row['file_path'];
            $filesize = file_exists($full_path) ? round(filesize($full_path) / 1024 / 1024, 2) . ' MB' : 'Unknown Size';

            $all_attachments[$row['document_id']][] = [
                'name' => basename($row['file_path']),
                'url' => BASE_URL . $row['file_path'],
                'size' => $filesize
            ];
        }
        return $all_attachments;
    }

    public function getApprovedForDispatch()
    {
        $stmt = $this->pdo->prepare("
        SELECT d.id, d.dts_no, d.subject, d.particulars, d.created_at, d.updated_at as approved_at,
               d.due_date, d.route_type, d.sender,
               c.name as classification, t.name as doc_type,
               s.name as status_name, s.category as status_category,
               u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
               addr.name as address_name, sig.first_name as sig_fname, sig.last_name as sig_lname,
               /* NEW: Fetch external contact persons if they exist */
               (SELECT GROUP_CONCAT(contact_person SEPARATOR ', ')
                FROM records_externalrecipient
                WHERE document_id = d.id AND contact_person IS NOT NULL AND contact_person != '') as external_receivers
        FROM records_document d
        LEFT JOIN records_classification c ON d.classification_id = c.id
        LEFT JOIN records_documenttype t ON d.document_type_id = t.id
        LEFT JOIN records_status s ON d.status_id = s.id
        LEFT JOIN records_address addr ON d.address_id = addr.id
        LEFT JOIN auth_user u ON d.creator_id = u.id
        LEFT JOIN records_userprofile p ON u.id = p.user_id
        LEFT JOIN records_division divi ON p.division_id = divi.id
        LEFT JOIN auth_user sig ON d.signatory_id = sig.id
        WHERE (s.name = 'APPROVED' OR s.category = 'APPROVED')
          AND d.route_type IN ('outside_dti', 'within_dti')
        ORDER BY (d.due_date IS NULL) ASC, d.due_date ASC, d.updated_at ASC
    ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getEncodedIncoming($user_id)
    {
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
          AND s.name IN ('FOR APPROVAL', 'REJECTED') -- Updated to include REJECTED
        ORDER BY d.created_at DESC
    ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function findOrCreateOrigin($name)
    {
        $name = trim($name);
        $stmtFind = $this->pdo->prepare("SELECT id FROM records_origin WHERE name = ? LIMIT 1");
        $stmtFind->execute([$name]);
        $id = $stmtFind->fetchColumn();

        if (!$id) {
            $stmtInsert = $this->pdo->prepare("INSERT IGNORE INTO records_origin (name) VALUES (?)");
            $stmtInsert->execute([$name]);

            $stmtFind->execute([$name]);
            $id = $stmtFind->fetchColumn();

            if (empty($id)) {
                throw new Exception("System Error: Failed to generate Origin ID for '" . $name . "'. Check records_origin AUTO_INCREMENT.");
            }
        }
        return $id;
    }

    public function getROHistory($user_id)
    {
        $stmt = $this->pdo->prepare("
        SELECT DISTINCT d.id, d.dts_no, d.subject, d.particulars, d.created_at, d.updated_at, d.due_date, d.sender,
               (SELECT GROUP_CONCAT(CONCAT(ru.first_name, ' ', ru.last_name) SEPARATOR ', ') FROM records_documentrecipient dr2 JOIN auth_user ru ON dr2.recipient_user_id = ru.id WHERE dr2.document_id = d.id) as receiver_name,
               c.name as classification, t.name as doc_type, s.name as status_name, s.category as status_category,
               u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
               sig.first_name as sig_fname, sig.last_name as sig_lname,
               addr.name as address_name, orig.name as origin_name,
               CASE WHEN d.creator_id = :uid THEN 'Outgoing' ELSE 'Incoming' END as doc_direction
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
        LEFT JOIN records_trackinghistory th ON d.id = th.document_id
        WHERE (d.creator_id = :uid OR th.acted_by_id = :uid)
          AND s.category IN ('CANCELLED', 'CLOSED')
          AND UPPER(s.name) != 'REJECTED'
        ORDER BY d.created_at DESC
    ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }


    public function getDocumentForEdit($doc_id, $user_id)
    {
        $stmt = $this->pdo->prepare("
        SELECT d.*,
               orig.name as origin_name,
               addr.name as address_name, /* <--- ADDED THIS */
               s.name as status_name,
               s.category as status_category
        FROM records_document d
        LEFT JOIN records_origin orig ON d.origin_id = orig.id
        LEFT JOIN records_address addr ON d.address_id = addr.id /* <--- ADDED THIS */
        LEFT JOIN records_status s ON d.status_id = s.id
        WHERE d.id = ? AND d.creator_id = ?
    ");
        $stmt->execute([$doc_id, $user_id]);
        return $stmt->fetch();
    }

    public function getDocumentAttachments($doc_id)
    {
        $stmt = $this->pdo->prepare("SELECT id, file_path FROM records_documentattachment WHERE document_id = ?");
        $stmt->execute([$doc_id]);
        return $stmt->fetchAll();
    }

    public function getDocumentDetails($doc_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*,
                   s.name as status_name, s.category as status_category,
                   addr.name as address_name,
                   orig.name as origin_name,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division
            FROM records_document d
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            LEFT JOIN records_origin orig ON d.origin_id = orig.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            LEFT JOIN auth_user u ON d.creator_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            WHERE d.id = ?
        ");
        $stmt->execute([$doc_id]);
        return $stmt->fetch();
    }

    public function getSignatoryMetrics($user_id)
    {
        $metrics = [];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM records_documentrecipient WHERE recipient_user_id = ? AND has_received = 0");
        $stmt->execute([$user_id]);
        $metrics['incoming'] = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(d.id) FROM records_document d JOIN records_status s ON d.status_id = s.id WHERE d.signatory_id = ? AND s.category = 'FOR-APPROVAL'");
        $stmt->execute([$user_id]);
        $metrics['for_approval'] = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(d.id) FROM records_document d JOIN records_status s ON d.status_id = s.id WHERE d.signatory_id = ? AND s.category IN ('APPROVED', 'REJECTED', 'CLOSED')");
        $stmt->execute([$user_id]);
        $metrics['finalized'] = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(id) FROM records_document WHERE creator_id = ?");
        $stmt->execute([$user_id]);
        $metrics['outgoing'] = $stmt->fetchColumn();

        return $metrics;
    }

    public function getMonthlyVolume($user_id)
    {
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
            $counts[(int) $row['month_num'] - 1] = (int) $row['doc_count'];
        }
        return $counts;
    }

    public function getRecentSignatoryDocs($user_id, $category_type)
    {
        $whereClause = ($category_type === 'approval')
            ? "s.category = 'FOR-APPROVAL'"
            : "s.category IN ('APPROVED', 'REJECTED', 'CLOSED')";

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

    public function getDivisionDocsForApproval($user_id)
    {
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

    public function getIncomingForApproval($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.created_at, d.due_date, d.sender,
                   orig.name as origin_name, t.name as doc_type,
                   c.name as classification,
                   s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname,
                   addr.name as address_name /* Ensure this is fetched */
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_origin orig ON d.origin_id = orig.id
            LEFT JOIN records_documenttype t ON d.document_type_id = t.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            LEFT JOIN auth_user u ON d.creator_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            WHERE d.signatory_id = ?
              AND s.category = 'FOR-APPROVAL'
              AND p.role = 'RO'
            /* PRIORITY SORTING */
            ORDER BY (d.due_date IS NULL) ASC, d.due_date ASC, d.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function getRecipientsByDocument()
    {
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

    public function getOutgoingForApproval($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.created_at, d.due_date, d.route_type, d.sender,
                   c.name as classification, t.name as doc_type,
                   s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
                   addr.name as address_name
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_documenttype t ON d.document_type_id = t.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            LEFT JOIN auth_user u ON d.creator_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            WHERE d.signatory_id = ?
              AND s.category = 'FOR-APPROVAL'
              AND p.role = 'Division'
            /* PRIORITY SORTING: Overdue/Nearest first, Nulls last */
            ORDER BY (d.due_date IS NULL) ASC, d.due_date ASC, d.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function getSignatoryHistory($user_id)
    {
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
              -- THIS IS THE FIX: Explicitly checks both the 'name' and 'category' for the new 6-status system
              AND (s.name IN ('CLOSED', 'REJECTED', 'APPROVED', 'CANCELLED')
                   OR s.category IN ('CLOSED', 'REJECTED', 'APPROVED', 'CANCELLED'))
            ORDER BY d.created_at DESC
        ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }

    public function getDashboardIncoming($user_id)
    {
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
          AND s.category IN ('FOR-APPROVAL', 'ONGOING')
          AND p.role = 'RO'
          AND DATE(d.created_at) = CURDATE() /* THIS LINE FILTERS BY TODAY ONLY */
        ORDER BY d.created_at DESC LIMIT 5
    ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function getDashboardOutgoing($user_id)
    {
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
              AND DATE(d.created_at) = CURDATE() /* FIX: Only Today's docs */
            ORDER BY d.created_at DESC LIMIT 5
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function getDashboardRODispatch()
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.due_date,
                   s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division
            FROM records_document d
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN auth_user u ON d.creator_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            WHERE (s.name = 'APPROVED' OR s.category = 'APPROVED')
              AND d.route_type IN ('outside_dti', 'within_dti')
              AND DATE(d.updated_at) = CURDATE() /* FIX: Only approved today */
            ORDER BY d.updated_at DESC LIMIT 5
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getDashboardDivIncoming($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.due_date,
                   s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division
            FROM records_documentrecipient dr
            JOIN records_document d ON dr.document_id = d.id
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN auth_user u ON d.creator_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            WHERE dr.recipient_user_id = :uid
              /* FIX: Ensure it is Approved and hasn't been received yet */
              AND s.category = 'APPROVED'
              AND dr.has_received = 0
              AND DATE(d.created_at) = CURDATE() /* FIX: Only routed today */
            ORDER BY d.created_at DESC LIMIT 5
        ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }

    public function updateIncomingDocument($doc_id, $user_id, $data, $files, $remove_attachments)
    {
        try {
            $this->pdo->beginTransaction();

            $origin_id = $this->findOrCreateOrigin($data['origin_office']);

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
                $stmtInsAddr = $this->pdo->prepare("INSERT INTO records_address (name) VALUES (?)");
                $stmtInsAddr->execute([$destination_name]);
                $address_id = $this->pdo->lastInsertId();

                if (empty($address_id)) {
                    $stmtFindAddr->execute([$destination_name]);
                    $address_id = $stmtFindAddr->fetchColumn();
                }
            }

            $stmtUpdate = $this->pdo->prepare("
                UPDATE records_document
                SET subject = ?, particulars = ?, due_date = ?, updated_at = NOW(),
                    document_type_id = ?, signatory_id = ?,
                    origin_id = ?, sender = ?, address_id = ?, route_type = ?
                WHERE id = ? AND creator_id = ?
            ");
            $stmtUpdate->execute([
                $data['subject'],
                $data['particulars'],
                (!empty($data['due_date']) ? $data['due_date'] : null),
                $data['document_type'],
                (!empty($data['signatory']) ? $data['signatory'] : null),
                $origin_id,
                $data['sender_name'],
                $address_id,
                $data['route_type'],
                $doc_id,
                $user_id
            ]);

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

            if (!empty($remove_attachments)) {
                foreach ($remove_attachments as $att_id) {
                    $stmtFile = $this->pdo->prepare("SELECT file_path FROM records_documentattachment WHERE id = ? AND document_id = ?");
                    $stmtFile->execute([$att_id, $doc_id]);
                    $path = $stmtFile->fetchColumn();

                    // BULLETPROOF PATH RESOLUTION FOR DELETE
                    $full_path = __DIR__ . '/../' . $path;
                    if ($path && file_exists($full_path)) {
                        unlink($full_path);
                    }
                    $this->pdo->prepare("DELETE FROM records_documentattachment WHERE id = ?")->execute([$att_id]);
                }
            }

            if (!empty($files['name'][0])) {
                // BULLETPROOF DIRECTORY CREATION FOR UPLOAD
                $upload_dir = __DIR__ . '/../uploads/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

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

            $this->pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('EDITED', 'Document details updated by Records Officer.', NOW(), ?, ?)")
                ->execute([$user_id, $doc_id]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function createIncomingDocument($user_id, $data, $files)
    {
        try {
            $this->pdo->beginTransaction();

            $origin_id = $this->findOrCreateOrigin($data['origin_office']);

            $stmtStatus = $this->pdo->query("SELECT id FROM records_status WHERE category = 'FOR-APPROVAL' LIMIT 1");
            $status_id = $stmtStatus->fetchColumn();

            $stmtClass = $this->pdo->prepare("SELECT name FROM records_classification WHERE id = ?");
            $stmtClass->execute([$data['classification']]);
            $class_name = strtoupper(trim($stmtClass->fetchColumn()));

            $prefix = ($class_name === 'EXTERNAL') ? 'EX' : 'IN';
            $current_year = date('y');
            $search_pattern = $prefix . $current_year . '%';

            $stmtLastDoc = $this->pdo->prepare("SELECT dts_no FROM records_document WHERE dts_no LIKE ? ORDER BY dts_no DESC LIMIT 1");
            $stmtLastDoc->execute([$search_pattern]);
            $last_dts = $stmtLastDoc->fetchColumn();
            $next_number = $last_dts ? ((int) substr($last_dts, -6)) + 1 : 1;
            $dts_no = $prefix . $current_year . str_pad($next_number, 6, '0', STR_PAD_LEFT);

            // ==========================================
            // MULTI-RECIPIENT ADDRESS RESOLUTION
            // ==========================================
            $destination_name = 'Unassigned Internal Routing';

            if ($data['route_type'] === 'division' && !empty($data['route_division'])) {
                $stmtDiv = $this->pdo->prepare("SELECT name FROM records_division WHERE id = ?");
                $stmtDiv->execute([$data['route_division']]);
                $res = $stmtDiv->fetchColumn();
                if ($res)
                    $destination_name = $res;
            } elseif ($data['route_type'] === 'group' && !empty($data['route_group'])) {
                $stmtGrp = $this->pdo->prepare("SELECT group_name FROM records_distributiongroup WHERE id = ?");
                $stmtGrp->execute([$data['route_group']]);
                $res = $stmtGrp->fetchColumn();
                if ($res)
                    $destination_name = $res;
            } elseif ($data['route_type'] === 'within_dti' && !empty($data['dti_branch'])) {
                $placeholders = implode(',', array_fill(0, count($data['dti_branch']), '?'));
                $stmt = $this->pdo->prepare("SELECT name FROM records_dtibranch WHERE id IN ($placeholders)");
                $stmt->execute($data['dti_branch']);
                $branch_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $destination_name = implode(', ', $branch_names);
            } elseif ($data['route_type'] === 'outside_dti' && !empty($data['ext_office'])) {
                $first_name = trim($data['ext_office'][0]);
                $count = count($data['ext_office']);
                $destination_name = ($count > 1) ? $first_name . ' and ' . ($count - 1) . ' more' : $first_name;
            }

            $destination_name = trim($destination_name);
            $stmtFindAddr = $this->pdo->prepare("SELECT id FROM records_address WHERE name = ? LIMIT 1");
            $stmtFindAddr->execute([$destination_name]);
            $address_id = $stmtFindAddr->fetchColumn();

            if (!$address_id) {
                $stmtInsAddr = $this->pdo->prepare("INSERT IGNORE INTO records_address (name) VALUES (?)");
                $stmtInsAddr->execute([$destination_name]);
                $stmtFindAddr->execute([$destination_name]);
                $address_id = $stmtFindAddr->fetchColumn();
            }

            // INSERT MAIN DOCUMENT
            $stmt = $this->pdo->prepare("INSERT INTO records_document
                (dts_no, route_type, subject, particulars, due_date, created_at, updated_at, classification_id, document_type_id, status_id, creator_id, signatory_id, origin_id, sender, address_id)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $dts_no,
                $data['route_type'],
                $data['subject'],
                $data['particulars'],
                (!empty($data['due_date']) ? $data['due_date'] : null),
                $data['classification'],
                $data['document_type'],
                $status_id,
                $user_id,
                (!empty($data['signatory']) ? $data['signatory'] : null),
                $origin_id,
                $data['sender_name'],
                $address_id
            ]);
            $document_id = $this->pdo->lastInsertId();

            // INSERT INDIVIDUAL EXTERNAL RECIPIENTS (NEW FEATURE)
            if ($data['route_type'] === 'within_dti') {
                $stmtExt = $this->pdo->prepare("INSERT INTO records_externalrecipient (document_id, type, dtibranch_id, contact_person, notes) VALUES (?, 'within_dti', ?, ?, ?)");
                for ($i = 0; $i < count($data['dti_branch']); $i++) {
                    if (!empty($data['dti_branch'][$i])) {
                        $stmtExt->execute([$document_id, $data['dti_branch'][$i], $data['dti_contact'][$i] ?? null, $data['dti_notes'][$i] ?? null]);
                    }
                }
            } elseif ($data['route_type'] === 'outside_dti') {
                $stmtExt = $this->pdo->prepare("INSERT INTO records_externalrecipient (document_id, type, ext_office, contact_person, notes) VALUES (?, 'outside_dti', ?, ?, ?)");
                for ($i = 0; $i < count($data['ext_office']); $i++) {
                    if (!empty($data['ext_office'][$i])) {
                        $stmtExt->execute([$document_id, $data['ext_office'][$i], $data['out_ext_name'][$i] ?? null, $data['out_notes'][$i] ?? null]);
                    }
                }
            }

            // Internal Route Handling (Division/Group)
            $recipient_ids = [];
            if ($data['route_type'] === 'division' && !empty($data['route_users'])) {
                $recipient_ids = $data['route_users'];
            } elseif ($data['route_type'] === 'group' && !empty($data['route_group'])) {
                $stmtGrpM = $this->pdo->prepare("SELECT user_id FROM records_distributiongroup_members WHERE group_id = ?");
                $stmtGrpM->execute([$data['route_group']]);
                $recipient_ids = $stmtGrpM->fetchAll(PDO::FETCH_COLUMN);
            }

            if (!empty($recipient_ids)) {
                $stmtRecip = $this->pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
                foreach ($recipient_ids as $rid) {
                    $stmtRecip->execute([$document_id, $rid]);
                }
            }

            // Handle Attachments
            if (!empty($files['name'][0])) {
                $upload_dir = __DIR__ . '/../uploads/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_name = time() . '_' . $i . '_' . basename($files['name'][$i]);
                        if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $file_name)) {
                            $this->pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())")->execute([$document_id, 'uploads/' . $file_name]);
                        }
                    }
                }
            }

            $this->pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('ENCODED', 'Incoming external document logged by RO.', NOW(), ?, ?)")->execute([$user_id, $document_id]);

            $this->pdo->commit();
            return $dts_no;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function createDocument($user_id, $data, $files)
    {
        try {
            $this->pdo->beginTransaction();

            $stmtStatus = $this->pdo->query("SELECT id FROM records_status WHERE category = 'FOR-APPROVAL' LIMIT 1");
            $status_id = $stmtStatus->fetchColumn();

            $stmtClass = $this->pdo->prepare("SELECT name FROM records_classification WHERE id = ?");
            $stmtClass->execute([$data['classification']]);
            $class_name = strtoupper(trim($stmtClass->fetchColumn()));

            $prefix = ($class_name === 'EXTERNAL') ? 'EX' : 'IN';
            $year = date('y');
            $pattern = $prefix . $year . '%';

            $stmtLast = $this->pdo->prepare("SELECT dts_no FROM records_document WHERE dts_no LIKE ? ORDER BY dts_no DESC LIMIT 1");
            $stmtLast->execute([$pattern]);
            $last = $stmtLast->fetchColumn();
            $next = $last ? ((int) substr($last, -6)) + 1 : 1;
            $dts_no = $prefix . $year . str_pad($next, 6, '0', STR_PAD_LEFT);

            // ==========================================
            // MULTI-RECIPIENT ADDRESS RESOLUTION
            // ==========================================
            $dest_name = 'Unassigned Internal Routing';

            if ($data['route_type'] === 'division' && !empty($data['route_division'])) {
                $stmt = $this->pdo->prepare("SELECT name FROM records_division WHERE id = ?");
                $stmt->execute([$data['route_division']]);
                $res = $stmt->fetchColumn();
                if ($res)
                    $dest_name = $res;
            } elseif ($data['route_type'] === 'group' && !empty($data['route_group'])) {
                $stmt = $this->pdo->prepare("SELECT group_name FROM records_distributiongroup WHERE id = ?");
                $stmt->execute([$data['route_group']]);
                $res = $stmt->fetchColumn();
                if ($res)
                    $dest_name = $res;
            } elseif ($data['route_type'] === 'within_dti' && !empty($data['dti_branch'])) {
                // Determine Summarized Name
                $placeholders = implode(',', array_fill(0, count($data['dti_branch']), '?'));
                $stmt = $this->pdo->prepare("SELECT name FROM records_dtibranch WHERE id IN ($placeholders)");
                $stmt->execute($data['dti_branch']);
                $branch_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $dest_name = implode(', ', $branch_names);
            } elseif ($data['route_type'] === 'outside_dti' && !empty($data['ext_office'])) {
                // Determine Summarized Name
                $first_name = trim($data['ext_office'][0]);
                $count = count($data['ext_office']);
                $dest_name = ($count > 1) ? $first_name . ' and ' . ($count - 1) . ' more' : $first_name;
            }

            $dest_name = trim($dest_name);
            $stmtFindAddr = $this->pdo->prepare("SELECT id FROM records_address WHERE name = ? LIMIT 1");
            $stmtFindAddr->execute([$dest_name]);
            $address_id = $stmtFindAddr->fetchColumn();

            if (!$address_id) {
                $stmtInsAddr = $this->pdo->prepare("INSERT IGNORE INTO records_address (name) VALUES (?)");
                $stmtInsAddr->execute([$dest_name]);
                $stmtFindAddr->execute([$dest_name]);
                $address_id = $stmtFindAddr->fetchColumn();
            }

            // INSERT MAIN DOCUMENT
            $stmtDoc = $this->pdo->prepare("INSERT INTO records_document
                (dts_no, route_type, subject, particulars, due_date, created_at, updated_at,
                 classification_id, document_type_id, status_id, creator_id, signatory_id, address_id)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?)");

            $stmtDoc->execute([
                $dts_no,
                $data['route_type'],
                $data['subject'],
                $data['particulars'],
                (!empty($data['due_date']) ? $data['due_date'] : null),
                $data['classification'],
                $data['document_type'],
                $status_id,
                $user_id,
                (!empty($data['signatory']) ? $data['signatory'] : null),
                $address_id
            ]);
            $document_id = $this->pdo->lastInsertId();

            // INSERT INDIVIDUAL EXTERNAL RECIPIENTS (THE NEW FEATURE)
            if ($data['route_type'] === 'within_dti') {
                $stmtExt = $this->pdo->prepare("INSERT INTO records_externalrecipient (document_id, type, dtibranch_id, contact_person, notes) VALUES (?, 'within_dti', ?, ?, ?)");
                for ($i = 0; $i < count($data['dti_branch']); $i++) {
                    if (!empty($data['dti_branch'][$i])) {
                        $stmtExt->execute([$document_id, $data['dti_branch'][$i], $data['dti_contact'][$i] ?? null, $data['dti_notes'][$i] ?? null]);
                    }
                }
            } elseif ($data['route_type'] === 'outside_dti') {
                $stmtExt = $this->pdo->prepare("INSERT INTO records_externalrecipient (document_id, type, ext_office, contact_person, notes) VALUES (?, 'outside_dti', ?, ?, ?)");
                for ($i = 0; $i < count($data['ext_office']); $i++) {
                    if (!empty($data['ext_office'][$i])) {
                        $stmtExt->execute([$document_id, $data['ext_office'][$i], $data['out_ext_name'][$i] ?? null, $data['out_notes'][$i] ?? null]);
                    }
                }
            }

            // Internal Route Handling (Division/Group)
            $recipients = [];
            if ($data['route_type'] === 'division' && !empty($data['route_users'])) {
                $recipients = (array) $data['route_users'];
            } elseif ($data['route_type'] === 'group' && !empty($data['route_group'])) {
                $stmtGrp = $this->pdo->prepare("SELECT user_id FROM records_distributiongroup_members WHERE group_id = ?");
                $stmtGrp->execute([$data['route_group']]);
                $recipients = $stmtGrp->fetchAll(PDO::FETCH_COLUMN);
            }

            // ADDED: Logic to include the Receiving Officer if provided
            if (!empty($data['receiving_officer'])) {
                $recipients[] = $data['receiving_officer'];
            }

            if (!empty($recipients)) {
                $recipients = array_unique($recipients); // Prevent duplicates
                $stmtRec = $this->pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
                foreach ($recipients as $rid) {
                    $stmtRec->execute([$document_id, $rid]);
                }
            }

            // Handle Attachments
            if (!empty($files['name'][0])) {
                $upload_dir = __DIR__ . '/../uploads/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $fname = time() . '_' . $i . '_' . basename($files['name'][$i]);
                        if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $fname)) {
                            $this->pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())")->execute([$document_id, 'uploads/' . $fname]);
                        }
                    }
                }
            }

            $this->pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('CREATED', 'Outgoing document drafted and routed.', NOW(), ?, ?)")->execute([$user_id, $document_id]);

            $this->pdo->commit();
            return $dts_no;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getOnMyDeskPaginated($user_id, $limit, $offset)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.particulars, d.due_date, d.created_at, d.sender,
                   c.name as classification, t.name as doc_type,
                   s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
                   sig.first_name as s_fname, sig.last_name as s_lname,
                   addr.name as address_name, orig.name as origin_name,
                   p.role as c_role /* <-- ADDED THIS LINE */
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
            WHERE dr.recipient_user_id = :uid
              AND s.category = 'APPROVED'
              AND dr.has_received = 0
            ORDER BY (d.due_date IS NULL) ASC, d.due_date ASC, d.created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getOnMyDeskTotalCount($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(d.id)
            FROM records_documentrecipient dr
            JOIN records_document d ON dr.document_id = d.id
            LEFT JOIN records_status s ON d.status_id = s.id
            WHERE dr.recipient_user_id = ?
              /* FIXED: Must match the filters in getOnMyDeskPaginated */
              AND s.category = 'APPROVED'
              AND dr.has_received = 0
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getActiveOutgoingPaginated($user_id, $limit, $offset)
    {
        $stmt = $this->pdo->prepare("
        SELECT d.*,
               s.name as status_name, s.category as status_category,
               sig.first_name as sig_fname, sig.last_name as sig_lname,
               c.name as class_name, addr.name as address_name,
               /* NEW: Fetch external contact persons from the external recipients table */
               (SELECT GROUP_CONCAT(contact_person SEPARATOR ', ')
                FROM records_externalrecipient
                WHERE document_id = d.id AND contact_person IS NOT NULL AND contact_person != '') as external_names
        FROM records_document d
        LEFT JOIN records_status s ON d.status_id = s.id
        LEFT JOIN auth_user sig ON d.signatory_id = sig.id
        LEFT JOIN records_classification c ON d.classification_id = c.id
        LEFT JOIN records_address addr ON d.address_id = addr.id
        WHERE d.creator_id = :uid AND s.category IN ('ONGOING', 'FOR-APPROVAL', 'APPROVED', 'REJECTED')
        ORDER BY (d.due_date IS NULL) ASC, d.due_date ASC, d.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getActiveOutgoingTotalCount($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(d.id) FROM records_document d
            LEFT JOIN records_status s ON d.status_id = s.id
            WHERE d.creator_id = ? AND s.category IN ('ONGOING', 'FOR-APPROVAL', 'APPROVED', 'REJECTED')
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getUserHistoryPaginated($user_id, $limit, $offset)
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT d.id, d.dts_no, d.subject, d.created_at, d.updated_at, d.due_date, d.sender,
                   c.name as classification, t.name as doc_type, s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   addr.name as address_name, orig.name as origin_name, dr.received_at,
                   CASE WHEN d.creator_id = :uid THEN 'Outgoing' ELSE 'Incoming' END as doc_direction
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
            WHERE (d.creator_id = :uid OR dr.recipient_user_id = :uid)
              AND s.category IN ('CLOSED', 'CANCELLED')
              AND UPPER(s.name) != 'REJECTED'
            ORDER BY d.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUserHistoryTotalCount($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM records_document d
            LEFT JOIN records_documentrecipient dr ON d.id = dr.document_id AND dr.recipient_user_id = :uid
            JOIN records_status s ON d.status_id = s.id
            WHERE (d.creator_id = :uid OR dr.recipient_user_id = :uid)
              AND s.category IN ('CLOSED', 'CANCELLED')
              AND UPPER(s.name) != 'REJECTED'
        ");
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getEncodedIncomingTotalCount($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(d.id)
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            WHERE d.creator_id = ? AND s.category IN ('FOR-APPROVAL', 'ONGOING', 'APPROVED', 'REJECTED')
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getEncodedIncomingPaginated($user_id, $limit, $offset)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.due_date, d.created_at, d.sender, orig.name as origin_name,
                   s.name as status_name, s.category as status_category,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   c.name as classification, t.name as doc_type,
                   addr.name as address_name, /* Changed from destination_name to address_name for consistency */
                   (SELECT GROUP_CONCAT(CONCAT(ru.first_name, ' ', ru.last_name) SEPARATOR ', ')
                    FROM records_documentrecipient dr2
                    LEFT JOIN auth_user ru ON dr2.recipient_user_id = ru.id
                    WHERE dr2.document_id = d.id) as receiver_name
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_origin orig ON d.origin_id = orig.id
            LEFT JOIN records_address addr ON d.address_id = addr.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            LEFT JOIN records_classification c ON d.classification_id = c.id
            LEFT JOIN records_documenttype t ON d.document_type_id = t.id
            WHERE d.creator_id = :uid AND s.category IN ('FOR-APPROVAL', 'ONGOING', 'APPROVED', 'REJECTED')
            /* PRIORITY SORTING */
            ORDER BY (d.due_date IS NULL) ASC, d.due_date ASC, d.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }


    // --- ADD THESE FUNCTIONS TO YOUR DOCUMENTMANAGER CLASS ---

    public function getDocumentRecipientsList($document_id)
    {
        $stmt = $this->pdo->prepare("SELECT recipient_user_id FROM records_documentrecipient WHERE document_id = ?");
        $stmt->execute([$document_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDocumentTrackingHistory($document_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT th.*, u.first_name, u.last_name, p.role, divi.name as division_name
            FROM records_trackinghistory th
            LEFT JOIN auth_user u ON th.acted_by_id = u.id
            LEFT JOIN records_userprofile p ON u.id = p.user_id
            LEFT JOIN records_division divi ON p.division_id = divi.id
            WHERE th.document_id = ?
            ORDER BY th.timestamp DESC
        ");
        $stmt->execute([$document_id]);
        return $stmt->fetchAll();
    }

    public function getUserDivisionId($user_id)
    {
        $stmt = $this->pdo->prepare("SELECT division_id FROM records_userprofile WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    public function getOriginNames()
    {
        return $this->pdo->query("SELECT name FROM records_origin ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getRejectReason($doc_id)
    {
        $stmt = $this->pdo->prepare("SELECT remarks FROM records_trackinghistory WHERE document_id = ? AND action_taken = 'REJECTED' ORDER BY timestamp DESC LIMIT 1");
        $stmt->execute([$doc_id]);
        return $stmt->fetchColumn();
    }

    public function reuseDocument($original_doc_id, $user_id)
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Fetch only the core metadata from the original document
            $stmt = $this->pdo->prepare("
            SELECT d.*, c.name as class_name
            FROM records_document d
            LEFT JOIN records_classification c ON d.classification_id = c.id
            WHERE d.id = ?
        ");
            $stmt->execute([$original_doc_id]);
            $orig = $stmt->fetch();

            if (!$orig)
                throw new Exception("Original document not found.");

            // 2. Generate Brand New DTS Number
            $prefix = (strtoupper(trim($orig['class_name'])) === 'EXTERNAL') ? 'EX' : 'IN';
            $year = date('y');
            $pattern = $prefix . $year . '%';

            $stmtLast = $this->pdo->prepare("SELECT dts_no FROM records_document WHERE dts_no LIKE ? ORDER BY dts_no DESC LIMIT 1");
            $stmtLast->execute([$pattern]);
            $last = $stmtLast->fetchColumn();
            $next = $last ? ((int) substr($last, -6)) + 1 : 1;
            $dts_no = $prefix . $year . str_pad($next, 6, '0', STR_PAD_LEFT);

            // 3. Get the "FOR-APPROVAL" status ID (Standard for new drafts)
            $stmtStatus = $this->pdo->prepare("SELECT id FROM records_status WHERE category = 'FOR-APPROVAL' LIMIT 1");
            $stmtStatus->execute();
            $status_id = $stmtStatus->fetchColumn();

            // 4. Insert cloned document as a FRESH record
            // Note: created_at/updated_at are NOW(), creator is current user, status is reset
            $stmtInsert = $this->pdo->prepare("INSERT INTO records_document
            (dts_no, route_type, subject, particulars, due_date, created_at, updated_at,
             classification_id, document_type_id, status_id, creator_id, signatory_id,
             origin_id, address_id, sender)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmtInsert->execute([
                $dts_no,
                $orig['route_type'],
                $orig['subject'],
                $orig['particulars'],
                $orig['due_date'],
                $orig['classification_id'],
                $orig['document_type_id'],
                $status_id,
                $user_id, // Logged in user becomes the new creator
                $orig['signatory_id'],
                $orig['origin_id'],
                $orig['address_id'],
                $orig['sender']
            ]);
            $new_doc_id = $this->pdo->lastInsertId();

            // 5. Clone Recipients (Resetting has_received to 0)
            $stmtRec = $this->pdo->prepare("SELECT recipient_user_id FROM records_documentrecipient WHERE document_id = ?");
            $stmtRec->execute([$original_doc_id]);
            $recipients = $stmtRec->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($recipients)) {
                $stmtInsRec = $this->pdo->prepare("INSERT INTO records_documentrecipient (document_id, recipient_user_id, has_received) VALUES (?, ?, 0)");
                foreach ($recipients as $rid) {
                    $stmtInsRec->execute([$new_doc_id, $rid]);
                }
            }

            // 6. Clone Attachments (Physical file duplication)
            $stmtAtt = $this->pdo->prepare("SELECT file_path FROM records_documentattachment WHERE document_id = ?");
            $stmtAtt->execute([$original_doc_id]);
            $attachments = $stmtAtt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($attachments)) {
                $upload_dir = __DIR__ . '/../uploads/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

                $stmtInsAtt = $this->pdo->prepare("INSERT INTO records_documentattachment (document_id, file_path, uploaded_at) VALUES (?, ?, NOW())");

                foreach ($attachments as $old_path) {
                    $old_full_path = __DIR__ . '/../' . $old_path;
                    if (file_exists($old_full_path)) {
                        $new_file_name = time() . '_' . uniqid() . '_reused_' . basename($old_path);
                        if (copy($old_full_path, $upload_dir . $new_file_name)) {
                            $stmtInsAtt->execute([$new_doc_id, 'uploads/' . $new_file_name]);
                        }
                    }
                }
            }

            // 7. Fresh Tracking History entry (Mandatory reference to old DTS)
            $log_remarks = "Document reused from DTS No. " . $orig['dts_no'];
            $this->pdo->prepare("INSERT INTO records_trackinghistory (action_taken, remarks, timestamp, acted_by_id, document_id) VALUES ('ENCODED', ?, NOW(), ?, ?)")
                ->execute([$log_remarks, $user_id, $new_doc_id]);

            $this->pdo->commit();
            return $new_doc_id;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }


    public function getFullOfficeList($doc_id)
    {
        // 1. Check for Internal Recipient Divisions (Fixed table name)
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT divi.name
            FROM records_documentrecipient dr
            JOIN records_userprofile p ON dr.recipient_user_id = p.user_id
            JOIN records_division divi ON p.division_id = divi.id
            WHERE dr.document_id = ?
        ");
        $stmt->execute([$doc_id]);
        $internal = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Check for External Recipient Branches (within_dti)
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT b.name
            FROM records_externalrecipient er
            JOIN records_dtibranch b ON er.dtibranch_id = b.id
            WHERE er.document_id = ? AND er.type = 'within_dti'
        ");
        $stmt->execute([$doc_id]);
        $external_dti = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 3. Check for External Recipient Offices (outside_dti)
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT ext_office
            FROM records_externalrecipient
            WHERE document_id = ? AND type = 'outside_dti'
        ");
        $stmt->execute([$doc_id]);
        $external_out = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $all = array_unique(array_merge($internal, $external_dti, $external_out));
        return !empty($all) ? implode(', ', $all) : null;
    }

    // --- ADMIN: Global History Fetching ---
    public function getGlobalHistoryAll()
    {
        $stmt = $this->pdo->prepare("
        SELECT d.id, d.dts_no, d.subject, d.particulars, d.created_at, d.updated_at, d.due_date, d.sender,
               c.name as classification, t.name as doc_type, s.name as status_name, s.category as status_category,
               u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
               sig.first_name as sig_fname, sig.last_name as sig_lname,
               addr.name as address_name, orig.name as origin_name
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
        WHERE s.category IN ('CLOSED', 'CANCELLED')
        ORDER BY d.updated_at DESC
    ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getGlobalHistoryPaginated($limit, $offset)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.particulars, d.created_at, d.updated_at, d.due_date, d.sender,
                   c.name as classification, t.name as doc_type, s.name as status_name, s.category as status_category,
                   u.first_name as c_fname, u.last_name as c_lname, divi.name as c_division,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   addr.name as address_name, orig.name as origin_name,
                   p.role as creator_role,
                   CASE WHEN p.role = 'RO' THEN 'Incoming' ELSE 'Outgoing' END as doc_direction
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
            WHERE s.category IN ('CLOSED', 'CANCELLED')
            ORDER BY d.updated_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getGlobalHistoryTotalCount()
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(d.id) FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            WHERE s.category IN ('CLOSED', 'CANCELLED')
        ");
        return $stmt->fetchColumn() ?: 0;
    }

    public function getDocumentAttachmentsByCategory($doc_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM records_documentattachment WHERE document_id = ? ORDER BY is_signed ASC, uploaded_at ASC");
        $stmt->execute([$doc_id]);
        $all = $stmt->fetchAll();

        $organized = ['original' => [], 'signed' => []];
        foreach ($all as $att) {
            // We check for a column 'is_signed'. If it doesn't exist in your DB yet,
            // you should add it: ALTER TABLE records_documentattachment ADD is_signed TINYINT(1) DEFAULT 0;
            if (isset($att['is_signed']) && $att['is_signed'] == 1) {
                $organized['signed'][] = $att;
            } else {
                $organized['original'][] = $att;
            }
        }
        return $organized;
    }

    public function getRecordsOfficers()
    {
        return $this->pdo->query("
        SELECT u.id, u.first_name, u.last_name
        FROM auth_user u
        JOIN records_userprofile p ON u.id = p.user_id
        WHERE p.role = 'RO' AND u.is_active = 1
    ")->fetchAll();
    }
}