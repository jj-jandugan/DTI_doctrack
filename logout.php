<?php
// DTS/logout.php
session_start(); // Start session to access it

// 1. Clear all session variables
$_SESSION = array(); //

// 2. Destroy the session cookie in the browser
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/'); //
}

// 3. Destroy the session on the server
session_destroy(); //

// 4. Redirect to login page
header("Location: login.php");
exit();