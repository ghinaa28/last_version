<?php
session_start();
include "connection.php";

// Check if user is logged in as instructor
if (!isset($_SESSION['instructor_id'])) {
    header("Location: login.php");
    exit();
}

$instructor_id = $_SESSION['instructor_id'];

// Get course ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_courses.php");
    exit();
}

$course_id = intval($_GET['id']);

// Get course information and verify ownership
$stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ? AND instructor_id = ?");
$stmt->bind_param("ii", $course_id, $instructor_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    header("Location: manage_courses.php");
    exit();
}

// Get course statistics
$stats_sql = "SELECT 
    COUNT(ce.enrollment_id) as total_enrollments,
    COUNT(CASE WHEN ce.status = 'enrolled' THEN 1 END) as active_enrollments,
    COUNT(CASE WHEN ce.status = 'completed' THEN 1 END) as completed_enrollments,
    AVG(cr.rating) as average_rating,
    COUNT(cr.review_id) as total_reviews
    FROM courses c
    LEFT JOIN course_enrollments ce ON c.course_id = ce.course_id
    LEFT JOIN course_reviews cr ON c.course_id = cr.course_id
    WHERE c.course_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get recent enrollments
$enrollments_sql = "SELECT 
    ce.*,
    s.first_name,
    s.last_name,
    s.email
    FROM course_enrollments ce
    JOIN students s ON ce.student_id = s.student_id
    WHERE ce.course_id = ?
    ORDER BY ce.enrollment_date DESC
    LIMIT 10";

