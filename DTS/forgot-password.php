<?php
require_once 'classes/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim($_POST['email'] ?? '');

	if (!empty($username)) {
		try {
			$stmt = $pdo->prepare("
                SELECT u.id, u.password, u.first_name, u.last_name, p.role, p.division_id
                FROM auth_user u
                LEFT JOIN records_userprofile p ON u.id = p.user_id
                WHERE u.username = :username AND u.is_active = 1
            ");

			$stmt->execute(['username' => $username]);
			$user = $stmt->fetch();

			if ($user) {

				if (empty($error)) {
					exit;
				}
			} else {
				$error = "Invalid email!";
			}
		} catch (PDOException $e) {
			$error = "System error. Please contact the administrator.";
		}
	} else {
		$error = "Please your email.";
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Forgot Password | doctrack</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="static/css/login.css">
	<link rel="stylesheet" href="static/css/button.css">
</head>

<body>
	<div class="bg-image"></div>
	<div class="bg-overlay"></div>
	<div class="login-container">
		<div class="login-card">
			<img src="static/images/DTI_logo.png" alt="DTI Logo" class="logo-img"> <?php if ($error): ?>
				<div class="alert alert-danger py-2"><?= $error ?></div> <?php endif; ?>
			<form method="post" action="">
				<div class="mb-3">
					<input type="email" name="email" class="form-control" placeholder="Enter your email*" required>
				</div>
				<button type="submit" class="btn-blue w-100">Reset password</button>
			</form>
		</div>
	</div>
</body>

</html>