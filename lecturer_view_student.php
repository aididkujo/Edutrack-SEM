<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

// Security Check
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: login.php');
    exit;
}

// Validate Input
if (!isset($_GET['student_id']) || !isset($_GET['course_id'])) {
    die("Invalid Request. Student ID and Course ID are required.");
}

$studentID = intval($_GET['student_id']);
$courseID = intval($_GET['course_id']);

// 1. Fetch Student & Course Info
$infoSql = "SELECT u.full_name, u.email, u.userID, c.courseName 
            FROM user u 
            JOIN enrollment e ON u.userID = e.userID 
            JOIN course c ON e.courseID = c.courseID
            WHERE u.userID = ? AND c.courseID = ?";
$stmt = $conn->prepare($infoSql);
$stmt->bind_param("ii", $studentID, $courseID);
$stmt->execute();
$studentInfo = $stmt->get_result()->fetch_assoc();

if (!$studentInfo) {
    die("Student not found in this course.");
}

// 2. Fetch Assessments & Grades
$gradeSql = "SELECT a.tittle, a.type, a.dueDate, s.grade, s.submitDate 
             FROM assessment a 
             LEFT JOIN submission s ON a.assessmentID = s.assessmentID AND s.userID = ?
             WHERE a.courseID = ?
             ORDER BY a.createdDate DESC";
$gStmt = $conn->prepare($gradeSql);
$gStmt->bind_param("ii", $studentID, $courseID);
$gStmt->execute();
$grades = $gStmt->get_result();

// Calculate Grade Stats
$totalGrade = 0;
$gradeCount = 0;
$gradeRows = [];
while ($row = $grades->fetch_assoc()) {
    $gradeRows[] = $row;
    if ($row['grade'] !== null) {
        $totalGrade += $row['grade'];
        $gradeCount++;
    }
}
$averageScore = ($gradeCount > 0) ? round($totalGrade / $gradeCount, 1) : 0;

// 3. Fetch Attendance History
$attSql = "SELECT sessionDate, status, remarks FROM attendance 
           WHERE userID = ? AND courseID = ? 
           ORDER BY sessionDate DESC";
$aStmt = $conn->prepare($attSql);
$aStmt->bind_param("ii", $studentID, $courseID);
$aStmt->execute();
$attendanceResult = $aStmt->get_result();

// Calculate Attendance Stats
$totalSessions = 0;
$presentSessions = 0;
$attRows = [];
while ($row = $attendanceResult->fetch_assoc()) {
    $attRows[] = $row;
    $totalSessions++;
    if ($row['status'] == 'Present' || $row['status'] == 'Late') {
        $presentSessions++;
    }
}
$attendanceRate = ($totalSessions > 0) ? round(($presentSessions / $totalSessions) * 100, 1) : 0;

