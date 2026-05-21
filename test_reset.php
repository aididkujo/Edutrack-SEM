<?php
echo "Test file works!<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "File exists: " . (file_exists('reset_password.php') ? 'YES' : 'NO');
