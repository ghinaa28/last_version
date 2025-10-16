<!DOCTYPE html>
<html>
<head>
    <title>Test Company Signup</title>
</head>
<body>
    <h2>Test Company Registration</h2>
    
    <?php
    include 'connection.php';
    
    $success = "";
    $error = "";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h3>POST Data Received:</h3>";
        echo "<pre>";
        print_r($_POST);
        echo "</pre>";
        
        // Test the company registration logic
        if (isset($_POST['company_name'])) {
            echo "<h3>Testing Company Registration...</h3>";
            
            try {
                $company = $conn->real_escape_string($_POST['company_name']);
                $email = $conn->real_escape_string($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $phone = $conn->real_escape_string($_POST['phone']);
                $industry = $conn->real_escape_string($_POST['industry']);
                $website = $conn->real_escape_string($_POST['website']);
                $status = 'pending';

                // Start transaction
                $conn->begin_transaction();

                // Insert company
                $sql = "INSERT INTO companies (company_name, email, password, phone, industry, website, logo_path, status, created_at)
                        VALUES ('$company', '$email', '$password', '$phone', '$industry', '$website', NULL, '$status', NOW())";

                echo "Company SQL: " . $sql . "<br>";

                if (!$conn->query($sql)) {
                    throw new Exception("Error creating company: " . $conn->error);
                }

                $company_id = $conn->insert_id;
                echo "Company created with ID: $company_id<br>";

                // Insert primary location
                $location_name = $conn->real_escape_string($_POST['location_name']);
                $location_type = $conn->real_escape_string($_POST['location_type']);
                $address = $conn->real_escape_string($_POST['address']);
                $city = $conn->real_escape_string($_POST['city']);
                $country = $conn->real_escape_string($_POST['country']);
                $postal_code = $conn->real_escape_string($_POST['postal_code']);
                $location_phone = $conn->real_escape_string($_POST['location_phone']);
                $location_email = $conn->real_escape_string($_POST['location_email']);

                $location_sql = "INSERT INTO company_locations (company_id, location_name, location_type, address, city, country, postal_code, phone, email, is_primary, status, created_at)
                                VALUES ('$company_id', '$location_name', '$location_type', '$address', '$city', '$country', '$postal_code', '$location_phone', '$location_email', TRUE, 'active', NOW())";

                echo "Location SQL: " . $location_sql . "<br>";

                if (!$conn->query($location_sql)) {
                    throw new Exception("Error creating primary location: " . $conn->error);
                }

                // Commit transaction
                $conn->commit();
                $success = "Company registration successful!";
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
    
    if ($success) {
        echo "<div style='color: green; font-weight: bold;'>$success</div>";
    }
    if ($error) {
        echo "<div style='color: red; font-weight: bold;'>$error</div>";
    }
    ?>
    
    <form method="POST">
        <h3>Company Information</h3>
        <p>Company Name: <input type="text" name="company_name" required></p>
        <p>Email: <input type="email" name="email" required></p>
        <p>Password: <input type="password" name="password" required></p>
        <p>Phone: <input type="text" name="phone"></p>
        <p>Industry: <input type="text" name="industry" required></p>
        <p>Website: <input type="url" name="website"></p>
        
        <h3>Primary Location</h3>
        <p>Location Name: <input type="text" name="location_name" required></p>
        <p>Location Type: 
            <select name="location_type" required>
                <option value="">Select</option>
                <option value="head_office">Head Office</option>
                <option value="branch">Branch</option>
                <option value="training_center">Training Center</option>
            </select>
        </p>
        <p>Address: <textarea name="address" required></textarea></p>
        <p>City: <input type="text" name="city" required></p>
        <p>Country: <input type="text" name="country" required></p>
        <p>Postal Code: <input type="text" name="postal_code"></p>
        <p>Location Phone: <input type="text" name="location_phone"></p>
        <p>Location Email: <input type="email" name="location_email"></p>
        
        <p><input type="submit" value="Test Registration"></p>
    </form>
</body>
</html>
