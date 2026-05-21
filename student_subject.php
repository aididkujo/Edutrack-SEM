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
$courseID = isset($_GET['courseID']) ? (int)$_GET['courseID'] : 0;

if ($courseID <= 0) {
  header("Location: student_courses.php");
  exit;
}

// Check student enrollment
$chk = $conn->prepare("SELECT 1 FROM enrollment WHERE userID = ? AND courseID = ? LIMIT 1");
$chk->bind_param("ii", $userID, $courseID);
$chk->execute();
$enrolled = $chk->get_result()->fetch_row();
$chk->close();

if (!$enrolled) {
  header("Location: student_courses.php");
  exit;
}

// Get course + lecturer info
$sql = "
  SELECT c.courseName, u.full_name AS lecturerName
  FROM course c
  JOIN user u ON c.userID = u.userID
  WHERE c.courseID = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $courseID);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

$courseName = $course ? $course['courseName'] : "Course";
$lecturerName = $course ? $course['lecturerName'] : "-";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack - Subject</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .subject-wrap {
      padding: 22px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .subject-meta {
      width: 100%;
      max-width: 560px;
      margin-bottom: 18px;
      text-align: ;
    }

    .tiles {
      width: 100%;
      max-width: 560px;
      display: grid;
      grid-template-columns: repeat(2, minmax(200px, 1fr));
      gap: 18px;
      margin: 0 auto;
    }

    .tile {
      background: #c8ff74;
      border-radius: 16px;
      padding: 26px;
      text-align: center;
      text-decoration: none;
      color: #111;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
      font-weight: 700;
      transition: transform 0.08s ease-in-out;
    }

    .tile:hover {
      transform: scale(1.01);
    }

    .back {
      display: inline-block;
      margin-top: 18px;
      background: #000;
      color: #fff;
      text-decoration: none;
      padding: 10px 16px;
      border-radius: 10px;
      font-weight: 700;
      transition: opacity 0.12s ease-in-out, transform 0.08s ease-in-out;
    }

    .back:hover {
      opacity: 0.9;
      transform: scale(1.01);
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
        <div class="subject-meta">
          <h2>Course and Assessment</h2>
          <div><strong>Course:</strong> <?php echo htmlspecialchars($courseName); ?></div>
          <div><strong>Lecturer:</strong> <?php echo htmlspecialchars($lecturerName); ?></div>
        </div>
      </div>

      <div class="subject-wrap">


        <div class="tiles">
          <a class="tile" href="student_notes.php?courseID=<?php echo $courseID; ?>">Notes</a>
          <a class="tile" href="student_attendance.php?courseID=<?php echo $courseID; ?>">Attendance</a>
          <a class="tile" href="student_quiz_list.php?courseID=<?php echo $courseID; ?>">Quiz</a>
          <a class="tile" href="student_coursework.php?courseID=<?php echo $courseID; ?>">Course Work</a>
        </div>

        <a class="back" href="student_courses.php">Back to My Courses</a>
      </div>
    </div>
  </div>
</body>

</html>