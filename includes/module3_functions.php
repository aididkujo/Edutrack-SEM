<?php
// includes/module3_functions.php

/**
 * Calculate Average Score for a specific student in a course
 */
function calculate_course_average($conn, $userID, $courseID) {
    // 1. Get all graded submissions for this course
    $sql = "SELECT s.grade 
            FROM submission s 
            JOIN assessment a ON s.assessmentID = a.assessmentID 
            WHERE s.userID = ? AND a.courseID = ?";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0; // Return 0 if query preparation fails
    }
    
    $stmt->bind_param("ii", $userID, $courseID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $totalScore = 0;
    $count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $totalScore += $row['grade'];
        $count++;
    }
    
    if ($count === 0) return 0; // Avoid division by zero
    
    return round($totalScore / $count, 2);
}

/**
 * Calculate Attendance Rate (%)
 */
function calculate_attendance_rate($conn, $userID, $courseID) {
    // Count total sessions for this course
    $sqlTotal = "SELECT COUNT(*) as total FROM attendance WHERE userID = ? AND courseID = ?";
    $stmt = $conn->prepare($sqlTotal);
    if (!$stmt) return 0;
    
    $stmt->bind_param("ii", $userID, $courseID);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    if ($total == 0) return 0;

    // Count 'Present' sessions
    $sqlPresent = "SELECT COUNT(*) as present FROM attendance WHERE userID = ? AND courseID = ? AND status = 'Present'";
    $stmt2 = $conn->prepare($sqlPresent);
    $stmt2->bind_param("ii", $userID, $courseID);
    $stmt2->execute();
    $present = $stmt2->get_result()->fetch_assoc()['present'];
    
    return round(($present / $total) * 100, 2);
}

/**
 * Determine Status based on Score and Attendance
 */
function get_student_status($averageScore, $attendanceRate) {
    if ($averageScore < 50 || $attendanceRate < 80) {
        return ['label' => 'At Risk', 'color' => '#dc3545']; // Red
    } elseif ($averageScore >= 80 && $attendanceRate >= 90) {
        return ['label' => 'Excellent', 'color' => '#28a745']; // Green
    } else {
        return ['label' => 'Good', 'color' => '#ffc107']; // Yellow
    }
}

/**
 * Update the optimization table (Progress Summary)
 * Call this whenever a grade or attendance is updated
 */
function update_progress_summary($conn, $userID, $courseID) {
    $avg = calculate_course_average($conn, $userID, $courseID);
    $att = calculate_attendance_rate($conn, $userID, $courseID);
    
    // Check if record exists
    $checkSql = "SELECT summaryID FROM progress_summary WHERE userID = ? AND courseID = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ii", $userID, $courseID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update
        $updateSql = "UPDATE progress_summary SET total_average = ?, attendance_rate = ? WHERE userID = ? AND courseID = ?";
        $stmt2 = $conn->prepare($updateSql);
        $stmt2->bind_param("ddii", $avg, $att, $userID, $courseID);
        $stmt2->execute();
    } else {
        // Insert
        $insertSql = "INSERT INTO progress_summary (userID, courseID, total_average, attendance_rate) VALUES (?, ?, ?, ?)";
        $stmt3 = $conn->prepare($insertSql);
        $stmt3->bind_param("iidd", $userID, $courseID, $avg, $att);
        $stmt3->execute();
    }
}
?>