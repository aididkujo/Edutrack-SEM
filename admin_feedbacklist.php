<?php
// admin_feedbacklist.php - Admin view of ALL feedback (students + lecturers) with View/Edit + Report + Delete
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_role('admin');

// Current admin
$user = get_user_by_id($conn, $_SESSION['userID']);

function safe($s) { return htmlspecialchars((string)$s); }
function clamp_int($v, $min, $max) { $v=(int)$v; return max($min, min($max, $v)); }

// ---------------------------
// Search + Pagination settings
// ---------------------------
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$perPage = 10;
$offset = ($page - 1) * $perPage;

// ---------------------------
// Delete action (POST)
// ---------------------------
$successMsg = "";
$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_evaluation'])) {
  $evaluationID = (int)($_POST['evaluationID'] ?? 0);

  if ($evaluationID > 0) {
    $stmt = $conn->prepare("DELETE FROM evaluation WHERE evaluationID = ?");
    $stmt->bind_param("i", $evaluationID);

    if ($stmt->execute()) {
      $successMsg = "Feedback deleted successfully.";
    } else {
      $errorMsg = "Failed to delete feedback: " . safe($conn->error);
    }
    $stmt->close();
  } else {
    $errorMsg = "Invalid feedback ID.";
  }
}

// ---------------------------
// View/Edit action (GET viewID)
// ---------------------------
$viewID = isset($_GET['viewID']) ? (int)$_GET['viewID'] : 0;
$editingRow = null;

if ($viewID > 0) {
  $sqlOne = "
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
  $stmt = $conn->prepare($sqlOne);
  $stmt->bind_param("i", $viewID);
  $stmt->execute();
  $editingRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$editingRow) {
    $errorMsg = "Feedback record not found.";
  }
}

// ---------------------------
// Update action (POST)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_evaluation'])) {
  $evaluationID = (int)($_POST['evaluationID'] ?? 0);

  $q1 = clamp_int($_POST['q1_rating'] ?? 0, 1, 5);
  $q2 = clamp_int($_POST['q2_rating'] ?? 0, 1, 5);
  $q3 = clamp_int($_POST['q3_rating'] ?? 0, 1, 5);
  $q4 = clamp_int($_POST['q4_rating'] ?? 0, 1, 5);
  $q5 = clamp_int($_POST['q5_rating'] ?? 0, 1, 5);
  $comments = trim($_POST['comments'] ?? '');

  if ($evaluationID <= 0) {
    $errorMsg = "Invalid feedback ID for update.";
  } else {
    $sqlUpd = "
      UPDATE evaluation
      SET
        q1_rating = ?,
        q2_rating = ?,
        q3_rating = ?,
        q4_rating = ?,
        q5_rating = ?,
        comments = ?
      WHERE evaluationID = ?
    ";
    $stmt = $conn->prepare($sqlUpd);
    $stmt->bind_param("iiiiisi", $q1, $q2, $q3, $q4, $q5, $comments, $evaluationID);

    if ($stmt->execute()) {
      $successMsg = "Feedback updated successfully.";

      // Re-fetch the row so the form shows latest values
      $stmt->close();
      $sqlOne = "
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
      $stmt = $conn->prepare($sqlOne);
      $stmt->bind_param("i", $evaluationID);
      $stmt->execute();
      $editingRow = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      // Keep the view open after update
      $viewID = $evaluationID;
    } else {
      $errorMsg = "Failed to update feedback: " . safe($conn->error);
      $stmt->close();
    }
  }
}

// ---------------------------
// Fetch list rows (students + lecturers)
// ---------------------------
$where = "";
$params = [];
$types = "";

if ($search !== "") {
  $where = "WHERE (ue.full_name LIKE ? OR ut.full_name LIKE ? OR c.courseName LIKE ? OR e.evaluationType LIKE ?)";
  $like = "%{$search}%";
  $params = [$like, $like, $like, $like];
  $types = "ssss";
}

