<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_role('lecturer');

// Get user data
$user = get_user_by_id($conn, $_SESSION['userID']);

$lecturerID = (int)($_SESSION['userID'] ?? 0);
$lecturerName = $_SESSION['full_name'] ?? 'Lecturer';

$courses = [];

$stmt = $conn->prepare("
    SELECT
        c.courseID,
        c.courseName,
        (SELECT COUNT(*) FROM enrollment e WHERE e.courseID = c.courseID) AS studentCount
    FROM course c
    WHERE c.userID = ?
    ORDER BY c.courseName
");
if ($stmt) {
  $stmt->bind_param("i", $lecturerID);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $courses[] = $row;
  }
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack - Lecturer Courses</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .module-title {
      font-size: 14px;
      letter-spacing: 1px;
      margin: 10px 0 0 10px;
      color: #1f2937;
      opacity: 0.7;
    }

    .courses-wrap {
      padding: 20px;
    }

    .course-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(140px, 1fr));
      gap: 22px;
      max-width: 760px;
      margin: 0 auto;
      padding-top: 20px;
    }

    .course-tile {
      display: block;
      text-decoration: none;
      background: #9fe6ee;
      border-radius: 12px;
      padding: 26px 14px;
      text-align: center;
      color: #111827;
      box-shadow: 0 2px 0 rgba(0, 0, 0, 0.05);
    }

    .course-tile:hover {
      filter: brightness(0.98);
    }

    .course-name {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 6px;
    }

    .course-meta {
      font-size: 12px;
      opacity: 0.8;
    }

    @media (max-width: 900px) {
      .course-grid {
        grid-template-columns: repeat(2, minmax(140px, 1fr));
      }
    }

    @media (max-width: 560px) {
      .course-grid {
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
        <li><a href="lecturer_courses.php">Assessment</a></li>
        <li><a href="lecturer_progress.php">Progress</a></li>
        <li><a href="lecturer_myfeedback.php">My Feedback</a></li>
        <li><a href="studevaluationlist.php">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="topbar">
        <h2><?php echo htmlspecialchars($lecturerName); ?> Courses</h2>
      </div>

      <div class="courses-wrap">
        <div class="course-grid">
          <?php if (count($courses) === 0): ?>
            <div style="grid-column: 1 / -1; text-align:center; opacity:0.7;">
              No assigned courses
            </div>
          <?php else: ?>
            <?php foreach ($courses as $c): ?>
              <a class="course-tile" href="lecturer_subject.php?courseID=<?php echo (int)$c['courseID']; ?>">
                <div class="course-name"><?php echo htmlspecialchars($c['courseName']); ?></div>
                <div class="course-meta">Students: <?php echo (int)$c['studentCount']; ?></div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>

</html>