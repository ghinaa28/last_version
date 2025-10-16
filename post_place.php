<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$success_message = "";
$error_message = "";

// Create equipment tables if they don't exist
$create_equipment_categories = "CREATE TABLE IF NOT EXISTS equipment_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    icon_class VARCHAR(50) DEFAULT 'fas fa-cog',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_equipment_categories);

$create_equipment_items = "CREATE TABLE IF NOT EXISTS equipment_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    standard_price DECIMAL(10,2) DEFAULT 0.00,
    unit_type ENUM('per_hour', 'per_day', 'per_week', 'per_month') DEFAULT 'per_hour',
    is_custom BOOLEAN DEFAULT FALSE,
    company_id INT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES equipment_categories(category_id) ON DELETE CASCADE
)";
$conn->query($create_equipment_items);

$create_equipment_packages = "CREATE TABLE IF NOT EXISTS equipment_packages (
    package_id INT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(200) NOT NULL,
    package_description TEXT,
    total_price DECIMAL(10,2) DEFAULT 0.00,
    package_type ENUM('predefined', 'custom') DEFAULT 'predefined',
    company_id INT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_equipment_packages);

$create_package_items = "CREATE TABLE IF NOT EXISTS package_items (
    package_item_id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT,
    item_id INT,
    quantity INT DEFAULT 1,
    FOREIGN KEY (package_id) REFERENCES equipment_packages(package_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES equipment_items(item_id) ON DELETE CASCADE
)";
$conn->query($create_package_items);

$create_place_equipment = "CREATE TABLE IF NOT EXISTS place_equipment (
    place_equipment_id INT AUTO_INCREMENT PRIMARY KEY,
    place_id INT,
    item_id INT,
    quantity_available INT DEFAULT 1,
    custom_price DECIMAL(10,2) NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (place_id) REFERENCES places(place_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES equipment_items(item_id) ON DELETE CASCADE
)";
$conn->query($create_place_equipment);

$create_place_packages = "CREATE TABLE IF NOT EXISTS place_packages (
    place_package_id INT AUTO_INCREMENT PRIMARY KEY,
    place_id INT,
    package_id INT,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (place_id) REFERENCES places(place_id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES equipment_packages(package_id) ON DELETE CASCADE
)";
$conn->query($create_place_packages);

// Insert sample equipment categories if they don't exist
$check_categories = "SELECT COUNT(*) as count FROM equipment_categories";
$result = $conn->query($check_categories);
$count = $result->fetch_assoc()['count'];

if ($count == 0) {
    $sample_categories = [
        "('Audio/Visual', 'fas fa-video', 'active')",
        "('Furniture', 'fas fa-chair', 'active')",
        "('Technology', 'fas fa-laptop', 'active')",
        "('Kitchen', 'fas fa-utensils', 'active')",
        "('Lighting', 'fas fa-lightbulb', 'active')",
        "('Cleaning', 'fas fa-broom', 'active')"
    ];
    
    $insert_categories = "INSERT INTO equipment_categories (category_name, icon_class, status) VALUES " . implode(', ', $sample_categories);
    $conn->query($insert_categories);
}

// Insert sample equipment items if they don't exist
$check_items = "SELECT COUNT(*) as count FROM equipment_items";
$result = $conn->query($check_items);
$count = $result->fetch_assoc()['count'];

if ($count == 0) {
    $sample_items = [
        "(1, 'Projector', 'HD Projector for presentations', 25.00, 'per_hour', FALSE, NULL, 'active')",
        "(1, 'Sound System', 'Professional audio system', 30.00, 'per_hour', FALSE, NULL, 'active')",
        "(1, 'Microphone', 'Wireless microphone set', 15.00, 'per_hour', FALSE, NULL, 'active')",
        "(2, 'Conference Table', 'Large conference table', 20.00, 'per_hour', FALSE, NULL, 'active')",
        "(2, 'Chairs', 'Comfortable seating chairs', 2.00, 'per_hour', FALSE, NULL, 'active')",
        "(3, 'Laptop', 'High-performance laptop', 15.00, 'per_hour', FALSE, NULL, 'active')",
        "(3, 'WiFi Hotspot', 'High-speed internet access', 10.00, 'per_hour', FALSE, NULL, 'active')",
        "(4, 'Coffee Machine', 'Professional coffee maker', 20.00, 'per_hour', FALSE, NULL, 'active')",
        "(4, 'Refrigerator', 'Commercial refrigerator', 15.00, 'per_hour', FALSE, NULL, 'active')",
        "(5, 'LED Lights', 'Professional lighting setup', 25.00, 'per_hour', FALSE, NULL, 'active')",
        "(6, 'Cleaning Supplies', 'Professional cleaning kit', 10.00, 'per_hour', FALSE, NULL, 'active')"
    ];
    
    $insert_items = "INSERT INTO equipment_items (category_id, item_name, description, standard_price, unit_type, is_custom, company_id, status) VALUES " . implode(', ', $sample_items);
    $conn->query($insert_items);
}

// Check and add pricing column to place_availability table if it doesn't exist
$check_availability_pricing = "SHOW COLUMNS FROM place_availability LIKE 'hourly_rate'";
$result_availability = $conn->query($check_availability_pricing);
if ($result_availability->num_rows == 0) {
    $add_availability_pricing = "ALTER TABLE place_availability ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT NULL AFTER end_time";
    $conn->query($add_availability_pricing);
}

