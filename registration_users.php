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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $userID = intval($_POST['userID']);

        switch ($_POST['action']) {
            case 'approve':
                if (approve_user($conn, $userID)) {
                    $success = 'User approved successfully';
                } else {
                    $error = 'Failed to approve user';
                }
                break;

            case 'reject':
                if (reject_user($conn, $userID)) {
                    $success = 'User rejected successfully';
                } else {
                    $error = 'Failed to reject user';
                }
                break;
        }
    }
}

// Get all pending users
$pending_users = get_pending_users($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Users - EduTrack</title>
    <link rel="stylesheet" href="style.css">
    <style>
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

        .btn-accept {
            background: #27ae60;
            color: white;
        }

        .btn-reject {
            background: #e74c3c;
            color: white;
        }

        .no-users {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
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
                <li><a href="evaluationlist.php">Feedback</a></li>
                <li><a href="registration_users.php">Registration Users</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
            </ul>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
        </div>

        <div class="main-content">
            <div class="topbar">
                <h2>Registration Users</h2>
            </div>

            <?php if ($error): ?>
                <?php echo show_error($error); ?>
            <?php endif; ?>

            <?php if ($success): ?>
                <?php echo show_success($success); ?>
            <?php endif; ?>

            <div class="users-table-container">
                <?php if (empty($pending_users)): ?>
                    <div class="no-users">No pending registrations</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Date</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_user.php?id=<?php echo $user['userID']; ?>" class="btn btn-view">View</a>
                                            <a href="edit_user.php?id=<?php echo $user['userID']; ?>" class="btn btn-edit">Edit</a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to accept this user?')">
                                                <input type="hidden" name="userID" value="<?php echo $user['userID']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-accept">Accept</button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this user?')">
                                                <input type="hidden" name="userID" value="<?php echo $user['userID']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-reject">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>