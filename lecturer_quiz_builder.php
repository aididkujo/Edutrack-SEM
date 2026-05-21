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

$lecturerID  = (int)($_SESSION['userID'] ?? 0);
$assessmentID = (int)($_GET['assessmentID'] ?? 0);

$success = '';
$error = '';

function safe_ext(string $name): string
{
  return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

function safe_name(string $name): string
{
  $name = preg_replace('/[^A-Za-z0-9_\.\s-]/', '', $name);
  $name = trim($name);
  return $name === '' ? 'file' : $name;
}

function upload_question_image(string $inputName, int $lecturerID): array
{
  if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
    return ['ok' => false, 'path' => null, 'err' => ''];
  }

  $allowed = ['png', 'jpg', 'jpeg', 'webp'];
  $orig = $_FILES[$inputName]['name'] ?? '';
  $ext = safe_ext($orig);

  if (!in_array($ext, $allowed, true)) {
    return ['ok' => false, 'path' => null, 'err' => 'Image type not allowed. Use png, jpg, jpeg, webp.'];
  }

  $uploadDir = __DIR__ . '/uploads/quiz_questions/';
  $publicDir = 'uploads/quiz_questions/';

  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
  }

  if (!is_dir($uploadDir)) {
    return ['ok' => false, 'path' => null, 'err' => 'Upload folder not available. Create uploads/quiz_questions and give permission.'];
  }

  $base = safe_name(pathinfo($orig, PATHINFO_FILENAME));
  $unique = date('Ymd_His') . '_' . $lecturerID . '_' . bin2hex(random_bytes(4));
  $final = $base . '_' . $unique . '.' . $ext;

  $abs = $uploadDir . $final;
  $pub = $publicDir . $final;

  if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $abs)) {
    return ['ok' => false, 'path' => null, 'err' => 'Image upload failed.'];
  }

  return ['ok' => true, 'path' => $pub, 'err' => ''];
}

