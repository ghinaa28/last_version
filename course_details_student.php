<?php
session_start();
include "connection.php";

// Check if user is logged in as student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Get course ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: browse_courses.php");
    exit();
}

$course_id = intval($_GET['id']);

// Get course information
$stmt = $conn->prepare("SELECT 
    c.*,
    i.first_name as instructor_first_name,
    i.last_name as instructor_last_name,
    i.department,
    i.email as instructor_email,
    i.bio as instructor_bio,
    AVG(cr.rating) as average_rating,
    COUNT(cr.review_id) as total_reviews,
    COUNT(ce.enrollment_id) as total_enrollments,
    CASE WHEN ce_student.enrollment_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled
    FROM courses c
    JOIN instructors i ON c.instructor_id = i.instructor_id
    LEFT JOIN course_reviews cr ON c.course_id = cr.course_id
    LEFT JOIN course_enrollments ce ON c.course_id = ce.course_id
    LEFT JOIN course_enrollments ce_student ON c.course_id = ce_student.course_id AND ce_student.student_id = ?
    WHERE c.course_id = ? AND c.status = 'published'
    GROUP BY c.course_id");

$stmt->bind_param("ii", $student_id, $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    header("Location: browse_courses.php");
    exit();
}

// Get recent reviews
$reviews_sql = "SELECT 
    cr.*,
    s.first_name,
    s.last_name
    FROM course_reviews cr
    JOIN students s ON cr.student_id = s.student_id
    WHERE cr.course_id = ?
    ORDER BY cr.created_at DESC
    LIMIT 10";

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
    <title><?php echo htmlspecialchars($course['course_title']); ?> - Course Details</title>
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
            font-size: 2rem;
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

        .course-instructor {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
        }

        .instructor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-white);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .instructor-info h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 0.25rem;
        }

        .instructor-info p {
            color: var(--muted);
            font-size: 0.9rem;
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

        .btn-enrolled {
            background: var(--success);
            color: var(--text-white);
            cursor: not-allowed;
        }

        .enrollment-card {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: var(--text-white);
            padding: 2rem;
            border-radius: var(--radius-xl);
            text-align: center;
        }

        .enrollment-price {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .enrollment-details {
            margin-bottom: 2rem;
        }

        .enrollment-details p {
            margin-bottom: 0.5rem;
            opacity: 0.9;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="student_dashboard.php">Student Portal</a>
                <i class="fas fa-chevron-right"></i>
                <a href="browse_courses.php">Browse Courses</a>
                <i class="fas fa-chevron-right"></i>
                <span>Course Details</span>
            </div>
            <h1 class="page-title"><?php echo htmlspecialchars($course['course_title']); ?></h1>
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
                            </div>
                        </div>
                    </div>

                    <div class="course-instructor">
                        <div class="instructor-avatar">
                            <?php echo strtoupper(substr($course['instructor_first_name'], 0, 1) . substr($course['instructor_last_name'], 0, 1)); ?>
                        </div>
                        <div class="instructor-info">
                            <h4><?php echo htmlspecialchars($course['instructor_first_name'] . ' ' . $course['instructor_last_name']); ?></h4>
                            <p><?php echo htmlspecialchars($course['department']); ?></p>
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
                <div class="enrollment-card">
                    <div class="enrollment-price">
                        <?php if ($course['course_price'] > 0): ?>
                            <?php echo $course['currency'] . ' ' . number_format($course['course_price'], 2); ?>
                        <?php else: ?>
                            Free
                        <?php endif; ?>
                    </div>
                    <div class="enrollment-details">
                        <p><i class="fas fa-users"></i> <?php echo $course['total_enrollments']; ?> students enrolled</p>
                        <?php if ($course['average_rating']): ?>
                            <p><i class="fas fa-star"></i> <?php echo number_format($course['average_rating'], 1); ?> average rating</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($course['is_enrolled']): ?>
                        <button class="btn btn-enrolled" disabled>
                            <i class="fas fa-check"></i>
                            Already Enrolled
                        </button>
                    <?php else: ?>
                        <button class="btn btn-primary" onclick="enrollCourse(<?php echo $course['course_id']; ?>)">
                            <i class="fas fa-plus"></i>
                            Enroll Now
                        </button>
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
                    <a href="browse_courses.php" class="btn btn-secondary" style="width: 100%;">
                        <i class="fas fa-arrow-left"></i>
                        Back to Courses
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function enrollCourse(courseId) {
            if (confirm('Are you sure you want to enroll in this course?')) {
                // Create a form to submit the enrollment
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'enroll_course.php';
                
                const courseIdInput = document.createElement('input');
                courseIdInput.type = 'hidden';
                courseIdInput.name = 'course_id';
                courseIdInput.value = courseId;
                
                form.appendChild(courseIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
