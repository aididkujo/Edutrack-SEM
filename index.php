<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
  header('Location: login.php');
  exit;
}

// Redirect user based on role
switch ($_SESSION['role']) {
  case 'student':
    header('Location: student_dashboard.php');
    exit;
  case 'lecturer':
    header('Location: lecturer_dashboard.php');
    exit;
  case 'admin':
    header('Location: admin_dashboard.php');
    exit;
  default:
    // Invalid role, logout
    header('Location: logout.php');
    exit;
}
