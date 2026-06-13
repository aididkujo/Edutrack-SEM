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
$courseID   = (int)($_GET['courseID'] ?? 0);

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

function clean_int($v, $min, $max, $default)
{
  $n = (int)$v;
  if ($n < $min || $n > $max) return $default;
  return $n;
}

function dt_local_to_sql($v)
{
  $v = trim((string)$v);
  if ($v === '') return null;
  return str_replace('T', ' ', $v) . ':00';
}

function quiz_owned(mysqli $conn, int $assessmentID, int $courseID, int $lecturerID): bool
{
  $chk = $conn->prepare("
    SELECT a.assessmentID
    FROM assessment a
    JOIN course c ON c.courseID = a.courseID
    WHERE a.assessmentID = ?
      AND a.courseID = ?
      AND a.type = 'Quiz'
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

  // CREATE QUIZ
  if ($action === 'create_quiz') {
    $title = trim($_POST['tittle'] ?? '');
    $openAtInput  = trim($_POST['openAt'] ?? '');
    $closeAtInput = trim($_POST['closeAt'] ?? '');
    $duration = clean_int($_POST['durationMinutes'] ?? 30, 5, 300, 30);

    if ($title === '') {
      $error = 'Quiz title is required';
    } elseif ($openAtInput === '' || $closeAtInput === '') {
      $error = 'Open and close date time are required';
    } else {
      $openAt  = dt_local_to_sql($openAtInput);
      $closeAt = dt_local_to_sql($closeAtInput);

      if (!$openAt || !$closeAt) {
        $error = 'Invalid date time format';
      } elseif (strtotime($closeAt) <= strtotime($openAt)) {
        $error = 'Close time must be after open time';
      } else {
        $type = 'Quiz';
        $createdDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime($closeAt));
        $isVisible = 1;

        $stmt = $conn->prepare("
          INSERT INTO assessment 
          (tittle, type, createdDate, openAt, closeAt, durationMinutes, dueDate, courseID, isVisible)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {
          $stmt->bind_param(
            "sssssisii",
            $title,
            $type,
            $createdDate,
            $openAt,
            $closeAt,
            $duration,
            $dueDate,
            $courseID,
            $isVisible
          );

          if ($stmt->execute()) {
            $success = 'Quiz created';
          } else {
            $error = 'Failed to create quiz';
          }

          $stmt->close();
        } else {
          $error = 'Database error when creating quiz';
        }
      }
    }
  }

  // UPDATE QUIZ AVAILABILITY
  if ($action === 'update_availability') {
    $assessmentID = (int)($_POST['assessmentID'] ?? 0);
    $openAtInput  = trim($_POST['openAt'] ?? '');
    $closeAtInput = trim($_POST['closeAt'] ?? '');
    $duration = clean_int($_POST['durationMinutes'] ?? 30, 5, 300, 30);

    if ($assessmentID <= 0) {
      $error = 'Invalid quiz';
    } elseif (!quiz_owned($conn, $assessmentID, $courseID, $lecturerID)) {
      $error = 'Quiz not found or no access';
    } elseif ($openAtInput === '' || $closeAtInput === '') {
      $error = 'Open and close date time are required';
    } else {
      $openAt  = dt_local_to_sql($openAtInput);
      $closeAt = dt_local_to_sql($closeAtInput);

      if (!$openAt || !$closeAt) {
        $error = 'Invalid date time format';
      } elseif (strtotime($closeAt) <= strtotime($openAt)) {
        $error = 'Close time must be after open time';
      } else {
        $dueDate = date('Y-m-d', strtotime($closeAt));

        $stmt = $conn->prepare("
          UPDATE assessment
          SET openAt = ?, closeAt = ?, durationMinutes = ?, dueDate = ?
          WHERE assessmentID = ? 
            AND courseID = ? 
            AND type = 'Quiz'
        ");

        if ($stmt) {
          $stmt->bind_param("ssisii", $openAt, $closeAt, $duration, $dueDate, $assessmentID, $courseID);

          if ($stmt->execute()) {
            $success = 'Availability updated';
          } else {
            $error = 'Failed to update availability';
          }

          $stmt->close();
        } else {
          $error = 'Database error when updating availability';
        }
      }
    }
  }

  // TOGGLE QUIZ VISIBILITY
  if ($action === 'toggle_visibility') {
    $assessmentID = (int)($_POST['assessmentID'] ?? 0);
    $isVisible = (int)($_POST['isVisible'] ?? -1);

    if ($assessmentID <= 0) {
      $error = 'Invalid quiz';
    } elseif ($isVisible !== 0 && $isVisible !== 1) {
      $error = 'Invalid visibility status';
    } elseif (!quiz_owned($conn, $assessmentID, $courseID, $lecturerID)) {
      $error = 'Quiz not found or no access';
    } else {
      $stmt = $conn->prepare("
        UPDATE assessment
        SET isVisible = ?
        WHERE assessmentID = ?
          AND courseID = ?
          AND type = 'Quiz'
      ");

      if ($stmt) {
        $stmt->bind_param("iii", $isVisible, $assessmentID, $courseID);

        if ($stmt->execute()) {
          $success = $isVisible === 1
            ? 'Quiz is now visible to students'
            : 'Quiz is now hidden from students';
        } else {
          $error = 'Failed to update quiz visibility';
        }

        $stmt->close();
      } else {
        $error = 'Database error when updating visibility';
      }
    }
  }

  // DELETE QUIZ
  if ($action === 'delete_quiz') {
    $assessmentID = (int)($_POST['assessmentID'] ?? 0);

    if ($assessmentID <= 0) {
      $error = 'Invalid quiz';
    } elseif (!quiz_owned($conn, $assessmentID, $courseID, $lecturerID)) {
      $error = 'Quiz not found or no access';
    } else {
      $conn->begin_transaction();

      try {
        $d1 = $conn->prepare("
          DELETE qa
          FROM quiz_answer qa
          JOIN quiz_attempt atp ON atp.attemptID = qa.attemptID
          WHERE atp.assessmentID = ?
        ");

        if ($d1) {
          $d1->bind_param("i", $assessmentID);
          $d1->execute();
          $d1->close();
        }

        $d2 = $conn->prepare("DELETE FROM quiz_attempt WHERE assessmentID = ?");
        if ($d2) {
          $d2->bind_param("i", $assessmentID);
          $d2->execute();
          $d2->close();
        }

        $d3 = $conn->prepare("
          DELETE qo
          FROM quiz_option qo
          JOIN quiz_question qq ON qq.questionID = qo.questionID
          WHERE qq.assessmentID = ?
        ");

        if ($d3) {
          $d3->bind_param("i", $assessmentID);
          $d3->execute();
          $d3->close();
        }

        $d4 = $conn->prepare("DELETE FROM quiz_question WHERE assessmentID = ?");
        if ($d4) {
          $d4->bind_param("i", $assessmentID);
          $d4->execute();
          $d4->close();
        }

        $d5 = $conn->prepare("DELETE FROM submission WHERE assessmentID = ?");
        if ($d5) {
          $d5->bind_param("i", $assessmentID);
          $d5->execute();
          $d5->close();
        }

        $d6 = $conn->prepare("DELETE FROM assessment WHERE assessmentID = ? AND courseID = ? AND type = 'Quiz'");
        $d6->bind_param("ii", $assessmentID, $courseID);
        $d6->execute();
        $d6->close();

        $conn->commit();
        $success = 'Quiz deleted';
      } catch (Throwable $e) {
        $conn->rollback();
        $error = 'Failed to delete quiz';
      }
    }
  }
}

$quizzes = [];

if ($course) {
  $sql = "
    SELECT 
      a.assessmentID, 
      a.tittle, 
      a.createdDate, 
      a.dueDate, 
      a.openAt, 
      a.closeAt, 
      a.durationMinutes,
      a.isVisible,
      (SELECT COUNT(*) 
       FROM quiz_question q 
       WHERE q.assessmentID = a.assessmentID) AS questionCount,
      (SELECT COUNT(*) 
       FROM quiz_attempt qa 
       WHERE qa.assessmentID = a.assessmentID 
       AND qa.status IN ('submitted','graded')) AS submittedCount
    FROM assessment a
    WHERE a.courseID = ? 
      AND a.type = 'Quiz'
    ORDER BY a.assessmentID DESC
  ";

  $stmt = $conn->prepare($sql);

  if ($stmt) {
    $stmt->bind_param("i", $courseID);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
      $quizzes[] = $row;
    }

    $stmt->close();
  } else {
    $error = $error ?: 'Quiz tables not ready';
  }
}

function to_dt_local($sqlDt)
{
  if (!$sqlDt) return '';
  return date('Y-m-d\TH:i', strtotime($sqlDt));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack Lecturer Quiz</title>
  <link rel="stylesheet" href="style.css">

  <style>
    .wrap {
      padding: 20px;
    }

    .msg {
      max-width: 980px;
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

    .card {
      max-width: 980px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.86);
      border-radius: 16px;
      padding: 16px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1.2fr 1fr 1fr 0.6fr auto;
      gap: 12px;
      align-items: end;
    }

    .field label {
      display: block;
      font-size: 12px;
      font-weight: 800;
      color: #111827;
      opacity: 0.85;
      margin-bottom: 6px;
    }

    .field input {
      width: 100%;
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      outline: none;
      background: #fff;
    }

    .hint {
      font-size: 12px;
      opacity: 0.7;
      margin-top: 10px;
    }

    .btn {
      padding: 10px 14px;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      background: #111827;
      color: #fff;
      font-weight: 900;
      white-space: nowrap;
    }

    .btnDanger {
      background: #7f1d1d;
    }

    .btnSoft {
      background: rgba(255, 255, 255, 0.85);
      color: #111827;
      border: 1px solid rgba(0, 0, 0, 0.12);
    }

    .btnShow {
      background: #15803d;
      color: #fff;
    }

    .btnHide {
      background: #ca8a04;
      color: #fff;
    }

    .list {
      max-width: 980px;
      margin: 16px auto 0 auto;
      display: grid;
      gap: 12px;
    }

    .item {
      background: #9fe6ee;
      border-radius: 16px;
      padding: 14px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      flex-wrap: wrap;
    }

    .title {
      font-weight: 900;
      color: #111827;
      font-size: 16px;
    }

    .meta {
      font-size: 12px;
      opacity: 0.85;
      margin-top: 6px;
      line-height: 1.5;
    }

    .right-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    .pill {
      font-size: 12px;
      font-weight: 900;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.75);
      color: #111827;
    }

    .visibility-visible {
      background: #dcfce7;
      color: #14532d;
    }

    .visibility-hidden {
      background: #fee2e2;
      color: #7f1d1d;
    }

    .btnLink {
      text-decoration: none;
      background: rgba(255, 255, 255, 0.85);
      color: #111827;
      padding: 9px 12px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 900;
      white-space: nowrap;
      display: inline-block;
    }

    .editBox {
      width: 100%;
      margin-top: 12px;
      background: rgba(255, 255, 255, 0.72);
      border-radius: 14px;
      padding: 12px;
      display: none;
    }

    .editGrid {
      display: grid;
      grid-template-columns: 1fr 1fr 180px auto;
      gap: 10px;
      align-items: end;
    }

    @media (max-width: 980px) {
      .form-grid {
        grid-template-columns: 1fr;
      }

      .editGrid {
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
        <h2><?php echo $course ? htmlspecialchars($course['courseName']) : 'Quiz'; ?></h2>
      </div>

      <a class="btnLink" href="lecturer_subject.php?courseID=<?php echo (int)$courseID; ?>">Back</a>

      <div class="wrap">
        <?php if ($error !== ''): ?>
          <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
          <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($course): ?>
          <div class="card">
            <form method="POST" action="">
              <input type="hidden" name="action" value="create_quiz">

              <div class="form-grid">
                <div class="field">
                  <label>Quiz title</label>
                  <input type="text" name="tittle" maxlength="255" placeholder="Example: Quiz 1" required>
                </div>

                <div class="field">
                  <label>Opens at</label>
                  <input type="datetime-local" name="openAt" required>
                </div>

                <div class="field">
                  <label>Closes at</label>
                  <input type="datetime-local" name="closeAt" required>
                </div>

                <div class="field">
                  <label>Duration (minutes)</label>
                  <input type="number" name="durationMinutes" min="5" max="300" value="30" required>
                </div>

                <div class="field">
                  <label>&nbsp;</label>
                  <button class="btn" type="submit">Create Quiz</button>
                </div>
              </div>

              <div class="hint">
                Opens at and closes at define when students can start the quiz.
                Duration is how long each student has after starting.
                New quizzes are visible to students by default.
              </div>
            </form>
          </div>

          <div class="list">
            <?php if (count($quizzes) === 0): ?>
              <div class="card" style="text-align:center; opacity:0.75;">No quizzes yet</div>
            <?php else: ?>
              <?php foreach ($quizzes as $q): ?>
                <?php
                $aid = (int)$q['assessmentID'];

                $openText  = $q['openAt']
                  ? date('Y-m-d H:i', strtotime($q['openAt']))
                  : 'Not set';

                $closeText = $q['closeAt']
                  ? date('Y-m-d H:i', strtotime($q['closeAt']))
                  : 'Not set';

                $dur = (int)($q['durationMinutes'] ?? 0);
                $durText = $dur > 0 ? ($dur . ' min') : 'Not set';

                $isVisible = (int)($q['isVisible'] ?? 1);
                $visibilityText = $isVisible === 1 ? 'Visible to Students' : 'Hidden from Students';
                $visibilityClass = $isVisible === 1 ? 'visibility-visible' : 'visibility-hidden';
                $nextVisibility = $isVisible === 1 ? 0 : 1;
                $visibilityButtonText = $isVisible === 1 ? 'Hide from Students' : 'Show to Students';
                $visibilityButtonClass = $isVisible === 1 ? 'btnHide' : 'btnShow';
                ?>

                <div class="item" id="quiz_<?php echo $aid; ?>">
                  <div style="flex:1;min-width:280px;">
                    <div class="title"><?php echo htmlspecialchars($q['tittle']); ?></div>

                    <div class="meta">
                      <div><b>Opens:</b> <?php echo htmlspecialchars($openText); ?></div>
                      <div><b>Closes:</b> <?php echo htmlspecialchars($closeText); ?></div>
                      <div><b>Duration:</b> <?php echo htmlspecialchars($durText); ?></div>

                      <div>
                        <b>Visibility:</b>
                        <span class="pill <?php echo $visibilityClass; ?>">
                          <?php echo htmlspecialchars($visibilityText); ?>
                        </span>
                      </div>

                      <div>
                        <b>Questions:</b> <?php echo (int)$q['questionCount']; ?>
                        &nbsp;|&nbsp;
                        <b>Submitted:</b> <?php echo (int)$q['submittedCount']; ?>
                      </div>
                    </div>

                    <div class="editBox" id="edit_<?php echo $aid; ?>">
                      <form method="POST" action="">
                        <input type="hidden" name="action" value="update_availability">
                        <input type="hidden" name="assessmentID" value="<?php echo $aid; ?>">

                        <div class="editGrid">
                          <div class="field" style="margin:0;">
                            <label>Opens at</label>
                            <input
                              type="datetime-local"
                              name="openAt"
                              value="<?php echo htmlspecialchars(to_dt_local($q['openAt'])); ?>"
                              required>
                          </div>

                          <div class="field" style="margin:0;">
                            <label>Closes at</label>
                            <input
                              type="datetime-local"
                              name="closeAt"
                              value="<?php echo htmlspecialchars(to_dt_local($q['closeAt'])); ?>"
                              required>
                          </div>

                          <div class="field" style="margin:0;">
                            <label>Duration (minutes)</label>
                            <input
                              type="number"
                              name="durationMinutes"
                              min="5"
                              max="300"
                              value="<?php echo (int)($q['durationMinutes'] ?? 30); ?>"
                              required>
                          </div>

                          <div class="field" style="margin:0;">
                            <label>&nbsp;</label>
                            <button class="btn" type="submit">Save</button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>

                  <div class="right-actions">
                    <span class="pill">Quiz</span>

                    <a class="btnLink" href="lecturer_quiz_builder.php?assessmentID=<?php echo $aid; ?>">
                      Edit Questions
                    </a>

                    <a class="btnLink" href="lecturer_quiz_grade.php?assessmentID=<?php echo $aid; ?>">
                      Grade
                    </a>

                    <button class="btn btnSoft" type="button" onclick="toggleEdit(<?php echo $aid; ?>)">
                      Modify Availability
                    </button>

                    <form method="POST" action="" style="margin:0;">
                      <input type="hidden" name="action" value="toggle_visibility">
                      <input type="hidden" name="assessmentID" value="<?php echo $aid; ?>">
                      <input type="hidden" name="isVisible" value="<?php echo $nextVisibility; ?>">

                      <button class="btn <?php echo $visibilityButtonClass; ?>" type="submit">
                        <?php echo htmlspecialchars($visibilityButtonText); ?>
                      </button>
                    </form>

                    <form
                      method="POST"
                      action=""
                      style="margin:0;"
                      onsubmit="return confirm('Delete this quiz and all related data?');">

                      <input type="hidden" name="action" value="delete_quiz">
                      <input type="hidden" name="assessmentID" value="<?php echo $aid; ?>">

                      <button class="btn btnDanger" type="submit">Delete</button>
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

  <script>
    function toggleEdit(id) {
      var box = document.getElementById('edit_' + id);
      if (!box) return;
      box.style.display = (box.style.display === 'block') ? 'none' : 'block';
    }
  </script>
</body>

</html>