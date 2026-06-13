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
$courseID = isset($_GET['courseID']) ? (int)$_GET['courseID'] : 0;

// Check whether the student is enrolled in this course
$access = $conn->prepare("SELECT 1 FROM enrollment WHERE userID = ? AND courseID = ? LIMIT 1");
$access->bind_param("ii", $userID, $courseID);
$access->execute();
$ok = $access->get_result()->fetch_assoc();
$access->close();

if (!$ok) {
  header("Location: student_courses.php");
  exit;
}

// Get course name
$cstmt = $conn->prepare("SELECT courseName FROM course WHERE courseID = ? LIMIT 1");
$cstmt->bind_param("i", $courseID);
$cstmt->execute();
$course = $cstmt->get_result()->fetch_assoc();
$cstmt->close();

/*
  UPDATED QUERY:
  Added AND isVisible = 1
  This ensures students only see quizzes that lecturers set as visible.
*/
$stmt = $conn->prepare("
  SELECT assessmentID, tittle, dueDate, openAt, closeAt, durationMinutes
  FROM assessment
  WHERE courseID = ? 
    AND type = 'Quiz'
    AND isVisible = 1
  ORDER BY dueDate ASC, assessmentID ASC
");
$stmt->bind_param("i", $courseID);
$stmt->execute();
$quizzes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$msg = $_GET['msg'] ?? '';
$alert = '';

if ($msg === 'not_open') {
  $alert = 'Quiz is not open yet.';
}

if ($msg === 'closed') {
  $alert = 'Quiz is already closed.';
}

if ($msg === 'duration_missing') {
  $alert = 'Quiz duration is not set.';
}

function fmt_dt($dt)
{
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

  <title>EduTrack Quiz List</title>
  <link rel="stylesheet" href="style.css">

  <style>
    .table-wrap {
      padding: 18px 26px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 10px 12px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.08);
      text-align: left;
      vertical-align: top;
    }

    th {
      background: rgba(0, 0, 0, 0.03);
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

    .btnDisabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .muted {
      color: #6b7280;
      font-size: 14px;
    }

    .alert {
      margin-top: 10px;
      color: #b45309;
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

      <span class="user-name">
        <?php echo htmlspecialchars($user['full_name']); ?>
      </span>
    </div>
  </div>

  <div class="content-wrapper">
    <div class="sidebar student-theme">
      <ul>
        <li>
          <a href="student_courses.php" style="font-weight:700;text-decoration:underline;">
            My Courses
          </a>
        </li>
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
        <h2><?php echo htmlspecialchars($fullName); ?></h2>
      </div>

      <div class="table-wrap">
        <h2 style="margin:0;">Quiz</h2>
        <div class="muted">
          <?php echo htmlspecialchars($course['courseName'] ?? ''); ?>
        </div>

        <?php if ($alert): ?>
          <div class="alert">
            <?php echo htmlspecialchars($alert); ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Quiz</th>
              <th>Opens</th>
              <th>Closes</th>
              <th>Duration</th>
              <th>Attempt Status</th>
              <th>Score</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($quizzes)): ?>
              <tr>
                <td colspan="7" class="muted">
                  No quiz available yet.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($quizzes as $q): ?>
                <?php
                $aid = (int)$q['assessmentID'];

                $attemptSql = "
                  SELECT status, totalScore
                  FROM quiz_attempt
                  WHERE assessmentID = ? AND userID = ?
                  ORDER BY attemptID DESC
                  LIMIT 1
                ";

                $astmt = $conn->prepare($attemptSql);
                $astmt->bind_param("ii", $aid, $userID);
                $astmt->execute();
                $attempt = $astmt->get_result()->fetch_assoc();
                $astmt->close();

                $status = $attempt['status'] ?? 'none';
                $score = $attempt['totalScore'] ?? '';

                $now = time();
                $openTs  = $q['openAt'] ? strtotime($q['openAt']) : 0;
                $closeTs = $q['closeAt'] ? strtotime($q['closeAt']) : 0;
                $durMin  = isset($q['durationMinutes']) ? (int)$q['durationMinutes'] : 0;

                $openText = fmt_dt($q['openAt'] ?? null);
                $closeText = fmt_dt($q['closeAt'] ?? null);
                $durText = $durMin > 0 ? ($durMin . ' min') : '-';

                $canStart = true;
                $lockLabel = '';

                if ($openTs > 0 && $now < $openTs) {
                  $canStart = false;
                  $lockLabel = 'Not Open';
                } elseif ($closeTs > 0 && $now > $closeTs) {
                  $canStart = false;
                  $lockLabel = 'Closed';
                } elseif ($durMin <= 0) {
                  $canStart = false;
                  $lockLabel = 'No Duration';
                }
                ?>

                <tr>
                  <td><?php echo htmlspecialchars($q['tittle']); ?></td>
                  <td><?php echo htmlspecialchars($openText); ?></td>
                  <td><?php echo htmlspecialchars($closeText); ?></td>
                  <td><?php echo htmlspecialchars($durText); ?></td>

                  <td>
                    <span class="badge">
                      <?php echo htmlspecialchars($status); ?>
                    </span>
                  </td>

                  <td>
                    <?php echo $score !== '' ? htmlspecialchars($score) : '-'; ?>
                  </td>

                  <td>
                    <?php if ($status === 'submitted' || $status === 'graded'): ?>
                      <a class="btn" href="student_quiz_review.php?assessmentID=<?php echo $aid; ?>&courseID=<?php echo (int)$courseID; ?>">
                        View Answers
                      </a>

                    <?php elseif ($status === 'in_progress'): ?>
                      <?php if ($canStart): ?>
                        <a class="btn" href="student_quiz_take.php?assessmentID=<?php echo $aid; ?>&courseID=<?php echo (int)$courseID; ?>">
                          Continue
                        </a>
                      <?php else: ?>
                        <span class="btn btnDisabled">
                          <?php echo htmlspecialchars($lockLabel); ?>
                        </span>
                      <?php endif; ?>

                    <?php else: ?>
                      <?php if ($canStart): ?>
                        <a class="btn" href="student_quiz_take.php?assessmentID=<?php echo $aid; ?>&courseID=<?php echo (int)$courseID; ?>">
                          Open
                        </a>
                      <?php else: ?>
                        <span class="btn btnDisabled">
                          <?php echo htmlspecialchars($lockLabel); ?>
                        </span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <div style="margin-top:16px;">
          <a class="btn" href="student_subject.php?courseID=<?php echo (int)$courseID; ?>">
            Back
          </a>
        </div>
      </div>
    </div>
  </div>
</body>

</html>