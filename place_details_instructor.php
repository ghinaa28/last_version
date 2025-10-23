<?php
session_start();
include "connection.php";

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Get place ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: browse_places_instructor.php");
    exit();
}

$place_id = intval($_GET['id']);

// Get place information
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
    header("Location: browse_places_instructor.php");
    exit();
}

// Check if instructor has already applied for this place
$check_application_sql = "SELECT application_id, status FROM instructor_place_applications WHERE instructor_id = ? AND place_id = ?";
$stmt = $conn->prepare($check_application_sql);
$stmt->bind_param("ii", $instructor_id, $place_id);
$stmt->execute();
$existing_application = $stmt->get_result()->fetch_assoc();

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

        .btn-success {
            background: var(--success);
            color: var(--text-white);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--text-white);
        }

        .btn-disabled {
            background: var(--muted);
            color: var(--text-white);
            cursor: not-allowed;
        }

        .application-status {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
            text-align: center;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .status-accepted {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-rejected {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
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

            .amenities-grid {
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
            <a href="instructor_dashboard.php">Instructor Portal</a>
            <i class="fas fa-chevron-right"></i>
            <a href="browse_places_instructor.php">Browse Places</a>
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
            </div>

            <div class="sidebar">
                <!-- Application Status -->
                <?php if ($existing_application): ?>
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-clipboard-check"></i>
                            Application Status
                        </h3>
                        <div class="application-status">
                            <span class="status-badge status-<?php echo $existing_application['status']; ?>">
                                <?php echo ucfirst($existing_application['status']); ?>
                            </span>
                            <p style="margin-top: 1rem; color: var(--muted);">
                                You have already applied for this place.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

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
                    
                    <?php if ($existing_application): ?>
                        <?php if ($existing_application['status'] === 'accepted'): ?>
                            <a href="mailto:<?php echo htmlspecialchars($place['company_email']); ?>" class="btn btn-success">
                                <i class="fas fa-envelope"></i>
                                Contact Company
                            </a>
                        <?php else: ?>
                            <button class="btn btn-disabled" disabled>
                                <i class="fas fa-clock"></i>
                                Application <?php echo ucfirst($existing_application['status']); ?>
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="apply_place_instructor.php?id=<?php echo $place['place_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-hand-paper"></i>
                            Apply for This Place
                        </a>
                    <?php endif; ?>
                    
                    <a href="browse_places_instructor.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Places
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
