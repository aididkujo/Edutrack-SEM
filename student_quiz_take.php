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

$userID = (int)($_SESSION['userID'] ?? 0);
$fullName = $_SESSION['full_name'] ?? 'Student';
$assessmentID = (int)($_GET['assessmentID'] ?? 0);
$courseID = (int)($_GET['courseID'] ?? 0);

if ($userID <= 0 || $assessmentID <= 0 || $courseID <= 0) {
  header("Location: student_courses.php");
  exit;
}

function fmt_dt($dt)
{
  if (!$dt) return 'Not set';
  return date('Y-m-d H:i', strtotime($dt));
}

function load_questions(mysqli $conn, int $assessmentID): array
{
  $q = $conn->prepare("
    SELECT questionID, questionType, questionText, imagePath, marks, questionOrder
    FROM quiz_question
    WHERE assessmentID = ?
    ORDER BY questionOrder ASC, questionID ASC
  ");
  $q->bind_param("i", $assessmentID);
  $q->execute();
  $questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);
  $q->close();

  $out = [];
  foreach ($questions as $row) {
    $qid = (int)$row['questionID'];

    $o = $conn->prepare("
      SELECT optionID, optionText, optionOrder
      FROM quiz_option
      WHERE questionID = ?
      ORDER BY optionOrder ASC, optionID ASC
    ");
    $o->bind_param("i", $qid);
    $o->execute();
    $row['options'] = $o->get_result()->fetch_all(MYSQLI_ASSOC);
    $o->close();

    $out[] = $row;
  }
  return $out;
}

/* enrollment access */
$access = $conn->prepare("SELECT 1 FROM enrollment WHERE userID = ? AND courseID = ? LIMIT 1");
$access->bind_param("ii", $userID, $courseID);
$access->execute();
$ok = $access->get_result()->fetch_assoc();
$access->close();
if (!$ok) {
  header("Location: student_courses.php");
  exit;
}

/* quiz info */
$qstmt = $conn->prepare("
  SELECT tittle, openAt, closeAt, durationMinutes
  FROM assessment
  WHERE assessmentID = ? AND courseID = ? AND type = 'Quiz'
  LIMIT 1
");
$qstmt->bind_param("ii", $assessmentID, $courseID);
$qstmt->execute();
$quiz = $qstmt->get_result()->fetch_assoc();
$qstmt->close();

if (!$quiz) {
  header("Location: student_quiz_list.php?courseID=" . (int)$courseID);
  exit;
}

/* availability */
$nowTs = time();
$openAtTs = $quiz['openAt'] ? strtotime($quiz['openAt']) : 0;
$closeAtTs = $quiz['closeAt'] ? strtotime($quiz['closeAt']) : 0;
$durationMinutes = (int)($quiz['durationMinutes'] ?? 0);

if ($openAtTs > 0 && $nowTs < $openAtTs) {
  header("Location: student_quiz_list.php?courseID=" . (int)$courseID . "&msg=not_open");
  exit;
}
if ($closeAtTs > 0 && $nowTs > $closeAtTs) {
  header("Location: student_quiz_list.php?courseID=" . (int)$courseID . "&msg=closed");
  exit;
}
if ($durationMinutes <= 0) {
  header("Location: student_quiz_list.php?courseID=" . (int)$courseID . "&msg=duration_missing");
  exit;
}

/* attempt rule: only one attempt */
$attemptStmt = $conn->prepare("
  SELECT attemptID, status, startedAt
  FROM quiz_attempt
  WHERE assessmentID = ? AND userID = ?
  ORDER BY attemptID DESC
  LIMIT 1
");
$attemptStmt->bind_param("ii", $assessmentID, $userID);
$attemptStmt->execute();
$attempt = $attemptStmt->get_result()->fetch_assoc();
$attemptStmt->close();

if ($attempt && in_array(($attempt['status'] ?? ''), ['submitted', 'graded'], true)) {
  header("Location: student_quiz_review.php?assessmentID=" . (int)$assessmentID . "&courseID=" . (int)$courseID);
  exit;
}

/* create or continue */
if (!$attempt) {
  $ins = $conn->prepare("INSERT INTO quiz_attempt (assessmentID, userID, startedAt, status) VALUES (?, ?, NOW(), 'in_progress')");
  $ins->bind_param("ii", $assessmentID, $userID);
  $ins->execute();
  $attemptID = (int)$conn->insert_id;
  $ins->close();
  $attemptStartedTs = $nowTs;
} else {
  $attemptID = (int)$attempt['attemptID'];
  $attemptStartedTs = $attempt['startedAt'] ? strtotime($attempt['startedAt']) : $nowTs;
}

/* attempt end time */
$attemptEndTs = $attemptStartedTs + ($durationMinutes * 60);
if ($closeAtTs > 0 && $attemptEndTs > $closeAtTs) $attemptEndTs = $closeAtTs;
$secondsLeft = $attemptEndTs - time();
if ($secondsLeft < 0) $secondsLeft = 0;

$success = '';
$error = '';

/* prefill answers when continue */
$prefill = [];
if ($attemptID > 0) {
  $p = $conn->prepare("
    SELECT questionID, selectedOptionID, answerText
    FROM quiz_answer
    WHERE attemptID = ?
  ");
  $p->bind_param("i", $attemptID);
  $p->execute();
  $rows = $p->get_result()->fetch_all(MYSQLI_ASSOC);
  $p->close();
  foreach ($rows as $r) {
    $qid = (int)$r['questionID'];
    $prefill[$qid] = [
      'selectedOptionID' => $r['selectedOptionID'],
      'answerText' => $r['answerText']
    ];
  }
}

/* submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (time() >= $attemptEndTs) {
    header("Location: student_quiz_review.php?assessmentID=" . (int)$assessmentID . "&courseID=" . (int)$courseID);
    exit;
  }

  $questions = load_questions($conn, $assessmentID);

  $conn->begin_transaction();
  try {
    $del = $conn->prepare("DELETE FROM quiz_answer WHERE attemptID = ?");
    $del->bind_param("i", $attemptID);
    $del->execute();
    $del->close();

    $totalScore = 0.0;

    foreach ($questions as $q) {
      $qid = (int)$q['questionID'];
      $qtype = (string)$q['questionType'];
      $marks = (float)$q['marks'];

      $selectedOptionID = null;
      $answerText = null;
      $score = 0.0;

      if ($qtype === 'objective') {
        $picked = isset($_POST['q_' . $qid]) ? (int)$_POST['q_' . $qid] : 0;
        if ($picked > 0) {
          $selectedOptionID = $picked;

          $cs = $conn->prepare("SELECT isCorrect FROM quiz_option WHERE optionID = ? AND questionID = ? LIMIT 1");
          $cs->bind_param("ii", $selectedOptionID, $qid);
          $cs->execute();
          $isCorrect = $cs->get_result()->fetch_assoc();
          $cs->close();

          if ($isCorrect && (int)$isCorrect['isCorrect'] === 1) {
            $score = $marks;
          }
        }
        $totalScore += $score;
      } else {
        $answerText = isset($_POST['q_' . $qid]) ? trim((string)$_POST['q_' . $qid]) : '';
      }

      $insA = $conn->prepare("
        INSERT INTO quiz_answer (attemptID, questionID, selectedOptionID, answerText, score)
        VALUES (?, ?, ?, ?, ?)
      ");
      $sel = $selectedOptionID;
      $txt = $answerText;
      $insA->bind_param("iiisd", $attemptID, $qid, $sel, $txt, $score);
      $insA->execute();
      $insA->close();
    }

    $up = $conn->prepare("
      UPDATE quiz_attempt
      SET submittedAt = NOW(),
          totalScore = ?,
          status = 'submitted'
      WHERE attemptID = ? AND userID = ?
    ");
    $up->bind_param("dii", $totalScore, $attemptID, $userID);
    $up->execute();
    $up->close();

    $conn->commit();

    header("Location: student_quiz_review.php?assessmentID=" . (int)$assessmentID . "&courseID=" . (int)$courseID);
    exit;
  } catch (Throwable $e) {
    $conn->rollback();
    $error = "Submission failed. Please try again.";
  }
}

$questions = load_questions($conn, $assessmentID);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack Take Quiz</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap {
      padding: 18px 26px;
    }

    .box {
      margin-bottom: 18px;
      padding: 14px;
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.85);
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(31, 41, 55, 0.08);
      font-size: 12px;
    }

    .btn {
      display: inline-block;
      padding: 8px 14px;
      border-radius: 10px;
      background: #1f2937;
      color: #fff;
      text-decoration: none;
      border: none;
      cursor: pointer;
    }

    .muted {
      color: #6b7280;
      font-size: 14px;
    }

    textarea {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, 0.18);
    }

    .timerBar {
      padding: 12px 14px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.85);
      display: flex;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      border: 1px solid rgba(0, 0, 0, 0.08);
      margin-bottom: 16px;
    }

    .timerText {
      font-weight: 800;
    }

    .small {
      font-size: 12px;
      opacity: 0.85;
    }

    .qimg {
      max-width: 100%;
      height: auto;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, 0.12);
      background: rgba(255, 255, 255, 0.65);
      margin: 10px 0 6px 0;
      display: block;
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
        <li><a href="student_courses.php" style="font-weight:700;text-decoration:underline;">My Courses</a></li>
        <li><a href="student_progress.php">My Progress</a></li>
        <li><a href="student_myfeedback.php">My Feedback</a></li>
        <li><a href="lectevaluationlist.php">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="topbar">
        <h2><?php echo htmlspecialchars($fullName); ?></h2>
      </div>

      <div class="wrap">
        <h2 style="margin:0;"><?php echo htmlspecialchars($quiz['tittle']); ?></h2>
        <div class="muted">Answer and submit</div>
      </div>

      <div class="wrap">
        <?php if ($error): ?><div class="muted"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="muted"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
      </div>

      <div class="wrap">
        <div class="timerBar">
          <div>
            <div class="small">Opens at: <b><?php echo htmlspecialchars(fmt_dt($quiz['openAt'])); ?></b></div>
            <div class="small">Closes at: <b><?php echo htmlspecialchars(fmt_dt($quiz['closeAt'])); ?></b></div>
            <div class="small">Duration: <b><?php echo (int)$durationMinutes; ?> minutes</b></div>
          </div>
          <div>
            <div class="timerText">Time left: <span id="timer">00:00</span></div>
            <div class="small">Attempt ends at: <b><?php echo date('Y-m-d H:i', $attemptEndTs); ?></b></div>
          </div>
        </div>
      </div>

      <div class="wrap">
        <?php if (empty($questions)): ?>
          <div class="muted">No questions added yet.</div>
        <?php else: ?>
          <form id="quizForm" method="POST" action="">
            <input type="hidden" id="serverSecondsLeft" value="<?php echo (int)$secondsLeft; ?>">

            <?php foreach ($questions as $idx => $q): ?>
              <?php
              $qid = (int)$q['questionID'];
              $savedSel = $prefill[$qid]['selectedOptionID'] ?? null;
              $savedTxt = $prefill[$qid]['answerText'] ?? '';
              ?>
              <div class="box">
                <div style="font-weight:700;margin-bottom:10px;">
                  Q<?php echo ($idx + 1); ?>. <?php echo htmlspecialchars($q['questionText']); ?>
                  <span class="badge" style="margin-left:10px;">
                    <?php echo htmlspecialchars($q['questionType']); ?> | <?php echo htmlspecialchars($q['marks']); ?> marks
                  </span>
                </div>

                <?php if (!empty($q['imagePath'])): ?>
                  <img class="qimg" src="<?php echo htmlspecialchars($q['imagePath']); ?>" alt="Question image">
                <?php endif; ?>

                <?php if ($q['questionType'] === 'objective'): ?>
                  <?php if (empty($q['options'])): ?>
                    <div class="muted">No options created.</div>
                  <?php else: ?>
                    <?php foreach ($q['options'] as $opt): ?>
                      <?php $oid = (int)$opt['optionID']; ?>
                      <label style="display:block;margin:6px 0;">
                        <input
                          type="radio"
                          name="q_<?php echo $qid; ?>"
                          value="<?php echo $oid; ?>"
                          <?php echo ($savedSel !== null && (int)$savedSel === $oid) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($opt['optionText']); ?>
                      </label>
                    <?php endforeach; ?>
                  <?php endif; ?>
                <?php else: ?>
                  <textarea
                    name="q_<?php echo $qid; ?>"
                    rows="4"
                    placeholder="Write answer here"><?php echo htmlspecialchars($savedTxt); ?></textarea>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>

            <button class="btn" type="submit" id="submitBtn">Submit Quiz</button>
            <a class="btn" href="student_quiz_list.php?courseID=<?php echo (int)$courseID; ?>" style="margin-left:10px;">Back</a>
          </form>

          <script>
            (function() {
              var seconds = parseInt(document.getElementById('serverSecondsLeft').value || '0', 10);
              var timerEl = document.getElementById('timer');
              var form = document.getElementById('quizForm');
              var submitBtn = document.getElementById('submitBtn');

              function pad(n) {
                return (n < 10 ? '0' : '') + n;
              }

              function render() {
                var m = Math.floor(seconds / 60);
                var s = seconds % 60;
                timerEl.textContent = pad(m) + ':' + pad(s);
              }

              function tick() {
                if (seconds <= 0) {
                  render();
                  if (submitBtn) submitBtn.disabled = true;
                  if (form) form.submit();
                  return;
                }
                render();
                seconds -= 1;
                setTimeout(tick, 1000);
              }

              tick();
            })();
          </script>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>