<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';
require_role('student');

// Get user data
$user = get_user_by_id($conn, $_SESSION['userID']);

$userID = (int)$_SESSION['userID'];
$fullName = $_SESSION['full_name'] ?? 'Student';

$courseID = isset($_GET['courseID']) ? (int)$_GET['courseID'] : 0;
$assessmentID = isset($_GET['assessmentID']) ? (int)$_GET['assessmentID'] : 0;
if ($courseID <= 0 || $assessmentID <= 0) {
  header("Location: student_courses.php");
  exit;
}

// verify enrollment + assessment belongs to course
$sql = "
  SELECT a.assessmentID, a.tittle, a.dueDate, c.courseCode, c.courseName
  FROM enrollment e
  INNER JOIN course c ON e.courseID=c.courseID
  INNER JOIN assessment a ON a.courseID=c.courseID
  WHERE e.userID=? AND e.courseID=? AND a.assessmentID=? AND a.type='Course Work'
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $userID, $courseID, $assessmentID);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$info) {
  header("Location: student_courses.php");
  exit;
}

$msg = '';
$err = '';

$existing = null;
$stmt = $conn->prepare("SELECT submissionID, grade FROM submission WHERE assessmentID=? AND userID=?");
$stmt->bind_param("ii", $assessmentID, $userID);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_FILES['submission']) || $_FILES['submission']['error'] !== UPLOAD_ERR_OK) {
    $err = 'Upload failed. Please try again.';
  } else {
    $tmp = $_FILES['submission']['tmp_name'];
    $name = $_FILES['submission']['name'];
    $size = (int)$_FILES['submission']['size'];

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
      $err = 'Only PDF file is allowed.';
    } elseif ($size > 10 * 1024 * 1024) {
      $err = 'File too large. Max 10MB.';
    } elseif ($existing && (float)$existing['grade'] > 0) {
      $err = 'Resubmission blocked because grading already exists.';
    } else {
      $dir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "coursework";
      if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
      }

      $destRel = "uploads/coursework/submission_" . $assessmentID . "_" . $userID . ".pdf";
      $destAbs = __DIR__ . DIRECTORY_SEPARATOR . $destRel;

      $conn->begin_transaction();
      try {
        if (!move_uploaded_file($tmp, $destAbs)) {
          throw new Exception('File save failed.');
        }

        if ($existing) {
          $gradeZero = 0.00;
          $stmt = $conn->prepare("UPDATE submission SET submitDate = CURDATE(), grade = ? WHERE submissionID = ?");
          $stmt->bind_param("di", $gradeZero, $existing['submissionID']);
          if (!$stmt->execute()) throw new Exception('DB update failed.');
          $stmt->close();
        } else {
          $gradeZero = 0.00;
          $stmt = $conn->prepare("INSERT INTO submission (submitDate, grade, assessmentID, userID) VALUES (CURDATE(), ?, ?, ?)");
          $stmt->bind_param("dii", $gradeZero, $assessmentID, $userID);
          if (!$stmt->execute()) throw new Exception('DB insert failed.');
          $stmt->close();
        }

        $conn->commit();
        header("Location: student_coursework.php?courseID=" . $courseID);
        exit;
      } catch (Exception $e) {
        $conn->rollback();
        $err = 'Submission failed. Please try again.';
      }
    }
  }
}

