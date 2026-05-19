<?php
// controllers/updatePassword.php
session_start();
require_once '../classes/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'], $_POST['new_password'], $_POST['confirm_password'])) {

    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Double check passwords match on the server
    if ($new_password !== $confirm_password) {
        $_SESSION['error_msg'] = "Passwords do not match. Please try again.";
        header("Location: ../resetPassword.php?token=" . urlencode($token));
        exit;
    }

    // 2. Validate token again to prevent hacking attempts
    $stmt = $pdo->prepare("SELECT id FROM auth_user WHERE reset_token = ? AND token_expires > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // 3. Hash the new password using PHP's strong default hashing
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // 4. Update the password AND wipe the token columns clean!
        $stmtUpdate = $pdo->prepare("
            UPDATE auth_user
            SET password = ?, reset_token = NULL, token_expires = NULL
            WHERE id = ?
        ");
        $stmtUpdate->execute([$hashed_password, $user['id']]);

        // 5. Success! Redirect to login page
        $_SESSION['success_msg'] = "Your password has been successfully reset! You can now log in.";
        header("Location: ../login.php");
        exit;
    } else {
        // Token was invalid or expired while they were typing
        $_SESSION['error_msg'] = "Your session expired. Please request a new link.";
        header("Location: ../forgotPassword.php");
        exit;
    }

} else {
    header("Location: ../login.php");
    exit;
}
?>