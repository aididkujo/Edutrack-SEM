<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_role('student');

// Get user data
$user = get_user_by_id($conn, $_SESSION['userID']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack - Student Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .success-popup {
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px 30px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
      z-index: 9999;
      animation: slideIn 0.5s ease-out;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    @keyframes slideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }

      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .success-popup.fade-out {
      animation: fadeOut 0.5s ease-out forwards;
    }

    @keyframes fadeOut {
      to {
        opacity: 0;
        transform: translateX(400px);
      }
    }
  </style>
</head>

<body>
  <?php if (isset($_SESSION['login_success']) && $_SESSION['login_success']): ?>
    <div class="success-popup" id="successPopup">
      <span style="font-size: 24px;">✓</span>
      <span>Login Successful! Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
    </div>
    <script>
      setTimeout(function() {
        var popup = document.getElementById('successPopup');
        popup.classList.add('fade-out');
        setTimeout(function() {
          popup.remove();
        }, 500);
      }, 3000);
    </script>
    <?php unset($_SESSION['login_success']); ?>
  <?php endif; ?>
  <div class="header student-theme">
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
    <div class="sidebar student-theme">
      <ul>
        <li><a href="student_courses.php">My Courses</a></li>
        <li><a href="student_progress.php">My Progress</a></li>
        <li><a href="student_myfeedback.php">My Feedback</a></li>
        <li><a href="lectevaluationlist.php">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="topbar">
        <h2>Student Interface</h2>
      </div>
      <div class="center-text">Student Interface</div>
    </div>
  </div>
</body>

</html>