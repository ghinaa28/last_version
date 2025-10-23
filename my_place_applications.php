<?php
session_start();
include "connection.php";

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Get instructor information
$stmt = $conn->prepare("SELECT * FROM instructors WHERE instructor_id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();

if (!$instructor) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Create instructor_place_applications table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS instructor_place_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_id INT NOT NULL,
    place_id INT NOT NULL,
    application_message TEXT NOT NULL,
    proposed_start_date DATE NOT NULL,
    proposed_end_date DATE NOT NULL,
    proposed_hours INT DEFAULT NULL,
    proposed_rate DECIMAL(10,2) DEFAULT NULL,
    additional_requirements TEXT DEFAULT NULL,
    contact_preference ENUM('email', 'phone', 'both') DEFAULT 'email',
    availability TEXT DEFAULT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    review_notes TEXT DEFAULT NULL,
    FOREIGN KEY (instructor_id) REFERENCES instructors(instructor_id) ON DELETE CASCADE,
    FOREIGN KEY (place_id) REFERENCES places(place_id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (instructor_id, place_id),
    INDEX idx_instructor_id (instructor_id),
    INDEX idx_place_id (place_id),
    INDEX idx_status (status)
)";

$conn->query($create_table_sql);

// Get instructor's applications with place and company information
$applications_sql = "SELECT 
    ipa.*,
    p.place_name,
    p.place_type,
    p.city,
    p.capacity,
    p.hourly_rate,
    p.weekly_rate,
    p.space_type,
    c.company_name,
    c.logo_path,
    c.email as company_email,
    c.phone as company_phone
    FROM instructor_place_applications ipa
    JOIN places p ON ipa.place_id = p.place_id
    JOIN companies c ON p.company_id = c.company_id
    WHERE ipa.instructor_id = ?
    ORDER BY ipa.applied_at DESC";

$stmt = $conn->prepare($applications_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_applications,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_applications,
    COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_applications,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_applications
    FROM instructor_place_applications 
    WHERE instructor_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Place Applications - Instructor Portal</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            text-align: center;
            border: 1px solid var(--line);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--brand);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--muted);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .applications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        .application-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .application-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .place-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .place-type {
            color: var(--muted);
            font-size: 0.9rem;
            text-transform: capitalize;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .company-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .company-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .company-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .company-details p {
            color: var(--muted);
            font-size: 0.875rem;
        }

        .application-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: var(--text-dark);
            font-weight: 500;
        }

        .application-message {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-style: italic;
            color: var(--text-dark);
        }

        .application-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.875rem;
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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--panel);
            border-radius: var(--radius-xl);
            border: 2px dashed var(--border-light);
        }

        .empty-state .icon {
            font-size: 4rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--ink);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: var(--muted);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .review-notes {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-top: 1rem;
            border-left: 4px solid var(--brand);
        }

        .review-notes h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .review-notes p {
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .applications-grid {
                grid-template-columns: 1fr;
            }

            .application-details {
                grid-template-columns: 1fr;
            }

            .application-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="instructor_dashboard.php">Instructor Portal</a>
                <i class="fas fa-chevron-right"></i>
                <span>My Place Applications</span>
            </div>
            <h1 class="page-title">My Place Applications</h1>
            <p class="page-subtitle">Track your applications for places posted by companies</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['accepted_applications']; ?></div>
                <div class="stat-label">Accepted</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejected_applications']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <div class="icon">📝</div>
                <h3>No Applications Yet</h3>
                <p>You haven't applied for any places yet. Start by browsing available places and submit your applications.</p>
                <a href="browse_places_instructor.php" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Browse Places
                </a>
            </div>
        <?php else: ?>
            <div class="applications-grid">
                <?php foreach ($applications as $application): ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div>
                                <h3 class="place-title"><?php echo htmlspecialchars($application['place_name']); ?></h3>
                                <p class="place-type"><?php echo str_replace('_', ' ', $application['place_type']); ?></p>
                            </div>
                            <span class="status-badge status-<?php echo $application['status']; ?>">
                                <?php echo ucfirst($application['status']); ?>
                            </span>
                        </div>

                        <div class="company-info">
                            <?php if ($application['logo_path']): ?>
                                <img src="<?php echo htmlspecialchars($application['logo_path']); ?>" alt="Company Logo" class="company-logo">
                            <?php else: ?>
                                <div class="company-logo" style="background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">
                                    <?php echo strtoupper(substr($application['company_name'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="company-details">
                                <h4><?php echo htmlspecialchars($application['company_name']); ?></h4>
                                <p><?php echo htmlspecialchars($application['company_email']); ?></p>
                            </div>
                        </div>

                        <div class="application-details">
                            <div class="detail-item">
                                <span class="detail-label">Applied Date</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($application['applied_at'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Proposed Period</span>
                                <span class="detail-value">
                                    <?php echo date('M d', strtotime($application['proposed_start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($application['proposed_end_date'])); ?>
                                </span>
                            </div>
                            <?php if ($application['proposed_hours']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Proposed Hours</span>
                                    <span class="detail-value"><?php echo $application['proposed_hours']; ?> hours/week</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($application['proposed_rate']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Proposed Rate</span>
                                    <span class="detail-value">$<?php echo number_format($application['proposed_rate'], 2); ?>/hour</span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?php echo htmlspecialchars($application['city']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Capacity</span>
                                <span class="detail-value"><?php echo $application['capacity']; ?> people</span>
                            </div>
                        </div>

                        <div class="application-message">
                            <strong>Your Message:</strong><br>
                            <?php echo htmlspecialchars($application['application_message']); ?>
                        </div>

                        <?php if ($application['additional_requirements']): ?>
                            <div style="margin-bottom: 1rem;">
                                <strong style="color: var(--ink);">Additional Requirements:</strong><br>
                                <span style="color: var(--text-dark);"><?php echo htmlspecialchars($application['additional_requirements']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($application['availability']): ?>
                            <div style="margin-bottom: 1rem;">
                                <strong style="color: var(--ink);">Your Availability:</strong><br>
                                <span style="color: var(--text-dark);"><?php echo htmlspecialchars($application['availability']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($application['review_notes']): ?>
                            <div class="review-notes">
                                <h4>Company Response:</h4>
                                <p><?php echo htmlspecialchars($application['review_notes']); ?></p>
                                <?php if ($application['reviewed_at']): ?>
                                    <small style="color: var(--muted);">Reviewed on <?php echo date('M d, Y', strtotime($application['reviewed_at'])); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="application-actions">
                            <a href="place_details_instructor.php?id=<?php echo $application['place_id']; ?>" class="btn btn-secondary">
                                <i class="fas fa-eye"></i>
                                View Place
                            </a>
                            <?php if ($application['status'] === 'accepted'): ?>
                                <a href="mailto:<?php echo htmlspecialchars($application['company_email']); ?>" class="btn btn-primary">
                                    <i class="fas fa-envelope"></i>
                                    Contact Company
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
