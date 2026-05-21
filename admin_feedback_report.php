<?php
// admin_feedback_report.php - Admin report for ONE feedback (print-friendly)
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_role('admin');

$admin = get_user_by_id($conn, $_SESSION['userID']);

$evaluationID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($evaluationID <= 0) {
  die("Invalid feedback ID.");
}

// Questions (use same as your evaluation forms)
$questions_student_to_lecturer = [
  "The lecturer explains concepts clearly and effectively",
  "The lecturer uses relevant examples and teaching materials",
  "The lecturer is approachable and available for consultation",
  "The lecturer treats students fairly and respectfully",
  "Overall, are you satisfied with this lecturer's teaching this semester?"
];

$questions_lecturer_to_student = [
  "The student attends classes consistently and on time",
  "The student participates actively during class activities",
  "The student completes tasks/assignments responsibly",
  "The student demonstrates good understanding of the course content",
  "Overall, the student's performance is satisfactory"
];

function safe($s) { return htmlspecialchars((string)$s); }

function stars($v) {
  $v = (int)$v;
  $out = "";
  for ($i=1; $i<=5; $i++) {
    $out .= '<span class="star '.(($i <= $v) ? 'active' : '').'">&#9733;</span>';
  }
  return $out;
}

// Fetch evaluation + names + course
$sql = "
  SELECT
    e.*,
    ue.full_name AS evaluatorName, ue.role AS evaluatorRole,
    ut.full_name AS evaluateeName, ut.role AS evaluateeRole,
    c.courseName
  FROM evaluation e
  JOIN user ue ON ue.userID = e.evaluatorID
  JOIN user ut ON ut.userID = e.evaluateeID
  JOIN course c ON c.courseID = e.courseID
  WHERE e.evaluationID = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $evaluationID);
$stmt->execute();
$fb = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fb) {
  die("Feedback record not found.");
}

$isStudentToLect = ($fb['evaluationType'] === 'student_to_lecturer');
$questions = $isStudentToLect ? $questions_student_to_lecturer : $questions_lecturer_to_student;

$q1 = (int)$fb['q1_rating'];
$q2 = (int)$fb['q2_rating'];
$q3 = (int)$fb['q3_rating'];
$q4 = (int)$fb['q4_rating'];
$q5 = (int)$fb['q5_rating'];
$avg = ($q1+$q2+$q3+$q4+$q5) / 5.0;

function rating_label($avg) {
  if ($avg >= 4.5) return "Excellent";
  if ($avg >= 3.5) return "Good";
  if ($avg >= 2.5) return "Average";
  if ($avg >= 1.5) return "Needs Improvement";
  return "Poor";
}

