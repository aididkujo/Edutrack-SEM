<?php
echo "<h2>PHP Email Configuration Test</h2>";

// Check PHP version
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// Check if OpenSSL is loaded
echo "<p><strong>OpenSSL Extension:</strong> " . (extension_loaded('openssl') ? '✅ Enabled' : '❌ Disabled') . "</p>";

// Check if sockets extension is loaded
echo "<p><strong>Sockets Extension:</strong> " . (extension_loaded('sockets') ? '✅ Enabled' : '❌ Disabled') . "</p>";

// Test Gmail SMTP connection
echo "<h3>Testing Gmail SMTP Connection...</h3>";

$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;

$connection = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);
if ($connection) {
    echo "<p>✅ Successfully connected to {$smtp_host}:{$smtp_port}</p>";
    fclose($connection);
} else {
    echo "<p>❌ Failed to connect to {$smtp_host}:{$smtp_port}</p>";
    echo "<p>Error: {$errstr} ({$errno})</p>";
}

// Test PHPMailer
echo "<h3>Testing PHPMailer...</h3>";
require_once 'vendor/autoload.php';

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    echo "<p>✅ PHPMailer loaded successfully</p>";
    echo "<p><strong>PHPMailer Version:</strong> " . $mail::VERSION . "</p>";
} catch (Exception $e) {
    echo "<p>❌ PHPMailer error: " . $e->getMessage() . "</p>";
}