// Count total
$sqlCount = "
  SELECT COUNT(*) AS cnt
  FROM evaluation e
  JOIN user ue ON ue.userID = e.evaluatorID
  JOIN user ut ON ut.userID = e.evaluateeID
  JOIN course c ON c.courseID = e.courseID
  $where
";
$stmt = $conn->prepare($sqlCount);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Data rows
$sqlList = "
  SELECT
    e.evaluationID,
    e.evaluationType,
    e.createdAt,
    ue.userID AS evaluatorID,
    ue.full_name AS evaluatorName,
    ue.role AS evaluatorRole,
    ut.userID AS evaluateeID,
    ut.full_name AS evaluateeName,
    ut.role AS evaluateeRole,
    c.courseName
  FROM evaluation e
  JOIN user ue ON ue.userID = e.evaluatorID
  JOIN user ut ON ut.userID = e.evaluateeID
  JOIN course c ON c.courseID = e.courseID
  $where
  ORDER BY e.createdAt DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sqlList);

if ($types) {
  // add LIMIT/OFFSET
  $types2 = $types . "ii";
  $params2 = array_merge($params, [$perPage, $offset]);
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt->bind_param("ii", $perPage, $offset);
}

$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

$stmt->close();

// helper for pagination links
function build_url($extra = []) {
  $q = $_GET;
  foreach ($extra as $k => $v) {
    if ($v === null) unset($q[$k]);
    else $q[$k] = $v;
  }
  return "admin_feedbacklist.php?" . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>EduTrack - Feedback Management</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { margin:0; font-family: Arial, Helvetica, sans-serif; }

    .header.admin-theme { background:#ffe352; }
    .content-wrapper { display:flex; min-height: calc(100vh - 80px); background:#f6f6f6; }
    .sidebar.admin-theme { background:#ffe76d; }
    .sidebar.admin-theme ul li a.active { background:#f3d861; }

    .main-content { flex:1; padding:30px 60px; }

    .evaluation-wrapper {
      background:#fff; border-radius:8px; max-width: 1080px;
      margin:0 auto; padding:24px 32px 32px; box-sizing:border-box;
      box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }

    .alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
    .alert-success { background:#e8fff0; border:1px solid #b7f2c8; }
    .alert-error { background:#ffecec; border:1px solid #ffb4b4; }

    /* Search bar */
    .search-row { display:flex; align-items:center; gap:16px; margin-bottom:24px; }
    .search-box-wrapper {
      flex:1; background:#f5f5f5; border-radius:30px; display:flex;
      align-items:center; padding:10px 18px;
    }
    .search-icon { margin-right:10px; font-size:18px; opacity:0.7; }
    .search-input { border:none; outline:none; background:transparent; width:100%; font-size:14px; }
    .search-btn {
      background:#e7d7ff; border:none; border-radius:16px; padding:10px 22px;
      font-size:14px; cursor:pointer; font-weight:600;
    }
    .search-btn:hover { filter: brightness(0.96); }

    /* Table */
    .feedback-table { width:100%; border-collapse:collapse; font-size:14px; }
    .feedback-table th, .feedback-table td { border:1px solid #d3d3d3; padding:10px 12px; text-align:left; }
    .feedback-table th { background:#f2f2f2; font-weight:700; }
    .feedback-table tr:nth-child(even) td { background:#f9f9f9; }
    .feedback-table tr:nth-child(odd) td { background:#ffffff; }

    .feedback-btn {
      background:#e7d7ff; border-radius:16px; border:none; padding:6px 14px;
      cursor:pointer; font-size:13px; text-decoration:none; color:#333;
      font-weight:700; display:inline-block; margin-right:6px;
    }
    .feedback-btn:hover { filter: brightness(0.95); }
    .feedback-btn.report { background:#fff3bf; }

    .action-cell { text-align:left; white-space:nowrap; }

    .action-btn {
      width:30px; height:30px; border-radius:999px; border:1px solid #d3d3d3;
      background:#fff; display:inline-flex; align-items:center; justify-content:center;
      margin:0 3px; cursor:pointer; font-size:14px;
    }
    .action-btn:hover { background:#f2f2f2; }

    /* Edit panel */
    .edit-panel {
      background:#fafafa; border:1px solid #eee; border-radius:12px;
      padding:16px 18px; margin-bottom:22px;
    }
    .edit-title { margin:0 0 10px 0; font-size:16px; font-weight:800; }
    .edit-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px; }
    .edit-meta { font-size:13px; color:#333; line-height:1.5; }
    .edit-row { display:flex; gap:10px; align-items:center; margin:10px 0; flex-wrap:wrap; }
    .edit-row label { font-weight:700; font-size:13px; min-width:80px; }
    .edit-row select, .edit-row textarea {
      padding:8px 10px; border-radius:10px; border:1px solid #ddd; font-size:13px;
    }
    .edit-row textarea { width:100%; min-height:90px; resize:vertical; }
    .btn-row { display:flex; gap:10px; justify-content:flex-end; margin-top:10px; }
    .btn {
      border:none; border-radius:16px; padding:10px 16px; cursor:pointer;
      font-weight:800; background:#e7d7ff; color:#333; text-decoration:none; font-size:13px;
    }
    .btn:hover { filter:brightness(0.96); }
    .btn.secondary { background:#f2f2f2; }

    /* Pagination */
    .pagination { max-width:1080px; margin:16px auto 0; text-align:center; font-size:14px; color:#777; }
    .pagination a, .pagination span { margin:0 4px; text-decoration:none; color:inherit; }
    .pagination .current-page { font-weight:700; color:#000; }
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
      <span class="user-name"><?php echo safe($user['full_name']); ?></span>
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
      <div class="evaluation-wrapper">

        <?php if ($successMsg): ?>
          <div class="alert alert-success"><?php echo safe($successMsg); ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
          <div class="alert alert-error"><?php echo safe($errorMsg); ?></div>
        <?php endif; ?>

        <!-- VIEW / EDIT PANEL -->
        <?php if ($editingRow): ?>
          <div class="edit-panel">
            <div class="edit-title">View / Edit Feedback (ID: <?php echo (int)$editingRow['evaluationID']; ?>)</div>

            <div class="edit-grid">
              <div class="edit-meta">
                <div><strong>Type:</strong> <?php echo safe($editingRow['evaluationType']); ?></div>
                <div><strong>Course:</strong> <?php echo safe($editingRow['courseName']); ?></div>
                <div><strong>Submitted:</strong> <?php echo safe($editingRow['createdAt']); ?></div>
                <div><strong>Updated:</strong> <?php echo safe($editingRow['updatedAt']); ?></div>
              </div>
              <div class="edit-meta">
                <div><strong>Evaluator:</strong> <?php echo safe($editingRow['evaluatorName']); ?> (<?php echo safe($editingRow['evaluatorRole']); ?>)</div>
                <div><strong>Evaluatee:</strong> <?php echo safe($editingRow['evaluateeName']); ?> (<?php echo safe($editingRow['evaluateeRole']); ?>)</div>
              </div>
            </div>

            <form method="post">
              <input type="hidden" name="evaluationID" value="<?php echo (int)$editingRow['evaluationID']; ?>">

              <?php for ($i=1; $i<=5; $i++): ?>
                <div class="edit-row">
                  <label for="q<?php echo $i; ?>_rating">Q<?php echo $i; ?>:</label>
                  <select id="q<?php echo $i; ?>_rating" name="q<?php echo $i; ?>_rating" required>
                    <?php for ($v=1; $v<=5; $v++): ?>
                      <option value="<?php echo $v; ?>" <?php echo ((int)$editingRow["q{$i}_rating"] === $v) ? "selected" : ""; ?>>
                        <?php echo $v; ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>
              <?php endfor; ?>

              <div class="edit-row">
                <label>Comment:</label>
                <textarea name="comments" placeholder="(Optional)"><?php echo safe($editingRow['comments'] ?? ''); ?></textarea>
              </div>

              <div class="btn-row">
                <a class="btn secondary" href="<?php echo build_url(['viewID'=>null]); ?>">Close</a>
                <button class="btn" type="submit" name="update_evaluation">Update</button>
                <a class="btn" href="admin_feedback_report.php?id=<?php echo (int)$editingRow['evaluationID']; ?>">Report</a>
              </div>
            </form>
          </div>
        <?php endif; ?>

        <!-- SEARCH -->
        <form method="get" class="search-row">
          <div class="search-box-wrapper">
            <span class="search-icon">&#128269;</span>
            <input type="text" name="search" class="search-input" value="<?php echo safe($search); ?>" placeholder="Search name / course / type">
          </div>
          <button class="search-btn" type="submit">Search</button>
        </form>

        <!-- TABLE -->
        <table class="feedback-table">
          <thead>
            <tr>
              <th style="width: 70px;">No</th>
              <th style="width: 90px;">ID</th>
              <th>Evaluator</th>
              <th>Evaluatee</th>
              <th>Course</th>
              <th style="width: 170px;">Type</th>
              <th style="width: 170px;">Submitted</th>
              <th style="width: 280px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($rows) === 0): ?>
              <tr>
                <td colspan="8">No feedback found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $i => $r): ?>
                <tr>
                  <td><?php echo ($offset + $i + 1); ?></td>
                  <td><?php echo (int)$r['evaluationID']; ?></td>
                  <td><?php echo safe($r['evaluatorName']); ?> <em>(<?php echo safe($r['evaluatorRole']); ?>)</em></td>
                  <td><?php echo safe($r['evaluateeName']); ?> <em>(<?php echo safe($r['evaluateeRole']); ?>)</em></td>
                  <td><?php echo safe($r['courseName']); ?></td>
                  <td><?php echo safe($r['evaluationType']); ?></td>
                  <td><?php echo safe($r['createdAt']); ?></td>
                  <td class="action-cell">
                    <!-- View/Edit -->
                    <a class="feedback-btn"
                      href="<?php echo build_url(['viewID'=>(int)$r['evaluationID']]); ?>">
                      View / Edit
                    </a>

                    <!-- Report -->
                    <a class="feedback-btn report"
                      href="admin_feedback_report.php?id=<?php echo (int)$r['evaluationID']; ?>">
                      Report
                    </a>

                    <!-- Delete -->
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this feedback record?');">
                      <input type="hidden" name="evaluationID" value="<?php echo (int)$r['evaluationID']; ?>">
                      <button type="submit" name="delete_evaluation" class="action-btn" title="Delete">&#128465;</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="<?php echo build_url(['page'=>$page-1]); ?>">&larr; Previous</a>
        <?php else: ?>
          <span>&larr; Previous</span>
        <?php endif; ?>

        <?php
          // show up to 5 page numbers around current
          $start = max(1, $page - 2);
          $end = min($totalPages, $page + 2);
          if ($start > 1) echo '<span>1</span><span>…</span>';
          for ($p=$start; $p<=$end; $p++) {
            if ($p === $page) echo '<span class="current-page">'.$p.'</span>';
            else echo '<a href="'.safe(build_url(['page'=>$p])).'">'.$p.'</a>';
          }
          if ($end < $totalPages) echo '<span>…</span><span>'.$totalPages.'</span>';
        ?>

        <?php if ($page < $totalPages): ?>
          <a href="<?php echo build_url(['page'=>$page+1]); ?>">Next &rarr;</a>
        <?php else: ?>
          <span>Next &rarr;</span>
        <?php endif; ?>
      </div>

    </div>
  </div>
</body>
</html>
