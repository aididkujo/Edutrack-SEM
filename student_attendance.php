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

if ($courseID <= 0) {
  header("Location: student_courses.php");
  exit;
}

/* Enrollment check */
$chk = $conn->prepare("SELECT 1 FROM enrollment WHERE userID = ? AND courseID = ? LIMIT 1");
$chk->bind_param("ii", $userID, $courseID);
$chk->execute();
$enrolled = $chk->get_result()->fetch_row();
$chk->close();
if (!$enrolled) {
  header("Location: student_courses.php");
  exit;
}

/* Course name */
$cstmt = $conn->prepare("SELECT courseName FROM course WHERE courseID = ? LIMIT 1");
$cstmt->bind_param("i", $courseID);
$cstmt->execute();
$crow = $cstmt->get_result()->fetch_assoc();
$cstmt->close();
$courseName = $crow ? $crow['courseName'] : "Course";

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function table_exists(mysqli $conn, string $table): bool
{
  $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
  $sql = "SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'";
  $res = $conn->query($sql);
  return $res && $res->num_rows > 0;
}

function has_column(mysqli $conn, string $table, string $col): bool
{
  $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
  $col   = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
  $sql = "SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($col) . "'";
  $res = $conn->query($sql);
  return $res && $res->num_rows > 0;
}

$slotTableOk = table_exists($conn, 'attendance_slot');
$attendanceHasSlot = has_column($conn, 'attendance', 'slotID');
$attendanceHasChecked = has_column($conn, 'attendance', 'checkedInAt');

$success = '';
$error = '';

/*
  New feature: student check-in by code (course specific)
  Requires:
    - attendance_slot table exists
    - attendance has slotID and checkedInAt
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
  $code = strtoupper(trim((string)($_POST['code'] ?? '')));

  if ($code === '') {
    $error = 'Attendance code is required.';
  } elseif (!$slotTableOk || !$attendanceHasSlot) {
    $error = 'Attendance slot feature not available. Database patch required.';
  } else {
    $s = $conn->prepare("
      SELECT slotID, slotDate, slotTime
      FROM attendance_slot
      WHERE courseID = ? AND code = ?
      ORDER BY slotDate DESC, slotTime DESC, slotID DESC
      LIMIT 1
    ");
    $s->bind_param("is", $courseID, $code);
    $s->execute();
    $slot = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$slot) {
      $error = 'Invalid code for this course.';
    } else {
      $slotID = (int)$slot['slotID'];
      $slotDate = $slot['slotDate'] ?? null;

      $present = 'Present';

      if ($attendanceHasChecked) {
        $ins = $conn->prepare("
          INSERT INTO attendance (sessionDate, status, remarks, courseID, userID, slotID, checkedInAt)
          VALUES (?, ?, NULL, ?, ?, ?, NOW())
          ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            checkedInAt = NOW()
        ");
        $ins->bind_param("ssiii", $slotDate, $present, $courseID, $userID, $slotID);
      } else {
        $ins = $conn->prepare("
          INSERT INTO attendance (sessionDate, status, remarks, courseID, userID, slotID)
          VALUES (?, ?, NULL, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            status = VALUES(status)
        ");
        $ins->bind_param("ssiii", $slotDate, $present, $courseID, $userID, $slotID);
      }

      if ($ins->execute()) {
        $success = 'Check-in successful.';
      } else {
        $error = 'Check-in failed. Please try again.';
      }
      $ins->close();
    }
  }
}

/* Attendance list aligned to slot feature */
$rows = [];

