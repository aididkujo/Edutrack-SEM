<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$userID = (int)($_SESSION['userID'] ?? 0);
$role = $_SESSION['role'] ?? '';
$submissionID = (int)($_GET['submissionID'] ?? 0);

if ($submissionID <= 0) {
    http_response_code(400);
    die("Invalid request.");
}

/* Load submission with assessment and course owner */
$stmt = $conn->prepare("
  SELECT s.submissionID, s.userID AS studentID, s.filePath, s.originalFileName,
         a.assessmentID, a.courseID,
         c.userID AS lecturerID
  FROM submission s
  JOIN assessment a ON a.assessmentID = s.assessmentID
  JOIN course c ON c.courseID = a.courseID
  WHERE s.submissionID = ?
  LIMIT 1
");
$stmt->bind_param("i", $submissionID);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || empty($row['filePath'])) {
    http_response_code(404);
    die("File not found.");
}

/* Access control */
$allowed = false;
if ($role === 'student' && (int)$row['studentID'] === $userID) $allowed = true;
if ($role === 'lecturer' && (int)$row['lecturerID'] === $userID) $allowed = true;

if (!$allowed) {
    http_response_code(403);
    die("Access denied.");
}

$abs = __DIR__ . "/" . $row['filePath'];
if (!is_file($abs)) {
    http_response_code(404);
    die("File missing on server.");
}

$downloadName = $row['originalFileName'] ?: basename($abs);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($downloadName).'"');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit;
