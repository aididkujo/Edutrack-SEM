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

// Check enrollment
$chk = $conn->prepare("SELECT 1 FROM enrollment WHERE userID = ? AND courseID = ? LIMIT 1");
$chk->bind_param("ii", $userID, $courseID);
$chk->execute();
$enrolled = $chk->get_result()->fetch_row();
$chk->close();
if (!$enrolled) {
  header("Location: student_courses.php");
  exit;
}

// Course name
$cstmt = $conn->prepare("SELECT courseName FROM course WHERE courseID = ? LIMIT 1");
$cstmt->bind_param("i", $courseID);
$cstmt->execute();
$crow = $cstmt->get_result()->fetch_assoc();
$cstmt->close();
$courseName = $crow ? $crow['courseName'] : "Course";

// Notes
$stmt = $conn->prepare("SELECT noteID, title, description, filePath, uploadedDate FROM notes WHERE courseID = ? ORDER BY uploadedDate DESC, noteID DESC");
$stmt->bind_param("i", $courseID);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack - Notes</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap {
      padding: 22px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 14px;
    }

    th,
    td {
      border: 1px solid #ddd;
      padding: 10px;
      text-align: left;
    }

    th {
      background: #f3f3f3;
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

    .pill {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      background: #c8ff74;
      text-decoration: none;
      color: #111;
      font-weight: 700;
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
        <h2>Notes </h2>
      </div>

      <div class="wrap">
        <div><strong>Course:</strong> <?php echo htmlspecialchars($courseName); ?></div>

        <?php if (empty($notes)) : ?>
          <p>No notes uploaded for this course yet.</p>
        <?php else : ?>
          <table>
            <thead>
              <tr>
                <th>Title</th>
                <th>Description</th>
                <th>Uploaded Date</th>
                <th>File</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($notes as $n): ?>
                <tr>
                  <td><?php echo htmlspecialchars($n['title']); ?></td>
                  <td><?php echo htmlspecialchars($n['description'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($n['uploadedDate']); ?></td>
                  <td>
                    <?php if (!empty($n['filePath'])): ?>
                      <a class="pill" href="<?php echo htmlspecialchars($n['filePath']); ?>" target="_blank" rel="noopener">Open</a>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <a class="back" href="student_subject.php?courseID=<?php echo $courseID; ?>">Back to Subject</a>
      </div>
    </div>
  </div>
</body>

</html>