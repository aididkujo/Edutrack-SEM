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
$attemptID = (int)($_GET['attemptID'] ?? 0);

$error = '';
$success = '';

$quiz = null;

if ($assessmentID > 0) {
  $sql = "
      SELECT a.assessmentID, a.tittle, a.courseID, c.courseName
      FROM assessment a
      JOIN course c ON c.courseID = a.courseID
      WHERE a.assessmentID = ? AND a.type = 'Quiz' AND c.userID = ?
    ";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("ii", $assessmentID, $lecturerID);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

if (!$quiz) {
  $error = 'Quiz not found or no access';
}

function clamp_score($value, $min, $max)
{
  $v = (float)$value;
  if ($v < $min) return $min;
  if ($v > $max) return $max;
  return $v;
}

$attempt = null;
$rows = [];

if ($quiz && $attemptID > 0) {
  $sql = "
      SELECT qa.attemptID, qa.assessmentID, qa.userID, qa.startedAt, qa.submittedAt, qa.totalScore, qa.status,
             u.full_name, u.email
      FROM quiz_attempt qa
      JOIN user u ON u.userID = qa.userID
      WHERE qa.attemptID = ? AND qa.assessmentID = ?
      LIMIT 1
    ";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("ii", $attemptID, $assessmentID);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }

  if (!$attempt) {
    $error = 'Attempt not found';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz && $attemptID > 0) {
  $action = $_POST['action'] ?? '';

  if ($action === 'grade_attempt') {
    $sql = "
          SELECT q.questionID, q.questionType, q.marks, a.answerID
          FROM quiz_question q
          JOIN quiz_answer a ON a.questionID = q.questionID
          WHERE q.assessmentID = ? AND a.attemptID = ? AND q.questionType = 'subjective'
          ORDER BY q.questionOrder, q.questionID
        ";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
      $error = 'Quiz tables not ready';
    } else {
      $stmt->bind_param("ii", $assessmentID, $attemptID);
      $stmt->execute();
      $res = $stmt->get_result();

      $subjective = [];
      while ($r = $res->fetch_assoc()) {
        $subjective[] = $r;
      }
      $stmt->close();

      $conn->begin_transaction();
      try {
        foreach ($subjective as $s) {
          $answerID = (int)$s['answerID'];
          $marks = (float)$s['marks'];
          $scoreKey = 'score_' . $answerID;
          $scoreInput = $_POST[$scoreKey] ?? 0;
          $score = clamp_score($scoreInput, 0, $marks);

          $u = $conn->prepare("UPDATE quiz_answer SET score = ? WHERE answerID = ? AND attemptID = ?");
          if ($u) {
            $u->bind_param("dii", $score, $answerID, $attemptID);
            $u->execute();
            $u->close();
          }
        }

        $sumStmt = $conn->prepare("SELECT COALESCE(SUM(score), 0) AS total FROM quiz_answer WHERE attemptID = ?");
        $sumStmt->bind_param("i", $attemptID);
        $sumStmt->execute();
        $totalRow = $sumStmt->get_result()->fetch_assoc();
        $sumStmt->close();

        $total = (float)($totalRow['total'] ?? 0);

        $updAttempt = $conn->prepare("UPDATE quiz_attempt SET totalScore = ?, status = 'graded' WHERE attemptID = ? AND assessmentID = ?");
        $updAttempt->bind_param("dii", $total, $attemptID, $assessmentID);
        $updAttempt->execute();
        $updAttempt->close();

        $userIdForAttempt = 0;
        $getUser = $conn->prepare("SELECT userID FROM quiz_attempt WHERE attemptID = ? LIMIT 1");
        $getUser->bind_param("i", $attemptID);
        $getUser->execute();
        $urow = $getUser->get_result()->fetch_assoc();
        $getUser->close();
        if ($urow) $userIdForAttempt = (int)$urow['userID'];

        if ($userIdForAttempt > 0) {
          $chk = $conn->prepare("SELECT submissionID FROM submission WHERE assessmentID = ? AND userID = ? ORDER BY submissionID DESC LIMIT 1");
          $chk->bind_param("ii", $assessmentID, $userIdForAttempt);
          $chk->execute();
          $sub = $chk->get_result()->fetch_assoc();
          $chk->close();

          if ($sub) {
            $subID = (int)$sub['submissionID'];
            $up = $conn->prepare("UPDATE submission SET grade = ? WHERE submissionID = ?");
            $up->bind_param("di", $total, $subID);
            $up->execute();
            $up->close();
          } else {
            $today = date('Y-m-d');
            $ins = $conn->prepare("INSERT INTO submission (submitDate, grade, assessmentID, userID) VALUES (?, ?, ?, ?)");
            $ins->bind_param("sdii", $today, $total, $assessmentID, $userIdForAttempt);
            $ins->execute();
            $ins->close();
          }
        }

        $conn->commit();
        $success = 'Grading saved';
      } catch (Exception $e) {
        $conn->rollback();
        $error = 'Failed to save grading';
      }
    }
  }
}

if ($quiz && $attemptID > 0 && !$error) {
  $sql = "
      SELECT
        q.questionID,
        q.questionOrder,
        q.questionType,
        q.questionText,
        q.marks,
        a.answerID,
        a.selectedOptionID,
        a.answerText,
        a.score,
        o.optionText AS selectedOptionText,
        oc.optionID AS correctOptionID,
        oc.optionText AS correctOptionText
      FROM quiz_question q
      LEFT JOIN quiz_answer a ON a.questionID = q.questionID AND a.attemptID = ?
      LEFT JOIN quiz_option o ON o.optionID = a.selectedOptionID
      LEFT JOIN quiz_option oc ON oc.questionID = q.questionID AND oc.isCorrect = 1
      WHERE q.assessmentID = ?
      ORDER BY q.questionOrder, q.questionID
    ";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("ii", $attemptID, $assessmentID);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      $rows[] = $r;
    }
    $stmt->close();
  } else {
    $error = 'Quiz tables not ready';
  }
}

