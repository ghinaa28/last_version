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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    quantity INT DEFAULT 1
)";
$conn->query($create_package_items);

$create_place_equipment = "CREATE TABLE IF NOT EXISTS place_equipment (
    place_equipment_id INT AUTO_INCREMENT PRIMARY KEY,
    place_id INT,
    item_id INT,
    quantity_available INT DEFAULT 1,
    custom_price DECIMAL(10,2) NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$result = $conn->query($create_place_equipment);

// Force create place_equipment table if it doesn't exist
$force_create = "CREATE TABLE IF NOT EXISTS place_equipment (
    place_equipment_id INT AUTO_INCREMENT PRIMARY KEY,
    place_id INT,
    item_id INT,
    quantity_available INT DEFAULT 1,
    custom_price DECIMAL(10,2) NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($force_create);

// Verify table was created successfully
$check_table = "SHOW TABLES LIKE 'place_equipment'";
$table_result = $conn->query($check_table);
if ($table_result->num_rows == 0) {
    // If table still doesn't exist, try to create it with a simpler structure
    $create_simple = "CREATE TABLE place_equipment (
        place_equipment_id INT AUTO_INCREMENT PRIMARY KEY,
        place_id INT,
        item_id INT,
        quantity_available INT DEFAULT 1,
        custom_price DECIMAL(10,2) NULL,
        is_available BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_simple);
}

$create_place_packages = "CREATE TABLE IF NOT EXISTS place_packages (
    place_package_id INT AUTO_INCREMENT PRIMARY KEY,
    place_id INT,
    package_id INT,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

