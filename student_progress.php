<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/module3_functions.php';

// Security Check
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$userID = (int)$_SESSION['userID'];
$filterID = $_GET['course_id'] ?? 'all';
$isPdfView = (isset($_GET['pdf']) && $_GET['pdf'] == '1');

// --- 1. Fetch List of All Courses (For Dropdown) ---
$listSql = "SELECT c.courseID, c.courseName 
            FROM enrollment e 
            JOIN course c ON e.courseID = c.courseID 
            WHERE e.userID = ?";
$listStmt = $conn->prepare($listSql);
$listStmt->bind_param("i", $userID);
$listStmt->execute();
$allCourses = $listStmt->get_result();

// --- 2. Fetch Data for Dashboard (Filtered) ---
$sql = "SELECT c.courseID, c.courseName, c.courseID as courseCode 
        FROM enrollment e 
        JOIN course c ON e.courseID = c.courseID 
        WHERE e.userID = ?";

if ($filterID !== 'all') {
    $sql .= " AND c.courseID = ?";
}

$stmt = $conn->prepare($sql);
if ($filterID !== 'all') {
    $stmt->bind_param("ii", $userID, $filterID);
} else {
    $stmt->bind_param("i", $userID);
}
$stmt->execute();
$courses = $stmt->get_result();

// Stats Variables
$totalAvg = 0;
$totalAtt = 0;
$courseCount = 0;
$totalAssessments = 0;
$coursesData = [];

// --- 3. Process Main Table & Summary Data ---
while($course = $courses->fetch_assoc()) {
    update_progress_summary($conn, $userID, $course['courseID']);

    // Fetch Summary
    $sumSql = "SELECT total_average, attendance_rate FROM progress_summary WHERE userID = ? AND courseID = ?";
    $sumStmt = $conn->prepare($sumSql);
    $sumStmt->bind_param("ii", $userID, $course['courseID']);
    $sumStmt->execute();
    $summary = $sumStmt->get_result()->fetch_assoc();

    $avg = $summary ? (float)$summary['total_average'] : 0;
    $att = $summary ? (float)$summary['attendance_rate'] : 0;

    // Fetch Remark
    $remSql = "SELECT remark FROM student_remarks WHERE studentID = ? AND courseID = ? LIMIT 1";
    $remStmt = $conn->prepare($remSql);
    $remStmt->bind_param("ii", $userID, $course['courseID']);
    $remStmt->execute();
    $remarkResult = $remStmt->get_result()->fetch_assoc();
    $remark = $remarkResult ? $remarkResult['remark'] : '-';

    // Count Assessments
    $countSql = "SELECT COUNT(*) as count FROM submission s 
                 JOIN assessment a ON s.assessmentID = a.assessmentID 
                 WHERE s.userID = ? AND a.courseID = ?";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("ii", $userID, $course['courseID']);
    $countStmt->execute();
    $assessCount = (int)$countStmt->get_result()->fetch_assoc()['count'];

    $status = get_student_status($avg, $att);

    $coursesData[] = [
        'id' => $course['courseCode'], 
        'name' => $course['courseName'],
        'type' => 'Mixed', 
        'avg' => $avg,
        'att' => $att,
        'status' => $status,
        'assess_count' => $assessCount,
        'remark' => $remark // Added this line
    ];

    $totalAvg += $avg;
    $totalAtt += $att;
    $totalAssessments += $assessCount;
    $courseCount++;
}

// Global Stats
$globalAvg = $courseCount > 0 ? round($totalAvg / $courseCount, 1) : 0;
$globalAtt = $courseCount > 0 ? round($totalAtt / $courseCount, 1) : 0;
$globalStatus = get_student_status($globalAvg, $globalAtt);

// --- 4. Analytics: Prepare Chart Data ---

// A. Line Chart: Performance Trend
$trendSql = "SELECT a.tittle, s.grade 
             FROM submission s 
             JOIN assessment a ON s.assessmentID = a.assessmentID 
             WHERE s.userID = ?";
if ($filterID !== 'all') {
    $trendSql .= " AND a.courseID = ?";
}
$trendSql .= " ORDER BY a.createdDate ASC LIMIT 10";

