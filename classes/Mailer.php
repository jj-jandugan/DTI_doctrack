<?php
// classes/Mailer.php

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load the PHPMailer files we added earlier
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

class Mailer {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);

        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = 'dti.dts.system@gmail.com'; // Your official system email

            // YOUR 16-DIGIT APP PASSWORD GOES HERE (No spaces)
            $this->mail->Password   = 'apcy owgs okxl rhmy';

            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = 465;

            // Set the "From" address
            $this->mail->setFrom('dti.dts.system@gmail.com', 'DTI Document Tracking System');

        } catch (Exception $e) {
            error_log("Mailer Initialization Error: {$this->mail->ErrorInfo}");
        }
    }

    /**
     * Reusable function to send any email
     */
    public function sendEmail($toEmail, $toName, $subject, $htmlBody) {
        try {
            // Clear previous recipients just in case
            $this->mail->clearAllRecipients();

            // Add the recipient
            $this->mail->addAddress($toEmail, $toName);

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $htmlBody;

            // Optional: Plain text fallback for older email clients
            $this->mail->AltBody = strip_tags($htmlBody);

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed to {$toEmail}. Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}
?>