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
$fullName   = $_SESSION['full_name'] ?? 'Lecturer';

$courseID = isset($_GET['courseID']) ? (int)$_GET['courseID'] : 0;
if ($courseID <= 0) {
  header("Location: lecturer_dashboard.php");
  exit;
}

/* Verify course belongs to lecturer (course.userID) */
$courseStmt = $conn->prepare("SELECT courseName FROM course WHERE courseID = ? AND userID = ? LIMIT 1");
$courseStmt->bind_param("ii", $courseID, $lecturerID);
$courseStmt->execute();
$course = $courseStmt->get_result()->fetch_assoc();
$courseStmt->close();
if (!$course) {
  header("Location: lecturer_dashboard.php");
  exit;
}

function rand_code(int $len = 6): string
{
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $out = '';
  for ($i = 0; $i < $len; $i++) {
    $out .= $chars[random_int(0, strlen($chars) - 1)];
  }
  return $out;
}

function table_exists(mysqli $conn, string $table): bool
{
  $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
  $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
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
$error   = '';

/* selected date and slot */
$selectedDate = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : date('Y-m-d');
$selectedSlotID = isset($_GET['slotID']) ? (int)$_GET['slotID'] : 0;

/* Handle actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $slotTableOk) {
  $action = $_POST['action'] ?? '';

  if ($action === 'create_slot') {
    $slotDate = trim((string)($_POST['slotDate'] ?? ''));
    $slotTime = trim((string)($_POST['slotTime'] ?? ''));
    $code     = strtoupper(trim((string)($_POST['code'] ?? '')));
    if ($code === '') {
      $code = rand_code(6);
    }

    if ($slotDate === '' || $slotTime === '') {
      $error = 'Please fill slot date and time.';
    } else {
      $ins = $conn->prepare("INSERT INTO attendance_slot (courseID, slotDate, slotTime, code, createdBy)
                             VALUES (?, ?, ?, ?, ?)");
      $ins->bind_param("isssi", $courseID, $slotDate, $slotTime, $code, $lecturerID);
      try {
        $ins->execute();
        $newID = (int)$conn->insert_id;
        $success = 'Attendance slot created.';
        $selectedDate = $slotDate;
        $selectedSlotID = $newID;
      } catch (Throwable $e) {
        $error = 'Create slot failed. Same date and time might already exist.';
      }
      $ins->close();
    }
  }

  if ($action === 'update_slot') {
    $slotID   = (int)($_POST['slotID'] ?? 0);
    $slotDate = trim((string)($_POST['slotDate'] ?? ''));
    $slotTime = trim((string)($_POST['slotTime'] ?? ''));
    $code     = strtoupper(trim((string)($_POST['code'] ?? '')));

    if ($slotID <= 0 || $slotDate === '' || $slotTime === '' || $code === '') {
      $error = 'Please fill slot date, time, and code.';
    } else {
      $conn->begin_transaction();
      try {
        /* Ensure slot belongs to this course */
        $chk = $conn->prepare("SELECT slotID FROM attendance_slot WHERE slotID = ? AND courseID = ? LIMIT 1");
        $chk->bind_param("ii", $slotID, $courseID);
        $chk->execute();
        $ok = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$ok) {
          throw new Exception('Not allowed');
        }

        $up = $conn->prepare("UPDATE attendance_slot
                              SET slotDate = ?, slotTime = ?, code = ?
                              WHERE slotID = ? AND courseID = ?");
        $up->bind_param("sssii", $slotDate, $slotTime, $code, $slotID, $courseID);
        $up->execute();
        $up->close();

        /* Keep attendance.sessionDate aligned with slotDate */
        if ($attendanceHasSlot) {
          $up2 = $conn->prepare("UPDATE attendance SET sessionDate = ? WHERE courseID = ? AND slotID = ?");
          $up2->bind_param("sii", $slotDate, $courseID, $slotID);
          $up2->execute();
          $up2->close();
        }

        $conn->commit();
        $success = 'Attendance slot updated.';
        $selectedDate = $slotDate;
        $selectedSlotID = $slotID;
      } catch (Throwable $e) {
        $conn->rollback();
        $error = 'Update slot failed.';
      }
    }
  }

  if ($action === 'delete_slot') {
    $slotID = (int)($_POST['slotID'] ?? 0);
    if ($slotID > 0) {
      $conn->begin_transaction();
      try {
        if ($attendanceHasSlot) {
          $delA = $conn->prepare("DELETE FROM attendance WHERE courseID = ? AND slotID = ?");
          $delA->bind_param("ii", $courseID, $slotID);
          $delA->execute();
          $delA->close();
        }

        $delS = $conn->prepare("DELETE FROM attendance_slot WHERE slotID = ? AND courseID = ?");
        $delS->bind_param("ii", $slotID, $courseID);
        $delS->execute();
        $delS->close();

        $conn->commit();
        $success = 'Attendance slot deleted.';
        $selectedSlotID = 0;
      } catch (Throwable $e) {
        $conn->rollback();
        $error = 'Delete slot failed.';
      }
    }
  }

  if ($action === 'save_attendance') {
    $slotID = (int)($_POST['slotID'] ?? 0);
    if ($slotID <= 0) {
      $error = 'Please select an attendance slot.';
    } elseif (!$attendanceHasSlot) {
      $error = 'Database missing attendance.slotID. Run the SQL patch first.';
    } else {
      /* Read slot date */
      $s = $conn->prepare("SELECT slotDate FROM attendance_slot WHERE slotID = ? AND courseID = ? LIMIT 1");
      $s->bind_param("ii", $slotID, $courseID);
      $s->execute();
      $slot = $s->get_result()->fetch_assoc();
      $s->close();
      if (!$slot) {
        $error = 'Slot not found.';
      } else {
        $slotDate = $slot['slotDate'];

        /* Load students enrolled */
        $st = $conn->prepare("
          SELECT u.userID, u.full_name, u.email
          FROM enrollment e
          JOIN `user` u ON u.userID = e.userID
          WHERE e.courseID = ? AND u.role = 'student'
          ORDER BY u.full_name ASC
        ");
        $st->bind_param("i", $courseID);
        $st->execute();
        $students = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $statusArr  = $_POST['status']  ?? [];
        $remarksArr = $_POST['remarks'] ?? [];

        $conn->begin_transaction();
        try {
          $upsert = $conn->prepare("
            INSERT INTO attendance (sessionDate, status, remarks, courseID, userID, slotID" . ($attendanceHasChecked ? ", checkedInAt" : "") . ")
            VALUES (?, ?, ?, ?, ?, ?" . ($attendanceHasChecked ? ", NULL" : "") . ")
            ON DUPLICATE KEY UPDATE
              sessionDate = VALUES(sessionDate),
              status = VALUES(status),
              remarks = VALUES(remarks)
          ");

          foreach ($students as $stu) {
            $uid = (int)$stu['userID'];
            $status = (string)($statusArr[$uid] ?? 'Absent');
            $remarks = trim((string)($remarksArr[$uid] ?? ''));

            $allowed = ['Present', 'Absent', 'Late', 'Excused'];
            if (!in_array($status, $allowed, true)) {
              $status = 'Absent';
            }

            $upsert->bind_param("sssiii", $slotDate, $status, $remarks, $courseID, $uid, $slotID);
            $upsert->execute();
          }

          $upsert->close();
          $conn->commit();
          $success = 'Attendance saved.';
          $selectedSlotID = $slotID;
          $selectedDate = $slotDate;
        } catch (Throwable $e) {
          $conn->rollback();
          $error = 'Save failed.';
        }
      }
    }
  }
}

/* Load slots for selected date (and all slots list) */
$slotsForDate = [];
$allSlots = [];
if ($slotTableOk) {
  $sf = $conn->prepare("SELECT slotID, slotDate, slotTime, code
                        FROM attendance_slot
                        WHERE courseID = ? AND slotDate = ?
                        ORDER BY slotTime ASC");
  $sf->bind_param("is", $courseID, $selectedDate);
  $sf->execute();
  $slotsForDate = $sf->get_result()->fetch_all(MYSQLI_ASSOC);
  $sf->close();

  $sa = $conn->prepare("SELECT slotID, slotDate, slotTime, code
                        FROM attendance_slot
                        WHERE courseID = ?
                        ORDER BY slotDate DESC, slotTime DESC
                        LIMIT 30");
  $sa->bind_param("i", $courseID);
  $sa->execute();
  $allSlots = $sa->get_result()->fetch_all(MYSQLI_ASSOC);
  $sa->close();

  if ($selectedSlotID <= 0 && !empty($slotsForDate)) {
    $selectedSlotID = (int)$slotsForDate[0]['slotID'];
  }
}

/* Load selected slot details */
$selectedSlot = null;
if ($slotTableOk && $selectedSlotID > 0) {
  $ss = $conn->prepare("SELECT slotID, slotDate, slotTime, code
                        FROM attendance_slot
                        WHERE slotID = ? AND courseID = ? LIMIT 1");
  $ss->bind_param("ii", $selectedSlotID, $courseID);
  $ss->execute();
  $selectedSlot = $ss->get_result()->fetch_assoc();
  $ss->close();
}

/* Load students and existing attendance for selected slot */
$students = [];
$attendanceMap = []; // userID => row
$slotDateForView = $selectedSlot['slotDate'] ?? $selectedDate;

$stuStmt = $conn->prepare("
  SELECT u.userID, u.full_name, u.email
  FROM enrollment e
  JOIN `user` u ON u.userID = e.userID
  WHERE e.courseID = ? AND u.role = 'student'
  ORDER BY u.full_name ASC
");
$stuStmt->bind_param("i", $courseID);
$stuStmt->execute();
$students = $stuStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stuStmt->close();

if ($selectedSlotID > 0 && $attendanceHasSlot) {
  $a = $conn->prepare("SELECT userID, status, remarks" . ($attendanceHasChecked ? ", checkedInAt" : "") . "
                       FROM attendance
                       WHERE courseID = ? AND slotID = ?");
  $a->bind_param("ii", $courseID, $selectedSlotID);
  $a->execute();
  $rows = $a->get_result()->fetch_all(MYSQLI_ASSOC);
  $a->close();

  foreach ($rows as $r) {
    $attendanceMap[(int)$r['userID']] = $r;
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
  <title>EduTrack - Lecturer Attendance</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap {
      padding: 18px 26px;
    }

    .course-title {
      font-size: 26px;
      font-weight: 800;
      margin: 0 0 10px;
    }

    .grid {
      display: grid;
      grid-template-columns: 420px 1fr;
      gap: 18px;
      align-items: start;
    }

    .card {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 16px;
      padding: 14px 14px;
    }

    .card h3 {
      margin: 0 0 10px;
      font-size: 16px;
    }

    .row {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 160px;
    }

    .field label {
      font-size: 12px;
      color: #6b7280;
    }

    input[type="date"],
    input[type="time"],
    input[type="text"],
    select {
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      min-width: 180px;
    }

    .btn {
      display: inline-block;
      padding: 9px 14px;
      border-radius: 12px;
      background: #1f2937;
      color: #fff;
      text-decoration: none;
      border: none;
      cursor: pointer;
      font-weight: 700;
    }

    .btn.secondary {
      background: rgba(31, 41, 55, 0.10);
      color: #111827;
    }

    .btn.danger {
      background: #ef4444;
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

    .muted {
      color: #6b7280;
      font-size: 13px;
    }

    .pill {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(31, 41, 55, 0.08);
      font-size: 12px;
    }

    .slot-item {
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 14px;
      padding: 10px 10px;
      margin: 10px 0;
      background: rgba(45, 212, 210, 0.12);
    }

    .slot-head {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
    }

    .slot-code {
      font-weight: 900;
      letter-spacing: 1px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 10px 10px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.08);
      vertical-align: top;
      text-align: left;
    }

    th {
      background: rgba(0, 0, 0, 0.03);
      font-size: 13px;
    }

    .name {
      font-weight: 800;
    }

    .email {
      font-size: 12px;
      color: #6b7280;
    }

    .top-msg {
      margin: 10px 0;
    }

    .alert {
      padding: 10px 12px;
      border-radius: 12px;
    }

    .ok {
      background: rgba(34, 197, 94, 0.12);
      border: 1px solid rgba(34, 197, 94, 0.25);
    }

    .bad {
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.25);
    }

    .right-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .small {
      font-size: 12px;
    }

    @media (max-width: 1100px) {
      .grid {
        grid-template-columns: 1fr;
      }
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
        <li><a href="lecturer_dashboard.php" style="font-weight:700;text-decoration:underline;">Assessment</a></li>
        <li><a href="lecturer_progress.php">Progress</a></li>
        <li><a href="lecturer_myfeedback.php">My Feedback</a></li>
        <li><a href="studevaluationlist.php">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="wrap">
        <div class="course-title"><?php echo htmlspecialchars($course['courseName']); ?></div>
        <div class="muted">Attendance management (slots, records, and code)</div><br>
        <a class="btnLink" href="lecturer_subject.php?courseID=<?php echo (int)$courseID; ?>">Back</a>
        <?php if ($success): ?>
          <div class="top-msg alert ok"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="top-msg alert bad"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$slotTableOk || !$attendanceHasSlot): ?>
          <div class="top-msg alert bad">
            Database is missing attendance slot support.
            Run the SQL patches to create <span class="pill">attendance_slot</span> and add <span class="pill">attendance.slotID</span>.
          </div>
        <?php endif; ?>

        <div class="grid">
          <!-- Left: Slot creation + slot list -->
          <div class="card">
            <h3>Create attendance slot</h3>
            <div class="muted small" style="margin-bottom:10px;">
              Slot consists of date, time, and code. Students check in using the code.
            </div>

            <form method="POST" action="">
              <input type="hidden" name="action" value="create_slot">
              <div class="row">
                <div class="field">
                  <label>Slot date</label>
                  <input type="date" name="slotDate" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
                </div>
                <div class="field">
                  <label>Slot time</label>
                  <input type="time" name="slotTime" required>
                </div>
                <div class="field">
                  <label>Code</label>
                  <input type="text" name="code" maxlength="20" placeholder="Auto if empty">
                </div>
              </div>
              <div class="row" style="margin-top:12px;">
                <button class="btn" type="submit">Create Slot</button>
                <a class="btn secondary" href="lecturer_subject.php?courseID=<?php echo (int)$courseID; ?>">Back</a>
              </div>
            </form>

            <hr style="border:none;border-top:1px solid rgba(0,0,0,0.08);margin:14px 0;">

            <h3>View slots by date</h3>
            <form method="GET" action="">
              <input type="hidden" name="courseID" value="<?php echo (int)$courseID; ?>">
              <div class="row">
                <div class="field">
                  <label>Select date</label>
                  <input type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                </div>
                <button class="btn" type="submit">View</button>
              </div>
            </form>

            <?php if (!$slotTableOk): ?>
              <div class="muted" style="margin-top:10px;">Slots unavailable until SQL patch is applied.</div>
            <?php else: ?>
              <div class="muted" style="margin-top:10px;">Slots for <?php echo htmlspecialchars($selectedDate); ?>:</div>

              <?php if (empty($slotsForDate)): ?>
                <div class="muted" style="margin-top:10px;">No slot created for this date.</div>
              <?php else: ?>
                <?php foreach ($slotsForDate as $s): ?>
                  <?php $active = ((int)$s['slotID'] === (int)$selectedSlotID); ?>
                  <div class="slot-item" style="<?php echo $active ? 'border-color:rgba(31,41,55,0.35);' : ''; ?>">
                    <div class="slot-head">
                      <div>
                        <div style="font-weight:900;">
                          <?php echo htmlspecialchars($s['slotTime']); ?>
                          <span class="pill" style="margin-left:8px;">ID <?php echo (int)$s['slotID']; ?></span>
                        </div>
                        <div class="muted">Code: <span class="slot-code"><?php echo htmlspecialchars($s['code']); ?></span></div>
                      </div>
                      <div class="row" style="justify-content:flex-end;">
                        <a class="btn secondary" href="lecturer_attendance.php?courseID=<?php echo (int)$courseID; ?>&date=<?php echo urlencode($selectedDate); ?>&slotID=<?php echo (int)$s['slotID']; ?>">Open</a>
                      </div>
                    </div>

                    <!-- Edit slot form -->
                    <details style="margin-top:10px;">
                      <summary class="muted" style="cursor:pointer;">Edit slot</summary>
                      <form method="POST" action="" style="margin-top:10px;">
                        <input type="hidden" name="action" value="update_slot">
                        <input type="hidden" name="slotID" value="<?php echo (int)$s['slotID']; ?>">
                        <div class="row">
                          <div class="field">
                            <label>Date</label>
                            <input type="date" name="slotDate" value="<?php echo htmlspecialchars($s['slotDate']); ?>" required>
                          </div>
                          <div class="field">
                            <label>Time</label>
                            <input type="time" name="slotTime" value="<?php echo htmlspecialchars($s['slotTime']); ?>" required>
                          </div>
                          <div class="field">
                            <label>Code</label>
                            <input type="text" name="code" value="<?php echo htmlspecialchars($s['code']); ?>" maxlength="20" required>
                          </div>
                        </div>
                        <div class="row" style="margin-top:10px;">
                          <button class="btn" type="submit">Save Slot</button>
                        </div>
                      </form>

                      <form method="POST" action="" onsubmit="return confirm('Delete this slot and its attendance records?');" style="margin-top:10px;">
                        <input type="hidden" name="action" value="delete_slot">
                        <input type="hidden" name="slotID" value="<?php echo (int)$s['slotID']; ?>">
                        <button class="btn danger" type="submit">Delete Slot</button>
                      </form>
                    </details>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>

              <div class="muted" style="margin-top:14px;">Recent slots (latest 30)</div>
              <?php if (empty($allSlots)): ?>
                <div class="muted" style="margin-top:8px;">No slot created yet.</div>
              <?php else: ?>
                <div style="max-height:240px;overflow:auto;margin-top:8px;border:1px solid rgba(0,0,0,0.08);border-radius:14px;padding:10px;">
                  <?php foreach ($allSlots as $s): ?>
                    <div class="row" style="justify-content:space-between;border-bottom:1px solid rgba(0,0,0,0.06);padding:8px 2px;">
                      <div class="muted">
                        <?php echo htmlspecialchars($s['slotDate']); ?> <?php echo htmlspecialchars($s['slotTime']); ?>
                        <span class="pill" style="margin-left:8px;"><?php echo htmlspecialchars($s['code']); ?></span>
                      </div>
                      <a class="btn secondary" href="lecturer_attendance.php?courseID=<?php echo (int)$courseID; ?>&date=<?php echo urlencode($s['slotDate']); ?>&slotID=<?php echo (int)$s['slotID']; ?>">Open</a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <!-- Right: Attendance record editor -->
          <div class="card">
            <div class="right-actions">
              <div>
                <h3 style="margin:0;">Attendance records</h3>
                <div class="muted">
                  <?php if ($selectedSlot): ?>
                    Slot: <?php echo htmlspecialchars($selectedSlot['slotDate']); ?> <?php echo htmlspecialchars($selectedSlot['slotTime']); ?>
                    | Code: <span class="slot-code"><?php echo htmlspecialchars($selectedSlot['code']); ?></span>
                    | Slot ID: <?php echo (int)$selectedSlot['slotID']; ?>
                  <?php else: ?>
                    Select a slot to edit attendance.
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div style="margin-top:12px;">
              <?php if (empty($students)): ?>
                <div class="muted">No students enrolled for this course.</div>
              <?php else: ?>
                <form method="POST" action="">
                  <input type="hidden" name="action" value="save_attendance">
                  <input type="hidden" name="slotID" value="<?php echo (int)$selectedSlotID; ?>">

                  <table>
                    <thead>
                      <tr>
                        <th style="width:45%;">Student</th>
                        <th style="width:20%;">Status</th>
                        <th>Remarks</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($students as $stu): ?>
                        <?php
                        $uid = (int)$stu['userID'];
                        $row = $attendanceMap[$uid] ?? null;
                        $status = $row['status'] ?? 'Absent';
                        $remarks = $row['remarks'] ?? '';
                        $checked = ($attendanceHasChecked && isset($row['checkedInAt']) && $row['checkedInAt']) ? $row['checkedInAt'] : '';
                        ?>
                        <tr>
                          <td>
                            <div class="name"><?php echo htmlspecialchars($stu['full_name']); ?></div>
                            <div class="email"><?php echo htmlspecialchars($stu['email']); ?></div>
                            <?php if ($checked !== ''): ?>
                              <div class="muted small">Checked in: <?php echo htmlspecialchars($checked); ?></div>
                            <?php endif; ?>
                          </td>
                          <td>
                            <select name="status[<?php echo $uid; ?>]">
                              <option value="Present" <?php echo ($status === 'Present') ? 'selected' : ''; ?>>Present</option>
                              <option value="Absent" <?php echo ($status === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                              <option value="Late" <?php echo ($status === 'Late') ? 'selected' : ''; ?>>Late</option>
                              <option value="Excused" <?php echo ($status === 'Excused') ? 'selected' : ''; ?>>Excused</option>
                            </select>
                          </td>
                          <td>
                            <input type="text" name="remarks[<?php echo $uid; ?>]" value="<?php echo htmlspecialchars($remarks); ?>" placeholder="Optional remarks" style="width:100%;min-width:240px;">
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>

                  <div class="row" style="margin-top:14px;">
                    <button class="btn" type="submit" <?php echo ($selectedSlotID <= 0 ? 'disabled' : ''); ?>>Save Attendance</button>
                    <a class="btn secondary" href="lecturer_attendance.php?courseID=<?php echo (int)$courseID; ?>&date=<?php echo urlencode($slotDateForView); ?>">Refresh</a>
                  </div>

                  <div class="muted small" style="margin-top:10px;">
                    Tip: Student check-in page can validate the slot code and set status to Present, then this page can still modify records if needed.
                  </div>
                </form>
              <?php endif; ?>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>
</body>

</html>