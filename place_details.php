<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$place_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
$conn->query($create_place_equipment);

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

if (!$place_id) {
    header("Location: browse_places.php");
    exit();
}

// Get place details
$place_sql = "SELECT 
    p.*,
    c.company_name,
    c.logo_path,
    c.email as company_email,
    c.phone as company_phone,
    AVG(pr.rating) as average_rating,
    COUNT(pr.review_id) as total_reviews,
    COUNT(pb.booking_id) as total_bookings
    FROM places p
    JOIN companies c ON p.company_id = c.company_id
    LEFT JOIN place_reviews pr ON p.place_id = pr.place_id
    LEFT JOIN place_bookings pb ON p.place_id = pb.place_id
    WHERE p.place_id = ? AND p.status = 'active'
    GROUP BY p.place_id";

$stmt = $conn->prepare($place_sql);
$stmt->bind_param("i", $place_id);
$stmt->execute();
$place = $stmt->get_result()->fetch_assoc();

if (!$place) {
    header("Location: browse_places.php");
    exit();
}

// Get place equipment using the new Smart Equipment System
$equipment = [];
$equipment_by_category = [];

// Check if place_equipment table exists and has the required columns
$check_table = "SHOW TABLES LIKE 'place_equipment'";
$table_exists = $conn->query($check_table);

if ($table_exists && $table_exists->num_rows > 0) {
    // Check if the table has the required columns
    $check_columns = "SHOW COLUMNS FROM place_equipment LIKE 'item_id'";
    $column_exists = $conn->query($check_columns);
    
    if ($column_exists && $column_exists->num_rows > 0) {
        try {
            $equipment_sql = "SELECT pe.*, ei.item_name, ei.description, ei.standard_price, ei.unit_type, ec.category_name, ec.icon_class
                  FROM place_equipment pe
                  JOIN equipment_items ei ON pe.item_id = ei.item_id
                  JOIN equipment_categories ec ON ei.category_id = ec.category_id
                  WHERE pe.place_id = ? AND pe.is_available = 1
                  ORDER BY ec.category_name, ei.item_name";
$equipment_stmt = $conn->prepare($equipment_sql);
            if ($equipment_stmt) {
$equipment_stmt->bind_param("i", $place_id);
$equipment_stmt->execute();
$equipment = $equipment_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            }
        } catch (Exception $e) {
            // If there's any error, just set empty array to prevent fatal error
            $equipment = [];
        }
    }
}

// Group equipment by category
$equipment_by_category = [];
foreach ($equipment as $item) {
    $category = $item['category_name'];
    if (!isset($equipment_by_category[$category])) {
        $equipment_by_category[$category] = [
            'icon_class' => $item['icon_class'],
            'items' => []
        ];
    }
    $equipment_by_category[$category]['items'][] = $item;
}

// Get place availability
$availability_sql = "SELECT * FROM place_availability WHERE place_id = ? ORDER BY day_of_week, start_time";
$availability_stmt = $conn->prepare($availability_sql);
$availability_stmt->bind_param("i", $place_id);
$availability_stmt->execute();
$availability = $availability_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent reviews
$reviews_sql = "SELECT 
    pr.*,
    c.company_name as reviewer_company
    FROM place_reviews pr
    JOIN companies c ON pr.company_id = c.company_id
    WHERE pr.place_id = ?
    ORDER BY pr.created_at DESC
    LIMIT 5";

$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $place_id);
$reviews_stmt->execute();
$reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if this is the user's own place
$is_own_place = $place['company_id'] == $company_id;

