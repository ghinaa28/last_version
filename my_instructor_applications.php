<?php
session_start();
require_once 'connection.php';

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Get filter parameters
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build WHERE conditions
$where_conditions = ["ia.instructor_id = ?"];
$params = [$instructor_id];

if (!empty($status)) {
    $where_conditions[] = "ia.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where_conditions[] = "(ir.course_title LIKE ? OR c.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get instructor applications with request and company details
$sql = "SELECT ia.*, ir.course_title, ir.course_description, ir.course_duration, 
        ir.location, ir.compensation_type, ir.compensation_amount, ir.application_deadline,
        ir.course_type, ir.experience_level, ir.is_online, ir.status as request_status,
        c.company_name, c.industry
        FROM instructor_applications ia
        JOIN instructor_requests ir ON ia.instructor_request_id = ir.instructor_request_id
        JOIN companies c ON ir.company_id = c.company_id
        WHERE $where_clause
        ORDER BY ia.applied_at DESC";

$stmt = $conn->prepare($sql);
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$applications = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_applications,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_applications,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications
    FROM instructor_applications WHERE instructor_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param('i', $instructor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - Instructor Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0ea5a8;
            --brand-2: #0891b2;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --ink: #1f2937;
            --text-dark: #374151;
            --text-light: #6b7280;
            --muted: #9ca3af;
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --border-light: #e5e7eb;
            --border-focus: var(--brand);
            --line: #d1d5db;
            --panel: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
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
            margin-bottom: 2rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: var(--text-light);
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
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--line);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-icon.total { color: var(--brand); }
        .stat-icon.pending { color: var(--warning); }
        .stat-icon.accepted { color: var(--success); }
        .stat-icon.rejected { color: var(--error); }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-light);
            font-weight: 600;
        }

        .filters {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--line);
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
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .filter-input, .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: var(--transition);
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14, 165, 168, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .btn-primary {
            background: var(--brand);
            color: white;
        }

        .btn-primary:hover {
            background: var(--brand-2);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-dark);
            border: 2px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--border-light);
        }

        .applications-grid {
            display: grid;
            gap: 1.5rem;
        }

        .application-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .application-card:hover {
            box-shadow: var(--shadow-md);
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .request-info h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .request-info p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-accepted {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }

        .application-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .detail-label {
            font-size: 0.9rem;
            color: var(--text-light);
            font-weight: 600;
        }

        .detail-value {
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .compensation {
            font-weight: 700;
            color: var(--success);
        }

        .meta-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .meta-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-primary {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
        }

        .badge-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .application-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }

        .applied-date {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .review-notes {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: var(--radius-lg);
            border-left: 4px solid var(--brand);
            margin-top: 1rem;
        }

        .review-notes h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .review-notes p {
            color: var(--text-light);
            line-height: 1.6;
        }

        .no-applications {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }

        .no-applications i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--muted);
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

            .application-header {
                flex-direction: column;
                gap: 1rem;
            }

            .application-details {
                grid-template-columns: 1fr;
            }

            .application-footer {
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
                <a href="instructor_dashboard.php">Instructor Portal</a>
                <i class="fas fa-chevron-right"></i>
                <span>My Applications</span>
            </div>
            <h1 class="page-title">My Applications</h1>
            <p class="page-subtitle">Track and manage your instructor opportunity applications.</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon accepted">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['accepted_applications']; ?></div>
                <div class="stat-label">Accepted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['rejected_applications']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="my_instructor_applications.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search courses, companies..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="accepted" <?php echo $status === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <a href="my_instructor_applications.php" class="btn btn-secondary">Clear Filters</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Applications -->
        <div class="applications-grid">
            <?php if (count($applications) === 0): ?>
                <div class="no-applications">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Applications Found</h3>
                    <p>You haven't applied for any instructor opportunities yet, or no applications match your current filters.</p>
                    <div style="margin-top: 1.5rem;">
                        <a href="browse_instructor_requests.php" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Browse Opportunities
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $application): ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div class="request-info">
                                <h3><?php echo htmlspecialchars($application['course_title']); ?></h3>
                                <p><?php echo htmlspecialchars($application['company_name']); ?> • <?php echo htmlspecialchars($application['industry']); ?></p>
                            </div>
                            <div class="status-badge status-<?php echo $application['status']; ?>">
                                <?php echo ucfirst($application['status']); ?>
                            </div>
                        </div>

                        <div class="meta-badges">
                            <span class="meta-badge badge-primary"><?php echo ucfirst($application['course_type']); ?></span>
                            <span class="meta-badge badge-info"><?php echo ucfirst($application['experience_level']); ?></span>
                            <?php if ($application['is_online']): ?>
                                <span class="meta-badge badge-success">Online</span>
                            <?php endif; ?>
                        </div>

                        <div class="application-details">
                            <div class="detail-item">
                                <span class="detail-label">Duration</span>
                                <span class="detail-value"><?php echo htmlspecialchars($application['course_duration']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?php echo htmlspecialchars($application['location']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Compensation</span>
                                <span class="detail-value compensation">
                                    $<?php echo number_format($application['compensation_amount'], 2); ?>
                                    <?php if ($application['compensation_type'] === 'hourly'): ?>/hour<?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Request Status</span>
                                <span class="detail-value"><?php echo ucfirst($application['request_status']); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($application['review_notes'])): ?>
                            <div class="review-notes">
                                <h4>Review Notes from Company</h4>
                                <p><?php echo nl2br(htmlspecialchars($application['review_notes'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="application-footer">
                            <div class="applied-date">
                                <i class="fas fa-calendar"></i>
                                Applied on <?php echo date('M j, Y', strtotime($application['applied_at'])); ?>
                                <?php if ($application['reviewed_at']): ?>
                                    • Reviewed on <?php echo date('M j, Y', strtotime($application['reviewed_at'])); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($application['status'] === 'pending'): ?>
                                    <span style="color: var(--warning); font-weight: 600;">
                                        <i class="fas fa-clock"></i>
                                        Under Review
                                    </span>
                                <?php elseif ($application['status'] === 'accepted'): ?>
                                    <span style="color: var(--success); font-weight: 600;">
                                        <i class="fas fa-check-circle"></i>
                                        Congratulations!
                                    </span>
                                <?php elseif ($application['status'] === 'rejected'): ?>
                                    <span style="color: var(--error); font-weight: 600;">
                                        <i class="fas fa-times-circle"></i>
                                        Not Selected
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

