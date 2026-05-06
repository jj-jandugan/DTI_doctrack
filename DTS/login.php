<?php
// index.php
require_once 'classes/database.php';

// If already logged in, redirect them to the dashboard
if (isset($_SESSION['user_id'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    $base_dir = "/DTS/";
    $absolute_url = $protocol . $domain . $base_dir;

    switch ($_SESSION['role']) {
        case 'RO':
            header("Location: " . $absolute_url . "templates/ro/roDashboard.php");
            break;
        case 'RD':
        case 'ARD':
            header("Location: " . $absolute_url . "templates/signatory/signDashboard.php");
            break;
        case 'Division':
            header("Location: " . $absolute_url . "templates/division/divDashboard.php");
            break;
        case 'Admin':
            header("Location: " . $absolute_url . "templates/admin/adminDashboard.php");
            break;
        default:
            // FAILSAFE: If the session has a bad role, destroy it so you aren't trapped!
            session_unset();
            session_destroy();
            header("Location: index.php");
            exit;
    }
    exit;
}

$error = '';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.password, u.first_name, u.last_name, p.role, p.division_id
                FROM auth_user u
                LEFT JOIN records_userprofile p ON u.id = p.user_id
                WHERE u.username = :username AND u.is_active = 1
            ");

            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            // Check if user exists and password matches
            if ($user && password_verify($password, $user['password'])) {

                // Set Session Variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['division_id'] = $user['division_id'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

                // Trim removes any accidental spaces in the database like "Division "
                $_SESSION['role'] = isset($user['role']) ? trim($user['role']) : 'Unknown';

                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
                $domain = $_SERVER['HTTP_HOST'];
                $base_dir = "/DTS/";
                $absolute_url = $protocol . $domain . $base_dir;

                // Redirect to dashboard
                switch ($_SESSION['role']) {
                    case 'RO':
                        header("Location: " . $absolute_url . "templates/ro/roDashboard.php");
                        break;
                    case 'RD':
                    case 'ARD':
                        header("Location: " . $absolute_url . "templates/signatory/signDashboard.php");
                        break;
                    case 'Division':
                        header("Location: " . $absolute_url . "templates/division/divDashboard.php");
                        break;
                    case 'Admin':
                        header("Location: " . $absolute_url . "templates/admin/adminDashboard.php");
                        break;
                    default:
                        $error = "User role not recognized (" . htmlspecialchars($_SESSION['role']) . "). Contact Administrator.";
                        // Destroy the bad session so they can try again
                        session_unset();
                        session_destroy();
                        break;
                }

                if (empty($error)) {
                    exit;
                }
            } else {
                $error = "Invalid username or password!";
            }
        } catch (PDOException $e) {
            $error = "System error. Please contact the administrator.";
        }
    } else {
        $error = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | doctrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="static/css/login.css">
    <link rel="stylesheet" href="static/css/button.css">
</head>
<body>
    <div class="bg-image"></div>
    <div class="bg-overlay"></div>

    <div class="login-container">
        <div class="login-card">
            <img src="static/images/DTI_logo.png" alt="DTI Logo" class="logo-img">

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= $error ?></div>
            <?php endif; ?>

            <form method="post" action="index.php">
                <div class="mb-3">
                    <input type="text" name="username" class="form-control" placeholder="Enter your username*" required>
                </div>
                <div class="mb-1">
                    <input type="password" name="password" class="form-control" placeholder="Enter your password*" required>
                </div>
                <a href="#" class="forgot-password">Forgot Password</a>
                <button type="submit" class="btn-blue w-100">Log In</button>
            </form>
        </div>
    </div>
</body>
</html>