$stmt = $conn->prepare($enrollments_sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$enrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent reviews
$reviews_sql = "SELECT 
    cr.*,
    s.first_name,
    s.last_name
    FROM course_reviews cr
    JOIN students s ON cr.student_id = s.student_id
    WHERE cr.course_id = ?
    ORDER BY cr.created_at DESC
    LIMIT 5";

$stmt = $conn->prepare($reviews_sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Details - Instructor Portal</title>
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

        .course-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .course-main {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .course-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .course-card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--line);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .course-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .course-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
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

        .course-description {
            color: var(--text-dark);
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .course-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            text-align: center;
        }

        .detail-label {
            font-size: 0.875rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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

        .enrollment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            margin-bottom: 0.5rem;
        }

        .enrollment-info {
            display: flex;
            flex-direction: column;
        }

        .enrollment-name {
            font-weight: 600;
            color: var(--ink);
        }

        .enrollment-email {
            font-size: 0.875rem;
            color: var(--muted);
        }

        .enrollment-date {
            font-size: 0.875rem;
            color: var(--muted);
        }

        .review-item {
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .review-name {
            font-weight: 600;
            color: var(--ink);
        }

        .review-rating {
            display: flex;
            gap: 2px;
        }

        .star {
            color: #d1d5db;
            font-size: 1rem;
        }

        .star.filled {
            color: #fbbf24;
        }

        .review-text {
            color: var(--text-dark);
            font-style: italic;
            margin-top: 0.5rem;
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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .course-grid {
                grid-template-columns: 1fr;
            }

            .course-header {
                flex-direction: column;
                gap: 1rem;
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
                <a href="instructor_dashboard.php">Instructor Portal</a>
                <i class="fas fa-chevron-right"></i>
                <a href="manage_courses.php">Manage Courses</a>
                <i class="fas fa-chevron-right"></i>
                <span>Course Details</span>
            </div>
            <h1 class="page-title"><?php echo htmlspecialchars($course['course_title']); ?></h1>
            <p class="page-subtitle">Course details and statistics</p>
        </div>

        <div class="course-grid">
            <div class="course-main">
                <div class="course-card">
                    <?php if ($course['is_featured']): ?>
                        <div class="featured-badge">Featured</div>
                    <?php endif; ?>
                    
                    <div class="course-header">
                        <div>
                            <div class="course-meta">
                                <span class="course-category"><?php echo ucfirst(str_replace('_', ' ', $course['course_category'])); ?></span>
                                <span class="course-level"><?php echo ucfirst($course['course_level']); ?></span>
                                <span class="status-badge status-<?php echo $course['status']; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="course-description">
                        <?php echo nl2br(htmlspecialchars($course['course_description'])); ?>
                    </div>

                    <div class="course-details">
                        <div class="detail-item">
                            <div class="detail-label">Duration</div>
                            <div class="detail-value"><?php echo htmlspecialchars($course['course_duration']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Price</div>
                            <div class="detail-value">
                                <?php if ($course['course_price'] > 0): ?>
                                    <?php echo $course['currency'] . ' ' . number_format($course['course_price'], 2); ?>
                                <?php else: ?>
                                    Free
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Type</div>
                            <div class="detail-value"><?php echo $course['is_online'] ? 'Online' : 'In-Person'; ?></div>
                        </div>
                        <?php if (!$course['is_online'] && $course['location']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Location</div>
                                <div class="detail-value"><?php echo htmlspecialchars($course['location']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($course['max_students']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Max Students</div>
                                <div class="detail-value"><?php echo $course['max_students']; ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($course['course_materials']): ?>
                        <div>
                            <h3 class="section-title">
                                <i class="fas fa-book"></i>
                                Course Materials
                            </h3>
                            <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-lg);">
                                <?php echo nl2br(htmlspecialchars($course['course_materials'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($course['prerequisites']): ?>
                        <div>
                            <h3 class="section-title">
                                <i class="fas fa-list-check"></i>
                                Prerequisites
                            </h3>
                            <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-lg);">
                                <?php echo nl2br(htmlspecialchars($course['prerequisites'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($course['learning_outcomes']): ?>
                        <div>
                            <h3 class="section-title">
                                <i class="fas fa-target"></i>
                                Learning Outcomes
                            </h3>
                            <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-lg);">
                                <?php echo nl2br(htmlspecialchars($course['learning_outcomes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($course['course_schedule']): ?>
                        <div>
                            <h3 class="section-title">
                                <i class="fas fa-calendar"></i>
                                Schedule
                            </h3>
                            <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-lg);">
                                <?php echo nl2br(htmlspecialchars($course['course_schedule'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="course-sidebar">
                <div class="course-card">
                    <h3 class="section-title">
                        <i class="fas fa-chart-bar"></i>
                        Statistics
                    </h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total_enrollments']; ?></div>
                            <div class="stat-label">Total Enrollments</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['active_enrollments']; ?></div>
                            <div class="stat-label">Active Students</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['completed_enrollments']; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">
                                <?php echo $stats['average_rating'] ? number_format($stats['average_rating'], 1) : 'N/A'; ?>
                            </div>
                            <div class="stat-label">Average Rating</div>
                        </div>
                    </div>
                </div>

                <div class="course-card">
                    <h3 class="section-title">
                        <i class="fas fa-users"></i>
                        Recent Enrollments
                    </h3>
                    <?php if (empty($enrollments)): ?>
                        <p style="color: var(--muted); text-align: center; padding: 2rem;">No enrollments yet</p>
                    <?php else: ?>
                        <?php foreach ($enrollments as $enrollment): ?>
                            <div class="enrollment-item">
                                <div class="enrollment-info">
                                    <div class="enrollment-name"><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></div>
                                    <div class="enrollment-email"><?php echo htmlspecialchars($enrollment['email']); ?></div>
                                </div>
                                <div class="enrollment-date"><?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($reviews)): ?>
                    <div class="course-card">
                        <h3 class="section-title">
                            <i class="fas fa-star"></i>
                            Recent Reviews
                        </h3>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="review-name"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if ($review['review_text']): ?>
                                    <div class="review-text">"<?php echo htmlspecialchars($review['review_text']); ?>"</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="course-card">
                    <a href="edit_course.php?id=<?php echo $course['course_id']; ?>" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                        <i class="fas fa-edit"></i>
                        Edit Course
                    </a>
                    <a href="manage_courses.php" class="btn btn-secondary" style="width: 100%;">
                        <i class="fas fa-arrow-left"></i>
                        Back to Courses
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
