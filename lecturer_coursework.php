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
$courseID = (int)($_GET['courseID'] ?? 0);

$error = '';
$success = '';

$course = null;
if ($courseID > 0) {
  $stmt = $conn->prepare("SELECT courseID, courseName FROM course WHERE courseID = ? AND userID = ?");
  if ($stmt) {
    $stmt->bind_param("ii", $courseID, $lecturerID);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

if (!$course) {
  $error = 'Course not found or no access';
}

function coursework_owned(mysqli $conn, int $assessmentID, int $courseID, int $lecturerID): bool
{
  $chk = $conn->prepare("
    SELECT a.assessmentID
    FROM assessment a
    JOIN course c ON c.courseID = a.courseID
    WHERE a.assessmentID = ?
      AND a.courseID = ?
      AND a.type = 'Course Work'
      AND c.userID = ?
    LIMIT 1
  ");

  if (!$chk) return false;

  $chk->bind_param("iii", $assessmentID, $courseID, $lecturerID);
  $chk->execute();
  $owned = $chk->get_result()->fetch_assoc();
  $chk->close();

  return (bool)$owned;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $course) {
  $action = $_POST['action'] ?? '';

  // CREATE COURSEWORK
  if ($action === 'create_coursework') {
    $title = trim($_POST['tittle'] ?? '');
    $dueDate = $_POST['dueDate'] ?? '';

    if ($title === '' || $dueDate === '') {
      $error = 'Title and due date are required';
    } else {
      $type = 'Course Work';
      $createdDate = date('Y-m-d');
      $isVisible = 1;

      $stmt = $conn->prepare("
        INSERT INTO assessment 
        (tittle, type, createdDate, dueDate, courseID, isVisible) 
        VALUES (?, ?, ?, ?, ?, ?)
      ");

      if ($stmt) {
        $stmt->bind_param("ssssii", $title, $type, $createdDate, $dueDate, $courseID, $isVisible);

        if ($stmt->execute()) {
          $success = 'Course work created';
        } else {
          $error = 'Failed to create course work';
        }

        $stmt->close();
      } else {
        $error = 'Database error';
      }
    }
  }

  // TOGGLE COURSEWORK VISIBILITY
  if ($action === 'toggle_visibility') {
    $assessmentID = (int)($_POST['assessmentID'] ?? 0);
    $isVisible = (int)($_POST['isVisible'] ?? -1);

    if ($assessmentID <= 0) {
      $error = 'Invalid course work';
    } elseif ($isVisible !== 0 && $isVisible !== 1) {
      $error = 'Invalid visibility status';
    } elseif (!coursework_owned($conn, $assessmentID, $courseID, $lecturerID)) {
      $error = 'Course work not found or no access';
    } else {
      $stmt = $conn->prepare("
        UPDATE assessment
        SET isVisible = ?
        WHERE assessmentID = ?
          AND courseID = ?
          AND type = 'Course Work'
      ");

      if ($stmt) {
        $stmt->bind_param("iii", $isVisible, $assessmentID, $courseID);

        if ($stmt->execute()) {
          $success = $isVisible === 1
            ? 'Course work is now visible to students'
            : 'Course work is now hidden from students';
        } else {
          $error = 'Failed to update course work visibility';
        }

        $stmt->close();
      } else {
        $error = 'Database error when updating visibility';
      }
    }
  }
}

$items = [];

if ($course) {
  $sql = "
    SELECT 
      a.assessmentID, 
      a.tittle, 
      a.createdDate, 
      a.dueDate,
      a.isVisible,
      (SELECT COUNT(*) 
       FROM submission s 
       WHERE s.assessmentID = a.assessmentID) AS submissionCount
    FROM assessment a
    WHERE a.courseID = ? 
      AND a.type = 'Course Work'
    ORDER BY a.createdDate DESC, a.assessmentID DESC
  ";

  $stmt = $conn->prepare($sql);

  if ($stmt) {
    $stmt->bind_param("i", $courseID);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
      $items[] = $row;
    }

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

  <title>EduTrack Lecturer Course Work</title>
  <link rel="stylesheet" href="style.css">

  <style>
    .wrap {
      padding: 20px;
    }

    .card {
      max-width: 820px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.85);
      border-radius: 14px;
      padding: 16px;
    }

    .row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .row input {
      flex: 1;
      min-width: 220px;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      outline: none;
    }

    .row button {
      padding: 10px 14px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      background: #1f2937;
      color: #fff;
      font-weight: 900;
    }

    .msg {
      max-width: 820px;
      margin: 10px auto 0 auto;
      padding: 10px 12px;
      border-radius: 10px;
      font-size: 13px;
    }

    .msg.error {
      background: #fee2e2;
      color: #7f1d1d;
    }

    .msg.success {
      background: #dcfce7;
      color: #14532d;
    }

    .list {
      max-width: 820px;
      margin: 16px auto 0 auto;
      display: grid;
      gap: 10px;
    }

    .item {
      background: #9fe6ee;
      border-radius: 14px;
      padding: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .item a {
      text-decoration: none;
      color: #111827;
      font-weight: 900;
    }

    .meta {
      font-size: 12px;
      opacity: 0.85;
      margin-top: 4px;
      line-height: 1.6;
    }

    .btnLink {
      text-decoration: none;
      background: rgba(255, 255, 255, 0.85);
      color: #111827;
      padding: 8px 10px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 800;
      border: none;
      cursor: pointer;
      display: inline-block;
    }

    .btnShow {
      background: #15803d;
      color: #fff;
    }

    .btnHide {
      background: #ca8a04;
      color: #fff;
    }

    .pill {
      font-size: 12px;
      font-weight: 900;
      padding: 5px 10px;
      border-radius: 999px;
      display: inline-block;
    }

    .visibility-visible {
      background: #dcfce7;
      color: #14532d;
    }

    .visibility-hidden {
      background: #fee2e2;
      color: #7f1d1d;
    }

    .actions {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
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

      <span class="user-name">
        <?php echo htmlspecialchars($user['full_name']); ?>
      </span>
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

      <button class="logout-btn" onclick="window.location.href='logout.php'">
        Log Out
      </button>
    </div>

    <div class="main-content">
      <div class="topbar">
        <h2>
          <?php echo $course ? htmlspecialchars($course['courseName']) : 'Course Work'; ?>
        </h2>
      </div>

      <div class="wrap">
        <?php if ($error !== ''): ?>
          <div class="msg error">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
          <div class="msg success">
            <?php echo htmlspecialchars($success); ?>
          </div>
        <?php endif; ?>

        <?php if ($course): ?>
          <div class="card">
            <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center;">
              <div style="font-weight:900;">Course Work</div>
              <a class="btnLink" href="lecturer_subject.php?courseID=<?php echo (int)$courseID; ?>">
                Back
              </a>
            </div>

            <div style="height:12px;"></div>

            <form method="POST" action="">
              <input type="hidden" name="action" value="create_coursework">

              <div class="row">
                <input
                  type="text"
                  name="tittle"
                  maxlength="255"
                  placeholder="Course work title"
                  required>

                <input
                  type="date"
                  name="dueDate"
                  required>

                <button type="submit">Create</button>
              </div>
            </form>
          </div>

          <div class="list">
            <?php if (count($items) === 0): ?>
              <div class="card" style="text-align:center; opacity:0.75;">
                No course work yet
              </div>
            <?php else: ?>
              <?php foreach ($items as $a): ?>
                <?php
                $aid = (int)$a['assessmentID'];

                $isVisible = (int)($a['isVisible'] ?? 1);
                $visibilityText = $isVisible === 1 ? 'Visible to Students' : 'Hidden from Students';
                $visibilityClass = $isVisible === 1 ? 'visibility-visible' : 'visibility-hidden';

                $nextVisibility = $isVisible === 1 ? 0 : 1;
                $visibilityButtonText = $isVisible === 1 ? 'Hide from Students' : 'Show to Students';
                $visibilityButtonClass = $isVisible === 1 ? 'btnHide' : 'btnShow';
                ?>

                <div class="item">
                  <div>
                    <a href="lecturer_coursework_grade.php?assessmentID=<?php echo $aid; ?>">
                      <?php echo htmlspecialchars($a['tittle']); ?>
                    </a>

                    <div class="meta">
                      Created <?php echo htmlspecialchars($a['createdDate']); ?> ,
                      Due <?php echo htmlspecialchars($a['dueDate']); ?> ,
                      Submissions <?php echo (int)$a['submissionCount']; ?>
                      <br>

                      <b>Visibility:</b>
                      <span class="pill <?php echo $visibilityClass; ?>">
                        <?php echo htmlspecialchars($visibilityText); ?>
                      </span>
                    </div>
                  </div>

                  <div class="actions">
                    <a class="btnLink" href="lecturer_coursework_grade.php?assessmentID=<?php echo $aid; ?>">
                      Grade
                    </a>

                    <form method="POST" action="" style="margin:0;">
                      <input type="hidden" name="action" value="toggle_visibility">
                      <input type="hidden" name="assessmentID" value="<?php echo $aid; ?>">
                      <input type="hidden" name="isVisible" value="<?php echo $nextVisibility; ?>">

                      <button class="btnLink <?php echo $visibilityButtonClass; ?>" type="submit">
                        <?php echo htmlspecialchars($visibilityButtonText); ?>
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>