<?php

/**
 * Email Configuration for EduTrack
 * Using Laragon's local SMTP for development
 */

// ========================================
// EMAIL CONFIGURATION
// ========================================
// Switch between Mailpit (local testing) and Gmail (real emails)
// Set USE_GMAIL to true to send real emails via Gmail
// Set USE_GMAIL to false to use Mailpit (local testing)

define('USE_GMAIL', true); // Change to true to use Gmail

if (USE_GMAIL) {
    // ===== GMAIL SMTP SETTINGS =====
    // IMPORTANT: Replace with your Gmail credentials
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USERNAME', 'boyvoo.01@gmail.com');
    define('SMTP_PASSWORD', 'ovighbuzbrhshfug'); // Gmail App Password - no spaces
    define('SMTP_SECURE', 'tls');
    define('MAIL_FROM_EMAIL', 'boyvoo.01@gmail.com');
    define('MAIL_FROM_NAME', 'EduTrack System');
} else {
    // ===== MAILPIT (LOCAL TESTING) =====
    define('SMTP_HOST', 'localhost');
    define('SMTP_PORT', 1025);
    define('SMTP_USERNAME', '');
    define('SMTP_PASSWORD', '');
    define('SMTP_SECURE', '');
    define('MAIL_FROM_EMAIL', 'noreply@edutrack.local');
    define('MAIL_FROM_NAME', 'EduTrack System');
}

// Site URL
define('SITE_URL', 'http://localhost/edutrack');