// Get company's primary location for auto-filling
$primary_location = null;
$location_sql = "SELECT * FROM company_locations WHERE company_id = ? AND is_primary = 1 LIMIT 1";
$stmt = $conn->prepare($location_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$location_result = $stmt->get_result();
if ($location_result && $location_result->num_rows > 0) {
    $primary_location = $location_result->fetch_assoc();
} else {
    // Company doesn't have a primary location, redirect to manage locations
    header("Location: manage_locations.php?error=no_primary_location");
    exit();
}

// Get equipment categories and items for the equipment selection
$equipment_categories_sql = "SELECT * FROM equipment_categories WHERE status = 'active' ORDER BY category_name";
$equipment_categories_result = $conn->query($equipment_categories_sql);
$equipment_categories = [];
if ($equipment_categories_result && $equipment_categories_result->num_rows > 0) {
    while ($row = $equipment_categories_result->fetch_assoc()) {
        $equipment_categories[] = $row;
    }
}

// Get predefined equipment items
$equipment_items_sql = "SELECT ei.*, ec.category_name, ec.icon_class 
                        FROM equipment_items ei 
                        JOIN equipment_categories ec ON ei.category_id = ec.category_id 
                        WHERE ei.is_custom = FALSE AND ei.status = 'active' 
                        ORDER BY ec.category_name, ei.item_name";
$equipment_items_result = $conn->query($equipment_items_sql);
$equipment_items = [];
if ($equipment_items_result && $equipment_items_result->num_rows > 0) {
    while ($row = $equipment_items_result->fetch_assoc()) {
        $equipment_items[] = $row;
    }
}

// Get custom equipment items for this company
$custom_equipment_sql = "SELECT ei.*, ec.category_name, ec.icon_class 
                         FROM equipment_items ei 
                         JOIN equipment_categories ec ON ei.category_id = ec.category_id 
                         WHERE ei.is_custom = TRUE AND ei.company_id = ? AND ei.status = 'active' 
                         ORDER BY ec.category_name, ei.item_name";
$stmt = $conn->prepare($custom_equipment_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$custom_equipment_result = $stmt->get_result();
$custom_equipment_items = [];
if ($custom_equipment_result && $custom_equipment_result->num_rows > 0) {
    while ($row = $custom_equipment_result->fetch_assoc()) {
        $custom_equipment_items[] = $row;
    }
}

// Get equipment packages
$equipment_packages_sql = "SELECT ep.*, 
                                  (SELECT COUNT(*) FROM package_items pi WHERE pi.package_id = ep.package_id) as item_count
                           FROM equipment_packages ep 
                           WHERE (ep.company_id = ? OR ep.package_type = 'predefined') 
                           AND ep.status = 'active' 
                           ORDER BY ep.package_type, ep.package_name";
$stmt = $conn->prepare($equipment_packages_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$equipment_packages_result = $stmt->get_result();
$equipment_packages = [];
if ($equipment_packages_result && $equipment_packages_result->num_rows > 0) {
    while ($row = $equipment_packages_result->fetch_assoc()) {
        $equipment_packages[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $place_name = $conn->real_escape_string($_POST['place_name']);
        $place_type = $conn->real_escape_string($_POST['place_type']);
        $space_type = $conn->real_escape_string($_POST['space_type']);
        $description = $conn->real_escape_string($_POST['description']);
        $capacity = (int)$_POST['capacity'];
        $hourly_rate = (float)$_POST['hourly_rate'];
        $daily_rate = (float)$_POST['daily_rate'];
        $weekly_rate = (float)$_POST['weekly_rate'];
        $monthly_rate = (float)$_POST['monthly_rate'];
        $address = $conn->real_escape_string($_POST['address']);
        $city = $conn->real_escape_string($_POST['city']);
        $country = $conn->real_escape_string($_POST['country']);
        $postal_code = !empty($_POST['postal_code']) ? $conn->real_escape_string($_POST['postal_code']) : null;
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $booking_policy = $conn->real_escape_string($_POST['booking_policy']);
        $cancellation_policy = $conn->real_escape_string($_POST['cancellation_policy']);
        $is_equipment_included = isset($_POST['is_equipment_included']) ? 1 : 0;
        
        // Handle amenities
        $amenities = [];
        if (isset($_POST['amenities'])) {
            foreach ($_POST['amenities'] as $amenity) {
                $amenities[] = $conn->real_escape_string($amenity);
            }
        }
        $amenities_json = json_encode($amenities);
        
        // Handle availability schedule with simple pricing
        $availability = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Get simple pricing settings
        $weekday_price = isset($_POST['weekday_price']) ? (float)$_POST['weekday_price'] : $hourly_rate;
        $weekend_price = isset($_POST['weekend_price']) ? (float)$_POST['weekend_price'] : $hourly_rate;
        
        foreach ($days as $day) {
            if (isset($_POST[$day . '_start']) && isset($_POST[$day . '_end'])) {
                // Determine if it's weekend or weekday
                $is_weekend = in_array($day, ['saturday', 'sunday']);
                $day_price = $is_weekend ? $weekend_price : $weekday_price;
                
                $availability[$day] = [
                    'start' => $_POST[$day . '_start'],
                    'end' => $_POST[$day . '_end'],
                    'available' => isset($_POST[$day . '_available']),
                    'hourly_rate' => $day_price
                ];
            }
        }
        $availability_json = json_encode($availability);
        
        // Handle image uploads
        $images = [];
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = "uploads/places/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                if ($_FILES['images']['error'][$i] == 0) {
                    $file_extension = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                    $filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $filepath)) {
                        $images[] = $filepath;
                    }
                }
            }
        }
        $images_json = json_encode($images);
        
        // Calculate total pricing with equipment costs
        $total_equipment_cost = 0;
        $selected_equipment = [];
        
        // Calculate equipment costs from database items
        if (isset($_POST['selected_equipment']) && is_array($_POST['selected_equipment'])) {
            foreach ($_POST['selected_equipment'] as $item_id) {
                $item_id = (int)$item_id;
                $quantity = isset($_POST['equipment_quantity'][$item_id]) ? (int)$_POST['equipment_quantity'][$item_id] : 1;
                $custom_price = isset($_POST['equipment_custom_price'][$item_id]) ? (float)$_POST['equipment_custom_price'][$item_id] : 0;
                
                // Get equipment item details
                $item_sql = "SELECT ei.*, ec.category_name FROM equipment_items ei 
                            JOIN equipment_categories ec ON ei.category_id = ec.category_id 
                            WHERE ei.item_id = ?";
                $item_stmt = $conn->prepare($item_sql);
                $item_stmt->bind_param("i", $item_id);
                $item_stmt->execute();
                $item_result = $item_stmt->get_result();
                
                if ($item_result && $item_result->num_rows > 0) {
                    $item = $item_result->fetch_assoc();
                    $item_price = $custom_price > 0 ? $custom_price : $item['standard_price'];
                    $item_total_cost = $item_price * $quantity;
                    
                    $total_equipment_cost += $item_total_cost;
                    $selected_equipment[] = [
                        'item_id' => $item_id,
                        'name' => $item['item_name'],
                        'category' => $item['category_name'],
                        'quantity' => $quantity,
                        'unit_price' => $item_price,
                        'total_cost' => $item_total_cost,
                        'unit_type' => $item['unit_type']
                    ];
                }
            }
        }
        
        // Calculate package costs
        if (isset($_POST['selected_packages']) && is_array($_POST['selected_packages'])) {
            foreach ($_POST['selected_packages'] as $package_id) {
                $package_id = (int)$package_id;
                
                // Get package details
                $package_sql = "SELECT * FROM equipment_packages WHERE package_id = ?";
                $package_stmt = $conn->prepare($package_sql);
                $package_stmt->bind_param("i", $package_id);
                $package_stmt->execute();
                $package_result = $package_stmt->get_result();
                
                if ($package_result && $package_result->num_rows > 0) {
                    $package = $package_result->fetch_assoc();
                    $total_equipment_cost += $package['total_price'];
                    $selected_equipment[] = [
                        'package_id' => $package_id,
                        'name' => $package['package_name'],
                        'type' => 'package',
                        'total_cost' => $package['total_price']
                    ];
                }
            }
        }
        
        // Calculate final rates
        $final_hourly_rate = $hourly_rate + $total_equipment_cost;
        $final_daily_rate = $daily_rate + ($total_equipment_cost * 8); // Assuming 8 hours per day
        $final_weekly_rate = $weekly_rate + ($total_equipment_cost * 40); // Assuming 40 hours per week
        $final_monthly_rate = $monthly_rate + ($total_equipment_cost * 160); // Assuming 160 hours per month
        
        // Store equipment information
        $equipment_info = json_encode([
            'selected_equipment' => $selected_equipment,
            'total_equipment_cost' => $total_equipment_cost,
            'base_hourly_rate' => $hourly_rate,
            'final_hourly_rate' => $final_hourly_rate
        ]);
        
        // Insert place into database
        $status = 'active';
        $stmt = $conn->prepare("INSERT INTO places (company_id, place_name, place_type, space_type, description, capacity, hourly_rate, daily_rate, weekly_rate, monthly_rate, address, city, country, postal_code, latitude, longitude, amenities, images, availability_schedule, booking_policy, cancellation_policy, is_equipment_included, equipment_info, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssiddddsssssssssssiss", $company_id, $place_name, $place_type, $space_type, $description, $capacity, $final_hourly_rate, $final_daily_rate, $final_weekly_rate, $final_monthly_rate, $address, $city, $country, $postal_code, $latitude, $longitude, $amenities_json, $images_json, $availability_json, $booking_policy, $cancellation_policy, $is_equipment_included, $equipment_info, $status);
        
        if ($stmt->execute()) {
            $place_id = $conn->insert_id;
            
            // Insert selected equipment items into place_equipment table
            if (isset($_POST['selected_equipment']) && is_array($_POST['selected_equipment'])) {
                foreach ($_POST['selected_equipment'] as $item_id) {
                    $item_id = (int)$item_id;
                    $quantity = isset($_POST['equipment_quantity'][$item_id]) ? (int)$_POST['equipment_quantity'][$item_id] : 1;
                    $custom_price = isset($_POST['equipment_custom_price'][$item_id]) ? (float)$_POST['equipment_custom_price'][$item_id] : 0;
                    
                    // Get equipment item details
                    $item_sql = "SELECT * FROM equipment_items WHERE item_id = ?";
                    $item_stmt = $conn->prepare($item_sql);
                    $item_stmt->bind_param("i", $item_id);
                    $item_stmt->execute();
                    $item_result = $item_stmt->get_result();
                    
                    if ($item_result && $item_result->num_rows > 0) {
                        $item = $item_result->fetch_assoc();
                        $item_price = $custom_price > 0 ? $custom_price : $item['standard_price'];
                        
                        $is_available = 1;
                        $place_equipment_stmt = $conn->prepare("INSERT INTO place_equipment (place_id, item_id, quantity_available, custom_price, is_available) VALUES (?, ?, ?, ?, ?)");
                        $place_equipment_stmt->bind_param("iiidi", $place_id, $item_id, $quantity, $item_price, $is_available);
                        $place_equipment_stmt->execute();
                    }
                }
            }
            
            // Insert availability schedule with pricing
            foreach ($availability as $day => $schedule) {
                if ($schedule['available']) {
                    $avail_stmt = $conn->prepare("INSERT INTO place_availability (place_id, day_of_week, start_time, end_time, hourly_rate) VALUES (?, ?, ?, ?, ?)");
                    $avail_stmt->bind_param("isssd", $place_id, $day, $schedule['start'], $schedule['end'], $schedule['hourly_rate']);
                    $avail_stmt->execute();
                }
            }
            
            $success_message = "Place posted successfully! Other companies can now book your space. Final hourly rate: $" . number_format($final_hourly_rate, 2) . " (Base: $" . number_format($hourly_rate, 2) . " + Equipment: $" . number_format($total_equipment_cost, 2) . ")";
        } else {
            $error_message = "Error posting place: " . $conn->error;
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Place - Company Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0ea5a8;
            --brand-2: #22d3ee;
            --ink: #0b1f3a;
            --muted: #475569;
            --panel: #ffffff;
            --line: #e5e7eb;
            --success: #4ade80;
            --error: #f87171;
            --warning: #fbbf24;
            --text-dark: #0f172a;
            --text-light: #475569;
            --text-white: #ffffff;
            --bg-primary: #ffffff;
            --bg-secondary: #f6f8fb;
            --border-light: #e5e7eb;
            --border-focus: #0ea5a8;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: var(--bg-secondary);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--muted);
        }

        .breadcrumb a {
            color: var(--brand);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--brand-2);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 1.1rem;
        }

        .form-container {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--line);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-label.required::after {
            content: " *";
            color: var(--error);
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--brand);
        }

        .checkbox-item label {
            font-size: 0.9rem;
            color: var(--text-dark);
            cursor: pointer;
        }

        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .availability-day {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1rem;
            border: 1px solid var(--line);
        }

        .day-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .day-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--brand);
        }

        .day-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .time-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .equipment-item {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--line);
        }

        .equipment-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .remove-equipment {
            background: var(--error);
            color: var(--text-white);
            border: none;
            border-radius: var(--radius-md);
            padding: 0.5rem;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            text-align: center;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-dark);
            border: 2px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--brand);
            color: var(--text-white);
            border-color: var(--brand);
        }

        .btn-success {
            background: var(--success);
            color: var(--text-white);
        }

        .btn-danger {
            background: var(--error);
            color: var(--text-white);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .file-upload {
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.05);
        }

        .file-upload.dragover {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.1);
        }

        /* Pricing Summary Styles */
        .pricing-summary {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: var(--radius-lg);
            border: 2px solid var(--brand);
        }

        .pricing-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .pricing-card h4 {
            margin: 0 0 1rem 0;
            color: var(--brand);
            font-size: 1.1rem;
            font-weight: 700;
        }

        .pricing-breakdown {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .equipment-costs {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .equipment-cost-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.25rem 0;
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .total-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0 0 0;
            border-top: 2px solid var(--brand);
            margin-top: 0.5rem;
            font-size: 1.1rem;
        }

        /* Equipment Selection Styles */
        .equipment-selection {
            margin-bottom: 2rem;
        }

        .equipment-selection h4 {
            margin: 0 0 1rem 0;
            color: var(--text-dark);
            font-size: 1.1rem;
            font-weight: 600;
        }

        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .equipment-option {
            position: relative;
        }

        .equipment-option input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .equipment-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
            background: var(--bg-primary);
            text-align: center;
            gap: 0.5rem;
        }

        .equipment-option label:hover {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.05);
        }

        .equipment-option input[type="checkbox"]:checked + label {
            border-color: var(--brand);
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
        }

        .equipment-option label i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .equipment-option label span:first-of-type {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .equipment-cost {
            font-size: 0.8rem;
            color: var(--success);
            font-weight: 600;
        }
        
        .priority-badge {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
        }
        
        .priority-required {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .priority-high {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fed7aa;
        }
        
        .priority-medium {
            background: #f0f9ff;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }
        
        .priority-low {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .recommendation-item:hover {
            background: var(--brand) !important;
            color: white !important;
            border-color: var(--brand) !important;
        }

        .custom-equipment {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-light);
        }

        .custom-equipment h4 {
            margin: 0 0 1rem 0;
            color: var(--text-dark);
            font-size: 1.1rem;
            font-weight: 600;
        }

        .equipment-item {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
            border: 1px solid var(--border-light);
        }

        .equipment-item .btn-danger {
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .availability-grid {
                grid-template-columns: 1fr;
            }

            .equipment-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .pricing-breakdown {
                font-size: 0.9rem;
            }
        }
        
        /* Simple Pricing Styles */
        .simple-pricing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .pricing-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .pricing-card:hover {
            border-color: var(--brand);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .pricing-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .pricing-header i {
            font-size: 2rem;
            color: var(--brand);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .pricing-header h4 {
            margin: 0 0 0.25rem 0;
            color: var(--text-dark);
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .pricing-header p {
            margin: 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .pricing-tips {
            margin-top: 2rem;
        }
        
        .tip-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .tip-box i {
            color: #f59e0b;
            font-size: 1.2rem;
            margin-top: 0.2rem;
        }
        
        .tip-content {
            flex: 1;
        }
        
        .tip-content strong {
            color: #0284c7;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .tip-content ul {
            margin: 0.5rem 0 0 0;
            padding-left: 1.5rem;
        }
        
        .tip-content li {
            margin-bottom: 0.25rem;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .simple-pricing-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .pricing-card {
                padding: 1rem;
            }
            
            .tip-box {
                padding: 1rem;
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="company_dashboard.php">Company Portal</a>
                <i class="fas fa-chevron-right"></i>
                <span>Post a Place</span>
            </div>
            <h1 class="page-title">Post a Place</h1>
            <p class="page-subtitle">Showcase your available spaces for other companies to book</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" id="placeForm">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Information
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Place Name</label>
                            <input type="text" name="place_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Place Type</label>
                            <select name="place_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="conference_room">Conference Room</option>
                                <option value="meeting_room">Meeting Room</option>
                                <option value="workspace">Workspace</option>
                                <option value="event_space">Event Space</option>
                                <option value="laboratory">Laboratory</option>
                                <option value="training_room">Training Room</option>
                                <option value="coworking_space">Coworking Space</option>
                                <option value="office_space">Office Space</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Space Rental Type</label>
                            <select name="space_type" class="form-select" required onchange="updatePricingFields()">
                                <option value="">Select Rental Type</option>
                                <option value="short_term">Short-term Rental</option>
                                <option value="long_term">Long-term Rental</option>
                            </select>
                            <div class="help-text" id="space-type-help">
                                Choose the rental type that best fits your space's intended use.
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Training Type (for Smart Recommendations)</label>
                            <select name="training_type" class="form-select" onchange="getSmartRecommendations(this.value)">
                                <option value="">Select Training Type (Optional)</option>
                                <option value="technology_training">Technology Training</option>
                                <option value="medical_training">Medical Training</option>
                                <option value="creative_training">Creative Training</option>
                                <option value="business_training">Business Training</option>
                                <option value="workshop">Workshop</option>
                                <option value="conference">Conference</option>
                                <option value="seminar">Seminar</option>
                                <option value="presentation">Presentation</option>
                            </select>
                            <div class="help-text">
                                Select a training type to get smart equipment recommendations based on your space's intended use.
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Capacity</label>
                            <input type="number" name="capacity" class="form-input" min="1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Equipment Included</label>
                            <div class="checkbox-item">
                                <input type="checkbox" name="is_equipment_included" id="is_equipment_included">
                                <label for="is_equipment_included">This place includes equipment</label>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label required">Description</label>
                            <textarea name="description" class="form-textarea" required placeholder="Describe your place, its features, and what makes it special..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Location Information -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Location Information
                    </h3>
                    <?php if ($primary_location): ?>
                        <div class="location-info" style="background: #f0f9ff; border: 1px solid #0ea5a8; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-info-circle" style="color: #0ea5a8;"></i>
                                <strong style="color: #0ea5a8;">Auto-filled from your primary location</strong>
                            </div>
                            <p style="color: #475569; font-size: 0.9rem; margin: 0;">
                                The location fields below are pre-filled with your primary location information. 
                                You can modify them if this place is at a different location.
                            </p>
                        </div>
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label required">Address</label>
                            <input type="text" name="address" class="form-input" 
                                   value="<?php echo $primary_location ? htmlspecialchars($primary_location['address']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">City</label>
                            <input type="text" name="city" class="form-input" 
                                   value="<?php echo $primary_location ? htmlspecialchars($primary_location['city']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Country</label>
                            <input type="text" name="country" class="form-input" 
                                   value="<?php echo $primary_location ? htmlspecialchars($primary_location['country']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code (Optional)</label>
                            <input type="text" name="postal_code" class="form-input" 
                                   value="<?php echo $primary_location ? htmlspecialchars($primary_location['postal_code']) : ''; ?>" 
                                   placeholder="Enter postal code">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Latitude (Optional)</label>
                            <input type="number" name="latitude" class="form-input" step="any" placeholder="e.g., 40.7128">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Longitude (Optional)</label>
                            <input type="number" name="longitude" class="form-input" step="any" placeholder="e.g., -74.0060">
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-dollar-sign"></i>
                        Pricing
                    </h3>
                    
                    <!-- Pricing Selection Message -->
                    <div id="pricing-selection-message" class="pricing-info" style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>
                            <strong style="color: #f59e0b;">Select Rental Type First</strong>
                        </div>
                        <p style="color: #475569; font-size: 0.9rem; margin: 0;">
                            Please select a rental type above to see the appropriate pricing fields.
                        </p>
                    </div>
                    
                    <!-- Short-term Rental Pricing -->
                    <div id="short-term-pricing" class="pricing-type-section" style="display: none;">
                        <div class="pricing-info" style="background: #f0f9ff; border: 1px solid #0ea5a8; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-info-circle" style="color: #0ea5a8;"></i>
                                <strong style="color: #0ea5a8;">Short-term Rental Pricing</strong>
                            </div>
                            <p style="color: #475569; font-size: 0.9rem; margin: 0;">
                                Perfect for events, workshops, and short-term use. Set hourly and daily rates.
                            </p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Hourly Rate ($)</label>
                                <input type="number" name="hourly_rate" id="base_hourly_rate" class="form-input" min="0" step="0.01" value="0">
                                <div class="help-text">Base price per hour (before equipment costs)</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Daily Rate ($)</label>
                                <input type="number" name="daily_rate" id="base_daily_rate" class="form-input" min="0" step="0.01" value="0">
                                <div class="help-text">Optional: Set a daily rate for full-day bookings</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Long-term Rental Pricing -->
                    <div id="long-term-pricing" class="pricing-type-section" style="display: none;">
                        <div class="pricing-info" style="background: #f0fdf4; border: 1px solid #059669; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-info-circle" style="color: #059669;"></i>
                                <strong style="color: #059669;">Long-term Rental Pricing</strong>
                            </div>
                            <p style="color: #475569; font-size: 0.9rem; margin: 0;">
                                Ideal for extended trainings and projects. Set weekly and monthly rates.
                            </p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Weekly Rate ($)</label>
                                <input type="number" name="weekly_rate" id="base_weekly_rate" class="form-input" min="0" step="0.01" value="0">
                                <div class="help-text">Base price per week (before equipment costs)</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Monthly Rate ($)</label>
                                <input type="number" name="monthly_rate" id="base_monthly_rate" class="form-input" min="0" step="0.01" value="0">
                                <div class="help-text">Optional: Set a monthly rate for extended bookings</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Simple Schedule Pricing -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Schedule & Pricing
                        </h3>
                        <p class="section-description">Set your availability and simple pricing for weekdays vs weekends</p>
                        
                        <div class="simple-pricing-grid">
                            <div class="pricing-card">
                                <div class="pricing-header">
                                    <i class="fas fa-briefcase"></i>
                                    <h4>Weekday Pricing</h4>
                                    <p>Monday - Friday</p>
                                </div>
                                <div class="form-group">
                                    <label for="weekday_price" class="form-label">Weekday Rate ($/hour)</label>
                                    <input type="number" id="weekday_price" name="weekday_price" class="form-input" placeholder="Enter weekday rate" min="0" step="0.01" value="<?php echo isset($_POST['weekday_price']) ? $_POST['weekday_price'] : ''; ?>">
                                    <div class="help-text">Standard rate for Monday through Friday</div>
                                </div>
                            </div>
                            
                            <div class="pricing-card">
                                <div class="pricing-header">
                                    <i class="fas fa-calendar-weekend"></i>
                                    <h4>Weekend Pricing</h4>
                                    <p>Saturday - Sunday</p>
                                </div>
                                <div class="form-group">
                                    <label for="weekend_price" class="form-label">Weekend Rate ($/hour)</label>
                                    <input type="number" id="weekend_price" name="weekend_price" class="form-input" placeholder="Enter weekend rate" min="0" step="0.01" value="<?php echo isset($_POST['weekend_price']) ? $_POST['weekend_price'] : ''; ?>">
                                    <div class="help-text">Rate for Saturday and Sunday (usually higher)</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pricing-tips">
                            <div class="tip-box">
                                <i class="fas fa-lightbulb"></i>
                                <div class="tip-content">
                                    <strong>Pricing Tips:</strong>
                                    <ul>
                                        <li>Weekend rates are typically 20-50% higher than weekdays</li>
                                        <li>Consider your location and demand when setting prices</li>
                                        <li>You can always adjust prices later</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dynamic Pricing Display -->
                    <div class="pricing-summary" id="pricing-summary" style="display: none;">
                        <div class="pricing-card">
                            <h4>Pricing Summary</h4>
                            <div class="pricing-breakdown">
                                <div class="price-item">
                                    <span id="base-rate-label">Base Rate:</span>
                                    <span id="display_base_hourly">$0.00</span>
                                </div>
                                <div class="equipment-costs" id="equipment-costs">
                                    <!-- Equipment costs will be displayed here -->
                                </div>
                                <div class="total-price">
                                    <span><strong id="total-rate-label">Total Rate:</strong></span>
                                    <span id="total_hourly_price"><strong>$0.00</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Amenities -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-star"></i>
                        Amenities
                    </h3>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="wifi" id="wifi">
                            <label for="wifi">WiFi</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="parking" id="parking">
                            <label for="parking">Parking</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="air_conditioning" id="air_conditioning">
                            <label for="air_conditioning">Air Conditioning</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="projector" id="projector">
                            <label for="projector">Projector</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="whiteboard" id="whiteboard">
                            <label for="whiteboard">Whiteboard</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="kitchen" id="kitchen">
                            <label for="kitchen">Kitchen</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="restroom" id="restroom">
                            <label for="restroom">Restroom</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="security" id="security">
                            <label for="security">Security</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="accessibility" id="accessibility">
                            <label for="accessibility">Accessibility</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="amenities[]" value="catering" id="catering">
                            <label for="catering">Catering Available</label>
                        </div>
                    </div>
                </div>

                <!-- Equipment -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-tools"></i>
                        Equipment & Facilities
                    </h3>
                    <div class="help-text" style="margin-bottom: 1rem;">
                        Select equipment to add to your place. Each equipment type has a predefined additional cost that will be automatically added to your base price.
                    </div>
                    
                    <!-- Smart Equipment System -->
                    <div class="equipment-selection">
                        <h4>Smart Equipment Selection</h4>
                        <p class="help-text">Select equipment to include with your space. Prices will be automatically calculated based on your rental type.</p>
                        
                        <!-- Smart Recommendations Section -->
                        <div id="smart-recommendations" style="display: none; margin-bottom: 2rem; padding: 1.5rem; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 2px solid #0ea5a8; border-radius: var(--radius-lg);">
                            <h5 style="display: flex; align-items: center; gap: 0.5rem; margin: 0 0 1rem 0; color: var(--brand);">
                                <i class="fas fa-lightbulb"></i>
                                Smart Recommendations
                            </h5>
                            <p style="margin: 0 0 1rem 0; color: var(--text-light); font-size: 0.9rem;">
                                Based on your training type, we recommend the following equipment categories:
                            </p>
                            <div id="recommendations-content">
                                <!-- Recommendations will be loaded here -->
                            </div>
                            <div style="margin-top: 1rem;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="applyAllRecommendations()">
                                    <i class="fas fa-magic"></i>
                                    Apply All Recommendations
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="clearRecommendations()">
                                    <i class="fas fa-times"></i>
                                    Clear Recommendations
                                </button>
                            </div>
                        </div>
                        
                        <!-- Equipment Categories -->
                        <?php foreach ($equipment_categories as $category): ?>
                            <div class="equipment-category-section">
                                <h5 style="display: flex; align-items: center; gap: 0.5rem; margin: 1.5rem 0 1rem 0; color: var(--text-dark);">
                                    <i class="<?php echo $category['icon_class']; ?>" style="color: var(--brand);"></i>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </h5>
                                <div class="equipment-grid">
                                    <?php 
                                    $category_items = array_filter($equipment_items, function($item) use ($category) {
                                        return $item['category_id'] == $category['category_id'];
                                    });
                                    foreach ($category_items as $item): 
                                    ?>
                                        <div class="equipment-option">
                                            <input type="checkbox" 
                                                   id="equipment_<?php echo $item['item_id']; ?>" 
                                                   name="selected_equipment[]" 
                                                   value="<?php echo $item['item_id']; ?>" 
                                                   data-cost="<?php echo $item['standard_price']; ?>" 
                                                   data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                   data-unit="<?php echo $item['unit_type']; ?>"
                                                   onchange="toggleEquipmentDetails(this)">
                                            <label for="equipment_<?php echo $item['item_id']; ?>">
                                                <i class="<?php echo $item['icon_class']; ?>"></i>
                                                <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                                                <span class="equipment-cost">$<?php echo number_format($item['standard_price'], 2); ?>/<?php echo str_replace('_', ' ', $item['unit_type']); ?></span>
                                            </label>
                                            <div class="equipment-details" style="display: none; margin-top: 0.5rem; padding: 0.5rem; background: var(--bg-secondary); border-radius: var(--radius-sm);">
                                                <div style="display: flex; gap: 1rem; align-items: center;">
                                                    <label style="font-size: 0.8rem;">Quantity:</label>
                                                    <input type="number" 
                                                           name="equipment_quantity[<?php echo $item['item_id']; ?>]" 
                                                           class="quantity-input" 
                                                           min="1" 
                                                           value="1" 
                                                           style="width: 60px; padding: 0.25rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                                                    <label style="font-size: 0.8rem;">Custom Price:</label>
                                                    <input type="number" 
                                                           name="equipment_custom_price[<?php echo $item['item_id']; ?>]" 
                                                           class="price-input" 
                                                           step="0.01" 
                                                           min="0" 
                                                           placeholder="<?php echo $item['standard_price']; ?>"
                                                           style="width: 80px; padding: 0.25rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                                                </div>
                                                <?php if (isset($item['item_description']) && !empty($item['item_description'])): ?>
                                                    <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem;">
                                                        <?php echo htmlspecialchars($item['item_description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Custom Equipment (if any) -->
                        <?php if (!empty($custom_equipment_items)): ?>
                            <div class="equipment-category-section">
                                <h5 style="display: flex; align-items: center; gap: 0.5rem; margin: 1.5rem 0 1rem 0; color: var(--text-dark);">
                                    <i class="fas fa-tools" style="color: var(--brand);"></i>
                                    Your Custom Equipment
                                </h5>
                                <div class="equipment-grid">
                                    <?php foreach ($custom_equipment_items as $item): ?>
                                        <div class="equipment-option">
                                            <input type="checkbox" 
                                                   id="equipment_<?php echo $item['item_id']; ?>" 
                                                   name="selected_equipment[]" 
                                                   value="<?php echo $item['item_id']; ?>" 
                                                   data-cost="<?php echo $item['standard_price']; ?>" 
                                                   data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                   data-unit="<?php echo $item['unit_type']; ?>"
                                                   onchange="toggleEquipmentDetails(this)">
                                            <label for="equipment_<?php echo $item['item_id']; ?>">
                                                <i class="<?php echo $item['icon_class']; ?>"></i>
                                                <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                                                <span class="equipment-cost">$<?php echo number_format($item['standard_price'], 2); ?>/<?php echo str_replace('_', ' ', $item['unit_type']); ?></span>
                                            </label>
                                            <div class="equipment-details" style="display: none; margin-top: 0.5rem; padding: 0.5rem; background: var(--bg-secondary); border-radius: var(--radius-sm);">
                                                <div style="display: flex; gap: 1rem; align-items: center;">
                                                    <label style="font-size: 0.8rem;">Quantity:</label>
                                                    <input type="number" 
                                                           name="equipment_quantity[<?php echo $item['item_id']; ?>]" 
                                                           class="quantity-input" 
                                                           min="1" 
                                                           value="1" 
                                                           style="width: 60px; padding: 0.25rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                                                    <label style="font-size: 0.8rem;">Custom Price:</label>
                                                    <input type="number" 
                                                           name="equipment_custom_price[<?php echo $item['item_id']; ?>]" 
                                                           class="price-input" 
                                                           step="0.01" 
                                                           min="0" 
                                                           placeholder="<?php echo $item['standard_price']; ?>"
                                                           style="width: 80px; padding: 0.25rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                                                </div>
                                                <?php if (isset($item['item_description']) && !empty($item['item_description'])): ?>
                                                    <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem;">
                                                        <?php echo htmlspecialchars($item['item_description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Equipment Packages -->
                        <?php if (!empty($equipment_packages)): ?>
                            <div class="equipment-category-section">
                                <h5 style="display: flex; align-items: center; gap: 0.5rem; margin: 1.5rem 0 1rem 0; color: var(--text-dark);">
                                    <i class="fas fa-box" style="color: var(--brand);"></i>
                                    Equipment Packages
                                </h5>
                                <div class="equipment-grid">
                                    <?php foreach ($equipment_packages as $package): ?>
                                        <div class="equipment-option package-option">
                                            <input type="checkbox" 
                                                   id="package_<?php echo $package['package_id']; ?>" 
                                                   name="selected_packages[]" 
                                                   value="<?php echo $package['package_id']; ?>" 
                                                   data-cost="<?php echo $package['total_price']; ?>" 
                                                   data-name="<?php echo htmlspecialchars($package['package_name']); ?>"
                                                   onchange="calculateTotalPrice()">
                                            <label for="package_<?php echo $package['package_id']; ?>">
                                                <i class="fas fa-box"></i>
                                                <span><?php echo htmlspecialchars($package['package_name']); ?></span>
                                                <span class="equipment-cost">$<?php echo number_format($package['total_price'], 2); ?><?php if ($package['discount_percentage'] > 0): ?> (<?php echo $package['discount_percentage']; ?>% off)<?php endif; ?></span>
                                            </label>
                                            <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem;">
                                                <?php echo htmlspecialchars($package['package_description']); ?>
                                                <br><strong><?php echo $package['item_count']; ?></strong> items included
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Custom Equipment -->
                    <div class="custom-equipment">
                        <h4>Custom Equipment</h4>
                        <div id="equipment-container">
                            <div class="equipment-item">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Equipment Name</label>
                                        <input type="text" name="equipment_name[]" class="form-input" placeholder="e.g., 4K Projector">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Equipment Type</label>
                                        <select name="equipment_type[]" class="form-select">
                                            <option value="technology">Technology</option>
                                            <option value="furniture">Furniture</option>
                                            <option value="presentation">Presentation</option>
                                            <option value="catering">Catering</option>
                                            <option value="safety">Safety</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" name="equipment_quantity[]" class="form-input" min="1" value="1">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Additional Cost ($/hour)</label>
                                        <input type="number" name="equipment_cost[]" class="form-input equipment-cost-input" min="0" step="0.01" value="0">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Description</label>
                                        <input type="text" name="equipment_description[]" class="form-input" placeholder="Brief description of the equipment">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeEquipment(this)">
                                    <i class="fas fa-trash"></i>
                                    Remove
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addEquipment()">
                            <i class="fas fa-plus"></i>
                            Add Custom Equipment
                        </button>
                    </div>
                </div>

                <!-- Availability Schedule -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Availability Schedule
                    </h3>
                    <div class="availability-grid">
                        <?php
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                        foreach ($days as $day): ?>
                            <div class="availability-day">
                                <div class="day-header">
                                    <input type="checkbox" name="<?php echo $day; ?>_available" class="day-checkbox" onchange="toggleDayAvailability('<?php echo $day; ?>')">
                                    <span class="day-name"><?php echo ucfirst($day); ?></span>
                                </div>
                                <div class="time-inputs" id="<?php echo $day; ?>_inputs" style="display: none;">
                                    <input type="time" name="<?php echo $day; ?>_start" class="form-input" placeholder="Start Time">
                                    <input type="time" name="<?php echo $day; ?>_end" class="form-input" placeholder="End Time">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Images -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-images"></i>
                        Images
                    </h3>
                    <div class="file-upload" onclick="document.getElementById('images').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--brand); margin-bottom: 1rem;"></i>
                        <p>Click to upload images or drag and drop</p>
                        <p style="font-size: 0.9rem; color: var(--muted);">PNG, JPG, GIF up to 10MB each</p>
                    </div>
                    <input type="file" id="images" name="images[]" multiple accept="image/*" style="display: none;" onchange="previewImages(this)">
                    <div id="image-preview" style="margin-top: 1rem;"></div>
                </div>

                <!-- Policies -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-file-contract"></i>
                        Policies
                    </h3>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">Booking Policy</label>
                            <textarea name="booking_policy" class="form-textarea" placeholder="Describe your booking requirements, advance notice needed, etc."></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Cancellation Policy</label>
                            <textarea name="cancellation_policy" class="form-textarea" placeholder="Describe your cancellation terms, refund policy, etc."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <a href="company_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Post Place
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addEquipment() {
            const container = document.getElementById('equipment-container');
            const equipmentItem = document.createElement('div');
            equipmentItem.className = 'equipment-item';
            equipmentItem.innerHTML = `
                <div class="equipment-header">
                    <button type="button" class="remove-equipment" onclick="removeEquipment(this)">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Equipment Name</label>
                        <input type="text" name="equipment_name[]" class="form-input" placeholder="e.g., 4K Projector">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Equipment Type</label>
                        <select name="equipment_type[]" class="form-select">
                            <option value="technology">Technology</option>
                            <option value="furniture">Furniture</option>
                            <option value="presentation">Presentation</option>
                            <option value="catering">Catering</option>
                            <option value="safety">Safety</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="equipment_quantity[]" class="form-input" min="1" value="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Additional Cost ($/hour)</label>
                        <input type="number" name="equipment_cost[]" class="form-input equipment-cost-input" min="0" step="0.01" value="0" onchange="calculateTotalPrice()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <input type="text" name="equipment_description[]" class="form-input" placeholder="Brief description of the equipment">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Included in Base Rate</label>
                        <div class="checkbox-item">
                            <input type="checkbox" name="equipment_included[]" checked>
                            <label>Included in base rate</label>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(equipmentItem);
        }

        function removeEquipment(button) {
            button.closest('.equipment-item').remove();
            calculateTotalPrice(); // Recalculate price when equipment is removed
        }

        function toggleDayAvailability(day) {
            const checkbox = document.querySelector(`input[name="${day}_available"]`);
            const inputs = document.getElementById(`${day}_inputs`);
            if (checkbox.checked) {
                inputs.style.display = 'grid';
            } else {
                inputs.style.display = 'none';
            }
        }

        function previewImages(input) {
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            
            if (input.files) {
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '150px';
                        img.style.height = '150px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '8px';
                        img.style.margin = '5px';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            }
        }

        // Dynamic Pricing Calculation
        function calculateTotalPrice() {
            const spaceType = document.querySelector('select[name="space_type"]').value;
            const pricingSummary = document.getElementById('pricing-summary');
            const displayBaseHourly = document.getElementById('display_base_hourly');
            const equipmentCosts = document.getElementById('equipment-costs');
            const totalHourlyPrice = document.getElementById('total_hourly_price');
            
            let baseRate = 0;
            let rateLabel = '';
            let totalEquipmentCost = 0;
            let equipmentCostItems = '';
            
            // Get base rate based on space type
            if (spaceType === 'short_term') {
                baseRate = parseFloat(document.getElementById('base_hourly_rate').value) || 0;
                rateLabel = 'Base Hourly Rate:';
            } else if (spaceType === 'long_term') {
                baseRate = parseFloat(document.getElementById('base_weekly_rate').value) || 0;
                rateLabel = 'Base Weekly Rate:';
            }
            
            // Calculate equipment costs from database items
            const selectedEquipment = document.querySelectorAll('input[name="selected_equipment[]"]:checked');
            selectedEquipment.forEach(checkbox => {
                const itemId = checkbox.value;
                const baseCost = parseFloat(checkbox.getAttribute('data-cost')) || 0;
                const name = checkbox.getAttribute('data-name');
                const unit = checkbox.getAttribute('data-unit');
                
                // Get quantity and custom price
                const quantityInput = document.querySelector(`input[name="equipment_quantity[${itemId}]"]`);
                const customPriceInput = document.querySelector(`input[name="equipment_custom_price[${itemId}]"]`);
                
                const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
                const customPrice = customPriceInput ? parseFloat(customPriceInput.value) || 0 : 0;
                const itemPrice = customPrice > 0 ? customPrice : baseCost;
                const itemTotalCost = itemPrice * quantity;
                
                totalEquipmentCost += itemTotalCost;
                const costUnit = spaceType === 'short_term' ? '/hour' : '/week';
                equipmentCostItems += `
                    <div class="equipment-cost-item">
                        <span>${name} (${quantity}x):</span>
                        <span>+$${itemTotalCost.toFixed(2)}${costUnit}</span>
                    </div>
                `;
            });
            
            // Calculate package costs
            const selectedPackages = document.querySelectorAll('input[name="selected_packages[]"]:checked');
            selectedPackages.forEach(checkbox => {
                const cost = parseFloat(checkbox.getAttribute('data-cost')) || 0;
                const name = checkbox.getAttribute('data-name');
                totalEquipmentCost += cost;
                const costUnit = spaceType === 'short_term' ? '/hour' : '/week';
                equipmentCostItems += `
                    <div class="equipment-cost-item">
                        <span>${name} (Package):</span>
                        <span>+$${cost.toFixed(2)}${costUnit}</span>
                    </div>
                `;
            });
            
            // Calculate custom equipment costs
            const customEquipmentCosts = document.querySelectorAll('.equipment-cost-input');
            customEquipmentCosts.forEach(input => {
                const cost = parseFloat(input.value) || 0;
                const equipmentName = input.closest('.equipment-item').querySelector('input[name="equipment_name[]"]').value || 'Custom Equipment';
                if (cost > 0) {
                    totalEquipmentCost += cost;
                    const costUnit = spaceType === 'short_term' ? '/hour' : '/week';
                    equipmentCostItems += `
                        <div class="equipment-cost-item">
                            <span>${equipmentName}:</span>
                            <span>+$${cost.toFixed(2)}${costUnit}</span>
                        </div>
                    `;
                }
            });
            
            // Update display
            displayBaseHourly.textContent = `$${baseRate.toFixed(2)}`;
            equipmentCosts.innerHTML = equipmentCostItems;
            
            const totalPrice = baseRate + totalEquipmentCost;
            const totalUnit = spaceType === 'short_term' ? 'hour' : 'week';
            totalHourlyPrice.innerHTML = `<strong>$${totalPrice.toFixed(2)}/${totalUnit}</strong>`;
            
            // Update the rate labels
            const baseRateLabel = document.getElementById('base-rate-label');
            const totalRateLabel = document.getElementById('total-rate-label');
            
            if (baseRateLabel) {
                baseRateLabel.textContent = rateLabel;
            }
            
            if (totalRateLabel) {
                const totalLabel = spaceType === 'short_term' ? 'Total Hourly Rate:' : 'Total Weekly Rate:';
                totalRateLabel.textContent = totalLabel;
            }
            
            // Show/hide pricing summary
            if (baseRate > 0 || totalEquipmentCost > 0) {
                pricingSummary.style.display = 'block';
            } else {
                pricingSummary.style.display = 'none';
            }
        }

        // Update pricing fields based on space type
        function updatePricingFields() {
            const spaceType = document.querySelector('select[name="space_type"]').value;
            const shortTermPricing = document.getElementById('short-term-pricing');
            const longTermPricing = document.getElementById('long-term-pricing');
            const pricingSelectionMessage = document.getElementById('pricing-selection-message');
            const spaceTypeHelp = document.getElementById('space-type-help');
            
            // Hide all pricing sections first
            shortTermPricing.style.display = 'none';
            longTermPricing.style.display = 'none';
            pricingSelectionMessage.style.display = 'none';
            
            if (spaceType === 'short_term') {
                shortTermPricing.style.display = 'block';
                spaceTypeHelp.innerHTML = 'Perfect for events, workshops, and short-term use. Set hourly and daily rates.';
            } else if (spaceType === 'long_term') {
                longTermPricing.style.display = 'block';
                spaceTypeHelp.innerHTML = 'Ideal for extended trainings and projects. Set weekly and monthly rates.';
            } else {
                pricingSelectionMessage.style.display = 'block';
                spaceTypeHelp.innerHTML = 'Choose the rental type that best fits your space\'s intended use.';
            }
            
            // Recalculate pricing
            calculateTotalPrice();
        }

        function toggleEquipmentDetails(checkbox) {
            const equipmentOption = checkbox.closest('.equipment-option');
            const detailsDiv = equipmentOption.querySelector('.equipment-details');
            
            if (checkbox.checked) {
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
            
            calculateTotalPrice();
        }
        
        function getSmartRecommendations(trainingType) {
            if (!trainingType) {
                document.getElementById('smart-recommendations').style.display = 'none';
                return;
            }
            
            // Show loading state
            const recommendationsContent = document.getElementById('recommendations-content');
            recommendationsContent.innerHTML = '<div style="text-align: center; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i> Loading recommendations...</div>';
            document.getElementById('smart-recommendations').style.display = 'block';
            
            // Fetch recommendations
            fetch(`get_equipment_recommendations.php?training_type=${encodeURIComponent(trainingType)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        recommendationsContent.innerHTML = `<div style="color: var(--error); text-align: center; padding: 1rem;">${data.error}</div>`;
                        return;
                    }
                    
                    let html = '';
                    if (data.recommendations && data.recommendations.length > 0) {
                        data.recommendations.forEach(rec => {
                            const priorityClass = rec.is_required ? 'required' : rec.priority_level === 1 ? 'high' : rec.priority_level === 2 ? 'medium' : 'low';
                            const priorityText = rec.is_required ? 'Required' : rec.priority_level === 1 ? 'Highly Recommended' : rec.priority_level === 2 ? 'Recommended' : 'Optional';
                            
                            html += `
                                <div class="recommendation-category" style="margin-bottom: 1rem; padding: 1rem; background: white; border-radius: var(--radius-md); border-left: 4px solid var(--brand);">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                        <i class="${rec.icon_class}" style="color: var(--brand);"></i>
                                        <strong>${rec.category_name}</strong>
                                        <span class="priority-badge priority-${priorityClass}" style="padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">
                                            ${priorityText}
                                        </span>
                                    </div>
                                    <p style="margin: 0 0 0.5rem 0; color: var(--text-light); font-size: 0.8rem;">${rec.category_description}</p>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                        ${rec.items.map(item => `
                                            <button type="button" class="recommendation-item" 
                                                    data-item-id="${item.item_id}" 
                                                    data-category-id="${rec.category_id}"
                                                    onclick="selectRecommendedItem(${item.item_id})"
                                                    style="padding: 0.25rem 0.5rem; background: var(--bg-secondary); border: 1px solid var(--border-light); border-radius: var(--radius-sm); font-size: 0.8rem; cursor: pointer; transition: var(--transition);">
                                                ${item.item_name} - $${parseFloat(item.standard_price).toFixed(2)}/${item.unit_type.replace('_', ' ')}
                                            </button>
                                        `).join('')}
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        html = '<div style="text-align: center; padding: 1rem; color: var(--text-light);">No specific recommendations found for this training type.</div>';
                    }
                    
                    recommendationsContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error fetching recommendations:', error);
                    recommendationsContent.innerHTML = '<div style="color: var(--error); text-align: center; padding: 1rem;">Error loading recommendations. Please try again.</div>';
                });
        }
        
        function selectRecommendedItem(itemId) {
            const checkbox = document.getElementById(`equipment_${itemId}`);
            if (checkbox) {
                checkbox.checked = true;
                toggleEquipmentDetails(checkbox);
                calculateTotalPrice();
                
                // Visual feedback
                const button = event.target;
                button.style.background = 'var(--success)';
                button.style.color = 'white';
                button.style.borderColor = 'var(--success)';
                setTimeout(() => {
                    button.style.background = 'var(--bg-secondary)';
                    button.style.color = 'var(--text-dark)';
                    button.style.borderColor = 'var(--border-light)';
                }, 1000);
            }
        }
        
        function applyAllRecommendations() {
            const recommendationItems = document.querySelectorAll('.recommendation-item');
            recommendationItems.forEach(item => {
                const itemId = item.getAttribute('data-item-id');
                selectRecommendedItem(itemId);
            });
        }
        
        function clearRecommendations() {
            const recommendationItems = document.querySelectorAll('.recommendation-item');
            recommendationItems.forEach(item => {
                const itemId = item.getAttribute('data-item-id');
                const checkbox = document.getElementById(`equipment_${itemId}`);
                if (checkbox) {
                    checkbox.checked = false;
                    toggleEquipmentDetails(checkbox);
                }
            });
            calculateTotalPrice();
        }

        // Add event listeners for dynamic pricing
        document.addEventListener('DOMContentLoaded', function() {
            // Base rate inputs
            document.getElementById('base_hourly_rate').addEventListener('input', calculateTotalPrice);
            document.getElementById('base_daily_rate').addEventListener('input', calculateTotalPrice);
            document.getElementById('base_weekly_rate').addEventListener('input', calculateTotalPrice);
            document.getElementById('base_monthly_rate').addEventListener('input', calculateTotalPrice);
            
            // Equipment checkboxes
            const equipmentCheckboxes = document.querySelectorAll('input[name="selected_equipment[]"]');
            equipmentCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', calculateTotalPrice);
            });
            
            // Package checkboxes
            const packageCheckboxes = document.querySelectorAll('input[name="selected_packages[]"]');
            packageCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', calculateTotalPrice);
            });
            
            // Equipment quantity and price inputs
            const equipmentQuantityInputs = document.querySelectorAll('input[name^="equipment_quantity["]');
            equipmentQuantityInputs.forEach(input => {
                input.addEventListener('input', calculateTotalPrice);
            });
            
            const equipmentPriceInputs = document.querySelectorAll('input[name^="equipment_custom_price["]');
            equipmentPriceInputs.forEach(input => {
                input.addEventListener('input', calculateTotalPrice);
            });
            
            // Custom equipment cost inputs
            const customCostInputs = document.querySelectorAll('.equipment-cost-input');
            customCostInputs.forEach(input => {
                input.addEventListener('input', calculateTotalPrice);
            });
            
            // Auto-suggest weekend pricing when weekday pricing changes
            document.getElementById('weekday_price').addEventListener('input', function() {
                const weekdayPrice = parseFloat(this.value) || 0;
                if (weekdayPrice > 0) {
                    const weekendPrice = Math.round(weekdayPrice * 1.3); // 30% higher for weekends
                    document.getElementById('weekend_price').value = weekendPrice;
                }
            });
            
            // Auto-suggest weekday pricing when base hourly rate changes
            document.getElementById('base_hourly_rate').addEventListener('input', function() {
                const baseRate = parseFloat(this.value) || 0;
                if (baseRate > 0) {
                    document.getElementById('weekday_price').value = baseRate;
                    document.getElementById('weekend_price').value = Math.round(baseRate * 1.3);
                }
            });
        });

        // File upload drag and drop
        const fileUpload = document.querySelector('.file-upload');
        
        fileUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUpload.classList.add('dragover');
        });
        
        fileUpload.addEventListener('dragleave', () => {
            fileUpload.classList.remove('dragover');
        });
        
        fileUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUpload.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            const input = document.getElementById('images');
            input.files = files;
            previewImages(input);
        });
    </script>
</body>
</html>


