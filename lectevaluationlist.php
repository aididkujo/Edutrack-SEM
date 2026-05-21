<?php
// module4 - lectevaluationlist (STUDENT -> LECTURER evaluation list)
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_role('student');

$user = get_user_by_id($conn, $_SESSION['userID']);
$studentID = (int)$_SESSION['userID'];

// Search keyword (optional)
$q = trim($_GET['q'] ?? '');

// Fetch enrolled courses + lecturer name + evaluated status
$items = [];
$sql = "
    SELECT
        c.courseID,
        c.courseName,
        c.userID AS lecturerID,
        u.full_name AS lecturerName,
        CASE
          WHEN EXISTS (
            SELECT 1 FROM evaluation ev
            WHERE ev.evaluatorID = ?
              AND ev.evaluateeID = c.userID
              AND ev.courseID = c.courseID
              AND ev.evaluationType = 'student_to_lecturer'
          )
          THEN 'Evaluated'
          ELSE 'Pending'
        END AS evalStatus
    FROM enrollment e
    JOIN course c ON e.courseID = c.courseID
    JOIN user u ON c.userID = u.userID
    WHERE e.userID = ?
      AND (
            ? = '' OR
            c.courseName LIKE CONCAT('%', ?, '%') OR
            u.full_name LIKE CONCAT('%', ?, '%')
      )
    ORDER BY c.courseName
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iisss", $studentID, $studentID, $q, $q, $q);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

// Helper: extract subject code from the beginning of courseName (e.g., "BCN1053 Data ...")
function extract_subject_code($courseName) {
    $courseName = trim($courseName);
    // take first word as code
    $parts = preg_split('/\s+/', $courseName);
    return $parts[0] ?? '';
}

// Helper: remove subject code from courseName to show subject title only
function extract_subject_title($courseName) {
    $courseName = trim($courseName);
    $parts = preg_split('/\s+/', $courseName, 2);
    return $parts[1] ?? $courseName;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack - Lecturer Evaluation List</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .evaluation-wrapper {
      background:#ffffff; border-radius:8px; max-width:900px; margin:30px auto 10px;
      padding:24px 32px 32px; box-sizing:border-box;
      box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }
    .evaluation-title { font-size:24px; font-weight:700; margin:0; }
    .evaluation-subtitle {
      margin-top:4px; margin-bottom:18px; font-style:italic; color:#333; font-size:14px;
    }

    /* Search */
    .search-row { display:flex; gap:12px; align-items:center; margin: 10px 0 18px; }
    .search-box {
      flex: 1; background:#f5f5f5; border-radius:30px; display:flex; align-items:center;
      padding:10px 18px; border:1px solid #eee;
    }
    .search-icon { margin-right:10px; font-size:16px; opacity:0.7; }
    .search-input { border:none; outline:none; background:transparent; width:100%; font-size:14px; }
    .search-btn {
      background:#e7d7ff; border:none; border-radius:16px; padding:10px 22px;
      font-size:14px; cursor:pointer; font-weight:600;
    }
    .search-btn:hover { filter:brightness(0.96); }

    .lecturer-list { margin-top:10px; }
    .lect-card {
      display:flex; align-items:center; justify-content:space-between;
      border-radius:16px; padding:18px 24px; margin-bottom:16px;
      box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }
    .lect-card.evaluated { background:#d9d9d9; }
    .lect-card.pending { background:#e5ffb8; }

    .lect-info { max-width:70%; }
    .lect-info h3 { margin:0 0 8px 0; font-size:20px; font-weight:700; }
    .lect-info p { margin:2px 0; font-size:14px; }

    .lect-actions { display:flex; flex-direction:column; gap:8px; }
    .btn-eval, .btn-evaluated, .btn-edit {
      border:none; border-radius:999px; padding:8px 24px; font-size:14px;
      cursor:pointer; background:#e7d7ff; color:#333; font-weight:600;
      text-decoration:none; text-align:center; display:inline-block;
    }
    .btn-evaluated { opacity:0.8; cursor:default; }
    .btn-eval:hover, .btn-edit:hover { filter:brightness(0.96); }

    .muted { color:#777; font-size:13px; }
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
        <li><a href="student_dashboard.php">My Courses</a></li>
        <li><a href="student_progress.php">My Progress</a></li>
        <li><a href="student_myfeedback.php">My Feedback</a></li>
        <li><a href="lectevaluationlist.php" class="active">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="evaluation-wrapper">
        <h2 class="evaluation-title">Evaluation for Academic Staff</h2>
        <p class="evaluation-subtitle">Please evaluate your lecturer with honesty</p>

        <!-- Search -->
        <form method="get" class="search-row">
          <div class="search-box">
            <span class="search-icon">&#128269;</span>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="search-input"
                   placeholder="Search course or lecturer name...">
          </div>
          <button class="search-btn" type="submit">Search</button>
        </form>

        <div class="lecturer-list">
          <?php if (empty($items)): ?>
            <div class="muted">No enrolled courses found.</div>
          <?php else: ?>
            <?php foreach ($items as $it): ?>
              <?php
                $status = $it['evalStatus'];
                $cardClass = ($status === 'Evaluated') ? 'evaluated' : 'pending';

                $subjectCode = extract_subject_code($it['courseName']);
                $subjectTitle = extract_subject_title($it['courseName']);

                $courseID = (int)$it['courseID'];
              ?>
              <div class="lect-card <?php echo $cardClass; ?>">
                <div class="lect-info">
                  <h3><?php echo htmlspecialchars($it['lecturerName']); ?></h3>
                  <p><strong>Subject Code :</strong> <?php echo htmlspecialchars($subjectCode); ?></p>
                  <p><strong>Subject Name :</strong> <?php echo htmlspecialchars($subjectTitle); ?></p>
                </div>

                <div class="lect-actions">
                  <?php if ($status === 'Evaluated'): ?>
                    <span class="btn-evaluated">Evaluated</span>
                    <!-- Edit goes to the same page. Your lecturerevaluation.php can decide to load existing record -->
                    <a href="lecturerevaluation.php?courseID=<?php echo $courseID; ?>" class="btn-edit">Edit</a>
                  <?php else: ?>
                    <a href="lecturerevaluation.php?courseID=<?php echo $courseID; ?>" class="btn-eval">Evaluate</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>

      <!-- keep your pagination UI if you want later -->
      <div class="pagination">
        &larr; Previous
        <span class="current-page">1</span>
        <span>2</span>
        <span>3</span>
        <span>…</span>
        <a href="#">Next &rarr;</a>
      </div>
    </div>
  </div>
</body>
</html>
