<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);

    // Validate email
    if (empty($email)) {
        $error = 'Please enter your email';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } else {
        // Check if user exists
        $user = get_user_by_email($conn, $email);

        if ($user) {
            // Generate reset token
            $token = generate_token(64);

            // Create reset token in database
            if (create_reset_token($conn, $user['userID'], $token)) {
                // Send reset email
                if (send_reset_email($email, $token)) {
                    $success = 'Password reset link has been sent to your email. Please check your inbox.';
                } else {
                    $error = 'Failed to send reset email. Please try again or contact support.';
                }
            } else {
                $error = 'Failed to create reset token. Please try again.';
            }
        } else {
            // Don't reveal if email exists or not for security
            $success = 'If that email exists, a password reset link has been sent';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - EduTrack</title>
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

        <h2>Forgot Password</h2>
        <p class="description">Enter your email address and we'll send you a link to reset your password.</p>

        <?php if ($error): ?>
            <?php echo show_error($error); ?>
        <?php endif; ?>

        <?php if ($success): ?>
            <?php echo show_success($success); ?>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>

            <button type="submit" class="submit-btn">Send Reset Link</button>

            <div class="back-link">
                <a href="login.php">Back to Login</a>
            </div>
        </form>
    </div>
</body>

</html>