$attempts = [];
if ($quiz && $attemptID === 0 && !$error) {
  $sql = "
      SELECT qa.attemptID, qa.userID, qa.submittedAt, qa.totalScore, qa.status,
             u.full_name, u.email
      FROM quiz_attempt qa
      JOIN user u ON u.userID = qa.userID
      WHERE qa.assessmentID = ? AND qa.status IN ('submitted','graded')
      ORDER BY qa.submittedAt DESC, qa.attemptID DESC
    ";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param("i", $assessmentID);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      $attempts[] = $r;
    }
    $stmt->close();
  } else {
    $error = 'Quiz tables not ready';
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
  <title>EduTrack Lecturer Quiz Grade</title>
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

    .box {
      max-width: 980px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.85);
      border-radius: 14px;
      padding: 16px;
    }

    .titleRow {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    .btnLink {
      text-decoration: none;
      background: rgba(255, 255, 255, 0.85);
      color: #111827;
      padding: 8px 10px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 800;
    }

    .list {
      max-width: 980px;
      margin: 14px auto 0 auto;
      display: grid;
      gap: 10px;
    }

    .item {
      background: #9fe6ee;
      border-radius: 14px;
      padding: 14px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    .item .name {
      font-weight: 900;
      color: #111827;
    }

    .item .meta {
      font-size: 12px;
      opacity: 0.85;
      margin-top: 4px;
    }

    .pill {
      font-size: 12px;
      font-weight: 900;
      padding: 4px 8px;
      border-radius: 999px;
      background: rgba(31, 41, 55, 0.1);
    }

    .qcard {
      max-width: 980px;
      margin: 14px auto 0 auto;
      background: #9fe6ee;
      border-radius: 14px;
      padding: 14px;
    }

    .qtitle {
      font-weight: 900;
      color: #111827;
    }

    .qmeta {
      font-size: 12px;
      opacity: 0.85;
      margin-top: 4px;
    }

    .ans {
      margin-top: 10px;
      background: rgba(255, 255, 255, 0.85);
      border-radius: 12px;
      padding: 12px;
    }

    .ansLine {
      margin-top: 6px;
      font-size: 13px;
    }

    .scoreRow {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
      margin-top: 10px;
    }

    .scoreRow input {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      outline: none;
      max-width: 160px;
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
        <h2><?php echo $quiz ? htmlspecialchars($quiz['tittle']) : 'Quiz Grade'; ?></h2>
      </div>

      <div class="wrap">
        <?php if ($error !== ''): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="msg success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <?php if ($quiz && $attemptID === 0 && $error === ''): ?>
          <div class="box">
            <div class="titleRow">
              <div>
                <div style="font-weight:900;"><?php echo htmlspecialchars($quiz['courseName']); ?></div>
                <div style="font-size:12px; opacity:0.8; margin-top:4px;">Attempts list</div>
              </div>
              <a class="btnLink" href="lecturer_quiz.php?courseID=<?php echo (int)$quiz['courseID']; ?>">Back</a>
            </div>
          </div>

          <div class="list">
            <?php if (count($attempts) === 0): ?>
              <div class="box" style="text-align:center; opacity:0.75;">No submitted attempts</div>
            <?php else: ?>
              <?php foreach ($attempts as $a): ?>
                <div class="item">
                  <div>
                    <div class="name"><?php echo htmlspecialchars($a['full_name']); ?></div>
                    <div class="meta">
                      <?php echo htmlspecialchars($a['email']); ?>
                      <?php echo $a['submittedAt'] ? ' , Submitted ' . htmlspecialchars($a['submittedAt']) : ''; ?>
                      <?php echo ' , Score ' . htmlspecialchars($a['totalScore']); ?>
                    </div>
                  </div>
                  <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span class="pill"><?php echo htmlspecialchars($a['status']); ?></span>
                    <a class="btnLink" href="lecturer_quiz_grade.php?assessmentID=<?php echo (int)$assessmentID; ?>&attemptID=<?php echo (int)$a['attemptID']; ?>">Open</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($quiz && $attemptID > 0 && $error === ''): ?>
          <div class="box">
            <div class="titleRow">
              <div>
                <div style="font-weight:900;"><?php echo htmlspecialchars($attempt['full_name']); ?></div>
                <div style="font-size:12px; opacity:0.8; margin-top:4px;">
                  <?php echo htmlspecialchars($attempt['email']); ?>
                  <?php echo $attempt['submittedAt'] ? ' , Submitted ' . htmlspecialchars($attempt['submittedAt']) : ''; ?>
                  <?php echo ' , Status ' . htmlspecialchars($attempt['status']); ?>
                </div>
              </div>
              <a class="btnLink" href="lecturer_quiz_grade.php?assessmentID=<?php echo (int)$assessmentID; ?>">Back to list</a>
            </div>
          </div>

          <form method="POST" action="lecturer_quiz_grade.php?assessmentID=<?php echo (int)$assessmentID; ?>&attemptID=<?php echo (int)$attemptID; ?>">
            <input type="hidden" name="action" value="grade_attempt">

            <?php foreach ($rows as $r): ?>
              <?php
              $qType = $r['questionType'] ?? '';
              $marks = (float)($r['marks'] ?? 0);
              $score = (float)($r['score'] ?? 0);
              $answerID = (int)($r['answerID'] ?? 0);
              ?>
              <div class="qcard">
                <div class="qtitle">
                  Q<?php echo (int)$r['questionOrder']; ?> <?php echo htmlspecialchars($r['questionText']); ?>
                </div>
                <div class="qmeta">
                  Type <?php echo htmlspecialchars($qType); ?> , Marks <?php echo htmlspecialchars($marks); ?>
                </div>

                <div class="ans">
                  <?php if ($qType === 'objective'): ?>
                    <div class="ansLine">
                      Selected: <?php echo $r['selectedOptionText'] ? htmlspecialchars($r['selectedOptionText']) : 'No answer'; ?>
                    </div>
                    <div class="ansLine">
                      Correct: <?php echo $r['correctOptionText'] ? htmlspecialchars($r['correctOptionText']) : 'Not set'; ?>
                    </div>
                    <div class="scoreRow">
                      <div class="pill">Auto score <?php echo htmlspecialchars($score); ?></div>
                    </div>
                  <?php else: ?>
                    <div class="ansLine">
                      Answer: <?php echo ($r['answerText'] !== null && $r['answerText'] !== '') ? nl2br(htmlspecialchars($r['answerText'])) : 'No answer'; ?>
                    </div>
                    <div class="scoreRow">
                      <label style="font-weight:900;">Score</label>
                      <input
                        type="number"
                        step="0.5"
                        min="0"
                        max="<?php echo htmlspecialchars($marks); ?>"
                        name="score_<?php echo $answerID; ?>"
                        value="<?php echo htmlspecialchars($score); ?>"
                        required>
                      <span style="font-size:12px; opacity:0.8;">Max <?php echo htmlspecialchars($marks); ?></span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>

            <div class="box" style="margin-top:14px; display:flex; justify-content:flex-end;">
              <button class="btn" type="submit">Save Grading</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>