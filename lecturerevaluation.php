<?php
// module4 - lecturerevaluation (STUDENT -> LECTURER)
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

$successMsg = "";
$errorMsg   = "";

// --------------------
// Fetch student's courses (enrolled) + lecturer info
// --------------------
$courses = [];
$sqlCourses = "
    SELECT c.courseID, c.courseName, c.userID AS lecturerID, u.full_name AS lecturerName
    FROM enrollment e
    JOIN course c ON e.courseID = c.courseID
    JOIN user u ON c.userID = u.userID
    WHERE e.userID = ?
    ORDER BY c.courseName
";
$stmt = $conn->prepare($sqlCourses);
$stmt->bind_param("i", $studentID);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $courses[] = $row;
$stmt->close();

if (count($courses) === 0) {
    $errorMsg = "No enrolled courses found. Please contact admin.";
}

// Selected course (GET)
$selectedCourseID = isset($_GET['courseID']) ? (int)$_GET['courseID'] : (count($courses) ? (int)$courses[0]['courseID'] : 0);

// Find course record
$selectedCourse = null;
foreach ($courses as $c) {
    if ((int)$c['courseID'] === $selectedCourseID) { $selectedCourse = $c; break; }
}
if (!$selectedCourse && count($courses)) {
    $selectedCourse = $courses[0];
    $selectedCourseID = (int)$selectedCourse['courseID'];
}

$lecturerID = $selectedCourse ? (int)$selectedCourse['lecturerID'] : 0;

// --------------------
// Load existing evaluation (for edit-prefill)
// --------------------
$existing = null;
$existingID = 0;
if ($selectedCourse) {
    $sqlGet = "
        SELECT evaluationID, q1_rating, q2_rating, q3_rating, q4_rating, q5_rating, comments
        FROM evaluation
        WHERE evaluatorID = ?
          AND evaluateeID = ?
          AND courseID = ?
          AND evaluationType = 'student_to_lecturer'
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlGet);
    $stmt->bind_param("iii", $studentID, $lecturerID, $selectedCourseID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $existing = $row;
        $existingID = (int)$row['evaluationID'];
    }
    $stmt->close();
}

// Defaults (prefill values)
$q1Val = $existing ? (int)$existing['q1_rating'] : 0;
$q2Val = $existing ? (int)$existing['q2_rating'] : 0;
$q3Val = $existing ? (int)$existing['q3_rating'] : 0;
$q4Val = $existing ? (int)$existing['q4_rating'] : 0;
$q5Val = $existing ? (int)$existing['q5_rating'] : 0;
$commentVal = $existing ? (string)$existing['comments'] : "";

