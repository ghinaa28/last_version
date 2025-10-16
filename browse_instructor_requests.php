<?php
session_start();
require_once 'connection.php';

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Get instructor department
$instructor_stmt = $conn->prepare("SELECT department FROM instructors WHERE instructor_id = ?");
$instructor_stmt->bind_param("i", $instructor_id);
$instructor_stmt->execute();
$instructor_result = $instructor_stmt->get_result();
$instructor = $instructor_result->fetch_assoc();
$instructor_department = $instructor['department'] ?? '';

// Check if user wants to see all opportunities
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// Define relevant areas of expertise for engineering department
$engineering_expertise = [
    'computer_science', 'software_engineering', 'information_technology', 'data_science',
    'artificial_intelligence', 'cybersecurity', 'mechanical_engineering', 'electrical_engineering',
    'civil_engineering', 'chemical_engineering', 'biomedical_engineering', 'aerospace_engineering'
];
$relevant_expertise = [];

// If instructor is from engineering department and not showing all, filter by engineering-related expertise
if (!empty($instructor_department) && !$show_all) {
    $department_lower = strtolower(trim($instructor_department));
    if (strpos($department_lower, 'engineer') !== false || strpos($department_lower, 'engineering') !== false) {
        $relevant_expertise = $engineering_expertise;
    }
}

// Function to check for scheduling conflicts
function checkSchedulingConflict($instructor_id, $new_working_days, $conn) {
    // Get instructor's existing applications with working days
    $conflict_sql = "SELECT ir.working_days, ir.course_title 
                     FROM instructor_requests ir 
                     JOIN instructor_applications ia ON ir.instructor_request_id = ia.instructor_request_id 
                     WHERE ia.instructor_id = ? AND ia.status IN ('pending', 'accepted')";
    
    $conflict_stmt = $conn->prepare($conflict_sql);
    $conflict_stmt->bind_param("i", $instructor_id);
    $conflict_stmt->execute();
    $conflict_result = $conflict_stmt->get_result();
    
    $conflicts = [];
    $new_days = explode(',', $new_working_days);
    
    while ($row = $conflict_result->fetch_assoc()) {
        if (!empty($row['working_days'])) {
            $existing_days = explode(',', $row['working_days']);
            $common_days = array_intersect($new_days, $existing_days);
            
            if (!empty($common_days)) {
                $conflicts[] = [
                    'course_title' => $row['course_title'],
                    'conflicting_days' => $common_days
                ];
            }
        }
    }
    
    return $conflicts;
}

// Get filter parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$course_type = isset($_GET['course_type']) ? $conn->real_escape_string($_GET['course_type']) : '';
$location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';
$experience_level = isset($_GET['experience_level']) ? $conn->real_escape_string($_GET['experience_level']) : '';
$compensation_type = isset($_GET['compensation_type']) ? $conn->real_escape_string($_GET['compensation_type']) : '';

// Build WHERE conditions
$where_conditions = ["ir.status = 'active'", "ir.application_deadline >= CURDATE()"];
$params = [];

// Add area of expertise filtering for engineering
if (!empty($relevant_expertise)) {
    $placeholders = str_repeat('?,', count($relevant_expertise) - 1) . '?';
    $where_conditions[] = "ir.area_of_expertise IN ($placeholders)";
    $params = array_merge($params, $relevant_expertise);
}

if (!empty($search)) {
    $where_conditions[] = "(ir.course_title LIKE ? OR ir.course_description LIKE ? OR ir.skills_required LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($course_type)) {
    $where_conditions[] = "ir.course_type = ?";
    $params[] = $course_type;
}

if (!empty($location)) {
    $where_conditions[] = "(ir.location LIKE ? OR ir.is_online = 1)";
    $params[] = "%$location%";
}

if (!empty($experience_level)) {
    $where_conditions[] = "ir.experience_level = ?";
    $params[] = $experience_level;
}

if (!empty($compensation_type)) {
    $where_conditions[] = "ir.compensation_type = ?";
    $params[] = $compensation_type;
}

$where_clause = implode(' AND ', $where_conditions);

// Get instructor requests with company information and check if instructor has applied
$sql = "SELECT ir.*, c.company_name, 
        (SELECT COUNT(*) FROM instructor_applications ia WHERE ia.instructor_request_id = ir.instructor_request_id) as application_count,
        (SELECT COUNT(*) FROM instructor_applications ia WHERE ia.instructor_request_id = ir.instructor_request_id AND ia.instructor_id = ?) as has_applied
        FROM instructor_requests ir 
        JOIN companies c ON ir.company_id = c.company_id 
        WHERE $where_clause 
        ORDER BY ir.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = 'i' . str_repeat('s', count($params));
    $stmt->bind_param($types, $instructor_id, ...$params);
} else {
    $stmt->bind_param('i', $instructor_id);
}
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);