// Determine Status Badge
$statusLabel = 'Good';
$statusColor = '#4caf50'; // Green
if ($averageScore < 50 || $attendanceRate < 80) {
    $statusLabel = 'At Risk';
    $statusColor = '#f44336'; // Red
} elseif ($averageScore >= 80) {
    $statusLabel = 'Excellent';
    $statusColor = '#009688'; // Teal
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student Details - EduTrack</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    /* === LAYOUT FIXES === */
    .content-wrapper {
        display: flex;
        align-items: flex-start;
        min-height: 100vh;
    }

    .sidebar {
        width: 250px;
        flex-shrink: 0;
        position: -webkit-sticky;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
    }

    .main-content {
        flex-grow: 1;
        background-color: #e0f7fa;
        padding: 30px;
        min-height: 100vh;
        width: 100%;
    }

    /* Profile Header Card */
    .profile-header {
        background: white;
        padding: 25px;
        border-radius: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    .profile-info h2 {
        margin: 0;
        color: #333;
        font-size: 24px;
    }

    .profile-info p {
        margin: 5px 0 0;
        color: #666;
    }

    .overall-badge {
        background: <?php echo $statusColor;
        ?>;
        color: white;
        padding: 10px 25px;
        border-radius: 25px;
        font-weight: bold;
        font-size: 16px;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .stat-card h3 {
        margin: 0 0 10px;
        font-size: 14px;
        color: #777;
        text-transform: uppercase;
    }

    .stat-card .num {
        font-size: 32px;
        font-weight: bold;
        color: #333;
    }

    /* Tables Section */
    .details-section {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
    }

    .detail-box {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .section-title {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 15px;
        color: #333;
        border-bottom: 2px solid #e0f7fa;
        padding-bottom: 10px;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        text-align: left;
        padding: 12px;
        background: #f9f9f9;
        color: #555;
        font-size: 12px;
        text-transform: uppercase;
    }

    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        color: #333;
        font-size: 14px;
    }

    /* Status Badges for Attendance */
    .badge-present {
        color: #2e7d32;
        background: #e8f5e9;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 11px;
    }

    .badge-absent {
        color: #c62828;
        background: #ffebee;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 11px;
    }

    .badge-late {
        color: #f9a825;
        background: #fffde7;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 11px;
    }

    .back-btn {
        display: inline-block;
        margin-bottom: 20px;
        text-decoration: none;
        color: #00bcd4;
        font-weight: bold;
        background: white;
        padding: 10px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .back-btn:hover {
        background: #00bcd4;
        color: white;
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
        <img src="assets/profile.png" class="profile-icon" alt="Profile">
    </div>

    <div class="content-wrapper">
        <div class="sidebar lecturer-theme">
            <ul>
                <li><a href="lecturer_dashboard.php">Assessment</a></li>
                <li><a href="lecturer_progress.php" style="background: rgba(255,255,255,0.6);">Progress</a></li>
                <li><a href="lecturer_myfeedback.php">My Feedback</a></li>
                <li><a href="studevaluationlist.php">Evaluation</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
        </div>

        <div class="main-content">
            <a href="lecturer_progress.php?course_id=<?php echo $courseID; ?>" class="back-btn">← Back to Dashboard</a>

            <div class="profile-header">
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($studentInfo['full_name']); ?></h2>
                    <p><strong>ID:</strong> CB<?php echo str_pad($studentID, 5, '0', STR_PAD_LEFT); ?> &nbsp;|&nbsp;
                        <strong>Course:</strong> <?php echo htmlspecialchars($studentInfo['courseName']); ?>
                    </p>
                    <p style="font-size: 13px; color: #888;"><?php echo htmlspecialchars($studentInfo['email']); ?></p>
                </div>
                <div class="overall-badge">
                    <?php echo $statusLabel; ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card" style="border-bottom: 4px solid #ab47bc;">
                    <h3>Current Average</h3>
                    <div class="num"><?php echo $averageScore; ?>%</div>
                </div>
                <div class="stat-card" style="border-bottom: 4px solid #26a69a;">
                    <h3>Attendance Rate</h3>
                    <div class="num"><?php echo $attendanceRate; ?>%</div>
                </div>
            </div>

            <div class="details-section">

                <div class="detail-box">
                    <div class="section-title">Assessment History</div>
                    <?php if (count($gradeRows) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Assessment</th>
                                <th>Type</th>
                                <th>Submitted On</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gradeRows as $g): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($g['tittle']); ?></td>
                                <td><?php echo ucfirst($g['type']); ?></td>
                                <td>
                                    <?php 
                                    echo $g['submitDate'] ? date("d M Y", strtotime($g['submitDate'])) : '<span style="color:#999; font-style:italic;">Pending</span>'; 
                                ?>
                                </td>
                                <td>
                                    <?php if ($g['grade'] !== null): ?>
                                    <span style="font-weight: bold; color: #333;"><?php echo $g['grade']; ?>%</span>
                                    <?php else: ?>
                                    <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="color: #777; text-align: center; padding: 20px;">No assessments recorded yet.</p>
                    <?php endif; ?>
                </div>

                <div class="detail-box">
                    <div class="section-title">Attendance Log</div>
                    <?php if (count($attRows) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attRows as $a): ?>
                            <tr>
                                <td><?php echo date("d M", strtotime($a['sessionDate'])); ?></td>
                                <td>
                                    <?php 
                                    $statusClass = 'badge-' . strtolower($a['status']);
                                    echo "<span class='$statusClass'>" . ucfirst($a['status']) . "</span>";
                                ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="color: #777; text-align: center; padding: 20px;">No attendance records found.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

</body>

</html>