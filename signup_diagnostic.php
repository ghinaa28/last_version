<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Company Signup Diagnostic</h2>";

// Check if company_locations table exists
include 'connection.php';

$result = $conn->query('SHOW TABLES LIKE "company_locations"');
if ($result && $result->num_rows > 0) {
    echo "✅ company_locations table exists<br>";
} else {
    echo "❌ company_locations table does NOT exist<br>";
}

// Check if companies table exists
$result = $conn->query('SHOW TABLES LIKE "companies"');
if ($result && $result->num_rows > 0) {
    echo "✅ companies table exists<br>";
} else {
    echo "❌ companies table does NOT exist<br>";
}

// Check triggers
$result = $conn->query("SHOW TRIGGERS LIKE 'company_locations'");
if ($result && $result->num_rows > 0) {
    echo "✅ Triggers exist on company_locations table<br>";
    while($row = $result->fetch_assoc()) {
        echo "&nbsp;&nbsp;- " . $row['Trigger'] . " (" . $row['Event'] . " " . $row['Timing'] . ")<br>";
    }
} else {
    echo "❌ No triggers found on company_locations table<br>";
}

// Test database connection
if ($conn->connect_error) {
    echo "❌ Database connection failed: " . $conn->connect_error . "<br>";
} else {
    echo "✅ Database connection successful<br>";
}

// Check if signup.php file exists and is readable
if (file_exists('signup.php')) {
    echo "✅ signup.php file exists<br>";
} else {
    echo "❌ signup.php file does NOT exist<br>";
}

echo "<br><h3>Common Issues and Solutions:</h3>";
echo "<ol>";
echo "<li><strong>Database Triggers:</strong> If you see trigger errors, the triggers might be causing conflicts. Try accessing the test page: <a href='test_signup.php'>test_signup.php</a></li>";
echo "<li><strong>Form Validation:</strong> Make sure all required fields are filled out in the company registration form</li>";
echo "<li><strong>Email Duplication:</strong> Check if the email address you're using is already registered</li>";
echo "<li><strong>File Permissions:</strong> Make sure the uploads/companies/logo directory exists and is writable</li>";
echo "<li><strong>JavaScript Errors:</strong> Check browser console for any JavaScript errors that might prevent form submission</li>";
echo "</ol>";

echo "<br><h3>Test Company Registration:</h3>";
echo "<p>Try the test registration form: <a href='test_signup.php'>test_signup.php</a></p>";
echo "<p>This will help identify exactly where the issue is occurring.</p>";
?>
