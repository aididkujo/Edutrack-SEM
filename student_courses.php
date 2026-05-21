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

$userID = (int)$_SESSION['userID'];
$message = "";

// Get enrolled courses + lecturer name
$sql = "
  SELECT c.courseID, c.courseName, u.full_name AS lecturerName
  FROM enrollment e
  JOIN course c ON e.courseID = c.courseID
  JOIN user u ON c.userID = u.userID
  WHERE e.userID = ?
  ORDER BY c.courseName
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack - My Courses</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(180px, 1fr));
      gap: 18px;
      padding: 20px;
    }

    .card {
      background: #c8ff74;
      border-radius: 14px;
      padding: 18px;
      text-decoration: none;
      color: #111;
      display: block;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
      transition: transform 0.08s ease-in-out;
    }

    .card:hover {
      transform: scale(1.01);
    }

    .card small {
      display: block;
      margin-top: 8px;
      opacity: 0.85;
    }

    .empty {
      padding: 22px;
    }
  </style>
</head>

<body>
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
        <h2>My Courses </h2>
      </div>

      <?php if (empty($courses)) : ?>
        <div class="empty">No enrolled courses found.</div>
      <?php else : ?>
        <div class="grid">
          <?php foreach ($courses as $c): ?>
            <a class="card" href="student_subject.php?courseID=<?php echo (int)$c['courseID']; ?>">
              <div><strong><?php echo htmlspecialchars($c['courseName']); ?></strong></div>
              <small>Lecturer: <?php echo htmlspecialchars($c['lecturerName']); ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>