<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

// Get company information
$company_id = $_SESSION['company_id'];
$stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get company statistics
$stats_sql = "SELECT 
    COUNT(*) as total_internships,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_internships,
    (SELECT COUNT(*) FROM internship_applications ia 
     JOIN internships i ON ia.internship_id = i.internship_id 
     WHERE i.company_id = ?) as total_applications
    FROM internships 
    WHERE company_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("ii", $company_id, $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get company locations
$locations_sql = "SELECT * FROM company_locations WHERE company_id = ? ORDER BY is_primary DESC, created_at ASC";
$stmt = $conn->prepare($locations_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$locations_result = $stmt->get_result();
$locations = [];
if ($locations_result && $locations_result->num_rows > 0) {
    while ($row = $locations_result->fetch_assoc()) {
        $locations[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile - Company Portal</title>
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

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .profile-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .company-logo {
            width: 120px;
            height: 120px;
            border-radius: var(--radius-xl);
            object-fit: cover;
            margin: 0 auto 1.5rem;
            display: block;
            border: 3px solid var(--line);
        }

        .logo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: var(--radius-xl);
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: var(--text-white);
            font-weight: 800;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--ink);
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .profile-role {
            color: var(--muted);
            text-align: center;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--brand);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--muted);
            font-weight: 600;
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

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .info-section {
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: var(--text-dark);
            font-weight: 500;
            font-size: 1.1rem;
        }

        .info-value.empty {
            color: var(--muted);
            font-style: italic;
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

        .status-approved {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .status-rejected {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .description-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .description-text {
            color: var(--text-dark);
            line-height: 1.7;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .location-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--line);
            position: relative;
            transition: var(--transition);
        }

        .location-card:hover {
            box-shadow: var(--shadow-md);
        }

        .location-card.primary {
            border: 2px solid var(--brand);
            background: linear-gradient(135deg, rgba(14, 165, 168, 0.05), rgba(34, 211, 238, 0.05));
        }

        .primary-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--brand);
            color: var(--text-white);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-lg);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .location-header {
            margin-bottom: 1rem;
        }

        .location-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .location-type {
            background: var(--panel);
            color: var(--muted);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .location-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .detail-item i {
            color: var(--muted);
            margin-top: 0.125rem;
            width: 16px;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--border-light);
        }

        .empty-state a {
            color: var(--brand);
            text-decoration: none;
        }

        .empty-state a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .profile-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
                <span>Company Profile</span>
            </div>
            <h1 class="page-title">Company Profile</h1>
            <p class="page-subtitle">Manage your company information and track your internship postings</p>
        </div>

        <div class="profile-grid">
            <div class="profile-sidebar">
                <div class="profile-card">
                    <?php if ($company['logo_path']): ?>
                        <img src="<?php echo htmlspecialchars($company['logo_path']); ?>" alt="Company Logo" class="company-logo">
                    <?php else: ?>
                        <div class="logo-placeholder">
                            <?php echo strtoupper(substr($company['company_name'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h2 class="profile-name"><?php echo htmlspecialchars($company['company_name']); ?></h2>
                    <p class="profile-role">Company</p>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total_internships']; ?></div>
                            <div class="stat-label">Total Internships</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['active_internships']; ?></div>
                            <div class="stat-label">Active</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                            <div class="stat-label">Applications</div>
                        </div>
                    </div>

                    <a href="company_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Portal
                    </a>
                </div>
            </div>

            <div class="main-content">
                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-building"></i>
                        Company Information
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Company Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($company['company_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Industry</span>
                            <span class="info-value"><?php echo htmlspecialchars($company['industry']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Company Size</span>
                            <span class="info-value <?php echo empty($company['company_size']) ? 'empty' : ''; ?>">
                                <?php echo !empty($company['company_size']) ? htmlspecialchars($company['company_size']) : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Founded Year</span>
                            <span class="info-value <?php echo empty($company['founded_year']) ? 'empty' : ''; ?>">
                                <?php echo !empty($company['founded_year']) ? htmlspecialchars($company['founded_year']) : 'Not specified'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Website</span>
                            <span class="info-value">
                                <?php if (!empty($company['website'])): ?>
                                    <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" style="color: var(--brand);">
                                        <?php echo htmlspecialchars($company['website']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="empty">Not provided</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="status-badge <?php echo $company['status'] === 'approved' ? 'status-approved' : ($company['status'] === 'pending' ? 'status-pending' : 'status-rejected'); ?>">
                                <?php echo ucfirst($company['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h3 class="section-title">
                        <i class="fas fa-envelope"></i>
                        Contact Information
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Email Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($company['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone Number</span>
                            <span class="info-value <?php echo empty($company['phone']) ? 'empty' : ''; ?>">
                                <?php echo !empty($company['phone']) ? htmlspecialchars($company['phone']) : 'Not provided'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Company Locations
                        </h3>
                        <a href="manage_locations.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-cog"></i>
                            Manage Locations
                        </a>
                    </div>
                    
                    <?php if (empty($locations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>No locations found. <a href="manage_locations.php">Add your first location</a></p>
                        </div>
                    <?php else: ?>
                        <div class="locations-grid">
                            <?php foreach ($locations as $location): ?>
                                <div class="location-card <?php echo $location['is_primary'] ? 'primary' : ''; ?>">
                                    <?php if ($location['is_primary']): ?>
                                        <div class="primary-badge">Primary</div>
                                    <?php endif; ?>
                                    
                                    <div class="location-header">
                                        <h4 class="location-name"><?php echo htmlspecialchars($location['location_name']); ?></h4>
                                        <span class="location-type"><?php echo str_replace('_', ' ', ucwords($location['location_type'])); ?></span>
                                    </div>
                                    
                                    <div class="location-details">
                                        <div class="detail-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($location['address']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-city"></i>
                                            <span><?php echo htmlspecialchars($location['city'] . ', ' . $location['country']); ?></span>
                                        </div>
                                        <?php if ($location['postal_code']): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-mail-bulk"></i>
                                                <span><?php echo htmlspecialchars($location['postal_code']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($location['phone']): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-phone"></i>
                                                <span><?php echo htmlspecialchars($location['phone']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($location['email']): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-envelope"></i>
                                                <span><?php echo htmlspecialchars($location['email']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($company['description'])): ?>
                    <div class="info-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Company Description
                        </h3>
                        <div class="description-section">
                            <div class="description-text">
                                <?php echo nl2br(htmlspecialchars($company['description'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>

