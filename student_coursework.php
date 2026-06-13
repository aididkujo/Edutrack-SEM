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

$message = "";

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assessmentID'])) {
  $assessmentID = (int)$_POST['assessmentID'];
  $submissionText = isset($_POST['submissionText']) ? trim($_POST['submissionText']) : "";

  /*
    UPDATED VALIDATION:
    Added AND isVisible = 1.
    This prevents students from submitting hidden coursework.
  */
  $v = $conn->prepare("
    SELECT 1 
    FROM assessment 
    WHERE assessmentID = ? 
      AND courseID = ? 
      AND type = 'Course Work'
      AND isVisible = 1
    LIMIT 1
  ");
  $v->bind_param("ii", $assessmentID, $courseID);
  $v->execute();
  $validAssessment = $v->get_result()->fetch_row();
  $v->close();

  if (!$validAssessment) {
    $message = show_error("Invalid assessment for this course or course work is currently hidden.");
  } else {
    // Prepare upload (optional)
    $newFilePath = null;

    if (isset($_FILES['submissionFile']) && $_FILES['submissionFile']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['submissionFile']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf', 'doc', 'docx', 'zip', 'png', 'jpg', 'jpeg'];
        $ext = strtolower(pathinfo($_FILES['submissionFile']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
          $message = show_error("Invalid file type. Allowed: pdf, doc, docx, zip, png, jpg, jpeg.");
        } else {
          $dir = "uploads/submissions";

          if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
          }

          $safeName = "cw_u" . $userID . "_a" . $assessmentID . "_" . time() . "." . $ext;
          $target = $dir . "/" . $safeName;

          if (move_uploaded_file($_FILES['submissionFile']['tmp_name'], $target)) {
            $newFilePath = $target;
          } else {
            $message = show_error("File upload failed.");
          }
        }
      } else {
        $message = show_error("File upload error.");
      }
    }

    if ($message === "") {
      // Find latest submission if exists
      $s = $conn->prepare("
        SELECT submissionID, filePath 
        FROM submission 
        WHERE assessmentID = ? AND userID = ? 
        ORDER BY submissionID DESC 
        LIMIT 1
      ");
      $s->bind_param("ii", $assessmentID, $userID);
      $s->execute();
      $existing = $s->get_result()->fetch_assoc();
      $s->close();

      if ($existing) {
        $finalPath = $existing['filePath'];

        if ($newFilePath !== null) {
          $finalPath = $newFilePath;
        }

        $u = $conn->prepare("
          UPDATE submission
          SET submitDate = CURDATE(),
              filePath = ?,
              submissionText = ?,
              submittedAt = NOW(),
              grade = 0.00
          WHERE submissionID = ?
        ");
        $u->bind_param("ssi", $finalPath, $submissionText, $existing['submissionID']);
        $ok = $u->execute();
        $u->close();

        $message = $ok
          ? show_success("Course work submitted successfully.")
          : show_error("Submission update failed.");
      } else {
        $finalPath = ($newFilePath !== null) ? $newFilePath : null;

        $ins = $conn->prepare("
          INSERT INTO submission 
          (submitDate, filePath, submissionText, submittedAt, grade, assessmentID, userID)
          VALUES (CURDATE(), ?, ?, NOW(), 0.00, ?, ?)
        ");
        $ins->bind_param("ssii", $finalPath, $submissionText, $assessmentID, $userID);
        $ok = $ins->execute();
        $ins->close();

        $message = $ok
          ? show_success("Course work submitted successfully.")
          : show_error("Submission failed.");
      }
    }
  }
}

/*
  UPDATED LIST QUERY:
  Added AND a.isVisible = 1.
  This ensures students only see coursework that lecturer has set as visible.
*/
$sql = "
  SELECT
    a.assessmentID,
    a.tittle,
    a.createdDate,
    a.dueDate,
    s.submissionID,
    s.filePath,
    s.submissionText,
    s.submittedAt,
    s.grade
  FROM assessment a
  LEFT JOIN submission s
    ON s.submissionID = (
      SELECT MAX(s2.submissionID)
      FROM submission s2
      WHERE s2.assessmentID = a.assessmentID 
        AND s2.userID = ?
    )
  WHERE a.courseID = ? 
    AND a.type = 'Course Work'
    AND a.isVisible = 1
  ORDER BY a.dueDate ASC, a.assessmentID ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userID, $courseID);
$stmt->execute();
$assessments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">

  <title>EduTrack - Course Work</title>
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
      vertical-align: top;
    }

    th {
      background: #f3f3f3;
    }

    .status {
      font-weight: 700;
    }

    .formbox {
      margin-top: 10px;
      padding: 12px;
      background: #f7f7f7;
      border-radius: 10px;
    }

    textarea {
      width: 100%;
      min-height: 80px;
      padding: 10px;
    }

    input[type="file"] {
      width: 100%;
    }

    .btn {
      margin-top: 10px;
      padding: 10px 14px;
      border: 0;
      border-radius: 10px;
      background: #c8ff74;
      font-weight: 700;
      cursor: pointer;
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

    .muted {
      color: #6b7280;
      font-size: 14px;
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

      <span class="user-name">
        <?php echo htmlspecialchars($user['full_name']); ?>
      </span>
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

      <button class="logout-btn" onclick="window.location.href='logout.php'">
        Log Out
      </button>
    </div>

    <div class="main-content">
      <div class="topbar">
        <h2>Course Work</h2>
      </div>

      <div class="wrap">
        <div>
          <strong>Course:</strong>
          <?php echo htmlspecialchars($courseName); ?>
        </div>

        <?php echo $message; ?>

        <?php if (empty($assessments)) : ?>
          <p class="muted">No visible course work assessments found for this course yet.</p>
        <?php else : ?>
          <table>
            <thead>
              <tr>
                <th>Title</th>
                <th>Created</th>
                <th>Due</th>
                <th>Submission</th>
                <th>Grade</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($assessments as $a): ?>
                <?php
                $submitted = !empty($a['submittedAt']) || !empty($a['filePath']) || !empty($a['submissionText']);
                ?>

                <tr>
                  <td><?php echo htmlspecialchars($a['tittle']); ?></td>
                  <td><?php echo htmlspecialchars($a['createdDate']); ?></td>
                  <td><?php echo htmlspecialchars($a['dueDate']); ?></td>

                  <td>
                    <div class="status">
                      <?php echo $submitted ? "Submitted" : "Not Submitted"; ?>
                    </div>

                    <?php if (!empty($a['submittedAt'])): ?>
                      <div>
                        Submitted At:
                        <?php echo htmlspecialchars($a['submittedAt']); ?>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($a['filePath'])): ?>
                      <div>
                        File:
                        <a href="<?php echo htmlspecialchars($a['filePath']); ?>" target="_blank" rel="noopener">
                          Open
                        </a>
                      </div>
                    <?php endif; ?>

                    <div class="formbox">
                      <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="assessmentID" value="<?php echo (int)$a['assessmentID']; ?>">

                        <label>
                          <strong>Submission Text (optional)</strong>
                        </label>

                        <textarea name="submissionText" placeholder="Enter submission text (optional)"><?php
                          echo htmlspecialchars($a['submissionText'] ?? "");
                        ?></textarea>

                        <label>
                          <strong>Upload File (optional)</strong>
                        </label>

                        <input
                          type="file"
                          name="submissionFile"
                          accept=".pdf,.doc,.docx,.zip,.png,.jpg,.jpeg">

                        <button class="btn" type="submit">
                          <?php echo $submitted ? "Resubmit" : "Submit"; ?>
                        </button>
                      </form>
                    </div>
                  </td>

                  <td>
                    <?php echo htmlspecialchars(number_format((float)$a['grade'], 2)); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <a class="back" href="student_subject.php?courseID=<?php echo (int)$courseID; ?>">
          Back to Subject
        </a>
      </div>
    </div>
  </div>
</body>

</html>