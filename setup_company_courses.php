<?php
require_once 'connection.php';

echo "<h2>Setting up Company Course Management</h2>";

// Check if courses table exists and modify it to support company courses
$check_table = "SHOW TABLES LIKE 'courses'";
$result = $conn->query($check_table);

if ($result->num_rows > 0) {
    // Table exists, check if it has the required columns for company courses
    $check_columns = "SHOW COLUMNS FROM courses LIKE 'created_by_type'";
    $column_result = $conn->query($check_columns);
    
    if ($column_result->num_rows == 0) {
        // Add required columns for company course support
        $alter_sql = "ALTER TABLE courses 
            ADD COLUMN created_by_type ENUM('company','instructor') DEFAULT 'instructor' AFTER instructor_id,
            ADD COLUMN created_by_id INT DEFAULT NULL AFTER created_by_type,
            ADD COLUMN related_internship_id INT DEFAULT NULL AFTER created_by_id,
            ADD INDEX idx_created_by (created_by_type, created_by_id)";
        
        if ($conn->query($alter_sql)) {
            echo "<p style='color: green;'>✓ Added company course support columns to existing courses table</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding columns: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Courses table already supports company courses</p>";
    }
} else {
    // Create courses table with company support
    $create_sql = "CREATE TABLE courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        instructor_id INT DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        duration VARCHAR(100) NOT NULL,
        mode ENUM('Online','Onsite') NOT NULL,
        requirements TEXT DEFAULT NULL,
        created_by_type ENUM('company','instructor') NOT NULL,
        created_by_id INT NOT NULL,
        related_internship_id INT DEFAULT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_created_by (created_by_type, created_by_id),
        INDEX idx_status (status)
    )";
    
    if ($conn->query($create_sql)) {
        echo "<p style='color: green;'>✓ courses table created successfully with company support</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating courses table: " . $conn->error . "</p>";
    }
}

echo "<p><a href='company_dashboard.php'>← Back to Company Dashboard</a></p>";
?>