$trendStmt = $conn->prepare($trendSql);
if ($filterID !== 'all') {
    $trendStmt->bind_param("ii", $userID, $filterID);
} else {
    $trendStmt->bind_param("i", $userID);
}
$trendStmt->execute();
$trendResult = $trendStmt->get_result();

$trendLabels = [];
$trendData = [];
$gradeCounts = ['A'=>0, 'B'=>0, 'C'=>0, 'D'=>0, 'F'=>0];

while($row = $trendResult->fetch_assoc()) {
    $trendLabels[] = $row['tittle'];
    $trendData[] = (float)$row['grade'];

    $g = (float)$row['grade'];
    if($g >= 80) $gradeCounts['A']++;
    elseif($g >= 70) $gradeCounts['B']++;
    elseif($g >= 60) $gradeCounts['C']++;
    elseif($g >= 50) $gradeCounts['D']++;
    else $gradeCounts['F']++;
}

// B. Bar Chart: Course Comparison
$barLabels = array_column($coursesData, 'name');
$barData = array_column($coursesData, 'avg');

// =========================
// DOWNLOAD EXCEL (CSV) MODE
// =========================
if (isset($_GET['download']) && $_GET['download'] === 'excel') {
    $filename = "my_progress_" . (($filterID === 'all') ? "all" : ("course_" . (int)$filterID)) . "_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    fputcsv($out, ["Student Progress Report"]);
    fputcsv($out, ["Student ID", $userID]);
    fputcsv($out, ["Course Filter", ($filterID === 'all') ? "All Courses" : (string)$filterID]);
    fputcsv($out, ["Average Score (%)", $globalAvg]);
    fputcsv($out, ["Attendance Rate (%)", $globalAtt]);
    fputcsv($out, ["Assessments Completed", $totalAssessments]);
    fputcsv($out, ["Overall Status", $globalStatus['label']]);
    fputcsv($out, []); 

    fputcsv($out, ["Course ID", "Course Name", "Assessment Type", "Average Score (%)", "Attendance (%)", "Status", "Assessments Completed"]);

    foreach ($coursesData as $c) {
        fputcsv($out, [
            $c['id'],
            $c['name'],
            $c['type'],
            $c['avg'],
            $c['att'],
            $c['status']['label'],
            $c['assess_count']
        ]);
    }

    fclose($out);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Progress - EduTrack</title>

    <?php if (!$isPdfView): ?>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>

    <style>
    /* === LAYOUT FIXES (Sticky Sidebar) === */
    .content-wrapper {
        display: flex;
        align-items: flex-start;
        /* Important for sticky */
        min-height: 100vh;
        position: relative;
    }

    .sidebar {
        width: 250px;
        flex-shrink: 0;
        position: -webkit-sticky;
        /* Safari */
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
    }

    .main-content {
        flex-grow: 1;
        width: 100%;
        padding: 20px;
        overflow-x: hidden;
        min-height: 100vh;
        background-color: #f0fdf4;
        /* Student Theme Green */
    }

    /* === DASHBOARD STYLES === */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .filter-form {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filter-select {
        padding: 8px 15px;
        border-radius: 5px;
        border: 1px solid #ccc;
        background: #e0e0e0;
        font-weight: bold;
        color: #555;
        cursor: pointer;
    }

    .title-badge {
        background: #ff80ab;
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        font-weight: bold;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .cards-container {
        background: #ccff90;
        padding: 20px;
        border-radius: 15px;
        display: flex;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .card {
        flex: 1;
        padding: 30px 15px;
        border-radius: 15px;
        text-align: center;
        color: #333;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 120px;
    }

    .card h3 {
        font-size: 14px;
        margin-bottom: 10px;
        font-weight: normal;
    }

    .card .big-num {
        font-size: 24px;
        font-weight: bold;
    }

    .card-purple {
        background-color: #e1bee7;
    }

    .card-green {
        background-color: #b9f6ca;
    }

    .card-blue {
        background-color: #90caf9;
    }

    .card-pink {
        background-color: #ff80ab;
        color: white;
    }

    .charts-container {
        background: #ccff90;
        padding: 20px;
        border-radius: 15px;
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .chart-box {
        background: white;
        border-radius: 10px;
        padding: 15px;
        height: 250px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .chart-title {
        font-size: 12px;
        color: #555;
        margin-top: 10px;
        font-weight: bold;
    }

    .bottom-section {
        background: #ccff90;
        padding: 20px;
        border-radius: 15px;
        display: flex;
        gap: 20px;
    }

    .table-wrapper {
        flex: 3;
        background: #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
    }

    .table-header {
        padding: 10px 15px;
        font-weight: bold;
        display: flex;
        justify-content: space-between;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }

    .custom-table th {
        background: #e0e0e0;
        padding: 10px;
        font-size: 12px;
        text-align: left;
    }

    .custom-table td {
        padding: 10px;
        border-bottom: 1px solid #eee;
        font-size: 13px;
    }

    .status-btn {
        padding: 5px 15px;
        border-radius: 15px;
        color: white;
        font-size: 11px;
        font-weight: bold;
        display: inline-block;
        text-align: center;
        width: 80px;
    }

    .action-sidebar {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .action-btn {
        padding: 15px;
        border: none;
        border-radius: 10px;
        color: white;
        font-weight: bold;
        cursor: pointer;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .btn-dl-pdf {
        background-color: #69f0ae;
        color: #333;
    }

    .btn-dl-excel {
        background-color: #e0e0e0;
        color: #333;
    }

    .btn-share {
        background-color: #4fc3f7;
        color: white;
    }

    canvas {
        max-height: 200px;
        width: 100%;
    }

    /* PRINT/PDF styles */
    @media print {

        .sidebar,
        .action-sidebar,
        .dashboard-header form,
        .logout-btn,
        .profile-icon {
            display: none !important;
        }

        .header,
        .content-wrapper {
            box-shadow: none !important;
        }

        .main-content,
        .cards-container,
        .charts-container,
        .bottom-section,
        .table-wrapper,
        .table-header {
            background: #fff !important;
        }

        a[href]:after {
            content: "" !important;
        }
    }

    <?php if ($isPdfView): ?>.charts-container {
        display: none !important;
    }

    .title-badge::after {
        content: " (PDF)";
    }

    <?php endif;
    ?>
    </style>
</head>

<body>
    <?php if (!$isPdfView): ?>
    <div class="header student-theme">
        <div class="brand">
            <img src="assets/logoedutrack.png" alt="EduTrack Logo">
            <div class="title">
                <h1>EduTrack</h1>
                <span>Smart Tracking for Smarter Learning</span>
            </div>
        </div>
        <img src="assets/profile.png" class="profile-icon" alt="Profile">
    </div>
    <?php endif; ?>

    <div class="content-wrapper">
        <?php if (!$isPdfView): ?>
        <div class="sidebar student-theme">
            <ul>
                <li><a href="student_dashboard.php">My Courses</a></li>
                <li><a href="student_progress.php" style="background: rgba(255,255,255,0.6);">My Progress</a></li>
                <li><a href="student_myfeedback.php">My Feedback</a></li>
                <li><a href="lectevaluationlist.php">Evaluation</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Log Out</button>
        </div>
        <?php endif; ?>

        <div class="main-content">
            <div class="dashboard-header">
                <form method="GET" class="filter-form">
                    <span style="font-weight: bold; color: #555;">Filter:</span>
                    <select name="course_id" class="filter-select" onchange="this.form.submit()">
                        <option value="all">All Courses</option>
                        <?php while($row = $allCourses->fetch_assoc()): ?>
                        <option value="<?php echo $row['courseID']; ?>"
                            <?php echo ($filterID == $row['courseID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($row['courseName']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </form>
                <div class="title-badge">Progress Dashboard</div>
            </div>

            <div class="cards-container">
                <div class="card card-purple">
                    <h3>Average Score</h3>
                    <div class="big-num"><?php echo $globalAvg; ?>%</div>
                </div>
                <div class="card card-green">
                    <h3>Attendance Rate</h3>
                    <div class="big-num"><?php echo $globalAtt; ?>%</div>
                </div>
                <div class="card card-blue">
                    <h3>Assessments completed</h3>
                    <div class="big-num"><?php echo $totalAssessments; ?></div>
                </div>
                <div class="card card-pink">
                    <h3>Status</h3>
                    <div class="big-num" style="font-size: 18px;">
                        <?php echo htmlspecialchars($globalStatus['label']); ?></div>
                </div>
            </div>

            <?php if (!$isPdfView): ?>
            <div class="charts-container">
                <div class="chart-box">
                    <canvas id="lineChart"></canvas>
                    <div class="chart-title">Student performance Trend</div>
                </div>
                <div class="chart-box">
                    <canvas id="pieChart"></canvas>
                    <div class="chart-title">Grade Distribution (Count)</div>
                </div>
                <div class="chart-box">
                    <canvas id="barChart"></canvas>
                    <div class="chart-title">Course Comparison</div>
                </div>
            </div>
            <?php endif; ?>

            <div class="bottom-section">
                <div class="table-wrapper">
                    <div class="table-header">
                        <span>Filter: Course/Score/Attendance/Status</span>
                        <span>COURSES</span>
                    </div>
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Course ID</th>
                                <th>Course Name</th>
                                <th>Assessment type</th>
                                <th>Average Score</th>
                                <th>Attendance</th>
                                <th>Status</th>
                                <th>Lecturer Remark</th> </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($coursesData as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['id']); ?></td>
                                <td>
                                    <a href="student_view_course.php?course_id=<?php echo $c['id']; ?>"
                                    style="color: #2e7d32; font-weight: bold; text-decoration: none;">
                                    <?php echo htmlspecialchars($c['name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($c['type']); ?></td>
                                <td style="text-align: center;"><?php echo (float)$c['avg']; ?></td>
                                <td><?php echo (float)$c['att']; ?>%</td>
                                <td>
                                    <span class="status-btn" style="background-color: <?php echo htmlspecialchars($c['status']['color']); ?>;">
                                        <?php echo htmlspecialchars($c['status']['label']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px; color: #555; font-style: italic;">
                                    <?php echo htmlspecialchars($c['remark']); ?> </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$isPdfView): ?>
                <div class="action-sidebar">
                    <button class="action-btn btn-dl-pdf"
                        onclick="window.open('student_progress.php?course_id=<?php echo urlencode($filterID); ?>&pdf=1','_blank')">
                        Download PDF
                    </button>

                    <button class="action-btn btn-dl-excel"
                        onclick="window.location.href='student_progress.php?course_id=<?php echo urlencode($filterID); ?>&download=excel'">
                        Download Excel
                    </button>

                    <button class="action-btn btn-share" onclick="alert('Link Copied to Clipboard!')">
                        Share Progress
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$isPdfView): ?>
    <script>
    const trendLabels = <?php echo json_encode($trendLabels); ?>;
    const trendData = <?php echo json_encode($trendData); ?>;
    const barLabels = <?php echo json_encode($barLabels); ?>;
    const barData = <?php echo json_encode($barData); ?>;
    const pieData = <?php echo json_encode(array_values($gradeCounts)); ?>;

    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: trendLabels.length ? trendLabels : ['No Data'],
            datasets: [{
                label: 'Marks',
                data: trendData.length ? trendData : [0],
                borderColor: '#3f51b5',
                backgroundColor: 'rgba(63, 81, 181, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            plugins: {
                legend: {
                    display: false
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    new Chart(document.getElementById('pieChart'), {
        type: 'pie',
        data: {
            labels: ['A (80+)', 'B (70-79)', 'C (60-69)', 'D (50-59)', 'F (<50)'],
            datasets: [{
                data: pieData,
                backgroundColor: ['#4caf50', '#cddc39', '#ffeb3b', '#ff9800', '#f44336']
            }]
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        font: {
                            size: 10
                        }
                    }
                }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });

    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: barLabels.length ? barLabels : ['No Data'],
            datasets: [{
                label: 'Avg Score',
                data: barData.length ? barData : [0],
                backgroundColor: ['#ffcdd2', '#bbdefb', '#fff9c4', '#b2dfdb', '#e1bee7']
            }]
        },
        options: {
            plugins: {
                legend: {
                    display: false
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    </script>
    <?php endif; ?>

    <?php if ($isPdfView): ?>
    <script>
    window.addEventListener('load', () => window.print());
    </script>
    <?php endif; ?>

</body>

</html>