// --------------------
// Handle POST (insert/update)
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {

    $courseID   = (int)($_POST['courseID'] ?? 0);
    $evaluateeID = (int)($_POST['evaluateeID'] ?? 0);
    $evaluationType = $_POST['evaluationType'] ?? '';

    $q1 = (int)($_POST['q1_rating'] ?? 0);
    $q2 = (int)($_POST['q2_rating'] ?? 0);
    $q3 = (int)($_POST['q3_rating'] ?? 0);
    $q4 = (int)($_POST['q4_rating'] ?? 0);
    $q5 = (int)($_POST['q5_rating'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');

    // Validate ratings
    foreach ([$q1,$q2,$q3,$q4,$q5] as $r) {
        if ($r < 1 || $r > 5) { $errorMsg = "Please rate all questions (1 to 5 stars)."; break; }
    }

    if (!$errorMsg && $evaluationType !== 'student_to_lecturer') {
        $errorMsg = "Invalid evaluation type.";
    }

    // Validate course belongs to student enrollment + lecturer matches
    if (!$errorMsg) {
        $valid = false;
        foreach ($courses as $c) {
            if ((int)$c['courseID'] === $courseID && (int)$c['lecturerID'] === $evaluateeID) {
                $valid = true; break;
            }
        }
        if (!$valid) $errorMsg = "Invalid course/lecturer selection.";
    }

    if (!$errorMsg) {
        // Check if exists
        $sqlGet = "
            SELECT evaluationID
            FROM evaluation
            WHERE evaluatorID = ?
              AND evaluateeID = ?
              AND courseID = ?
              AND evaluationType = 'student_to_lecturer'
            LIMIT 1
        ";
        $stmt = $conn->prepare($sqlGet);
        $stmt->bind_param("iii", $studentID, $evaluateeID, $courseID);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            // UPDATE
            $evaluationID = (int)$row['evaluationID'];
            $sqlUp = "
                UPDATE evaluation
                SET q1_rating=?, q2_rating=?, q3_rating=?, q4_rating=?, q5_rating=?, comments=?
                WHERE evaluationID=? AND evaluatorID=? AND evaluateeID=? AND courseID=? AND evaluationType='student_to_lecturer'
                LIMIT 1
            ";
            $stmt = $conn->prepare($sqlUp);
            $stmt->bind_param("iiiiisiiii",
                $q1,$q2,$q3,$q4,$q5,$comments,
                $evaluationID, $studentID, $evaluateeID, $courseID
            );
            if (!$stmt->execute()) {
                $errorMsg = "Database error: " . htmlspecialchars($conn->error);
            }
            $stmt->close();
        } else {
            // INSERT
            $sqlIns = "
                INSERT INTO evaluation
                    (evaluatorID, evaluateeID, courseID, evaluationType,
                     q1_rating, q2_rating, q3_rating, q4_rating, q5_rating, comments)
                VALUES (?, ?, ?, 'student_to_lecturer', ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($sqlIns);
            $stmt->bind_param("iiiiiiiis",
                $studentID, $evaluateeID, $courseID,
                $q1,$q2,$q3,$q4,$q5,
                $comments
            );
            if (!$stmt->execute()) {
                $errorMsg = ($conn->errno == 1062)
                    ? "You have already submitted an evaluation for this course."
                    : "Database error: " . htmlspecialchars($conn->error);
            }
            $stmt->close();
        }
    }

    // Redirect ONLY if success
    if (!$errorMsg) {
        header("Location: stud_tq.php");
        exit;
    }

    // Keep selected after POST
    $selectedCourseID = $courseID;
    $q1Val=$q1; $q2Val=$q2; $q3Val=$q3; $q4Val=$q4; $q5Val=$q5; $commentVal=$comments;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>EduTrack - Lecturer Evaluation</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .evaluation-wrapper{background:#fff;border-radius:8px;max-width:900px;margin:30px auto;padding:24px 32px 32px;box-sizing:border-box;}
        .evaluation-title{font-size:24px;font-weight:700;margin:0;}
        .evaluation-subtitle{margin-top:4px;margin-bottom:24px;font-style:italic;color:#333;font-size:14px;}
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;}
        .alert-error{background:#ffecec;border:1px solid #ffb4b4;}
        .select-row{display:flex;gap:12px;align-items:center;margin:12px 0 22px;}
        .select-row label{font-size:14px;font-weight:600;}
        .select-row select{padding:8px 10px;border-radius:8px;border:1px solid #ddd;min-width:340px;}
        .evaluation-question{background:#f2f2f2;border-radius:24px;padding:16px 24px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;box-sizing:border-box;}
        .evaluation-question-text{font-size:14px;color:#333;max-width:80%;}
        .evaluation-stars{white-space:nowrap;}
        .evaluation-stars span{font-size:20px;cursor:pointer;color:#d3d3d3;margin-left:2px;}
        .evaluation-stars span.active{color:#f7b500;}
        .evaluation-comments-label{background:#f2f2f2;border-radius:24px 24px 0 0;padding:16px 24px 8px;font-size:14px;margin-top:10px;}
        .evaluation-comments-box{background:#f2f2f2;border-radius:0 0 24px 24px;padding:0 24px 16px;}
        .evaluation-comments-box textarea{width:100%;border-radius:12px;border:none;padding:10px;font-size:14px;resize:vertical;box-sizing:border-box;outline:none;}
        .evaluation-submit-row{margin-top:24px;text-align:right;}
        .evaluation-submit-btn{background:#e5cff9;border:none;border-radius:16px;padding:10px 32px;font-size:14px;cursor:pointer;color:#333;}
        .evaluation-submit-btn:hover{filter:brightness(0.95);}
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
                <h2 class="evaluation-title">Evaluation Form</h2>
                <p class="evaluation-subtitle">Please evaluate your lecturer with honesty</p>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
                <?php endif; ?>

                <?php if (!$selectedCourse): ?>
                    <div class="alert alert-error">No valid course selected.</div>
                <?php else: ?>

                    <!-- Course selector -->
                    <form method="get" class="select-row">
                        <label for="courseID">Course:</label>
                        <select name="courseID" id="courseID" onchange="this.form.submit()">
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo (int)$c['courseID']; ?>"
                                    <?php echo ((int)$c['courseID'] === (int)$selectedCourseID) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['courseName']); ?>
                                    (<?php echo htmlspecialchars($c['lecturerName']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- MAIN FORM (ONLY ONE FORM!) -->
                    <form method="post">
                        <input type="hidden" name="courseID" value="<?php echo (int)$selectedCourseID; ?>">
                        <input type="hidden" name="evaluateeID" value="<?php echo (int)$lecturerID; ?>">
                        <input type="hidden" name="evaluationType" value="student_to_lecturer">

                        <!-- prefill hidden inputs -->
                        <input type="hidden" name="q1_rating" id="q1_rating" value="<?php echo (int)$q1Val; ?>" required>
                        <input type="hidden" name="q2_rating" id="q2_rating" value="<?php echo (int)$q2Val; ?>" required>
                        <input type="hidden" name="q3_rating" id="q3_rating" value="<?php echo (int)$q3Val; ?>" required>
                        <input type="hidden" name="q4_rating" id="q4_rating" value="<?php echo (int)$q4Val; ?>" required>
                        <input type="hidden" name="q5_rating" id="q5_rating" value="<?php echo (int)$q5Val; ?>" required>

                        <div class="evaluation-question">
                            <div class="evaluation-question-text">The lecturer explains concepts clearly and effectively</div>
                            <div class="evaluation-stars" data-input="q1_rating">
                                <span data-value="1">&#9733;</span><span data-value="2">&#9733;</span><span data-value="3">&#9733;</span><span data-value="4">&#9733;</span><span data-value="5">&#9733;</span>
                            </div>
                        </div>

                        <div class="evaluation-question">
                            <div class="evaluation-question-text">The lecturer uses relevant examples and teaching materials</div>
                            <div class="evaluation-stars" data-input="q2_rating">
                                <span data-value="1">&#9733;</span><span data-value="2">&#9733;</span><span data-value="3">&#9733;</span><span data-value="4">&#9733;</span><span data-value="5">&#9733;</span>
                            </div>
                        </div>

                        <div class="evaluation-question">
                            <div class="evaluation-question-text">The lecturer is approachable and available for consultation</div>
                            <div class="evaluation-stars" data-input="q3_rating">
                                <span data-value="1">&#9733;</span><span data-value="2">&#9733;</span><span data-value="3">&#9733;</span><span data-value="4">&#9733;</span><span data-value="5">&#9733;</span>
                            </div>
                        </div>

                        <div class="evaluation-question">
                            <div class="evaluation-question-text">The lecturer treats students fairly and respectfully</div>
                            <div class="evaluation-stars" data-input="q4_rating">
                                <span data-value="1">&#9733;</span><span data-value="2">&#9733;</span><span data-value="3">&#9733;</span><span data-value="4">&#9733;</span><span data-value="5">&#9733;</span>
                            </div>
                        </div>

                        <div class="evaluation-question">
                            <div class="evaluation-question-text">Overall, are you satisfied with this lecturer's teaching this semester?</div>
                            <div class="evaluation-stars" data-input="q5_rating">
                                <span data-value="1">&#9733;</span><span data-value="2">&#9733;</span><span data-value="3">&#9733;</span><span data-value="4">&#9733;</span><span data-value="5">&#9733;</span>
                            </div>
                        </div>

                        <div class="evaluation-comments-label">Additional Comments</div>
                        <div class="evaluation-comments-box">
                            <textarea name="comments" rows="4"
                                placeholder="You may share any additional comments or suggestions here..."><?php
                                    echo htmlspecialchars($commentVal);
                                ?></textarea>
                        </div>

                        <div class="evaluation-submit-row">
                            <button type="submit" name="submit_evaluation" class="evaluation-submit-btn">
                                <?php echo $existing ? "Update" : "Submit"; ?>
                            </button>
                        </div>
                    </form>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function paintStars(group, value) {
            const stars = group.querySelectorAll('span');
            stars.forEach(s => {
                const v = parseInt(s.getAttribute('data-value'));
                if (v <= value) s.classList.add('active');
                else s.classList.remove('active');
            });
        }

        document.querySelectorAll('.evaluation-stars').forEach(group => {
            const inputId = group.getAttribute('data-input');
            const hiddenInput = document.getElementById(inputId);

            // PREFILL paint from existing value
            const initial = parseInt(hiddenInput.value || "0");
            if (initial >= 1) paintStars(group, initial);

            group.querySelectorAll('span').forEach(star => {
                star.addEventListener('click', () => {
                    const value = parseInt(star.getAttribute('data-value'));
                    hiddenInput.value = value;
                    paintStars(group, value);
                });
            });
        });
    </script>
</body>
</html>