if ($slotTableOk && $attendanceHasSlot) {
  $sql = "
    SELECT
      s.slotDate,
      s.slotTime,
      s.code,
      a.status,
      a.remarks
      " . ($attendanceHasChecked ? ", a.checkedInAt" : "") . "
    FROM attendance a
    LEFT JOIN attendance_slot s ON s.slotID = a.slotID
    WHERE a.courseID = ? AND a.userID = ?
    ORDER BY
      COALESCE(s.slotDate, a.sessionDate) DESC,
      COALESCE(s.slotTime, '00:00:00') DESC,
      a.attendanceID DESC
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $courseID, $userID);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
} else {
  /* fallback to old structure */
  $stmt = $conn->prepare("SELECT sessionDate, status, remarks FROM attendance WHERE courseID = ? AND userID = ? ORDER BY sessionDate DESC");
  $stmt->bind_param("ii", $courseID, $userID);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

/* summary */
$total = count($rows);
$presentCount = 0;
foreach ($rows as $r) {
  $st = strtolower((string)($r['status'] ?? ''));
  if ($st === 'present') {
    $presentCount++;
  }
}
$rate = ($total > 0) ? round(($presentCount / $total) * 100, 2) : 0.00;

function fmt_time($t)
{
  if (!$t) return '-';
  return date('H:i', strtotime($t));
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
  <title>EduTrack - Attendance</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap {
      padding: 22px;
    }

    .panel {
      background: rgba(255, 255, 255, 0.88);
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 16px;
      padding: 14px;
      margin-top: 14px;
      max-width: 1100px;
    }

    .summary {
      margin-top: 10px;
      padding: 12px;
      background: rgba(0, 0, 0, 0.03);
      border-radius: 12px;
      display: inline-block;
      border: 1px solid rgba(0, 0, 0, 0.08);
    }

    .muted {
      color: #6b7280;
      font-size: 13px;
    }

    .msg {
      margin-top: 10px;
      padding: 10px 12px;
      border-radius: 12px;
      font-size: 13px;
    }

    .msg.ok {
      background: rgba(34, 197, 94, 0.12);
      border: 1px solid rgba(34, 197, 94, 0.25);
      color: #14532d;
    }

    .msg.bad {
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.25);
      color: #7f1d1d;
    }

    .row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: end;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 220px;
    }

    .field label {
      font-size: 12px;
      color: #6b7280;
      font-weight: 800;
    }

    .field input {
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      background: #fff;
    }

    .btn {
      display: inline-block;
      background: #000;
      color: #fff;
      text-decoration: none;
      padding: 10px 16px;
      border-radius: 12px;
      font-weight: 900;
      border: none;
      cursor: pointer;
      transition: opacity 0.12s ease-in-out, transform 0.08s ease-in-out;
    }

    .btn:hover {
      opacity: 0.9;
      transform: scale(1.01);
    }

    .btn.soft {
      background: rgba(0, 0, 0, 0.08);
      color: #111827;
      border: 1px solid rgba(0, 0, 0, 0.12);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 14px;
    }

    th,
    td {
      border-bottom: 1px solid rgba(0, 0, 0, 0.10);
      padding: 10px;
      text-align: left;
      vertical-align: top;
    }

    th {
      background: rgba(0, 0, 0, 0.03);
    }

    .pill {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(31, 41, 55, 0.08);
      font-size: 12px;
      font-weight: 900;
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
        <li><a href="student_courses.php">My Courses</a></li>
        <li><a href="student_progress.php">My Progress</a></li>
        <li><a href="student_myfeedback.php">My Feedback</a></li>
        <li><a href="lectevaluationlist.php">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="topbar">
        <h2>Attendance</h2>
      </div>

      <div class="wrap">
        <div class="panel">
          <div><strong>Course:</strong> <?php echo h($courseName); ?></div>
          <div class="summary">
            <strong>Total Sessions:</strong> <?php echo (int)$total; ?> |
            <strong>Present:</strong> <?php echo (int)$presentCount; ?> |
            <strong>Rate:</strong> <?php echo number_format($rate, 2); ?>%
          </div>

          <?php if ($success): ?><div class="msg ok"><?php echo h($success); ?></div><?php endif; ?>
          <?php if ($error): ?><div class="msg bad"><?php echo h($error); ?></div><?php endif; ?>

          <?php if ($slotTableOk && $attendanceHasSlot): ?>
            <div style="margin-top:14px;">
              <div style="font-weight:900;margin-bottom:6px;">Attendance Check-in</div>
              <div class="muted">Enter the code given by the lecturer for this course slot.</div>

              <form method="POST" action="" style="margin-top:10px;">
                <input type="hidden" name="action" value="checkin">
                <div class="row">
                  <div class="field">
                    <label>Attendance code</label>
                    <input type="text" name="code" maxlength="20" placeholder="Example: ABC123" required>
                  </div>
                  <button class="btn" type="submit">Check In</button>
                </div>
              </form>
            </div>
          <?php else: ?>
            <div class="muted" style="margin-top:14px;">
              Check-in by code is not available yet. Database patch is required.
            </div>
          <?php endif; ?>
        </div>

        <div class="panel">
          <?php if (empty($rows)) : ?>
            <p>No attendance records found for this course yet.</p>
          <?php else : ?>
            <table>
              <thead>
                <tr>
                  <?php if ($slotTableOk && $attendanceHasSlot): ?>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <?php if ($attendanceHasChecked): ?><th>Checked in at</th><?php endif; ?>
                  <?php else: ?>
                    <th>Session Date</th>
                    <th>Status</th>
                    <th>Remarks</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <?php if ($slotTableOk && $attendanceHasSlot): ?>
                      <td><?php echo h($r['slotDate'] ?? $r['sessionDate'] ?? '-'); ?></td>
                      <td><?php echo h(fmt_time($r['slotTime'] ?? '')); ?></td>
                      <td><?php echo h($r['code'] ?? '-'); ?></td>
                      <td><span class="pill"><?php echo h($r['status'] ?? '-'); ?></span></td>
                      <td><?php echo h(($r['remarks'] ?? '') !== '' ? $r['remarks'] : '-'); ?></td>
                      <?php if ($attendanceHasChecked): ?>
                        <td class="muted"><?php echo h(fmt_dt($r['checkedInAt'] ?? '')); ?></td>
                      <?php endif; ?>
                    <?php else: ?>
                      <td><?php echo h($r['sessionDate']); ?></td>
                      <td><?php echo h($r['status']); ?></td>
                      <td><?php echo h(($r['remarks'] ?? '') !== '' ? $r['remarks'] : '-'); ?></td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <div style="margin-top:14px;">
            <a class="btn" href="student_subject.php?courseID=<?php echo (int)$courseID; ?>">Back to Subject</a>
          </div>
        </div>

      </div>
    </div>
  </div>
</body>

</html>