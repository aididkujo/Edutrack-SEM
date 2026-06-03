<?php
// Helper Functions for EduTrack System

/**
 * Check if user is logged in
 */
function is_logged_in()
{
    return isset($_SESSION['userID']) && isset($_SESSION['role']);
}

/**
 * Check if user has specific role
 */
function has_role($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Prevent browser caching of pages
 */
function prevent_cache()
{
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
}

/**
 * Require login - redirect to login page if not logged in
 */
function require_login()
{
    prevent_cache(); // Prevent caching of authenticated pages
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require specific role
 */
function require_role($role)
{
    require_login();
    if (!has_role($role)) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Sanitize input data
 */
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email format
 */
function validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Hash password using bcrypt
 */
function hash_password($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password
 */
function verify_password($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Generate random token
 */
function generate_token($length = 64)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get user by ID
 */
function get_user_by_id($conn, $userID)
{
    $stmt = $conn->prepare("SELECT * FROM user WHERE userID = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get user by email
 */
function get_user_by_email($conn, $email)
{
    $stmt = $conn->prepare("SELECT * FROM user WHERE email = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Update last login timestamp
 */
function update_last_login($conn, $userID)
{
    $stmt = $conn->prepare("UPDATE user SET last_login_at = NOW() WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    return $stmt->execute();
}

/**
 * Redirect based on role
 */
function redirect_by_role($role)
{
    switch ($role) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'lecturer':
            header('Location: lecturer_dashboard.php');
            break;
        case 'student':
            header('Location: student_dashboard.php');
            break;
        default:
            header('Location: login.php');
            break;
    }
    exit;
}

/**
 * Display error message
 */
function show_error($message)
{
    return '<div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px;">' . htmlspecialchars($message) . '</div>';
}

/**
 * Display success message
 */
function show_success($message)
{
    return '<div class="success-message" style="background-color: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border: 1px solid #c3e6cb; border-radius: 4px;">' . htmlspecialchars($message) . '</div>';
}

/**
 * Get all pending users for admin approval
 */
function get_pending_users($conn)
{
    $stmt = $conn->prepare("SELECT * FROM user WHERE status = 'pending' AND deleted_at IS NULL ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all active users
 */
function get_all_users($conn)
{
    $stmt = $conn->prepare("SELECT * FROM user WHERE deleted_at IS NULL ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Approve user
 */
function approve_user($conn, $userID)
{
    $stmt = $conn->prepare("UPDATE user SET status = 'active', approved_at = NOW() WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    return $stmt->execute();
}

/**
 * Reject user
 */
function reject_user($conn, $userID)
{
    $stmt = $conn->prepare("UPDATE user SET status = 'rejected' WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    return $stmt->execute();
}

/**
 * Soft delete user
 */
function delete_user($conn, $userID)
{
    $stmt = $conn->prepare("UPDATE user SET deleted_at = NOW() WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    return $stmt->execute();
}

/**
 * Update user profile
 */
function update_user_profile($conn, $userID, $full_name, $email, $age, $profile_picture = null)
{
    if ($profile_picture !== null) {
        $stmt = $conn->prepare("UPDATE user SET full_name = ?, email = ?, age = ?, profile_picture = ? WHERE userID = ?");
        $stmt->bind_param("ssisi", $full_name, $email, $age, $profile_picture, $userID);
    } else {
        $stmt = $conn->prepare("UPDATE user SET full_name = ?, email = ?, age = ? WHERE userID = ?");
        $stmt->bind_param("ssii", $full_name, $email, $age, $userID);
    }
    return $stmt->execute();
}

/**
 * Upload profile picture
 */
function upload_profile_picture($file)
{
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }

    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size must be less than 5MB'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . uniqid() . '_' . time() . '.' . $extension;
    $upload_dir = __DIR__ . '/../uploads/profile_pictures/';
    $upload_path = $upload_dir . $filename;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => 'uploads/profile_pictures/' . $filename];
    }

    return ['success' => false, 'error' => 'Failed to upload file'];
}

/**
 * Update user password
 */
function update_user_password($conn, $userID, $new_password)
{
    $password_hash = hash_password($new_password);
    $stmt = $conn->prepare("UPDATE user SET password_hash = ?, force_password_reset = 0 WHERE userID = ?");
    $stmt->bind_param("si", $password_hash, $userID);
    return $stmt->execute();
}

/**
 * Send password reset email using PHPMailer
 */
function send_reset_email($email, $token)
{
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/email_config.php';

    $reset_link = SITE_URL . "/reset_password.php?token=" . $token . "&email=" . urlencode($email);

    // Create PHPMailer instance
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->SMTPDebug = 0; // Set to 0 for production (no debug output)
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = !empty(SMTP_USERNAME);
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // For local development without authentication
        if (SMTP_HOST === 'localhost') {
            $mail->SMTPAuth = false;
            $mail->SMTPAutoTLS = false;
        } else {
            // For Gmail
            $mail->SMTPAuth = true;
        }

        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - EduTrack';
        $mail->Body    = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 15px 30px; background: #26d0ce; color: white; text-decoration: none; border-radius: 25px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #777; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>EduTrack</h1>
                    <p>Smart Tracking for Smarter Learning</p>
                </div>
                <div class="content">
                    <h2>Password Reset Request</h2>
                    <p>You have requested to reset your password. Click the button below to reset it:</p>
                    <p style="text-align: center;">
                        <a href="' . $reset_link . '" class="button">Reset Password</a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style="word-break: break-all; color: #26d0ce;">' . $reset_link . '</p>
                    <p><strong>This link will expire in 1 hour.</strong></p>
                    <p>If you did not request a password reset, please ignore this email.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' EduTrack. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';

        $mail->AltBody = "Password Reset Request\n\n" .
            "Click the following link to reset your password:\n" .
            $reset_link . "\n\n" .
            "This link expires in 1 hour.\n\n" .
            "If you did not request a password reset, please ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Send approval message through email
function send_approval_email($email, $name)
{
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/email_config.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = !empty(SMTP_USERNAME);
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Account Approved - EduTrack';

        $mail->Body = "
            <h2>Welcome {$name}</h2>
            <p>Your account has been approved.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log($mail->ErrorInfo);
        return false;
    }
}

// Send rejection through email
function send_rejection_email($email, $name)
{
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/email_config.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = !empty(SMTP_USERNAME);
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Account Rejected - EduTrack';

        $mail->Body = "
            <h2>Hello {$name}</h2>
            <p>Unfortunately your account was not approved.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log($mail->ErrorInfo);
        return false;
    }
}

/**
 * Create password reset token
 */
function create_reset_token($conn, $userID, $token)
{
    // Hash the token before storing
    $token_hash = hash_password($token);

    // Increment force_password_reset counter to track how many times user forgot password
    $conn->query("UPDATE user SET force_password_reset = force_password_reset + 1 WHERE userID = $userID");

    // Set expiry to 1 hour from now
    $stmt = $conn->prepare("INSERT INTO password_resets (userID, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    $stmt->bind_param("is", $userID, $token_hash);
    return $stmt->execute();
}

/**
 * Verify password reset token
 */
function verify_reset_token($conn, $email, $token)
{
    $user = get_user_by_email($conn, $email);
    if (!$user) {
        return false;
    }

    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE userID = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $user['userID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $reset = $result->fetch_assoc();

    if (!$reset) {
        return false;
    }

    // Verify token hash
    if (verify_password($token, $reset['token_hash'])) {
        return $reset;
    }

    return false;
}

/**
 * Mark reset token as used
 */
function mark_token_used($conn, $passwordresetsID)
{
    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE passwordresetsID = ?");
    $stmt->bind_param("i", $passwordresetsID);
    return $stmt->execute();
}
