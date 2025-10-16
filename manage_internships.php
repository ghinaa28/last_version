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

// Get filter parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$department_filter = isset($_GET['department']) ? $conn->real_escape_string($_GET['department']) : '';

// Build query
$where_conditions = ["i.company_id = ?"];
$params = [$company_id];
$param_types = "i";

if (!empty($search)) {
    $where_conditions[] = "(i.title LIKE ? OR i.description LIKE ? OR i.department LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($department_filter)) {
    $where_conditions[] = "i.department LIKE ?";
    $params[] = "%$department_filter%";
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get internships
$internships_sql = "SELECT 
    i.*,
    COUNT(ia.application_id) as total_applications,
    COUNT(CASE WHEN ia.status = 'pending' THEN 1 END) as pending_applications,
    COUNT(CASE WHEN ia.status = 'accepted' THEN 1 END) as accepted_applications,
    COUNT(CASE WHEN ia.status = 'rejected' THEN 1 END) as rejected_applications
    FROM internships i
    LEFT JOIN internship_applications ia ON i.internship_id = ia.internship_id
    WHERE $where_clause
    GROUP BY i.internship_id
    ORDER BY i.created_at DESC";

$stmt = $conn->prepare($internships_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$internships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique departments for filter dropdown
$departments_sql = "SELECT DISTINCT department FROM internships WHERE company_id = ? AND department IS NOT NULL ORDER BY department";
$departments_stmt = $conn->prepare($departments_sql);
$departments_stmt->bind_param("i", $company_id);
$departments_stmt->execute();
$departments = $departments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Internships - Company Portal</title>
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

        .btn-warning {
            background: var(--warning);
            color: var(--text-white);
        }

        .btn-danger {
            background: var(--error);
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

        .internships-grid {
            display: grid;
            gap: 1.5rem;
        }

        .internship-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .internship-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .poster-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--bg-secondary);
        }

        .poster-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--bg-secondary), var(--line));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 1.2rem;
        }

        .card-content {
            padding: 1.5rem;
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

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
        }

        .status-inactive {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .status-expired {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
        }

        .card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--muted);
        }

        .detail-item i {
            color: var(--brand);
            width: 16px;
        }

        .card-description {
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .application-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
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

        .card-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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

            .results-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .card-header {
                flex-direction: column;
                gap: 1rem;
            }

            .card-details {
                grid-template-columns: 1fr;
            }

            .application-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .card-actions {
                flex-direction: column;
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
                <span>Manage Internships</span>
            </div>
            <h1 class="page-title">Manage Internships</h1>
            <p class="page-subtitle">View and manage your posted internship opportunities</p>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" id="filtersForm">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-input" placeholder="Search internships..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $department_filter === $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                    <a href="manage_internships.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Results -->
        <div class="results-header">
            <div class="results-count">
                <?php echo count($internships); ?> internship<?php echo count($internships) !== 1 ? 's' : ''; ?> found
            </div>
            <a href="post_internship.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Post New Internship
            </a>
        </div>

        <!-- Internships Grid -->
        <?php if (empty($internships)): ?>
            <div class="empty-state">
                <i class="fas fa-briefcase"></i>
                <h3>No Internships Found</h3>
                <p>You haven't posted any internships yet, or no internships match your current filters.</p>
                <a href="post_internship.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Post Your First Internship
                </a>
            </div>
        <?php else: ?>
            <div class="internships-grid">
                <?php foreach ($internships as $internship): ?>
                    <div class="internship-card">
                        <?php if ($internship['poster_path']): ?>
                            <img src="<?php echo htmlspecialchars($internship['poster_path']); ?>" alt="Internship Poster" class="poster-image">
                        <?php else: ?>
                            <div class="poster-placeholder">
                                <i class="fas fa-image"></i>
                                <span>No poster available</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-content">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title"><?php echo htmlspecialchars($internship['title']); ?></h3>
                                    <div class="card-details">
                                        <div class="detail-item">
                                            <i class="fas fa-building"></i>
                                            <span><?php echo htmlspecialchars($internship['department']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($internship['location']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo htmlspecialchars($internship['type']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-calendar"></i>
                                            <span><?php echo date('M j, Y', strtotime($internship['start_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <?php
                                    $status_class = 'status-active';
                                    $status_text = 'Active';
                                    if ($internship['status'] === 'inactive') {
                                        $status_class = 'status-inactive';
                                        $status_text = 'Inactive';
                                    } elseif (strtotime($internship['application_deadline']) < time()) {
                                        $status_class = 'status-expired';
                                        $status_text = 'Expired';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </div>
                            </div>

                            <p class="card-description"><?php echo htmlspecialchars(substr($internship['description'], 0, 120)) . (strlen($internship['description']) > 120 ? '...' : ''); ?></p>

                            <div class="application-stats">
                                <div class="stat">
                                    <div class="stat-value"><?php echo $internship['total_applications']; ?></div>
                                    <div class="stat-label">Total</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?php echo $internship['pending_applications']; ?></div>
                                    <div class="stat-label">Pending</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?php echo $internship['accepted_applications']; ?></div>
                                    <div class="stat-label">Accepted</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?php echo $internship['rejected_applications']; ?></div>
                                    <div class="stat-label">Rejected</div>
                                </div>
                            </div>

                            <div class="card-actions">
                                <a href="internship_details.php?id=<?php echo $internship['internship_id']; ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                                <a href="manage_applications.php?internship=<?php echo $internship['internship_id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-users"></i>
                                    Manage Applications
                                </a>
                                <?php if ($internship['status'] === 'active'): ?>
                                    <a href="post_internship.php?edit=<?php echo $internship['internship_id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>