// Get filter parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$place_type = isset($_GET['place_type']) ? $conn->real_escape_string($_GET['place_type']) : '';
$space_type = isset($_GET['space_type']) ? $conn->real_escape_string($_GET['space_type']) : '';
$city = isset($_GET['city']) ? $conn->real_escape_string($_GET['city']) : '';
$min_capacity = isset($_GET['min_capacity']) ? (int)$_GET['min_capacity'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$amenities = isset($_GET['amenities']) ? $_GET['amenities'] : [];

// Build the query
$where_conditions = ["p.status = 'active'", "p.company_id != ?"]; // Exclude own company's places
$params = [$company_id];
$param_types = "i";

if (!empty($search)) {
    $where_conditions[] = "(p.place_name LIKE ? OR p.description LIKE ? OR p.city LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($place_type)) {
    $where_conditions[] = "p.place_type = ?";
    $params[] = $place_type;
    $param_types .= "s";
}

if (!empty($space_type)) {
    $where_conditions[] = "p.space_type = ?";
    $params[] = $space_type;
    $param_types .= "s";
}

if (!empty($city)) {
    $where_conditions[] = "p.city LIKE ?";
    $params[] = "%$city%";
    $param_types .= "s";
}

if ($min_capacity > 0) {
    $where_conditions[] = "p.capacity >= ?";
    $params[] = $min_capacity;
    $param_types .= "i";
}

if ($max_price > 0) {
    $where_conditions[] = "p.hourly_rate <= ?";
    $params[] = $max_price;
    $param_types .= "d";
}

$where_clause = implode(" AND ", $where_conditions);

// Get places
$places_sql = "SELECT 
    p.*,
    c.company_name,
    c.logo_path,
    AVG(pr.rating) as average_rating,
    COUNT(pr.review_id) as total_reviews,
    COUNT(pb.booking_id) as total_bookings
    FROM places p
    JOIN companies c ON p.company_id = c.company_id
    LEFT JOIN place_reviews pr ON p.place_id = pr.place_id
    LEFT JOIN place_bookings pb ON p.place_id = pb.place_id
    WHERE $where_clause
    GROUP BY p.place_id
    ORDER BY p.created_at DESC";

$stmt = $conn->prepare($places_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$places = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Filter by amenities if specified
if (!empty($amenities)) {
    $filtered_places = [];
    foreach ($places as $place) {
        $place_amenities = json_decode($place['amenities'], true) ?: [];
        $has_all_amenities = true;
        foreach ($amenities as $amenity) {
            if (!in_array($amenity, $place_amenities)) {
                $has_all_amenities = false;
                break;
            }
        }
        if ($has_all_amenities) {
            $filtered_places[] = $place;
        }
    }
    $places = $filtered_places;
}

// Get unique cities for filter dropdown
$cities_sql = "SELECT DISTINCT city FROM places WHERE status = 'active' ORDER BY city";
$cities_result = $conn->query($cities_sql);
$cities = $cities_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Places - Company Portal</title>
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
            max-width: 1200px;
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

        .filters-section {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-input, .form-select {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .amenities-section {
            margin-top: 1rem;
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .results-count {
            color: var(--muted);
            font-weight: 600;
        }

        .places-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .place-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .place-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .place-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--bg-secondary);
        }

        .place-content {
            padding: 1.5rem;
        }

        .place-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .place-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .place-type {
            color: var(--muted);
            font-size: 0.9rem;
            text-transform: capitalize;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .company-logo {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }

        .company-name {
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: 500;
        }

        .place-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
        }

        .place-description {
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .place-amenities {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .amenity-tag {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .place-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-weight: 700;
            color: var(--brand);
            font-size: 1.1rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .place-actions {
            display: flex;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--panel);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--muted);
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .places-grid {
                grid-template-columns: 1fr;
            }

            .results-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
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
                <span>Browse Places</span>
            </div>
            <h1 class="page-title">Browse Places</h1>
            <p class="page-subtitle">Find and book places from other companies</p>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" id="filtersForm">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-input" placeholder="Search places..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Place Type</label>
                        <select name="place_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="conference_room" <?php echo $place_type === 'conference_room' ? 'selected' : ''; ?>>Conference Room</option>
                            <option value="meeting_room" <?php echo $place_type === 'meeting_room' ? 'selected' : ''; ?>>Meeting Room</option>
                            <option value="workspace" <?php echo $place_type === 'workspace' ? 'selected' : ''; ?>>Workspace</option>
                            <option value="event_space" <?php echo $place_type === 'event_space' ? 'selected' : ''; ?>>Event Space</option>
                            <option value="laboratory" <?php echo $place_type === 'laboratory' ? 'selected' : ''; ?>>Laboratory</option>
                            <option value="training_room" <?php echo $place_type === 'training_room' ? 'selected' : ''; ?>>Training Room</option>
                            <option value="coworking_space" <?php echo $place_type === 'coworking_space' ? 'selected' : ''; ?>>Coworking Space</option>
                            <option value="office_space" <?php echo $place_type === 'office_space' ? 'selected' : ''; ?>>Office Space</option>
                            <option value="other" <?php echo $place_type === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rental Type</label>
                        <select name="space_type" class="form-select">
                            <option value="">All Rental Types</option>
                            <option value="short_term" <?php echo $space_type === 'short_term' ? 'selected' : ''; ?>>Short-term Rental</option>
                            <option value="long_term" <?php echo $space_type === 'long_term' ? 'selected' : ''; ?>>Long-term Rental</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <select name="city" class="form-select">
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $city_option): ?>
                                <option value="<?php echo htmlspecialchars($city_option['city']); ?>" <?php echo $city === $city_option['city'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city_option['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Capacity</label>
                        <input type="number" name="min_capacity" class="form-input" min="1" value="<?php echo $min_capacity; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Hourly Rate ($)</label>
                        <input type="number" name="max_price" class="form-input" min="0" step="0.01" value="<?php echo $max_price; ?>">
                    </div>
                </div>

                <div class="amenities-section">
                    <label class="form-label">Amenities</label>
                    <div class="amenities-grid">
                        <?php
                        $all_amenities = ['wifi', 'parking', 'air_conditioning', 'projector', 'whiteboard', 'kitchen', 'restroom', 'security', 'accessibility', 'catering'];
                        foreach ($all_amenities as $amenity): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="amenities[]" value="<?php echo $amenity; ?>" id="amenity_<?php echo $amenity; ?>" <?php echo in_array($amenity, $amenities) ? 'checked' : ''; ?>>
                                <label for="amenity_<?php echo $amenity; ?>"><?php echo ucwords(str_replace('_', ' ', $amenity)); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                    <a href="browse_places.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Results -->
        <div class="results-header">
            <div class="results-count">
                <?php echo count($places); ?> place<?php echo count($places) !== 1 ? 's' : ''; ?> found
            </div>
            <a href="manage_places.php" class="btn btn-secondary">
                <i class="fas fa-cog"></i>
                Manage My Places
            </a>
        </div>

        <!-- Places Grid -->
        <?php if (empty($places)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No Places Found</h3>
                <p>Try adjusting your search criteria or browse all available places.</p>
                <a href="browse_places.php" class="btn btn-primary">
                    <i class="fas fa-refresh"></i>
                    View All Places
                </a>
            </div>
        <?php else: ?>
            <div class="places-grid">
                <?php foreach ($places as $place): ?>
                    <div class="place-card">
                        <?php 
                        $images = json_decode($place['images'], true);
                        $first_image = !empty($images) ? $images[0] : null;
                        ?>
                        <?php if ($first_image): ?>
                            <img src="<?php echo htmlspecialchars($first_image); ?>" alt="<?php echo htmlspecialchars($place['place_name']); ?>" class="place-image">
                        <?php else: ?>
                            <div class="place-image" style="display: flex; align-items: center; justify-content: center; color: var(--muted);">
                                <i class="fas fa-building" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="place-content">
                            <div class="place-header">
                                <div>
                                    <h3 class="place-title"><?php echo htmlspecialchars($place['place_name']); ?></h3>
                                    <p class="place-type"><?php echo str_replace('_', ' ', $place['place_type']); ?></p>
                                    <div class="space-type-badge" style="display: inline-block; margin-top: 0.5rem;">
                                        <span style="background: <?php echo $place['space_type'] === 'short_term' ? '#0ea5a8' : '#059669'; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">
                                            <?php echo $place['space_type'] === 'short_term' ? 'Short-term' : 'Long-term'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--brand);">
                                        $<?php echo number_format($place['space_type'] === 'short_term' ? $place['hourly_rate'] : $place['weekly_rate'], 2); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--muted);">
                                        per <?php echo $place['space_type'] === 'short_term' ? 'hour' : 'week'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="company-info">
                                <?php if ($place['logo_path']): ?>
                                    <img src="<?php echo htmlspecialchars($place['logo_path']); ?>" alt="Company Logo" class="company-logo">
                                <?php else: ?>
                                    <div class="company-logo" style="background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem;">
                                        <?php echo strtoupper(substr($place['company_name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="company-name"><?php echo htmlspecialchars($place['company_name']); ?></span>
                            </div>

                            <div class="place-info">
                                <div class="info-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $place['capacity']; ?> people</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($place['city']); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-star"></i>
                                    <span><?php echo $place['average_rating'] ? number_format($place['average_rating'], 1) : 'No ratings'; ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo $place['total_bookings']; ?> bookings</span>
                                </div>
                            </div>

                            <p class="place-description"><?php echo htmlspecialchars(substr($place['description'], 0, 120)) . (strlen($place['description']) > 120 ? '...' : ''); ?></p>

                            <?php 
                            $place_amenities = json_decode($place['amenities'], true) ?: [];
                            if (!empty($place_amenities)): ?>
                                <div class="place-amenities">
                                    <?php foreach (array_slice($place_amenities, 0, 4) as $amenity): ?>
                                        <span class="amenity-tag"><?php echo ucwords(str_replace('_', ' ', $amenity)); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($place_amenities) > 4): ?>
                                        <span class="amenity-tag">+<?php echo count($place_amenities) - 4; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Equipment Information -->
                            <?php
                            // Check if place_equipment table exists and has the required columns
                            $check_table = "SHOW TABLES LIKE 'place_equipment'";
                            $table_exists = $conn->query($check_table);
                            
                            $equipment_categories = [];
                            
                            if ($table_exists && $table_exists->num_rows > 0) {
                                // Check if the table has the required columns
                                $check_columns = "SHOW COLUMNS FROM place_equipment LIKE 'item_id'";
                                $column_exists = $conn->query($check_columns);
                                
                                if ($column_exists && $column_exists->num_rows > 0) {
                                    try {
                                        // Get equipment categories for this place
                                        $equipment_categories_sql = "SELECT DISTINCT ec.category_name, ec.icon_class, COUNT(pe.item_id) as equipment_count
                                                                     FROM place_equipment pe
                                                                     JOIN equipment_items ei ON pe.item_id = ei.item_id
                                                                     JOIN equipment_categories ec ON ei.category_id = ec.category_id
                                                                     WHERE pe.place_id = ? AND pe.is_available = 1
                                                                     GROUP BY ec.category_id, ec.category_name, ec.icon_class
                                                                     ORDER BY equipment_count DESC";
                                        $equipment_stmt = $conn->prepare($equipment_categories_sql);
                                        if ($equipment_stmt) {
                                            $equipment_stmt->bind_param("i", $place['place_id']);
                                            $equipment_stmt->execute();
                                            $equipment_categories = $equipment_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        }
                                    } catch (Exception $e) {
                                        // If there's any error, just set empty array to prevent fatal error
                                        $equipment_categories = [];
                                    }
                                }
                            }
                            ?>
                            
                            <?php if (!empty($equipment_categories)): ?>
                                <div class="place-equipment" style="margin-top: 1rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                        <i class="fas fa-tools" style="color: var(--brand); font-size: 0.9rem;"></i>
                                        <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark);">Equipment Available:</span>
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                        <?php foreach (array_slice($equipment_categories, 0, 3) as $category): ?>
                                            <span style="display: flex; align-items: center; gap: 0.25rem; background: var(--bg-secondary); color: var(--text-dark); padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500;">
                                                <i class="<?php echo $category['icon_class']; ?>" style="font-size: 0.7rem;"></i>
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                                <span style="background: var(--brand); color: white; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 600;">
                                                    <?php echo $category['equipment_count']; ?>
                                                </span>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($equipment_categories) > 3): ?>
                                            <span style="background: var(--muted); color: white; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500;">
                                                +<?php echo count($equipment_categories) - 3; ?> more
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif ($place['is_equipment_included']): ?>
                                <div class="place-equipment" style="margin-top: 1rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-check-circle" style="color: var(--success); font-size: 0.9rem;"></i>
                                        <span style="font-size: 0.9rem; color: var(--success); font-weight: 500;">Basic Equipment Included</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="place-actions">
                                <a href="place_details.php?id=<?php echo $place['place_id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                                <a href="book_place.php?id=<?php echo $place['place_id']; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-calendar-plus"></i>
                                    Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>




