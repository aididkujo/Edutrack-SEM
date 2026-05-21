<?php
// lecturer_myfeedback.php (LECTURER reads feedback given by STUDENTS about lecturer)
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_role('lecturer');

$user = get_user_by_id($conn, $_SESSION['userID']);
$lecturerID = (int)$_SESSION['userID'];

$questions_student_to_lecturer = [
  "The lecturer explains concepts clearly and effectively",
  "The lecturer uses relevant examples and teaching materials",
  "The lecturer is approachable and available for consultation",
  "The lecturer treats students fairly and respectfully",
  "Overall, are you satisfied with this lecturer's teaching this semester?"
];

function render_stars($value) {
    $value = (int)$value;
    for ($i=1; $i<=5; $i++) {
        $active = ($i <= $value) ? 'active' : '';
        echo '<span class="'.$active.'">&#9733;</span>';
    }
}

// If lecturer clicks "View Feedback"
$viewEvaluationID = isset($_GET['evaluationID']) ? (int)$_GET['evaluationID'] : 0;
$evaluation = null;

if ($viewEvaluationID > 0) {
    $sqlOne = "
        SELECT e.*, 
               s.full_name AS studentName,
               c.courseName
        FROM evaluation e
        JOIN user s ON e.evaluatorID = s.userID
        JOIN course c ON e.courseID = c.courseID
        WHERE e.evaluationID = ?
          AND e.evaluateeID = ?
          AND e.evaluationType = 'student_to_lecturer'
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlOne);
    $stmt->bind_param("ii", $viewEvaluationID, $lecturerID);
    $stmt->execute();
    $evaluation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// List: all students enrolled in lecturer courses + evaluation status
$sqlList = "
    SELECT 
        c.courseID,
        c.courseName,
        u.userID AS studentID,
        u.full_name AS studentName,
        e.evaluationID,
        e.createdAt
    FROM course c
    JOIN enrollment en ON en.courseID = c.courseID
    JOIN user u ON u.userID = en.userID AND u.role = 'student'
    LEFT JOIN evaluation e
        ON e.courseID = c.courseID
       AND e.evaluatorID = u.userID
       AND e.evaluateeID = c.userID
       AND e.evaluationType = 'student_to_lecturer'
    WHERE c.userID = ?
    ORDER BY c.courseName, u.full_name
";
$stmt = $conn->prepare($sqlList);
$stmt->bind_param("i", $lecturerID);
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>EduTrack - My Feedback (Lecturer)</title>
  <link rel="stylesheet" href="style.css">

  <style>
    .evaluation-wrapper {
      background: #ffffff;
      border-radius: 8px;
      max-width: 980px;
      margin: 30px auto;
      padding: 24px 32px 32px;
      box-sizing: border-box;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }

    .section-title {
      font-size: 22px;
      font-weight: 700;
      margin: 0 0 16px;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }
    .table th, .table td {
      border: 1px solid #d3d3d3;
      padding: 10px 12px;
      text-align: left;
    }
    .table th {
      background: #f2f2f2;
      font-weight: 700;
    }

    .status-pill {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
    }
    .status-completed { background: #e8fff0; border: 1px solid #b7f2c8; }
    .status-pending { background: #fff7e6; border: 1px solid #ffd28a; }

    .btn-view {
      border: none;
      border-radius: 999px;
      padding: 8px 18px;
      font-size: 13px;
      cursor: pointer;
      background: #e7d7ff;
      color: #333;
      font-weight: 600;
      text-decoration: none;
      display: inline-block;
    }
    .btn-view:hover { filter: brightness(0.96); }

    /* Feedback card (same look as form, but readonly) */
    .feedback-card {
      margin-top: 22px;
      padding-top: 18px;
      border-top: 1px solid #eee;
    }

    .evaluation-question {
      background: #f2f2f2;
      border-radius: 24px;
      padding: 16px 24px;
      margin-bottom: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-sizing: border-box;
    }
    .evaluation-question-text {
      font-size: 14px;
      color: #333;
      max-width: 80%;
    }
    .evaluation-stars span {
      font-size: 20px;
      color: #d3d3d3;
      margin-left: 2px;
    }
    .evaluation-stars span.active { color: #f7b500; }

    .comments-block {
      background: #f2f2f2;
      border-radius: 16px;
      padding: 14px 18px;
      font-size: 14px;
      margin-top: 10px;
      white-space: pre-wrap;
    }

    .meta {
      font-size: 13px;
      color: #444;
      margin: 0 0 12px;
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
        <li><a href="lecturer_myfeedback.php" class="active">My Feedback</a></li>
        <li><a href="studevaluationlist.php">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="evaluation-wrapper">
        <h2 class="section-title">Student Feedback About You</h2>

        <table class="table">
          <thead>
            <tr>
              <th style="width:60px;">No</th>
              <th>Student Name</th>
              <th>Course</th>
              <th style="width:140px;">Status</th>
              <th style="width:160px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($list) === 0): ?>
              <tr><td colspan="5">No students found for your courses.</td></tr>
            <?php else: ?>
              <?php $no = 1; ?>
              <?php foreach ($list as $row): ?>
                <tr>
                  <td><?php echo $no++; ?></td>
                  <td><?php echo htmlspecialchars($row['studentName']); ?></td>
                  <td><?php echo htmlspecialchars($row['courseName']); ?></td>
                  <td>
                    <?php if (!empty($row['evaluationID'])): ?>
                      <span class="status-pill status-completed">Completed</span>
                    <?php else: ?>
                      <span class="status-pill status-pending">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($row['evaluationID'])): ?>
                      <a class="btn-view" href="lecturer_myfeedback.php?evaluationID=<?php echo (int)$row['evaluationID']; ?>">
                        View Feedback
                      </a>
                    <?php else: ?>
                      <span style="color:#777;">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <?php if ($viewEvaluationID > 0): ?>
          <div class="feedback-card">
            <?php if (!$evaluation): ?>
              <div class="meta" style="color:#b00020;">Feedback not found (or not yours).</div>
            <?php else: ?>
              <div class="meta">
                <strong>Student:</strong> <?php echo htmlspecialchars($evaluation['studentName']); ?>
                &nbsp; | &nbsp;
                <strong>Course:</strong> <?php echo htmlspecialchars($evaluation['courseName']); ?>
                &nbsp; | &nbsp;
                <strong>Submitted:</strong> <?php echo htmlspecialchars($evaluation['createdAt']); ?>
              </div>

              <?php for ($i=1; $i<=5; $i++): ?>
                <div class="evaluation-question">
                  <div class="evaluation-question-text">
                    <?php echo htmlspecialchars($questions_student_to_lecturer[$i-1]); ?>
                  </div>
                  <div class="evaluation-stars">
                    <?php render_stars($evaluation["q{$i}_rating"]); ?>
                  </div>
                </div>
              <?php endfor; ?>

              <div style="font-weight:700; margin-top:14px;">Additional Comments</div>
              <div class="comments-block">
                <?php echo htmlspecialchars($evaluation['comments'] ?? ''); ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</body>
</html>