// Check for scheduling conflicts for each request
foreach ($requests as &$request) {
    if (!empty($request['working_days'])) {
        $conflicts = checkSchedulingConflict($instructor_id, $request['working_days'], $conn);
        $request['scheduling_conflicts'] = $conflicts;
        $request['has_conflicts'] = !empty($conflicts);
    } else {
        $request['scheduling_conflicts'] = [];
        $request['has_conflicts'] = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Instructor Opportunities - Instructor Portal</title>
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

        .department-notice {
            background: rgba(14, 165, 168, 0.1);
            border: 1px solid rgba(14, 165, 168, 0.3);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        .department-notice i {
            color: var(--brand);
            font-size: 1.1rem;
        }

        .department-notice span {
            color: var(--text-dark);
            flex: 1;
        }

        .show-all-link {
            color: var(--brand);
            text-decoration: none;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            background: rgba(14, 165, 168, 0.1);
            transition: var(--transition);
        }

        .show-all-link:hover {
            background: var(--brand);
            color: white;
        }

        .conflict-warning {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: var(--radius-md);
            padding: 0.75rem;
            margin: 0.5rem 0;
            font-size: 0.85rem;
        }

        .conflict-warning i {
            color: var(--error);
            margin-right: 0.5rem;
        }

        .conflict-warning .conflict-title {
            font-weight: 600;
            color: var(--error);
            margin-bottom: 0.25rem;
        }

        .conflict-warning .conflict-details {
            color: var(--text-dark);
            font-size: 0.8rem;
        }

        .conflict-warning .conflict-days {
            color: var(--error);
            font-weight: 600;
        }

        .btn-disabled {
            background: var(--muted);
            color: var(--text-light);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-disabled:hover {
            background: var(--muted);
            color: var(--text-light);
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

        .btn-outline {
            background: transparent;
            color: var(--brand);
            border: 2px solid var(--brand);
        }

        .btn-outline:hover {
            background: var(--brand);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:disabled {
            opacity: 0.7;
            cursor: not-allowed;
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
            transform: translateY(-2px);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .company-logo {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-md);
            object-fit: cover;
        }

        .company-details h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .company-details p {
            font-size: 0.9rem;
            color: var(--text-light);
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

        .request-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.75rem;
        }

        .request-description {
            color: var(--text-light);
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
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

        .request-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }

        .application-count {
            font-size: 0.9rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .deadline {
            font-size: 0.9rem;
            color: var(--warning);
            font-weight: 600;
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

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
                align-items: stretch;
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

            .department-notice {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .show-all-link {
                align-self: flex-start;
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
                <span>Browse Opportunities</span>
            </div>
            <h1 class="page-title">Browse Instructor Opportunities</h1>
            <p class="page-subtitle">Find teaching opportunities that match your skills and expertise.</p>
            <?php if (!empty($instructor_department) && strpos(strtolower($instructor_department), 'engineer') !== false): ?>
                <div class="department-notice">
                    <i class="fas fa-info-circle"></i>
                    <?php if ($show_all): ?>
                        <span>Showing <strong>all opportunities</strong> (expertise filtering disabled)</span>
                        <a href="?" class="show-all-link">Show engineering-related opportunities</a>
                    <?php else: ?>
                        <span>Showing opportunities relevant to your expertise: <strong><?php echo htmlspecialchars($instructor_department); ?></strong></span>
                        <a href="?show_all=1" class="show-all-link">Show all opportunities</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="browse_instructor_requests_fixed.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search courses, skills..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Course Type</label>
                        <select name="course_type" class="filter-select">
                            <option value="">All Types</option>
                            <option value="technical" <?php echo $course_type === 'technical' ? 'selected' : ''; ?>>Technical</option>
                            <option value="business" <?php echo $course_type === 'business' ? 'selected' : ''; ?>>Business</option>
                            <option value="language" <?php echo $course_type === 'language' ? 'selected' : ''; ?>>Language</option>
                            <option value="soft_skills" <?php echo $course_type === 'soft_skills' ? 'selected' : ''; ?>>Soft Skills</option>
                            <option value="certification" <?php echo $course_type === 'certification' ? 'selected' : ''; ?>>Certification</option>
                            <option value="workshop" <?php echo $course_type === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                            <option value="seminar" <?php echo $course_type === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                            <option value="other" <?php echo $course_type === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Location</label>
                        <input type="text" name="location" class="filter-input" placeholder="City, state..." value="<?php echo htmlspecialchars($location); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Experience Level</label>
                        <select name="experience_level" class="filter-select">
                            <option value="">All Levels</option>
                            <option value="beginner" <?php echo $experience_level === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                            <option value="intermediate" <?php echo $experience_level === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="advanced" <?php echo $experience_level === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                            <option value="expert" <?php echo $experience_level === 'expert' ? 'selected' : ''; ?>>Expert</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Compensation Type</label>
                        <select name="compensation_type" class="filter-select">
                            <option value="">All Types</option>
                            <option value="hourly" <?php echo $compensation_type === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                            <option value="salary" <?php echo $compensation_type === 'salary' ? 'selected' : ''; ?>>Salary</option>
                            <option value="project" <?php echo $compensation_type === 'project' ? 'selected' : ''; ?>>Project</option>
                            <option value="negotiable" <?php echo $compensation_type === 'negotiable' ? 'selected' : ''; ?>>Negotiable</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <a href="browse_instructor_requests_fixed.php" class="btn btn-secondary">Clear Filters</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Results -->
        <div class="requests-grid">
            <?php if (count($requests) === 0): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No opportunities found</h3>
                    <p>Try adjusting your search criteria or check back later for new opportunities.</p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <div class="company-info">
                                <div class="company-logo" style="background: var(--brand); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                    <?php echo strtoupper(substr($request['company_name'], 0, 2)); ?>
                                </div>
                                <div class="company-details">
                                    <h3><?php echo htmlspecialchars($request['company_name']); ?></h3>
                                    <p><?php echo htmlspecialchars($request['location']); ?></p>
                                </div>
                            </div>
                            <div class="request-meta">
                                <span class="meta-badge badge-primary"><?php echo ucfirst($request['course_type']); ?></span>
                                <span class="meta-badge badge-info"><?php echo ucfirst($request['experience_level']); ?></span>
                                <?php if ($request['is_online']): ?>
                                    <span class="meta-badge badge-success">Online</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h2 class="request-title"><?php echo htmlspecialchars($request['course_title']); ?></h2>
                        <p class="request-description"><?php echo htmlspecialchars($request['course_description']); ?></p>

                        <div class="request-details">
                            <div class="detail-item">
                                <span class="detail-label">Duration</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['course_duration']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Compensation</span>
                                <span class="detail-value compensation">
                                    $<?php echo number_format($request['compensation_amount'], 2); ?>
                                    <?php if ($request['compensation_type'] === 'hourly'): ?>/hour<?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Compensation Type</span>
                                <span class="detail-value"><?php echo ucfirst($request['compensation_type']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Deadline</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($request['application_deadline'])); ?></span>
                            </div>
                        </div>

                        <?php if ($request['has_conflicts']): ?>
                            <div class="conflict-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div class="conflict-title">Scheduling Conflict Detected</div>
                                <div class="conflict-details">
                                    This course conflicts with your existing applications on:
                                    <?php foreach ($request['scheduling_conflicts'] as $conflict): ?>
                                        <span class="conflict-days"><?php echo implode(', ', array_map('ucfirst', $conflict['conflicting_days'])); ?></span>
                                        (<?php echo htmlspecialchars($conflict['course_title']); ?>)
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="request-footer">
                            <div class="application-count">
                                <i class="fas fa-users"></i>
                                <?php echo $request['application_count']; ?> application<?php echo $request['application_count'] != 1 ? 's' : ''; ?>
                            </div>
                            <div class="deadline">
                                <i class="fas fa-clock"></i>
                                <?php 
                                $days_left = (strtotime($request['application_deadline']) - time()) / (60 * 60 * 24);
                                if ($days_left > 0) {
                                    echo ceil($days_left) . ' days left';
                                } else {
                                    echo 'Deadline passed';
                                }
                                ?>
                            </div>
                            <?php if ($request['has_applied'] > 0): ?>
                                <button class="btn btn-warning" disabled>
                                    <i class="fas fa-check-circle"></i>
                                    Already Applied
                                </button>
                            <?php elseif (strtotime($request['application_deadline']) < time()): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-calendar-times"></i>
                                    Application Closed
                                </button>
                            <?php elseif ($request['has_conflicts']): ?>
                                <button class="btn btn-disabled" disabled>
                                    <i class="fas fa-ban"></i>
                                    Scheduling Conflict
                                </button>
                            <?php else: ?>
                                <a href="apply_instructor_request.php?id=<?php echo $request['instructor_request_id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-paper-plane"></i>
                                    Apply Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