/* 1) Validate assessment ownership and get course info */
$info = null;
if ($assessmentID > 0) {
  $stmt = $conn->prepare("
    SELECT a.assessmentID, a.tittle AS quizTitle, a.courseID, c.courseName
    FROM assessment a
    JOIN course c ON c.courseID = a.courseID
    WHERE a.assessmentID = ?
      AND a.type = 'Quiz'
      AND c.userID = ?
    LIMIT 1
  ");
  if ($stmt) {
    $stmt->bind_param("ii", $assessmentID, $lecturerID);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

if (!$info) {
  header("Location: lecturer_courses.php");
  exit;
}

/* 2) Handle actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add_question') {
    $questionType = $_POST['questionType'] ?? 'objective';
    if ($questionType !== 'objective' && $questionType !== 'subjective') $questionType = 'objective';

    $marks = (float)($_POST['marks'] ?? 1);
    if ($marks <= 0) $marks = 1;

    $questionOrder = (int)($_POST['questionOrder'] ?? 1);
    if ($questionOrder <= 0) $questionOrder = 1;

    $questionText = trim($_POST['questionText'] ?? '');
    if ($questionText === '') {
      $error = 'Question text is required.';
    } else {
      $imgPath = null;
      $up = upload_question_image('questionImage', $lecturerID);
      if ($up['err'] !== '') {
        $error = $up['err'];
      }
      if ($up['ok']) {
        $imgPath = $up['path'];
      }

      if ($error === '') {
        $stmt = $conn->prepare("
          INSERT INTO quiz_question (assessmentID, questionType, questionText, imagePath, marks, questionOrder)
          VALUES (?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
          $stmt->bind_param("isssdi", $assessmentID, $questionType, $questionText, $imgPath, $marks, $questionOrder);
          if ($stmt->execute()) {
            $success = 'Question added.';
          } else {
            $error = 'Failed to add question.';
          }
          $stmt->close();
        } else {
          $error = 'Database error when adding question.';
        }
      }
    }
  }

  if ($action === 'add_option') {
    $questionID = (int)($_POST['questionID'] ?? 0);
    $optionText = trim($_POST['optionText'] ?? '');
    $optionOrder = (int)($_POST['optionOrder'] ?? 1);
    if ($optionOrder <= 0) $optionOrder = 1;

    if ($questionID <= 0 || $optionText === '') {
      $error = 'Option text is required.';
    } else {
      $chk = $conn->prepare("SELECT 1 FROM quiz_question WHERE questionID = ? AND assessmentID = ? LIMIT 1");
      $chk->bind_param("ii", $questionID, $assessmentID);
      $chk->execute();
      $ok = $chk->get_result()->fetch_assoc();
      $chk->close();

      if (!$ok) {
        $error = 'Invalid question.';
      } else {
        $stmt = $conn->prepare("INSERT INTO quiz_option (questionID, optionText, optionOrder, isCorrect) VALUES (?, ?, ?, 0)");
        if ($stmt) {
          $stmt->bind_param("isi", $questionID, $optionText, $optionOrder);
          if ($stmt->execute()) {
            $success = 'Option added.';
          } else {
            $error = 'Failed to add option.';
          }
          $stmt->close();
        } else {
          $error = 'Database error when adding option.';
        }
      }
    }
  }

  if ($action === 'set_correct') {
    $questionID = (int)($_POST['questionID'] ?? 0);
    $optionID = (int)($_POST['optionID'] ?? 0);

    if ($questionID <= 0 || $optionID <= 0) {
      $error = 'Invalid correct option.';
    } else {
      $conn->begin_transaction();
      try {
        $z = $conn->prepare("UPDATE quiz_option SET isCorrect = 0 WHERE questionID = ?");
        $z->bind_param("i", $questionID);
        $z->execute();
        $z->close();

        $u = $conn->prepare("
          UPDATE quiz_option
          SET isCorrect = 1
          WHERE optionID = ? AND questionID = ?
        ");
        $u->bind_param("ii", $optionID, $questionID);
        $u->execute();
        $u->close();

        $conn->commit();
        $success = 'Correct option updated.';
      } catch (Throwable $e) {
        $conn->rollback();
        $error = 'Failed to update correct option.';
      }
    }
  }

  if ($action === 'update_image') {
    $questionID = (int)($_POST['questionID'] ?? 0);

    if ($questionID <= 0) {
      $error = 'Invalid question.';
    } else {
      $chk = $conn->prepare("SELECT imagePath FROM quiz_question WHERE questionID = ? AND assessmentID = ? LIMIT 1");
      $chk->bind_param("ii", $questionID, $assessmentID);
      $chk->execute();
      $row = $chk->get_result()->fetch_assoc();
      $chk->close();

      if (!$row) {
        $error = 'Invalid question.';
      } else {
        $up = upload_question_image('newQuestionImage', $lecturerID);
        if ($up['err'] !== '') {
          $error = $up['err'];
        } elseif (!$up['ok']) {
          $error = 'Image is required.';
        } else {
          $newPath = $up['path'];

          $stmt = $conn->prepare("UPDATE quiz_question SET imagePath = ? WHERE questionID = ? AND assessmentID = ?");
          $stmt->bind_param("sii", $newPath, $questionID, $assessmentID);
          $stmt->execute();
          $stmt->close();

          $old = $row['imagePath'] ?? '';
          if ($old !== '') {
            $abs = __DIR__ . '/' . $old;
            if (is_file($abs)) @unlink($abs);
          }

          $success = 'Question image updated.';
        }
      }
    }
  }

  if ($action === 'delete_question') {
    $questionID = (int)($_POST['questionID'] ?? 0);
    if ($questionID <= 0) {
      $error = 'Invalid question.';
    } else {
      $conn->begin_transaction();
      try {
        $qimg = null;
        $g = $conn->prepare("SELECT imagePath FROM quiz_question WHERE questionID = ? AND assessmentID = ? LIMIT 1");
        $g->bind_param("ii", $questionID, $assessmentID);
        $g->execute();
        $qimgRow = $g->get_result()->fetch_assoc();
        $g->close();
        if ($qimgRow) $qimg = $qimgRow['imagePath'] ?? null;

        $d1 = $conn->prepare("DELETE FROM quiz_option WHERE questionID = ?");
        $d1->bind_param("i", $questionID);
        $d1->execute();
        $d1->close();

        $d2 = $conn->prepare("DELETE FROM quiz_question WHERE questionID = ? AND assessmentID = ?");
        $d2->bind_param("ii", $questionID, $assessmentID);
        $d2->execute();
        $d2->close();

        $conn->commit();
        if ($qimg) {
          $abs = __DIR__ . '/' . $qimg;
          if (is_file($abs)) @unlink($abs);
        }
        $success = 'Question deleted.';
      } catch (Throwable $e) {
        $conn->rollback();
        $error = 'Failed to delete question.';
      }
    }
  }
}

/* 3) Load questions and options */
$questions = [];
$q = $conn->prepare("
  SELECT questionID, questionType, questionText, imagePath, marks, questionOrder
  FROM quiz_question
  WHERE assessmentID = ?
  ORDER BY questionOrder ASC, questionID ASC
");
$q->bind_param("i", $assessmentID);
$q->execute();
$questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$q->close();

$optsByQ = [];
foreach ($questions as $row) {
  $qid = (int)$row['questionID'];
  $o = $conn->prepare("
    SELECT optionID, optionText, optionOrder, isCorrect
    FROM quiz_option
    WHERE questionID = ?
    ORDER BY optionOrder ASC, optionID ASC
  ");
  $o->bind_param("i", $qid);
  $o->execute();
  $optsByQ[$qid] = $o->get_result()->fetch_all(MYSQLI_ASSOC);
  $o->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>EduTrack Lecturer Quiz Builder</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .wrap {
      padding: 18px 26px;
    }

    .msg {
      max-width: 980px;
      margin: 10px auto 0 auto;
      padding: 10px 12px;
      border-radius: 10px;
      font-size: 13px;
    }

    .msg.ok {
      background: #dcfce7;
      color: #14532d;
    }

    .msg.err {
      background: #fee2e2;
      color: #7f1d1d;
    }

    .panel {
      max-width: 980px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.86);
      border-radius: 16px;
      padding: 16px;
    }

    .head {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    .quizTitle {
      font-weight: 900;
      font-size: 20px;
      color: #111827;
    }

    .courseName {
      font-weight: 800;
      opacity: 0.85;
      margin-top: 4px;
    }

    .btnLink {
      display: inline-block;
      text-decoration: none;
      background: rgba(255, 255, 255, 0.85);
      color: #111827;
      padding: 9px 12px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 900;
    }

    .formGrid {
      display: grid;
      grid-template-columns: 1fr 180px 180px;
      gap: 12px;
      align-items: end;
      margin-top: 12px;
    }

    .field label {
      display: block;
      font-size: 12px;
      font-weight: 900;
      opacity: 0.85;
      margin-bottom: 6px;
    }

    .field select,
    .field input,
    .field textarea {
      width: 100%;
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      outline: none;
      background: #fff;
    }

    .field textarea {
      min-height: 90px;
      resize: vertical;
    }

    .help {
      font-size: 12px;
      opacity: 0.7;
      margin-top: 8px;
      line-height: 1.4;
    }

    .btn {
      padding: 10px 14px;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      background: #111827;
      color: #fff;
      font-weight: 900;
    }

    .btnDanger {
      background: #7f1d1d;
    }

    .qList {
      max-width: 980px;
      margin: 16px auto 0 auto;
      display: grid;
      gap: 12px;
    }

    .qCard {
      background: #9fe6ee;
      border-radius: 16px;
      padding: 14px;
    }

    .qTop {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    .qText {
      font-weight: 900;
      font-size: 15px;
      color: #111827;
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.75);
      font-size: 12px;
      font-weight: 900;
      color: #111827;
    }

    .meta {
      font-size: 12px;
      opacity: 0.85;
      margin-top: 6px;
    }

    .imgRow {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: flex-start;
      margin-top: 10px;
    }

    .thumb {
      width: 140px;
      height: 110px;
      border-radius: 12px;
      object-fit: cover;
      background: rgba(255, 255, 255, 0.6);
      border: 1px solid rgba(0, 0, 0, 0.12);
    }

    .imgBox {
      background: rgba(255, 255, 255, 0.75);
      border-radius: 12px;
      padding: 10px;
      flex: 1;
      min-width: 240px;
    }

    .miniHelp {
      font-size: 12px;
      opacity: 0.75;
      margin-top: 6px;
    }

    .optBox {
      background: rgba(255, 255, 255, 0.75);
      border-radius: 14px;
      padding: 12px;
      margin-top: 12px;
    }

    .optGrid {
      display: grid;
      grid-template-columns: 1fr 110px auto;
      gap: 10px;
      align-items: end;
    }

    .optRow {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      padding: 10px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.85);
      margin-top: 8px;
    }

    .optText {
      font-weight: 800;
    }

    .pillOk {
      font-size: 12px;
      font-weight: 900;
      padding: 5px 10px;
      border-radius: 999px;
      background: #16a34a;
      color: #fff;
    }

    .pillNo {
      font-size: 12px;
      font-weight: 900;
      padding: 5px 10px;
      border-radius: 999px;
      background: rgba(17, 24, 39, 0.15);
      color: #111827;
    }

    @media (max-width: 900px) {
      .formGrid {
        grid-template-columns: 1fr;
      }

      .optGrid {
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
        <h2><?php echo htmlspecialchars($info['quizTitle']); ?></h2>
      </div>

      <div class="wrap">
        <?php if ($success): ?><div class="msg ok"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="panel">
          <div class="head">
            <div>
              <div class="quizTitle"><?php echo htmlspecialchars($info['quizTitle']); ?></div>
              <div class="courseName"><?php echo htmlspecialchars($info['courseName']); ?></div>
            </div>
            <a class="btnLink" href="lecturer_quiz.php?courseID=<?php echo (int)$info['courseID']; ?>">Back</a>
          </div>

          <div style="height:10px;"></div>

          <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_question">

            <div class="formGrid">
              <div class="field">
                <label>Question type</label>
                <select name="questionType">
                  <option value="objective">Objective (MCQ)</option>
                  <option value="subjective">Subjective (Short answer)</option>
                </select>
                <div class="help">Objective needs options and one correct answer. Subjective will be graded in Grade page.</div>
              </div>

              <div class="field">
                <label>Marks</label>
                <input type="number" name="marks" step="0.5" min="0.5" value="1" placeholder="Example: 1">
                <div class="help">Marks for this question.</div>
              </div>

              <div class="field">
                <label>Question order</label>
                <input type="number" name="questionOrder" min="1" value="1" placeholder="Example: 1">
                <div class="help">Displayed order for students.</div>
              </div>

              <div class="field" style="grid-column: 1 / -1;">
                <label>Question text</label>
                <textarea name="questionText" placeholder="Example: What is the main purpose of TCP?"></textarea>
                <div class="help">Write clear instruction. Include unit or format if needed.</div>
              </div>

              <div class="field" style="grid-column: 1 / -1;">
                <label>Attach image (optional)</label>
                <input type="file" name="questionImage" accept=".png,.jpg,.jpeg,.webp">
                <div class="help">Attach diagram or figure for the question. Supported: png, jpg, jpeg, webp.</div>
              </div>

              <div class="field">
                <button class="btn" type="submit">Add Question</button>
              </div>
            </div>
          </form>
        </div>

        <div class="qList">
          <?php if (empty($questions)): ?>
            <div class="panel" style="text-align:center;opacity:0.75;">No questions yet. Add the first question above.</div>
          <?php else: ?>
            <?php foreach ($questions as $idx => $qq): ?>
              <?php
              $qid = (int)$qq['questionID'];
              $type = $qq['questionType'];
              $img = $qq['imagePath'] ?? '';
              $opts = $optsByQ[$qid] ?? [];
              ?>
              <div class="qCard">
                <div class="qTop">
                  <div>
                    <div class="qText">
                      Q<?php echo ($idx + 1); ?>. <?php echo htmlspecialchars($qq['questionText']); ?>
                    </div>
                    <div class="meta">
                      <span class="badge"><?php echo htmlspecialchars($type); ?></span>
                      <span class="badge">Marks <?php echo htmlspecialchars($qq['marks']); ?></span>
                      <span class="badge">Order <?php echo (int)$qq['questionOrder']; ?></span>
                    </div>
                  </div>

                  <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Delete this question and its options?');">
                    <input type="hidden" name="action" value="delete_question">
                    <input type="hidden" name="questionID" value="<?php echo $qid; ?>">
                    <button class="btn btnDanger" type="submit">Delete Question</button>
                  </form>
                </div>

                <div class="imgRow">
                  <?php if ($img): ?>
                    <img class="thumb" src="<?php echo htmlspecialchars($img); ?>" alt="Question image">
                  <?php else: ?>
                    <div class="thumb" style="display:flex;align-items:center;justify-content:center;font-size:12px;opacity:0.7;">
                      No image
                    </div>
                  <?php endif; ?>

                  <div class="imgBox">
                    <div style="font-weight:900;">Question image</div>
                    <div class="miniHelp">Upload or replace image for this question.</div>
                    <form method="POST" action="" enctype="multipart/form-data" style="margin-top:10px;">
                      <input type="hidden" name="action" value="update_image">
                      <input type="hidden" name="questionID" value="<?php echo $qid; ?>">
                      <input type="file" name="newQuestionImage" accept=".png,.jpg,.jpeg,.webp" required>
                      <button class="btn" type="submit" style="margin-top:8px;">Upload Image</button>
                    </form>
                  </div>
                </div>

                <?php if ($type === 'objective'): ?>
                  <div class="optBox">
                    <div style="font-weight:900;margin-bottom:8px;">Options</div>

                    <form method="POST" action="">
                      <input type="hidden" name="action" value="add_option">
                      <input type="hidden" name="questionID" value="<?php echo $qid; ?>">

                      <div class="optGrid">
                        <div class="field" style="margin:0;">
                          <label>Option text</label>
                          <input type="text" name="optionText" maxlength="255" placeholder="Example: Connection-oriented protocol" required>
                        </div>
                        <div class="field" style="margin:0;">
                          <label>Order</label>
                          <input type="number" name="optionOrder" min="1" value="1" required>
                        </div>
                        <div class="field" style="margin:0;">
                          <label>&nbsp;</label>
                          <button class="btn" type="submit">Add Option</button>
                        </div>
                      </div>
                      <div class="help">After adding options, set one option as correct.</div>
                    </form>

                    <?php if (empty($opts)): ?>
                      <div style="margin-top:10px;opacity:0.75;">No options yet.</div>
                    <?php else: ?>
                      <?php foreach ($opts as $op): ?>
                        <div class="optRow">
                          <div>
                            <div class="optText"><?php echo htmlspecialchars($op['optionText']); ?></div>
                            <div class="meta">Order <?php echo (int)$op['optionOrder']; ?></div>
                          </div>

                          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <?php if ((int)$op['isCorrect'] === 1): ?>
                              <span class="pillOk">Correct</span>
                            <?php else: ?>
                              <span class="pillNo">Not correct</span>
                            <?php endif; ?>

                            <form method="POST" action="" style="margin:0;">
                              <input type="hidden" name="action" value="set_correct">
                              <input type="hidden" name="questionID" value="<?php echo $qid; ?>">
                              <input type="hidden" name="optionID" value="<?php echo (int)$op['optionID']; ?>">
                              <button class="btn" type="submit">Set Correct</button>
                            </form>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="optBox">
                    <div style="font-weight:900;">Subjective question</div>
                    <div class="help">Student answers will be graded in Grade page.</div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</body>

</html>