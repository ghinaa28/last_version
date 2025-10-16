<?php
session_start();
require_once 'connection.php';

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];

// Get filter parameters
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build WHERE conditions
$where_conditions = ["ir.company_id = ?"];
$params = [$company_id];

if (!empty($status)) {
    $where_conditions[] = "ir.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where_conditions[] = "(ir.course_title LIKE ? OR ir.course_description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get instructor requests with application counts
$sql = "SELECT ir.*, 
        (SELECT COUNT(*) FROM instructor_applications ia WHERE ia.instructor_request_id = ir.instructor_request_id) as total_applications,
        (SELECT COUNT(*) FROM instructor_applications ia WHERE ia.instructor_request_id = ir.instructor_request_id AND ia.status = 'pending') as pending_applications,
        (SELECT COUNT(*) FROM instructor_applications ia WHERE ia.instructor_request_id = ir.instructor_request_id AND ia.status = 'accepted') as accepted_applications
        FROM instructor_requests ir 
        WHERE $where_clause 
        ORDER BY ir.created_at DESC";

$stmt = $conn->prepare($sql);
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Instructor Requests - Company Portal</title>
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
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
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

        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
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
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--border-focus);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .requests-grid {
            display: grid;
            gap: 1.5rem;
        }

        .request-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .request-card:hover {
            box-shadow: var(--shadow-md);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .request-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }

        .request-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .meta-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-closed {
            background: rgba(107, 114, 128, 0.1);
            color: var(--text-light);
        }

        .badge-filled {
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
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

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .request-description {
            color: var(--text-light);
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .detail-value {
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .compensation {
            font-size: 1rem;
            font-weight: 700;
            color: var(--success);
        }

        .request-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
        }

        .application-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .request-actions {
            display: flex;
            gap: 0.5rem;
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--muted);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .filter-row {
                flex-direction: column;
            }

            .request-header {
                flex-direction: column;
                gap: 1rem;
            }

            .request-details {
                grid-template-columns: 1fr;
            }

            .request-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .request-actions {
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
                <span>Manage Instructor Requests</span>
            </div>
            <h1 class="page-title">Manage Instructor Requests</h1>
            <p class="page-subtitle">View and manage your instructor opportunity posts and applications.</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <?php
            $total_requests = count($requests);
            $active_requests = count(array_filter($requests, function($r) { return $r['status'] === 'active'; }));
            $total_applications = array_sum(array_column($requests, 'total_applications'));
            $pending_applications = array_sum(array_column($requests, 'pending_applications'));
            ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_requests; ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_requests; ?></div>
                <div class="stat-label">Active Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_applications; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_applications; ?></div>
                <div class="stat-label">Pending Applications</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="manage_instructor_requests.php">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search course titles..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            <option value="filled" <?php echo $status === 'filled' ? 'selected' : ''; ?>>Filled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Apply Filters
                        </button>
                    </div>
                    <div class="filter-group">
                        <a href="manage_instructor_requests.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quick Actions -->
        <div style="margin-bottom: 2rem;">
            <a href="post_instructor_request.php" class="btn btn-success">
                <i class="fas fa-plus"></i>
                Post New Instructor Request
            </a>
        </div>

        <!-- Results -->
        <div class="requests-grid">
            <?php if (empty($requests)): ?>
                <div class="no-results">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>No instructor requests found</h3>
                    <p>You haven't posted any instructor requests yet, or no requests match your current filters.</p>
                    <a href="post_instructor_request.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i>
                        Post Your First Request
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <div>
                                <h2 class="request-title"><?php echo htmlspecialchars($request['course_title']); ?></h2>
                                <div class="request-meta">
                                    <span class="meta-badge badge-<?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span>
                                    <span class="meta-badge badge-primary"><?php echo ucfirst($request['course_type']); ?></span>
                                    <span class="meta-badge badge-info"><?php echo ucfirst($request['experience_level']); ?></span>
                                    <?php if ($request['is_online']): ?>
                                        <span class="meta-badge badge-success">Online</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <p class="request-description"><?php echo htmlspecialchars($request['course_description']); ?></p>

                        <div class="request-details">
                            <div class="detail-item">
                                <span class="detail-label">Duration</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['course_duration']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['location']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Compensation</span>
                                <span class="detail-value compensation">
                                    $<?php echo number_format($request['compensation_amount'], 2); ?>
                                    <?php if ($request['compensation_type'] === 'hourly'): ?>/hour<?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Deadline</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($request['application_deadline'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Posted</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="request-footer">
                            <div class="application-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users" style="color: var(--info);"></i>
                                    <span><?php echo $request['total_applications']; ?> Total</span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-clock" style="color: var(--warning);"></i>
                                    <span><?php echo $request['pending_applications']; ?> Pending</span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-check" style="color: var(--success);"></i>
                                    <span><?php echo $request['accepted_applications']; ?> Accepted</span>
                                </div>
                            </div>
                            <div class="request-actions">
                                <a href="view_instructor_applications.php?id=<?php echo $request['instructor_request_id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                    View Applications
                                </a>
                                <?php if ($request['status'] === 'active'): ?>
                                    <a href="edit_instructor_request.php?id=<?php echo $request['instructor_request_id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </a>
                                    <a href="close_instructor_request.php?id=<?php echo $request['instructor_request_id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Are you sure you want to close this request?')">
                                        <i class="fas fa-times"></i>
                                        Close
                                    </a>
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