$label = rating_label($avg);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>EduTrack - Feedback Report</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { margin:0; font-family: Arial, Helvetica, sans-serif; background:#f6f6f6; }
    .header.admin-theme { background:#ffe352; }
    .content-wrapper { display:flex; min-height:calc(100vh - 80px); }
    .sidebar.admin-theme { background:#ffe76d; }
    .sidebar.admin-theme ul li a.active { background:#f3d861; }
    .main-content { flex:1; padding:30px 60px; }

    .report-card {
      background:#fff; border-radius:10px; max-width:980px; margin:0 auto;
      padding:26px 30px; box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }

    .report-top {
      display:flex; justify-content:space-between; align-items:flex-start; gap:20px;
      border-bottom:1px solid #eee; padding-bottom:16px; margin-bottom:18px;
    }
    .report-title { margin:0; font-size:22px; font-weight:800; }
    .report-meta { margin-top:6px; color:#444; font-size:13px; line-height:1.6; }
    .badge {
      display:inline-block; padding:7px 12px; border-radius:999px; border:1px solid #ddd;
      font-weight:800; font-size:12px; background:#f9f9f9;
    }

    .btn-row { display:flex; gap:10px; justify-content:flex-end; margin-bottom:14px; }
    .btn {
      border:none; border-radius:16px; padding:10px 18px; cursor:pointer;
      font-weight:700; background:#e7d7ff; color:#333; text-decoration:none; display:inline-block;
      font-size:13px;
    }
    .btn:hover { filter:brightness(0.96); }
    .btn.secondary { background:#f2f2f2; }

    .summary {
      display:flex; gap:14px; flex-wrap:wrap; margin:14px 0 18px;
    }
    .box {
      flex:1; min-width:220px; background:#f7f7f7; border-radius:12px; padding:14px 16px;
      border:1px solid #eee;
    }
    .box .big { font-size:22px; font-weight:900; margin-top:6px; }
    .box .small { font-size:12px; color:#444; }

    .qrow {
      background:#f2f2f2; border-radius:18px; padding:14px 16px; margin-bottom:10px;
      display:flex; justify-content:space-between; align-items:center; gap:14px;
    }
    .qtext { font-size:14px; color:#333; max-width:75%; }
    .star { font-size:18px; color:#d3d3d3; }
    .star.active { color:#f7b500; }

    .comments {
      margin-top:16px; padding:14px 16px; border-radius:12px; border:1px solid #eee;
      background:#fff;
    }
    .comments h4 { margin:0 0 8px 0; }
    .comments p { margin:0; color:#333; white-space:pre-wrap; }

    /* Print */
    @media print {
      body { background:#fff; }
      .sidebar, .header, .btn-row { display:none !important; }
      .main-content { padding:0 !important; }
      .report-card { box-shadow:none !important; border:none !important; }
    }
  </style>
</head>
<body>

  <div class="header admin-theme">
    <div class="brand">
      <img src="assets/logoedutrack.png" alt="EduTrack Logo">
      <div class="title">
        <h1>EduTrack</h1>
        <span>Smart Tracking for Smarter Learning</span>
      </div>
    </div>
    <div class="user-info">
      <span class="user-name"><?php echo safe($admin['full_name']); ?></span>
    </div>
  </div>

  <div class="content-wrapper">
    <div class="sidebar admin-theme">
      <ul>
        <li><a href="#">Assessment</a></li>
        <li><a href="#">Progress</a></li>
        <li><a href="admin_feedbacklist.php" class="active">Feedback</a></li>
        <li><a href="registration_users.php">Registration Users</a></li>
        <li><a href="manage_users.php">Manage Users</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="report-card">

        <div class="btn-row">
          <a class="btn secondary" href="admin_feedbacklist.php">Back</a>
          <button class="btn" onclick="window.print()">Print / Save as PDF</button>
        </div>

        <div class="report-top">
          <div>
            <h2 class="report-title">Feedback Report</h2>
            <div class="report-meta">
              <div><strong>Feedback ID:</strong> <?php echo (int)$fb['evaluationID']; ?></div>
              <div><strong>Type:</strong> <?php echo safe($fb['evaluationType']); ?></div>
              <div><strong>Course:</strong> <?php echo safe($fb['courseName']); ?></div>
              <div><strong>Submitted:</strong> <?php echo safe($fb['createdAt']); ?></div>
              <div><strong>Last Updated:</strong> <?php echo safe($fb['updatedAt']); ?></div>
              <div>
                <strong>Evaluator:</strong> <?php echo safe($fb['evaluatorName']); ?> (<?php echo safe($fb['evaluatorRole']); ?>)
                <br>
                <strong>Evaluatee:</strong> <?php echo safe($fb['evaluateeName']); ?> (<?php echo safe($fb['evaluateeRole']); ?>)
              </div>
            </div>
          </div>
          <div class="badge"><?php echo safe($label); ?></div>
        </div>

        <div class="summary">
          <div class="box">
            <div class="small">Average Rating</div>
            <div class="big"><?php echo number_format($avg, 2); ?> / 5.00</div>
          </div>
          <div class="box">
            <div class="small">Score Breakdown</div>
            <div class="big"><?php echo "{$q1}, {$q2}, {$q3}, {$q4}, {$q5}"; ?></div>
          </div>
        </div>

        <?php for ($i=1; $i<=5; $i++): ?>
          <?php $val = (int)$fb["q{$i}_rating"]; ?>
          <div class="qrow">
            <div class="qtext"><?php echo safe($questions[$i-1]); ?></div>
            <div><?php echo stars($val); ?></div>
          </div>
        <?php endfor; ?>

        <div class="comments">
          <h4>Additional Comments</h4>
          <p><?php echo safe($fb['comments'] ?? ''); ?></p>
        </div>

      </div>
    </div>
  </div>

</body>
</html>
