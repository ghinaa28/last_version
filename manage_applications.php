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

// Handle application status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['application_id'])) {
    $application_id = intval($_POST['application_id']);
    $action = $_POST['action'];
    
    // Verify the application belongs to this company
    $verify_sql = "SELECT ia.* FROM internship_applications ia 
                   JOIN internships i ON ia.internship_id = i.internship_id 
                   WHERE ia.application_id = ? AND i.company_id = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $application_id, $company_id);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    
    if ($application) {
        $new_status = '';
        switch ($action) {
            case 'accept':
                $new_status = 'accepted';
                break;
            case 'reject':
                $new_status = 'rejected';
                break;
            case 'shortlist':
                $new_status = 'shortlisted';
                break;
            case 'interview':
                $new_status = 'interviewed';
                break;
            case 'review':
                $new_status = 'reviewed';
                break;
        }
        
        if ($new_status) {
            $update_sql = "UPDATE internship_applications SET status = ?, updated_at = NOW() WHERE application_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $new_status, $application_id);
            $stmt->execute();
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$internship_filter = $_GET['internship'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for applications
$sql = "SELECT ia.*, 
        CONCAT(s.first_name, ' ', s.last_name) as full_name, s.email, s.phone, s.university, s.department, s.cv_path,
        i.title as internship_title, i.department as internship_department, i.location, i.type, i.start_date, i.end_date,
        c.company_name
        FROM internship_applications ia
        JOIN internships i ON ia.internship_id = i.internship_id
        JOIN students s ON ia.student_id = s.student_id
        JOIN companies c ON i.company_id = c.company_id
        WHERE i.company_id = ?";

$params = [$company_id];
$param_types = "i";

if (!empty($status_filter)) {
    $sql .= " AND ia.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($internship_filter)) {
    $sql .= " AND ia.internship_id = ?";
    $params[] = intval($internship_filter);
    $param_types .= "i";
}

if (!empty($search)) {
    $sql .= " AND (CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.email LIKE ? OR i.title LIKE ? OR s.university LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

$sql .= " ORDER BY ia.application_date DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get application statistics
$stats_sql = "SELECT 
    COUNT(*) as total_applications,
    SUM(CASE WHEN ia.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN ia.status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_count,
    SUM(CASE WHEN ia.status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted_count,
    SUM(CASE WHEN ia.status = 'interviewed' THEN 1 ELSE 0 END) as interviewed_count,
    SUM(CASE WHEN ia.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
    SUM(CASE WHEN ia.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM internship_applications ia
    JOIN internships i ON ia.internship_id = i.internship_id
    WHERE i.company_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get company's internships for filter
$internships_sql = "SELECT internship_id, title FROM internships WHERE company_id = ? ORDER BY title";
$stmt = $conn->prepare($internships_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company_internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications - Company Portal</title>
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
            max-width: 1400px;
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--brand);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filters */
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

        /* Applications Grid */
        .applications-grid {
            display: grid;
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
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .student-info h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .student-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .status-reviewed {
            background: rgba(59, 130, 246, 0.1);
            color: #1d4ed8;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .status-shortlisted {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .status-interviewed {
            background: rgba(168, 85, 247, 0.1);
            color: #9333ea;
            border: 1px solid rgba(168, 85, 247, 0.3);
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

        .internship-info {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .internship-info h4 {
            color: var(--ink);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .application-content {
            margin-bottom: 1.5rem;
        }

        .content-section {
            margin-bottom: 1rem;
        }

        .content-section h5 {
            color: var(--ink);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .content-text {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.6;
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-lg);
            border-left: 3px solid var(--brand);
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-accept {
            background: var(--success);
            color: white;
        }

        .btn-accept:hover {
            background: #059669;
        }

        .btn-reject {
            background: var(--error);
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
        }

        .btn-shortlist {
            background: #8b5cf6;
            color: white;
        }

        .btn-shortlist:hover {
            background: #7c3aed;
        }

        .btn-interview {
            background: #a855f7;
            color: white;
        }

        .btn-interview:hover {
            background: #9333ea;
        }

        .btn-review {
            background: #3b82f6;
            color: white;
        }

        .btn-review:hover {
            background: #1d4ed8;
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

            .student-details {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                justify-content: center;
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
                <span>Manage Applications</span>
            </div>
            <h1 class="page-title">Manage Applications</h1>
            <p class="page-subtitle">Review and manage student applications for your internships</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_count']; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['shortlisted_count']; ?></div>
                <div class="stat-label">Shortlisted</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['accepted_count']; ?></div>
                <div class="stat-label">Accepted</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <h3>Filter Applications</h3>
            <form method="GET" action="manage_applications.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Student name, email, or internship" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                            <option value="shortlisted" <?php echo $status_filter === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                            <option value="interviewed" <?php echo $status_filter === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                            <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Internship</label>
                        <select name="internship" class="filter-select">
                            <option value="">All Internships</option>
                            <?php foreach ($company_internships as $internship): ?>
                                <option value="<?php echo $internship['internship_id']; ?>" <?php echo $internship_filter == $internship['internship_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($internship['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                    <a href="manage_applications.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Applications -->
        <div class="applications-grid">
            <?php if (count($applications) > 0): ?>
                <?php foreach ($applications as $app): ?>
                    <div class="application-card">
                        <div class="card-header">
                            <div class="student-info">
                                <h3><?php echo htmlspecialchars($app['full_name']); ?></h3>
                                <div class="student-details">
                                    <div class="detail-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($app['email']); ?></span>
                                    </div>
                                    <?php if ($app['phone']): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-phone"></i>
                                            <span><?php echo htmlspecialchars($app['phone']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-item">
                                        <i class="fas fa-graduation-cap"></i>
                                        <span><?php echo htmlspecialchars($app['university']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-book"></i>
                                        <span><?php echo htmlspecialchars($app['department']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <?php echo ucfirst($app['status']); ?>
                            </span>
                        </div>

                        <div class="internship-info">
                            <h4><?php echo htmlspecialchars($app['internship_title']); ?></h4>
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                <span>Applied: <?php echo date('M d, Y', strtotime($app['application_date'])); ?></span>
                            </div>
                        </div>

                        <div class="application-content">
                            <?php if ($app['cover_letter']): ?>
                                <div class="content-section">
                                    <h5>Cover Letter</h5>
                                    <div class="content-text"><?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($app['motivation']): ?>
                                <div class="content-section">
                                    <h5>Motivation</h5>
                                    <div class="content-text"><?php echo nl2br(htmlspecialchars($app['motivation'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($app['relevant_experience']): ?>
                                <div class="content-section">
                                    <h5>Relevant Experience</h5>
                                    <div class="content-text"><?php echo nl2br(htmlspecialchars($app['relevant_experience'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($app['why_this_company']): ?>
                                <div class="content-section">
                                    <h5>Why This Company</h5>
                                    <div class="content-text"><?php echo nl2br(htmlspecialchars($app['why_this_company'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($app['career_goals']): ?>
                                <div class="content-section">
                                    <h5>Career Goals</h5>
                                    <div class="content-text"><?php echo nl2br(htmlspecialchars($app['career_goals'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($app['additional_info']): ?>
                                <div class="content-section">
                                    <h5>Additional Information</h5>
                                    <div class="content-text"><?php echo nl2br(htmlspecialchars($app['additional_info'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="action-buttons">
                            <?php if ($app['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                                    <input type="hidden" name="action" value="review">
                                    <button type="submit" class="btn-action btn-review">
                                        <i class="fas fa-eye"></i>
                                        Mark as Reviewed
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($app['status'], ['pending', 'reviewed'])): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                                    <input type="hidden" name="action" value="shortlist">
                                    <button type="submit" class="btn-action btn-shortlist">
                                        <i class="fas fa-star"></i>
                                        Shortlist
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($app['status'], ['shortlisted'])): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                                    <input type="hidden" name="action" value="interview">
                                    <button type="submit" class="btn-action btn-interview">
                                        <i class="fas fa-video"></i>
                                        Schedule Interview
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($app['status'], ['interviewed', 'shortlisted'])): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" class="btn-action btn-accept">
                                        <i class="fas fa-check"></i>
                                        Accept
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (!in_array($app['status'], ['accepted', 'rejected'])): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn-action btn-reject">
                                        <i class="fas fa-times"></i>
                                        Reject
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-inbox"></i>
                    <h3>No applications found</h3>
                    <p>No applications match your current filter criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit forms for better UX
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                button.disabled = true;
                
                // Re-enable after 2 seconds in case of error
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            });
        });
    </script>
</body>
</html>
