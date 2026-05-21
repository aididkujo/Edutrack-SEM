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
$courseID = (int)($_GET['courseID'] ?? 0);

$error = '';
$success = '';

$course = null;
if ($courseID > 0) {
  $stmt = $conn->prepare("SELECT courseID, courseName FROM course WHERE courseID = ? AND userID = ?");
  if ($stmt) {
    $stmt->bind_param("ii", $courseID, $lecturerID);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}
if (!$course) {
  $error = 'Course not found or no access';
}

function safe_file_ext($filename)
{
  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  return $ext;
}

function safe_basename($filename)
{
  $name = preg_replace('/[^A-Za-z0-9_\.\s]/', '', $filename);
  $name = trim($name);
  if ($name === '') $name = 'file';
  return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $course) {
  $action = $_POST['action'] ?? '';

  if ($action === 'upload_note') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') {
      $error = 'Title is required';
    } elseif (!isset($_FILES['noteFile']) || $_FILES['noteFile']['error'] !== UPLOAD_ERR_OK) {
      $error = 'Note file is required';
    } else {
      $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg'];
      $origName = $_FILES['noteFile']['name'] ?? '';
      $ext = safe_file_ext($origName);

      if (!in_array($ext, $allowed, true)) {
        $error = 'File type not allowed';
      } else {
        $uploadDir = __DIR__ . '/uploads/notes/';
        $publicDir = 'uploads/notes/';

        if (!is_dir($uploadDir)) {
          @mkdir($uploadDir, 0775, true);
        }

        $base = safe_basename(pathinfo($origName, PATHINFO_FILENAME));
        $unique = date('Ymd_His') . '_' . $lecturerID . '_' . bin2hex(random_bytes(4));
        $finalName = $base . '_' . $unique . '.' . $ext;

        $fullPath = $uploadDir . $finalName;
        $publicPath = $publicDir . $finalName;

        if (!is_dir($uploadDir)) {
          $error = 'Upload folder not available. Create uploads/notes and give permission.';
        } else {
          if (move_uploaded_file($_FILES['noteFile']['tmp_name'], $fullPath)) {
            $stmt = $conn->prepare("
                            INSERT INTO notes (title, description, filePath, uploadedDate, courseID, lecturerID)
                            VALUES (?, ?, ?, CURDATE(), ?, ?)
                        ");
            if ($stmt) {
              $stmt->bind_param("sssii", $title, $description, $publicPath, $courseID, $lecturerID);
              if ($stmt->execute()) {
                $success = 'Note uploaded';
              } else {
                $error = 'Database insert failed';
              }
              $stmt->close();
            } else {
              $error = 'Notes table not ready';
            }
          } else {
            $error = 'File upload failed';
          }
        }
      }
    }
  }

  if ($action === 'delete_note') {
    $noteID = (int)($_POST['noteID'] ?? 0);
    if ($noteID > 0) {
      $stmt = $conn->prepare("SELECT filePath FROM notes WHERE noteID = ? AND courseID = ? AND lecturerID = ?");
      if ($stmt) {
        $stmt->bind_param("iii", $noteID, $courseID, $lecturerID);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
          $stmt2 = $conn->prepare("DELETE FROM notes WHERE noteID = ? AND courseID = ? AND lecturerID = ?");
          if ($stmt2) {
            $stmt2->bind_param("iii", $noteID, $courseID, $lecturerID);
            $stmt2->execute();
            $stmt2->close();
          }

          $fp = $row['filePath'] ?? '';
          if ($fp !== '') {
            $abs = __DIR__ . '/' . $fp;
            if (is_file($abs)) {
              @unlink($abs);
            }
          }
          $success = 'Note deleted';
        }
      }
    }
  }
}

