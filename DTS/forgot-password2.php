<?php
session_start();
require_once "classes/Account.php";

$accountObj = new Account();

$step = 1;
$message = "";
$messageType = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

	if (isset($_POST['request_otp'])) {
		$email = trim($_POST['email']);

		if (empty($email)) {
			$message = "Please enter your email address.";
			$messageType = "error";
		} else {
			if ($accountObj->initiatePasswordReset($email)) {
				$step = 2;
				$message = "✅ An OTP has been sent to <strong>" . htmlspecialchars($email) . "</strong>";
				$messageType = "success";
			} else {
				$message = "❌ Email address not found.";
				$messageType = "error";
			}
		}

	} elseif (isset($_POST['reset_password'])) {
		$email = trim($_POST['email']);
		$otp = trim($_POST['otp']);
		$new_pass = $_POST['new_password'];
		$confirm_pass = $_POST['confirm_password'];

		$step = 2;

		if ($new_pass !== $confirm_pass) {
			$message = "❌ Passwords do not match.";
			$messageType = "error";
		} elseif (strlen($new_pass) < 6) {
			$message = "❌ Password must be at least 6 characters.";
			$messageType = "error";
		} else {
			$result = $accountObj->resetPassword($email, $otp, $new_pass);

			if ($result === true) {
				$step = 3;
				$message = "✅ Password reset successfully!";
				$messageType = "success";
			} else {
				$message = "❌ " . $result;
				$messageType = "error";
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Forgot Password - CCS Clearance</title>
	<link rel="stylesheet" href="assets/css/login_style.css">
	<style>
		.back-link {
			display: block;
			text-align: center;
			margin-top: 15px;
			color: #fff;
			text-decoration: none;
			font-size: 0.9em;
		}

		.back-link:hover {
			text-decoration: underline;
		}

		.alert {
			padding: 10px;
			border-radius: 4px;
			margin-bottom: 15px;
			font-size: 0.9em;
		}

		.alert.error {
			background-color: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}

		.alert.success {
			background-color: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
	</style>
</head>

<body>
	<div class="login-container">
		<div class="logo-container">
			<img src="assets/img/wmsu_logo.png" alt="WMSU Logo">
			<img src="assets/img/ccs_logo.png" alt="CCS Logo">
		</div>
		<h2>Reset Password</h2> <?php if (!empty($message)): ?> <div class="alert <?= $messageType ?>"> <?= $message ?>
			</div> <?php endif; ?> <?php if ($step == 1): ?> <form action="forgot_password.php" method="POST">
				<p style="color:white; font-size:0.9em; margin-bottom:15px; text-align:center;">Enter your email address to
					receive a verification code.</p>
				<div class="form-group">
					<label for="email">Email Address:</label>
					<input type="email" id="email" name="email" required placeholder="Enter your registered email">
				</div>
				<button type="submit" name="request_otp" class="login-button">Send OTP</button>
			</form> <?php elseif ($step == 2): ?> <form action="forgot_password.php" method="POST">
				<input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
				<div class="form-group">
					<label for="otp">Enter 6-Digit OTP:</label>
					<input type="text" id="otp" name="otp" required pattern="[0-9]{6}" maxlength="6" placeholder="123456"
						autocomplete="off">
				</div>
				<div class="form-group">
					<label for="new_password">New Password:</label>
					<input type="password" id="new_password" name="new_password" required>
				</div>
				<div class="form-group">
					<label for="confirm_password">Confirm New Password:</label>
					<input type="password" id="confirm_password" name="confirm_password" required>
				</div>
				<button type="submit" name="reset_password" class="login-button">Reset Password</button>
			</form> <?php elseif ($step == 3): ?> <div style="text-align: center;">
				<p style="color: white; margin-bottom: 20px;">Your password has been updated successfully.</p>
				<a href="index.php" class="login-button"
					style="text-decoration:none; display:inline-block; line-height: 40px;">Return to Login</a>
			</div> <?php endif; ?> <?php if ($step != 3): ?> <a href="index.php" class="back-link">Back to Login</a>
		<?php endif; ?>
	</div>
</body>

</html>