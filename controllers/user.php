<?php
// controllers/user.php
session_start();
require_once '../classes/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle Forced Credential Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'force_update_credentials') {
    // Determine the current "must change" state from the session
    $current_state = $_SESSION['must_change_password'] ?? 0;

    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Validate Passwords (Required for both states)
    if ($new_password !== $confirm_password) {
        $_SESSION['cred_error'] = "Passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['cred_error'] = "Password must be at least 6 characters.";
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);

            if ($current_state == 1) {
                // CASE 1: New User - Must update BOTH Username and Password
                $new_username = trim($_POST['new_username'] ?? '');

                if (empty($new_username)) {
                    $_SESSION['cred_error'] = "Username cannot be empty.";
                } else {
                    $stmt = $pdo->prepare("UPDATE auth_user SET username = ?, password = ?, must_change_password = 0 WHERE id = ?");
                    $stmt->execute([$new_username, $hashed, $user_id]);

                    $_SESSION['cred_success'] = "Credentials updated successfully!";
                    $_SESSION['must_change_password'] = 0; // Clear the session flag
                }
            } elseif ($current_state == 2) {
                // CASE 2: Password Reset - Only update Password
                $stmt = $pdo->prepare("UPDATE auth_user SET password = ?, must_change_password = 0 WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);

                $_SESSION['cred_success'] = "Password updated successfully!";
                $_SESSION['must_change_password'] = 0; // Clear the session flag
            }

        } catch (PDOException $e) {
            // Handle duplicate username error
            $_SESSION['cred_error'] = ($e->getCode() == 23000) ? "Username already taken. Please choose another." : "System Error: " . $e->getMessage();
        }
    }

    // Redirect back to the dashboard
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
} else {
    header("Location: ../login.php");
    exit;
}