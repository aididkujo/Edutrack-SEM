<?php
// student_myfeedback.php (STUDENT views LECTURER->STUDENT feedback)
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_role('student');

$user = get_user_by_id($conn, $_SESSION['userID']);
$studentID = (int)$_SESSION['userID'];

// 1) Get student's enrolled courses + lecturer info
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

$errorMsg = "";
if (count($courses) === 0) {
    $errorMsg = "No enrolled courses found.";
}

// 2) Choose selected course
$selectedCourseID = isset($_GET['courseID']) ? (int)$_GET['courseID'] : (count($courses) ? (int)$courses[0]['courseID'] : 0);
$selectedCourse = null;
foreach ($courses as $c) {
    if ((int)$c['courseID'] === $selectedCourseID) { $selectedCourse = $c; break; }
}
if (!$selectedCourse && count($courses)) {
    $selectedCourse = $courses[0];
    $selectedCourseID = (int)$selectedCourse['courseID'];
}

// 3) Fetch evaluation: lecturer -> student for this course
$evaluation = null;
if ($selectedCourse) {
    $lecturerID = (int)$selectedCourse['lecturerID'];

    $sqlEval = "
        SELECT e.*, u.full_name AS evaluatorName
        FROM evaluation e
        JOIN user u ON e.evaluatorID = u.userID
        WHERE e.courseID = ?
          AND e.evaluationType = 'lecturer_to_student'
          AND e.evaluatorID = ?
          AND e.evaluateeID = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlEval);
    $stmt->bind_param("iii", $selectedCourseID, $lecturerID, $studentID);
    $stmt->execute();
    $res = $stmt->get_result();
    $evaluation = $res->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EduTrack - My Feedback</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .evaluation-wrapper {
            background: #ffffff;
            border-radius: 8px;
            max-width: 900px;
            margin: 30px auto;
            padding: 24px 32px 32px;
            box-sizing: border-box;
        }
        .evaluation-title { font-size: 24px; font-weight: 700; margin: 0; }
        .evaluation-subtitle { margin-top: 4px; margin-bottom: 18px; font-style: italic; color: #333; font-size: 14px; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .alert-info { background: #eef6ff; border: 1px solid #b9dbff; }
        .alert-error { background: #ffecec; border: 1px solid #ffb4b4; }

        .select-row { display: flex; gap: 12px; align-items: center; margin: 12px 0 18px; }
        .select-row label { font-size: 14px; font-weight: 600; }
        .select-row select { padding: 8px 10px; border-radius: 8px; border: 1px solid #ddd; min-width: 340px; }

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
        .evaluation-question-text { font-size: 14px; color: #333; max-width: 80%; }
        .evaluation-stars { white-space: nowrap; pointer-events: none; } /* READONLY */
        .evaluation-stars span { font-size: 20px; color: #d3d3d3; margin-left: 2px; }
        .evaluation-stars span.active { color: #f7b500; }

        .evaluation-comments-label {
            background: #f2f2f2;
            border-radius: 24px 24px 0 0;
            padding: 16px 24px 8px;
            font-size: 14px;
            margin-top: 10px;
        }
        .evaluation-comments-box {
            background: #f2f2f2;
            border-radius: 0 0 24px 24px;
            padding: 0 24px 16px;
        }
        .evaluation-comments-box textarea {
            width: 100%;
            border-radius: 12px;
            border: none;
            padding: 10px;
            font-size: 14px;
            resize: vertical;
            box-sizing: border-box;
            outline: none;
            background: #fff;
        }
        .meta-row { font-size: 13px; color: #333; margin: 6px 0 14px; }
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
            <img src="assets/profile.png" class="profile-icon" alt="Profile">
            <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="sidebar student-theme">
            <ul>
                <li><a href="student_dashboard.php">My Courses</a></li>
                <li><a href="student_progress.php">My Progress</a></li>
                <li><a href="student_myfeedback.php" class="active">My Feedback</a></li>
                <li><a href="lectevaluationlist.php">Evaluation</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
        </div>

        <div class="main-content">
            <div class="evaluation-wrapper">
                <h2 class="evaluation-title">My Feedback</h2>
                <p class="evaluation-subtitle">Lecturer feedback about you (read-only)</p>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
                <?php endif; ?>

                <?php if ($selectedCourse): ?>
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

                    <?php if (!$evaluation): ?>
                        <div class="alert alert-info">No feedback yet for this course.</div>
                    <?php else: ?>
                        <div class="meta-row">
                            <strong>Evaluator:</strong> <?php echo htmlspecialchars($evaluation['evaluatorName']); ?>
                            &nbsp; | &nbsp;
                            <strong>Date:</strong> <?php echo htmlspecialchars($evaluation['createdAt']); ?>
                        </div>

                        <?php
                          // helper: render stars
                          function render_stars($value) {
                              $value = (int)$value;
                              for ($i=1; $i<=5; $i++) {
                                  $active = ($i <= $value) ? 'active' : '';
                                  echo '<span class="'.$active.'">&#9733;</span>';
                              }
                          }
                        ?>

                        <div class="evaluation-question">
                            <div class="evaluation-question-text">The student applies critical thinking skills</div>
                            <div class="evaluation-stars"><?php render_stars($evaluation['q1_rating']); ?></div>
                        </div>
                        <div class="evaluation-question">
                            <div class="evaluation-question-text">The student completes assignments and tasks on time</div>
                            <div class="evaluation-stars"><?php render_stars($evaluation['q2_rating']); ?></div>
                        </div>
                        <div class="evaluation-question">
                            <div class="evaluation-question-text">The student demonstrates a positive attitude and engagement in class</div>
                            <div class="evaluation-stars"><?php render_stars($evaluation['q3_rating']); ?></div>
                        </div>
                        <div class="evaluation-question">
                            <div class="evaluation-question-text">The student actively participates in class discussions and group activities</div>
                            <div class="evaluation-stars"><?php render_stars($evaluation['q4_rating']); ?></div>
                        </div>
                        <div class="evaluation-question">
                            <div class="evaluation-question-text">The student shows consistency in attendance and punctuality</div>
                            <div class="evaluation-stars"><?php render_stars($evaluation['q5_rating']); ?></div>
                        </div>

                        <div class="evaluation-comments-label">Additional Comments</div>
                        <div class="evaluation-comments-box">
                            <textarea rows="4" readonly><?php echo htmlspecialchars($evaluation['comments'] ?? ''); ?></textarea>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
