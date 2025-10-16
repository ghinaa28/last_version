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

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT ia.*, i.title, i.department, i.location, i.type, i.start_date, i.end_date, 
        c.company_name, c.industry, c.logo_path,
        CASE 
            WHEN ia.status = 'pending' THEN 'Pending Review'
            WHEN ia.status = 'reviewed' THEN 'Under Review'
            WHEN ia.status = 'shortlisted' THEN 'Shortlisted'
            WHEN ia.status = 'interviewed' THEN 'Interviewed'
            WHEN ia.status = 'accepted' THEN 'Accepted'
            WHEN ia.status = 'rejected' THEN 'Rejected'
            ELSE ia.status
        END as status_display
        FROM internship_applications ia
        JOIN internships i ON ia.internship_id = i.internship_id
        JOIN companies c ON i.company_id = c.company_id
        WHERE ia.student_id = ?";

$params = [$student_id];
$param_types = "i";

if (!empty($status_filter)) {
    $sql .= " AND ia.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (i.title LIKE ? OR c.company_name LIKE ? OR i.department LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
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
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_count,
    SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted_count,
    SUM(CASE WHEN status = 'interviewed' THEN 1 ELSE 0 END) as interviewed_count,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM internship_applications 
    WHERE student_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - Student Portal</title>
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.875rem;
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
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
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

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .application-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .company-logo {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            border: 2px solid var(--line);
        }

        .company-details h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .company-details p {
            color: var(--muted);
            font-size: 0.9rem;
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

        .status-reviewed {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
            border: 1px solid rgba(14, 165, 168, 0.3);
        }

        .status-shortlisted {
            background: rgba(34, 211, 238, 0.1);
            color: var(--brand-2);
            border: 1px solid rgba(34, 211, 238, 0.3);
        }

        .status-interviewed {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            border: 1px solid rgba(99, 102, 241, 0.3);
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

        .application-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.875rem;
            color: var(--muted);
            font-weight: 500;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        .application-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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

            .application-header {
                flex-direction: column;
                gap: 1rem;
            }

            .application-details {
                grid-template-columns: 1fr;
            }

            .application-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
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
                <span>My Applications</span>
            </div>
            <h1 class="page-title">My Applications</h1>
            <p class="page-subtitle">Track the status of your internship applications</p>
        </div>

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

        <div class="filters-section">
            <form method="GET" class="filters-grid">
                <div class="form-group">
                    <label for="search" class="form-label">Search Applications</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        class="form-input" 
                        placeholder="Search by internship title, company, or department..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>
                <div class="form-group">
                    <label for="status" class="form-label">Filter by Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="shortlisted" <?php echo $status_filter === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                        <option value="interviewed" <?php echo $status_filter === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                        <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No Applications Found</h3>
                <p>You haven't applied to any internships yet, or no applications match your current filters.</p>
                <a href="browse_internships.php" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Browse Internships
                </a>
            </div>
        <?php else: ?>
            <div class="applications-grid">
                <?php foreach ($applications as $application): ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div class="application-info">
                                <h3><?php echo htmlspecialchars($application['title']); ?></h3>
                                <div class="company-info">
                                    <?php if ($application['logo_path']): ?>
                                        <img src="<?php echo htmlspecialchars($application['logo_path']); ?>" alt="Company Logo" class="company-logo">
                                    <?php endif; ?>
                                    <div class="company-details">
                                        <h4><?php echo htmlspecialchars($application['company_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($application['industry']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $application['status']; ?>">
                                <?php echo $application['status_display']; ?>
                            </span>
                        </div>

                        <div class="application-details">
                            <div class="detail-item">
                                <span class="detail-label">Department</span>
                                <span class="detail-value"><?php echo htmlspecialchars($application['department']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?php echo htmlspecialchars($application['location']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Type</span>
                                <span class="detail-value"><?php echo ucfirst($application['type']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Application Date</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($application['application_date'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Internship Period</span>
                                <span class="detail-value">
                                    <?php echo date('M d', strtotime($application['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($application['end_date'])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="application-actions">
                            <a href="internship_details.php?id=<?php echo $application['internship_id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-eye"></i>
                                View Details
                            </a>
                            <?php if ($application['status'] === 'accepted'): ?>
                                <button class="btn btn-primary btn-sm" disabled>
                                    <i class="fas fa-check-circle"></i>
                                    Congratulations!
                                </button>
                            <?php elseif ($application['status'] === 'rejected'): ?>
                                <a href="browse_internships.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search"></i>
                                    Find More Opportunities
                                </a>
                            <?php else: ?>
                                <span class="btn btn-secondary btn-sm" style="opacity: 0.7;">
                                    <i class="fas fa-clock"></i>
                                    Awaiting Response
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
