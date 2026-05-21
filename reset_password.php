<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$token = '';
$email = '';
$force_reset = false;

// Check if it's a forced password reset
if (isset($_GET['force']) && $_GET['force'] == 1 && is_logged_in()) {
    $force_reset = true;
    $email = $_SESSION['email'];
} else {
    // Get token and email from URL
    if (!isset($_GET['token']) || !isset($_GET['email'])) {
        header('Location: login.php');
        exit;
    }

    $token = $_GET['token'];
    $email = $_GET['email'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        if ($force_reset) {
            // Update password for logged-in user
            if (update_user_password($conn, $_SESSION['userID'], $new_password)) {
                $success = 'Password updated successfully';
                // Redirect to dashboard after 2 seconds
                header("Refresh: 2; url=" . $_SESSION['role'] . "_dashboard.php");
            } else {
                $error = 'Failed to update password';
            }
        } else {
            // Verify reset token
            $reset = verify_reset_token($conn, $email, $token);

            if (!$reset) {
                $error = 'Invalid or expired reset link';
            } else {
                // Get user
                $user = get_user_by_email($conn, $email);

                // Update password
                if (update_user_password($conn, $user['userID'], $new_password)) {
                    // Mark token as used
                    mark_token_used($conn, $reset['passwordresetsID']);

                    $success = 'Password reset successfully. You can now login with your new password.';
                    // Redirect to login after 3 seconds
                    header("Refresh: 3; url=login.php");
                } else {
                    $error = 'Failed to reset password';
                }
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
    <title>Reset Password - EduTrack</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            padding: 50px;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 100px;
            height: 100px;
            margin-bottom: 15px;
        }

        .logo-text h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .logo-text p {
            font-size: 14px;
            color: #34495e;
            font-style: italic;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .description {
            text-align: center;
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 25px;
            background: #26d0ce;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .submit-btn:hover {
            background: #1fb8b6;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #26d0ce;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo-container">
            <img src="assets/logo.png" alt="EduTrack Logo" class="logo" onerror="this.style.display='none'">
            <div class="logo-text">
                <h1>EduTrack</h1>
                <p>Smart Tracking for Smarter Learning</p>
            </div>
        </div>

        <h2>Reset Password</h2>
        <p class="description">Enter your new password below.</p>

        <?php if ($force_reset): ?>
            <div class="warning">
                You are required to reset your password before continuing.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <?php echo show_error($error); ?>
        <?php endif; ?>

        <?php if ($success): ?>
            <?php echo show_success($success); ?>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="password" name="new_password" placeholder="New Password" required>
            </div>

            <div class="form-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>

            <button type="submit" class="submit-btn">Reset Password</button>

            <?php if (!$force_reset): ?>
                <div class="back-link">
                    <a href="login.php">Back to Login</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</body>

</html>