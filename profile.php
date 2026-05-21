<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

// Require login
require_login();

$error = '';
$success = '';

$user = get_user_by_id($conn, $_SESSION['userID']);

if (!$user) {
    header('Location: logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $age = intval($_POST['age']);
    $new_password = $_POST['new_password'] ?? '';

    // Validate inputs
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all required fields';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } elseif ($age < 1 || $age > 120) {
        $error = 'Please enter a valid age';
    } else {
        // Check if email is taken by another user
        $existing = get_user_by_email($conn, $email);
        if ($existing && $existing['userID'] != $_SESSION['userID']) {
            $error = 'Email already taken by another user';
        } else {
            // Handle profile picture upload
            $profile_picture = null;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_result = upload_profile_picture($_FILES['profile_picture']);
                if ($upload_result['success']) {
                    $profile_picture = $upload_result['filename'];

                    // Delete old profile picture if exists
                    if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                        unlink($user['profile_picture']);
                    }
                } else {
                    $error = $upload_result['error'];
                }
            }

            if (!$error) {
                // Update user profile
                if (update_user_profile($conn, $_SESSION['userID'], $full_name, $email, $age, $profile_picture)) {
                    // Update password if provided
                    if (!empty($new_password)) {
                        if (strlen($new_password) < 6) {
                            $error = 'Password must be at least 6 characters';
                        } else {
                            update_user_password($conn, $_SESSION['userID'], $new_password);
                            $success = 'Profile and password updated successfully';
                        }
                    } else {
                        $success = 'Profile updated successfully';
                    }

                    // Refresh user data and session
                    $user = get_user_by_id($conn, $_SESSION['userID']);
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                } else {
                    $error = 'Failed to update profile';
                }
            }
        }
    }
}

$theme_class = 'admin-theme';
if ($user['role'] === 'lecturer') {
    $theme_class = 'lecturer-theme';
} elseif ($user['role'] === 'student') {
    $theme_class = 'student-theme';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - EduTrack</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-form-container {
            max-width: 900px;
            margin: 40px auto;
            background: #e0e0e0;
            border-radius: 15px;
            padding: 50px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .profile-left {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .profile-left input {
            width: 100%;
            padding: 18px 22px;
            border: none;
            border-radius: 30px;
            font-size: 15px;
            background: white;
            box-sizing: border-box;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .profile-left input:focus {
            outline: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .profile-right {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .profile-photo {
            width: 160px;
            height: 160px;
            background: #bbb;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 70px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }

        .profile-photo:hover {
            transform: scale(1.05);
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-photo:hover::after {
            content: 'Change Photo';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }

        #profile_picture_input {
            display: none;
        }

        .profile-info-box {
            width: 100%;
            background: white;
            padding: 18px;
            border-radius: 30px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .profile-info-box label {
            display: block;
            font-size: 11px;
            color: #999;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .profile-info-box .value {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }

        .profile-actions {
            text-align: center;
            margin-top: 40px;
        }

        .btn-edit {
            background: #5a6268;
            color: white;
            border: none;
            padding: 14px 50px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .btn-edit:hover {
            background: #4a5258;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <div class="header <?php echo $theme_class; ?>">
        <div class="brand">
            <img src="assets/logoedutrack.png" alt="EduTrack Logo">
            <div class="title">
                <h1>EduTrack</h1>
                <span>Smart Tracking for Smarter Learning</span>
            </div>
        </div>
        <div class="user-info">
            <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="profile-icon" alt="Profile">
            <?php else: ?>
                <img src="assets/profile.png" class="profile-icon" alt="Profile">
            <?php endif; ?>
            <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="sidebar <?php echo $theme_class; ?>">
            <ul>
                <?php if ($user['role'] === 'admin'): ?>
                    <li><a href="admin_dashboard.php">Assessment</a></li>
                    <li><a href="#">Progress</a></li>
                    <li><a href="adminfeedbacklist.php">Feedback</a></li>
                    <li><a href="registration_users.php">Registration Users</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                <?php elseif ($user['role'] === 'lecturer'): ?>
                    <li><a href="lecturer_dashboard.php">Assessment</a></li>
                    <li><a href="lecturer_progress.php">Progress</a></li>
                    <li><a href="lecturer_myfeedback.php">My Feedback</a></li>
                    <li><a href="studevaluationlist.php">Evaluation</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                <?php else: ?>
                    <li><a href="student_courses.php">My Courses</a></li>
                    <li><a href="student_progress.php">My Progress</a></li>
                    <li><a href="student_myfeedback.php">My Feedback</a></li>
                    <li><a href="lectevaluationlist.php">Evaluation</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                <?php endif; ?>
            </ul>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
        </div>

        <div class="main-content">
            <div class="topbar">
                <h2>User Profile</h2>
            </div>

            <?php if ($error): ?>
                <?php echo show_error($error); ?>
            <?php endif; ?>

            <?php if ($success): ?>
                <?php echo show_success($success); ?>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="profile-form-container">
                    <div class="profile-grid">
                        <div class="profile-left">
                            <input type="text" name="full_name" placeholder="Full Name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <input type="password" name="new_password" placeholder="New Password (leave blank to keep current)">
                            <input type="number" name="age" placeholder="Age" min="1" max="120" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>" required>
                        </div>

                        <div class="profile-right">
                            <div class="profile-photo" onclick="document.getElementById('profile_picture_input').click()">
                                <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    👤
                                <?php endif; ?>
                            </div>
                            <input type="file" id="profile_picture_input" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif">

                            <div class="profile-info-box">
                                <label>ID</label>
                                <div class="value"><?php echo htmlspecialchars($user['userID']); ?></div>
                            </div>

                            <div class="profile-info-box">
                                <label>Role</label>
                                <div class="value"><?php echo ucfirst($user['role']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <button type="submit" class="btn-edit">Edit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Preview image when selected
        document.getElementById('profile_picture_input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const photoDiv = document.querySelector('.profile-photo');
                    photoDiv.innerHTML = '<img src="' + e.target.result + '" alt="Profile Picture">';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>