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
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $age = intval($_POST['age']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $role = sanitize_input($_POST['role']);
    
    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill in all fields';
    } elseif ($age < 1 || $age > 120) {
        $error = 'Please enter a valid age';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!in_array($role, ['student', 'lecturer', 'admin'])) {
        $error = 'Invalid role selected';
    } else {
        $existing_user = get_user_by_email($conn, $email);
        if ($existing_user) {
            $error = 'Email already registered';
        } else {
            // Create new user
            $full_name = $first_name . ' ' . $last_name;
            $password_hash = hash_password($password);
            
            $stmt = $conn->prepare("INSERT INTO user (full_name, email, password_hash, role, age, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("ssssi", $full_name, $email, $password_hash, $role, $age);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! Please wait for admin approval.';
                $first_name = $last_name = $email = $age = $role = '';
            } else {
                $error = 'Registration failed. Please try again.';
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
    <title>Register - EduTrack</title>
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
            padding: 20px 0;
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
            max-height: 90vh;
            overflow-y: auto;
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
            margin-bottom: 15px;
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
                <button class="tab-btn" onclick="window.location.href='login.php'">Sign In</button>
                <button class="tab-btn active" onclick="window.location.href='register.php'">Sign Up</button>
            </div>
            
            <?php if ($error): ?>
                <?php echo show_error($error); ?>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <?php echo show_success($success); ?>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <input type="text" name="first_name" placeholder="First Name" value="<?php echo isset($first_name) ? htmlspecialchars($first_name) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="last_name" placeholder="Last Name" value="<?php echo isset($last_name) ? htmlspecialchars($last_name) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="number" name="age" placeholder="Age" min="1" max="120" value="<?php echo isset($age) ? htmlspecialchars($age) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                
                <div class="form-group">
                    <select name="role" required>
                        <option value="">Select User</option>
                        <option value="student" <?php echo (isset($role) && $role === 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="lecturer" <?php echo (isset($role) && $role === 'lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                        <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <button type="submit" class="submit-btn">Sign Up</button>
            </form>
        </div>
    </div>
</body>
</html>
