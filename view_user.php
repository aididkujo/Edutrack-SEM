<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

require_role('admin');

// Get current user data (admin)
$user = get_user_by_id($conn, $_SESSION['userID']);

if (!isset($_GET['id'])) {
    header('Location: manage_users.php');
    exit;
}

$userID = intval($_GET['id']);
$viewedUser = get_user_by_id($conn, $userID);

if (!$viewedUser) {
    header('Location: manage_users.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - EduTrack</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .user-container {
            background: #d3d3d3;
            padding: 40px;
            border-radius: 10px;
            max-width: 800px;
            margin: 0 auto;
        }

        .user-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: start;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 15px;

        }

        .info-field {
            background: white;
            padding: 15px;
            border-radius: 25px;
            width: 80%;
            margin-right: 4rem;
        }

        .info-field label {
            display: block;
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .info-field .value {
            font-size: 16px;
            color: #2c3e50;
        }

        .user-photo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .user-photo {
            width: 150px;
            height: 150px;
            background: #ccc;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
            overflow: hidden;
        }

        .user-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-id-field {
            width: 100%;
            background: white;
            padding: 15px;
            border-radius: 25px;
            text-align: center;
        }

        .user-id-field label {
            display: block;
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .user-id-field .value {
            font-size: 16px;
            color: #2c3e50;
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 40px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-edit {
            background: #7f8c8d;
            color: white;
        }

        .btn-back {
            background: #95a5a6;
            color: white;
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
            <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="profile-icon" alt="Profile">
            <?php else: ?>
                <img src="assets/profile.png" class="profile-icon" alt="Profile">
            <?php endif; ?>
            <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="sidebar admin-theme">
            <ul>
                <li><a href="admin_dashboard.php">Assessment</a></li>
                <li><a href="#">Progress</a></li>
                <li><a href="evaluationlist.php">Feedback</a></li>
                <li><a href="registration_users.php">Registration Users</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
            </ul>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
        </div>

        <div class="main-content">
            <div class="topbar">
                <h2>View User</h2>
            </div>

            <div class="user-container">
                <div class="user-grid">
                    <div class="user-info">
                        <div class="info-field">
                            <label>First Name</label>
                            <div class="value"><?php echo htmlspecialchars($viewedUser['full_name']); ?></div>
                        </div>

                        <div class="info-field">
                            <label>Email</label>
                            <div class="value"><?php echo htmlspecialchars($viewedUser['email']); ?></div>
                        </div>

                        <div class="info-field">
                            <label>Age</label>
                            <div class="value"><?php echo htmlspecialchars($viewedUser['age'] ?? 'N/A'); ?></div>
                        </div>
                    </div>

                    <div class="user-photo-section">
                        <div class="user-photo">
                            <?php if (!empty($viewedUser['profile_picture']) && file_exists($viewedUser['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($viewedUser['profile_picture']); ?>" alt="User Photo">
                            <?php else: ?>
                                👤
                            <?php endif; ?>
                        </div>

                        <div class="user-id-field">
                            <label>ID</label>
                            <div class="value"><?php echo htmlspecialchars($viewedUser['userID']); ?></div>
                        </div>

                        <div class="user-id-field">
                            <label>Role</label>
                            <div class="value"><?php echo ucfirst($viewedUser['role']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="edit_user.php?id=<?php echo $viewedUser['userID']; ?>" class="btn btn-edit">Edit</a>
                    <a href="javascript:history.back()" class="btn btn-back">Back</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>