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

// Handle course deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $course_id = intval($_GET['delete']);
    
    // Verify the course belongs to this instructor
    $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND instructor_id = ?");
    $stmt->bind_param("ii", $course_id, $instructor_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    
    if ($course) {
        // Delete course (this will also delete enrollments and reviews due to foreign key constraints)
        $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->bind_param("i", $course_id);
        if ($stmt->execute()) {
            $success_message = "Course deleted successfully!";
        } else {
            $error_message = "Error deleting course: " . $conn->error;
        }
    } else {
        $error_message = "Course not found or you don't have permission to delete it.";
    }
}

// Get instructor's courses with enrollment statistics
$courses_sql = "SELECT 
    c.*,
    COUNT(ce.enrollment_id) as total_enrollments,
    COUNT(CASE WHEN ce.status = 'enrolled' THEN 1 END) as active_enrollments,
    COUNT(CASE WHEN ce.status = 'completed' THEN 1 END) as completed_enrollments,
    AVG(cr.rating) as average_rating,
    COUNT(cr.review_id) as total_reviews
    FROM courses c
    LEFT JOIN course_enrollments ce ON c.course_id = ce.course_id
    LEFT JOIN course_reviews cr ON c.course_id = cr.course_id
    WHERE c.instructor_id = ?
    GROUP BY c.course_id
    ORDER BY c.created_at DESC";

$stmt = $conn->prepare($courses_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$success_message = isset($success_message) ? $success_message : "";
$error_message = isset($error_message) ? $error_message : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Instructor Portal</title>
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

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
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

        .btn-danger {
            background: var(--error);
            color: var(--text-white);
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-weight: 500;
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

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .course-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
            transition: var(--transition);
            position: relative;
        }

        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .course-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .course-category {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(14, 165, 168, 0.1);
            color: var(--brand);
            border: 1px solid rgba(14, 165, 168, 0.3);
        }

        .course-level {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
            margin-left: 0.5rem;
        }

        .course-description {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .course-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--brand);
            margin-bottom: 0.25rem;
        }

        .stat-text {
            font-size: 0.75rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .course-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--muted);
        }

        .course-price {
            font-weight: 700;
            color: var(--brand);
            font-size: 1.1rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-published {
            background: rgba(74, 222, 128, 0.1);
            color: #059669;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .status-draft {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .status-archived {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .featured-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .courses-grid {
                grid-template-columns: 1fr;
            }

            .header-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <span>Manage Courses</span>
            </div>
            <h1 class="page-title">Manage Courses</h1>
            <p class="page-subtitle">Create, edit, and manage your courses</p>
            
            <div class="header-actions">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($courses); ?></div>
                        <div class="stat-label">Total Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($courses, function($c) { return $c['status'] == 'published'; })); ?></div>
                        <div class="stat-label">Published</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo array_sum(array_column($courses, 'total_enrollments')); ?></div>
                        <div class="stat-label">Total Enrollments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo array_sum(array_column($courses, 'completed_enrollments')); ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                
                <a href="add_course.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add New Course
                </a>
            </div>
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

        <?php if (empty($courses)): ?>
            <div class="empty-state">
                <div class="icon">📚</div>
                <h3>No Courses Yet</h3>
                <p>You haven't created any courses yet. Start by adding your first course to begin teaching students.</p>
                <a href="add_course.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create Your First Course
                </a>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <?php if ($course['is_featured']): ?>
                            <div class="featured-badge">Featured</div>
                        <?php endif; ?>
                        
                        <div class="course-header">
                            <div>
                                <h3 class="course-title"><?php echo htmlspecialchars($course['course_title']); ?></h3>
                                <div>
                                    <span class="course-category"><?php echo ucfirst(str_replace('_', ' ', $course['course_category'])); ?></span>
                                    <span class="course-level"><?php echo ucfirst($course['course_level']); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="course-description">
                            <?php echo htmlspecialchars(substr($course['course_description'], 0, 150)) . (strlen($course['course_description']) > 150 ? '...' : ''); ?>
                        </div>

                        <div class="course-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $course['total_enrollments']; ?></div>
                                <div class="stat-text">Enrollments</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $course['completed_enrollments']; ?></div>
                                <div class="stat-text">Completed</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $course['total_reviews']; ?></div>
                                <div class="stat-text">Reviews</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php echo $course['average_rating'] ? number_format($course['average_rating'], 1) : 'N/A'; ?>
                                </div>
                                <div class="stat-text">Rating</div>
                            </div>
                        </div>

                        <div class="course-meta">
                            <div class="course-price">
                                <?php if ($course['course_price'] > 0): ?>
                                    <?php echo $course['currency'] . ' ' . number_format($course['course_price'], 2); ?>
                                <?php else: ?>
                                    Free
                                <?php endif; ?>
                            </div>
                            <span class="status-badge status-<?php echo $course['status']; ?>">
                                <?php echo ucfirst($course['status']); ?>
                            </span>
                        </div>

                        <div class="course-actions">
                            <a href="edit_course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i>
                                Edit
                            </a>
                            <a href="course_details.php?id=<?php echo $course['course_id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-eye"></i>
                                View
                            </a>
                            <a href="manage_courses.php?delete=<?php echo $course['course_id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this course? This action cannot be undone.')">
                                <i class="fas fa-trash"></i>
                                Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
