<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

// Require admin role
require_role('admin');

// Get current user data
$user = get_user_by_id($conn, $_SESSION['userID']);

$error = '';
$success = '';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $userID = intval($_POST['userID']);

    // Prevent admin from deleting themselves
    if ($userID === $_SESSION['userID']) {
        $error = 'You cannot delete your own account';
    } else {
        if (delete_user($conn, $userID)) {
            $success = 'User deleted successfully';
        } else {
            $error = 'Failed to delete user';
        }
    }
}

// Get all users
$all_users = get_all_users($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - EduTrack</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }

        .btn-view {
            background: #3498db;
            color: white;
        }

        .btn-edit {
            background: #f39c12;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-deactivated {
            background: #d6d8db;
            color: #383d41;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: #f0f0f0;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            font-weight: bold;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .users-table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
                <li><a href="adminfeedbacklist.php">Feedback</a></li>
                <li><a href="registration_users.php">Registration Users</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
            </ul>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
        </div>

        <div class="main-content">
            <div class="topbar">
                <h2>Manage Users</h2>
            </div>

            <?php if ($error): ?>
                <?php echo show_error($error); ?>
            <?php endif; ?>

            <?php if ($success): ?>
                <?php echo show_success($success); ?>
            <?php endif; ?>

            <div class="users-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo ucfirst($user['role']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_user.php?id=<?php echo $user['userID']; ?>" class="btn btn-view">View</a>
                                        <a href="edit_user.php?id=<?php echo $user['userID']; ?>" class="btn btn-edit">Edit</a>
                                        <?php if ($user['userID'] !== $_SESSION['userID']): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                <input type="hidden" name="userID" value="<?php echo $user['userID']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-delete">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>