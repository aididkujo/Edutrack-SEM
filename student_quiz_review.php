<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_role('student');

$userID = (int)($_SESSION['userID'] ?? 0);
$fullName = $_SESSION['full_name'] ?? 'Student';
$assessmentID = (int)($_GET['assessmentID'] ?? 0);
$courseID = (int)($_GET['courseID'] ?? 0);

if ($userID <= 0 || $assessmentID <= 0 || $courseID <= 0) {
  header("Location: student_courses.php");
  exit;
}

/* enrollment check */
$access = $conn->prepare("SELECT 1 FROM enrollment WHERE userID = ? AND courseID = ? LIMIT 1");
$access->bind_param("ii", $userID, $courseID);
$access->execute();
$ok = $access->get_result()->fetch_assoc();
$access->close();
if (!$ok) { header("Location: student_courses.php"); exit; }

/* quiz title */
$qz = $conn->prepare("
  SELECT tittle
  FROM assessment
  WHERE assessmentID = ? AND courseID = ? AND type='Quiz'
  LIMIT 1
");
$qz->bind_param("ii", $assessmentID, $courseID);
$qz->execute();
$quiz = $qz->get_result()->fetch_assoc();
$qz->close();
if (!$quiz) { header("Location: student_quiz_list.php?courseID=".$courseID); exit; }

/* attempt must be submitted or graded */
$a = $conn->prepare("
  SELECT attemptID, totalScore, status, submittedAt
  FROM quiz_attempt
  WHERE assessmentID = ? AND userID = ?
    AND status IN ('submitted','graded')
  ORDER BY attemptID DESC
  LIMIT 1
");
$a->bind_param("ii", $assessmentID, $userID);
$a->execute();
$attempt = $a->get_result()->fetch_assoc();
$a->close();
if (!$attempt) { header("Location: student_quiz_list.php?courseID=".$courseID); exit; }

$attemptID = (int)$attempt['attemptID'];
$totalScore = $attempt['totalScore'] ?? '';
$status = $attempt['status'] ?? '';
$submittedAt = $attempt['submittedAt'] ?? '';

/* answers + question details */
$q = $conn->prepare("
  SELECT
    qq.questionID,
    qq.questionOrder,
    qq.questionType,
    qq.questionText,
    qq.imagePath,
    qq.marks,
    qa.selectedOptionID,
    qa.answerText,
    qa.score
  FROM quiz_answer qa
  JOIN quiz_question qq ON qq.questionID = qa.questionID
  WHERE qa.attemptID = ?
  ORDER BY qq.questionOrder ASC, qq.questionID ASC
");
$q->bind_param("i", $attemptID);
$q->execute();
$rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$q->close();

/* load options for objective questions to show text */
$optionsByQuestion = [];
$objectiveQids = [];
foreach ($rows as $r) {
  if (($r['questionType'] ?? '') === 'objective') {
    $objectiveQids[] = (int)$r['questionID'];
  }
}
$objectiveQids = array_values(array_unique($objectiveQids));

foreach ($objectiveQids as $qid) {
  $o = $conn->prepare("
    SELECT optionID, optionText, isCorrect
    FROM quiz_option
    WHERE questionID = ?
    ORDER BY optionOrder ASC, optionID ASC
  ");
  $o->bind_param("i", $qid);
  $o->execute();
  $optionsByQuestion[$qid] = $o->get_result()->fetch_all(MYSQLI_ASSOC);
  $o->close();
}

function fmt_dt($dt) {
  if (!$dt) return '-';
  return date('Y-m-d H:i', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack - Quiz Review</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap{padding:18px 26px;}
    .panel{
      max-width:980px;margin:0 auto 14px auto;
      background:rgba(255,255,255,0.86);
      border-radius:16px;padding:16px;
      border:1px solid rgba(0,0,0,0.08);
    }
    .titleRow{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start;}
    .h1{font-size:20px;font-weight:900;margin:0;color:#111827;}
    .muted{color:#6b7280;font-size:14px;}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;background:rgba(31,41,55,0.08);font-size:12px;font-weight:900;}
    .btn{display:inline-block;padding:8px 14px;border-radius:10px;background:#1f2937;color:#fff;text-decoration:none;border:none;cursor:pointer;font-weight:900;}
    .grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:12px;}
    .kpi{background:rgba(255,255,255,0.75);border:1px solid rgba(0,0,0,0.08);border-radius:14px;padding:12px;}
    .kpi b{display:block;font-size:12px;opacity:0.75;margin-bottom:6px;}
    .kpi span{font-size:16px;font-weight:900;color:#111827;}
    @media (max-width: 900px){ .grid{grid-template-columns:1fr;} }

    .qCard{
      max-width:980px;margin:0 auto 12px auto;
      background:#9fe6ee;border-radius:16px;padding:14px;
      border:1px solid rgba(0,0,0,0.08);
    }
    .qHead{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start;}
    .qText{font-weight:900;font-size:15px;color:#111827;}
    .qMeta{margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;}
    .qimg{
      max-width:100%;height:auto;border-radius:12px;
      border:1px solid rgba(0,0,0,0.12);
      background:rgba(255,255,255,0.65);
      margin:10px 0 6px 0;display:block;
    }

    .ansBox{
      background:rgba(255,255,255,0.78);
      border-radius:14px;padding:12px;margin-top:10px;
      border:1px solid rgba(0,0,0,0.08);
    }
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:8px;}
    .pillOk{font-size:12px;font-weight:900;padding:6px 10px;border-radius:999px;background:#16a34a;color:#fff;}
    .pillNo{font-size:12px;font-weight:900;padding:6px 10px;border-radius:999px;background:rgba(17,24,39,0.15);color:#111827;}
    .opt{
      padding:10px;border-radius:12px;
      border:1px solid rgba(0,0,0,0.08);
      background:rgba(255,255,255,0.85);
      margin-top:8px;
      display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;
    }
    .opt b{font-weight:900;}
    .scoreBox{
      margin-top:10px;
      display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;
      font-size:13px;
    }
    .scoreBox .big{font-size:14px;font-weight:900;color:#111827;}
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
    <img src="assets/profile.png" class="profile-icon" alt="Profile">
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
        <div class="panel">
          <div class="titleRow">
            <div>
              <h2 class="h1"><?php echo htmlspecialchars($quiz['tittle']); ?></h2>
              <div class="muted">Review submitted answers</div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <span class="badge"><?php echo htmlspecialchars($status); ?></span>
              <a class="btn" href="student_quiz_list.php?courseID=<?php echo (int)$courseID; ?>">Back</a>
            </div>
          </div>

          <div class="grid">
            <div class="kpi">
              <b>Submitted at</b>
              <span><?php echo htmlspecialchars(fmt_dt($submittedAt)); ?></span>
            </div>
            <div class="kpi">
              <b>Total score</b>
              <span><?php echo ($totalScore !== '' ? htmlspecialchars($totalScore) : '-'); ?></span>
            </div>
            <div class="kpi">
              <b>Attempt status</b>
              <span><?php echo htmlspecialchars($status); ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="wrap">
        <?php if (empty($rows)): ?>
          <div class="panel" style="text-align:center;opacity:0.75;">No answers found for this attempt.</div>
        <?php else: ?>
          <?php foreach ($rows as $idx => $r): ?>
            <?php
              $qid = (int)$r['questionID'];
              $qtype = (string)($r['questionType'] ?? '');
              $selectedOptionID = $r['selectedOptionID'];
              $answerText = $r['answerText'];
              $score = $r['score'];
              $marks = $r['marks'];
              $opts = $optionsByQuestion[$qid] ?? [];

              $pickedText = '';
              $pickedIsCorrect = null;

              if ($qtype === 'objective' && $selectedOptionID) {
                foreach ($opts as $op) {
                  if ((int)$op['optionID'] === (int)$selectedOptionID) {
                    $pickedText = (string)$op['optionText'];
                    $pickedIsCorrect = ((int)$op['isCorrect'] === 1);
                    break;
                  }
                }
              }
            ?>
            <div class="qCard">
              <div class="qHead">
                <div style="flex:1;min-width:260px;">
                  <div class="qText">
                    Q<?php echo ($idx + 1); ?>. <?php echo htmlspecialchars($r['questionText']); ?>
                  </div>
                  <div class="qMeta">
                    <span class="badge"><?php echo htmlspecialchars($qtype); ?></span>
                    <span class="badge"><?php echo htmlspecialchars($marks); ?> marks</span>
                  </div>
                </div>
              </div>

              <?php if (!empty($r['imagePath'])): ?>
                <img class="qimg" src="<?php echo htmlspecialchars($r['imagePath']); ?>" alt="Question image">
              <?php endif; ?>

              <div class="ansBox">
                <?php if ($qtype === 'objective'): ?>
                  <div class="muted" style="font-weight:900;color:#111827;">Selected answer</div>

                  <?php if (!$selectedOptionID): ?>
                    <div class="row"><span class="pillNo">No answer selected</span></div>
                  <?php else: ?>
                    <div class="opt">
                      <div>
                        <b><?php echo htmlspecialchars($pickedText !== '' ? $pickedText : ('Option ID '.$selectedOptionID)); ?></b>
                        <div class="muted">Selected Option ID: <?php echo (int)$selectedOptionID; ?></div>
                      </div>
                      <div>
                        <?php if ($pickedIsCorrect === true): ?>
                          <span class="pillOk">Correct</span>
                        <?php elseif ($pickedIsCorrect === false): ?>
                          <span class="pillNo">Not correct</span>
                        <?php else: ?>
                          <span class="pillNo">Not evaluated</span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <?php if (!empty($opts)): ?>
                      <div class="muted" style="margin-top:10px;">Options list</div>
                      <?php foreach ($opts as $op): ?>
                        <?php
                          $isPicked = ((int)$op['optionID'] === (int)$selectedOptionID);
                          $isCorrect = ((int)$op['isCorrect'] === 1);
                        ?>
                        <div class="opt" style="<?php echo $isPicked ? 'outline:2px solid rgba(17,24,39,0.22);' : ''; ?>">
                          <div>
                            <b><?php echo htmlspecialchars($op['optionText']); ?></b>
                            <div class="muted">Option ID: <?php echo (int)$op['optionID']; ?></div>
                          </div>
                          <div class="row">
                            <?php if ($isCorrect): ?><span class="pillOk">Correct</span><?php endif; ?>
                            <?php if ($isPicked): ?><span class="pillNo">Selected</span><?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  <?php endif; ?>

                <?php else: ?>
                  <div class="muted" style="font-weight:900;color:#111827;">Written answer</div>
                  <div style="margin-top:8px;white-space:pre-wrap;background:rgba(255,255,255,0.85);border:1px solid rgba(0,0,0,0.08);padding:10px;border-radius:12px;">
                    <?php echo htmlspecialchars($answerText !== null && $answerText !== '' ? $answerText : 'No answer'); ?>
                  </div>
                  <div class="muted" style="margin-top:8px;">Subjective answers will be graded by lecturer.</div>
                <?php endif; ?>

                <div class="scoreBox">
                  <div class="muted">Score</div>
                  <div class="big"><?php echo htmlspecialchars($score); ?> / <?php echo htmlspecialchars($marks); ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</body>
</html>
