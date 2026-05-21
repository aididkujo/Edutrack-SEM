<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

require_role('admin');

// Get current user data for header
$current_user = get_user_by_id($conn, $_SESSION['userID']);

$error = '';
$success = '';

if (!isset($_GET['id'])) {
    header('Location: manage_users.php');
    exit;
}

$userID = intval($_GET['id']);
$user = get_user_by_id($conn, $userID);

if (!$user) {
    header('Location: manage_users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $age = intval($_POST['age']);
    $role = sanitize_input($_POST['role']);

    // Validate inputs
    if (empty($full_name) || empty($email) || empty($role)) {
        $error = 'Please fill in all fields';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email format';
    } elseif ($age < 1 || $age > 120) {
        $error = 'Please enter a valid age';
    } elseif (!in_array($role, ['student', 'lecturer', 'admin'])) {
        $error = 'Invalid role selected';
    } else {
        $existing = get_user_by_email($conn, $email);
        if ($existing && $existing['userID'] != $userID) {
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
                if ($profile_picture !== null) {
                    $stmt = $conn->prepare("UPDATE user SET full_name = ?, email = ?, age = ?, role = ?, profile_picture = ? WHERE userID = ?");
                    $stmt->bind_param("ssissi", $full_name, $email, $age, $role, $profile_picture, $userID);
                } else {
                    $stmt = $conn->prepare("UPDATE user SET full_name = ?, email = ?, age = ?, role = ? WHERE userID = ?");
                    $stmt->bind_param("ssisi", $full_name, $email, $age, $role, $userID);
                }

                if ($stmt->execute()) {
                    $success = 'User updated successfully';
                    $user = get_user_by_id($conn, $userID);
                } else {
                    $error = 'Failed to update user';
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
    <title>Edit User - EduTrack</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .user-container {
            background: #e0e0e0;
            padding: 50px;
            border-radius: 15px;
            max-width: 900px;
            margin: 0 auto;
        }

        .user-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        .user-info-edit {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-right: 4rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            margin-left: 5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 18px 22px;
            border: none;
            border-radius: 30px;
            font-size: 15px;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-color: white;
            padding-right: 45px;
        }

        .user-photo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .user-photo {
            width: 160px;
            height: 160px;
            background: #bbb;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 70px;
            color: white;
            overflow: hidden;
            cursor: pointer;
            position: relative;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }

        .user-photo:hover {
            transform: scale(1.05);
        }

        .user-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-photo:hover::after {
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

        .user-id-field {
            width: 100%;
            background: white;
            padding: 7px;
            border-radius: 30px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .user-id-field label {
            display: block;
            font-size: 11px;
            color: #999;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .user-id-field .value {
            font-size: 18px;
            color: #2c3e50;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 14px 50px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-save {
            background: #5a6268;
            color: white;
        }

        .btn-save:hover {
            background: #4a5258;
        }

        .btn-back {
            background: #95a5a6;
            color: white;
        }

        .btn-back:hover {
            background: #7f8c8d;
        }
    </style>
</head>

<body>
    <div class="header admin-theme">
        <div class="brand">
            <img src="assets/logoedutrack.png" alt="EduTrack Logo">
            <div class="title">
                <h1>EduTrack</h1>
                <span>Smart Tracking for Smarter Learning</span>
            </div>
        </div>
        <div class="user-info">
            <?php if (!empty($current_user['profile_picture']) && file_exists($current_user['profile_picture'])): ?>
                <img src="<?php echo htmlspecialchars($current_user['profile_picture']); ?>" class="profile-icon" alt="Profile">
            <?php else: ?>
                <img src="assets/profile.png" class="profile-icon" alt="Profile">
            <?php endif; ?>
            <span class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></span>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="sidebar admin-theme">
            <ul>
                <li><a href="admin_dashboard.php">Assessment</a></li>
                <li><a href="#">Progress</a></li>
                <li><a href="#">Feedback</a></li>
                <li><a href="registration_users.php">Registration Users</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
            </ul>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
        </div>

        <div class="main-content">
            <div class="topbar">
                <h2>Edit User</h2>
            </div>

            <?php if ($error): ?>
                <?php echo show_error($error); ?>
            <?php endif; ?>

            <?php if ($success): ?>
                <?php echo show_success($success); ?>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="user-container">
                    <div class="user-grid">
                        <div class="user-info-edit">
                            <div class="form-group">
                                <input type="text" name="full_name" placeholder="Full Name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <input type="number" name="age" placeholder="Age" min="1" max="120" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="user-photo-section">
                            <div class="user-photo" onclick="document.getElementById('profile_picture_input').click()">
                                <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                                <?php else: ?>
                                    👤
                                <?php endif; ?>
                            </div>
                            <input type="file" id="profile_picture_input" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif">

                            <div class="user-id-field">
                                <label>ID</label>
                                <div class="value"><?php echo htmlspecialchars($user['userID']); ?></div>
                            </div>

                            <div class="form-group" style="width: 100%;">
                                <select name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="lecturer" <?php echo $user['role'] === 'lecturer' ? 'selected' : ''; ?>>Lecturer</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-save">Save</button>
                        <a href="javascript:history.back()" class="btn btn-back">Back</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </div>

    <script>
        // Preview image when selected
        document.getElementById('profile_picture_input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const photoDiv = document.querySelector('.user-photo');
                    photoDiv.innerHTML = '<img src="' + e.target.result + '" alt="Profile Picture">';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>