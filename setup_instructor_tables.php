<?php
require_once 'connection.php';

// Read the SQL file
$sql = file_get_contents('instructor_service_tables.sql');

// Split the SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

$success_count = 0;
$error_count = 0;

echo "<h2>Setting up Instructor Service Database Tables</h2>";

foreach ($statements as $statement) {
    if (!empty($statement) && !preg_match('/^--/', $statement)) {
        if ($conn->query($statement)) {
            $success_count++;
            echo "<p style='color: green;'>✓ Success: " . substr($statement, 0, 50) . "...</p>";
        } else {
            $error_count++;
            echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
            echo "<p style='color: red;'>Statement: " . substr($statement, 0, 100) . "...</p>";
        }
    }
}

echo "<h3>Summary:</h3>";
echo "<p>Successful statements: $success_count</p>";
echo "<p>Failed statements: $error_count</p>";

if ($error_count == 0) {
    echo "<p style='color: green; font-weight: bold;'>✓ All tables created successfully! The Instructor Service is ready to use.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Some errors occurred. Please check the error messages above.</p>";
}

$conn->close();
?>


