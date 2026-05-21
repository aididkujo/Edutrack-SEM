<?php
// This script adds the profile_picture column to the user table
require_once 'config.php';

try {
    // Check if column already exists
    $result = $conn->query("SHOW COLUMNS FROM user LIKE 'profile_picture'");

    if ($result->num_rows > 0) {
        echo "✓ Column 'profile_picture' already exists in the user table.<br>";
    } else {
        // Add the column
        $sql = "ALTER TABLE user ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER age";

        if ($conn->query($sql)) {
            echo "✓ Successfully added 'profile_picture' column to the user table!<br>";
        } else {
            echo "✗ Error adding column: " . $conn->error . "<br>";
        }
    }

    // Verify the column was added
    $result = $conn->query("SHOW COLUMNS FROM user LIKE 'profile_picture'");
    if ($result->num_rows > 0) {
        echo "<br><strong style='color: green;'>Database is ready for profile pictures!</strong><br>";
        echo "<br>You can now:<br>";
        echo "1. Go to your profile page (as student, lecturer, or admin)<br>";
        echo "2. Click on the profile photo area<br>";
        echo "3. Select an image file (JPG, PNG, or GIF, max 5MB)<br>";
        echo "4. Click Edit to save<br>";
        echo "<br><a href='index.php'>← Go to Login</a>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}

$conn->close();
