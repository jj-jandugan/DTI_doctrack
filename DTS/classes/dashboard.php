<?php
// classes/Dashboard.php

class Dashboard {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getIncomingCount($user_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM records_documentrecipient WHERE recipient_user_id = ? AND has_received = 0");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    public function getOnHandCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM records_documentrecipient dr
            JOIN records_document d ON dr.document_id = d.id
            JOIN records_status s ON d.status_id = s.id
            WHERE dr.recipient_user_id = ? AND dr.has_received = 1 AND s.category != 'CLOSED'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    public function getApprovalCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM records_documentrecipient dr
            JOIN records_document d ON dr.document_id = d.id
            JOIN records_status s ON d.status_id = s.id
            WHERE dr.recipient_user_id = ? AND s.category = 'FOR-APPROVAL'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    public function getClosedCount($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM records_documentrecipient dr
            JOIN records_document d ON dr.document_id = d.id
            JOIN records_status s ON d.status_id = s.id
            WHERE dr.recipient_user_id = ? AND s.category = 'CLOSED'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    // We can also move the table data fetch here to make the template even cleaner!
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
            WHERE dr.recipient_user_id = ? AND dr.has_received = 0
            ORDER BY d.updated_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
}
?>