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
$assessmentID = (int)($_GET['assessmentID'] ?? 0);

$error = '';
$success = '';

$assess = null;

if ($assessmentID > 0) {
  $sql = "
      SELECT a.assessmentID, a.tittle, a.courseID, c.courseName
      FROM assessment a
      JOIN course c ON c.courseID = a.courseID
      WHERE a.assessmentID = ? AND a.type = 'Course Work' AND c.userID = ?
      LIMIT 1
    ";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("ii", $assessmentID, $lecturerID);
    $stmt->execute();
    $assess = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

if (!$assess) {
  $error = 'Course work not found or no access';
}

$students = [];
$subMap = [];

if ($assess) {
  $sql = "
      SELECT u.userID, u.full_name, u.email
      FROM enrollment e
      JOIN user u ON u.userID = e.userID
      WHERE e.courseID = ? AND u.role = 'student'
      ORDER BY u.full_name
    ";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("i", $assess['courseID']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $students[] = $row;
    }
    $stmt->close();
  }

  $sql = "
      SELECT submissionID, userID, grade, submitDate, filePath, submissionText
      FROM submission
      WHERE assessmentID = ?
    ";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("i", $assessmentID);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $subMap[(int)$row['userID']] = $row;
    }
    $stmt->close();
  } else {
    $error = $error ?: 'Submission columns not ready. Run the ALTER TABLE SQL first.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assess) {
  $conn->begin_transaction();
  try {
    foreach ($students as $s) {
      $sid = (int)$s['userID'];
      $key = 'grade_' . $sid;
      $val = $_POST[$key] ?? '';

      if ($val === '') {
        continue;
      }

      $grade = (float)$val;

      $chk = $conn->prepare("SELECT submissionID FROM submission WHERE assessmentID = ? AND userID = ? ORDER BY submissionID DESC LIMIT 1");
      $chk->bind_param("ii", $assessmentID, $sid);
      $chk->execute();
      $row = $chk->get_result()->fetch_assoc();
      $chk->close();

      if ($row) {
        $subID = (int)$row['submissionID'];
        $u = $conn->prepare("UPDATE submission SET grade = ? WHERE submissionID = ?");
        $u->bind_param("di", $grade, $subID);
        $u->execute();
        $u->close();
      } else {
        $today = date('Y-m-d');
        $u = $conn->prepare("INSERT INTO submission (submitDate, grade, assessmentID, userID) VALUES (?, ?, ?, ?)");
        $u->bind_param("sdii", $today, $grade, $assessmentID, $sid);
        $u->execute();
        $u->close();
      }
    }

    $conn->commit();
    $success = 'Grades saved';
    header('Location: lecturer_coursework_grade.php?assessmentID=' . $assessmentID);
    exit;
  } catch (Exception $e) {
    $conn->rollback();
    $error = 'Failed to save grades';
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
  <title>EduTrack Lecturer Course Work Grade</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap {
      padding: 20px;
    }

    .box {
      max-width: 1100px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.85);
      border-radius: 14px;
      padding: 16px;
    }

    .msg {
      max-width: 1100px;
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

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 12px;
    }

    th,
    td {
      padding: 10px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.12);
      text-align: left;
      vertical-align: top;
    }

    input[type="number"] {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      outline: none;
      width: 160px;
    }

    .row {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    .btn {
      padding: 10px 14px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      background: #1f2937;
      color: #fff;
      font-weight: 900;
    }

    .btnLink {
      text-decoration: none;
      background: rgba(255, 255, 255, 0.85);
      color: #111827;
      padding: 8px 10px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 800;
      display: inline-block;
    }

    .small {
      font-size: 12px;
      opacity: 0.8;
    }

    .submissionBox {
      background: rgba(255, 255, 255, 0.85);
      border-radius: 10px;
      padding: 10px;
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
        <h2><?php echo $assess ? htmlspecialchars($assess['tittle']) : 'Grade'; ?></h2>
      </div>

      <div class="wrap">
        <?php if ($error !== ''): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="msg success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <?php if ($assess): ?>
          <div class="box">
            <div class="row">
              <div>
                <div style="font-weight:900;"><?php echo htmlspecialchars($assess['courseName']); ?></div>
                <div class="small">Course work submissions and grading</div>
              </div>
              <a class="btnLink" href="lecturer_coursework.php?courseID=<?php echo (int)$assess['courseID']; ?>">Back</a>
            </div>

            <form method="POST" action="" style="margin-top:12px;">
              <table>
                <thead>
                  <tr>
                    <th style="width: 26%;">Student</th>
                    <th style="width: 12%;">Submit date</th>
                    <th>Submission</th>
                    <th style="width: 12%;">Current grade</th>
                    <th style="width: 12%;">New grade</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($students) === 0): ?>
                    <tr>
                      <td colspan="5" style="opacity:0.75;">No students enrolled</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($students as $s): ?>
                      <?php
                      $sid = (int)$s['userID'];
                      $sub = $subMap[$sid] ?? null;
                      $curGrade = $sub ? $sub['grade'] : '';
                      $submitDate = $sub ? $sub['submitDate'] : '';
                      $filePath = $sub ? ($sub['filePath'] ?? '') : '';
                      $text = $sub ? ($sub['submissionText'] ?? '') : '';
                      ?>
                      <tr>
                        <td>
                          <div style="font-weight:800;"><?php echo htmlspecialchars($s['full_name']); ?></div>
                          <div class="small"><?php echo htmlspecialchars($s['email']); ?></div>
                        </td>
                        <td>
                          <?php echo $submitDate ? htmlspecialchars($submitDate) : 'Not submitted'; ?>
                        </td>
                        <td>
                          <div class="submissionBox">
                            <?php if ($filePath): ?>
                              <div class="small">File</div>
                              <a class="btnLink" href="<?php echo htmlspecialchars($filePath); ?>" target="_blank">Open submission</a>
                            <?php endif; ?>

                            <?php if ($text): ?>
                              <div style="height:8px;"></div>
                              <div class="small">Text</div>
                              <div style="white-space:pre-wrap;"><?php echo htmlspecialchars($text); ?></div>
                            <?php endif; ?>

                            <?php if (!$filePath && !$text): ?>
                              <span class="small">No submission content</span>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td>
                          <?php echo ($curGrade === '' && $submitDate === '') ? 'No grade' : htmlspecialchars($curGrade); ?>
                        </td>
                        <td>
                          <input type="number" step="0.5" name="grade_<?php echo $sid; ?>" placeholder="Enter grade">
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>

              <div style="height:12px;"></div>
              <div style="display:flex; justify-content:flex-end;">
                <button class="btn" type="submit">Save Grades</button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>