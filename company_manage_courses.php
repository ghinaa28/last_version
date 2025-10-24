<?php
session_start();
include "connection.php";

// Check if user is logged in as company
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];

// Get company information
$stmt = $conn->prepare("SELECT * FROM companies WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle course actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $course_id = intval($_POST['course_id']);
    
    switch ($action) {
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM courses WHERE id = ? AND created_by_type = 'company' AND created_by_id = ?");
            $stmt->bind_param("ii", $course_id, $company_id);
            if ($stmt->execute()) {
                $success_message = "Course deleted successfully!";
            } else {
                $error_message = "Error deleting course: " . $conn->error;
            }
            break;
            
        case 'deactivate':
            $stmt = $conn->prepare("UPDATE courses SET status = 'inactive' WHERE id = ? AND created_by_type = 'company' AND created_by_id = ?");
            $stmt->bind_param("ii", $course_id, $company_id);
            if ($stmt->execute()) {
                $success_message = "Course deactivated successfully!";
            } else {
                $error_message = "Error deactivating course: " . $conn->error;
            }
            break;
            
        case 'activate':
            $stmt = $conn->prepare("UPDATE courses SET status = 'active' WHERE id = ? AND created_by_type = 'company' AND created_by_id = ?");
            $stmt->bind_param("ii", $course_id, $company_id);
            if ($stmt->execute()) {
                $success_message = "Course activated successfully!";
            } else {
                $error_message = "Error activating course: " . $conn->error;
            }
            break;
    }
}

// Get company's courses
$sql = "SELECT * FROM courses WHERE created_by_type = 'company' AND created_by_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_courses,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_courses,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_courses
    FROM courses 
    WHERE created_by_type = 'company' AND created_by_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Company Portal</title>
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
            font-size: 0.9rem;
        }

        .courses-table {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--line);
        }

        .table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--ink);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            color: var(--text-dark);
        }

        .course-title {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .course-description {
            color: var(--muted);
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .status-badge {
            display: inline-block;
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
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-inactive {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        .mode-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
            border: 1px solid rgba(14, 165, 168, 0.3);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            text-decoration: none;
            text-align: center;
            justify-content: center;
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
            border: 1px solid var(--border-light);
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
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-error {
            background: var(--error);
            color: var(--text-white);
        }

        .btn-error:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .no-courses {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--panel);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .no-courses-icon {
            font-size: 4rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .no-courses h3 {
            color: var(--ink);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .no-courses p {
            color: var(--muted);
            margin-bottom: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .alert-success {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            color: #dc2626;
            border: 1px solid rgba(248, 113, 113, 0.3);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .table {
                font-size: 0.9rem;
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
                <span>Manage Courses</span>
            </div>
            <h1 class="page-title">Manage Courses</h1>
            <p class="page-subtitle">View and manage all your company's training courses</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_courses']; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_courses']; ?></div>
                <div class="stat-label">Active Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['inactive_courses']; ?></div>
                <div class="stat-label">Inactive Courses</div>
            </div>
        </div>

        <?php if (!empty($courses)): ?>
            <div class="courses-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course Title</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                    <div class="course-description"><?php echo htmlspecialchars($course['description']); ?></div>
                                </td>
                                <td>
                                    <span class="mode-badge"><?php echo htmlspecialchars($course['mode']); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $course['status']; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="company_edit_course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </a>
                                        
                                        <?php if ($course['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to deactivate this course?')">
                                                    <i class="fas fa-pause"></i>
                                                    Deactivate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-play"></i>
                                                    Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" class="btn btn-error" onclick="return confirm('Are you sure you want to delete this course? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-courses">
                <div class="no-courses-icon">📚</div>
                <h3>No Courses Yet</h3>
                <p>You haven't created any courses yet. Start by creating your first training course.</p>
                <a href="company_add_course.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create Course
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
