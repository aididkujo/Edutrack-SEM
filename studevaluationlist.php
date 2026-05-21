<?php
// module4 - studevaluationlist (LECTURER -> STUDENT evaluation list)
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

// Search keyword
$q = trim($_GET['q'] ?? '');

// 1) Get lecturer courses (for dropdown)
$courses = [];
$sqlCourses = "SELECT courseID, courseName FROM course WHERE userID = ? ORDER BY courseName";
$stmt = $conn->prepare($sqlCourses);
$stmt->bind_param("i", $lecturerID);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $courses[] = $row;
$stmt->close();

// If no course assigned
if (count($courses) === 0) {
    $errorMsg = "No courses assigned to you. Please contact admin.";
    $selectedCourseID = 0;
    $rows = [];
} else {
    // 2) Selected course
    $selectedCourseID = isset($_GET['courseID']) ? (int)$_GET['courseID'] : (int)$courses[0]['courseID'];

    // Validate selectedCourseID belongs to lecturer
    $validCourse = false;
    foreach ($courses as $c) {
        if ((int)$c['courseID'] === $selectedCourseID) {
            $validCourse = true;
            break;
        }
    }
    if (!$validCourse) $selectedCourseID = (int)$courses[0]['courseID'];

    // 3) Fetch students enrolled in selected course + Completed/Pending status
    // NOTE: You do not have a "matric_id" column in user table,
    // so we will show:
    // - Matric ID = the email prefix before "@", OR fallback to userID
    // If you DO have matric in another column, tell me and I will adjust.
    $rows = [];

    $sql = "
        SELECT
            u.userID AS studentID,
            u.full_name AS studentName,
            u.email AS studentEmail,
            CASE
              WHEN EXISTS (
                SELECT 1 FROM evaluation ev
                WHERE ev.evaluatorID = ?
                  AND ev.evaluateeID = u.userID
                  AND ev.courseID = ?
                  AND ev.evaluationType = 'lecturer_to_student'
              )
              THEN 'Completed'
              ELSE 'Pending'
            END AS evalStatus
        FROM enrollment e
        JOIN user u ON e.userID = u.userID
        WHERE e.courseID = ?
          AND u.role = 'student'
          AND (
                ? = '' OR
                u.full_name LIKE CONCAT('%', ?, '%') OR
                u.email LIKE CONCAT('%', ?, '%')
          )
        ORDER BY u.full_name
    ";

    $stmt = $conn->prepare($sql);
    // evaluatorID, courseID(for EXISTS), courseID(for list), q, q, q
    $stmt->bind_param("iiisss", $lecturerID, $selectedCourseID, $selectedCourseID, $q, $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack - Student Evaluation List</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { margin:0; font-family: Arial, Helvetica, sans-serif; }
    .header.lecturer-theme { background:#85f0ff; }
    .content-wrapper { display:flex; min-height: calc(100vh - 80px); background:#f6f6f6; }
    .sidebar.lecturer-theme { background:#7ee8f7; }
    .sidebar.lecturer-theme ul li a.active { background:#a3d6dd; }

    .main-content { flex:1; padding:30px 60px; }

    .evaluation-wrapper {
      background:#fff; border-radius:8px; max-width:960px; margin:0 auto;
      padding:24px 32px 32px; box-sizing:border-box;
      box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }

    .filters-row { display:flex; gap:14px; align-items:center; margin-bottom:18px; flex-wrap:wrap; }
    .filters-row label { font-weight:700; font-size:14px; }
    .filters-row select {
      padding:10px 12px; border-radius:10px; border:1px solid #ddd; min-width:280px;
      background:#fff;
    }

    .search-row { display:flex; align-items:center; gap:16px; margin-bottom:24px; }
    .search-box-wrapper {
      flex:1; background:#f5f5f5; border-radius:30px; display:flex; align-items:center; padding:10px 18px;
      border:1px solid #eee;
    }
    .search-icon { margin-right:10px; font-size:16px; opacity:0.7; }
    .search-input { border:none; outline:none; background:transparent; width:100%; font-size:14px; }
    .search-btn {
      background:#e7d7ff; border:none; border-radius:16px; padding:10px 28px;
      font-size:14px; cursor:pointer; font-weight:600;
    }
    .search-btn:hover { filter:brightness(0.96); }

    .evaluation-table { width:100%; border-collapse:collapse; margin-top:10px; font-size:14px; }
    .evaluation-table th, .evaluation-table td { border:1px solid #d3d3d3; padding:10px 12px; text-align:left; }
    .evaluation-table th { background:#f2f2f2; font-weight:700; }
    .evaluation-table tr:nth-child(even) td { background:#f9f9f9; }

    .status-completed { font-style:italic; text-decoration:underline; }
    .status-pending { font-style:italic; }

    .action-cell { text-align:center; }
    .action-btn {
      width:30px; height:30px; border-radius:999px; border:1px solid #d3d3d3;
      background:#fff; display:inline-flex; align-items:center; justify-content:center;
      margin:0 3px; cursor:pointer; font-size:14px; text-decoration:none; color:inherit;
    }
    .action-btn:hover { background:#f2f2f2; }

    .alert { padding:12px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }
    .alert-error { background:#ffecec; border:1px solid #ffb4b4; }
    .muted { color:#777; font-size:13px; }
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
        <li><a href="studevaluationlist.php" class="active">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="evaluation-wrapper">

        <?php if (!empty($errorMsg)): ?>
          <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <!-- Course filter + Search (GET) -->
        <form method="get">
          <div class="filters-row">
            <label for="courseID">Course:</label>
            <select name="courseID" id="courseID" onchange="this.form.submit()">
              <?php foreach ($courses as $c): ?>
                <option value="<?php echo (int)$c['courseID']; ?>"
                  <?php echo ((int)$c['courseID'] === (int)$selectedCourseID) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($c['courseName']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="muted">Select a course to view its students.</span>
          </div>

          <div class="search-row">
            <div class="search-box-wrapper">
              <span class="search-icon">&#128269;</span>
              <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="search-input" placeholder="Search by student name or email...">
            </div>
            <button class="search-btn" type="submit">Search</button>
          </div>
        </form>

        <!-- Table -->
        <table class="evaluation-table">
          <thead>
            <tr>
              <th style="width: 60px;">No</th>
              <th style="width: 140px;">Matric ID</th>
              <th>Name</th>
              <th style="width: 180px;">Evaluation Status</th>
              <th style="width: 120px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="5" class="muted">No students found for this course.</td>
              </tr>
            <?php else: ?>
              <?php $i = 1; foreach ($rows as $r): ?>
                <?php
                  // "Matric ID" display hack:
                  // Use email prefix before '@' (example: cb22045@student...)
                  $matric = $r['studentEmail'] ? explode('@', $r['studentEmail'])[0] : ('UID' . (int)$r['studentID']);
                  $status = $r['evalStatus'];
                  $statusClass = ($status === 'Completed') ? 'status-completed' : 'status-pending';
                ?>
                <tr>
                  <td><?php echo $i++; ?></td>
                  <td><?php echo htmlspecialchars($matric); ?></td>
                  <td><?php echo htmlspecialchars($r['studentName']); ?></td>
                  <td class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></td>
                  <td class="action-cell">
                    <!-- Link to studentevaluation.php with correct GET params -->
                    <a
                      href="studentevaluation.php?courseID=<?php echo (int)$selectedCourseID; ?>&studentID=<?php echo (int)$r['studentID']; ?>"
                      class="action-btn"
                      title="<?php echo ($status === 'Completed') ? 'Edit / View' : 'Evaluate'; ?>"
                    >
                      &#9998;
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

      </div>
    </div>
  </div>
</body>
</html>
