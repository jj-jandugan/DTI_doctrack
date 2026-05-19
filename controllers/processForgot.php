<?php
// controllers/processForgot.php
session_start();
require_once '../classes/database.php';
require_once '../classes/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    $email = trim($_POST['email']);

    // 1. Check if the email exists in the database
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM auth_user WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // SECURITY BEST PRACTICE: We always show the same success message even if the email doesn't exist.
    $_SESSION['success_msg'] = "If that email is registered in our system, we have sent a password reset link.";

    if ($user) {
        // 2. Generate a highly secure, random 64-character token
        $token = bin2hex(random_bytes(32));

        // 3. Set the expiration time to 30 minutes from now
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // 4. Save the token and expiration into the database
        $stmtUpdate = $pdo->prepare("UPDATE auth_user SET reset_token = ?, token_expires = ? WHERE id = ?");
        $stmtUpdate->execute([$token, $expires, $user['id']]);

        // 5. Construct the Reset Link
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $base_dir = dirname($_SERVER['SCRIPT_NAME'], 2);
        $reset_link = $protocol . $host . $base_dir . '/resetPassword.php?token=' . $token;
        $current_year = date('Y');

        // 6. Prepare the Email Content
        $subject = "Password Reset Request - DTI Document Tracking System";
        $recipient_name = $user['first_name'] . ' ' . $user['last_name'];

        // --- NEW PROFESSIONAL EMAIL TEMPLATE ---
        $htmlBody = "
        <div style=\"font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; margin: 0;\">
            <div style=\"max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);\">

                <div style=\"background-color: #1d4ed8; padding: 30px 20px; text-align: center;\">
                    <h1 style=\"color: #ffffff; margin: 0; font-size: 22px; letter-spacing: 1px;\">DTI Document Tracking System</h1>
                </div>

                <div style=\"padding: 40px 30px;\">
                    <h2 style=\"color: #1e293b; font-size: 20px; margin-top: 0; margin-bottom: 20px;\">Password Reset Request</h2>

                    <p style=\"color: #334155; font-size: 16px; line-height: 1.6; margin-bottom: 20px;\">
                        Hello <strong style=\"color: #0f172a;\">{$user['first_name']}</strong>,
                    </p>

                    <p style=\"color: #334155; font-size: 16px; line-height: 1.6; margin-bottom: 30px;\">
                        We received a request to reset the password associated with your account. Click the button below to choose a new secure password. For your safety, this link will expire in <strong>30 minutes</strong>.
                    </p>

                    <div style=\"text-align: center; margin-bottom: 35px;\">
                        <a href=\"{$reset_link}\" style=\"background-color: #10b981; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;\">Reset My Password</a>
                    </div>

                    <div style=\"background-color: #f1f5f9; padding: 15px; border-radius: 6px; margin-bottom: 30px; text-align: center;\">
                        <p style=\"color: #64748b; font-size: 13px; margin: 0 0 8px 0;\">If the button above doesn't work, copy and paste this link into your browser:</p>
                        <a href=\"{$reset_link}\" style=\"color: #1d4ed8; font-size: 13px; word-break: break-all; text-decoration: none;\">{$reset_link}</a>
                    </div>

                    <p style=\"color: #64748b; font-size: 14px; line-height: 1.5; margin: 0;\">
                        If you did not request a password reset, please ignore this email or contact your system administrator. Your current password will remain unchanged.
                    </p>
                </div>

                <div style=\"background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\">
                    <p style=\"color: #94a3b8; font-size: 12px; margin: 0; line-height: 1.5;\">
                        &copy; {$current_year} Department of Trade and Industry. All rights reserved.<br>
                        This is an automated system message, please do not reply directly to this email.
                    </p>
                </div>

            </div>
        </div>
        ";

        // 7. Send the Email!
        $mailer = new Mailer();
        $mailer->sendEmail($email, $recipient_name, $subject, $htmlBody);
    }

    // 8. Redirect back to the form
    header("Location: ../forgotPassword.php");
    exit;
} else {
    header("Location: ../login.php");
    exit;
}
?>