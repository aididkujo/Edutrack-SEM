<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sID = $_POST['studentID'];
    $cID = $_POST['courseID'];
    $lID = $_SESSION['userID'];
    $remark = $_POST['remark'];

    // 1. Delete old remark for this student/course
    $del = $conn->prepare("DELETE FROM student_remarks WHERE studentID = ? AND courseID = ?");
    $del->bind_param("ii", $sID, $cID);
    $del->execute();

    // 2. Insert new remark
    $stmt = $conn->prepare("INSERT INTO student_remarks (courseID, studentID, lecturerID, remark) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $cID, $sID, $lID, $remark);
    
    if ($stmt->execute()) {
        echo "Success";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>