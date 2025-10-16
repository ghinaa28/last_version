<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Debug: Testing company signup process\n";
echo "POST data received:\n";
print_r($_POST);
echo "\nFILES data received:\n";
print_r($_FILES);
echo "\n";

// Check if we're in POST mode
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "POST method detected\n";
    
    // Check for company_name to identify company registration
    if (isset($_POST['company_name'])) {
        echo "Company registration detected\n";
        
        // Check required fields
        $required_fields = ['company_name', 'email', 'password', 'industry', 'location_name', 'location_type', 'address', 'city', 'country'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            echo "Missing required fields: " . implode(', ', $missing_fields) . "\n";
        } else {
            echo "All required fields present\n";
        }
        
        // Check email validation
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            echo "Invalid email format\n";
        } else {
            echo "Email format is valid\n";
        }
        
    } else {
        echo "Not a company registration\n";
    }
} else {
    echo "GET method - showing form\n";
}
?>
