<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_role('lecturer');

// Get user data
$user = get_user_by_id($conn, $_SESSION['userID']);

$lecturerID = (int)($_SESSION['userID'] ?? 0);

$courseID = (int)($_GET['courseID'] ?? 0);
$course = null;

if ($courseID > 0) {
  $stmt = $conn->prepare("SELECT courseID, courseName FROM course WHERE courseID = ? AND userID = ?");
  if ($stmt) {
    $stmt->bind_param("ii", $courseID, $lecturerID);
    $stmt->execute();
    $res = $stmt->get_result();
    $course = $res->fetch_assoc();
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack - Lecturer Subject</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .module-title {
      font-size: 14px;
      letter-spacing: 1px;
      margin: 10px 0 0 10px;
      color: #1f2937;
      opacity: 0.7;
    }

    .subject-wrap {
      padding: 20px;
    }

    .subject-head {
      max-width: 640px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
    }

    .back-link {
      text-decoration: none;
      font-size: 13px;
      padding: 8px 10px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.8);
      color: #111827;
    }

    .tile-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(180px, 1fr));
      gap: 24px;
      max-width: 640px;
      margin: 24px auto 0 auto;
    }

    .tile {
      display: block;
      text-decoration: none;
      background: #9fe6ee;
      border-radius: 14px;
      padding: 34px 16px;
      text-align: center;
      color: #111827;
      font-weight: 600;
      box-shadow: 0 2px 0 rgba(0, 0, 0, 0.05);
    }

    .tile:hover {
      filter: brightness(0.98);
    }

    .error-box {
      max-width: 640px;
      margin: 20px auto;
      background: #fee2e2;
      color: #7f1d1d;
      padding: 12px 14px;
      border-radius: 12px;
      text-align: center;
    }

    @media (max-width: 560px) {
      .tile-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <div class="header lecturer-theme">
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
    <div class="sidebar lecturer-theme">
      <ul>
        <li><a href="lecturer_module2_courses.php">Assessment</a></li>
        <li><a href="lecturer_progress.php">Progress</a></li>
        <li><a href="lecturer_myfeedback.php">My Feedback</a></li>
        <li><a href="studevaluationlist.php">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="topbar">
        <h2><?php echo $course ? htmlspecialchars($course['courseName']) : 'Subject'; ?></h2>
      </div>


      <div class="subject-wrap">
        <?php if (!$course): ?>
          <div class="error-box">Course not found or no access</div>
          <div style="text-align:center;">
            <a class="back-link" href="lecturer_module2_courses.php">Back to courses</a>
          </div>
        <?php else: ?>
          <div class="subject-head">
            <a class="back-link" href="lecturer_courses.php">Back</a>
          </div>

          <div class="tile-grid">
            <a class="tile" href="lecturer_notes.php?courseID=<?php echo (int)$course['courseID']; ?>">Notes</a>
            <a class="tile" href="lecturer_attendance.php?courseID=<?php echo (int)$course['courseID']; ?>">Attendance</a>
            <a class="tile" href="lecturer_quiz.php?courseID=<?php echo (int)$course['courseID']; ?>">Quiz</a>
            <a class="tile" href="lecturer_coursework.php?courseID=<?php echo (int)$course['courseID']; ?>">Course Work</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>