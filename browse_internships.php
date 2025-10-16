<?php
session_start();
include "connection.php";

// Check if user is logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Get student information
$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Monthly application limit removed - students can now apply to multiple internships

// Get search and filter parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
$department = isset($_GET['department']) ? $conn->real_escape_string($_GET['department']) : '';

// Build query
$where_conditions = ["i.status = 'active'", "i.application_deadline >= CURDATE()"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(i.title LIKE ? OR i.description LIKE ? OR c.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($location)) {
    $where_conditions[] = "i.location LIKE ?";
    $params[] = "%$location%";
}

if (!empty($type)) {
    $where_conditions[] = "i.type = ?";
    $params[] = $type;
}

if (!empty($department)) {
    $where_conditions[] = "i.department LIKE ?";
    $params[] = "%$department%";
}

$where_clause = implode(' AND ', $where_conditions);

$sql = "SELECT i.*, c.company_name, c.industry, c.logo_path,
        (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.internship_id) as application_count,
        (SELECT COUNT(*) FROM internship_applications ia WHERE ia.internship_id = i.internship_id AND ia.student_id = ?) as has_applied
        FROM internships i 
        JOIN companies c ON i.company_id = c.company_id 
        WHERE $where_clause 
        ORDER BY i.created_at DESC";

$params = array_merge([$student_id], $params);
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$internships = $stmt->get_result();

// Get unique values for filters
$locations_result = $conn->query("SELECT DISTINCT location FROM internships WHERE status = 'active' ORDER BY location");
$departments_result = $conn->query("SELECT DISTINCT department FROM internships WHERE status = 'active' AND department IS NOT NULL ORDER BY department");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Internships - Student Portal</title>
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

        .header h1 {
            color: var(--ink);
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--muted);
            font-size: 1.1rem;
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

        .filters {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .filters h3 {
            margin-bottom: 1rem;
            color: var(--ink);
            font-weight: 600;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-input, .filter-select {
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--brand);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
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

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: var(--warning);
            color: var(--text-white);
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .internships-grid {
            display: grid;
            gap: 1.5rem;
        }

        .internship-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 0;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            transition: var(--transition);
            overflow: hidden;
            position: relative;
        }

        .internship-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .poster-image {
            width: 100%;
            height: 800px;
            object-fit: cover;
            border-radius: var(--radius-xl);
            border: none;
            display: block;
        }

        .poster-placeholder {
            width: 100%;
            height: 600px;
            background: linear-gradient(135deg, var(--bg-secondary), var(--line));
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 1.2rem;
            border: none;
        }

        .card-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            padding: 2rem 1.5rem 1.5rem;
            color: white;
        }

        .card-title-overlay {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .company-name-overlay {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .card-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn-overlay {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            min-width: 120px;
            justify-content: center;
        }

        .btn-apply {
            background: var(--success);
            color: white;
        }

        .btn-apply:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-details {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-details:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }

        .btn-applied {
            background: var(--warning);
            color: white;
            cursor: not-allowed;
        }

        .btn-overlay:disabled {
            background: rgba(107, 114, 128, 0.5);
            color: rgba(255, 255, 255, 0.7);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-overlay:disabled:hover {
            transform: none;
            background: rgba(107, 114, 128, 0.5);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .company-logo {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }

        .card-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-type {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
        }

        .badge-location {
            background: rgba(34, 211, 238, 0.1);
            color: var(--brand-2);
        }

        .badge-department {
            background: rgba(71, 85, 105, 0.1);
            color: var(--muted);
        }

        .card-content {
            margin-bottom: 1rem;
        }

        .card-description {
            color: var(--muted);
            font-size: 0.95rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .detail-item i {
            color: var(--brand);
            width: 16px;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
        }

        .application-info {
            font-size: 0.85rem;
            color: var(--muted);
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--muted);
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-light);
        }


        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .card-header {
                flex-direction: column;
                gap: 1rem;
            }

            .card-details {
                grid-template-columns: 1fr;
            }

            .card-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="student_dashboard.php">Student Portal</a>
                <i class="fas fa-chevron-right"></i>
                <span>Browse Internships</span>
            </div>
            <h1>Browse Internships</h1>
            <p>Discover exciting internship opportunities from top companies.</p>
        </div>


        <div class="filters">
            <h3>Filter Internships</h3>
            <form method="GET" action="browse_internships.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Job title, company, or keywords" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Location</label>
                        <select name="location" class="filter-select">
                            <option value="">All Locations</option>
                            <?php while ($loc = $locations_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['location']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Type</label>
                        <select name="type" class="filter-select">
                            <option value="">All Types</option>
                            <option value="full-time" <?php echo $type === 'full-time' ? 'selected' : ''; ?>>Full-time</option>
                            <option value="part-time" <?php echo $type === 'part-time' ? 'selected' : ''; ?>>Part-time</option>
                            <option value="remote" <?php echo $type === 'remote' ? 'selected' : ''; ?>>Remote</option>
                            <option value="hybrid" <?php echo $type === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Department</label>
                        <select name="department" class="filter-select">
                            <option value="">All Departments</option>
                            <?php while ($dept = $departments_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $department === $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <a href="browse_internships.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <div class="internships-grid">
            <?php if ($internships->num_rows > 0): ?>
                <?php while ($internship = $internships->fetch_assoc()): ?>
                    <div class="internship-card">
                        <?php if ($internship['poster_path']): ?>
                            <img src="<?php echo htmlspecialchars($internship['poster_path']); ?>" alt="Internship Poster" class="poster-image">
                        <?php else: ?>
                            <div class="poster-placeholder">
                                <i class="fas fa-image"></i>
                                <span>No poster available</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-overlay">
                            <h3 class="card-title-overlay"><?php echo htmlspecialchars($internship['title']); ?></h3>
                            <div class="company-name-overlay"><?php echo htmlspecialchars($internship['company_name']); ?></div>
                            
                            <div class="card-buttons">
                                <?php if ($internship['has_applied'] > 0): ?>
                                    <button class="btn-overlay btn-applied" disabled>
                                        <i class="fas fa-check"></i>
                                        Applied
                                    </button>
                                <?php else: ?>
                                    <a href="apply_internship.php?id=<?php echo $internship['internship_id']; ?>" class="btn-overlay btn-apply">
                                        <i class="fas fa-paper-plane"></i>
                                        Apply Now
                                    </a>
                                <?php endif; ?>
                                <a href="internship_details.php?id=<?php echo $internship['internship_id']; ?>" class="btn-overlay btn-details">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No internships found</h3>
                    <p>Try adjusting your search criteria or check back later for new opportunities.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