$notes = [];
if ($course) {
  $stmt = $conn->prepare("
        SELECT noteID, title, description, filePath, uploadedDate
        FROM notes
        WHERE courseID = ? AND lecturerID = ?
        ORDER BY uploadedDate DESC, noteID DESC
    ");
  if ($stmt) {
    $stmt->bind_param("ii", $courseID, $lecturerID);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $notes[] = $row;
    }
    $stmt->close();
  } else {
    $error = $error ?: 'Notes table not ready';
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
  <title>EduTrack Lecturer Notes</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap {
      padding: 20px;
    }

    .msg {
      max-width: 980px;
      margin: 10px auto 0 auto;
      padding: 10px 12px;
      border-radius: 10px;
      font-size: 13px;
    }

    .msg.error {
      background: #fee2e2;
      color: #7f1d1d;
    }

    .msg.success {
      background: #dcfce7;
      color: #14532d;
    }

    .box {
      max-width: 980px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.85);
      border-radius: 14px;
      padding: 16px;
    }

    .row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
    }

    .row input[type="text"],
    .row textarea,
    .row input[type="file"] {
      flex: 1;
      min-width: 220px;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      outline: none;
      background: #fff;
    }

    .row textarea {
      min-width: 100%;
      min-height: 80px;
    }

    .btn {
      padding: 10px 14px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      background: #1f2937;
      color: #fff;
      font-weight: 900;
    }

    .btnDanger {
      background: #7f1d1d;
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

    .list {
      max-width: 980px;
      margin: 14px auto 0 auto;
      display: grid;
      gap: 10px;
    }

    .item {
      background: #9fe6ee;
      border-radius: 14px;
      padding: 14px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    .name {
      font-weight: 900;
      color: #111827;
    }

    .meta {
      font-size: 12px;
      opacity: 0.85;
      margin-top: 4px;
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
        <li><a href="lecturer_myfeedback.php">My Feedback</a></li>
        <li><a href="studevaluationlist.php">Evaluation</a></li>
        <li><a href="profile.php">My Profile</a></li>
      </ul>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
    </div>

    <div class="main-content">
      <div class="topbar">
        <h2><?php echo $course ? htmlspecialchars($course['courseName']) : 'Notes'; ?></h2>
      </div>

      <div class="wrap">
        <?php if ($error !== ''): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="msg success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <?php if ($course): ?>
          <div class="box">
            <div class="row" style="justify-content:space-between;">
              <div style="font-weight:900;">Notes</div>
              <a class="btnLink" href="lecturer_subject.php?courseID=<?php echo (int)$courseID; ?>">Back</a>
            </div>

            <div style="height:12px;"></div>

            <form method="POST" action="" enctype="multipart/form-data">
              <input type="hidden" name="action" value="upload_note">

              <div class="row">
                <input type="text" name="title" maxlength="255" placeholder="Title" required>
              </div>

              <div style="height:10px;"></div>

              <div class="row">
                <textarea name="description" maxlength="255" placeholder="Description"></textarea>
              </div>

              <div style="height:10px;"></div>

              <div class="row">
                <input type="file" name="noteFile" accept=".pdf,.doc,.docx,.ppt,.pptx,.png,.jpg,.jpeg" required>
                <button class="btn" type="submit">Upload Note</button>
              </div>
            </form>
          </div>

          <div class="list">
            <?php if (count($notes) === 0): ?>
              <div class="box" style="text-align:center; opacity:0.75;">No notes uploaded</div>
            <?php else: ?>
              <?php foreach ($notes as $n): ?>
                <div class="item">
                  <div>
                    <div class="name"><?php echo htmlspecialchars($n['title']); ?></div>
                    <div class="meta">
                      Uploaded <?php echo htmlspecialchars($n['uploadedDate']); ?>
                      <?php echo ($n['description'] ?? '') !== '' ? ' , ' . htmlspecialchars($n['description']) : ''; ?>
                    </div>
                    <div style="margin-top:8px;">
                      <a class="btnLink" href="<?php echo htmlspecialchars($n['filePath']); ?>" target="_blank">Open</a>
                    </div>
                  </div>

                  <form method="POST" action="" style="margin:0;">
                    <input type="hidden" name="action" value="delete_note">
                    <input type="hidden" name="noteID" value="<?php echo (int)$n['noteID']; ?>">
                    <button class="btn btnDanger" type="submit">Delete</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>