$fileRel = "uploads/coursework/submission_" . $assessmentID . "_" . $userID . ".pdf";
$hasFile = file_exists(__DIR__ . DIRECTORY_SEPARATOR . $fileRel);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Course Work - EduTrack</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .module-top {
      text-align: center;
      color: #cfcfcf;
      margin: 12px 0;
      font-weight: 700;
      letter-spacing: .5px;
    }

    .wrap {
      background: #111;
      min-height: 100vh;
      padding: 10px;
    }

    .board {
      max-width: 1100px;
      margin: 0 auto;
      background: #eaffc6;
      border-radius: 4px;
      overflow: hidden;
    }

    .hdr {
      background: #caff8a;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 16px;
    }

    .hdr .brand {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .hdr img {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
    }

    .hdr .txt h1 {
      margin: 0;
      font-size: 18px;
    }

    .hdr .txt p {
      margin: 0;
      font-size: 12px;
    }

    .profile {
      width: 28px;
      height: 28px;
      border: 1px solid #666;
      border-radius: 2px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #e5e5e5;
    }

    .main {
      display: flex;
      background: #fff;
      min-height: 520px;
    }

    .side {
      width: 190px;
      background: #caff8a;
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .side a {
      display: block;
      padding: 8px 10px;
      background: #d9ffad;
      border-radius: 8px;
      text-decoration: none;
      color: #111;
      font-size: 13px;
    }

    .logout {
      margin-top: auto;
    }

    .logout a {
      background: #e28a8a;
      color: #111;
      text-align: center;
    }

    .content {
      flex: 1;
      padding: 26px;
    }

    .back {
      display: inline-block;
      margin-bottom: 12px;
      text-decoration: none;
      color: #111;
      background: #f3f4f6;
      padding: 8px 10px;
      border-radius: 10px;
    }

    .box {
      background: #f9fafb;
      border-radius: 12px;
      padding: 16px;
      max-width: 720px;
    }

    .title {
      margin: 0 0 10px 0;
      font-size: 16px;
    }

    .meta {
      color: #6b7280;
      font-size: 12px;
      margin: 6px 0 12px 0;
    }

    .msg {
      margin: 10px 0;
      padding: 10px;
      border-radius: 10px;
      background: #dcfce7;
    }

    .err {
      margin: 10px 0;
      padding: 10px;
      border-radius: 10px;
      background: #fecaca;
    }

    .btn {
      display: inline-block;
      text-decoration: none;
      padding: 8px 12px;
      border-radius: 10px;
      background: #caff8a;
      color: #111;
      font-size: 13px;
      border: none;
      cursor: pointer;
    }

    .btn.secondary {
      background: #e5e7eb;
    }

    @media (max-width: 860px) {
      .main {
        flex-direction: column;
      }

      .side {
        width: auto;
        flex-direction: row;
        flex-wrap: wrap;
      }

      .logout {
        margin-top: 0;
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="module-top">MODULE 2: COURSE AND ASSESSMENT</div>
    <div class="board student-theme">
      <div class="hdr">
        <div class="brand">
          <img src="assets/logoedutrack.png" alt="EduTrack">
          <div class="txt">
            <h1>EduTrack</h1>
            <p>Smart Tracking for Smarter Learning</p>
          </div>
        </div>
        <div class="profile" title="<?php echo htmlspecialchars($user['full_name']); ?>">
          <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;" alt="Profile">
          <?php else: ?>
            👤
          <?php endif; ?>
        </div>
      </div>

      <div class="main">
        <div class="side">
          <a href="student_courses.php">My Courses</a>
          <a href="student_progress.php">My Progress</a>
          <a href="student_myfeedback.php">My Feedback</a>
          <a href="lectevaluationlist.php">Evaluation</a>
          <div class="logout"><a href="logout.php">Log Out</a></div>
        </div>

        <div class="content">
          <a class="back" href="student_coursework.php?courseID=<?php echo $courseID; ?>">Back</a>

          <div class="box">
            <h2 class="title"><?php echo htmlspecialchars($info['tittle']); ?></h2>
            <div class="meta">
              Course: <?php echo htmlspecialchars($info['courseCode']); ?> |
              Due: <?php echo htmlspecialchars($info['dueDate']); ?> |
              File: PDF only
            </div>

            <?php if ($err): ?><div class="err"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
            <?php if ($msg): ?><div class="msg"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

            <?php if ($hasFile): ?>
              <div class="meta">Existing submission file detected.</div>
              <a class="btn secondary" href="download_coursework.php?courseID=<?php echo $courseID; ?>&assessmentID=<?php echo $assessmentID; ?>">Download current file</a>
              <div style="height:10px;"></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
              <input type="file" name="submission" accept="application/pdf" required>
              <div style="height:10px;"></div>
              <button class="btn" type="submit">Upload Submission</button>
            </form>
          </div>

        </div>
      </div>

    </div>
  </div>
</body>

</html>