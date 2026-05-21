<?php
// lect_tq.php
// After lecturer submits evaluation for a student → Save to DB → Thank You page

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

// ---------- SAVE EVALUATION (POST) ----------
$saveOk = false;
$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // evaluator = logged-in lecturer
    $evaluatorID = (int)($_SESSION['userID'] ?? 0);

    // from hidden inputs in studentevaluation.php form
    $courseID = (int)($_POST['courseID'] ?? 0);
    $evaluateeID = (int)($_POST['evaluateeID'] ?? 0);
    $evaluationType = $_POST['evaluationType'] ?? 'lecturer_to_student'; // should be lecturer_to_student

    // ratings + comments
    $q1 = (int)($_POST['q1_rating'] ?? 0);
    $q2 = (int)($_POST['q2_rating'] ?? 0);
    $q3 = (int)($_POST['q3_rating'] ?? 0);
    $q4 = (int)($_POST['q4_rating'] ?? 0);
    $q5 = (int)($_POST['q5_rating'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');

    // validation
    $validType = in_array($evaluationType, ['student_to_lecturer', 'lecturer_to_student'], true);
    $validRatings = ($q1>=1 && $q1<=5) && ($q2>=1 && $q2<=5) && ($q3>=1 && $q3<=5) && ($q4>=1 && $q4<=5) && ($q5>=1 && $q5<=5);

    if ($evaluatorID <= 0 || $courseID <= 0 || $evaluateeID <= 0 || !$validType || !$validRatings) {
        $errorMsg = "Invalid submission. Please go back and submit again.";
    } else {
        if ($evaluatorID === $evaluateeID) {
            $errorMsg = "Invalid submission (cannot evaluate yourself).";
        } else {

            $sql = "INSERT INTO evaluation
                    (evaluatorID, evaluateeID, courseID, evaluationType,
                     q1_rating, q2_rating, q3_rating, q4_rating, q5_rating, comments)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                $errorMsg = "Database error (prepare failed): " . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    "iiisiiiiis",
                    $evaluatorID,
                    $evaluateeID,
                    $courseID,
                    $evaluationType,
                    $q1, $q2, $q3, $q4, $q5,
                    $comments
                );

                if (mysqli_stmt_execute($stmt)) {
                    $saveOk = true;
                } else {
                    $errno = mysqli_errno($conn);

                    if ($errno === 1062) {
                        // unique constraint uq_eval_once
                        $errorMsg = "You have already submitted an evaluation for this student in this course.";
                    } else if ($errno === 1452) {
                        // FK fails (courseID/userID not exists)
                        $errorMsg = "Invalid course/user reference. Please go back and try again.";
                    } else {
                        $errorMsg = "Database error (execute failed): " . mysqli_error($conn);
                    }
                }

                mysqli_stmt_close($stmt);
            }
        }
    }

} else {
    $errorMsg = "No evaluation data received. Please submit the form first.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EduTrack - Thank You</title>
    <link rel="stylesheet" href="style.css">

    <style>
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; }

        .header.lecturer-theme { background: #80f7ff; }
        .content-wrapper { display: flex; min-height: calc(100vh - 80px); }
        .sidebar.lecturer-theme { background: #7ee8f7; }
        .sidebar.lecturer-theme ul li a.active { background: #a3d6dd; }
        .main-content { flex: 1; padding: 40px; background: #ffffff; position: relative; }

        .back-arrow { font-size: 22px; cursor: pointer; display: inline-block; margin-bottom: 20px; color: #333; }
        .thankyou-container { text-align: center; margin-top: 40px; }
        .thankyou-container img { width: 260px; opacity: 0.95; }
        .thankyou-title { font-size: 26px; margin-top: 20px; font-weight: 700; color: #000; }
        .thankyou-subtitle { margin-top: 6px; font-style: italic; color: #444; font-size: 16px; }

        .error-box {
            max-width: 700px;
            margin: 20px auto 0;
            background: #ffecec;
            border: 1px solid #ffb3b3;
            color: #b30000;
            padding: 14px 18px;
            border-radius: 10px;
            text-align: center;
        }
        .success-box {
            max-width: 700px;
            margin: 20px auto 0;
            background: #eaffea;
            border: 1px solid #a6e6a6;
            color: #1b7a1b;
            padding: 14px 18px;
            border-radius: 10px;
            text-align: center;
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
                <li><a href="lecturer_dashboard.php">Assessment</a></li>
                <li><a href="lecturer_progress.php">Progress</a></li>
                <li><a href="lecturer_myfeedback.php">My Feedback</a></li>
                <li><a href="studevaluationlist.php">Evaluation</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
        </div>

        <div class="main-content">
            <div class="back-arrow" onclick="window.location.href='studevaluationlist.php'">&larr;</div>

            <?php if ($saveOk): ?>
                <div class="success-box">Evaluation saved successfully.</div>
                <div class="thankyou-container">
                    <img src="assets/logoedutrack.png" alt="EduTrack Logo">
                    <div class="thankyou-title">Thank You for the Evaluation!</div>
                    <div class="thankyou-subtitle">Your Evaluation always Matter</div>
                </div>
            <?php else: ?>
                <div class="error-box"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
