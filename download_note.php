<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_role('student');

$userID = (int)($_SESSION['userID'] ?? 0);
$noteID = isset($_GET['noteID']) ? (int)$_GET['noteID'] : 0;

$sql = "
  SELECT n.filePath, n.courseID
  FROM notes n
  JOIN enrollment e ON e.courseID = n.courseID
  WHERE n.noteID = ? AND e.userID = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $noteID, $userID);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) { http_response_code(403); echo "Access denied."; exit; }

$filePath = $row['filePath'];

$baseDir = realpath(__DIR__);
$fullPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . $filePath);

if (!$fullPath || strpos($fullPath, $baseDir) !== 0 || !is_file($fullPath)) {
  http_response_code(404);
  echo "File not found.";
  exit;
}

$filename = basename($fullPath);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
