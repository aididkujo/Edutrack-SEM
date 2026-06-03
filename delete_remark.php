<?php
session_start();
require_once 'config.php';

// 1. Ensure the user is authorized
if ($_SESSION['role'] !== 'lecturer') {
    http_response_code(403); // Forbidden
    exit("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remarkID'])) {
    // 2. Prepare and execute
    $stmt = $conn->prepare("DELETE FROM student_remarks WHERE remarkID = ?");
    $stmt->bind_param("i", $_POST['remarkID']);
    
    if ($stmt->execute()) {
        // 3. IMPORTANT: Tell the JS that it succeeded
        echo "success"; 
    } else {
        // Optional: Send back the error for debugging
        http_response_code(500);
        echo "Database error: " . $stmt->error;
    }
} else {
    http_response_code(400); // Bad request
    echo "Invalid request";
}
?>