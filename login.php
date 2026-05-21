<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect_by_role($_SESSION['role']);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $role = sanitize_input($_POST['role']);
    
    if (empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill in all fields';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } else {
        $user = get_user_by_email($conn, $email);
        
        if (!$user) {
            $error = 'Invalid email or password';
        } elseif ($user['role'] !== $role) {
            $error = 'Invalid role selected';
        } elseif ($user['status'] !== 'active') {
            if ($user['status'] === 'pending') {
                $error = 'Your account is pending admin approval';
            } elseif ($user['status'] === 'rejected') {
                $error = 'Your account has been rejected';
            } elseif ($user['status'] === 'deactivated') {
                $error = 'Your account has been deactivated';
            }
        } elseif (!verify_password($password, $user['password_hash'])) {
            $error = 'Invalid email or password';
        } else {
           
            session_regenerate_id(true);
            
            $_SESSION['userID'] = $user['userID'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['login_success'] = true;
            
            update_last_login($conn, $user['userID']);
            
            if ($user['force_password_reset'] > 0) {
                header('Location: reset_password.php?force=1');
                exit;
            }
            
            redirect_by_role($user['role']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EduTrack</title>
    <link rel="stylesheet" href="style.css">
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
            display: flex;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            max-width: 900px;
            width: 90%;
        }
        
        .logo-section {
            background: linear-gradient(135deg, #b8d4d4 0%, #a0c5c5 100%);
            padding: 60px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 40%;
        }
        
        .logo {
            width: 150px;
            height: 150px;
            margin-bottom: 20px;
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
        
        .form-section {
            background: #2dd4d2;
            padding: 60px;
            width: 60%;
        }
        
        .tab-buttons {
            display: flex;
            margin-bottom: 30px;
            gap: 10px;
        }
        
        .tab-btn {
            flex: 1;
            padding: 12px;
            border: none;
            background: rgba(255,255,255,0.3);
            color: white;
            font-size: 16px;
            cursor: pointer;
            border-radius: 25px;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: #1f2937;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            background: white;
        }
        
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 25px;
            background: #1f2937;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .submit-btn:hover {
            background: #374151;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }
        
        .forgot-password a {
            color: #1f2937;
            text-decoration: none;
            font-size: 14px;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .logo-section,
            .form-section {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <img src="assets/logoedutrack.png" alt="EduTrack Logo" class="logo">
            <div class="logo-text">
                <h1>EduTrack</h1>
                <p>Smart Tracking for Smarter Learning</p>
            </div>
        </div>
        
        <div class="form-section">
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="window.location.href='login.php'">Sign In</button>
                <button class="tab-btn" onclick="window.location.href='register.php'">Sign Up</button>
            </div>
            
            <?php if ($error): ?>
                <?php echo show_error($error); ?>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <?php echo show_success($success); ?>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                
                <div class="form-group">
                    <select name="role" required>
                        <option value="">Select User</option>
                        <option value="student">Student</option>
                        <option value="lecturer">Lecturer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <button type="submit" class="submit-btn">Log In</button>
                
                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
