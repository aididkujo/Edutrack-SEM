<?php
// Database configuration for EduTrack (Cloud + Local compatible)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Read from environment variables (Render)
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: 3306;
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$database = getenv('DB_NAME') ?: 'edutrack';

// Create connection
//$conn = new mysqli($host, $username, $password, $database, (int)$port);
$conn = new mysqli("127.0.0.1", "root", "", "edutrack", 3306);

// Set charset
$conn->set_charset("utf8mb4");
?>

