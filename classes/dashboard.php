<?php
// classes/Dashboard.php

class Dashboard {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ==========================================
    // DIVISION DASHBOARD METRICS
    // ==========================================

    public function getStrictUserClosedCount($user_id) {
    $stmt = $this->pdo->prepare("
        SELECT COUNT(DISTINCT d.id)
        FROM records_document d
        JOIN records_status s ON d.status_id = s.id
        JOIN records_trackinghistory th ON d.id = th.document_id
        WHERE th.acted_by_id = ?
          /* Filter ONLY for Closed category */
          AND s.category = 'CLOSED'
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}

    public function getIncomingCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM records_documentrecipient dr
            JOIN records_document d ON dr.document_id = d.id
            JOIN records_status s ON d.status_id = s.id
            WHERE dr.recipient_user_id = ?
              AND dr.has_received = 0
              AND s.category = 'APPROVED'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

   public function getApprovedOutgoingCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            WHERE d.creator_id = ?
              AND (s.name = 'APPROVED' OR s.category = 'APPROVED')
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getApprovalCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            WHERE d.creator_id = ? AND s.category IN ('FOR APPROVAL', 'FOR-APPROVAL')
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getIncomingDocuments($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.dts_no, d.subject, d.due_date, d.updated_at,
                   s.name as status_name, s.category as status_category,
                   a.name as address_name,
                   sig.first_name as sig_fname, sig.last_name as sig_lname,
                   creator.first_name as c_fname, creator.last_name as c_lname,
                   creator_div.name as c_division
            FROM records_documentrecipient dr
            JOIN records_document d ON dr.document_id = d.id
            LEFT JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_address a ON d.address_id = a.id
            LEFT JOIN auth_user sig ON d.signatory_id = sig.id
            LEFT JOIN auth_user creator ON d.creator_id = creator.id
            LEFT JOIN records_userprofile creator_p ON creator.id = creator_p.user_id
            LEFT JOIN records_division creator_div ON creator_p.division_id = creator_div.id
            WHERE dr.recipient_user_id = ?
              AND dr.has_received = 0
              AND s.category = 'APPROVED'
            ORDER BY d.updated_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    // ==========================================
    // RECORDS OFFICER (RO) SPECIFIC METRICS
    // ==========================================

    public function getROIncomingCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            WHERE d.creator_id = ?
              AND s.category IN ('PENDING', 'DRAFT')
              AND d.route_type = 'incoming'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getRODispatchCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            WHERE (s.name = 'APPROVED' OR s.category = 'APPROVED')
              AND d.route_type IN ('outside_dti', 'within_dti')
        ");
        $stmt->execute();
        return $stmt->fetchColumn() ?: 0;
    }

    public function getROOverdueCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            WHERE d.due_date IS NOT NULL
              AND d.due_date < CURDATE()
              AND s.category NOT IN ('CLOSED', 'REJECTED', 'CANCELLED')
              /* Filters for docs the RO created or needs to dispatch */
              AND (d.creator_id = ? OR (s.name = 'APPROVED' AND d.route_type != 'incoming'))
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getROClosedCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            WHERE s.category = 'CLOSED'
              AND (d.creator_id = ? OR d.id IN (SELECT document_id FROM records_trackinghistory WHERE acted_by_id = ?))
        ");
        $stmt->execute([$user_id, $user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    /**
     * MISSING METHOD FIXED:
     * Counts documents for RO based on status category.
     */
    public function getROStatusCount($user_id, $category) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            LEFT JOIN records_trackinghistory th ON d.id = th.document_id
            WHERE s.category = ?
            AND (d.creator_id = ? OR th.acted_by_id = ?)
        ");
        $stmt->execute([$category, $user_id, $user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    // ==========================================
    // SIGNATORY DASHBOARD METRICS
    // ==========================================

    // 1. Incoming - Active incoming docu (Waiting for signature, encoded by RO)
    public function getSignatoryIncomingCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            JOIN auth_user u ON d.creator_id = u.id
            JOIN records_userprofile p ON u.id = p.user_id
            WHERE d.signatory_id = ?
              AND s.category IN ('FOR APPROVAL', 'FOR-APPROVAL')
              AND p.role = 'RO'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    // 2. Outgoing - Active outgoing docu (Waiting for signature, encoded by Divisions)
    public function getSignatoryOutgoingCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            JOIN auth_user u ON d.creator_id = u.id
            JOIN records_userprofile p ON u.id = p.user_id
            WHERE d.signatory_id = ?
              AND s.category IN ('FOR APPROVAL', 'FOR-APPROVAL')
              AND p.role = 'Division'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    // 3. Universal Status Count (Handles Approved, Rejected, and Closed for BOTH incoming & outgoing)
    public function getSignatoryCountByStatus($user_id, $category) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM records_document d
            JOIN records_status s ON d.status_id = s.id
            WHERE d.signatory_id = ?
              AND (s.category = ? OR s.name = ?)
        ");
        // Passed twice to ensure it catches whether your DB uses it as a name or a category
        $stmt->execute([$user_id, $category, $category]);
        return $stmt->fetchColumn() ?: 0;
    }

    // ==========================================
    // SHARED ANALYTICS METRICS
    // ==========================================

    public function getMonthlyVolume($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT MONTH(timestamp) as month_num, COUNT(DISTINCT document_id) as doc_count
            FROM records_trackinghistory
            WHERE acted_by_id = ? AND YEAR(timestamp) = YEAR(CURDATE())
            GROUP BY MONTH(timestamp)
        ");
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = array_fill(0, 12, 0);
        foreach ($data as $row) {
            $counts[(int)$row['month_num'] - 1] = (int)$row['doc_count'];
        }
        return $counts;
    }
}