// Parse JSON data
$amenities = json_decode($place['amenities'], true) ?: [];
$images = json_decode($place['images'], true) ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($place['place_name']); ?> - Place Details</title>
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

        .place-header {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .place-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .place-subtitle {
            color: var(--muted);
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .place-meta {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stars {
            color: #fbbf24;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
        }

        .company-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .company-details h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .company-details p {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .section {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .image-gallery {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 0.5rem;
            height: 400px;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .main-image {
            grid-row: 1 / 3;
            object-fit: cover;
            width: 100%;
            height: 100%;
        }

        .side-image {
            object-fit: cover;
            width: 100%;
            height: 100%;
        }

        .no-images {
            grid-column: 1 / 4;
            grid-row: 1 / 3;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 3rem;
        }

        .description {
            color: var(--text-dark);
            line-height: 1.7;
            font-size: 1.1rem;
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .amenity-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--line);
        }

        .amenity-icon {
            width: 40px;
            height: 40px;
            background: var(--brand);
            color: var(--text-white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .amenity-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .equipment-item {
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--line);
        }

        .equipment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .equipment-name {
            font-weight: 700;
            color: var(--ink);
        }

        .equipment-type {
            background: var(--brand);
            color: var(--text-white);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .equipment-details {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .equipment-cost {
            font-weight: 600;
            color: var(--success);
        }

        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .day-schedule {
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--line);
            text-align: center;
        }

        .day-name {
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
            text-transform: capitalize;
        }

        .day-times {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .day-unavailable {
            color: var(--error);
            font-style: italic;
        }

        .pricing-card {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
            text-align: center;
            position: sticky;
            top: 2rem;
        }

        .pricing-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .price-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .price-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: var(--radius-lg);
        }

        .price-value {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .price-label {
            font-size: 0.9rem;
            opacity: 0.9;
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
            width: 100%;
        }

        .btn-primary {
            background: var(--text-white);
            color: var(--brand);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-white);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .reviews-section {
            margin-top: 2rem;
        }

        .review-item {
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--line);
            margin-bottom: 1rem;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .reviewer-name {
            font-weight: 600;
            color: var(--ink);
        }

        .review-date {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .review-rating {
            display: flex;
            gap: 0.25rem;
        }

        .review-text {
            color: var(--text-dark);
            line-height: 1.6;
        }

        .no-reviews {
            text-align: center;
            color: var(--muted);
            font-style: italic;
            padding: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .place-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .image-gallery {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
                height: auto;
            }

            .main-image {
                grid-row: auto;
            }

            .no-images {
                grid-column: auto;
                grid-row: auto;
            }

            .amenities-grid,
            .equipment-grid,
            .availability-grid {
                grid-template-columns: 1fr;
            }

            .price-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="company_dashboard.php">Company Portal</a>
            <i class="fas fa-chevron-right"></i>
            <a href="browse_places.php">Browse Places</a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($place['place_name']); ?></span>
        </div>

        <div class="place-header">
            <h1 class="place-title"><?php echo htmlspecialchars($place['place_name']); ?></h1>
            <p class="place-subtitle">
                <?php echo str_replace('_', ' ', ucwords($place['place_type'])); ?> • 
                <span style="background: <?php echo $place['space_type'] === 'short_term' ? '#0ea5a8' : '#059669'; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; margin: 0 0.5rem;">
                    <?php echo $place['space_type'] === 'short_term' ? 'Short-term' : 'Long-term'; ?>
                </span>
                • <?php echo htmlspecialchars($place['city']); ?>, <?php echo htmlspecialchars($place['country']); ?>
            </p>
            
            <div class="place-meta">
                <div class="meta-item">
                    <i class="fas fa-users"></i>
                    <span><?php echo $place['capacity']; ?> people</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($place['address']); ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo $place['total_bookings']; ?> bookings</span>
                </div>
                <?php if ($place['average_rating']): ?>
                    <div class="rating">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $place['average_rating'] ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span><?php echo number_format($place['average_rating'], 1); ?> (<?php echo $place['total_reviews']; ?> reviews)</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="company-info">
                <?php if ($place['logo_path']): ?>
                    <img src="<?php echo htmlspecialchars($place['logo_path']); ?>" alt="Company Logo" class="company-logo">
                <?php else: ?>
                    <div class="company-logo" style="background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.5rem;">
                        <?php echo strtoupper(substr($place['company_name'], 0, 2)); ?>
                    </div>
                <?php endif; ?>
                <div class="company-details">
                    <h4><?php echo htmlspecialchars($place['company_name']); ?></h4>
                    <p><?php echo htmlspecialchars($place['company_email']); ?></p>
                    <?php if ($place['company_phone']): ?>
                        <p><?php echo htmlspecialchars($place['company_phone']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <!-- Images -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-images"></i>
                        Photos
                    </h3>
                    <?php if (!empty($images)): ?>
                        <div class="image-gallery">
                            <?php foreach ($images as $index => $image): ?>
                                <?php if ($index === 0): ?>
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Place Image" class="main-image">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Place Image" class="side-image">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-images">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        About This Place
                    </h3>
                    <div class="description">
                        <?php echo nl2br(htmlspecialchars($place['description'])); ?>
                    </div>
                </div>

                <!-- Amenities -->
                <?php if (!empty($amenities)): ?>
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-star"></i>
                            Amenities
                        </h3>
                        <div class="amenities-grid">
                            <?php foreach ($amenities as $amenity): ?>
                                <div class="amenity-item">
                                    <div class="amenity-icon">
                                        <i class="fas fa-<?php 
                                            $icons = [
                                                'wifi' => 'wifi',
                                                'parking' => 'parking',
                                                'air_conditioning' => 'snowflake',
                                                'projector' => 'video',
                                                'whiteboard' => 'chalkboard',
                                                'kitchen' => 'utensils',
                                                'restroom' => 'restroom',
                                                'security' => 'shield-alt',
                                                'accessibility' => 'wheelchair',
                                                'catering' => 'hamburger'
                                            ];
                                            echo $icons[$amenity] ?? 'check';
                                        ?>"></i>
                                    </div>
                                    <span class="amenity-name"><?php echo ucwords(str_replace('_', ' ', $amenity)); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Smart Equipment System -->
                <?php if (!empty($equipment_by_category)): ?>
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-tools"></i>
                            Available Equipment
                        </h3>
                        <p style="color: var(--muted); margin-bottom: 1.5rem;">
                            This space includes the following equipment organized by category:
                        </p>
                        
                        <?php foreach ($equipment_by_category as $category_name => $category_data): ?>
                            <div class="equipment-category-section" style="margin-bottom: 2rem;">
                                <h4 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: var(--text-dark);">
                                    <i class="<?php echo $category_data['icon_class']; ?>" style="color: var(--brand);"></i>
                                    <?php echo htmlspecialchars($category_name); ?>
                                </h4>
                                <div class="equipment-grid">
                                    <?php foreach ($category_data['items'] as $item): ?>
                                        <div class="equipment-item">
                                            <div class="equipment-header">
                                                <span class="equipment-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                                <span class="equipment-type"><?php echo ucfirst(str_replace('_', ' ', $item['unit_type'])); ?></span>
                                            </div>
                                            <div class="equipment-details">
                                                <strong>Quantity:</strong> <?php echo $item['quantity_available']; ?>
                                                <?php if (isset($item['description']) && !empty($item['description'])): ?>
                                                    <br><strong>Description:</strong> <?php echo htmlspecialchars($item['description']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="equipment-cost">
                                                <?php if ($item['custom_price'] > 0): ?>
                                                    <strong>Price:</strong> $<?php echo number_format($item['custom_price'], 2); ?>/<?php echo str_replace('_', ' ', $item['unit_type']); ?>
                                                <?php else: ?>
                                                    <strong>Price:</strong> $<?php echo number_format($item['standard_price'], 2); ?>/<?php echo str_replace('_', ' ', $item['unit_type']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($place['is_equipment_included']): ?>
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-tools"></i>
                            Equipment & Facilities
                        </h3>
                        <div style="padding: 1.5rem; background: var(--bg-secondary); border-radius: var(--radius-lg); text-align: center;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success); margin-bottom: 1rem;"></i>
                            <h4 style="color: var(--success); margin-bottom: 0.5rem;">Equipment Included</h4>
                            <p style="color: var(--muted); margin: 0;">
                                This space includes basic equipment and facilities. Contact the company for specific equipment details.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Availability -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Availability
                    </h3>
                    <div class="availability-grid">
                        <?php
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                        $availability_by_day = [];
                        foreach ($availability as $avail) {
                            $availability_by_day[$avail['day_of_week']][] = $avail;
                        }
                        
                        foreach ($days as $day): ?>
                            <div class="day-schedule">
                                <div class="day-name"><?php echo ucfirst($day); ?></div>
                                <?php if (isset($availability_by_day[$day])): ?>
                                    <div class="day-times">
                                        <?php foreach ($availability_by_day[$day] as $avail): ?>
                                            <?php echo date('g:i A', strtotime($avail['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($avail['end_time'])); ?><br>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="day-times day-unavailable">Not available</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Reviews -->
                <?php if (!empty($reviews)): ?>
                    <div class="section reviews-section">
                        <h3 class="section-title">
                            <i class="fas fa-comments"></i>
                            Recent Reviews
                        </h3>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <span class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_company']); ?></span>
                                        <span class="review-date"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></span>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>" style="color: #fbbf24;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-text">
                                    <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sidebar">
                <!-- Pricing -->
                <div class="section pricing-card">
                    <h3 class="pricing-title">Pricing</h3>
                    <div class="price-grid">
                        <?php if ($place['space_type'] === 'short_term'): ?>
                            <!-- Short-term rental pricing -->
                            <?php if ($place['hourly_rate'] > 0): ?>
                                <div class="price-item">
                                    <div class="price-value">$<?php echo number_format($place['hourly_rate'], 2); ?></div>
                                    <div class="price-label">Per Hour</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($place['daily_rate'] > 0): ?>
                                <div class="price-item">
                                    <div class="price-value">$<?php echo number_format($place['daily_rate'], 2); ?></div>
                                    <div class="price-label">Per Day</div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Long-term rental pricing -->
                            <?php if ($place['weekly_rate'] > 0): ?>
                                <div class="price-item">
                                    <div class="price-value">$<?php echo number_format($place['weekly_rate'], 2); ?></div>
                                    <div class="price-label">Per Week</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($place['monthly_rate'] > 0): ?>
                                <div class="price-item">
                                    <div class="price-value">$<?php echo number_format($place['monthly_rate'], 2); ?></div>
                                    <div class="price-label">Per Month</div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($is_own_place): ?>
                        <a href="manage_places.php" class="btn btn-secondary">
                            <i class="fas fa-cog"></i>
                            Manage This Place
                        </a>
                    <?php else: ?>
                        <a href="book_place.php?id=<?php echo $place['place_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i>
                            Book This Place
                        </a>
                        <a href="contact_company.php?id=<?php echo $place['company_id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-envelope"></i>
                            Contact Company
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Policies -->
                <?php if ($place['booking_policy'] || $place['cancellation_policy']): ?>
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-file-contract"></i>
                            Policies
                        </h3>
                        <?php if ($place['booking_policy']): ?>
                            <div style="margin-bottom: 1rem;">
                                <h4 style="font-weight: 600; color: var(--ink); margin-bottom: 0.5rem;">Booking Policy</h4>
                                <p style="color: var(--muted); font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($place['booking_policy'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($place['cancellation_policy']): ?>
                            <div>
                                <h4 style="font-weight: 600; color: var(--ink); margin-bottom: 0.5rem;">Cancellation Policy</h4>
                                <p style="color: var(--muted); font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($place['cancellation_policy'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>




